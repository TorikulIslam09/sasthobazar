<?php
declare(strict_types=1);

/**
 * Shared helpers for vendor panel pages.
 *   pending / rejected vendors: dashboard + profile only
 *   suspended vendors:          can look at everything, cannot act (status changes, payouts)
 *   approved vendors:           everything
 */
abstract class VendorBaseController extends Controller
{
    protected function vendor(): array
    {
        $v = Auth::vendor();
        if (!$v || $v['deleted_at'] !== null) {
            $this->view('vendor/no_shop', ['title' => __('vendor_panel')], 'vendor');
            exit;
        }
        return $v;
    }

    /** Pages that need an approved (or suspended, read-only) shop. */
    protected function activeVendor(): array
    {
        $v = $this->vendor();
        if (!in_array($v['status'], ['approved', 'suspended'], true)) {
            flash('warning', __('vendor_wait_approval'));
            redirect('vendor-panel');
        }
        return $v;
    }

    /** Actions (status change, payout request) need an approved shop. */
    protected function requireApproved(array $v): void
    {
        if ($v['status'] !== 'approved') {
            flash('error', __('vendor_suspended_action'));
            back();
        }
    }
}
