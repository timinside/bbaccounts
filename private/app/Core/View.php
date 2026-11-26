<?php
declare(strict_types=1);

namespace BBA\Core;

final class View
{
    public static function render(string $template, array $data = [], string $layout = 'layouts/panel'): string
    {
        $content = self::capture($template, $data);

        return self::capture($layout, array_merge($data, ['content' => $content]));
    }

    public static function capture(string $template, array $data = []): string
    {
        $file = BBA_APP . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
