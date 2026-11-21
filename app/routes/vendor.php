<?php
/** Vendor panel routes (http://localhost/sasthobazar/vendor-panel/...) */
/** @var Router $router */

$router->get('/login', 'AuthController@vendorLoginForm', ['guard' => 'guest']);
$router->post('/login', 'AuthController@vendorLogin', ['guard' => 'guest']);
$router->get('/register', 'VendorRegisterController@form', ['guard' => 'guest']);
$router->post('/register', 'VendorRegisterController@store', ['guard' => 'guest']);
$router->post('/logout', 'AuthController@vendorLogout', ['guard' => 'vendor']);

// API for the pickup address dropdowns
$router->get('/api/locations', 'LocationController@index');
$router->get('/api/locations/{parentId:\d+}', 'LocationController@index');

$router->group(['guard' => 'vendor'], function (Router $r) {
    $r->get('/', 'VendorDashboardController@index');

    $r->get('/products', 'VendorOrderController@products');
    $r->get('/orders', 'VendorOrderController@index');
    $r->get('/orders/{id:\d+}', 'VendorOrderController@show');
    $r->get('/orders/{id:\d+}/slip', 'VendorOrderController@slip');
    $r->post('/orders/{id:\d+}/status', 'VendorOrderController@status');
    $r->get('/returns', 'VendorOrderController@returns');

    $r->get('/offers', 'VendorOfferController@index');
    $r->get('/offers/create', 'VendorOfferController@create');
    $r->post('/offers', 'VendorOfferController@store');
    $r->get('/offers/{id:\d+}/edit', 'VendorOfferController@edit');
    $r->post('/offers/{id:\d+}', 'VendorOfferController@update');
    $r->post('/offers/{id:\d+}/toggle', 'VendorOfferController@toggle');
    $r->post('/offers/{id:\d+}/delete', 'VendorOfferController@destroy');

    $r->get('/payouts', 'VendorWalletController@index');
    $r->post('/payouts', 'VendorWalletController@request');
    $r->get('/profile', 'VendorWalletController@profile');
    $r->post('/profile', 'VendorWalletController@update');
    $r->get('/documents/{doc:[a-z_]+}', 'VendorWalletController@document');
});
