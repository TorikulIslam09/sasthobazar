<?php
/**
 * View - renders app/views/{view}.php inside app/views/layouts/{layout}.php.
 * The view's HTML is available to the layout as $content.
 */
declare(strict_types=1);

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'main'): void
    {
        echo self::make($view, $data, $layout);
    }

    public static function make(string $view, array $data = [], ?string $layout = 'main'): string
    {
        $content = self::partial($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial('layouts/' . $layout, array_merge($data, ['content' => $content]));
    }

    public static function partial(string $view, array $data = []): string
    {
        $file = VIEW_PATH . '/' . str_replace(['..', '\\'], ['', '/'], $view) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
