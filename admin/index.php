<?php
/**
 * Admin panel front controller - http://localhost/sasthobazar/admin/
 */
declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

$router = new Router(BASE_PATH . '/admin');
require APP_PATH . '/routes/admin.php';
$router->dispatch();
