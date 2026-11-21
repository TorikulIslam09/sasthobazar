<?php
/**
 * Vendor panel front controller - http://localhost/sasthobazar/vendor-panel/
 */
declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

$router = new Router(BASE_PATH . '/vendor-panel');
require APP_PATH . '/routes/vendor.php';
$router->dispatch();
