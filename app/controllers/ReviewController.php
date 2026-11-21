<?php
declare(strict_types=1);

/** Customers post product reviews (one per product). */
class ReviewController extends Controller
{
    public function store(string $id): void
    {
        $product = $this->db->fetch(
            "SELECT id, slug, vendor_id FROM products WHERE id = :id AND status = 'active' AND deleted_at IS NULL",
            ['id' => (int) $id]
        );
        if (!$product) {
            $this->notFound();
        }
        $back = Storefront::url($product) . '#reviews';

        $in = $this->postData();
        $errors = $this->validate($in, [
            'rating'  => 'required|in:1,2,3,4,5',
            'title'   => 'max:200',
            'comment' => 'max:2000',
        ]);
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            Helpers::setOld($in);
            flash('error', __('form_has_errors'));
            redirect($back);
        }

        $key = 'review:' . Auth::id();
        if (RateLimiter::tooMany($key, Review::MAX_PER_HOUR)) {
            flash('error', __('too_many_requests'));
            redirect($back);
        }
        RateLimiter::hit($key, 3600);

        $result = Review::submit(
            (int) Auth::id(),
            $product,
            (int) $in['rating'],
            ($in['title'] ?? '') !== '' ? $in['title'] : null,
            ($in['comment'] ?? '') !== '' ? $in['comment'] : null
        );
        flash($result === 'duplicate' ? 'warning' : 'success', __('review_' . $result));
        redirect($back);
    }
}
