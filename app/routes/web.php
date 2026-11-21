<?php
/** Storefront routes (http://localhost/sasthobazar/...) */
/** @var Router $router */

$router->get('/', 'HomeController@index');

// Catalog
$router->get('/products', 'ShopController@index');
$router->get('/offers', 'OfferController@index');
$router->get('/category/{slug}', 'ShopController@category');
$router->get('/brand/{slug}', 'ShopController@brand');
$router->get('/seller/{slug}', 'ShopController@seller');
$router->get('/product/{slug}', 'ShopController@product');
$router->get('/api/search', 'ShopController@suggest');

// Auth (customers: mobile number + password)
$router->get('/login', 'AuthController@loginForm', ['guard' => 'guest']);
$router->post('/login', 'AuthController@login', ['guard' => 'guest']);
$router->post('/login/guest', 'AuthController@guestStart', ['guard' => 'guest']); // guest checkout: mobile or email, no account
$router->get('/register', 'AuthController@registerForm', ['guard' => 'guest']);
$router->post('/register', 'AuthController@register', ['guard' => 'guest']);
$router->post('/logout', 'AuthController@logout');

// Cart (guests too; the cart moves into the account on login)
$router->get('/cart', 'CartController@index');
$router->post('/cart/add', 'CartController@add');
$router->post('/cart/update', 'CartController@update');
$router->post('/cart/remove', 'CartController@remove');
$router->post('/cart/coupon', 'CartController@coupon');

// Checkout: signed-in customers, or guests who gave a mobile / email on /login (the controller checks which)
$router->get('/checkout', 'CheckoutController@index');
$router->get('/checkout/quote', 'CheckoutController@quote');
$router->post('/checkout', 'CheckoutController@place');
$router->post('/checkout/otp/send', 'CheckoutController@otpSend');
$router->post('/checkout/otp/verify', 'CheckoutController@otpVerify');

// Guest order placed + create an account from it
$router->get('/order/placed/{no:[A-Z0-9]+}', 'OrderController@placed');
$router->post('/order/placed/{no:[A-Z0-9]+}/account', 'OrderController@createAccount');

// Order by prescription (customers or guests)
$router->get('/prescription', 'PrescriptionController@form');
$router->post('/prescription', 'PrescriptionController@store');
$router->get('/prescription/sent/{code:RX[0-9A-F]+}', 'PrescriptionController@sent');

// Order tracking without login (order no + mobile or email)
$router->get('/track', 'OrderController@track');

// Customer-only actions
$router->post('/product/{id:\d+}/review', 'ReviewController@store', ['guard' => 'customer']);
$router->post('/wishlist/toggle', 'WishlistController@toggle', ['guard' => 'customer']);

// CMS: pages, FAQ, blog / health tips
$router->get('/page/{slug:[a-z0-9-]+}', 'PageController@page');
$router->get('/faq', 'PageController@faq');
$router->get('/blog', 'PageController@blog');
$router->get('/blog/{slug:[a-z0-9-]+}', 'PageController@post');

// My account
$router->group(['guard' => 'customer', 'prefix' => '/account'], function (Router $r) {
    // Support tickets
    $r->get('/support', 'SupportController@index');
    $r->get('/support/new', 'SupportController@create');
    $r->post('/support', 'SupportController@store');
    $r->get('/support/{no:TK[0-9]+}', 'SupportController@show');
    $r->post('/support/{no:TK[0-9]+}/reply', 'SupportController@reply');
    $r->post('/support/{no:TK[0-9]+}/close', 'SupportController@close');

    $r->get('/', 'AccountController@index');
    $r->post('/profile', 'AccountController@update');
    $r->post('/password', 'AccountController@password');

    $r->get('/addresses', 'AddressController@index');
    $r->get('/addresses/create', 'AddressController@create');
    $r->post('/addresses', 'AddressController@store');
    $r->get('/addresses/{id:\d+}/edit', 'AddressController@edit');
    $r->post('/addresses/{id:\d+}', 'AddressController@update');
    $r->post('/addresses/{id:\d+}/delete', 'AddressController@destroy');
    $r->post('/addresses/{id:\d+}/default', 'AddressController@makeDefault');

    $r->get('/wishlist', 'WishlistController@index');

    $r->get('/orders', 'OrderController@index');
    $r->get('/orders/{no:[A-Z0-9]+}', 'OrderController@show');
    $r->get('/orders/{no:[A-Z0-9]+}/invoice', 'OrderController@invoice');
    $r->post('/orders/{no:[A-Z0-9]+}/cancel', 'OrderController@cancel');
    $r->post('/orders/{no:[A-Z0-9]+}/return', 'OrderController@requestReturn');
    $r->get('/prescriptions', 'PrescriptionController@mine');
    $r->get('/prescriptions/{id:\d+}', 'OrderController@prescriptionFile');
});

// Courier status webhooks (server to server: no CSRF, checked by the courier's secret token)
$router->post('/webhooks/courier/{code:[a-z]+}', 'WebhookController@courier', ['csrf' => false]);

// Location API for cascading dropdowns
$router->get('/api/locations', 'LocationController@index');
$router->get('/api/locations/{parentId:\d+}', 'LocationController@index');
