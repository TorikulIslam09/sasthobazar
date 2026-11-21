<?php
declare(strict_types=1);

/**
 * Categories: multi-level tree CRUD + CSV export.
 *
 * Rules:
 *  - a category cannot be moved under itself or one of its own sub-categories
 *  - a category that still has sub-categories or products cannot be deleted (move them first)
 *  - slug is generated from the English name when left empty; a typed slug must be unique
 */
class AdminCategoryController extends Controller
{
    public function index(): void
    {
        $q = mb_substr(Helpers::str('q'), 0, 100);
        $status = Helpers::str('status');
        $rows = Category::tree();

        if ($q !== '' || in_array($status, ['active', 'inactive'], true)) {
            $needle = mb_strtolower($q);
            $rows = array_values(array_filter($rows, static function ($c) use ($needle, $status) {
                if ($status === 'active' && !(int) $c['is_active']) return false;
                if ($status === 'inactive' && (int) $c['is_active']) return false;
                if ($needle === '') return true;
                return str_contains(mb_strtolower($c['name_en'] . ' ' . $c['name_bn'] . ' ' . $c['slug']), $needle);
            }));
        }

        $this->view('admin/categories/index', [
            'title'      => __('categories'),
            'categories' => $rows,
            'filtered'   => $q !== '' || $status !== '',
            'filters'    => ['q' => $q, 'status' => $status],
        ], 'admin');
    }

    public function export(): void
    {
        $rows = Category::tree();
        Audit::log('categories.export', 'categories', null, null, ['rows' => count($rows)]);
        Helpers::csvDownload('categories-' . date('Ymd-His') . '.csv',
            ['id', 'parent_id', 'path', 'name_en', 'name_bn', 'slug', 'icon', 'requires_prescription', 'commission_rate',
             'sort_order', 'is_featured', 'is_active', 'products', 'meta_title', 'meta_description'],
            array_map(static fn($c) => [
                $c['id'], $c['parent_id'], $c['path'], $c['name_en'], $c['name_bn'], $c['slug'], $c['icon'],
                $c['requires_prescription'], $c['commission_rate'], $c['sort_order'], $c['is_featured'], $c['is_active'],
                $c['products_count'], $c['meta_title'], $c['meta_description'],
            ], $rows)
        );
    }

    public function create(): void
    {
        $parent = ctype_digit(Helpers::str('parent')) ? Helpers::str('parent') : '';
        $this->renderForm(null, ['parent_id' => $parent, 'is_active' => '1', 'sort_order' => '0'], []);
    }

    public function store(): void
    {
        $in = $this->input();
        [$errors, $image] = $this->validateCategory($in, null);
        if ($errors) {
            $this->renderForm(null, $in, $errors);
            return;
        }
        $data = $this->toRow($in, 0);
        if ($image) {
            $data['image'] = Upload::storeImage($image, 'categories');
        }
        $id = Category::create($data);
        flash('success', __('category_saved'));
        redirect(($in['_after'] ?? '') === 'new' ? 'admin/categories/create?parent=' . ($data['parent_id'] ?? '') : 'admin/categories');
    }

    public function edit(string $id): void
    {
        $cat = $this->findCategory((int) $id);
        $this->renderForm($cat, $cat, []);
    }

    public function update(string $id): void
    {
        $cat = $this->findCategory((int) $id);
        $in = $this->input();
        [$errors, $image] = $this->validateCategory($in, $cat);
        if ($errors) {
            $this->renderForm($cat, array_merge($cat, $in), $errors);
            return;
        }
        $data = $this->toRow($in, (int) $cat['id']);
        $oldImage = $cat['image'];
        if ($image) {
            $data['image'] = Upload::storeImage($image, 'categories');
        } elseif (!empty($in['remove_image'])) {
            $data['image'] = null;
        }
        Category::updateById((int) $cat['id'], $data);
        if (array_key_exists('image', $data) && $oldImage && $oldImage !== $data['image']) {
            Upload::delete($oldImage);
        }
        flash('success', __('category_saved'));
        redirect('admin/categories');
    }

    public function destroy(string $id): void
    {
        $cat = $this->findCategory((int) $id);
        $all = Category::allById(true);
        $children = (int) $all[$cat['id']]['children_count'];
        $products = (int) $all[$cat['id']]['products_count'];
        if ($children > 0) {
            flash('error', __('category_has_children', ['name' => $cat['name_en'], 'n' => num($children)]));
            redirect('admin/categories');
        }
        if ($products > 0) {
            flash('error', __('category_has_products', ['name' => $cat['name_en'], 'n' => num($products)]));
            redirect('admin/categories');
        }
        Category::deleteById((int) $cat['id']);
        Upload::delete($cat['image']);
        flash('success', __('category_deleted', ['name' => $cat['name_en']]));
        redirect('admin/categories');
    }

    /* ---------------- helpers ---------------- */

    private function findCategory(int $id): array
    {
        $cat = Category::find($id);
        if (!$cat) {
            $this->notFound();
        }
        return $cat;
    }

    private function input(): array
    {
        $in = $this->postData();
        foreach (['requires_prescription', 'is_featured', 'is_active', 'remove_image'] as $cb) {
            $in[$cb] = isset($in[$cb]) && $in[$cb] === '1' ? '1' : '0';
        }
        $in['slug'] = isset($in['slug']) ? mb_strtolower($in['slug']) : '';
        return $in;
    }

    /** @return array{0: array, 1: ?array} [errors, validated upload or null] */
    private function validateCategory(array $in, ?array $cat): array
    {
        $errors = $this->validate($in, [
            'name_en'          => 'required|max:150',
            'name_bn'          => 'max:150',
            'slug'             => 'max:180|slug',
            'parent_id'        => 'integer',
            'icon'             => 'max:100',
            'description'      => 'max:5000',
            'commission_rate'  => 'decimal|gte:0|lte:100',
            'sort_order'       => 'integer|gte:-100000|lte:100000',
            'meta_title'       => 'max:255',
            'meta_description' => 'max:500',
        ]);
        $selfId = (int) ($cat['id'] ?? 0);

        if (!isset($errors['icon']) && ($in['icon'] ?? '') !== '' && !preg_match('/^bi-[a-z0-9-]+$/', $in['icon'])) {
            $errors['icon'] = __('v_icon');
        }
        if (!isset($errors['slug']) && ($in['slug'] ?? '') !== '' && Category::slugExists($in['slug'], $selfId)) {
            $errors['slug'] = __('slug_taken');
        }
        if (!isset($errors['name_en'])) {
            $dupe = $this->db->fetchColumn(
                'SELECT 1 FROM categories WHERE name_en = :n AND parent_id <=> :p AND id <> :id',
                ['n' => $in['name_en'], 'p' => ($in['parent_id'] ?? '') !== '' ? (int) $in['parent_id'] : null, 'id' => $selfId]
            );
            if ($dupe) {
                $errors['name_en'] = __('category_name_taken');
            }
        }
        if (!isset($errors['parent_id']) && ($in['parent_id'] ?? '') !== '') {
            $parentId = (int) $in['parent_id'];
            if (!isset(Category::allById()[$parentId])) {
                $errors['parent_id'] = __('v_in');
            } elseif ($selfId && in_array($parentId, Category::descendantIds($selfId), true)) {
                $errors['parent_id'] = __('category_parent_loop');
            }
        }

        $image = null;
        $files = Upload::files('image');
        if ($files) {
            if ($err = Upload::validateImage($files[0], 2 * 1024 * 1024)) {
                $errors['image'] = $err;
            } else {
                $image = $files[0];
            }
        }
        if ($errors && $image) {
            $errors['image'] ??= __('reselect_files');
        }
        return [$errors, $errors ? null : $image];
    }

    private function toRow(array $in, int $selfId): array
    {
        return [
            'parent_id'             => ($in['parent_id'] ?? '') !== '' ? (int) $in['parent_id'] : null,
            'name_en'               => $in['name_en'],
            'name_bn'               => ($in['name_bn'] ?? '') ?: null,
            'slug'                  => ($in['slug'] ?? '') !== '' ? $in['slug'] : Category::uniqueSlug($in['name_en'], $selfId),
            'icon'                  => ($in['icon'] ?? '') ?: null,
            'description'           => ($in['description'] ?? '') ?: null,
            'commission_rate'       => ($in['commission_rate'] ?? '') !== '' ? $in['commission_rate'] : null,
            'requires_prescription' => (int) $in['requires_prescription'],
            'sort_order'            => (int) ($in['sort_order'] ?? 0),
            'is_featured'           => (int) $in['is_featured'],
            'is_active'             => (int) $in['is_active'],
            'meta_title'            => ($in['meta_title'] ?? '') ?: null,
            'meta_description'      => ($in['meta_description'] ?? '') ?: null,
        ];
    }

    private function renderForm(?array $cat, array $input, array $errors): void
    {
        $tree = Category::tree();
        $blocked = $cat ? Category::descendantIds((int) $cat['id']) : [];
        $this->view('admin/categories/form', [
            'title'    => $cat ? __('edit_category') : __('add_category'),
            'category' => $cat,
            'input'    => $input,
            'errors'   => $errors,
            'parents'  => array_values(array_filter($tree, static fn($c) => !in_array((int) $c['id'], $blocked, true))),
            'stats'    => $cat ? (Category::allById()[$cat['id']] ?? null) : null,
        ], 'admin');
    }
}
