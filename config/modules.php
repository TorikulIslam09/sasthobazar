<?php
/**
 * Admin module registry - SINGLE SOURCE OF TRUTH for:
 *   - permissions (module x action)  -> synced into `permissions` table (Roles page > "Sync permissions")
 *   - admin sidebar menu (grouped, shown only when the user can "<module>.view")
 *
 * To add a module: add an entry here, then click "Sync permissions" on the Roles page
 * (or run: C:\xampp\php\php.exe database\migrate.php).
 * 'url' => null means the page is not built yet (shown as "coming soon").
 */
return [
    // Standard matrix columns
    'standard_actions' => ['view', 'create', 'edit', 'delete', 'export'],

    'actions' => [
        'view'          => ['দেখা', 'View'],
        'create'        => ['তৈরি', 'Create'],
        'edit'          => ['সম্পাদনা', 'Edit'],
        'delete'        => ['মুছে ফেলা', 'Delete'],
        'export'        => ['এক্সপোর্ট', 'Export'],
        'import'        => ['ইমপোর্ট', 'Import'],
        'approve'       => ['অনুমোদন', 'Approve'],
        'adjust'        => ['স্টক ইন/আউট ও সমন্বয়', 'Stock in/out & adjust'],
        'batches'       => ['ব্যাচ ও মেয়াদ', 'Batches & expiry'],
        'update_status' => ['স্ট্যাটাস পরিবর্তন', 'Update status'],
        'cancel'        => ['বাতিল', 'Cancel'],
        'verify'        => ['যাচাই', 'Verify'],
        'moderate'      => ['মডারেশন', 'Moderate'],
        'reply'         => ['উত্তর', 'Reply'],
        'assign'        => ['অ্যাসাইন', 'Assign'],
        'send'          => ['পাঠানো', 'Send'],
        'receive'       => ['মাল গ্রহণ (GRN)', 'Receive (GRN)'],
        'configure'     => ['সেটিংস', 'Configure'],
        'backup'        => ['ব্যাকআপ ও রিস্টোর', 'Backup & restore'],
    ],

    'groups' => [
        'main'      => ['মূল', 'Main'],
        'catalog'   => ['ক্যাটালগ', 'Catalog'],
        'sales'     => ['বিক্রয়', 'Sales'],
        'vendors'   => ['ভেন্ডর', 'Vendors'],
        'marketing' => ['মার্কেটিং', 'Marketing'],
        'logistics' => ['ডেলিভারি', 'Logistics'],
        'finance'   => ['হিসাব', 'Finance'],
        'content'   => ['কনটেন্ট', 'Content'],
        'support'   => ['সাপোর্ট', 'Support'],
        'system'    => ['সিস্টেম', 'System'],
    ],

    'modules' => [
        'dashboard'      => ['group' => 'main',      'bn' => 'ড্যাশবোর্ড',        'en' => 'Dashboard',          'icon' => 'bi-speedometer2',          'url' => 'admin',             'actions' => ['view']],

        'products'       => ['group' => 'catalog',   'bn' => 'পণ্য',              'en' => 'Products',           'icon' => 'bi-capsule',               'url' => 'admin/products', 'actions' => ['view', 'create', 'edit', 'delete', 'export', 'import', 'approve']],
        'categories'     => ['group' => 'catalog',   'bn' => 'ক্যাটাগরি',         'en' => 'Categories',         'icon' => 'bi-diagram-3',             'url' => 'admin/categories', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'brands'         => ['group' => 'catalog',   'bn' => 'ব্র্যান্ড',          'en' => 'Brands',             'icon' => 'bi-award',                 'url' => 'admin/brands', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'inventory'      => ['group' => 'catalog',   'bn' => 'ইনভেন্টরি',         'en' => 'Inventory',          'icon' => 'bi-box-seam',              'url' => 'admin/inventory', 'actions' => ['view', 'adjust', 'batches', 'export', 'configure']],
        'suppliers'      => ['group' => 'catalog',   'bn' => 'সরবরাহকারী',        'en' => 'Suppliers',          'icon' => 'bi-truck-front',           'url' => 'admin/suppliers', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'purchase_orders'=> ['group' => 'catalog',   'bn' => 'ক্রয় আদেশ ও GRN',    'en' => 'Purchase Orders',    'icon' => 'bi-clipboard-check',       'url' => 'admin/purchase-orders', 'actions' => ['view', 'create', 'edit', 'delete', 'receive', 'cancel', 'export']],

        'orders'         => ['group' => 'sales',     'bn' => 'অর্ডার',            'en' => 'Orders',             'icon' => 'bi-receipt',               'url' => 'admin/orders', 'actions' => ['view', 'create', 'edit', 'update_status', 'cancel', 'export']],
        'prescriptions'  => ['group' => 'sales',     'bn' => 'প্রেসক্রিপশন',      'en' => 'Prescriptions',      'icon' => 'bi-file-medical',          'url' => 'admin/prescriptions', 'actions' => ['view', 'verify', 'update_status']],
        'customers'      => ['group' => 'sales',     'bn' => 'কাস্টমার',           'en' => 'Customers',          'icon' => 'bi-people',                'url' => 'admin/customers', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'reviews'        => ['group' => 'sales',     'bn' => 'রিভিউ',             'en' => 'Reviews',            'icon' => 'bi-star',                  'url' => 'admin/reviews', 'actions' => ['view', 'moderate', 'delete']],

        'vendors'        => ['group' => 'vendors',   'bn' => 'ভেন্ডর',            'en' => 'Vendors',            'icon' => 'bi-shop',                  'url' => 'admin/vendors', 'actions' => ['view', 'edit', 'approve', 'delete']],
        'vendor_types'   => ['group' => 'vendors',   'bn' => 'ভেন্ডরের ধরন',       'en' => 'Vendor Types',       'icon' => 'bi-tags',                  'url' => 'admin/vendor-types', 'actions' => ['view', 'create', 'edit']],
        'vendor_payouts' => ['group' => 'vendors',   'bn' => 'ভেন্ডর পেআউট',      'en' => 'Vendor Payouts',     'icon' => 'bi-cash-coin',             'url' => 'admin/payouts', 'actions' => ['view', 'approve']],
        'commissions'    => ['group' => 'vendors',   'bn' => 'কমিশন',             'en' => 'Commissions',        'icon' => 'bi-percent',               'url' => 'admin/commissions', 'actions' => ['view', 'edit']],

        'coupons'        => ['group' => 'marketing', 'bn' => 'কুপন',              'en' => 'Coupons',            'icon' => 'bi-ticket-perforated',     'url' => 'admin/coupons', 'actions' => ['view', 'create', 'edit', 'delete']],
        'offers'         => ['group' => 'marketing', 'bn' => 'অফার',              'en' => 'Offers',             'icon' => 'bi-gift',                  'url' => 'admin/offers', 'actions' => ['view', 'create', 'edit', 'delete', 'approve']],
        'flash_sales'    => ['group' => 'marketing', 'bn' => 'ফ্ল্যাশ সেল',        'en' => 'Flash Sales',        'icon' => 'bi-lightning-charge',      'url' => 'admin/flash-sales', 'actions' => ['view', 'create', 'edit', 'delete']],
        'notifications'  => ['group' => 'marketing', 'bn' => 'SMS ও ইমেইল লগ',     'en' => 'SMS & Email log',    'icon' => 'bi-megaphone',             'url' => 'admin/messages', 'actions' => ['view', 'send']],

        'shipping'       => ['group' => 'logistics', 'bn' => 'শিপিং জোন ও রেট',    'en' => 'Shipping Zones',     'icon' => 'bi-truck',                 'url' => 'admin/shipping', 'actions' => ['view', 'edit', 'delete']],
        'couriers'       => ['group' => 'logistics', 'bn' => 'কুরিয়ার',            'en' => 'Couriers',           'icon' => 'bi-bicycle',               'url' => 'admin/couriers', 'actions' => ['view', 'edit']],
        'shipments'      => ['group' => 'logistics', 'bn' => 'শিপমেন্ট',           'en' => 'Shipments',          'icon' => 'bi-send',                  'url' => 'admin/shipments', 'actions' => ['view', 'create', 'edit', 'export']],
        'locations'      => ['group' => 'logistics', 'bn' => 'লোকেশন',            'en' => 'Locations',          'icon' => 'bi-geo-alt',               'url' => 'admin/locations', 'actions' => ['view', 'create', 'edit', 'delete']],

        'payments'       => ['group' => 'finance',   'bn' => 'পেমেন্ট',            'en' => 'Payments',           'icon' => 'bi-credit-card',           'url' => 'admin/payments', 'actions' => ['view', 'edit', 'export']],
        'refunds'        => ['group' => 'finance',   'bn' => 'রিফান্ড',            'en' => 'Refunds',            'icon' => 'bi-arrow-counterclockwise','url' => 'admin/refunds', 'actions' => ['view', 'approve']],
        'reports'        => ['group' => 'finance',   'bn' => 'রিপোর্ট',            'en' => 'Reports',            'icon' => 'bi-graph-up',              'url' => 'admin/reports', 'actions' => ['view', 'export']],
        'accounting'     => ['group' => 'finance',   'bn' => 'হিসাবরক্ষণ',          'en' => 'Accounting',         'icon' => 'bi-journal-bookmark',      'url' => 'admin/accounting', 'actions' => ['view', 'create', 'edit', 'delete', 'export', 'configure']],

        'banners'        => ['group' => 'content',   'bn' => 'ব্যানার',            'en' => 'Banners',            'icon' => 'bi-images',                'url' => 'admin/banners', 'actions' => ['view', 'create', 'edit', 'delete']],
        'page_builder'   => ['group' => 'content',   'bn' => 'পেজ বিল্ডার (সেকশন)',  'en' => 'Page Builder',       'icon' => 'bi-grid-1x2',              'url' => 'admin/page-builder', 'actions' => ['view', 'create', 'edit', 'delete']],
        'appearance'     => ['group' => 'content',   'bn' => 'হেডার, মেনু ও রং',     'en' => 'Header, Menus & Colours', 'icon' => 'bi-palette',          'url' => 'admin/appearance', 'actions' => ['view', 'edit']],
        'pages'          => ['group' => 'content',   'bn' => 'পেজ',               'en' => 'Pages',              'icon' => 'bi-file-earmark-text',     'url' => 'admin/pages', 'actions' => ['view', 'create', 'edit', 'delete']],

        'support'        => ['group' => 'support',   'bn' => 'সাপোর্ট টিকেট',       'en' => 'Support Tickets',    'icon' => 'bi-headset',               'url' => 'admin/support', 'actions' => ['view', 'reply', 'assign', 'export']],

        'employees'      => ['group' => 'system',    'bn' => 'কর্মী',              'en' => 'Employees',          'icon' => 'bi-person-badge',          'url' => 'admin/employees',   'actions' => ['view', 'create', 'edit', 'export']],
        'roles'          => ['group' => 'system',    'bn' => 'রোল ও পারমিশন',       'en' => 'Roles & Permissions','icon' => 'bi-shield-lock',           'url' => 'admin/roles',       'actions' => ['view', 'create', 'edit', 'delete']],
        'audit_logs'     => ['group' => 'system',    'bn' => 'অডিট লগ',            'en' => 'Audit Logs',         'icon' => 'bi-journal-text',          'url' => 'admin/audit-logs',  'actions' => ['view', 'export']],
        'settings'       => ['group' => 'system',    'bn' => 'সেটিংস',             'en' => 'Settings',           'icon' => 'bi-gear',                  'url' => 'admin/settings', 'actions' => ['view', 'edit', 'backup']],
    ],
];
