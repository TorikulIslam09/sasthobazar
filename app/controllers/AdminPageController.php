<?php
declare(strict_types=1);

/** Static pages (About, Privacy, Terms, Return policy, Contact ...): bilingual content, SEO, footer link. */
class AdminPageController extends Controller
{
    /** Pages the storefront links to - they can be edited and hidden, not deleted. */
    public const CORE = ['about-us', 'privacy-policy', 'terms-conditions', 'return-refund-policy', 'contact-us'];

    public function index(): void
    {
        $this->view('admin/cms/pages', [
            'title' => __('pages'),
            'pages' => $this->db->fetchAll('SELECT id, slug, title_en, title_bn, show_in_footer, sort_order, is_active, updated_at, CHAR_LENGTH(content_en) AS len_en, CHAR_LENGTH(content_bn) AS len_bn FROM pages ORDER BY sort_order, id'),
        ], 'admin');
    }

    public function create(): void
    {
        $this->form(null, ['is_active' => '1', 'show_in_footer' => '1', 'sort_order' => '10']);
    }

    public function edit(string $id): void
    {
        $p = $this->page((int) $id);
        $this->form($p, $p);
    }

    private function page(int $id): array
    {
        $p = $this->db->fetch('SELECT * FROM pages WHERE id = :id', ['id' => $id]);
        if (!$p) {
            $this->notFound();
        }
        return $p;
    }

    private function form(?array $page, array $defaults): void
    {
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/cms/page_form', [
            'title'  => __($page ? 'edit_page' : 'add_page'),
            'page'   => $page,
            'input'  => is_array($old) ? $old : $defaults,
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        $data = $this->validated(null, 'admin/pages/create');
        $id = $this->db->insert('pages', $data);
        Audit::log('pages.create', 'pages', $id, null, ['slug' => $data['slug']]);
        flash('success', __('page_saved'));
        redirect('admin/pages/' . $id . '/edit');
    }

    public function update(string $id): void
    {
        $p = $this->page((int) $id);
        $data = $this->validated($p, 'admin/pages/' . (int) $id . '/edit');
        $this->db->update('pages', $data, 'id = :id', ['id' => (int) $id]);
        Audit::log('pages.update', 'pages', (int) $id, ['slug' => $p['slug']], ['slug' => $data['slug']]);
        flash('success', __('page_saved'));
        redirect('admin/pages/' . (int) $id . '/edit');
    }

    public function destroy(string $id): void
    {
        $p = $this->page((int) $id);
        if (in_array($p['slug'], self::CORE, true)) {
            $this->db->update('pages', ['is_active' => 0], 'id = :id', ['id' => (int) $id]);
            flash('warning', __('page_core_hidden'));
        } else {
            $this->db->query('DELETE FROM pages WHERE id = :id', ['id' => (int) $id]);
            flash('success', __('page_deleted'));
        }
        Audit::log('pages.delete', 'pages', (int) $id, ['slug' => $p['slug']], null);
        redirect('admin/pages');
    }

    private function validated(?array $page, string $formPath): array
    {
        $in = $this->postData();
        $in['slug'] = strtolower(trim((string) ($in['slug'] ?? '')));
        if ($in['slug'] === '' && !empty($in['title_en'])) {
            $in['slug'] = Helpers::slugify($in['title_en']);
        }
        $errors = $this->validate($in, [
            'title_en'         => 'required|max:200',
            'title_bn'         => 'max:200',
            'slug'             => 'required|max:180',
            'meta_title'       => 'max:255',
            'meta_description' => 'max:500',
            'sort_order'       => 'integer',
        ]);
        if (!isset($errors['slug']) && !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $in['slug'])) {
            $errors['slug'] = __('v_slug_simple');
        }
        if ($page && in_array($page['slug'], self::CORE, true) && $in['slug'] !== $page['slug']) {
            $errors['slug'] = __('page_core_slug');
        }
        if (!isset($errors['slug']) && $this->db->fetchColumn('SELECT 1 FROM pages WHERE slug = :s AND id <> :id', ['s' => $in['slug'], 'id' => (int) ($page['id'] ?? 0)])) {
            $errors['slug'] = __('duplicate_value');
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($formPath);
        }
        $nul = static fn(string $k) => trim((string) ($in[$k] ?? '')) !== '' ? trim((string) $in[$k]) : null;
        return [
            'slug'             => $in['slug'],
            'title_en'         => $in['title_en'],
            'title_bn'         => $nul('title_bn'),
            'content_en'       => Html::clean($in['content_en'] ?? ''),
            'content_bn'       => Html::clean($in['content_bn'] ?? '') ?: null,
            'meta_title'       => $nul('meta_title'),
            'meta_description' => $nul('meta_description'),
            'show_in_footer'   => !empty($in['show_in_footer']) ? 1 : 0,
            'sort_order'       => (int) ($in['sort_order'] ?? 0),
            'is_active'        => !empty($in['is_active']) ? 1 : 0,
        ];
    }
}
