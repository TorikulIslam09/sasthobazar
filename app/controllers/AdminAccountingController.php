<?php
declare(strict_types=1);

/**
 * Accounting: overview, profit & loss, manual income / expense entries and their categories, VAT settings.
 * The registers (commission ledger, seller payouts, COD reconciliation, refunds, VAT report) are report pages
 * served by AdminReportController under /admin/accounting/{key}.
 */
class AdminAccountingController extends Controller
{
    public const PAY_METHODS = ['cash', 'bank', 'bkash', 'nagad', 'rocket', 'card', 'other'];

    /* ---------------- overview ---------------- */

    public function index(): void
    {
        $month = Finance::profitLoss(date('Y-m-01'), date('Y-m-d'));
        $lastFrom = date('Y-m-01', strtotime('first day of last month'));
        $last = Finance::profitLoss($lastFrom, date('Y-m-t', strtotime($lastFrom)));
        $db = $this->db;
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $from = date('Y-m-01', strtotime("first day of -{$i} month"));
            $to = $i === 0 ? date('Y-m-d') : date('Y-m-t', strtotime($from));
            $pl = Finance::profitLoss($from, $to);
            $trend[] = ['label' => dt($from, 'M y'), 'income' => $pl['total_income'], 'net' => $pl['net_profit']];
        }
        $this->view('admin/accounting/index', [
            'title'  => __('accounting'),
            'month'  => $month,
            'last'   => $last,
            'trend'  => $trend,
            'stats'  => [
                'cod_held'      => (float) $db->fetchColumn("SELECT COALESCE(SUM(collected_amount), 0) FROM shipments WHERE cod_status = 'collected'"),
                'refunds_due'   => (float) $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM refunds WHERE status = 'approved'"),
                'payouts_open'  => (float) $db->fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM vendor_payouts WHERE status IN ('requested','approved')"),
                'seller_owed'   => (float) $db->fetchColumn('SELECT COALESCE(SUM(balance + pending_balance), 0) FROM vendor_wallets'),
            ],
            'recent' => $db->fetchAll(
                'SELECT e.*, c.name_en, c.name_bn FROM account_entries e JOIN account_categories c ON c.id = e.category_id
                  WHERE e.deleted_at IS NULL ORDER BY e.entry_date DESC, e.id DESC LIMIT 8'
            ),
        ], 'admin');
    }

    /* ---------------- profit & loss ---------------- */

    public function pnl(): void
    {
        $f = Reports::filters();
        $format = Helpers::str('format');
        if (in_array($format, Export::FORMATS, true)) {
            if (!can('accounting.export')) {
                Auth::forbidden();
            }
            $lang = $_SESSION['lang'] ?? null;
            if ($format === 'pdf') {
                $_SESSION['lang'] = 'en';
            }
            $report = Finance::profitLossReport(Finance::profitLoss($f['from'], $f['to']));
            if ($format === 'pdf') {
                if ($lang === null) {
                    unset($_SESSION['lang']);
                } else {
                    $_SESSION['lang'] = $lang;
                }
            }
            Audit::log('reports.export', 'reports', null, null, ['report' => 'pnl', 'format' => $format, 'from' => $f['from'], 'to' => $f['to']]);
            Export::download($report, $format, 'profit-loss-' . $f['from'] . '-to-' . $f['to']);
        }
        $this->view('admin/accounting/pnl', [
            'title' => __('pl_title'),
            'f'     => $f,
            'pl'    => Finance::profitLoss($f['from'], $f['to']),
        ], 'admin');
    }

    /* ---------------- income / expense entries ---------------- */

    public function entries(): void
    {
        $f = Reports::filters();
        $this->view('admin/accounting/entries', [
            'title'      => __('acc_entries'),
            'f'          => $f,
            'report'     => Reports::build('entries', $f),
            'categories' => $this->categories(),
        ], 'admin');
    }

    private function categories(bool $activeOnly = false): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM account_categories' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY type DESC, sort_order, name_en'
        );
    }

    public function createEntry(): void
    {
        $type = Helpers::str('type') === 'income' ? 'income' : 'expense';
        $this->entryForm(null, ['type' => $type, 'entry_date' => date('Y-m-d'), 'payment_method' => 'cash']);
    }

    public function editEntry(string $id): void
    {
        $e = $this->entry((int) $id);
        $e['amount'] = (string) ((float) $e['amount'] + 0);
        $this->entryForm($e, $e);
    }

    private function entry(int $id): array
    {
        $e = $this->db->fetch('SELECT * FROM account_entries WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if (!$e) {
            $this->notFound();
        }
        return $e;
    }

    private function entryForm(?array $entry, array $defaults): void
    {
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/accounting/entry_form', [
            'title'      => __($entry ? 'acc_edit_entry' : 'acc_add_entry'),
            'entry'      => $entry,
            'input'      => is_array($old) ? $old : $defaults,
            'errors'     => $_SESSION['_errors'] ?? [],
            'categories' => $this->categories(true),
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function storeEntry(): void
    {
        $data = $this->validatedEntry(null, 'admin/accounting/entries/create');
        $data['entry_no'] = $this->newEntryNo($data['type']);
        $data['created_by'] = Auth::id();
        $id = $this->db->insert('account_entries', $data);
        Audit::log('accounting.entry_create', 'account_entries', $id, null, $data);
        flash('success', __('acc_entry_saved', ['no' => $data['entry_no']]));
        redirect(!empty($_POST['add_another']) ? 'admin/accounting/entries/create?type=' . $data['type'] : 'admin/accounting/entries');
    }

    public function updateEntry(string $id): void
    {
        $e = $this->entry((int) $id);
        $data = $this->validatedEntry($e, 'admin/accounting/entries/' . (int) $id . '/edit');
        $data['updated_by'] = Auth::id();
        $this->db->update('account_entries', $data, 'id = :id', ['id' => (int) $id]);
        Audit::log('accounting.entry_update', 'account_entries', (int) $id, $e, $data);
        flash('success', __('acc_entry_saved', ['no' => $e['entry_no']]));
        redirect('admin/accounting/entries');
    }

    public function deleteEntry(string $id): void
    {
        $e = $this->entry((int) $id);
        $this->db->update('account_entries', ['deleted_at' => date('Y-m-d H:i:s'), 'updated_by' => Auth::id()], 'id = :id', ['id' => (int) $id]);
        Audit::log('accounting.entry_delete', 'account_entries', (int) $id, $e, null);
        flash('success', __('acc_entry_deleted'));
        redirect('admin/accounting/entries');
    }

    private function newEntryNo(string $type): string
    {
        $prefix = ($type === 'income' ? 'IN' : 'EX') . date('ym');
        $n = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM account_entries WHERE entry_no LIKE :p", ['p' => $prefix . '%']);
        do {
            $no = $prefix . '-' . str_pad((string) ++$n, 4, '0', STR_PAD_LEFT);
        } while ($this->db->fetchColumn('SELECT 1 FROM account_entries WHERE entry_no = :n', ['n' => $no]));
        return $no;
    }

    private function validatedEntry(?array $entry, string $formPath): array
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'type'           => 'required|in:income,expense',
            'category_id'    => 'required|integer',
            'amount'         => 'required|decimal|gte:0.01',
            'entry_date'     => 'required|date',
            'payment_method' => 'required|in:' . implode(',', self::PAY_METHODS),
            'reference'      => 'max:100',
            'description'    => 'max:500',
        ]);
        if (!isset($errors['category_id'])) {
            $cat = $this->db->fetch('SELECT type, is_active FROM account_categories WHERE id = :id', ['id' => (int) ($in['category_id'] ?? 0)]);
            if (!$cat || $cat['type'] !== ($in['type'] ?? '') || (!(int) $cat['is_active'] && (int) ($entry['category_id'] ?? 0) !== (int) $in['category_id'])) {
                $errors['category_id'] = __('acc_category_mismatch');
            }
        }
        if (!isset($errors['entry_date']) && ($in['entry_date'] ?? '') > date('Y-m-d', strtotime('+1 day'))) {
            $errors['entry_date'] = __('acc_future_date');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($formPath);
        }
        return [
            'type'           => $in['type'],
            'category_id'    => (int) $in['category_id'],
            'amount'         => $in['amount'],
            'entry_date'     => $in['entry_date'],
            'payment_method' => $in['payment_method'],
            'reference'      => ($in['reference'] ?? '') !== '' ? $in['reference'] : null,
            'description'    => ($in['description'] ?? '') !== '' ? $in['description'] : null,
        ];
    }

    /* ---------------- categories ---------------- */

    public function categoryList(): void
    {
        $this->view('admin/accounting/categories', [
            'title'      => __('acc_categories'),
            'categories' => $this->db->fetchAll(
                'SELECT c.*, (SELECT COUNT(*) FROM account_entries e WHERE e.category_id = c.id AND e.deleted_at IS NULL) AS used
                   FROM account_categories c ORDER BY c.type DESC, c.sort_order, c.name_en'
            ),
            'errors'     => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
    }

    public function saveCategory(): void
    {
        $in = $this->postData();
        $id = (int) ($in['id'] ?? 0);
        $errors = $this->validate($in, ['name_en' => 'required|max:100', 'name_bn' => 'max:100'] + ($id ? [] : ['type' => 'required|in:income,expense']));
        if ($errors) {
            flash('error', reset($errors));
            redirect('admin/accounting/categories');
        }
        if ($id) {
            $c = $this->db->fetch('SELECT * FROM account_categories WHERE id = :id', ['id' => $id]);
            if (!$c) {
                $this->notFound();
            }
            $data = ['name_en' => $in['name_en'], 'name_bn' => ($in['name_bn'] ?? '') !== '' ? $in['name_bn'] : null, 'is_active' => !empty($in['is_active']) ? 1 : 0];
            $this->db->update('account_categories', $data, 'id = :id', ['id' => $id]);
            Audit::log('accounting.category_update', 'account_categories', $id, $c, $data);
        } else {
            $slug = Helpers::slugify($in['name_en']);
            while ($this->db->fetchColumn('SELECT 1 FROM account_categories WHERE slug = :s', ['s' => $slug])) {
                $slug .= '-' . random_int(2, 99);
            }
            $data = ['type' => $in['type'], 'name_en' => $in['name_en'], 'name_bn' => ($in['name_bn'] ?? '') !== '' ? $in['name_bn'] : null,
                     'slug' => $slug, 'sort_order' => 100];
            $id = $this->db->insert('account_categories', $data);
            Audit::log('accounting.category_create', 'account_categories', $id, null, $data);
        }
        flash('success', __('acc_category_saved'));
        redirect('admin/accounting/categories');
    }

    public function deleteCategory(string $id): void
    {
        $c = $this->db->fetch('SELECT * FROM account_categories WHERE id = :id', ['id' => (int) $id]);
        if (!$c) {
            $this->notFound();
        }
        if ((int) $c['is_system'] || $this->db->fetchColumn('SELECT 1 FROM account_entries WHERE category_id = :id LIMIT 1', ['id' => (int) $id])) {
            $this->db->update('account_categories', ['is_active' => 0], 'id = :id', ['id' => (int) $id]);
            flash('warning', __('acc_category_deactivated'));
        } else {
            $this->db->query('DELETE FROM account_categories WHERE id = :id', ['id' => (int) $id]);
            flash('success', __('acc_category_deleted'));
        }
        Audit::log('accounting.category_delete', 'account_categories', (int) $id, $c, null);
        redirect('admin/accounting/categories');
    }

    /* ---------------- VAT / tax settings ---------------- */

    public function tax(): void
    {
        $old = $_SESSION['_old'] ?? null;
        $values = [
            'vat_enabled'   => Tax::enabled() ? '1' : '',
            'vat_rate'      => (string) (Tax::defaultRate() + 0),
            'vat_inclusive' => Tax::inclusive() ? '1' : '0',
            'vat_label'     => Tax::label(),
            'vat_reg_no'    => Tax::regNo(),
        ];
        $this->view('admin/accounting/tax', [
            'title'   => __('acc_tax_settings'),
            'input'   => is_array($old) ? $old : $values,
            'errors'  => $_SESSION['_errors'] ?? [],
            'special' => $this->db->fetchAll("SELECT id, name_en, sku, vat_percent FROM products WHERE deleted_at IS NULL AND vat_percent > 0 ORDER BY vat_percent DESC, name_en LIMIT 50"),
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function saveTax(): void
    {
        $in = $this->postData();
        $errors = $this->validate($in, [
            'vat_rate'      => 'required|decimal|lte:100',
            'vat_inclusive' => 'required|in:0,1',
            'vat_label'     => 'required|max:20',
            'vat_reg_no'    => 'max:30',
        ]);
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect('admin/accounting/tax');
        }
        $values = [
            'vat_enabled'   => !empty($in['vat_enabled']) ? '1' : '0',
            'vat_rate'      => number_format((float) $in['vat_rate'], 2, '.', ''),
            'vat_inclusive' => $in['vat_inclusive'],
            'vat_label'     => trim($in['vat_label']),
            'vat_reg_no'    => trim((string) ($in['vat_reg_no'] ?? '')),
        ];
        $old = [];
        foreach ($values as $k => $v) {
            $cur = $this->db->fetchColumn('SELECT `value` FROM settings WHERE `key` = :k', ['k' => $k]);
            if ($cur === false) {
                $this->db->insert('settings', ['group' => 'tax', 'key' => $k, 'value' => $v, 'type' => in_array($k, ['vat_enabled', 'vat_inclusive'], true) ? 'bool' : 'string', 'label' => $k, 'is_public' => 0]);
            } elseif ((string) $cur !== $v) {
                $this->db->update('settings', ['value' => $v], '`key` = :k', ['k' => $k]);
            }
            $old[$k] = $cur;
        }
        Helpers::clearSettingsCache();
        Audit::log('settings.update', 'settings', null, $old, $values);
        flash('success', __('acc_tax_saved'));
        redirect('admin/accounting/tax');
    }
}
