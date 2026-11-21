<?php
declare(strict_types=1);

/** Storefront CMS: static pages (/page/{slug}), FAQ (/faq), blog / health tips (/blog, /blog/{slug}). */
class PageController extends Controller
{
    public function page(string $slug): void
    {
        $p = $this->db->fetch('SELECT * FROM pages WHERE slug = :s AND is_active = 1', ['s' => $slug]);
        if (!$p) {
            $this->notFound();
        }
        $title = tr($p['title_bn'], $p['title_en']);
        $content = tr($p['content_bn'], (string) $p['content_en']);
        $this->view('shop/page', [
            'title'           => $p['meta_title'] ?: $title,
            'metaDescription' => $p['meta_description'] ?: Html::excerpt($content, 160),
            'page'            => $p,
            'heading'         => $title,
            'content'         => Html::clean($content), // cleaned on save too; cleaned again in case of old / imported content
            'siblings'        => $this->db->fetchAll('SELECT slug, title_en, title_bn FROM pages WHERE is_active = 1 ORDER BY sort_order, id'),
        ]);
    }

    public function faq(): void
    {
        $q = mb_substr(Helpers::str('q'), 0, 80);
        $params = [];
        $where = 'is_active = 1';
        if ($q !== '') {
            $where .= ' AND (question_en LIKE :a OR question_bn LIKE :b OR answer_en LIKE :c OR answer_bn LIKE :d)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $params = ['a' => $like, 'b' => $like, 'c' => $like, 'd' => $like];
        }
        $rows = $this->db->fetchAll(
            "SELECT * FROM faqs WHERE {$where} ORDER BY FIELD(category, " . implode(',', array_map(static fn($c) => "'{$c}'", AdminFaqController::CATEGORIES)) . '), sort_order, id',
            $params
        );
        $groups = [];
        foreach ($rows as $r) {
            $groups[$r['category']][] = $r;
        }
        $this->view('shop/faq', [
            'title'           => __('faq_title'),
            'metaDescription' => __('faq_meta'),
            'groups'          => $groups,
            'q'               => $q,
        ]);
    }

    public function blog(): void
    {
        $cat = in_array(Helpers::str('category'), AdminBlogController::CATEGORIES, true) ? Helpers::str('category') : '';
        $where = "status = 'published' AND published_at <= NOW()" . ($cat !== '' ? ' AND category = :c' : '');
        $params = $cat !== '' ? ['c' => $cat] : [];
        $pager = new Paginator((int) $this->db->fetchColumn("SELECT COUNT(*) FROM blog_posts WHERE {$where}", $params), 12);
        $this->view('shop/blog', [
            'title'           => $cat !== '' ? __('blogcat_' . $cat) : __('blog_title'),
            'metaDescription' => __('blog_meta'),
            'posts'           => $this->db->fetchAll(
                "SELECT id, slug, title_en, title_bn, excerpt_en, excerpt_bn, content_en, content_bn, image, category, published_at
                   FROM blog_posts WHERE {$where} ORDER BY published_at DESC, id DESC LIMIT {$pager->perPage} OFFSET {$pager->offset()}",
                $params
            ),
            'pager'           => $pager,
            'category'        => $cat,
            'counts'          => array_column($this->db->fetchAll("SELECT category, COUNT(*) AS n FROM blog_posts WHERE status = 'published' AND published_at <= NOW() GROUP BY category"), 'n', 'category'),
        ]);
    }

    public function post(string $slug): void
    {
        $p = $this->db->fetch("SELECT * FROM blog_posts WHERE slug = :s AND status = 'published' AND published_at <= NOW()", ['s' => $slug]);
        if (!$p) {
            $this->notFound();
        }
        $key = 'blog_viewed_' . (int) $p['id'];
        if (empty($_SESSION[$key])) {
            $_SESSION[$key] = 1;
            $this->db->query('UPDATE blog_posts SET views = views + 1, updated_at = updated_at WHERE id = :id', ['id' => (int) $p['id']]);
        }
        $title = tr($p['title_bn'], $p['title_en']);
        $content = tr($p['content_bn'], (string) $p['content_en']);
        $this->view('shop/post', [
            'title'           => $p['meta_title'] ?: $title,
            'metaDescription' => $p['meta_description'] ?: (tr($p['excerpt_bn'], (string) $p['excerpt_en']) ?: Html::excerpt($content, 160)),
            'post'            => $p,
            'heading'         => $title,
            'content'         => Html::clean($content),
            'related'         => $this->db->fetchAll(
                "SELECT slug, title_en, title_bn, image, published_at FROM blog_posts
                  WHERE status = 'published' AND published_at <= NOW() AND id <> :id ORDER BY category = :c DESC, published_at DESC LIMIT 4",
                ['id' => (int) $p['id'], 'c' => $p['category']]
            ),
        ]);
    }
}
