<?php
declare(strict_types=1);

/** Blog / health tips: bilingual posts with a cover image, category, tags, draft / published and publish date. */
class AdminBlogController extends Controller
{
    public const CATEGORIES = ['health_tips', 'medicine_info', 'nutrition', 'mother_baby', 'news'];

    public function index(): void
    {
        $status = in_array(Helpers::str('status'), ['draft', 'published'], true) ? Helpers::str('status') : '';
        $q = mb_substr(Helpers::str('q'), 0, 60);
        $where = ['1 = 1'];
        $p = [];
        if ($status !== '') {
            $where[] = 'status = :st';
            $p['st'] = $status;
        }
        if ($q !== '') {
            $where[] = '(title_en LIKE :q OR title_bn LIKE :q2)';
            $p['q'] = $p['q2'] = '%' . addcslashes($q, '%_\\') . '%';
        }
        $w = implode(' AND ', $where);
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) FROM blog_posts WHERE {$w}", $p), 20);
        $this->view('admin/cms/blog', [
            'title'  => __('blog'),
            'status' => $status,
            'q'      => $q,
            'posts'  => $this->db->fetchAll("SELECT id, slug, title_en, title_bn, image, category, status, published_at, views, updated_at FROM blog_posts WHERE {$w} ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}", $p),
            'pager'  => $pager,
        ], 'admin');
    }

    public function create(): void
    {
        $this->form(null, ['status' => 'draft', 'category' => 'health_tips', 'published_at' => date('Y-m-d\TH:i')]);
    }

    public function edit(string $id): void
    {
        $p = $this->post((int) $id);
        $in = $p;
        $in['published_at'] = $p['published_at'] ? date('Y-m-d\TH:i', strtotime($p['published_at'])) : '';
        $this->form($p, $in);
    }

    private function post(int $id): array
    {
        $p = $this->db->fetch('SELECT * FROM blog_posts WHERE id = :id', ['id' => $id]);
        if (!$p) {
            $this->notFound();
        }
        return $p;
    }

    private function form(?array $post, array $defaults): void
    {
        $old = $_SESSION['_old'] ?? null;
        $this->view('admin/cms/blog_form', [
            'title'  => __($post ? 'edit_post' : 'add_post'),
            'post'   => $post,
            'input'  => is_array($old) ? $old : $defaults,
            'errors' => $_SESSION['_errors'] ?? [],
        ], 'admin');
        unset($_SESSION['_errors']);
        Helpers::clearOld();
    }

    public function store(): void
    {
        [$data, $image] = $this->validated(null, 'admin/blog/create');
        $data['author_id'] = Auth::id();
        if ($image) {
            $data['image'] = $image;
        }
        $id = $this->db->insert('blog_posts', $data);
        Audit::log('blog.create', 'blog_posts', $id, null, ['slug' => $data['slug']]);
        flash('success', __('post_saved'));
        redirect('admin/blog/' . $id . '/edit');
    }

    public function update(string $id): void
    {
        $p = $this->post((int) $id);
        [$data, $image] = $this->validated($p, 'admin/blog/' . (int) $id . '/edit');
        if ($image) {
            Upload::delete($p['image']);
            $data['image'] = $image;
        } elseif (!empty($_POST['remove_image'])) {
            Upload::delete($p['image']);
            $data['image'] = null;
        }
        $this->db->update('blog_posts', $data, 'id = :id', ['id' => (int) $id]);
        Audit::log('blog.update', 'blog_posts', (int) $id, ['status' => $p['status']], ['status' => $data['status']]);
        flash('success', __('post_saved'));
        redirect('admin/blog/' . (int) $id . '/edit');
    }

    public function destroy(string $id): void
    {
        $p = $this->post((int) $id);
        $this->db->query('DELETE FROM blog_posts WHERE id = :id', ['id' => (int) $id]);
        Upload::delete($p['image']);
        Audit::log('blog.delete', 'blog_posts', (int) $id, ['slug' => $p['slug']], null);
        flash('success', __('post_deleted'));
        redirect('admin/blog');
    }

    /** @return array{0: array, 1: ?string} */
    private function validated(?array $post, string $formPath): array
    {
        $in = $this->postData();
        $in['slug'] = strtolower(trim((string) ($in['slug'] ?? '')));
        if ($in['slug'] === '' && !empty($in['title_en'])) {
            $in['slug'] = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($in['title_en'])), '-');
        }
        $errors = $this->validate($in, [
            'title_en'         => 'required|max:200',
            'title_bn'         => 'max:200',
            'slug'             => 'required|max:180',
            'excerpt_en'       => 'max:500',
            'excerpt_bn'       => 'max:500',
            'category'         => 'required|in:' . implode(',', self::CATEGORIES),
            'tags'             => 'max:255',
            'status'           => 'required|in:draft,published',
            'meta_title'       => 'max:255',
            'meta_description' => 'max:500',
        ]);
        if (!isset($errors['slug']) && !preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $in['slug'])) {
            $errors['slug'] = __('v_slug_simple');
        }
        if (!isset($errors['slug']) && $this->db->fetchColumn('SELECT 1 FROM blog_posts WHERE slug = :s AND id <> :id', ['s' => $in['slug'], 'id' => (int) ($post['id'] ?? 0)])) {
            $errors['slug'] = __('duplicate_value');
        }
        if (trim(strip_tags((string) ($in['content_en'] ?? ''))) === '') {
            $errors['content_en'] = __('v_required');
        }
        $published = null;
        if (($in['published_at'] ?? '') !== '') {
            $d = DateTime::createFromFormat('!Y-m-d\TH:i', (string) $in['published_at']);
            if (!$d) {
                $errors['published_at'] = __('v_date');
            } else {
                $published = $d->format('Y-m-d H:i:00');
            }
        }
        $files = Upload::files('image');
        if ($files && ($e = Upload::validateImage($files[0]))) {
            $errors['image'] = $e;
        }
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($formPath);
        }
        $nul = static fn(string $k) => trim((string) ($in[$k] ?? '')) !== '' ? trim((string) $in[$k]) : null;
        if ($in['status'] === 'published' && $published === null) {
            $published = date('Y-m-d H:i:s');
        }
        return [[
            'slug'             => $in['slug'],
            'title_en'         => $in['title_en'],
            'title_bn'         => $nul('title_bn'),
            'excerpt_en'       => $nul('excerpt_en'),
            'excerpt_bn'       => $nul('excerpt_bn'),
            'content_en'       => Html::clean($in['content_en']),
            'content_bn'       => Html::clean($in['content_bn'] ?? '') ?: null,
            'category'         => $in['category'],
            'tags'             => $nul('tags'),
            'status'           => $in['status'],
            'published_at'     => $published,
            'meta_title'       => $nul('meta_title'),
            'meta_description' => $nul('meta_description'),
        ], $files ? Upload::storeImage($files[0], 'blog') : null];
    }
}
