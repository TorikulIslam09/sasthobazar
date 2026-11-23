<?php
declare(strict_types=1);

/**
 * Product CSV export / import.
 *
 * The export and import use the same columns, so an exported file can be edited in Excel and imported back.
 *
 * Import rules
 *  - Row matching: `id` (existing product) -> update; else `sku` (+ vendor) of an existing product -> update; else -> new product.
 *  - On UPDATE an empty cell means "keep the current value" - only filled cells change anything.
 *  - New products need: name_en, price, category. Status defaults to draft.
 *  - category / brand / vendor accept a slug, an id or the exact name. Missing brands can be created on the fly.
 *  - ALL-OR-NOTHING: every row is validated first; if any row has an error nothing is saved.
 *  - "Validate only" (dry run) checks the file and reports what would happen without saving.
 *  - Variants and images are not part of the CSV - manage them on the product page.
 */
final class ProductCsv
{
    public const COLUMNS = [
        'id', 'sku', 'barcode', 'name_en', 'name_bn', 'product_type', 'generic_name', 'strength', 'dosage_form', 'pack_size',
        'unit', 'category', 'brand', 'vendor', 'price', 'sale_price', 'purchase_price', 'vat_percent', 'requires_prescription',
        'status', 'is_featured', 'min_order_qty', 'max_order_qty', 'short_description', 'description', 'indications',
        'usage_instructions', 'side_effects', 'slug', 'meta_title', 'meta_description', 'meta_keywords', 'images',
    ];

    /** Alternative header names people commonly use */
    private const ALIASES = [
        'name' => 'name_en', 'product_name' => 'name_en', 'name_bangla' => 'name_bn', 'type' => 'product_type',
        'generic' => 'generic_name', 'mrp' => 'price', 'regular_price' => 'price', 'cost_price' => 'purchase_price',
        'cost' => 'purchase_price', 'vat' => 'vat_percent', 'rx' => 'requires_prescription',
        'prescription_required' => 'requires_prescription', 'rx_required' => 'requires_prescription',
        'category_slug' => 'category', 'brand_slug' => 'brand', 'company' => 'brand', 'manufacturer' => 'brand',
        'vendor_slug' => 'vendor', 'featured' => 'is_featured', 'usage' => 'usage_instructions', 'dosage' => 'usage_instructions',
        'image' => 'images', 'image_url' => 'images', 'image_urls' => 'images', 'photo' => 'images', 'photos' => 'images', 'picture' => 'images',
        'image_filename' => 'images', 'image_filenames' => 'images', 'image_file' => 'images',
        'product_id' => 'id', 'brand_name' => 'brand', 'vendor_id' => 'vendor', 'category_name' => 'category',
        'full_description' => 'description', 'long_description' => 'description',
        'active_ingredients' => 'generic_name', 'active_ingredient' => 'generic_name',
        'warnings' => 'side_effects', 'warning' => 'side_effects', 'precautions' => 'side_effects',
        'storage_instructions' => 'usage_instructions', 'storage' => 'usage_instructions',
        'parent_category' => 'main_category', 'sub_category' => 'subcategory', 'child_category_name' => 'child_category',
    ];

    /** Category path columns (instead of one "category" column): Main › Sub › Child */
    private const PATH_COLUMNS = ['main_category', 'subcategory', 'child_category'];

    /** Columns that may come from several source columns: how to combine them */
    private const MERGE = ['images' => '|', 'brand' => 'first', 'generic_name' => 'first', 'description' => "\n\n",
                           'side_effects' => "\n", 'usage_instructions' => "\n"];

    /** Columns some catalogue files have that this shop does not import (stock is managed in Inventory) */
    private const IGNORED = ['origin_country', 'country_of_origin', 'stock_quantity', 'stock_qty', 'stock', 'stock_status',
                             'image_alt', 'catalog_status', 'source_note', 'notes', 'note'];

    /** Plain text columns => max length */
    private const TEXT = [
        'name_en' => 255, 'name_bn' => 255, 'generic_name' => 255, 'strength' => 100, 'dosage_form' => 100, 'pack_size' => 100,
        'unit' => 30, 'short_description' => 500, 'description' => 60000, 'indications' => 20000, 'usage_instructions' => 20000,
        'side_effects' => 20000, 'meta_title' => 255, 'meta_description' => 500, 'meta_keywords' => 255,
    ];

    public const MAX_ROWS = 5000;
    public const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_REPORTED_ERRORS = 200;

    private array $errors = [];
    private array $warnings = [];
    /** soft fixes counted once for a summary (instead of one message per row) */
    private array $fixes = ['no_price' => 0, 'type_guessed' => 0, 'slug_renamed' => 0];
    /** categories to create: path key => ['name' => ..., 'parent' => parent path key | null, 'parent_id' => id | null] */
    private array $newCategories = [];

    /* ================= Export ================= */

    /** One CSV row from a Product::filtered() row. */
    public static function exportRow(array $p): array
    {
        return [
            $p['id'], $p['sku'], $p['barcode'], $p['name_en'], $p['name_bn'], $p['product_type'], $p['generic_name'],
            $p['strength'], $p['dosage_form'], $p['pack_size'], $p['unit'], $p['category_slug'], $p['brand_slug'],
            $p['vendor_slug'], $p['mrp'], ((float) $p['price'] < (float) $p['mrp']) ? $p['price'] : '', $p['cost_price'],
            $p['vat_percent'], $p['requires_prescription'], $p['status'], $p['is_featured'], $p['min_order_qty'],
            $p['max_order_qty'], $p['short_description'], $p['description'], $p['indications'], $p['usage_instructions'] ?? '',
            $p['side_effects'], $p['slug'], $p['meta_title'], $p['meta_description'], $p['meta_keywords'] ?? '',
            '', // images: empty = keep the current photos when this file is imported again
        ];
    }

    public static function sampleRows(): array
    {
        $blank = array_fill_keys(self::COLUMNS, '');
        $rows = [
            array_merge($blank, [
                'sku' => 'NAPA-500', 'name_en' => 'Napa 500 mg Tablet', 'name_bn' => 'নাপা ৫০০ মি.গ্রা. ট্যাবলেট',
                'product_type' => 'otc', 'generic_name' => 'Paracetamol', 'strength' => '500 mg', 'dosage_form' => 'Tablet',
                'pack_size' => '10x10', 'unit' => 'strip', 'category' => 'otc-medicine', 'brand' => 'Beximco Pharmaceuticals',
                'price' => '12.00', 'sale_price' => '11.50', 'purchase_price' => '10.00', 'vat_percent' => '0',
                'requires_prescription' => '0', 'status' => 'draft', 'is_featured' => '0', 'min_order_qty' => '1',
                'short_description' => 'Fever and pain relief', 'usage_instructions' => '1-2 tablets every 4-6 hours. Max 8 tablets a day.',
                'images' => 'NAPA-500.jpg|NAPA-500-back.jpg',
            ]),
            array_merge($blank, [
                'sku' => 'SECLO-20', 'name_en' => 'Seclo 20 mg Capsule', 'product_type' => 'medicine', 'generic_name' => 'Omeprazole',
                'strength' => '20 mg', 'dosage_form' => 'Capsule', 'unit' => 'strip', 'category' => 'prescription-medicine',
                'brand' => 'Square Pharmaceuticals', 'price' => '60.00', 'requires_prescription' => '1', 'status' => 'draft',
                'min_order_qty' => '1', 'usage_instructions' => '1 capsule before breakfast, or as directed by the physician.',
            ]),
        ];
        return array_map(static fn($r) => array_values($r), $rows);
    }

    /* ================= Import ================= */

    /** Check the uploaded CSV file itself. @return string|null error */
    public static function validateUpload(array $f): ?string
    {
        if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $f['size'] > self::MAX_BYTES) {
            return __('import_file_too_large', ['mb' => self::MAX_BYTES / 1048576]);
        }
        if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            return __('upload_failed', ['name' => basename($f['name'])]);
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) ?: '';
        $okMime = str_starts_with($mime, 'text/') || in_array($mime, ['application/csv', 'application/vnd.ms-excel', 'application/octet-stream'], true);
        if (!in_array($ext, ['csv', 'txt'], true) || !$okMime) {
            return __('import_not_csv');
        }
        if ((int) $f['size'] === 0) {
            return __('import_empty');
        }
        return null;
    }

    /**
     * @param array $options dry_run, create_brands, can_approve, can_create, can_edit
     * @return array{fatal:?string, errors:array, warnings:array, total:int, created:int, updated:int, dry_run:bool, brands_created:array}
     */
    public function import(string $path, array $options): array
    {
        $result = ['fatal' => null, 'errors' => [], 'warnings' => [], 'total' => 0, 'created' => 0, 'updated' => 0,
                   'dry_run' => !empty($options['dry_run']), 'brands_created' => [], 'images_found' => 0, 'images_added' => 0, 'image_warnings' => []];

        [$header, $rows, $fatal] = $this->read($path);
        if ($fatal) {
            $result['fatal'] = $fatal;
            return $result;
        }

        $plan = [];          // validated operations
        $seenIds = [];
        $seenSkus = [];
        $seenSlugs = [];
        $newBrands = [];     // lower(name) => display name
        foreach ($rows as [$line, $raw]) {
            $op = $this->validateRow($line, $raw, $options, $seenIds, $seenSkus, $seenSlugs, $newBrands);
            if ($op) {
                $plan[] = $op;
            }
        }

        $result['total'] = count($rows);
        if ($this->fixes['no_price']) {
            $this->warnings[] = __('import_fix_no_price', ['n' => num($this->fixes['no_price'])]);
        }
        if ($this->fixes['type_guessed']) {
            $this->warnings[] = __('import_fix_type', ['n' => num($this->fixes['type_guessed'])]);
        }
        if ($this->fixes['slug_renamed']) {
            $this->warnings[] = __('import_fix_slug', ['n' => num($this->fixes['slug_renamed'])]);
        }
        $result['categories_created'] = array_values(array_map(static fn($c) => $c['name'], $this->newCategories));
        $result['warnings'] = $this->warnings;
        $result['errors'] = array_slice($this->errors, 0, self::MAX_REPORTED_ERRORS);
        $result['errors_total'] = count($this->errors);
        $result['created'] = count(array_filter($plan, static fn($o) => $o['mode'] === 'create'));
        $result['updated'] = count(array_filter($plan, static fn($o) => $o['mode'] === 'update'));
        $result['brands_created'] = array_values($newBrands);
        $result['images_found'] = array_sum(array_map(static fn($o) => count($o['images']), $plan));

        if ($this->errors || $result['dry_run'] || !$plan) {
            return $result;
        }

        try {
            $ids = [];
            $newCategories = $this->newCategories;
            Database::getInstance()->transaction(function (Database $db) use ($plan, $newBrands, $newCategories, &$ids) {
                $catIds = [];
                foreach ($newCategories as $key => $c) { // parents come first (they were planned first)
                    $parent = $c['parent'] !== null ? ($catIds[$c['parent']] ?? null) : $c['parent_id'];
                    $catIds[$key] = $db->insert('categories', [
                        'parent_id' => $parent, 'name_en' => $c['name'], 'slug' => Category::uniqueSlug($c['name']),
                        'is_active' => 1, 'sort_order' => 100,
                    ]);
                }
                $brandIds = [];
                foreach ($newBrands as $key => $name) {
                    $brandIds[$key] = $db->insert('brands', [
                        'name' => $name, 'slug' => Brand::uniqueSlug($name), 'is_manufacturer' => 1, 'is_active' => 1,
                    ]);
                }
                foreach ($plan as $k => $op) {
                    $data = $op['data'];
                    if (isset($op['new_brand'])) {
                        $data['brand_id'] = $brandIds[$op['new_brand']];
                    }
                    if (isset($op['new_category'])) {
                        $data['category_id'] = $catIds[$op['new_category']];
                    }
                    if ($op['mode'] === 'create') {
                        $data['slug'] ??= Product::uniqueSlug(Product::slugBase($data['name_en'], $data['strength'] ?? null));
                        $data['created_by'] = Auth::id();
                        $ids[$k] = $db->insert('products', $data);
                    } else {
                        if ($data) {
                            $db->update('products', $data, 'id = :id', ['id' => $op['id']]);
                        }
                        $ids[$k] = (int) $op['id'];
                    }
                }
            });
            if ($newCategories) {
                Category::clearCache();
            }
        } catch (PDOException $e) {
            error_log('Product import failed: ' . $e->getMessage());
            $result['fatal'] = __('import_db_error') . ($e->getCode() === '23000' ? ' (' . __('duplicate_value') . ')' : '');
            $result['created'] = $result['updated'] = 0;
        }

        // Photos: after the products are saved (a failed photo never undoes the product import)
        if (empty($result['fatal']) && !empty($ids)) {
            @set_time_limit(900);
            $imp = new ProductImageImport($options['zip_path'] ?? null);
            foreach ($plan as $k => $op) {
                if (!$op['images'] || !isset($ids[$k])) {
                    continue;
                }
                $alt = $op['name'] !== '' ? $op['name'] : (string) Database::getInstance()->fetchColumn('SELECT name_en FROM products WHERE id = :id', ['id' => $ids[$k]]);
                [$n, $warn] = $imp->attach((int) $ids[$k], $op['images'], $alt, !empty($options['replace_images']));
                $result['images_added'] += $n;
                foreach ($warn as $w) {
                    if (count($result['image_warnings']) < self::MAX_REPORTED_ERRORS) {
                        $result['image_warnings'][] = ['line' => $op['line'], 'message' => $w];
                    }
                }
            }
        }
        return $result;
    }

    /** @return array{0: array, 1: array<int, array{0:int, 1:array}>, 2: ?string} [header, [line, assoc row], fatal] */
    private function read(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
            $this->warnings[] = __('import_encoding_converted');
        }
        if (trim($content) === '') {
            return [[], [], __('import_empty')];
        }

        $firstLine = strtok($content, "\r\n");
        $delims = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($delims);
        $delim = (string) array_key_first($delims);

        $h = fopen('php://temp', 'r+');
        fwrite($h, $content);
        rewind($h);

        $rawHeader = fgetcsv($h, 0, $delim);
        if (!$rawHeader) {
            return [[], [], __('import_empty')];
        }
        $header = [];
        $ignored = [];
        foreach ($rawHeader as $i => $col) {
            $key = strtolower(trim((string) preg_replace('/\s+/', '_', trim((string) $col))));
            $key = self::ALIASES[$key] ?? $key;
            if (in_array($key, self::COLUMNS, true) || in_array($key, self::PATH_COLUMNS, true)) {
                if (in_array($key, $header, true) && !isset(self::MERGE[$key])) {
                    return [[], [], __('import_duplicate_column', ['col' => $key])];
                }
                $header[$i] = $key;
            } elseif (in_array($key, self::IGNORED, true)) {
                $ignored[] = (string) $col;
            } elseif ($key !== '') {
                $this->warnings[] = __('import_unknown_column', ['col' => mb_substr((string) $col, 0, 50)]);
            }
        }
        if (!empty($ignored)) {
            $this->warnings[] = __('import_ignored_columns', ['list' => implode(', ', array_slice($ignored, 0, 10))]);
        }
        if (!array_intersect(['id', 'sku', 'name_en'], $header)) {
            return [[], [], __('import_missing_columns')];
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($h, 0, $delim)) !== false) {
            $line++;
            if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                continue; // blank line
            }
            if (count($rows) >= self::MAX_ROWS) {
                fclose($h);
                return [[], [], __('import_too_many_rows', ['n' => num(self::MAX_ROWS)])];
            }
            $row = [];
            foreach ($header as $i => $key) {
                $v = trim((string) ($cells[$i] ?? ''));
                // undo the formula-injection guard added by our own export ('=..., '+..., '-..., '@...)
                if (strlen($v) > 1 && $v[0] === "'" && in_array($v[1], ['=', '+', '-', '@'], true)) {
                    $v = substr($v, 1);
                }
                // several source columns for one field (e.g. image_url + image_filename, brand_name + manufacturer)
                if (isset($row[$key]) && $row[$key] !== '' && isset(self::MERGE[$key])) {
                    if ($v !== '' && self::MERGE[$key] !== 'first') {
                        $row[$key] .= self::MERGE[$key] . $v;
                    }
                    continue;
                }
                $row[$key] = $v;
            }
            $rows[] = [$line, $row];
        }
        fclose($h);
        if (!$rows) {
            return [[], [], __('import_no_rows')];
        }
        return [$header, $rows, null];
    }

    /** "Home Care", "homecare", "Home-Care" → "homecare" (for matching category names) */
    private static function norm(string $s): string
    {
        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower(trim(str_replace('&', 'and', $s))));
    }

    /**
     * Main › Sub › Child category names → category id. Each level is looked up by name (or slug) under its parent.
     * Missing levels are planned for creation when allowed.
     * @return array{0: ?int, 1: ?string, 2: string} [existing id, path key to create, readable path]
     */
    private function resolvePath(array $r, bool $create): array
    {
        $names = array_values(array_filter(array_map(static fn($k) => trim((string) ($r[$k] ?? '')), self::PATH_COLUMNS), static fn($v) => $v !== ''));
        $readable = implode(' › ', $names);
        $all = Category::allById();
        $parentId = null;
        $parentKey = null;
        $key = '';
        foreach ($names as $name) {
            if (mb_strlen($name) > 150) {
                return [null, null, $readable];
            }
            $key .= '/' . self::norm($name);
            if ($parentKey === null) { // parent exists (or top level): look among its children
                $found = null;
                foreach ($all as $c) {
                    $sameParent = $c['parent_id'] === null ? $parentId === null : (int) $c['parent_id'] === $parentId;
                    if ($sameParent && (self::norm((string) $c['name_en']) === self::norm($name) || $c['slug'] === mb_strtolower($name)
                        || ($c['name_bn'] !== null && $c['name_bn'] === $name))) {
                        $found = $c;
                        break;
                    }
                }
                if ($found) {
                    $parentId = (int) $found['id'];
                    continue;
                }
            }
            if (!$create) {
                return [null, null, $readable];
            }
            $this->newCategories[$key] ??= ['name' => $name, 'parent' => $parentKey, 'parent_id' => $parentKey === null ? $parentId : null];
            $parentKey = $key;
        }
        return $parentKey === null ? [$parentId, null, $readable] : [null, $parentKey, $readable];
    }

    /** Product type from the category words and the prescription flag. */
    private static function guessType(array $r): string
    {
        $path = mb_strtolower(implode(' ', array_map(static fn($k) => (string) ($r[$k] ?? ''), array_merge(['category'], self::PATH_COLUMNS))));
        $rx = self::bool((string) ($r['requires_prescription'] ?? '')) === 1;
        if (preg_match('/device|equipment|electronic|thermomet|monitor|oximet|glucomet|nebuli/u', $path)) {
            return 'device';
        }
        if (preg_match('/medicine|homeopath|herbal|unani|ayurved|veterinar|antibiot|drug|pharma/u', $path)) {
            return $rx && trim((string) ($r['generic_name'] ?? '')) !== '' ? 'medicine' : 'otc';
        }
        return 'general';
    }

    private function err(int $line, string $field, string $msg): void
    {
        $this->errors[] = ['line' => $line, 'field' => $field, 'message' => $msg];
    }

    /** Validate one CSV row. Returns an operation or null when the row has errors. */
    private function validateRow(int $line, array $r, array $o, array &$seenIds, array &$seenSkus, array &$seenSlugs, array &$newBrands): ?array
    {
        $before = count($this->errors);
        $has = static fn(string $k) => isset($r[$k]) && $r[$k] !== '';

        // ---- vendor (needed before SKU matching) ----
        $vendorId = null;
        $vendorGiven = $has('vendor');
        if ($vendorGiven && !in_array(strtolower($r['vendor']), ['inhouse', 'in-house', '0'], true)) {
            $v = Vendor::findByRef($r['vendor']);
            if (!$v) {
                $this->err($line, 'vendor', __('import_not_found', ['value' => $r['vendor']]));
            } else {
                $vendorId = (int) $v['id'];
            }
        }

        // ---- match existing product ----
        $current = null;
        if ($has('id')) {
            if (!ctype_digit($r['id']) || !($current = Product::findLive((int) $r['id']))) {
                $this->err($line, 'id', __('import_id_not_found', ['id' => $r['id']]));
                return null;
            }
            if (isset($seenIds[$current['id']])) {
                $this->err($line, 'id', __('import_dup_in_file', ['line' => num($seenIds[$current['id']])]));
                return null;
            }
            $seenIds[$current['id']] = $line;
            if (!$vendorGiven) {
                $vendorId = $current['vendor_id'] !== null ? (int) $current['vendor_id'] : null;
            }
        } elseif ($has('sku')) {
            $current = Product::findBySku($r['sku'], $vendorId);
            if ($current) {
                if (isset($seenIds[$current['id']])) {
                    $this->err($line, 'sku', __('import_dup_in_file', ['line' => num($seenIds[$current['id']])]));
                    return null;
                }
                $seenIds[$current['id']] = $line;
            }
        }
        $mode = $current ? 'update' : 'create';
        if ($mode === 'create' && empty($o['can_create'])) {
            $this->err($line, 'id', __('import_no_create_permission'));
            return null;
        }
        if ($mode === 'update' && empty($o['can_edit'])) {
            $this->err($line, 'id', __('import_no_edit_permission'));
            return null;
        }
        $selfId = (int) ($current['id'] ?? 0);

        $data = [];
        if ($vendorGiven && ($current === null || $vendorId !== ($current['vendor_id'] !== null ? (int) $current['vendor_id'] : null))) {
            $data['vendor_id'] = $vendorId;
        }

        // ---- required on create ----
        $finalStatus = $has('status') ? strtolower($r['status']) : ($current['status'] ?? 'draft');
        $pathGiven = $has('main_category') || $has('subcategory') || $has('child_category');
        if ($mode === 'create') {
            if (!$has('name_en')) {
                $this->err($line, 'name_en', __('v_required'));
            }
            if (!$has('category') && !$pathGiven) {
                $this->err($line, 'category', __('v_required'));
            }
            if (!$has('price')) {
                if ($finalStatus === 'draft') {
                    // catalogue without prices: saved as a hidden draft, price is set before publishing
                    $data['mrp'] = '0.00';
                    $data['price'] = '0.00';
                    $this->fixes['no_price']++;
                } else {
                    $this->err($line, 'price', __('import_price_needed_active'));
                }
            }
        }

        // ---- plain text ----
        foreach (self::TEXT as $k => $max) {
            if ($has($k)) {
                if (mb_strlen($r[$k]) > $max) {
                    $this->err($line, $k, __('v_max', ['n' => $max]));
                } else {
                    $data[$k] = $r[$k];
                }
            }
        }

        // ---- enums & flags ----
        if ($has('product_type')) {
            $t = strtolower($r['product_type']);
            if (in_array($t, Product::TYPES, true)) {
                $data['product_type'] = $t;
            } else {
                // not a type (e.g. the product name): work it out from the category / prescription flag
                $data['product_type'] = self::guessType($r);
                $this->fixes['type_guessed']++;
            }
        } elseif ($mode === 'create' && $pathGiven) {
            $data['product_type'] = self::guessType($r);
        }
        if ($has('status')) {
            $s = strtolower($r['status']);
            if (!in_array($s, Product::STATUSES, true)) {
                $this->err($line, 'status', __('import_allowed', ['list' => implode(', ', Product::STATUSES)]));
            } elseif (in_array($s, Product::APPROVAL_STATUSES, true) && empty($o['can_approve']) && $s !== ($current['status'] ?? null)) {
                $this->err($line, 'status', __('status_needs_approve'));
            } elseif ($s === 'rejected' && ($current['status'] ?? null) !== 'rejected') {
                // rejecting needs a reason - do it on the product page
                $this->err($line, 'status', __('import_no_reject'));
            } else {
                $data['status'] = $s;
            }
        }
        foreach (['requires_prescription', 'is_featured'] as $k) {
            if ($has($k)) {
                $b = self::bool($r[$k]);
                $b === null ? $this->err($line, $k, __('import_bool')) : $data[$k] = $b;
            }
        }

        // ---- numbers ----
        foreach (['price' => 'mrp', 'sale_price' => 'price', 'purchase_price' => 'cost_price', 'vat_percent' => 'vat_percent'] as $k => $col) {
            if ($has($k)) {
                $n = self::money($r[$k]);
                if ($n === null) {
                    $this->err($line, $k, __('v_decimal'));
                } elseif ($k === 'price' && (float) $n <= 0) {
                    $this->err($line, $k, __('v_gte', ['n' => '0.01']));
                } elseif ($k === 'vat_percent' && (float) $n > 100) {
                    $this->err($line, $k, __('v_lte', ['n' => 100]));
                } else {
                    $data[$col] = $n;
                }
            }
        }
        foreach (['min_order_qty' => 1, 'max_order_qty' => 1] as $k => $min) {
            if ($has($k)) {
                $n = self::int($r[$k]);
                ($n === null || $n < $min || $n > 100000) ? $this->err($line, $k, __('v_integer')) : $data[$k] = $n;
            }
        }

        // ---- identifiers ----
        if ($has('sku')) {
            if (mb_strlen($r['sku']) > 100 || !preg_match('/^[A-Za-z0-9._\/-]+$/', $r['sku'])) {
                $this->err($line, 'sku', __('v_sku'));
            } else {
                $key = ($vendorId ?? 0) . '|' . strtolower($r['sku']);
                if (isset($seenSkus[$key])) {
                    $this->err($line, 'sku', __('import_dup_in_file', ['line' => num($seenSkus[$key])]));
                } elseif (Product::skuExists($r['sku'], $vendorId, $selfId)) {
                    $this->err($line, 'sku', __('sku_taken'));
                }
                $seenSkus[$key] = $line;
                $data['sku'] = $r['sku'];
            }
        }
        if ($has('barcode')) {
            (mb_strlen($r['barcode']) > 100 || !preg_match('/^[A-Za-z0-9-]+$/', $r['barcode']))
                ? $this->err($line, 'barcode', __('v_barcode')) : $data['barcode'] = $r['barcode'];
        }
        if ($has('slug')) {
            $slug = mb_strtolower($r['slug']);
            if (mb_strlen($slug) > 250 || !preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', $slug)) {
                $this->err($line, 'slug', __('v_slug'));
            } elseif (isset($seenSlugs[$slug]) || Product::slugExists($slug, $selfId)) {
                // same web address as another product: a unique one is made automatically (new products)
                if ($mode === 'create') {
                    $this->fixes['slug_renamed']++;
                } else {
                    $this->err($line, 'slug', __('slug_taken'));
                }
            } else {
                $data['slug'] = $slug;
            }
            $seenSlugs[$slug] = $line;
        }

        // ---- relations ----
        $newCategory = null;
        if ($has('category')) {
            $c = Category::findByRef($r['category']);
            $c ? $data['category_id'] = (int) $c['id'] : $this->err($line, 'category', __('import_not_found', ['value' => $r['category']]));
        } elseif ($pathGiven) {
            [$catId, $catKey, $missing] = $this->resolvePath($r, !empty($o['create_categories']));
            if ($catId !== null) {
                $data['category_id'] = $catId;
            } elseif ($catKey !== null) {
                $newCategory = $catKey; // created when the import is saved
            } else {
                $this->err($line, 'category', __('import_cat_missing', ['path' => $missing]));
            }
        }
        $newBrand = null;
        if ($has('brand')) {
            $b = Brand::findByRef($r['brand']);
            if ($b) {
                $data['brand_id'] = (int) $b['id'];
            } elseif (!empty($o['create_brands']) && mb_strlen($r['brand']) <= 150) {
                $newBrand = mb_strtolower($r['brand']);
                $newBrands[$newBrand] ??= $r['brand'];
            } else {
                $this->err($line, 'brand', __('import_not_found', ['value' => $r['brand']]));
            }
        }

        // ---- cross-field rules on the merged (current + changes) product ----
        $m = array_merge($current ?? ['product_type' => 'general', 'min_order_qty' => 1], $data);
        // No sale price given: a new product sells at MRP; an existing product that had no sale
        // (price == old MRP) keeps selling at MRP when MRP changes.
        if (isset($data['mrp']) && !$has('sale_price')
            && ($mode === 'create' || (float) $current['price'] >= (float) $current['mrp'])) {
            $data['price'] = $data['mrp'];
            $m['price'] = $data['mrp'];
        }
        if (isset($m['price'], $m['mrp']) && (float) $m['price'] > (float) $m['mrp']) {
            if ($mode === 'update' && isset($data['mrp']) && !$has('sale_price')) {
                $data['price'] = $data['mrp']; // MRP lowered below the old sale price: sale ends
            } else {
                $this->err($line, 'sale_price', __('sale_gt_price'));
            }
        }
        if (($m['product_type'] ?? '') === 'medicine' && trim((string) ($m['generic_name'] ?? '')) === '') {
            $this->err($line, 'generic_name', __('generic_required'));
        }
        if (!empty($m['max_order_qty']) && (int) $m['max_order_qty'] < (int) ($m['min_order_qty'] ?? 1)) {
            $this->err($line, 'max_order_qty', __('max_lt_min'));
        }
        if ($mode === 'create') {
            $data += ['status' => 'draft', 'unit' => 'pcs', 'product_type' => 'general'];
        }

        $images = [];
        if ($has('images')) {
            [$images, $imgErrors] = ProductImageImport::parseCell($r['images'], $o['zip_index'] ?? null);
            foreach ($imgErrors as $msg) {
                $this->err($line, 'images', $msg);
            }
        }

        if (count($this->errors) > $before) {
            return null;
        }
        $op = ['mode' => $mode, 'id' => $selfId, 'data' => $data, 'images' => $images, 'line' => $line, 'name' => (string) ($data['name_en'] ?? ($r['name_en'] ?? ''))];
        if ($newBrand !== null) {
            $op['new_brand'] = $newBrand;
        }
        if ($newCategory !== null) {
            $op['new_category'] = $newCategory;
        }
        return $op;
    }

    /* ---------------- value parsers ---------------- */

    private static function digits(string $v): string
    {
        return strtr($v, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    }

    /** "1,250.5" / "৳১২০" -> "1250.50"; null if invalid */
    private static function money(string $v): ?string
    {
        $v = str_replace([',', '৳', 'Tk', 'tk', ' '], '', self::digits($v));
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $v)) {
            return null;
        }
        return number_format((float) $v, 2, '.', '');
    }

    private static function int(string $v): ?int
    {
        $v = self::digits($v);
        return preg_match('/^\d{1,9}$/', $v) ? (int) $v : null;
    }

    private static function bool(string $v): ?int
    {
        $v = mb_strtolower(trim($v));
        return match (true) {
            in_array($v, ['1', 'yes', 'y', 'true', 'হ্যাঁ'], true) => 1,
            in_array($v, ['0', 'no', 'n', 'false', 'না'], true)   => 0,
            default => null,
        };
    }
}
