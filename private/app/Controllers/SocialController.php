<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\App;
use BBA\Core\Audit;
use BBA\Core\Auth;
use BBA\Core\Config;
use BBA\Core\Crypto;
use BBA\Core\Guard;
use BBA\Core\RateLimiter;
use BBA\Core\Request;
use BBA\Core\Session;
use BBA\Models\SocialIdentity;
use BBA\Models\User;
use BBA\Services\SocialAuth;

final class SocialController extends Controller
{
    private const SESSION_KEY = '_social_oauth';
    private const TTL = 600;

    public function start(array $params): void
    {
        $provider = (string) ($params['provider'] ?? '');
        if (!SocialAuth::exists($provider)) {
            App::notFound();
        }

        $link = Request::query('link') === '1';
        $account = Auth::user();
        $next = $this->safeNext(Request::query('next', ''));
        $add = Request::query('add') === '1';

        if ($link && $account === null) {
            $this->redirect('/login');
        }

        $fallback = $link ? '/account/connections?tab=social' : $this->loginUrl($next, $add);

        if (!SocialAuth::enabled($provider)) {
            $this->fail(SocialAuth::name($provider) . ' sign-in is not configured on this instance.');
            $this->redirect($fallback);
        }

        if ($link && $this->hasIdentity((int) $account['id'], $provider)) {
            $this->fail('This provider is already connected.');
            $this->redirect($fallback);
        }

        $state = Crypto::token(24);
        Session::set(self::SESSION_KEY, [
            'state' => $state,
            'provider' => $provider,
            'link' => $link,
            'account_id' => $link ? (int) $account['id'] : null,
            'next' => $next,
            'add' => $add,
            'expires' => time() + self::TTL,
        ]);

        $this->redirect(SocialAuth::authorizeUrl($provider, $state));
    }

    public function callback(array $params): void
    {
        $provider = (string) ($params['provider'] ?? '');
        if (!SocialAuth::exists($provider)) {
            App::notFound();
        }

        $pending = Session::get(self::SESSION_KEY);
        Session::forget(self::SESSION_KEY);

        $link = is_array($pending) && ($pending['link'] ?? false) === true;
        $next = is_array($pending) ? (string) ($pending['next'] ?? '') : '';
        $add = is_array($pending) && ($pending['add'] ?? false) === true;
        $fallback = $link ? '/account/connections?tab=social' : $this->loginUrl($next, $add);

        if ((string) Request::input('error', '') !== '') {
            $this->fail('Sign-in was cancelled.');
            $this->redirect($fallback);
        }

        if (!is_array($pending)
            || ($pending['provider'] ?? '') !== $provider
            || ($pending['state'] ?? '') !== (string) Request::input('state', '')
            || (int) ($pending['expires'] ?? 0) < time()) {
            $this->fail('That sign-in attempt expired. Start again.');
            $this->redirect($fallback);
        }

        try {
            $query = array_merge(is_array($_GET) ? $_GET : [], is_array($_POST) ? $_POST : []);
            $profile = SocialAuth::profile($provider, (string) Request::input('code', ''), $query);
        } catch (\Throwable $e) {
            error_log('social ' . $provider . ': ' . $e->getMessage());
            $detail = (bool) Config::get('debug', false) ? ' ' . $e->getMessage() : '';
            $this->fail('Could not complete sign-in with ' . SocialAuth::name($provider) . '. Try again.' . $detail);
            $this->redirect($fallback);
        }

        if ($link) {
            $this->finishLink($provider, $profile, (int) $pending['account_id']);

            return;
        }

        $this->finishLogin($provider, $profile, $next, $add);
    }

    private function finishLink(string $provider, array $profile, int $accountId): never
    {
        $account = Auth::user();
        if ($account === null || (int) $account['id'] !== $accountId) {
            $this->fail('Sign in again before connecting a provider.');
            $this->redirect('/login');
        }

        $identity = SocialIdentity::find($provider, (string) $profile['id']);
        if ($identity !== null) {
            if ((int) $identity['account_id'] !== $accountId) {
                $this->fail('That ' . SocialAuth::name($provider) . ' account is already linked to another bbaccounts account.');
                $this->redirect('/account/connections?tab=social');
            }

            $this->succeed(SocialAuth::name($provider) . ' is already connected.');
            $this->redirect('/account/connections?tab=social');
        }

        SocialIdentity::link($accountId, $provider, (string) $profile['id'], (string) $profile['email'], (string) ($profile['username'] ?? ''));
        Audit::log('account.social_linked', $accountId, null, 'identity', null, ['provider' => $provider]);
        $this->succeed(SocialAuth::name($provider) . ' connected.');
        $this->redirect('/account/connections?tab=social');
    }

    private function finishLogin(string $provider, array $profile, string $next, bool $add = false): never
    {
        $identity = SocialIdentity::find($provider, (string) $profile['id']);
        if ($identity !== null) {
            $account = User::find((int) $identity['account_id']);
            if ($account === null || !User::isAvailable($account)) {
                $this->fail('This account is not available.');
                $this->redirect($this->loginUrl($next, $add));
            }

            if (empty($identity['username'])) {
                SocialIdentity::updateUsername((int) $identity['id'], (string) ($profile['username'] ?? ''));
            }

            $this->loginOrTwoFactor($account, $next, $provider, 'auth.social_login', $add);
        }

        $existing = User::findByEmail((string) $profile['email']);
        // Yandex does not verify its email flag, so never auto-link an unlinked identity by email.
        if ($existing !== null && $provider !== 'yandex') {
            if (!User::isAvailable($existing)) {
                $this->fail('This account is not available.');
                $this->redirect($this->loginUrl($next, $add));
            }

            SocialIdentity::link((int) $existing['id'], $provider, (string) $profile['id'], (string) $profile['email'], (string) ($profile['username'] ?? ''));
            $this->loginOrTwoFactor($existing, $next, $provider, 'auth.social_linked', $add);
        }

        // Social sign-in never creates an account: the email must already be registered.
        $email = trim((string) ($profile['email'] ?? ''));
        if ($email === '') {
            $this->fail('Sign in with your bbaccounts account first, then connect ' . SocialAuth::name($provider) . ' in Connections. This provider does not provide an email address for sign-in.');
            $this->redirect($this->loginUrl($next, $add));
        }
        $this->fail(
            'An account with ' . $email . ' must exist first. '
            . ($provider === 'yandex'
                ? 'Sign in with email and password, then connect '
                : 'Create one with email and password, then connect ')
            . SocialAuth::name($provider) . ' in your settings.'
        );
        $this->redirect($this->loginUrl($next, $add));
    }

    private function loginOrTwoFactor(array $account, string $next, string $provider, string $auditEvent, bool $add = false): never
    {
        if (AuthController::twoFactorRequired($account)) {
            $confirmed = Guard::enabled($account) ? AuthController::confirmGuardWithService($account) : null;
            if ($confirmed === true) {
                User::update((int) $account['id'], ['guard_last_step' => Guard::step()]);
            } else {
                Session::set('twofa_pending', [
                    'id' => (int) $account['id'],
                    'next' => $next,
                    'add' => $add ? '1' : '0',
                ]);
                $this->redirect('/2fa');
            }
        }

        RateLimiter::clear('login_email', hash('sha256', mb_strtolower(trim((string) $account['email']))));
        RateLimiter::clear('login_ip', Request::ip());
        Auth::login($account, oauth_app_name($next));
        Audit::log($auditEvent, (int) $account['id'], null, null, null, ['provider' => $provider]);
        $this->succeed('Signed in with ' . SocialAuth::name($provider) . '.');
        $this->redirect($next !== '' ? $next : '/account');
    }

    private function hasIdentity(int $accountId, string $provider): bool
    {
        foreach (SocialIdentity::forAccount($accountId) as $identity) {
            if ($identity['provider'] === $provider) {
                return true;
            }
        }

        return false;
    }

    private function safeNext(mixed $next): string
    {
        if (!is_string($next)) {
            return '';
        }

        $next = trim($next);
        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, "\n") || str_contains($next, "\r")) {
            return '';
        }

        return $next;
    }

    private function loginUrl(string $next, bool $add = false): string
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
}
