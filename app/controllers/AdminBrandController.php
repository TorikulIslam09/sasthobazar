<?php
declare(strict_types=1);

/**
 * Brands / pharmaceutical companies: list (search, filters, pagination), CRUD, CSV export.
 * A brand that still has products cannot be deleted - reassign or delete the products first.
 */
class AdminBrandController extends Controller
{
    public function index(): void
    {
        $filters = $this->filters();
        $p = new Paginator(Brand::countFiltered($filters), 25);
        $this->view('admin/brands/index', [
            'title'     => __('brands'),
            'brands'    => Brand::filtered($filters, $p->perPage, $p->offset()),
            'paginator' => $p,
            'filters'   => $filters,
        ], 'admin');
    }

    public function export(): void
    {
        $filters = $this->filters();
        $rows = Brand::filtered($filters, 100000, 0);
        Audit::log('brands.export', 'brands', null, null, ['filters' => $filters, 'rows' => count($rows)]);
        Helpers::csvDownload('brands-' . date('Ymd-His') . '.csv',
            ['id', 'name', 'name_bn', 'slug', 'is_manufacturer', 'country', 'website', 'is_featured', 'is_active', 'products', 'created_at'],
            array_map(static fn($b) => [
                $b['id'], $b['name'], $b['name_bn'], $b['slug'], $b['is_manufacturer'], $b['country'], $b['website'],
                $b['is_featured'], $b['is_active'], $b['products_count'], $b['created_at'],
            ], $rows)
        );
    }

    public function create(): void
    {
        $this->renderForm(null, ['is_active' => '1', 'is_manufacturer' => '1', 'country' => 'Bangladesh'], []);
    }

    public function store(): void
    {
        $in = $this->input();
        [$errors, $logo] = $this->validateBrand($in, null);
        if ($errors) {
            $this->renderForm(null, $in, $errors);
            return;
        }
        $data = $this->toRow($in, 0);
        if ($logo) {
            $data['logo'] = Upload::storeImage($logo, 'brands');
        }
        Brand::create($data);
        flash('success', __('brand_saved'));
        redirect(($in['_after'] ?? '') === 'new' ? 'admin/brands/create' : 'admin/brands');
    }

    public function edit(string $id): void
    {
        $brand = $this->findBrand((int) $id);
        $this->renderForm($brand, $brand, []);
    }

    public function update(string $id): void
    {
        $brand = $this->findBrand((int) $id);
        $in = $this->input();
        [$errors, $logo] = $this->validateBrand($in, $brand);
        if ($errors) {
            $this->renderForm($brand, array_merge($brand, $in), $errors);
            return;
        }
        $data = $this->toRow($in, (int) $brand['id']);
        $oldLogo = $brand['logo'];
        if ($logo) {
            $data['logo'] = Upload::storeImage($logo, 'brands');
        } elseif (!empty($in['remove_logo'])) {
            $data['logo'] = null;
        }
        Brand::updateById((int) $brand['id'], $data);
        if (array_key_exists('logo', $data) && $oldLogo && $oldLogo !== $data['logo']) {
            Upload::delete($oldLogo);
        }
        flash('success', __('brand_saved'));
        redirect('admin/brands');
    }

    public function destroy(string $id): void
    {
        $brand = $this->findBrand((int) $id);
        $n = Brand::productsCount((int) $brand['id']);
        if ($n > 0) {
            flash('error', __('brand_has_products', ['name' => $brand['name'], 'n' => num($n)]));
            back();
        }
        // Soft-deleted products may still point here; detach them so the FK doesn't block / they don't dangle
        $this->db->update('products', ['brand_id' => null], 'brand_id = :b', ['b' => (int) $brand['id']]);
        Brand::deleteById((int) $brand['id']);
        Upload::delete($brand['logo']);
        flash('success', __('brand_deleted', ['name' => $brand['name']]));
        // back to the (filtered/paged) list, but never to the edit page of the brand that is gone
        str_contains($_SERVER['HTTP_REFERER'] ?? '', '/brands/' . $brand['id'] . '/') ? redirect('admin/brands') : back();
    }

    /* ---------------- helpers ---------------- */

    private function filters(): array
    {
        $type = Helpers::str('type');
        $status = Helpers::str('status');
        return [
            'q'      => mb_substr(Helpers::str('q'), 0, 100),
            'type'   => in_array($type, ['manufacturer', 'brand'], true) ? $type : '',
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : '',
        ];
    }

    private function findBrand(int $id): array
    {
        $brand = Brand::find($id);
        if (!$brand) {
            $this->notFound();
        }
        return $brand;
    }

    private function input(): array
    {
        $in = $this->postData();
        foreach (['is_manufacturer', 'is_featured', 'is_active', 'remove_logo'] as $cb) {
            $in[$cb] = isset($in[$cb]) && $in[$cb] === '1' ? '1' : '0';
        }
        $in['slug'] = isset($in['slug']) ? mb_strtolower($in['slug']) : '';
        return $in;
    }

    /** @return array{0: array, 1: ?array} */
    private function validateBrand(array $in, ?array $brand): array
    {
        $errors = $this->validate($in, [
            'name'             => 'required|max:150',
            'name_bn'          => 'max:150',
            'slug'             => 'max:180|slug',
            'country'          => 'max:80',
            'website'          => 'max:255|url',
            'description'      => 'max:5000',
            'meta_title'       => 'max:255',
            'meta_description' => 'max:500',
        ]);
        $selfId = (int) ($brand['id'] ?? 0);
        if (!isset($errors['name']) && Brand::nameExists($in['name'], $selfId)) {
            $errors['name'] = __('brand_name_taken');
        }
        if (!isset($errors['slug']) && ($in['slug'] ?? '') !== '' && Brand::slugExists($in['slug'], $selfId)) {
            $errors['slug'] = __('slug_taken');
        }

        $logo = null;
        $files = Upload::files('logo');
        if ($files) {
            if ($err = Upload::validateImage($files[0], 2 * 1024 * 1024)) {
                $errors['logo'] = $err;
            } else {
                $logo = $files[0];
            }
        }
        if ($errors && $logo) {
            $errors['logo'] ??= __('reselect_files');
        }
        return [$errors, $errors ? null : $logo];
    }

    private function toRow(array $in, int $selfId): array
    {
        return [
            'name'             => $in['name'],
            'name_bn'          => ($in['name_bn'] ?? '') ?: null,
            'slug'             => ($in['slug'] ?? '') !== '' ? $in['slug'] : Brand::uniqueSlug($in['name'], $selfId),
            'is_manufacturer'  => (int) $in['is_manufacturer'],
            'country'          => ($in['country'] ?? '') ?: null,
            'website'          => ($in['website'] ?? '') ?: null,
            'description'      => ($in['description'] ?? '') ?: null,
            'is_featured'      => (int) $in['is_featured'],
            'is_active'        => (int) $in['is_active'],
            'meta_title'       => ($in['meta_title'] ?? '') ?: null,
            'meta_description' => ($in['meta_description'] ?? '') ?: null,
        ];
    }

    private function renderForm(?array $brand, array $input, array $errors): void
    {
        $this->view('admin/brands/form', [
            'title'    => $brand ? __('edit_brand') : __('add_brand'),
            'brand'    => $brand,
            'input'    => $input,
            'errors'   => $errors,
            'products' => $brand ? Brand::productsCount((int) $brand['id']) : 0,
        ], 'admin');
    }
}
