<?php
declare(strict_types=1);

/**
 * Reports hub + every report page (Reports::LIST) with date range filters and CSV / Excel / PDF export.
 * Accounting registers (commission ledger, payouts, COD, refunds, VAT, income & expenses) use the same pages
 * under /admin/accounting/{key} and need the accounting permissions instead of the reports ones.
 */
class AdminReportController extends Controller
{
    public function index(): void
    {
        $this->view('admin/reports/index', ['title' => __('reports')], 'admin');
    }

    private function def(string $key): array
    {
        $def = Reports::LIST[$key] ?? null;
        if (!$def) {
            $this->notFound();
        }
        return $def;
    }

    private function guard(string $group, string $action): void
    {
        if (!can($group . '.' . $action)) {
            Auth::forbidden();
        }
    }

    public function show(string $key): void
    {
        [$group, $icon, $filters] = $this->def($key);
        $this->guard($group, 'view');
        $f = Reports::filters();
        $this->view('admin/reports/show', [
            'title'   => __('rp_' . $key),
            'key'     => $key,
            'group'   => $group,
            'icon'    => $icon,
            'filters' => $filters,
            'f'       => $f,
            'report'  => Reports::build($key, $f),
            'options' => $this->options($filters),
        ], 'admin');
    }

    public function export(string $key): void
    {
        [$group] = $this->def($key);
        $this->guard($group, 'export');
        $format = in_array(Helpers::str('format'), Export::FORMATS, true) ? Helpers::str('format') : 'csv';
        $f = Reports::filters();
        $lang = $_SESSION['lang'] ?? null;
        if ($format === 'pdf') {
            $_SESSION['lang'] = 'en'; // PDF core fonts are Latin only
        }
        try {
            $report = Reports::build($key, $f);
        } finally {
            if ($format === 'pdf') {
                if ($lang === null) {
                    unset($_SESSION['lang']);
                } else {
                    $_SESSION['lang'] = $lang;
                }
            }
        }
        Audit::log('reports.export', 'reports', null, null, ['report' => $key, 'format' => $format, 'from' => $f['from'], 'to' => $f['to']]);
        Export::download($report, $format, $key . '-' . $f['from'] . '-to-' . $f['to']);
    }

    public function exportEntries(): void
    {
        $this->export('entries');
    }

        /** Select options for the filters a report uses. */
    private function options(array $filters): array
    {
        $o = [];
        if (in_array('category', $filters, true)) {
            $o['categories'] = Category::tree();
        }
        if (array_intersect(['vendor', 'seller'], $filters)) {
            $o['vendors'] = Vendor::options();
        }
        if (in_array('courier', $filters, true)) {
            $o['couriers'] = $this->db->fetchAll('SELECT id, name FROM couriers ORDER BY name');
        }
        if (in_array('entry_category', $filters, true)) {
            $o['entryCategories'] = $this->db->fetchAll('SELECT id, type, name_en, name_bn FROM account_categories ORDER BY type, sort_order, name_en');
        }
        return $o;
    }
}
