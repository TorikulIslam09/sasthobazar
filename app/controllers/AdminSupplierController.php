<?php
declare(strict_types=1);

/**
 * Suppliers: list (search, status, pagination), CRUD, CSV export.
 * A supplier used by purchase orders or batches can't be deleted - deactivate it instead.
 */
class AdminSupplierController extends Controller
{
    public function index(): void
    {
        $f = $this->filters();
        $p = new Paginator(Supplier::countFiltered($f), 25);
        $this->view('admin/suppliers/index', [
            'title'     => __('suppliers'),
            'suppliers' => Supplier::filtered($f, $p->perPage, $p->offset()),
            'paginator' => $p,
            'filters'   => $f,
        ], 'admin');
    }

    public function export(): void
    {
        $rows = Supplier::filtered($this->filters(), 100000, 0);
        Audit::log('suppliers.export', 'suppliers', null, null, ['rows' => count($rows)]);
        Helpers::csvDownload('suppliers-' . date('Ymd-His') . '.csv',
            ['id', 'name', 'contact_person', 'phone', 'email', 'address', 'drug_license_no', 'tin_no', 'payment_terms', 'active', 'purchase_orders', 'received_value', 'last_received'],
            array_map(static fn($s) => [
                $s['id'], $s['name'], $s['contact_person'], $s['phone'], $s['email'], $s['address'], $s['drug_license_no'], $s['tin_no'],
                $s['payment_terms'], $s['is_active'], $s['po_count'], $s['received_value'], $s['last_received'],
            ], $rows)
        );
    }

    public function create(): void
    {
        $this->renderForm(null, ['is_active' => '1'], []);
    }

    public function store(): void
    {
        $in = $this->input();
        $errors = $this->validateSupplier($in, 0);
        if ($errors) {
            $this->renderForm(null, $in, $errors);
            return;
        }
        Supplier::create($this->toRow($in) + ['created_by' => Auth::id()]);
        flash('success', __('supplier_saved', ['name' => $in['name']]));
        redirect('admin/suppliers');
    }

    public function edit(string $id): void
    {
        $s = $this->findSupplier((int) $id);
        $this->renderForm($s, $s, []);
    }

    public function update(string $id): void
    {
        $s = $this->findSupplier((int) $id);
        $in = $this->input();
        $errors = $this->validateSupplier($in, (int) $s['id']);
        if ($errors) {
            $this->renderForm($s, array_merge($s, $in), $errors);
            return;
        }
        Supplier::updateById((int) $s['id'], $this->toRow($in));
        flash('success', __('supplier_saved', ['name' => $in['name']]));
        redirect('admin/suppliers');
    }

    public function destroy(string $id): void
    {
        $s = $this->findSupplier((int) $id);
        $u = Supplier::usage((int) $s['id']);
        if ($u['pos'] > 0 || $u['batches'] > 0) {
            flash('error', __('supplier_in_use', ['name' => $s['name'], 'pos' => num($u['pos']), 'batches' => num($u['batches'])]));
        } else {
            Supplier::deleteById((int) $s['id']);
            flash('success', __('supplier_deleted', ['name' => $s['name']]));
        }
        redirect('admin/suppliers');
    }

    /* ---------------- helpers ---------------- */

    private function filters(): array
    {
        $st = Helpers::str('status');
        return ['q' => mb_substr(Helpers::str('q'), 0, 100), 'status' => in_array($st, ['active', 'inactive'], true) ? $st : ''];
    }

    private function findSupplier(int $id): array
    {
        $s = Supplier::find($id);
        if (!$s) {
            $this->notFound();
        }
        return $s;
    }

    private function input(): array
    {
        $in = $this->postData();
        $in['is_active'] = ($in['is_active'] ?? '') === '1' ? '1' : '0';
        return $in;
    }

    private function validateSupplier(array $in, int $id): array
    {
        $errors = $this->validate($in, [
            'name'            => 'required|max:150',
            'contact_person'  => 'max:150',
            'phone'           => 'phone',
            'email'           => 'email|max:191',
            'address'         => 'max:255',
            'drug_license_no' => 'max:100',
            'tin_no'          => 'max:50',
            'payment_terms'   => 'max:100',
            'notes'           => 'max:5000',
        ]);
        if (!isset($errors['name']) && Supplier::nameExists($in['name'], $id)) {
            $errors['name'] = __('supplier_name_taken');
        }
        return $errors;
    }

    private function toRow(array $in): array
    {
        $n = static fn(string $k) => ($in[$k] ?? '') !== '' ? $in[$k] : null;
        return [
            'name'            => $in['name'],
            'contact_person'  => $n('contact_person'),
            'phone'           => ($in['phone'] ?? '') !== '' ? Auth::normalizePhone($in['phone']) : null,
            'email'           => ($in['email'] ?? '') !== '' ? strtolower($in['email']) : null,
            'address'         => $n('address'),
            'drug_license_no' => $n('drug_license_no'),
            'tin_no'          => $n('tin_no'),
            'payment_terms'   => $n('payment_terms'),
            'notes'           => $n('notes'),
            'is_active'       => (int) $in['is_active'],
        ];
    }

    private function renderForm(?array $s, array $input, array $errors): void
    {
        $this->view('admin/suppliers/form', [
            'title'    => $s ? __('edit_supplier') : __('add_supplier'),
            'supplier' => $s,
            'input'    => $input,
            'errors'   => $errors,
            'usage'    => $s ? Supplier::usage((int) $s['id']) : null,
        ], 'admin');
    }
}
