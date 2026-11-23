<?php
declare(strict_types=1);

/**
 * Products: list (search, filters, sort, pagination), create/edit with variants + multiple images,
 * delete, CSV export, CSV import.
 *
 * Form field names use the business terms; they map to DB columns like this:
 *   price          -> products.mrp         (regular price / MRP, required)
 *   sale_price     -> products.price       (blank = no sale, price = MRP)
 *   purchase_price -> products.cost_price
 *
 * Status: only users with "products.approve" can set Active or Rejected. Others can save as
 * Draft / Pending review / Inactive (or keep the status the product already has).
 */
class AdminProductController extends Controller
{
    private const PER_PAGE = [20, 50, 100];

    /* ================= List / export ================= */

    public function index(): void
    {
        $filters = $this->filters();
        $p = new Paginator(Product::countFiltered($filters), $filters['per_page']);
        $this->view('admin/products/index', [
            'title'        => __('products'),
            'products'     => Product::filtered($filters, $p->perPage, $p->offset()),
            'paginator'    => $p,
            'filters'      => $filters,
            'categories'   => Category::tree(),
            'brands'       => Brand::options(),
            'vendors'      => Vendor::options(),
            'statusCounts' => Product::statusCounts(),
        ], 'admin');
    }

    public function export(): void
    {
        $filters = $this->filters();
        $rows = Product::filtered($filters, 50000, 0);
        Audit::log('products.export', 'products', null, null, ['filters' => array_filter($filters), 'rows' => count($rows)]);
        Helpers::csvDownload('products-' . date('Ymd-His') . '.csv', ProductCsv::COLUMNS, array_map([ProductCsv::class, 'exportRow'], $rows));
    }

    /* ================= Create / edit ================= */

    public function create(): void
    {
        $this->renderForm(null, [
            'product_type' => 'medicine', 'unit' => 'pcs', 'status' => 'draft', 'min_order_qty' => '1',
            'low_stock_threshold' => '10', 'vat_percent' => '0', 'track_stock' => '1', 'weight_grams' => '0',
            'category_id' => ctype_digit(Helpers::str('category_id')) ? Helpers::str('category_id') : '',
        ], [], []);
    }

    public function store(): void
    {
        $in = $this->input();
        $variants = $this->variantInput();
        [$errors, $newFiles] = $this->validateProduct($in, $variants, null);
        if ($errors) {
            $this->renderForm(null, $in, $variants, $errors);
            return;
        }

        $stored = $this->storeFiles($newFiles);
        try {
            $id = $this->db->transaction(function () use ($in, $variants, $stored) {
                $id = Product::create($this->toRow($in, 0) + ['created_by' => Auth::id()]);
                if ($variants) {
                    Product::syncVariants($id, $this->variantsToModel($variants));
                }
                $this->saveImages($id, $in, $stored, []);
                return $id;
            });
        } catch (PDOException $e) {
            $this->discardFiles($stored);
            if ($e->getCode() === '23000') {
                $this->renderForm(null, $in, $variants, ['sku' => __('duplicate_value')]);
                return;
            }
            throw $e;
        }

        flash('success', __('product_saved', ['name' => $in['name_en']]));
        redirect(($in['_after'] ?? '') === 'new' ? 'admin/products/create' : 'admin/products/' . $id . '/edit');
    }

    public function edit(string $id): void
    {
        $product = $this->findProduct((int) $id);
        $this->renderForm($product, $this->productToInput($product), $this->variantsToInput(Product::variants((int) $product['id'])), []);
    }

    public function update(string $id): void
    {
        $product = $this->findProduct((int) $id);
        $in = $this->input();
        $variants = $this->variantInput();
        [$errors, $newFiles] = $this->validateProduct($in, $variants, $product);
        if ($errors) {
            $this->renderForm($product, $in, $variants, $errors);
            return;
        }

        $pid = (int) $product['id'];
        $stored = $this->storeFiles($newFiles);
        $existingImages = Product::images($pid);
        try {
            $removedPaths = $this->db->transaction(function () use ($pid, $in, $variants, $stored, $existingImages) {
                Product::updateById($pid, $this->toRow($in, $pid));
                Product::syncVariants($pid, $this->variantsToModel($variants));
                return $this->saveImages($pid, $in, $stored, $existingImages);
            });
        } catch (PDOException $e) {
            $this->discardFiles($stored);
            if ($e->getCode() === '23000') {
                $this->renderForm($product, $in, $variants, ['sku' => __('duplicate_value')]);
                return;
            }
            throw $e;
        }
        $this->discardFiles($removedPaths); // only after the DB commit succeeded

        flash('success', __('product_saved', ['name' => $in['name_en']]));
        redirect('admin/products/' . $pid . '/edit');
    }

    public function destroy(string $id): void
    {
        $product = $this->findProduct((int) $id);
        $result = Product::remove((int) $product['id']);
        flash('success', __($result === 'archived' ? 'product_archived' : 'product_deleted', ['name' => $product['name_en']]));
        $back = $_SERVER['HTTP_REFERER'] ?? '';
        // Don't go "back" to the edit page of the product that no longer exists
        str_contains($back, '/products/' . $product['id'] . '/') ? redirect('admin/products') : back();
    }

    /* ================= CSV import ================= */

    public function importForm(): void
    {
        $this->view('admin/products/import', ['title' => __('import_products'), 'result' => null, 'options' => []], 'admin');
    }

    public function import(): void
    {
        @set_time_limit(600); // big catalogues (thousands of rows, photos) take a while
        $options = [
            'dry_run'       => Helpers::str('dry_run') === '1',
            'create_brands' => Helpers::str('create_brands') === '1',
            'create_categories' => Helpers::str('create_categories') === '1' && can('categories.create'),
            'can_approve'   => can('products.approve'),
            'can_create'    => can('products.create'),
            'can_edit'      => can('products.edit'),
        ];
        $options['replace_images'] = Helpers::str('replace_images') === '1';
        $files = Upload::files('file');
        $error = null;
        if (!$files) {
            $error = __('import_choose_file');
        } else {
            $error = ProductCsv::validateUpload($files[0]);
        }
        // optional ZIP with the photos named in the "images" column
        $zip = Upload::files('images_zip')[0] ?? null;
        if (!$error && $zip) {
            $zipName = mb_substr(basename((string) $zip['name']), 0, 80);
            if (($zip['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $zip['tmp_name'])) {
                $error = __('imgimp_zip_upload_failed', ['name' => $zipName, 'mb' => num((int) ini_get('upload_max_filesize'))]);
            } elseif (strtolower(pathinfo($zipName, PATHINFO_EXTENSION)) !== 'zip') {
                $error = __('imgimp_bad_zip');
            } else {
                [$index, $zipError] = ProductImageImport::zipIndex((string) $zip['tmp_name']);
                $error = $zipError;
                $options['zip_index'] = $index;
                $options['zip_path'] = (string) $zip['tmp_name'];
                $options['zip_count'] = count($index);
            }
        }
        if ($error) {
            $this->view('admin/products/import', ['title' => __('import_products'), 'result' => ['fatal' => $error], 'options' => $options], 'admin');
            return;
        }
        $result = (new ProductCsv())->import($files[0]['tmp_name'], $options);
        if (empty($result['fatal']) && !$result['errors'] && !$options['dry_run']) {
            Audit::log('products.import', 'products', null, null, [
                'file' => mb_substr($files[0]['name'], 0, 100), 'created' => $result['created'], 'updated' => $result['updated'],
            ]);
        }
        $this->view('admin/products/import', ['title' => __('import_products'), 'result' => $result, 'options' => $options], 'admin');
    }

    public function template(): void
    {
        Helpers::csvDownload('products-import-template.csv', ProductCsv::COLUMNS, ProductCsv::sampleRows());
    }

    /* ================= helpers: input ================= */

    private function filters(): array
    {
        $s = static fn(string $k) => Helpers::str($k);
        $perPage = (int) $s('per_page');
        return [
            'q'           => mb_substr($s('q'), 0, 100),
            'category_id' => ctype_digit($s('category_id')) ? (int) $s('category_id') : 0,
            'brand_id'    => ctype_digit($s('brand_id')) ? (int) $s('brand_id') : 0,
            'vendor'      => ($s('vendor') === 'inhouse' || ctype_digit($s('vendor'))) ? $s('vendor') : '',
            'status'      => in_array($s('status'), Product::STATUSES, true) ? $s('status') : '',
            'type'        => in_array($s('type'), Product::TYPES, true) ? $s('type') : '',
            'rx'          => in_array($s('rx'), ['0', '1'], true) ? $s('rx') : '',
            'stock'       => in_array($s('stock'), ['low', 'out'], true) ? $s('stock') : '',
            'sort'        => isset(Product::SORTS[$s('sort')]) ? $s('sort') : 'newest',
            'per_page'    => in_array($perPage, self::PER_PAGE, true) ? $perPage : 20,
        ];
    }

    private function findProduct(int $id): array
    {
        $p = Product::findLive($id);
        if (!$p) {
            $this->notFound();
        }
        return $p;
    }

    private function input(): array
    {
        $in = $this->postData();
        foreach (['requires_prescription', 'is_featured', 'track_stock'] as $cb) {
            $in[$cb] = isset($in[$cb]) && $in[$cb] === '1' ? '1' : '0';
        }
        $in['slug'] = mb_strtolower($in['slug'] ?? '');
        $in['sku'] = $in['sku'] ?? '';
        // existing images: images[ID][alt|sort|delete]
        $in['_images'] = [];
        foreach ((array) ($_POST['images'] ?? []) as $imgId => $img) {
            if (ctype_digit((string) $imgId) && is_array($img)) {
                $in['_images'][(int) $imgId] = [
                    'alt'    => is_scalar($img['alt'] ?? null) ? trim((string) $img['alt']) : '',
                    'sort'   => is_scalar($img['sort'] ?? null) ? trim((string) $img['sort']) : '0',
                    'delete' => ($img['delete'] ?? '') === '1',
                ];
            }
        }
        return $in;
    }

    /** variants[i][field] rows; completely blank rows are ignored. */
    private function variantInput(): array
    {
        $fields = ['id', 'name_en', 'name_bn', 'sku', 'barcode', 'unit_multiplier', 'price', 'sale_price', 'purchase_price', 'is_default', 'is_active'];
        $rows = [];
        // default variant radio: variants_default = the POST key of the chosen row
        $default = Helpers::str('variants_default');
        foreach ((array) ($_POST['variants'] ?? []) as $key => $r) {
            if (!is_array($r) || count($rows) > Product::MAX_VARIANTS) {
                continue;
            }
            $row = [];
            foreach ($fields as $f) {
                $row[$f] = is_scalar($r[$f] ?? null) ? trim((string) $r[$f]) : '';
            }
            $row['is_default'] = ($default !== '' && (string) $key === $default) ? '1' : '';
            $row['is_active'] = $row['is_active'] === '1' ? '1' : '';
            if (implode('', array_intersect_key($row, array_flip(['name_en', 'name_bn', 'sku', 'barcode', 'price', 'sale_price', 'purchase_price']))) === '') {
                continue;
            }
            $rows[] = $row; // re-indexed 0..n-1; the form re-renders with these indexes
        }
        return $rows;
    }

    /* ================= helpers: validation ================= */

    /** @return array{0: array, 1: array} [errors, validated new image uploads] */
    private function validateProduct(array $in, array $variants, ?array $product): array
    {
        $statuses = $this->allowedStatuses($product);
        $errors = $this->validate($in, [
            'name_en'             => 'required|max:255',
            'name_bn'             => 'max:255',
            'product_type'        => 'required|in:' . implode(',', Product::TYPES),
            'generic_name'        => 'max:255',
            'category_id'         => 'required|integer',
            'brand_id'            => 'integer',
            'vendor_id'           => 'integer',
            'sku'                 => 'max:100',
            'barcode'             => 'max:100',
            'strength'            => 'max:100',
            'dosage_form'         => 'max:100',
            'pack_size'           => 'max:100',
            'unit'                => 'required|max:30',
            'short_description'   => 'max:500',
            'description'         => 'max:60000',
            'indications'         => 'max:20000',
            'usage_instructions'  => 'max:20000',
            'side_effects'        => 'max:20000',
            'price'               => 'required|decimal|gte:0.01',
            'sale_price'          => 'decimal',
            'purchase_price'      => 'decimal',
            'vat_percent'         => 'decimal|lte:100',
            'min_order_qty'       => 'required|integer|gte:1|lte:100000',
            'max_order_qty'       => 'integer|gte:1|lte:100000',
            'low_stock_threshold' => 'integer|gte:0|lte:1000000',
            'weight_grams'        => 'integer|gte:0|lte:1000000',
            'status'              => 'required|in:' . implode(',', Product::STATUSES),
            'rejection_reason'    => 'max:255',
            'slug'                => 'max:250|slug',
            'meta_title'          => 'max:255',
            'meta_description'    => 'max:500',
            'meta_keywords'       => 'max:255',
        ]);
        $selfId = (int) ($product['id'] ?? 0);

        // Relations
        if (!isset($errors['category_id']) && !isset(Category::allById()[(int) $in['category_id']])) {
            $errors['category_id'] = __('v_in');
        }
        if (!isset($errors['brand_id']) && ($in['brand_id'] ?? '') !== '' && !Brand::find((int) $in['brand_id'])) {
            $errors['brand_id'] = __('v_in');
        }
        $vendorId = ($in['vendor_id'] ?? '') !== '' ? (int) $in['vendor_id'] : null;
        if (!isset($errors['vendor_id']) && $vendorId !== null && !Vendor::existsActive($vendorId)) {
            $errors['vendor_id'] = __('v_in');
        }

        // Seller type rules: allowed categories, prescription medicine only for types that may sell it
        if ($vendorId !== null && !isset($errors['vendor_id'])) {
            $vt = VendorType::get((int) $this->db->fetchColumn('SELECT vendor_type_id FROM vendors WHERE id = :id', ['id' => $vendorId]));
            if ($vt) {
                if (!isset($errors['category_id']) && !VendorType::allowsCategory($vt, (int) $in['category_id'])) {
                    $errors['category_id'] = __('vt_category_not_allowed', ['type' => VendorType::label($vt)]);
                }
                if (!empty($in['requires_prescription']) && !(int) $vt['can_sell_rx']) {
                    $errors['vendor_id'] = __('vt_rx_not_allowed', ['type' => VendorType::label($vt)]);
                }
            }
        }

        // Business rules
        if (($in['product_type'] ?? '') === 'medicine' && ($in['generic_name'] ?? '') === '') {
            $errors['generic_name'] ??= __('generic_required');
        }
        if (!isset($errors['sale_price']) && !isset($errors['price']) && ($in['sale_price'] ?? '') !== '' && (float) $in['sale_price'] > (float) ($in['price'] ?? 0)) {
            $errors['sale_price'] = __('sale_gt_price');
        }
        if (!isset($errors['max_order_qty']) && ($in['max_order_qty'] ?? '') !== '' && (int) $in['max_order_qty'] < (int) ($in['min_order_qty'] ?? 1)) {
            $errors['max_order_qty'] = __('max_lt_min');
        }
        if (($in['status'] ?? '') === 'rejected' && trim($in['rejection_reason'] ?? '') === '') {
            $errors['rejection_reason'] = __('v_required');
        }
        if (!isset($errors['status']) && !in_array($in['status'], $statuses, true)) {
            $errors['status'] = __('status_needs_approve');
        }
        if (!isset($errors['sku']) && $in['sku'] !== '') {
            if (!preg_match('/^[A-Za-z0-9._\/-]+$/', $in['sku'])) {
                $errors['sku'] = __('v_sku');
            } elseif (Product::skuExists($in['sku'], $vendorId, $selfId)) {
                $errors['sku'] = __('sku_taken');
            }
        }
        if (!isset($errors['barcode']) && ($in['barcode'] ?? '') !== '' && !preg_match('/^[A-Za-z0-9-]+$/', $in['barcode'])) {
            $errors['barcode'] = __('v_barcode');
        }
        if (!isset($errors['slug']) && $in['slug'] !== '' && Product::slugExists($in['slug'], $selfId)) {
            $errors['slug'] = __('slug_taken');
        }

        $errors += $this->validateVariants($variants, $selfId);

        // Images
        $kept = 0;
        if ($product) {
            foreach (Product::images($selfId) as $img) {
                $state = $in['_images'][(int) $img['id']] ?? null;
                if ($state && mb_strlen($state['alt']) > 255) {
                    $errors['images'] = __('v_max', ['n' => 255]);
                }
                if (!($state['delete'] ?? false)) {
                    $kept++;
                }
            }
        }
        $newFiles = [];
        $fileErrors = [];
        foreach (Upload::files('new_images') as $f) {
            if ($err = Upload::validateImage($f)) {
                $fileErrors[] = $err;
            } else {
                $newFiles[] = $f;
            }
        }
        if ($kept + count($newFiles) + count($fileErrors) > Product::MAX_IMAGES) {
            $fileErrors[] = __('too_many_images', ['n' => Product::MAX_IMAGES]);
        }
        if ($fileErrors) {
            $errors['new_images'] = implode(' ', $fileErrors);
        }
        if ($errors && $newFiles) {
            $errors['new_images'] = trim(($errors['new_images'] ?? '') . ' ' . __('reselect_files'));
        }
        return [$errors, $errors ? [] : $newFiles];
    }

    private function validateVariants(array $rows, int $productId): array
    {
        $errors = [];
        if (count($rows) > Product::MAX_VARIANTS) {
            $errors['variants'] = __('too_many_variants', ['n' => Product::MAX_VARIANTS]);
            return $errors;
        }
        $existing = $productId ? array_column(Product::variants($productId), null, 'id') : [];
        $keptIds = array_filter(array_map(static fn($r) => (int) $r['id'], $rows));
        $skus = [];
        foreach ($rows as $i => $r) {
            $e = $this->validate($r, [
                'name_en'         => 'required|max:150',
                'name_bn'         => 'max:150',
                'sku'             => 'max:100',
                'barcode'         => 'max:100',
                'unit_multiplier' => 'integer|gte:1|lte:100000',
                'price'           => 'required|decimal|gte:0.01',
                'sale_price'      => 'decimal',
                'purchase_price'  => 'decimal',
            ]);
            if (!isset($e['sale_price']) && !isset($e['price']) && $r['sale_price'] !== '' && (float) $r['sale_price'] > (float) $r['price']) {
                $e['sale_price'] = __('sale_gt_price');
            }
            if ($r['sku'] !== '' && !isset($e['sku'])) {
                if (!preg_match('/^[A-Za-z0-9._\/-]+$/', $r['sku'])) {
                    $e['sku'] = __('v_sku');
                } elseif (isset($skus[strtolower($r['sku'])])) {
                    $e['sku'] = __('sku_duplicate_row');
                } else {
                    // a variant that is being removed but kept inactive (has stock/orders) still owns its SKU
                    foreach ($existing as $vid => $v) {
                        if (!in_array((int) $vid, $keptIds, true) && strcasecmp((string) $v['sku'], $r['sku']) === 0) {
                            $e['sku'] = __('sku_taken');
                        }
                    }
                }
                $skus[strtolower($r['sku'])] = true;
            }
            if ($r['barcode'] !== '' && !isset($e['barcode']) && !preg_match('/^[A-Za-z0-9-]+$/', $r['barcode'])) {
                $e['barcode'] = __('v_barcode');
            }
            foreach ($e as $field => $msg) {
                $errors['variants.' . $i . '.' . $field] = $msg;
            }
        }
        if (count(array_filter($errors, static fn($k) => str_starts_with($k, 'variants.'), ARRAY_FILTER_USE_KEY)) > 0) {
            $errors['variants'] = __('variants_have_errors');
        }
        return $errors;
    }

    /** Statuses the current user may choose for this product. */
    private function allowedStatuses(?array $product): array
    {
        if (can('products.approve')) {
            return Product::STATUSES;
        }
        $list = array_values(array_diff(Product::STATUSES, Product::APPROVAL_STATUSES));
        if ($product && !in_array($product['status'], $list, true)) {
            $list[] = $product['status']; // may keep the current (approved/rejected) status
        }
        return $list;
    }

    /* ================= helpers: mapping ================= */

    private function toRow(array $in, int $selfId): array
    {
        $nul = static fn(string $k) => (($in[$k] ?? '') !== '') ? $in[$k] : null;
        $mrp = $in['price'];
        return [
            'vendor_id'             => ($in['vendor_id'] ?? '') !== '' ? (int) $in['vendor_id'] : null,
            'category_id'           => (int) $in['category_id'],
            'brand_id'              => ($in['brand_id'] ?? '') !== '' ? (int) $in['brand_id'] : null,
            'product_type'          => $in['product_type'],
            'name_en'               => $in['name_en'],
            'name_bn'               => $nul('name_bn'),
            'slug'                  => $in['slug'] !== '' ? $in['slug'] : Product::uniqueSlug(Product::slugBase($in['name_en'], $in['strength'] ?? null), $selfId),
            'sku'                   => $nul('sku'),
            'barcode'               => $nul('barcode'),
            'generic_name'          => $nul('generic_name'),
            'strength'              => $nul('strength'),
            'dosage_form'           => $nul('dosage_form'),
            'pack_size'             => $nul('pack_size'),
            'unit'                  => $in['unit'],
            'short_description'     => $nul('short_description'),
            'description'           => $nul('description'),
            'indications'           => $nul('indications'),
            'usage_instructions'    => $nul('usage_instructions'),
            'side_effects'          => $nul('side_effects'),
            'mrp'                   => $mrp,
            'price'                 => ($in['sale_price'] ?? '') !== '' ? $in['sale_price'] : $mrp,
            'cost_price'            => $nul('purchase_price'),
            'vat_percent'           => ($in['vat_percent'] ?? '') !== '' ? $in['vat_percent'] : '0',
            'requires_prescription' => (int) $in['requires_prescription'],
            'track_stock'           => (int) $in['track_stock'],
            'is_featured'           => (int) $in['is_featured'],
            'min_order_qty'         => (int) $in['min_order_qty'],
            'max_order_qty'         => ($in['max_order_qty'] ?? '') !== '' ? (int) $in['max_order_qty'] : null,
            'low_stock_threshold'   => (int) ($in['low_stock_threshold'] ?? 10),
            'weight_grams'          => (int) ($in['weight_grams'] ?? 0),
            'status'                => $in['status'],
            'rejection_reason'      => $in['status'] === 'rejected' ? $nul('rejection_reason') : null,
            'meta_title'            => $nul('meta_title'),
            'meta_description'      => $nul('meta_description'),
            'meta_keywords'         => $nul('meta_keywords'),
        ];
    }

    private function productToInput(array $p): array
    {
        $in = $p;
        $in['price'] = $p['mrp'];
        $in['sale_price'] = ((float) $p['price'] < (float) $p['mrp']) ? $p['price'] : '';
        $in['purchase_price'] = $p['cost_price'];
        foreach ($in as $k => $v) {
            $in[$k] = $v === null ? '' : (string) $v;
        }
        return $in;
    }

    private function variantsToInput(array $variants): array
    {
        return array_map(static fn($v) => [
            'id'              => (string) $v['id'],
            'name_en'         => $v['name_en'],
            'name_bn'         => (string) $v['name_bn'],
            'sku'             => (string) $v['sku'],
            'barcode'         => (string) $v['barcode'],
            'unit_multiplier' => (string) $v['unit_multiplier'],
            'price'           => (string) $v['mrp'],
            'sale_price'      => (float) $v['price'] < (float) $v['mrp'] ? (string) $v['price'] : '',
            'purchase_price'  => (string) $v['cost_price'],
            'is_default'      => (int) $v['is_default'] ? '1' : '',
            'is_active'       => (int) $v['is_active'] ? '1' : '',
            'stock_qty'       => (int) $v['stock_qty'],
        ], $variants);
    }

    private function variantsToModel(array $rows): array
    {
        return array_map(static fn($r) => [
            'id'              => $r['id'],
            'name_en'         => $r['name_en'],
            'name_bn'         => $r['name_bn'],
            'sku'             => $r['sku'],
            'barcode'         => $r['barcode'],
            'unit_multiplier' => $r['unit_multiplier'],
            'mrp'             => $r['price'],
            'price'           => $r['sale_price'],
            'cost_price'      => $r['purchase_price'],
            'is_default'      => $r['is_default'],
            'is_active'       => $r['is_active'],
        ], $rows);
    }

    /* ================= helpers: images ================= */

    /** Move validated uploads into place. On failure, already-moved files are removed. */
    private function storeFiles(array $files): array
    {
        $paths = [];
        try {
            foreach ($files as $f) {
                $path = Upload::storeImage($f, 'products');
                $paths[] = $path;
                Watermark::applyToStored($path); // SasthoBazar watermark (Settings → Watermark); original kept privately
            }
        } catch (Throwable $e) {
            $this->discardFiles($paths);
            throw $e;
        }
        return $paths;
    }

    private function discardFiles(array $paths): void
    {
        foreach ($paths as $p) {
            Upload::delete($p);
            Watermark::forget($p);
        }
    }

    /**
     * Apply image edits (alt, order, delete), insert new images, set the primary one.
     * primary_image = "e<ID>" (existing) or "n<index>" (new upload).
     * @return string[] paths of deleted images (unlink after commit)
     */
    private function saveImages(int $pid, array $in, array $newPaths, array $existing): array
    {
        $removed = [];
        $maxSort = 0;
        foreach ($existing as $img) {
            $state = $in['_images'][(int) $img['id']] ?? null;
            if ($state && $state['delete']) {
                $this->db->delete('product_images', 'id = :id AND product_id = :p', ['id' => (int) $img['id'], 'p' => $pid]);
                $removed[] = $img['path'];
                continue;
            }
            if ($state) {
                $sort = ctype_digit($state['sort']) ? (int) $state['sort'] : (int) $img['sort_order'];
                $this->db->update('product_images', ['alt' => $state['alt'] !== '' ? $state['alt'] : null, 'sort_order' => $sort], 'id = :id', ['id' => (int) $img['id']]);
                $maxSort = max($maxSort, $sort);
            } else {
                $maxSort = max($maxSort, (int) $img['sort_order']);
            }
        }

        $primary = Helpers::str('primary_image');
        $primaryId = null;
        if (preg_match('/^e(\d+)$/', $primary, $m)) {
            $primaryId = (int) $m[1];
        }
        foreach ($newPaths as $i => $path) {
            $id = $this->db->insert('product_images', [
                'product_id' => $pid,
                'path'       => $path,
                'alt'        => mb_substr($in['name_en'], 0, 255),
                'sort_order' => $maxSort + $i + 1,
                'is_primary' => 0,
            ]);
            if ($primary === 'n' . $i) {
                $primaryId = $id;
            }
        }
        Product::normalizePrimaryImage($pid, $primaryId);
        if ($removed || $newPaths) {
            Audit::log('products.images', 'products', $pid, $removed ? ['removed' => $removed] : null, $newPaths ? ['added' => $newPaths] : null);
        }
        return $removed;
    }

    /* ================= render ================= */

    private function renderForm(?array $product, array $input, array $variants, array $errors): void
    {
        $this->view('admin/products/form', [
            'title'      => $product ? __('edit_product') : __('add_product'),
            'product'    => $product,
            'input'      => $input,
            'variants'   => $variants,
            'errors'     => $errors,
            'images'     => $product ? Product::images((int) $product['id']) : [],
            'categories' => Category::tree(),
            'brands'     => Brand::options(),
            'vendors'    => Vendor::options(),
            'statuses'   => $this->allowedStatuses($product),
        ], 'admin');
    }
}
