<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\App;
use BBA\Core\Auth;
use BBA\Core\Crypto;
use BBA\Core\Request;
use BBA\Core\Response;
use BBA\Core\Session;
use BBA\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'layouts/panel'): never
    {
        if (!isset($data['title'])) {
            $data['title'] = null;
        }

        Response::html(View::render($template, $data, $layout));
    }

    protected function json(mixed $data, int $status = 200): never
    {
        Response::json($data, $status);
    }

    protected function redirect(string $path, int $status = 302): never
    {
        $location = preg_match('~^https?://~i', $path) ? $path : url($path);

        Response::redirect($location, $status);
    }

    protected function csrf(): void
    {
        $token = Request::input('_token');
        if (!is_string($token) || !Crypto::csrfValid($token)) {
            Response::html(
                View::render('errors/419', ['title' => 'Session expired'], 'layouts/plain'),
                419
            );
        }
    }

    protected function auth(): array
    {
        $account = Auth::user();
        if ($account === null) {
            Session::intended(Request::path() . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''));
            $this->redirect('/login');
        }

        return $account;
    }

    protected function developer(): array
    {
        $account = $this->auth();
        if ((int) ($account['is_developer'] ?? 0) !== 1) {
            App::forbidden('Developer access is granted manually. Ask the service owner to enable it for your account.');
        }

        return $account;
    }

    protected function moderator(): array
    {
        $account = $this->auth();
        if ((int) ($account['is_admin'] ?? 0) !== 1 && (int) ($account['is_moderator'] ?? 0) !== 1) {
            App::forbidden('Moderator access is granted manually. Ask the service owner to enable it for your account.');
        }

        return $account;
    }

    protected function admin(): array
    {
        $account = $this->auth();
        if ((int) ($account['is_admin'] ?? 0) !== 1) {
            App::forbidden('Administrator access is limited to the service owner.');
        }

        return $account;
    }

    protected function input(string $key, mixed $default = ''): mixed
    {
        $value = Request::input($key, $default);

        return is_string($value) ? trim($value) : $value;
    }

    protected function succeed(string $message): void
    {
        Session::set('_success', $message);
    }

    /** Return to the page that opened an inline editor, or to the classic settings page. */
    protected function afterEdit(string $fallback): never
    {
        $next = safe_next_path($this->input('next'));
        $this->redirect($next !== '' ? $next : $fallback);
    }

    protected function fail(string $message): void
    {
        Session::set('_error', $message);
    }

    protected function back(array $errors = [], array $old = [], string $fallback = '/account'): never
    {
        if ($errors !== []) {
            Session::set('_errors', $errors);
        }
        if ($old !== []) {
            Session::set('_old', $old);
        }

        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $path = parse_url($referer, PHP_URL_PATH);
        $query = parse_url($referer, PHP_URL_QUERY);
        $host = parse_url($referer, PHP_URL_HOST);
        $currentHost = (string) ($_SERVER['HTTP_HOST'] ?? '');

        if (is_string($path) && $path !== '' && str_starts_with($path, '/') && !str_starts_with($path, '//')
            && (is_string($host) ? strcasecmp($host, $currentHost) === 0 || $host === '' : true)) {
            $this->redirect($path . (is_string($query) && $query !== '' ? '?' . $query : ''));
        }

        $this->redirect($fallback);
    }

    protected function allowed(string $bucket, string $key, int $max, ?int $window = null): bool
    {
        $window ??= (int) \BBA\Core\Config::get('limits.window', 900);

        return \BBA\Core\RateLimiter::attempt($bucket, $key, $max, $window);
    }
}
