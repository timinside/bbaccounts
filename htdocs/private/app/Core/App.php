<?php
declare(strict_types=1);

namespace BBA\Core;

final class App
{
    public static function run(): void
    {
        try {
            \BBA\Models\User::purgeDue();
        } catch (\Throwable $e) {
            error_log('[bbaccounts] purgeDue skipped: ' . $e->getMessage());
        }

        $path = Request::path();
        if (setting('maintenance_enabled', '0') === '1' && !self::maintenanceAllowed($path)) {
            $account = Auth::user();
            if ($account === null || (int) $account['is_admin'] !== 1) {
                self::unavailable(setting(
                    'maintenance_message',
                    'The site is down for maintenance. Sign in as an administrator to disable it.'
                ));

                return;
            }
        }

        $router = new Router();
        $routes = require BBA_APP . '/routes.php';
        foreach ($routes as [$method, $path, $handler]) {
            $router->add($method, $path, $handler);
        }

        $pending = Session::pending();
        $router->dispatch(Request::method(), Request::path());
        Session::sweep($pending);
    }

    /** Home, docs, sign-in and static files stay reachable while maintenance is on. */
    private static function maintenanceAllowed(string $path): bool
    {
        $path = '/' . trim($path, '/');

        return in_array($path, ['/', '/docs', '/login', '/logout', '/503'], true)
            || str_starts_with($path, '/assets/')
            || str_starts_with($path, '/uploads/');
    }

    public static function notFound(): never
    {
        if (Request::wantsJson()) {
            Response::json(['error' => 'not_found', 'error_description' => 'The requested resource does not exist.'], 404);
        }

        Response::html(View::render('errors/404', ['title' => 'Page not found'], 'layouts/plain'), 404);
    }

    public static function forbidden(string $message = 'You do not have access to this page.'): never
    {
        Response::html(View::render('errors/403', ['title' => 'Access denied', 'message' => $message], 'layouts/plain'), 403);
    }

    public static function unauthorized(string $message = 'Sign in to continue.'): never
    {
        Response::html(View::render('errors/401', ['title' => 'Authentication required', 'message' => $message], 'layouts/plain'), 401);
    }

    public static function paymentRequired(string $message = 'This page is not available on the current plan.'): never
    {
        Response::html(View::render('errors/402', ['title' => 'Payment required', 'message' => $message], 'layouts/plain'), 402);
    }

    public static function unavailable(string $message = 'The service is temporarily unavailable. Try again in a few minutes.'): never
    {
        Response::html(View::render('errors/503', ['title' => 'Service unavailable', 'message' => $message], 'layouts/plain'), 503);
    }

    public static function handleException(\Throwable $e): void
    {
        error_log(sprintf(
            "[%s] %s in %s:%d\n%s",
            date('c'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));

        if (Request::wantsJson()) {
            Response::json(['error' => 'server_error', 'error_description' => 'The server could not complete the request.'], 500);
        }

        try {
            $body = View::render('errors/500', [
                'title' => 'Something went wrong',
                'debug' => (bool) Config::get('debug', false),
                'exception' => $e,
            ], 'layouts/plain');
        } catch (\Throwable $inner) {
            $body = '<!doctype html><html><body><h1>500</h1><p>Something went wrong.</p></body></html>';
        }

        Response::html($body, 500);
    }
}
