<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\Audit;
use BBA\Core\Auth;
use BBA\Core\Config;
use BBA\Core\Crypto;
use BBA\Core\Db;
use BBA\Core\RateLimiter;
use BBA\Core\Request;
use BBA\Core\Session;
use BBA\Models\Guard;
use BBA\Models\Project;
use BBA\Models\RecoveryCode;
use BBA\Models\Referral;
use BBA\Models\User;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        $next = $this->safeNext(Request::query('next', ''));
        if (Auth::check() && Request::query('add') !== '1') {
            $this->redirect($next !== '' ? $next : '/account');
        }

        $this->view('auth/login', [
            'title' => 'Sign in',
            'next' => $next,
            'brand' => $this->brandFromNext($next),
            'email' => (string) Request::query('email', ''),
            'add' => Request::query('add') === '1',
        ], 'layouts/auth');
    }

    public function login(): void
    {
        $this->csrf();

        $email = mb_strtolower(trim((string) Request::input('email', '')));
        $password = (string) Request::input('password', '');
        $next = $this->safeNext(Request::input('next', ''));
        $add = Request::input('add') === '1';
        $old = ['email' => $email, 'next' => $next, 'add' => $add ? '1' : ''];

        $errors = [];
        if (!valid_email($email)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($password === '') {
            $errors['password'] = 'Enter your password.';
        }
        if ($errors !== []) {
            $this->back($errors, $old, $this->loginFallback($next, $add));
        }

        if (!turnstile_verify()) {
            $this->back(
                ['turnstile' => 'Complete the "Verify you are human" check and try again.'],
                $old,
                $this->loginFallback($next, $add)
            );
        }

        $ipAllowed = $this->allowed('login_ip', Request::ip(), 30);
        $emailAllowed = $this->allowed('login_email', hash('sha256', $email), (int) Config::get('limits.login', 8));
        if (!$ipAllowed || !$emailAllowed) {
            Audit::log('auth.rate_limited', null, null, 'email', null, ['email' => $email]);

            $this->back(
                ['form' => 'Too many attempts. Try again in a few minutes.'],
                $old,
                $this->loginFallback($next, $add)
            );
        }

        $account = Auth::attempt($email, $password);
        if ($account === null) {
            Audit::log('auth.login_failed', null, null, 'email', null, ['email' => $email]);

            $this->back(
                ['form' => 'Email or password is incorrect.'],
                $old,
                $this->loginFallback($next, $add)
            );
        }

        if (!empty($account['_blocked'])) {
            Audit::log('auth.login_blocked', (int) $account['id'], null, 'account', (int) $account['id'], [
                'status' => $account['status'],
            ]);

            $this->back(
                ['form' => User::blockMessage($account)],
                $old,
                $this->loginFallback($next, $add)
            );
        }

        if (self::twoFactorRequired($account)) {
            $confirmed = Guard::enabled($account) ? self::confirmGuardWithService($account) : null;
            if ($confirmed === true) {
                User::update((int) $account['id'], ['guard_last_step' => Guard::step()]);
            } else {
                Session::set('twofa_pending', [
                    'id' => (int) $account['id'],
                    'next' => $next,
                    'add' => Request::input('add') === '1' ? '1' : '0',
                ]);
                $this->redirect('/2fa');
            }
        }

        RateLimiter::clear('login_email', hash('sha256', $email));
        RateLimiter::clear('login_ip', Request::ip());
        Auth::login($account, oauth_app_name($next));
        Session::forget('_referral_code');
        $this->succeed('Signed in successfully.');

        $this->redirect($next !== '' ? $next : '/account');
    }

    public function referral(array $params): void
    {
        $code = trim((string) ($params['code'] ?? ''));
        if ($code !== '') {
            Referral::click($code);
        }

        $this->redirect('/register' . ($code !== '' ? '?ref=' . rawurlencode($code) : ''));
    }

    public function showRegister(): void
    {
        $next = $this->safeNext(Request::query('next', ''));
        $add = Request::query('add') === '1';
        if (Auth::check() && !$add) {
            $this->redirect($next !== '' ? $next : '/account');
        }

        $ref = trim((string) Request::query('ref', ''));
        if ($ref !== '') {
            if (Referral::findActiveByCode($ref) !== null) {
                Session::set('_referral_code', $ref);
            } else {
                Session::forget('_referral_code');
            }
        }

        $this->view('auth/register', [
            'title' => 'Create account',
            'next' => $next,
            'add' => $add,
            'brand' => $this->brandFromNext($next),
        ], 'layouts/auth');
    }

    public function register(): void
    {
        $this->csrf();

        $name = trim((string) Request::input('display_name', ''));
        $username = mb_strtolower(trim((string) Request::input('username', '')));
        $email = mb_strtolower(trim((string) Request::input('email', '')));
        $password = (string) Request::input('password', '');
        $confirm = (string) Request::input('password_confirmation', '');
        $terms = (string) Request::input('terms', '');
        $next = $this->safeNext(Request::input('next', ''));
        $add = Request::input('add') === '1';
        $old = [
            'display_name' => $name,
            'username' => $username,
            'email' => $email,
            'next' => $next,
            'add' => $add ? '1' : '',
        ];

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $errors['display_name'] = 'Use between 2 and 60 characters.';
        }
        if ($username !== '' && !User::usernameValid($username)) {
            $errors['username'] = 'Use 3-30 characters: letters, numbers, and underscores, starting with a letter.';
        } elseif ($username !== '' && User::usernameTaken($username)) {
            $errors['username'] = 'This username is already taken.';
        }
        if (!valid_email($email) || mb_strlen($email) > 254) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (User::emailTaken($email)) {
            $errors['email'] = 'An account with this email already exists.';
        }
        if (mb_strlen($password) < 10) {
            $errors['password'] = 'Use at least 10 characters.';
        }
        if (!hash_equals($password, $confirm)) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }
        if ($terms !== '1') {
            $errors['terms'] = 'Accept the terms to create an account.';
        }
        if ($errors !== []) {
            $this->back($errors, $old, $this->registerFallback($next, $add));
        }

        if (!turnstile_verify()) {
            $this->back(
                ['turnstile' => 'Complete the "Verify you are human" check and try again.'],
                $old,
                $this->registerFallback($next, $add)
            );
        }

        if (!$this->allowed('register_ip', Request::ip(), (int) Config::get('limits.register', 5) * 2)) {
            $this->back(
                ['form' => 'Too many accounts were created from this network. Try again later.'],
                $old,
                $this->registerFallback($next, $add)
            );
        }

        try {
            $accountId = User::create($email, Crypto::passwordHash($password), $name, true, $username !== '' ? $username : null);
        } catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $this->back(['email' => 'An account with this email already exists.'], $old, $this->registerFallback($next, $add));
            }
            throw $e;
        }

        $codes = RecoveryCode::generate($accountId);
        $account = User::find($accountId);
        if ($account === null) {
            throw new \RuntimeException('Account was not created.');
        }

        $referrer = null;
        $referralCode = (string) Session::pull('_referral_code', '');
        if ($referralCode !== '') {
            $referral = Referral::findActiveByCode($referralCode);
            if ($referral !== null) {
                $referrer = Referral::complete($referral, $accountId);
                if ($referrer !== null) {
                    Audit::log('referral.completed', $accountId, null, 'account', (int) $referrer['id'], [
                        'code' => $referralCode,
                    ]);
                }
            }
        }

        Auth::login($account, oauth_app_name($next));
        Session::set('_recovery_codes', $codes);
        welcome_notification($accountId);
        Audit::log('auth.registered', $accountId);
        $this->succeed($referrer !== null
            ? 'Account created. Your referral bonus is in. Save your recovery codes before continuing.'
            : 'Account created. Save your recovery codes before continuing.');

        $after = $next;
        if ($after === '' && $referrer !== null) {
            $handle = trim((string) ($referrer['username'] ?? ''));
            $after = '/u/' . rawurlencode($handle !== '' ? $handle : (string) (int) $referrer['id']);
        }
        $this->redirect('/account/recovery-codes' . ($after !== '' ? '?next=' . rawurlencode($after) : ''));
    }

    public function showRecover(): void
    {
        $add = Request::query('add') === '1';
        if (Auth::check() && !$add) {
            $this->redirect('/account');
        }

        $this->view('auth/recover', [
            'title' => 'Recover account',
            'email' => (string) Request::query('email', ''),
            'add' => $add,
        ], 'layouts/auth');
    }

    public function recover(): void
    {
        $this->csrf();

        $add = Request::input('add') === '1';
        $fallback = $add ? '/recover?add=1' : '/recover';
        $email = mb_strtolower(trim((string) Request::input('email', '')));
        $code = trim((string) Request::input('code', ''));
        $password = (string) Request::input('password', '');
        $confirm = (string) Request::input('password_confirmation', '');

        $errors = [];
        if (!valid_email($email)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($code === '') {
            $errors['code'] = 'Enter one of your recovery codes.';
        }
        if (mb_strlen($password) < 10) {
            $errors['password'] = 'Use at least 10 characters.';
        }
        if (!hash_equals($password, $confirm)) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }
        if ($errors !== []) {
            $this->back($errors, ['email' => $email], $fallback);
        }

        if (!turnstile_verify()) {
            $this->back(
                ['turnstile' => 'Complete the "Verify you are human" check and try again.'],
                ['email' => $email],
                $fallback
            );
        }

        $ipAllowed = $this->allowed('recovery_ip', Request::ip(), (int) Config::get('limits.recovery', 6) * 3);
        $emailAllowed = $this->allowed('recovery_email', hash('sha256', $email), (int) Config::get('limits.recovery', 6));
        if (!$ipAllowed || !$emailAllowed) {
            $this->back(['form' => 'Too many attempts. Try again in a few minutes.'], ['email' => $email], $fallback);
        }

        $account = User::findByEmail($email);
        $codeId = $account === null ? null : RecoveryCode::verify((int) $account['id'], $code);
        if ($account === null || $codeId === null) {
            Audit::log('auth.recovery_failed', $account === null ? null : (int) $account['id'], null, 'email', null, [
                'email' => $email,
            ]);

            $this->back(['form' => 'Email or recovery code is incorrect.'], ['email' => $email], $fallback);
        }

        if (!User::isAvailable($account)) {
            Audit::log('auth.recovery_blocked', (int) $account['id'], null, 'account', (int) $account['id'], [
                'status' => $account['status'],
            ]);

            // The code stays unused on purpose; a block is not a failed recovery attempt.
            $this->back(['form' => User::blockMessage($account)], ['email' => $email], $fallback);
        }

        RecoveryCode::markUsed($codeId);
        User::update((int) $account['id'], [
            'password_hash' => Crypto::passwordHash($password),
            'password_set' => 1,
        ]);
        Auth::revokeAllSessions((int) $account['id']);
        RateLimiter::clear('recovery_email', hash('sha256', $email));
        Audit::log('auth.password_recovered', (int) $account['id']);
        $this->succeed('Password updated. Sign in with your new password.');

        $this->redirect('/login' . ($add ? '?add=1&email=' . rawurlencode($email) : ''));
    }

    public function logout(): void
    {
        $this->csrf();
        Auth::logout();

        $this->redirect('/login?logout=1');
    }

    private function safeNext(mixed $next): string
    {
        return safe_next_path($next);
    }

    /** Local desktop confirmation: true approved, false denied, null unavailable. */
    public static function confirmGuardWithService(array $account): ?bool
    {
        $url = trim((string) Config::get('guard.service_url', ''));
        if ($url === '' || parse_url($url, PHP_URL_HOST) !== 'localhost'
            || !function_exists('curl_init') || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $payload = json_encode([
            'email' => (string) $account['email'],
            'app' => 'bbaccounts',
            'ip' => Request::ip(),
            'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return null;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return null;
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT_MS => 500,
            CURLOPT_TIMEOUT => 45,
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $decoded = is_string($body) && $status === 200 ? json_decode($body, true) : null;

        return is_array($decoded) && array_key_exists('approved', $decoded)
            ? $decoded['approved'] === true
            : null;
    }

    private function brandFromNext(string $next): ?array
    {
        if ($next === '') {
            return null;
        }

        $path = parse_url($next, PHP_URL_PATH);
        if ($path !== '/oauth/authorize') {
            return null;
        }

        parse_str((string) parse_url($next, PHP_URL_QUERY), $query);
        $clientId = (string) ($query['client_id'] ?? '');
        if ($clientId === '') {
            return null;
        }

        $project = Project::findByClientId($clientId);

        return $project === null ? null : [
            'name' => (string) $project['name'],
            'domain' => (string) $project['domain'],
            'accent' => (string) $project['accent_color'],
            'slug' => (string) $project['slug'],
        ];
    }

    private function loginFallback(string $next, bool $add = false): string
    {
        $query = [];
        if ($next !== '') {
            $query['next'] = $next;
        }
        if ($add) {
            $query['add'] = '1';
        }

        return '/login' . ($query !== [] ? '?' . http_build_query($query) : '');
    }

    private function registerFallback(string $next, bool $add = false): string
    {
        $query = [];
        if ($next !== '') {
            $query['next'] = $next;
        }
        if ($add) {
            $query['add'] = '1';
        }

        return '/register' . ($query !== [] ? '?' . http_build_query($query) : '');
    }

    public static function twoFactorRequired(array $account): bool
    {
        return Guard::enabled($account) || self::telegramTwoFactorEnabled($account);
    }

    private static function telegramTwoFactorEnabled(array $account): bool
    {
        return (int) ($account['telegram_2fa'] ?? 0) === 1
            && trim((string) ($account['telegram_chat_id'] ?? '')) !== '';
    }

    public function showTwoFactor(): void
    {
        $pending = Session::get('twofa_pending');
        if (!is_array($pending) || empty($pending['id'])) {
            $this->redirect('/login');
        }
        $account = User::find((int) $pending['id']);
        if ($account === null || !User::isAvailable($account)) {
            Session::forget('twofa_pending');
            $this->redirect('/login');
        }

        $this->view('auth/twofa', [
            'title' => 'Two-factor confirmation',
            'next' => (string) ($pending['next'] ?? ''),
            'guard' => Guard::enabled($account),
            'telegram' => self::telegramTwoFactorEnabled($account),
            'telegramUsername' => (string) ($account['telegram_username'] ?? ''),
        ], 'layouts/auth');
    }

    public function twoFactor(): void
    {
        $this->csrf();
        $pending = Session::get('twofa_pending');
        if (!is_array($pending) || empty($pending['id'])) {
            $this->redirect('/login');
        }

        $account = User::find((int) $pending['id']);
        if ($account === null || !User::isAvailable($account)) {
            Session::forget('twofa_pending');
            $this->redirect('/login');
        }

        $method = (string) Request::input('method', 'totp');
        $code = trim((string) Request::input('code', ''));
        $next = $this->safeNext((string) ($pending['next'] ?? ''));

        if ($method === 'telegram' && self::telegramTwoFactorEnabled($account)) {
            if (!self::verifyTelegramCode($account, $code)) {
                Audit::log('auth.2fa_failed', (int) $account['id'], null, 'account', (int) $account['id'], ['method' => 'telegram']);
                $this->back(['code' => 'That Telegram code is invalid or expired.'], [], '/2fa');
            }
        } elseif ($method === 'recovery') {
            $normalized = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($code));
            if ($normalized === '' || !RecoveryCode::verify((int) $account['id'], $normalized)) {
                Audit::log('auth.2fa_failed', (int) $account['id'], null, 'account', (int) $account['id'], ['method' => 'recovery']);
                $this->back(['code' => 'That recovery code is invalid or has already been used.'], [], '/2fa');
            }
            Audit::log('auth.recovery_used', (int) $account['id']);
        } else {
            $matchedStep = null;
            $ok = Guard::enabled($account)
                && Guard::verify(Guard::secretFor($account), $code, (int) ($account['guard_last_step'] ?? 0), false, $matchedStep);
            if (!$ok) {
                Audit::log('auth.2fa_failed', (int) $account['id'], null, 'account', (int) $account['id'], ['method' => 'totp']);
                $this->back(['code' => 'That code is invalid. Try the current code from your authenticator app.'], [], '/2fa');
            }
            User::update((int) $account['id'], ['guard_last_step' => $matchedStep ?? Guard::step()]);
        }

        Session::forget('twofa_pending');
        RateLimiter::clear('login_email', hash('sha256', (string) $account['email']));
        RateLimiter::clear('login_ip', Request::ip());
        Auth::login($account, oauth_app_name($next));
        Session::forget('_referral_code');
        $this->succeed('Signed in successfully.');
        $this->redirect($next !== '' ? $next : '/account');
    }

    public function sendTelegramCode(): void
    {
        $this->csrf();
        $pending = Session::get('twofa_pending');
        if (!is_array($pending) || empty($pending['id'])) {
            $this->redirect('/login');
        }
        $account = User::find((int) $pending['id']);
        if ($account === null || !self::telegramTwoFactorEnabled($account)) {
            $this->back(['code' => 'Telegram confirmation is not available for this account.'], [], '/2fa');
        }

        $code = (string) random_int(100000, 999999);
        User::update((int) $account['id'], [
            'telegram_login_code' => Crypto::hash($code),
            'telegram_login_expires_at' => date('Y-m-d H:i:s', time() + 600),
        ]);
        $sent = self::telegramSend((string) $account['telegram_chat_id'], "bbaccounts login code: {$code}\n\nIf this wasn't you, ignore this message.");
        if (!$sent) {
            $this->back(['code' => 'Could not send a Telegram code right now. Try your authenticator app or try again later.'], [], '/2fa');
        }

        $this->succeed('Code sent to Telegram.');
        $this->redirect('/2fa');
    }

    private static function verifyTelegramCode(array $account, string $code): bool
    {
        $hash = (string) ($account['telegram_login_code'] ?? '');
        $expires = (string) ($account['telegram_login_expires_at'] ?? '');
        if ($hash === '' || $expires === '' || strtotime($expires) < time()) {
            return false;
        }
        if (!Crypto::equals($hash, Crypto::hash(trim($code)))) {
            return false;
        }
        User::update((int) $account['id'], [
            'telegram_login_code' => null,
            'telegram_login_expires_at' => null,
        ]);
        return true;
    }

    public static function telegramSend(string $chatId, string $text): bool
    {
        $token = trim((string) Config::get('telegram.bot_token', ''));
        if ($token === '' || $chatId === '' || !function_exists('curl_init')) {
            return false;
        }
        $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
        $payload = json_encode(['chat_id' => $chatId, 'text' => $text], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return false;
        }
        $curl = curl_init($url);
        if ($curl === false) {
            return false;
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $decoded = is_string($body) ? json_decode($body, true) : null;
        return $status >= 200 && $status < 300 && is_array($decoded) && !empty($decoded['ok']);
    }
}
