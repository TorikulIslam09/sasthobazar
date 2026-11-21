<?php
/**
 * Router - small regex router with route groups and middleware.
 *
 *   $r = new Router('/sasthobazar/admin');
 *   $r->get('/login', 'AuthController@adminLoginForm', ['guard' => 'guest']);
 *
 *   $r->group(['guard' => 'admin', 'prefix' => '/roles'], function (Router $r) {
 *       $r->get('/', 'AdminRoleController@index', ['permission' => 'roles.view']);
 *       $r->post('/{id:\d+}/delete', 'AdminRoleController@destroy', ['permission' => 'roles.delete']);
 *   });
 *
 * Route options (all optional, merged with the enclosing group):
 *   guard      => 'admin' | 'vendor' | 'customer' | 'guest'
 *   permission => 'module.action' or ['a.view', 'b.view'] (ALL required); 'a.view|b.view' = ANY
 *   middleware => ['super_admin', 'can:roles.edit', ...]  (see core/Middleware.php)
 *   csrf       => false  skip the CSRF check (ONLY for server-to-server webhooks that check their own secret)
 * Every other POST is CSRF-checked automatically.
 */
declare(strict_types=1);

final class Router
{
    private array $routes = [];
    private string $prefix;
    private array $groupStack = [];
    /** @var callable|null */
    private $notFound = null;

    public function __construct(string $prefix = '')
    {
        $this->prefix = rtrim($prefix, '/');
    }

    public function get(string $path, callable|string $handler, array $opts = []): self
    {
        return $this->add('GET', $path, $handler, $opts);
    }

    public function post(string $path, callable|string $handler, array $opts = []): self
    {
        return $this->add('POST', $path, $handler, $opts);
    }

    public function any(string $path, callable|string $handler, array $opts = []): self
    {
        $this->add('GET', $path, $handler, $opts);
        return $this->add('POST', $path, $handler, $opts);
    }

    /** Share options (guard, permission, middleware, prefix) with all routes defined in $fn. */
    public function group(array $opts, callable $fn): void
    {
        $this->groupStack[] = $opts;
        $fn($this);
        array_pop($this->groupStack);
    }

    public function setNotFound(callable $fn): void
    {
        $this->notFound = $fn;
    }

    private function add(string $method, string $path, callable|string $handler, array $opts): self
    {
        $prefix = '';
        $merged = ['middleware' => []];
        foreach ([...$this->groupStack, $opts] as $o) {
            $prefix .= isset($o['prefix']) ? '/' . trim($o['prefix'], '/') : '';
            if (isset($o['guard'])) {
                $merged['guard'] = $o['guard'];
            }
            if (array_key_exists('csrf', $o)) {
                $merged['csrf'] = (bool) $o['csrf']; // false only for machine callbacks (courier webhooks) that verify their own token
            }
            foreach ((array) ($o['permission'] ?? []) as $p) {
                $merged['middleware'][] = 'can:' . $p;
            }
            foreach ((array) ($o['middleware'] ?? []) as $m) {
                $merged['middleware'][] = $m;
            }
        }

        $path = '/' . trim($prefix . '/' . trim($path, '/'), '/');
        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}#',
            static fn($m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $path
        );
        $this->routes[$method][] = ['regex' => '#^' . $regex . '$#u', 'handler' => $handler, 'opts' => $merged];
        return $this;
    }

    /** Current request path relative to the router prefix, e.g. "/products/5". */
    public function currentPath(): string
    {
        $uri = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        if ($this->prefix !== '' && stripos($uri, $this->prefix) === 0) {
            $uri = substr($uri, strlen($this->prefix));
        }
        $uri = preg_replace('#/index\.php$#i', '', $uri);
        return '/' . trim((string) $uri, '/');
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = $this->currentPath();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);

            if ($method === 'POST' && Upload::postTooLarge()) {
                // PHP dropped the whole body (post_max_size) - say so instead of "session expired"
                Helpers::flash('error', __('upload_post_too_large', ['mb' => ini_get('post_max_size')]));
                Helpers::back();
            }
            if ($method === 'POST' && ($route['opts']['csrf'] ?? true) && !CSRF::verifyRequest()) {
                if (Helpers::isAjax()) {
                    http_response_code(403); // 419 is not a standard code (Apache turns it into 500)
                    header('Content-Type: application/json; charset=UTF-8');
                    echo json_encode(['error' => __('csrf_expired')], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                Helpers::flash('error', __('csrf_expired'));
                Helpers::back();
            }

            Middleware::run($route['opts']);
            $this->call($route['handler'], $params);
            return;
        }

        // Path exists for another method?
        $other = $method === 'GET' ? 'POST' : 'GET';
        foreach ($this->routes[$other] ?? [] as $route) {
            if (preg_match($route['regex'], $path)) {
                $this->abort(405);
            }
        }
        $this->abort(404);
    }

    private function call(callable|string $handler, array $params): void
    {
        if (is_string($handler)) {
            [$class, $action] = explode('@', $handler, 2);
            if (!class_exists($class) || !method_exists($class, $action)) {
                throw new RuntimeException("Route handler not found: {$handler}");
            }
            $handler = [new $class(), $action];
        }
        $handler(...array_values($params));
    }

    public function abort(int $code): void
    {
        http_response_code($code);
        if ($code === 404 && $this->notFound) {
            ($this->notFound)();
            exit;
        }
        $view = in_array($code, [403, 404], true) ? 'errors/' . $code : 'errors/404';
        View::render($view, ['title' => (string) $code], 'blank');
        exit;
    }
}
