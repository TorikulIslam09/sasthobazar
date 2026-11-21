<?php
/**
 * Storefront front controller.
 * All storefront URLs (http://localhost/sasthobazar/...) are rewritten here by the .htaccess files.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

// Support both /sasthobazar/login and /sasthobazar/public/login
$uriPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$prefix = stripos($uriPath, BASE_PATH . '/public') === 0 ? BASE_PATH . '/public' : BASE_PATH;

$router = new Router($prefix);
require APP_PATH . '/routes/web.php';
$router->dispatch();
