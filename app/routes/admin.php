<?php
/**
 * Admin panel routes (http://localhost/sasthobazar/admin/...)
 * Every route inside the 'admin' group requires a logged-in Super Admin or Employee;
 * 'permission' adds a module.action check (403 if missing). Super Admin passes every check.
 */
/** @var Router $router */

$router->get('/login', 'AuthController@adminLoginForm', ['guard' => 'guest']);
$router->post('/login', 'AuthController@adminLogin', ['guard' => 'guest']);

$router->group(['guard' => 'admin'], function (Router $r) {
    $r->post('/logout', 'AuthController@adminLogout');
    $r->get('/', 'AdminDashboardController@index');

    // My profile + notification bell (any staff member)
    $r->get('/profile', 'AdminProfileController@index');
    $r->post('/profile', 'AdminProfileController@update');
    $r->post('/profile/password', 'AdminProfileController@password');
    $r->post('/notifications/read-all', 'AdminNotificationController@readAll');
    $r->get('/notifications', 'AdminNotificationController@index');
    $r->get('/notifications/poll', 'AdminNotificationController@poll');
    $r->get('/notifications/{id:\d+}/open', 'AdminNotificationController@open');

    // Roles & permissions
    $r->group(['prefix' => '/roles'], function (Router $r) {
        $r->get('/', 'AdminRoleController@index', ['permission' => 'roles.view']);
        $r->get('/create', 'AdminRoleController@create', ['permission' => 'roles.create']);
        $r->post('/', 'AdminRoleController@store', ['permission' => 'roles.create']);
        $r->get('/{id:\d+}/edit', 'AdminRoleController@edit', ['permission' => 'roles.view']);
        $r->post('/{id:\d+}', 'AdminRoleController@update', ['permission' => 'roles.edit']);
        $r->post('/{id:\d+}/delete', 'AdminRoleController@destroy', ['permission' => 'roles.delete']);
        $r->post('/sync', 'AdminRoleController@sync', ['middleware' => ['super_admin']]);
    });

    // Employees
    $r->group(['prefix' => '/employees'], function (Router $r) {
        $r->get('/', 'AdminEmployeeController@index', ['permission' => 'employees.view']);
        $r->get('/export', 'AdminEmployeeController@export', ['permission' => 'employees.export']);
        $r->get('/create', 'AdminEmployeeController@create', ['permission' => 'employees.create']);
        $r->post('/', 'AdminEmployeeController@store', ['permission' => 'employees.create']);
        $r->get('/{id:\d+}/edit', 'AdminEmployeeController@edit', ['permission' => 'employees.edit']);
        $r->post('/{id:\d+}', 'AdminEmployeeController@update', ['permission' => 'employees.edit']);
        $r->post('/{id:\d+}/toggle', 'AdminEmployeeController@toggle', ['permission' => 'employees.edit']);
    });

    // Catalog: categories (multi-level)
    $r->group(['prefix' => '/categories'], function (Router $r) {
        $r->get('/', 'AdminCategoryController@index', ['permission' => 'categories.view']);
        $r->get('/export', 'AdminCategoryController@export', ['permission' => 'categories.export']);
        $r->get('/create', 'AdminCategoryController@create', ['permission' => 'categories.create']);
        $r->post('/', 'AdminCategoryController@store', ['permission' => 'categories.create']);
        $r->get('/{id:\d+}/edit', 'AdminCategoryController@edit', ['permission' => 'categories.edit']);
        $r->post('/{id:\d+}', 'AdminCategoryController@update', ['permission' => 'categories.edit']);
        $r->post('/{id:\d+}/delete', 'AdminCategoryController@destroy', ['permission' => 'categories.delete']);
    });

    // Catalog: brands / companies
    $r->group(['prefix' => '/brands'], function (Router $r) {
        $r->get('/', 'AdminBrandController@index', ['permission' => 'brands.view']);
        $r->get('/export', 'AdminBrandController@export', ['permission' => 'brands.export']);
        $r->get('/create', 'AdminBrandController@create', ['permission' => 'brands.create']);
        $r->post('/', 'AdminBrandController@store', ['permission' => 'brands.create']);
        $r->get('/{id:\d+}/edit', 'AdminBrandController@edit', ['permission' => 'brands.edit']);
        $r->post('/{id:\d+}', 'AdminBrandController@update', ['permission' => 'brands.edit']);
        $r->post('/{id:\d+}/delete', 'AdminBrandController@destroy', ['permission' => 'brands.delete']);
    });

    // Catalog: products
    $r->group(['prefix' => '/products'], function (Router $r) {
        $r->get('/', 'AdminProductController@index', ['permission' => 'products.view']);
        $r->get('/export', 'AdminProductController@export', ['permission' => 'products.export']);
        $r->get('/import', 'AdminProductController@importForm', ['permission' => 'products.import']);
        $r->post('/import', 'AdminProductController@import', ['permission' => 'products.import']);
        $r->get('/import/template', 'AdminProductController@template', ['permission' => 'products.import']);
        $r->get('/create', 'AdminProductController@create', ['permission' => 'products.create']);
        $r->post('/', 'AdminProductController@store', ['permission' => 'products.create']);
        $r->get('/{id:\d+}/edit', 'AdminProductController@edit', ['permission' => 'products.edit']);
        $r->post('/{id:\d+}', 'AdminProductController@update', ['permission' => 'products.edit']);
        $r->post('/{id:\d+}/delete', 'AdminProductController@destroy', ['permission' => 'products.delete']);
    });

    // Inventory
    $r->group(['prefix' => '/inventory'], function (Router $r) {
        $r->get('/', 'AdminInventoryController@index', ['permission' => 'inventory.view']);
        $r->post('/settings', 'AdminInventoryController@settings', ['permission' => 'inventory.configure']);
        $r->get('/stock-in', 'AdminInventoryController@stockInForm', ['permission' => 'inventory.adjust']);
        $r->post('/stock-in', 'AdminInventoryController@stockIn', ['permission' => 'inventory.adjust']);
        $r->get('/stock-out', 'AdminInventoryController@stockOutForm', ['permission' => 'inventory.adjust']);
        $r->post('/stock-out', 'AdminInventoryController@stockOut', ['permission' => 'inventory.adjust']);
        $r->get('/lookup/products', 'AdminInventoryController@lookupProducts', ['permission' => 'inventory.adjust|purchase_orders.create|purchase_orders.edit']);
        $r->get('/lookup/batches', 'AdminInventoryController@lookupBatches', ['permission' => 'inventory.view']);

        $r->get('/batches', 'AdminStockController@batches', ['permission' => 'inventory.view']);
        $r->get('/batches/export', 'AdminStockController@batchesExport', ['permission' => 'inventory.export']);
        $r->get('/batches/{id:\d+}', 'AdminStockController@batch', ['permission' => 'inventory.view']);
        $r->post('/batches/{id:\d+}', 'AdminStockController@batchUpdate', ['permission' => 'inventory.batches']);
        $r->post('/batches/{id:\d+}/adjust', 'AdminStockController@batchAdjust', ['permission' => 'inventory.adjust']);
        $r->post('/batches/{id:\d+}/write-off', 'AdminStockController@batchWriteOff', ['permission' => 'inventory.adjust']);

        $r->get('/movements', 'AdminStockController@movements', ['permission' => 'inventory.view']);
        $r->get('/movements/export', 'AdminStockController@movementsExport', ['permission' => 'inventory.export']);
        $r->get('/reports/valuation', 'AdminStockController@valuation', ['permission' => 'inventory.view']);
        $r->get('/reports/valuation/export', 'AdminStockController@valuationExport', ['permission' => 'inventory.export']);
        $r->get('/reports/vendors', 'AdminStockController@vendors', ['permission' => 'inventory.view']);
        $r->get('/reports/vendors/export', 'AdminStockController@vendorsExport', ['permission' => 'inventory.export']);
    });

    // Suppliers
    $r->group(['prefix' => '/suppliers'], function (Router $r) {
        $r->get('/', 'AdminSupplierController@index', ['permission' => 'suppliers.view']);
        $r->get('/export', 'AdminSupplierController@export', ['permission' => 'suppliers.export']);
        $r->get('/create', 'AdminSupplierController@create', ['permission' => 'suppliers.create']);
        $r->post('/', 'AdminSupplierController@store', ['permission' => 'suppliers.create']);
        $r->get('/{id:\d+}/edit', 'AdminSupplierController@edit', ['permission' => 'suppliers.edit']);
        $r->post('/{id:\d+}', 'AdminSupplierController@update', ['permission' => 'suppliers.edit']);
        $r->post('/{id:\d+}/delete', 'AdminSupplierController@destroy', ['permission' => 'suppliers.delete']);
    });

    // Purchase orders + GRN
    $r->group(['prefix' => '/purchase-orders'], function (Router $r) {
        $r->get('/', 'AdminPurchaseOrderController@index', ['permission' => 'purchase_orders.view']);
        $r->get('/export', 'AdminPurchaseOrderController@export', ['permission' => 'purchase_orders.export']);
        $r->get('/create', 'AdminPurchaseOrderController@create', ['permission' => 'purchase_orders.create']);
        $r->post('/', 'AdminPurchaseOrderController@store', ['permission' => 'purchase_orders.create']);
        $r->get('/grn/{id:\d+}', 'AdminPurchaseOrderController@grn', ['permission' => 'purchase_orders.view']);
        $r->get('/{id:\d+}', 'AdminPurchaseOrderController@show', ['permission' => 'purchase_orders.view']);
        $r->get('/{id:\d+}/edit', 'AdminPurchaseOrderController@edit', ['permission' => 'purchase_orders.edit']);
        $r->post('/{id:\d+}', 'AdminPurchaseOrderController@update', ['permission' => 'purchase_orders.edit']);
        $r->post('/{id:\d+}/place', 'AdminPurchaseOrderController@place', ['permission' => 'purchase_orders.edit']);
        $r->post('/{id:\d+}/cancel', 'AdminPurchaseOrderController@cancel', ['permission' => 'purchase_orders.cancel']);
        $r->post('/{id:\d+}/close', 'AdminPurchaseOrderController@close', ['permission' => 'purchase_orders.cancel']);
        $r->post('/{id:\d+}/delete', 'AdminPurchaseOrderController@destroy', ['permission' => 'purchase_orders.delete']);
        $r->get('/{id:\d+}/receive', 'AdminPurchaseOrderController@receiveForm', ['permission' => 'purchase_orders.receive']);
        $r->post('/{id:\d+}/receive', 'AdminPurchaseOrderController@receive', ['permission' => 'purchase_orders.receive']);
    });

    // Audit log
    $r->group(['prefix' => '/audit-logs'], function (Router $r) {
        $r->get('/', 'AdminAuditController@index', ['permission' => 'audit_logs.view']);
        $r->get('/export', 'AdminAuditController@export', ['permission' => 'audit_logs.export']);
        $r->get('/{id:\d+}', 'AdminAuditController@show', ['permission' => 'audit_logs.view']);
    });
    // Orders
    $r->group(['prefix' => '/orders'], function (Router $r) {
        $r->get('/', 'AdminOrderController@index', ['permission' => 'orders.view']);
        $r->get('/export', 'AdminOrderController@export', ['permission' => 'orders.export']);
        $r->get('/invoices', 'AdminOrderController@invoices', ['permission' => 'orders.view']);
        $r->post('/bulk', 'AdminOrderController@bulk', ['permission' => 'orders.view']);
        $r->get('/{id:\d+}', 'AdminOrderController@show', ['permission' => 'orders.view']);
        $r->get('/{id:\d+}/invoice', 'AdminOrderController@invoice', ['permission' => 'orders.view']);
        $r->post('/{id:\d+}/status', 'AdminOrderController@status', ['permission' => 'orders.update_status']);
        $r->post('/{id:\d+}/splits/{splitId:\d+}/status', 'AdminOrderController@splitStatus', ['permission' => 'orders.update_status']);
        $r->post('/{id:\d+}/note', 'AdminOrderController@note', ['permission' => 'orders.edit']);
        $r->post('/{id:\d+}/prescription', 'AdminOrderController@prescription', ['permission' => 'prescriptions.verify']);
        $r->post('/{id:\d+}/payments/{paymentId:\d+}', 'AdminOrderController@payment', ['permission' => 'orders.edit|payments.edit']);
        $r->post('/{id:\d+}/return', 'AdminOrderController@returnDecision', ['permission' => 'refunds.approve']);
        $r->post('/{id:\d+}/refunded', 'AdminOrderController@refunded', ['permission' => 'refunds.approve']);
    });

    // Prescriptions, refunds
    $r->get('/prescriptions', 'AdminPrescriptionController@index', ['permission' => 'prescriptions.view']);
    $r->get('/prescriptions/{id:\d+}/file', 'AdminPrescriptionController@file', ['permission' => 'prescriptions.view|orders.view']);
    $r->get('/prescriptions/{id:\d+}', 'AdminPrescriptionController@show', ['permission' => 'prescriptions.view']);
    $r->post('/prescriptions/{id:\d+}', 'AdminPrescriptionController@update', ['permission' => 'prescriptions.verify|prescriptions.update_status']);
    $r->get('/refunds', 'AdminRefundController@index', ['permission' => 'refunds.view']);

    // Coupons
    $r->group(['prefix' => '/coupons'], function (Router $r) {
        $r->get('/', 'AdminCouponController@index', ['permission' => 'coupons.view']);
        $r->get('/create', 'AdminCouponController@create', ['permission' => 'coupons.create']);
        $r->post('/', 'AdminCouponController@store', ['permission' => 'coupons.create']);
        $r->get('/{id:\d+}/edit', 'AdminCouponController@edit', ['permission' => 'coupons.edit']);
        $r->post('/{id:\d+}', 'AdminCouponController@update', ['permission' => 'coupons.edit']);
        $r->post('/{id:\d+}/delete', 'AdminCouponController@destroy', ['permission' => 'coupons.delete']);
    });

    // Offers (price / buy-X-get-Y / first order / free delivery) + vendor requests
    $r->group(['prefix' => '/offers'], function (Router $r) {
        $r->get('/', 'AdminOfferController@index', ['permission' => 'offers.view']);
        $r->get('/create', 'AdminOfferController@create', ['permission' => 'offers.create']);
        $r->post('/', 'AdminOfferController@store', ['permission' => 'offers.create']);
        $r->get('/{id:\d+}/edit', 'AdminOfferController@edit', ['permission' => 'offers.view']);
        $r->post('/{id:\d+}', 'AdminOfferController@update', ['permission' => 'offers.edit']);
        $r->post('/{id:\d+}/review', 'AdminOfferController@review', ['permission' => 'offers.approve']);
        $r->post('/{id:\d+}/toggle', 'AdminOfferController@toggle', ['permission' => 'offers.edit']);
        $r->post('/{id:\d+}/delete', 'AdminOfferController@destroy', ['permission' => 'offers.delete']);
    });

    // Flash sales
    $r->group(['prefix' => '/flash-sales'], function (Router $r) {
        $r->get('/', 'AdminFlashSaleController@index', ['permission' => 'flash_sales.view']);
        $r->get('/create', 'AdminFlashSaleController@create', ['permission' => 'flash_sales.create']);
        $r->post('/', 'AdminFlashSaleController@store', ['permission' => 'flash_sales.create']);
        $r->get('/{id:\d+}/edit', 'AdminFlashSaleController@edit', ['permission' => 'flash_sales.view']);
        $r->post('/{id:\d+}', 'AdminFlashSaleController@update', ['permission' => 'flash_sales.edit']);
        $r->post('/{id:\d+}/delete', 'AdminFlashSaleController@destroy', ['permission' => 'flash_sales.delete']);
        $r->post('/{id:\d+}/items', 'AdminFlashSaleController@addItem', ['permission' => 'flash_sales.edit']);
        $r->post('/{id:\d+}/items/{item:\d+}', 'AdminFlashSaleController@updateItem', ['permission' => 'flash_sales.edit']);
        $r->post('/{id:\d+}/items/{item:\d+}/delete', 'AdminFlashSaleController@removeItem', ['permission' => 'flash_sales.edit']);
    });

    // Banners + popup
    $r->group(['prefix' => '/banners'], function (Router $r) {
        $r->get('/', 'AdminBannerController@index', ['permission' => 'banners.view']);
        $r->get('/create', 'AdminBannerController@create', ['permission' => 'banners.create']);
        $r->post('/', 'AdminBannerController@store', ['permission' => 'banners.create']);
        $r->get('/{id:\d+}/edit', 'AdminBannerController@edit', ['permission' => 'banners.edit']);
        $r->post('/{id:\d+}', 'AdminBannerController@update', ['permission' => 'banners.edit']);
        $r->post('/{id:\d+}/toggle', 'AdminBannerController@toggle', ['permission' => 'banners.edit']);
        $r->post('/{id:\d+}/delete', 'AdminBannerController@destroy', ['permission' => 'banners.delete']);
    });

    // Settings: general, orders & payments, SMS, email, notifications, backup
    $r->get('/settings', 'AdminSettingController@home', ['permission' => 'settings.view']);
    $r->get('/settings/general', 'AdminSettingController@general', ['permission' => 'settings.view']);
    $r->post('/settings/general', 'AdminSettingController@saveGeneral', ['permission' => 'settings.edit']);
    $r->get('/settings/orders', 'AdminSettingController@index', ['permission' => 'settings.view']);
    $r->post('/settings/orders', 'AdminSettingController@update', ['permission' => 'settings.edit']);
    $r->get('/settings/checkout', 'AdminSettingController@checkout', ['permission' => 'settings.view']);
    $r->post('/settings/checkout', 'AdminSettingController@saveCheckout', ['permission' => 'settings.edit']);
    $r->get('/settings/watermark', 'AdminWatermarkController@index', ['permission' => 'settings.view']);
    $r->post('/settings/watermark', 'AdminWatermarkController@save', ['permission' => 'settings.edit']);
    $r->get('/settings/watermark/preview', 'AdminWatermarkController@preview', ['permission' => 'settings.view']);
    $r->post('/settings/watermark/apply', 'AdminWatermarkController@apply', ['permission' => 'settings.edit']);
    $r->post('/settings/watermark/remove', 'AdminWatermarkController@remove', ['permission' => 'settings.edit']);
    $r->get('/settings/sms', 'AdminMessagingController@sms', ['permission' => 'settings.view']);
    $r->post('/settings/sms', 'AdminMessagingController@saveSms', ['permission' => 'settings.edit']);
    $r->get('/settings/email', 'AdminMessagingController@email', ['permission' => 'settings.view']);
    $r->post('/settings/email', 'AdminMessagingController@saveEmail', ['permission' => 'settings.edit']);
    $r->get('/settings/notifications', 'AdminMessagingController@notifications', ['permission' => 'settings.view']);
    $r->post('/settings/notifications', 'AdminMessagingController@saveNotifications', ['permission' => 'settings.edit']);
    $r->post('/settings/test', 'AdminMessagingController@test', ['permission' => 'settings.edit']);
    $r->get('/settings/backup', 'AdminBackupController@index', ['permission' => 'settings.backup']);
    $r->post('/settings/backup', 'AdminBackupController@create', ['permission' => 'settings.backup']);
    $r->get('/settings/backup/download/{name:[a-z0-9-]+\.sql\.gz}', 'AdminBackupController@download', ['permission' => 'settings.backup']);
    $r->post('/settings/backup/delete/{name:[a-z0-9-]+\.sql\.gz}', 'AdminBackupController@destroy', ['permission' => 'settings.backup']);
    $r->post('/settings/backup/restore', 'AdminBackupController@restore', ['permission' => 'settings.backup']);
    $r->post('/settings/backup/schedule', 'AdminBackupController@schedule', ['permission' => 'settings.backup']);
    $r->get('/messages', 'AdminMessagingController@log', ['permission' => 'notifications.view']);
    $r->post('/messages/retry', 'AdminMessagingController@retry', ['permission' => 'notifications.send']);

    // CMS: pages, FAQ, blog
    $r->group(['prefix' => '/pages'], function (Router $r) {
        $r->get('/', 'AdminPageController@index', ['permission' => 'pages.view']);
        $r->get('/create', 'AdminPageController@create', ['permission' => 'pages.create']);
        $r->post('/', 'AdminPageController@store', ['permission' => 'pages.create']);
        $r->get('/{id:\d+}/edit', 'AdminPageController@edit', ['permission' => 'pages.edit']);
        $r->post('/{id:\d+}', 'AdminPageController@update', ['permission' => 'pages.edit']);
        $r->post('/{id:\d+}/delete', 'AdminPageController@destroy', ['permission' => 'pages.delete']);
    });
    $r->get('/faqs', 'AdminFaqController@index', ['permission' => 'pages.view']);
    $r->post('/faqs', 'AdminFaqController@save', ['permission' => 'pages.edit|pages.create']);
    $r->post('/faqs/{id:\d+}/delete', 'AdminFaqController@destroy', ['permission' => 'pages.delete']);
    $r->group(['prefix' => '/blog'], function (Router $r) {
        $r->get('/', 'AdminBlogController@index', ['permission' => 'pages.view']);
        $r->get('/create', 'AdminBlogController@create', ['permission' => 'pages.create']);
        $r->post('/', 'AdminBlogController@store', ['permission' => 'pages.create']);
        $r->get('/{id:\d+}/edit', 'AdminBlogController@edit', ['permission' => 'pages.edit']);
        $r->post('/{id:\d+}', 'AdminBlogController@update', ['permission' => 'pages.edit']);
        $r->post('/{id:\d+}/delete', 'AdminBlogController@destroy', ['permission' => 'pages.delete']);
    });

    // Support tickets + review moderation
    // Page builder (sections on any storefront page)
    $r->get('/page-builder', 'AdminPageBuilderController@index', ['permission' => 'page_builder.view']);
    $r->get('/page-builder/create', 'AdminPageBuilderController@create', ['permission' => 'page_builder.create']);
    $r->post('/page-builder', 'AdminPageBuilderController@store', ['permission' => 'page_builder.create']);
    $r->get('/page-builder/{id:\d+}/edit', 'AdminPageBuilderController@edit', ['permission' => 'page_builder.edit']);
    $r->post('/page-builder/{id:\d+}', 'AdminPageBuilderController@update', ['permission' => 'page_builder.edit']);
    $r->post('/page-builder/{id:\d+}/toggle', 'AdminPageBuilderController@toggle', ['permission' => 'page_builder.edit']);
    $r->post('/page-builder/{id:\d+}/move', 'AdminPageBuilderController@move', ['permission' => 'page_builder.edit']);
    $r->post('/page-builder/{id:\d+}/duplicate', 'AdminPageBuilderController@duplicate', ['permission' => 'page_builder.create']);
    $r->post('/page-builder/{id:\d+}/delete', 'AdminPageBuilderController@destroy', ['permission' => 'page_builder.delete']);

    // Header, menus & colours (storefront look)
    $r->get('/appearance', 'AdminAppearanceController@index', ['permission' => 'appearance.view']);
    $r->post('/appearance', 'AdminAppearanceController@saveHeader', ['permission' => 'appearance.edit']);
    $r->post('/appearance/links', 'AdminAppearanceController@storeLink', ['permission' => 'appearance.edit']);
    $r->post('/appearance/links/{id:\d+}', 'AdminAppearanceController@updateLink', ['permission' => 'appearance.edit']);
    $r->post('/appearance/links/{id:\d+}/toggle', 'AdminAppearanceController@toggleLink', ['permission' => 'appearance.edit']);
    $r->post('/appearance/links/{id:\d+}/move', 'AdminAppearanceController@moveLink', ['permission' => 'appearance.edit']);
    $r->post('/appearance/links/{id:\d+}/delete', 'AdminAppearanceController@deleteLink', ['permission' => 'appearance.edit']);

    // Payments (all methods) + delivery locations
    $r->get('/payments', 'AdminPaymentController@index', ['permission' => 'payments.view']);
    $r->get('/payments/export', 'AdminPaymentController@export', ['permission' => 'payments.export']);
    $r->post('/payments/{id:\d+}/verify', 'AdminPaymentController@verify', ['permission' => 'payments.edit']);
    $r->get('/locations', 'AdminLocationController@index', ['permission' => 'locations.view']);
    $r->post('/locations', 'AdminLocationController@store', ['permission' => 'locations.create']);
    $r->post('/locations/{id:\d+}', 'AdminLocationController@update', ['permission' => 'locations.edit']);
    $r->post('/locations/{id:\d+}/toggle', 'AdminLocationController@toggle', ['permission' => 'locations.edit']);
    $r->post('/locations/{id:\d+}/delete', 'AdminLocationController@destroy', ['permission' => 'locations.delete']);

    // Customers (registered + guest buyers)
    $r->group(['prefix' => '/customers'], function (Router $r) {
        $r->get('/', 'AdminCustomerController@index', ['permission' => 'customers.view']);
        $r->get('/export', 'AdminCustomerController@export', ['permission' => 'customers.export']);
        $r->get('/create', 'AdminCustomerController@create', ['permission' => 'customers.create']);
        $r->post('/', 'AdminCustomerController@store', ['permission' => 'customers.create']);
        $r->get('/{id:\d+}', 'AdminCustomerController@show', ['permission' => 'customers.view']);
        $r->post('/{id:\d+}/status', 'AdminCustomerController@status', ['permission' => 'customers.edit']);
        $r->post('/{id:\d+}/password', 'AdminCustomerController@password', ['permission' => 'customers.edit']);
        $r->post('/{id:\d+}/delete', 'AdminCustomerController@destroy', ['permission' => 'customers.delete']);
    });
    $r->group(['prefix' => '/support'], function (Router $r) {
        $r->get('/', 'AdminSupportController@index', ['permission' => 'support.view']);
        $r->get('/export', 'AdminSupportController@export', ['permission' => 'support.export']);
        $r->get('/{id:\d+}', 'AdminSupportController@show', ['permission' => 'support.view']);
        $r->post('/{id:\d+}/reply', 'AdminSupportController@reply', ['permission' => 'support.reply']);
        $r->post('/{id:\d+}', 'AdminSupportController@update', ['permission' => 'support.reply']);
    });
    $r->get('/reviews', 'AdminReviewController@index', ['permission' => 'reviews.view']);
    $r->post('/reviews/moderate', 'AdminReviewController@moderate', ['permission' => 'reviews.moderate']);
    $r->post('/reviews/settings', 'AdminReviewController@settings', ['permission' => 'reviews.moderate']);
    // Vendor types (Pharmacy, Grocery, Beauty ...): sign-up documents, rules, default commission
    $r->get('/vendor-types', 'AdminVendorTypeController@index', ['permission' => 'vendor_types.view']);
    $r->get('/vendor-types/create', 'AdminVendorTypeController@create', ['permission' => 'vendor_types.create']);
    $r->post('/vendor-types', 'AdminVendorTypeController@store', ['permission' => 'vendor_types.create']);
    $r->get('/vendor-types/{id:\d+}/edit', 'AdminVendorTypeController@edit', ['permission' => 'vendor_types.edit']);
    $r->post('/vendor-types/{id:\d+}', 'AdminVendorTypeController@update', ['permission' => 'vendor_types.edit']);
    $r->post('/vendor-types/{id:\d+}/toggle', 'AdminVendorTypeController@toggle', ['permission' => 'vendor_types.edit']);
    // Vendors
    $r->group(['prefix' => '/vendors'], function (Router $r) {
        $r->get('/', 'AdminVendorController@index', ['permission' => 'vendors.view']);
        $r->get('/{id:\d+}', 'AdminVendorController@show', ['permission' => 'vendors.view']);
        $r->post('/{id:\d+}/status', 'AdminVendorController@status', ['permission' => 'vendors.approve']);
        $r->post('/{id:\d+}/commission', 'AdminVendorController@commission', ['permission' => 'commissions.edit']);
        $r->post('/{id:\d+}/type', 'AdminVendorController@type', ['permission' => 'vendors.edit']);
        $r->post('/{id:\d+}/delete', 'AdminVendorController@destroy', ['permission' => 'vendors.delete']);
        $r->post('/{id:\d+}/restore', 'AdminVendorController@restore', ['permission' => 'vendors.delete']);
        $r->post('/{id:\d+}/adjust', 'AdminVendorController@adjust', ['permission' => 'vendor_payouts.approve']);
        $r->get('/{id:\d+}/documents/{doc:[a-z_]+}', 'AdminVendorController@document', ['permission' => 'vendors.view']);
    });

    // Commissions, payouts, vendor report
    $r->get('/commissions', 'AdminCommissionController@index', ['permission' => 'commissions.view']);
    $r->post('/commissions', 'AdminCommissionController@save', ['permission' => 'commissions.edit']);
    $r->get('/payouts', 'AdminPayoutController@index', ['permission' => 'vendor_payouts.view']);
    $r->post('/payouts/{id:\d+}', 'AdminPayoutController@decide', ['permission' => 'vendor_payouts.approve']);

    // Reports (sales, products, sellers, commission, stock, expiry, customers, delivery) - CSV / Excel / PDF export
    $r->get('/reports', 'AdminReportController@index', ['permission' => 'reports.view|accounting.view']);
    $r->get('/reports/{key:[a-z]+}', 'AdminReportController@show', ['permission' => 'reports.view|accounting.view']);
    $r->get('/reports/{key:[a-z]+}/export', 'AdminReportController@export', ['permission' => 'reports.export|accounting.export']);

    // Accounting: overview, P&L, income / expense entries, categories, VAT settings + registers
    $r->group(['prefix' => '/accounting'], function (Router $r) {
        $r->get('/', 'AdminAccountingController@index', ['permission' => 'accounting.view']);
        $r->get('/pnl', 'AdminAccountingController@pnl', ['permission' => 'accounting.view']);
        $r->get('/entries', 'AdminAccountingController@entries', ['permission' => 'accounting.view']);
        $r->get('/entries/export', 'AdminReportController@exportEntries', ['permission' => 'accounting.export']);
        $r->get('/entries/create', 'AdminAccountingController@createEntry', ['permission' => 'accounting.create']);
        $r->post('/entries', 'AdminAccountingController@storeEntry', ['permission' => 'accounting.create']);
        $r->get('/entries/{id:\d+}/edit', 'AdminAccountingController@editEntry', ['permission' => 'accounting.edit']);
        $r->post('/entries/{id:\d+}', 'AdminAccountingController@updateEntry', ['permission' => 'accounting.edit']);
        $r->post('/entries/{id:\d+}/delete', 'AdminAccountingController@deleteEntry', ['permission' => 'accounting.delete']);
        $r->get('/categories', 'AdminAccountingController@categoryList', ['permission' => 'accounting.view']);
        $r->post('/categories', 'AdminAccountingController@saveCategory', ['permission' => 'accounting.edit']);
        $r->post('/categories/{id:\d+}/delete', 'AdminAccountingController@deleteCategory', ['permission' => 'accounting.delete']);
        $r->get('/tax', 'AdminAccountingController@tax', ['permission' => 'accounting.view']);
        $r->post('/tax', 'AdminAccountingController@saveTax', ['permission' => 'accounting.configure']);
        $r->get('/{key:ledger|payouts|cod|refunds|vat}', 'AdminReportController@show', ['permission' => 'accounting.view']);
        $r->get('/{key:ledger|payouts|cod|refunds|vat}/export', 'AdminReportController@export', ['permission' => 'accounting.export']);
    });
    // Shipping (zones, rates, districts), couriers, shipments, COD
    $r->get('/shipping', 'AdminShippingController@index', ['permission' => 'shipping.view']);
    $r->post('/shipping/zones', 'AdminShippingController@saveZones', ['permission' => 'shipping.edit']);
    $r->post('/shipping/rates', 'AdminShippingController@saveRate', ['permission' => 'shipping.edit']);
    $r->post('/shipping/rates/{id:\d+}/delete', 'AdminShippingController@deleteRate', ['permission' => 'shipping.delete']);
    $r->post('/shipping/districts', 'AdminShippingController@saveDistricts', ['permission' => 'shipping.edit']);
    $r->post('/shipping/general', 'AdminShippingController@saveGeneral', ['permission' => 'shipping.edit']);
    $r->get('/couriers', 'AdminCourierController@index', ['permission' => 'couriers.view']);
    $r->post('/couriers/mock-mode', 'AdminCourierController@mockMode', ['permission' => 'couriers.edit']);
    $r->post('/couriers/{id:\d+}', 'AdminCourierController@update', ['permission' => 'couriers.edit']);
    $r->post('/couriers/{id:\d+}/test', 'AdminCourierController@test', ['permission' => 'couriers.edit']);
    $r->group(['prefix' => '/shipments'], function (Router $r) {
        $r->get('/', 'AdminShipmentController@index', ['permission' => 'shipments.view']);
        $r->get('/export', 'AdminShipmentController@export', ['permission' => 'shipments.export']);
        $r->get('/labels', 'AdminShipmentController@labels', ['permission' => 'shipments.view']);
        $r->get('/manifest', 'AdminShipmentController@manifest', ['permission' => 'shipments.view']);
        $r->post('/bulk', 'AdminShipmentController@bulk', ['permission' => 'shipments.view']);
        $r->post('/sync', 'AdminShipmentController@syncAll', ['permission' => 'shipments.edit']);
        $r->get('/{id:\d+}', 'AdminShipmentController@show', ['permission' => 'shipments.view']);
        $r->post('/{id:\d+}/book', 'AdminShipmentController@book', ['permission' => 'shipments.create']);
        $r->post('/{id:\d+}/cancel', 'AdminShipmentController@cancel', ['permission' => 'shipments.edit']);
        $r->post('/{id:\d+}/sync', 'AdminShipmentController@sync', ['permission' => 'shipments.edit']);
        $r->post('/{id:\d+}/status', 'AdminShipmentController@status', ['permission' => 'shipments.edit']);
        $r->post('/{id:\d+}/picked-up', 'AdminShipmentController@pickedUp', ['permission' => 'shipments.edit']);
    });
    $r->get('/cod', 'AdminCodController@index', ['permission' => 'shipments.view']);
    $r->post('/cod/settle', 'AdminCodController@settle', ['permission' => 'shipments.edit']);
});
