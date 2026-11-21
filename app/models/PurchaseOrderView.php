<?php
declare(strict_types=1);

/** Display constants shared by the purchase order / GRN views. */
final class PurchaseOrderView
{
    public const BADGES = [
        'draft'     => 'bg-secondary-subtle text-secondary',
        'ordered'   => 'bg-primary-subtle text-primary',
        'partial'   => 'bg-warning-subtle text-warning-emphasis',
        'received'  => 'bg-success-subtle text-success',
        'closed'    => 'bg-dark-subtle text-dark',
        'cancelled' => 'bg-danger-subtle text-danger',
    ];
}
