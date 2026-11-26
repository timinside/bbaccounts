<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\App;
use BBA\Core\Audit;
use BBA\Core\Auth;
use BBA\Core\Config;
use BBA\Core\Crypto;
use BBA\Core\Db;
use BBA\Core\Request;
use BBA\Models\Badge;
use BBA\Models\Friend;
use BBA\Models\RecoveryCode;
use BBA\Models\Showcase;
use BBA\Models\SocialIdentity;
use BBA\Models\User;
use BBA\Models\Verification;
use BBA\Services\SocialAuth;

final class AdminController extends Controller
{
    protected function admin(): array
    {
        $admin = parent::admin();
        if (Request::method() !== 'GET'
            && preg_match('~^/admin/accounts/(\d+)~', Request::path(), $match) === 1
            && !$this->canManageAccount($admin, (int) $match[1])
        ) {
            App::forbidden('Only the Super Account can manage that account.');
        }

        return $admin;
    }

    private function canManageAccount(array $admin, int $targetId): bool
    {
        if ($targetId === (int) $admin['id']) {
            return is_super_account($admin);
        }

        return !is_super_account(['id' => $targetId]);
    }

    public function index(): void
    {
        $this->admin();
        User::restoreExpired();

        $totals = Db::one(
            'SELECT (SELECT COUNT(*) FROM accounts) AS total,
                    (SELECT COUNT(*) FROM projects) AS projects'
        ) ?? ['total' => 0, 'projects' => 0];

        $tab = in_array(Request::query('tab'), ['accounts', 'projects', 'banners', 'referrals', 'maintenance', 'updates'], true)
            ? (string) Request::query('tab')
            : 'accounts';
        $query = trim((string) Request::query('q', ''));
        $page = max(1, (int) Request::query('page', 1));
        $perPage = 20;
        $like = '%' . $query . '%';
        $accounts = [];
        $projects = [];
        $banners = [];
        $referrals = [];
        $total = 0;
        $pages = 1;
        $role = '';
        $status = '';

        if ($tab === 'banners') {
            $banners = Db::all('SELECT * FROM banners ORDER BY id DESC');
        } elseif ($tab === 'referrals') {
            $status = in_array(Request::query('status'), ['active', 'expired', 'disabled'], true)
                ? (string) Request::query('status')
                : '';

            $conditions = [];
            $params = [];
            if ($query !== '') {
                $conditions[] = '(a.display_name LIKE ? OR a.username LIKE ? OR a.email LIKE ? OR r.code LIKE ?)';
                $params = array_fill(0, 4, $like);
            }
            if ($status === 'active') {
                $conditions[] = 'r.is_active = 1 AND (r.expires_at IS NULL OR r.expires_at > NOW())';
            } elseif ($status === 'expired') {
                $conditions[] = 'r.expires_at IS NOT NULL AND r.expires_at <= NOW()';
            } elseif ($status === 'disabled') {
                $conditions[] = 'r.is_active = 0';
            }
            $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

            $total = (int) Db::value(
                'SELECT COUNT(*) FROM referrals r INNER JOIN accounts a ON a.id = r.account_id ' . $where,
                $params
            );
            $pages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $pages);
            $offset = ($page - 1) * $perPage;
            $referrals = Db::all(
                'SELECT r.*, a.display_name, a.username, a.email, a.status AS owner_status
                 FROM referrals r
                 INNER JOIN accounts a ON a.id = r.account_id ' . $where . '
                 ORDER BY r.id DESC
                 LIMIT ' . $perPage . ' OFFSET ' . $offset,
                $params
            );
        } elseif ($tab === 'accounts') {
            $role = in_array(Request::query('role'), ['admin', 'developer', 'tester', 'member'], true)
                ? (string) Request::query('role')
                : '';
            $status = in_array(Request::query('status'), ['active', 'suspended', 'banned'], true)
                ? (string) Request::query('status')
                : '';

            $conditions = [];
            $params = [];
            if ($query !== '') {
                $conditions[] = '(a.email LIKE ? OR a.display_name LIKE ?)';
                array_push($params, $like, $like);
            }
            if ($role === 'admin') {
                $conditions[] = 'a.is_admin = 1';
            } elseif ($role === 'developer') {
                $conditions[] = 'a.is_developer = 1';
            } elseif ($role === 'tester') {
                $conditions[] = 'a.is_tester = 1';
            } elseif ($role === 'member') {
                $conditions[] = 'a.is_admin = 0 AND a.is_developer = 0 AND a.is_tester = 0';
            }
            if ($status !== '') {
                $conditions[] = 'a.status = ?';
                $params[] = $status;
            }
            $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

            $total = (int) Db::value('SELECT COUNT(*) FROM accounts a ' . $where, $params);
            $pages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $pages);
            $offset = ($page - 1) * $perPage;
            $accounts = Db::all(
                'SELECT a.*,
                        (SELECT COUNT(*) FROM auth_sessions s
                         WHERE s.account_id = a.id AND s.revoked_at IS NULL AND s.expires_at > NOW()) AS session_count
                 FROM accounts a ' . $where . '
                 ORDER BY a.id DESC
                 LIMIT ' . $perPage . ' OFFSET ' . $offset,
                $params
            );
        } else {
            $status = in_array(Request::query('status'), ['active', 'disabled'], true)
                ? (string) Request::query('status')
                : '';

            $conditions = [];
            $params = [];
            if ($query !== '') {
                $conditions[] = '(p.name LIKE ? OR p.slug LIKE ? OR p.domain LIKE ? OR a.display_name LIKE ? OR a.email LIKE ?)';
                $params = array_fill(0, 5, $like);
            }
            if ($status !== '') {
                $conditions[] = 'p.status = ?';
                $params[] = $status;
            }
            $where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

            $total = (int) Db::value(
                'SELECT COUNT(*) FROM projects p INNER JOIN accounts a ON a.id = p.owner_id ' . $where,
                $params
            );
            $pages = max(1, (int) ceil($total / $perPage));
            $page = min($page, $pages);
            $offset = ($page - 1) * $perPage;
            $projects = Db::all(
                'SELECT p.*, a.display_name AS owner_name, a.email AS owner_email,
                        (SELECT COUNT(*) FROM project_redirects r WHERE r.project_id = p.id) AS redirect_count,
                        (SELECT COUNT(*) FROM project_credentials c
                         WHERE c.project_id = p.id AND c.revoked_at IS NULL) AS credential_count,
                        (SELECT COUNT(*) FROM oauth_tokens t
                         WHERE t.project_id = p.id AND t.revoked_at IS NULL AND t.expires_at > NOW()) AS active_token_count
                 FROM projects p
                 INNER JOIN accounts a ON a.id = p.owner_id ' . $where . '
                 ORDER BY p.id DESC
                 LIMIT ' . $perPage . ' OFFSET ' . $offset,
                $params
            );
        }

        $updateInfo = update_check($tab === 'updates');

        $this->view('admin/index', [
            'title' => 'Administrator',
            'totals' => $totals,
            'tab' => $tab,
            'query' => $query,
            'role' => $role,
            'status' => $status,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'accounts' => $accounts,
            'projects' => $projects,
            'banners' => $banners,
            'referrals' => $referrals,
            'updateInfo' => $updateInfo,
        ]);
    }

    private function canGrantAdmin(array $admin): bool
    {
        $ids = array_map('intval', (array) Config::get('admin.super_ids', []));

        return in_array((int) $admin['id'], $ids, true);
    }

    public function account(array $params): void
    {
        User::restoreExpired();
        $admin = $this->admin();
        $target = User::find((int) ($params['id'] ?? 0));

        if ($target === null) {
            App::notFound();
        }
        Badge::syncAutomatic($target);

        $this->view('admin/account', [
            'title' => 'Account actions',
            'target' => $target,
            'currentAdminId' => (int) $admin['id'],
            'canManage' => $this->canManageAccount($admin, (int) $target['id']),
            'canGrantAdmin' => $this->canGrantAdmin($admin),
            'sessionCount' => User::activeSessionCount((int) $target['id']),
            'sessions' => Db::all(
                'SELECT id, device_id, ip, user_agent, app_name, label, last_active_at, expires_at
                 FROM auth_sessions
                 WHERE account_id = ? AND revoked_at IS NULL AND expires_at > NOW()
                 ORDER BY last_active_at DESC',
                [(int) $target['id']]
            ),
            'badges' => Badge::all(),
            'assignedBadges' => array_column(Badge::forAccount((int) $target['id']), null, 'id'),
            'showcases' => Showcase::forAccount((int) $target['id']),
            'showcaseLabels' => Showcase::DEFAULT_TITLES,
            'friends' => Friend::forAccount((int) $target['id']),
            'verification' => Verification::for('profile', (int) $target['id']),
            'projectCount' => (int) Db::value(
                'SELECT COUNT(*) FROM projects WHERE owner_id = ?',
                [(int) $target['id']]
            ),
            'connections' => SocialIdentity::forAccount((int) $target['id']),
            'recoveryCodesCount' => RecoveryCode::countUnused((int) $target['id']),
            'notifications' => Db::all(
                'SELECT * FROM notifications WHERE account_id = ? ORDER BY id DESC LIMIT 20',
                [(int) $target['id']]
            ),
        ]);
    }

    public function accountPassword(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        if (User::find($id) === null) {
            App::notFound();
        }

        $password = (string) $this->input('password');
        if (mb_strlen($password) < 10 || !hash_equals($password, (string) $this->input('password_confirmation'))) {
            $this->fail('Use at least 10 characters and enter the confirmation exactly.');
            $this->redirect('/admin/accounts/' . $id . '#security');
        }

        User::update($id, ['password_hash' => Crypto::passwordHash($password), 'password_set' => 1]);
        Auth::revokeOtherSessions($id, 0);
        Db::insert('notifications', [
            'account_id' => $id,
            'actor_id' => (int) $admin['id'],
            'subject' => 'Your password was reset',
            'body' => 'An administrator set a new password for your account. All devices were signed out. Recovery codes still work.',
            'icon' => 'key',
            'link_text' => 'Security settings',
            'link_url' => url('/account/security'),
        ]);
        Audit::log('admin.password_reset', (int) $admin['id'], null, 'account', $id);
        $this->succeed('Password reset. Every session of this account was signed out.');
        $this->redirect('/admin/accounts/' . $id . '#security');
    }

    public function accountEmail(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        if (User::find($id) === null) {
            App::notFound();
        }

        $email = mb_strtolower(trim((string) $this->input('email')));
        if (!valid_email($email) || mb_strlen($email) > 254 || User::emailTaken($email, $id)) {
            $this->fail('Enter a valid email address that is not used by another account.');
            $this->redirect('/admin/accounts/' . $id . '#security');
        }

        User::update($id, ['email' => $email, 'email_verified_at' => null]);
        Auth::revokeOtherSessions($id, 0);
        Db::insert('notifications', [
            'account_id' => $id,
            'actor_id' => (int) $admin['id'],
            'subject' => 'Your email address changed',
            'body' => 'An administrator changed the email address on your account to ' . $email . '. All devices were signed out. Verify the new address in Security.',
            'icon' => 'mail',
            'link_text' => 'Security settings',
            'link_url' => url('/account/security'),
        ]);
        Audit::log('admin.email_changed', (int) $admin['id'], null, 'account', $id, ['email' => $email]);
        $this->succeed('Email changed. Every session of this account was signed out.');
        $this->redirect('/admin/accounts/' . $id . '#security');
    }

    public function accountRegistration(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $target = User::find($id);
        if ($target === null) {
            App::notFound();
        }
        if (!$this->canManageAccount($admin, $id)) {
            $this->fail('You cannot change this account.');
            $this->redirect('/admin/accounts/' . $id);
        }

        $raw = trim((string) $this->input('created_at'));
        $ts = strtotime($raw);
        if ($raw === '' || $ts === false) {
            $this->fail('Enter a valid registration date.');
            $this->redirect('/admin/accounts/' . $id . '#details');
        }

        $createdAt = date('Y-m-d H:i:s', $ts);
        User::update($id, ['created_at' => $createdAt]);
        Audit::log('admin.registration_changed', (int) $admin['id'], null, 'account', $id, ['created_at' => $createdAt]);
        $this->succeed('Registration date updated.');
        $this->redirect('/admin/accounts/' . $id . '#details');
    }

    public function accountConnectionUnlink(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $target = User::find($id);
        if ($target === null) {
            App::notFound();
        }
        if (!$this->canManageAccount($admin, $id)) {
            $this->fail('You cannot change this account.');
            $this->redirect('/admin/accounts/' . $id);
        }

        $identityId = (int) $this->input('identity_id');
        $identity = Db::one('SELECT * FROM auth_identities WHERE id = ? AND account_id = ? LIMIT 1', [$identityId, $id]);
        if ($identity === null) {
            $this->fail('Connection not found.');
            $this->redirect('/admin/accounts/' . $id . '#connections');
        }

        SocialIdentity::unlink($identityId, $id);
        Audit::log('admin.connection_unlinked', (int) $admin['id'], null, 'account', $id, [
            'provider' => (string) $identity['provider'],
            'provider_user_id' => (string) $identity['provider_user_id'],
        ]);
        $this->succeed(SocialAuth::name((string) $identity['provider']) . ' connection removed.');
        $this->redirect('/admin/accounts/' . $id . '#connections');
    }

    public function accountTwoFactor(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $target = User::find($id);
        if ($target === null) {
            App::notFound();
        }
        if (!$this->canManageAccount($admin, $id)) {
            $this->fail('You cannot change this account.');
            $this->redirect('/admin/accounts/' . $id);
        }

        $action = (string) $this->input('action');
        if ($action === 'disable_guard') {
            User::update($id, ['guard_enabled' => 0, 'guard_secret' => null, 'guard_last_step' => 0]);
            Audit::log('admin.2fa_guard_disabled', (int) $admin['id'], null, 'account', $id);
            $this->succeed('Authenticator/Guard 2FA disabled.');
        } elseif ($action === 'disable_telegram') {
            User::update($id, ['telegram_2fa' => 0]);
            Audit::log('admin.2fa_telegram_disabled', (int) $admin['id'], null, 'account', $id);
            $this->succeed('Telegram 2FA disabled.');
        } elseif ($action === 'revoke_recovery') {
            Db::run('DELETE FROM recovery_codes WHERE account_id = ?', [$id]);
            Audit::log('admin.2fa_recovery_revoked', (int) $admin['id'], null, 'account', $id);
            $this->succeed('Recovery codes revoked.');
        } else {
            $this->fail('Unknown 2FA action.');
        }

        $this->redirect('/admin/accounts/' . $id . '#twofa');
    }

    public function accountStatus(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $target = User::find($id);

        if ($target === null) {
            App::notFound();
        }
        if (!$this->canManageAccount($admin, $id)) {
            $this->fail('You cannot change your own account status.');
            $this->redirect('/admin/accounts/' . $id);
        }

        $action = (string) $this->input('action');
        $status = match ($action) {
            'suspend' => 'suspended',
            'ban' => 'banned',
            'restore' => 'active',
            default => null,
        };
        if ($status === null) {
            $this->fail('Choose an action.');
            $this->redirect('/admin/accounts/' . $id);
        }

        $until = null;
        if ($status !== 'active') {
            $input = (string) $this->input('until');
            if ($input !== '') {
                $timestamp = strtotime($input);
                if ($timestamp === false || $timestamp <= time()) {
                    $this->fail('Choose a future date and time, or leave it empty.');
                    $this->redirect('/admin/accounts/' . $id);
                }
                $until = date('Y-m-d H:i:s', $timestamp);
            }
        }

        $reason = mb_substr(trim((string) $this->input('reason')), 0, 500);
        $purgeAfter = null;
        if ($status === 'banned' && $until === null) {
            $purgeAfter = date('Y-m-d H:i:s', time() + max(1, (int) Config::get('account.purge_days', 90)) * 86400);
        }

        User::update($id, [
            'status' => $status,
            'status_until' => $until,
            'status_reason' => $status === 'active' || $reason === '' ? null : $reason,
            'purge_after' => $purgeAfter,
        ]);
        if ($status !== 'active') {
            Auth::revokeAllSessions($id);
        }

        Audit::log('admin.account_' . $action, (int) $admin['id'], null, 'account', $id, ['until' => $until, 'reason' => $reason]);
        $label = $status === 'active'
            ? 'Account restored.'
            : ($status === 'banned' ? 'Account banned' : 'Account suspended')
                . ($until === null ? ' indefinitely.' : ' until ' . format_date($until, 'M j, Y H:i') . '.')
                . ($purgeAfter !== null ? ' It will be deleted after ' . format_date($purgeAfter, 'M j, Y') . '.' : '');
        $this->succeed($label);
        $this->redirect('/admin/accounts/' . $id);
    }

    public function notifyAccount(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $target = User::find($id);

        if ($target === null) {
            App::notFound();
        }

        $subject = mb_substr(trim((string) $this->input('subject')), 0, 120);
        $body = mb_substr(trim((string) $this->input('body')), 0, 4000);
        $linkText = mb_substr(trim((string) $this->input('link_text')), 0, 60);
        $linkUrl = trim((string) $this->input('link_url'));
        $icon = (string) $this->input('icon', 'bell');
        if ($subject === '' || $body === '') {
            $this->fail('Add a subject and a message.');
            $this->redirect('/admin/accounts/' . $id);
        }
        if ($linkUrl !== '' && preg_match('~^https?://~i', $linkUrl) !== 1) {
            $this->fail('The notification link must start with http:// or https://.');
            $this->redirect('/admin/accounts/' . $id);
        }

        Db::insert('notifications', [
            'account_id' => $id,
            'actor_id' => (int) $admin['id'],
            'subject' => $subject,
            'body' => $body,
            'icon' => array_key_exists($icon, notification_icons()) ? $icon : 'bell',
            'link_text' => $linkText !== '' ? $linkText : null,
            'link_url' => $linkUrl !== '' ? $linkUrl : null,
        ]);
        Audit::log('admin.notification_sent', (int) $admin['id'], null, 'account', $id, ['subject' => $subject]);
        $this->succeed('Notification sent.');
        $this->redirect('/admin/accounts/' . $id);
    }

    public function accountProfile(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $target = User::find($id);

        if ($target === null) {
            App::notFound();
        }

        $description = trim((string) $this->input('description'));
        if (mb_strlen($description) > 280) {
            $this->back(['description' => 'Keep the description under 280 characters.'], [], '/admin/accounts/' . $id);
        }
        $removeAvatar = $this->input('remove_avatar') === '1';

        $changes = [];
        if ($description !== (string) ($target['description'] ?? '')) {
            $changes[] = 'description';
        }
        if ($removeAvatar && ($target['avatar_type'] ?? '') !== 'initials') {
            $changes[] = 'avatar';
        }
        if ($changes === []) {
            $this->succeed('No profile changes were made.');
            $this->redirect('/admin/accounts/' . $id);
        }

        $data = ['description' => $description !== '' ? $description : null];
        if (in_array('avatar', $changes, true)) {
            if (($target['avatar_type'] ?? '') === 'upload') {
                delete_upload('avatars', (string) ($target['avatar_value'] ?? ''));
            }
            $data['avatar_type'] = 'initials';
            $data['avatar_value'] = null;
            $data['avatar_color'] = null;
        }
        User::update($id, $data);

        Db::insert('notifications', [
            'account_id' => $id,
            'actor_id' => (int) $admin['id'],
            'subject' => 'Your profile was moderated',
            'body' => 'An administrator changed the ' . implode(' and ', $changes) . ' on your profile. Review it in Profile.',
            'icon' => 'shield',
            'link_text' => 'Open profile',
            'link_url' => url('/account/profile'),
        ]);
        Audit::log('admin.profile_moderated', (int) $admin['id'], null, 'account', $id, ['fields' => $changes]);
        $this->succeed('Profile updated and the account was notified.');
        $this->redirect('/admin/accounts/' . $id);
    }

    public function accountBadges(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $target = User::find($id);
        if ($target === null) {
            App::notFound();
        }

        $selected = array_map('intval', (array) $this->input('badges', []));
        $existing = array_column(Badge::forAccount($id), null, 'id');
        $changes = [];

        foreach (Badge::all() as $badge) {
            if ((string) $badge['rule'] !== 'manual') {
                continue;
            }
            $badgeId = (int) $badge['id'];
            $had = isset($existing[$badgeId]);
            $want = in_array($badgeId, $selected, true);
            if ($want && !$had) {
                Badge::assign($id, $badgeId, (int) $admin['id']);
                $changes[] = '+' . $badge['name'];
            } elseif (!$want && $had) {
                Badge::revoke($id, $badgeId);
                $changes[] = '-' . $badge['name'];
            }
        }

        if ($changes === []) {
            $this->succeed('No badge changes were made.');
        } else {
            Db::insert('notifications', [
                'account_id' => $id,
                'actor_id' => (int) $admin['id'],
                'subject' => 'Your badges changed',
                'body' => 'An administrator updated your badges: ' . implode(', ', $changes) . '.',
                'icon' => 'shield',
                'link_text' => 'Open profile',
                'link_url' => account_url($target),
            ]);
            Audit::log('admin.badges_updated', (int) $admin['id'], null, 'account', $id, ['changes' => $changes]);
            $this->succeed('Badges updated and the account was notified.');
        }

        $this->redirect('/admin/accounts/' . $id . '#badges');
    }

    public function project(array $params): void
    {
        $this->admin();
        $project = $this->findProject((int) ($params['id'] ?? 0));

        $this->view('admin/project', [
            'title' => 'Project actions',
            'project' => $project,
            'verification' => Verification::for('project', (int) $project['id']),
        ]);
    }

    public function projectStatus(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $project = $this->findProject($id);

        $disabled = $project['status'] === 'active';
        Db::update('projects', ['status' => $disabled ? 'disabled' : 'active'], 'id = ?', [$id]);

        if ($disabled) {
            Db::run('UPDATE oauth_tokens SET revoked_at = NOW() WHERE project_id = ? AND revoked_at IS NULL', [$id]);
            Db::run('UPDATE oauth_codes SET used_at = NOW() WHERE project_id = ? AND used_at IS NULL', [$id]);
        }

        Audit::log(
            $disabled ? 'admin.project_disabled' : 'admin.project_enabled',
            (int) $admin['id'],
            $id,
            'project',
            $id
        );
        $this->succeed($disabled ? 'Project disabled and its tokens revoked.' : 'Project enabled.');
        $this->redirect('/admin/projects/' . $id);
    }

    public function revokeAccountSession(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        if (User::find($id) === null) {
            App::notFound();
        }
        if (!$this->canManageAccount($admin, $id)) {
            $this->fail('Manage your own sessions in Security.');
            $this->redirect('/account/security');
        }

        $sessionId = (int) $this->input('session_id', 0);
        Auth::revokeSession($sessionId, $id);
        Audit::log('admin.session_revoked', (int) $admin['id'], null, 'session', $sessionId, ['account_id' => $id]);
        $this->succeed('Session signed out.');
        $this->redirect('/admin/accounts/' . $id . '#sessions');
    }

    public function revokeAllAccountSessions(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        if (User::find($id) === null) {
            App::notFound();
        }
        if (!$this->canManageAccount($admin, $id)) {
            $this->fail('Manage your own sessions in Security.');
            $this->redirect('/account/security');
        }

        Auth::revokeOtherSessions($id, 0);
        Audit::log('admin.sessions_revoked', (int) $admin['id'], null, 'account', $id);
        $this->succeed('Every session was signed out.');
        $this->redirect('/admin/accounts/' . $id . '#sessions');
    }

    public function deleteAccount(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $target = User::find($id);

        if ($target === null) {
            App::notFound();
        }
        if (!$this->canManageAccount($admin, $id)) {
            $this->fail('You cannot delete your own account.');
            $this->redirect('/admin/accounts/' . $id);
        }
        if (!hash_equals((string) $target['email'], (string) $this->input('confirmation'))) {
            $this->back(['confirmation' => 'Type the account email exactly to delete it.'], [], '/admin/accounts/' . $id);
        }

        Audit::log('admin.account_deleted', (int) $admin['id'], null, 'account', $id, [
            'email' => $target['email'],
            'display_name' => $target['display_name'],
        ]);
        Db::run('DELETE FROM accounts WHERE id = ?', [$id]);
        $this->succeed('Account deleted.');
        $this->redirect('/admin?tab=accounts');
    }

    public function deleteProject(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $project = Db::one('SELECT * FROM projects WHERE id = ? LIMIT 1', [$id]);

        if ($project === null) {
            App::notFound();
        }
        if (!hash_equals((string) $project['name'], (string) $this->input('confirmation'))) {
            $this->back(['confirmation' => 'Type the project name exactly to delete it.'], [], '/admin/projects/' . $id);
        }

        Audit::log('admin.project_deleted', (int) $admin['id'], null, 'project', $id, [
            'name' => $project['name'],
            'owner_id' => (int) $project['owner_id'],
        ]);
        Db::run('DELETE FROM projects WHERE id = ?', [$id]);
        $this->succeed('Project deleted.');
        $this->redirect('/admin?tab=projects');
    }

    public function accountRoles(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $target = User::find($id);

        if ($target === null) {
            App::notFound();
        }

        if (!$this->canManageAccount($admin, $id)) {
            $this->fail('You cannot change your own roles.');
            $this->redirect('/admin/accounts/' . $id);
        }

        $roles = [
            'is_developer' => $this->input('is_developer') === '1' ? 1 : 0,
            'is_tester' => $this->input('is_tester') === '1' ? 1 : 0,
            'is_moderator' => $this->input('is_moderator') === '1' ? 1 : 0,
            // Only the configured owner may move the administrator role.
            'is_admin' => $this->canGrantAdmin($admin) && $this->input('is_admin') === '1' ? 1 : (int) $target['is_admin'],
        ];

        $adminDays = max(1, (int) Config::get('account.admin_days', 90));
        if ($roles['is_admin'] === 1 && (int) ($target['is_admin'] ?? 0) !== 1 && account_age_days($target) < $adminDays) {
            $this->fail('Administrator role needs an account at least ' . $adminDays . ' days old.');
            $this->redirect('/admin/accounts/' . $id);
        }

        $changed = [];
        foreach ($roles as $field => $value) {
            if ((int) $target[$field] !== $value) {
                $changed[$field] = $value ? 'granted' : 'removed';
            }
        }

        if ($changed !== []) {
            User::update($id, $roles);
            $labels = ['is_developer' => 'Developer', 'is_tester' => 'Tester', 'is_moderator' => 'Moderator', 'is_admin' => 'Administrator'];
            $parts = [];
            foreach ($changed as $field => $state) {
                $parts[] = $labels[$field] . ' role ' . $state;
            }
            Db::insert('notifications', [
                'account_id' => $id,
                'actor_id' => (int) $admin['id'],
                'subject' => 'Your account roles changed',
                'body' => implode("\n", array_map('ucfirst', $parts)) . '.',
            ]);
            Audit::log('admin.roles_changed', (int) $admin['id'], null, 'account', $id, $changed);
        }

        $this->succeed($changed === [] ? 'Roles are unchanged.' : 'Roles updated and the account was notified.');
        $this->redirect('/admin/accounts/' . $id);
    }

    public function accountVerification(array $params): void
    {
        $this->saveVerification('profile', (int) ($params['id'] ?? 0));
        $this->redirect('/admin/accounts/' . (int) ($params['id'] ?? 0));
    }

    public function accountWarning(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        if (User::find($id) === null) {
            App::notFound();
        }

        $warning = mb_substr(trim((string) $this->input('warning')), 0, 255);
        User::update($id, ['warning_text' => $warning !== '' ? $warning : null]);
        Audit::log('admin.account_warning_saved', (int) $admin['id'], null, 'account', $id, ['warning' => $warning]);
        $this->succeed($warning !== '' ? 'Warning note saved.' : 'Warning note removed.');
        $this->redirect('/admin/accounts/' . $id);
    }

    public function projectVerification(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);
        $this->findProject($id);
        $this->saveVerification('project', $id);
        $this->redirect('/admin/projects/' . $id);
    }

    private function saveVerification(string $type, int $id): void
    {
        $this->csrf();
        $admin = $this->admin();

        $kind = $this->input('kind');
        if ($kind === 'none') {
            Verification::remove($type, $id);
            Audit::log('admin.verification_removed', (int) $admin['id'], null, $type, $id);
            $this->succeed('Verification check removed.');
            return;
        }

        if (!in_array($kind, ['gold', 'blue'], true)) {
            $this->fail('Choose a valid check type.');
            return;
        }

        $note = mb_substr(trim((string) $this->input('note')), 0, 255);
        Verification::save($type, $id, $kind, $note, (int) $admin['id']);
        Audit::log('admin.verification_saved', (int) $admin['id'], null, $type, $id, ['kind' => $kind]);
        $this->succeed(($kind === 'gold' ? 'Gold' : 'Blue') . ' check saved.');
    }

    public function accountPoints(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        if (User::find($id) === null) {
            App::notFound();
        }

        $points = max(0, min(1000000, (int) $this->input('points')));
        User::update($id, ['points' => $points]);
        Audit::log('admin.points_changed', (int) $admin['id'], null, 'account', $id, ['points' => $points]);
        $this->succeed('Point balance set to ' . $points . '.');
        $this->redirect('/admin/accounts/' . $id . '#points');
    }

    public function accountShowcase(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        if (User::find($id) === null || Showcase::count($id) >= Showcase::SLOT_MAX) {
            $this->fail('That account has no free showcase slots.');
            $this->redirect('/admin/accounts/' . $id . '#points');
        }

        $type = (string) $this->input('type');
        $existingTypes = array_column(Showcase::forAccount($id), 'type');
        if (!in_array($type, Showcase::TYPES, true) || in_array($type, Showcase::FIXED_TYPES, true) || in_array($type, $existingTypes, true)) {
            $this->fail('Choose a showcase type this account does not have yet.');
            $this->redirect('/admin/accounts/' . $id . '#points');
        }

        $title = trim((string) $this->input('admin_showcase_title'));
        if ($title === '') {
            $title = Showcase::DEFAULT_TITLES[$type] ?? 'Showcase';
        }
        $body = trim((string) $this->input('admin_showcase_text'));
        if ($body === '' && $type === 'media') {
            $body = trim((string) $this->input('media_url'));
        }
        $meta = null;
        if ($type === 'bestfriends') {
            $friendIds = array_map('intval', array_column(Friend::forAccount($id), 'id'));
            $picked = array_map('intval', (array) $this->input('bestfriends', []));
            $body = (string) json_encode(array_values(array_slice(
                array_unique(array_intersect($picked, $friendIds)), 0, Friend::BEST_FRIENDS_MAX
            )));
        }
        if ($type === 'reviews') {
            $body = '';
            $project = mb_substr(trim((string) $this->input('review_project')), 0, 60);
            $text = mb_substr(trim((string) $this->input('review_text')), 0, 500);
            if ($project !== '' && $text !== '') {
                $meta = (string) json_encode(['reviews' => [[
                    'project' => $project,
                    'rating' => max(1, min(5, (int) $this->input('review_rating', 5))),
                    'text' => $text,
                    'url' => mb_substr(trim((string) $this->input('review_url')), 0, 300),
                ]]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }

        Showcase::create($id, $type, mb_substr($title, 0, 60), mb_substr($body, 0, 500) !== '' ? mb_substr($body, 0, 500) : null, $meta);
        Audit::log('admin.showcase_added', (int) $admin['id'], null, 'account', $id, ['type' => $type]);
        $this->succeed('Showcase added.');
        $this->redirect('/admin/accounts/' . $id . '#points');
    }

    public function accountShowcaseVisibility(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        if (User::find($id) === null) {
            App::notFound();
        }

        $visible = array_map('intval', (array) $this->input('visible_showcases', []));
        foreach (Showcase::forAccount($id) as $showcase) {
            Showcase::setVisibility($id, (int) $showcase['id'], in_array((int) $showcase['id'], $visible, true));
        }
        Audit::log('admin.showcase_visibility', (int) $admin['id'], null, 'account', $id);
        $this->succeed('Showcase visibility saved.');
        $this->redirect('/admin/accounts/' . $id . '#points');
    }

    public function accountShowcaseWidth(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $showcase = Showcase::find((int) ($params['showcase'] ?? 0), $id);
        if ($showcase === null) {
            $this->fail('That showcase was not found.');
            $this->redirect('/admin/accounts/' . $id . '#points');
        }

        Showcase::setWidth($showcase, (string) $this->input('width'));
        Audit::log('admin.showcase_width', (int) $admin['id'], null, 'showcase', (int) $showcase['id'], ['width' => (string) $this->input('width')]);
        $this->succeed('Showcase width saved.');
        $this->redirect('/admin/accounts/' . $id . '#points');
    }

    public function deleteAccountShowcase(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $showcaseId = (int) ($params['showcase'] ?? 0);
        $showcase = Showcase::find($showcaseId, $id);
        if ($showcase === null || in_array((string) $showcase['type'], Showcase::FIXED_TYPES, true)
            || (string) $showcase['type'] === 'team') {
            $this->fail('That showcase cannot be removed.');
            $this->redirect('/admin/accounts/' . $id . '#points');
        }
        if ((string) $showcase['type'] === 'media') {
            delete_upload('media', (string) ($showcase['body'] ?? ''));
        }
        Db::run('DELETE FROM showcases WHERE id = ? AND account_id = ?', [$showcaseId, $id]);
        Audit::log('admin.showcase_deleted', (int) $admin['id'], null, 'account', $id, ['showcase' => $showcaseId]);
        $this->succeed('Showcase removed.');
        $this->redirect('/admin/accounts/' . $id . '#points');
    }

    public function deleteNotification(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $notificationId = (int) $this->input('notification_id', 0);

        Db::run('DELETE FROM notifications WHERE id = ? AND account_id = ?', [$notificationId, $id]);
        Audit::log('admin.notification_deleted', (int) $admin['id'], null, 'account', $id, [
            'notification_id' => $notificationId,
        ]);
        $this->succeed('Notification deleted.');
        $this->redirect('/admin/accounts/' . $id);
    }

    public function approveIcon(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $project = $this->findProject($id);

        $file = $_FILES['icon'] ?? null;
        $hasUpload = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($hasUpload) {
            [$name, $error] = save_image_upload($file, 'projects');
            if ($error !== null) {
                $this->back(['icon' => $error], [], '/admin/projects/' . $id);
            }
            if (!empty($project['icon_file'])) {
                delete_upload('projects', (string) $project['icon_file']);
            }
            Db::update('projects', ['icon_file' => $name, 'icon_approved' => 1], 'id = ?', [$id]);
            Db::insert('notifications', [
                'account_id' => (int) $project['owner_id'],
                'actor_id' => (int) $admin['id'],
                'subject' => 'Your project icon was changed',
                'body' => 'An administrator replaced the icon for ' . $project['name'] . '.',
                'icon' => 'grid',
                'link_text' => 'Open project',
                'link_url' => url('/developer/projects/' . $id),
            ]);
            Audit::log('admin.project_icon_changed', (int) $admin['id'], $id, 'project', $id, ['file' => $name]);
            $this->succeed('Project icon replaced.');
            $this->redirect('/admin/projects/' . $id);
        }

        Db::update('projects', ['icon_approved' => 1], 'id = ?', [$id]);
        Db::insert('notifications', [
            'account_id' => (int) $project['owner_id'],
            'actor_id' => (int) $admin['id'],
            'subject' => 'Your project icon was approved',
            'body' => 'The custom icon for ' . $project['name'] . ' is now visible on its public page.',
            'icon' => 'check',
        ]);
        Audit::log('admin.project_icon_approved', (int) $admin['id'], $id, 'project', $id);
        $this->succeed('Project icon approved.');
        $this->redirect('/admin/projects/' . $id);
    }

    public function storeBanner(): void
    {
        $this->csrf();
        $admin = $this->admin();

        $body = mb_substr(trim((string) $this->input('body')), 0, 2000);
        $linkText = mb_substr(trim((string) $this->input('link_text')), 0, 60);
        $linkUrl = trim((string) $this->input('link_url'));
        $bgColor = strtolower(trim((string) $this->input('bg_color')));
        if ($this->input('use_default_bg') === '1') {
            $bgColor = '';
        }

        if ($body === '') {
            $this->fail('Write the banner text.');
            $this->redirect('/admin?tab=banners');
        }
        if ($linkUrl !== '' && preg_match('~^https?://~i', $linkUrl) !== 1) {
            $this->fail('The banner link must start with http:// or https://.');
            $this->redirect('/admin?tab=banners');
        }
        if ($bgColor !== '' && preg_match('/^#[0-9a-f]{6}$/', $bgColor) !== 1) {
            $this->fail('Choose a valid banner color, or leave it empty for the default gradient.');
            $this->redirect('/admin?tab=banners');
        }

        Db::insert('banners', [
            'body' => $body,
            'link_text' => $linkText !== '' ? $linkText : null,
            'link_url' => $linkUrl !== '' ? $linkUrl : null,
            'bg_color' => $bgColor !== '' ? $bgColor : null,
            'is_active' => 1,
        ]);
        Audit::log('admin.banner_created', (int) $admin['id'], null, 'banner', null, ['body' => $body]);
        $this->succeed('Banner published.');
        $this->redirect('/admin?tab=banners');
    }

    public function deleteBanner(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);

        Db::run('DELETE FROM banners WHERE id = ?', [$id]);
        Audit::log('admin.banner_deleted', (int) $admin['id'], null, 'banner', $id);
        $this->succeed('Banner deleted.');
        $this->redirect('/admin?tab=banners');
    }

    public function saveHomeAds(): void
    {
        $this->csrf();
        $admin = $this->admin();

        $slot = (string) $this->input('slot');
        if (!in_array($slot, ['left', 'right'], true)) {
            $this->fail('Unknown banner slot.');
            $this->redirect('/admin?tab=banners');
        }

        $url = trim((string) $this->input('url'));
        if ($url !== '' && preg_match('~^https?://~i', $url) !== 1) {
            $this->fail('The banner link must start with http:// or https://.');
            $this->redirect('/admin?tab=banners');
        }

        $current = setting('home_ad_' . $slot . '_image');
        $file = $_FILES['image'] ?? null;
        $new = null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$new, $error] = save_image_upload($file, 'ads');
            if ($error !== null) {
                $this->fail($error);
                $this->redirect('/admin?tab=banners');
            }
        }

        if ($new !== null) {
            if ($current !== '') {
                delete_upload('ads', $current);
            }
            setting_save('home_ad_' . $slot . '_image', $new);
        } elseif ($this->input('remove') === '1' && $current !== '') {
            delete_upload('ads', $current);
            setting_save('home_ad_' . $slot . '_image', null);
        }

        setting_save('home_ad_' . $slot . '_url', $url !== '' ? $url : null);
        Audit::log('admin.home_ad_saved', (int) $admin['id'], null, 'setting', null, ['slot' => $slot]);
        $this->succeed(($slot === 'left' ? 'Left' : 'Right') . ' banner saved.');
        $this->redirect('/admin?tab=banners');
    }

    public function saveTrustedSites(): void
    {
        $this->csrf();
        $admin = $this->admin();

        $sites = [];
        foreach (preg_split('/\R+/', (string) $this->input('sites')) ?: [] as $line) {
            $site = strtolower(trim($line));
            $site = preg_replace('~^\*\.~', '', $site) ?? '';
            if (preg_match('~^[a-z0-9.-]+\.[a-z]{2,}$~i', $site) === 1) {
                $sites[$site] = $site;
            }
        }

        $sites = array_values($sites);
        setting_save('trusted_sites', $sites === [] ? null : (string) json_encode($sites));
        Audit::log('admin.trusted_sites_saved', (int) $admin['id'], null, 'setting', null, ['count' => count($sites)]);
        $this->succeed('Trusted sites saved.');
        $this->redirect('/admin?tab=banners');
    }

    public function maintenance(): void
    {
        $this->csrf();
        $admin = $this->admin();

        $enabled = $this->input('enabled') === '1';
        $message = mb_substr(trim((string) $this->input('message')), 0, 255);
        setting_save('maintenance_enabled', $enabled ? '1' : null);
        setting_save('maintenance_message', $message !== '' ? $message : null);
        Audit::log('admin.maintenance', (int) $admin['id'], null, 'setting', null, ['enabled' => $enabled]);
        $this->succeed($enabled ? 'Maintenance mode enabled. Visitors now see the 503 page.' : 'Maintenance mode disabled.');
        $this->redirect('/admin?tab=maintenance');
    }

    public function system(): void
    {
        $this->csrf();
        $admin = $this->admin();

        $version = trim((string) $this->input('version'));
        if ($version === '' || mb_strlen($version) > 32 || !preg_match('/^[0-9A-Za-z._-]+$/', $version)) {
            $this->fail('Version must be 1-32 chars: letters, numbers, dot, dash, underscore.');
            $this->redirect('/admin?tab=maintenance');
        }

        $file = BBA_PRIVATE . '/storage/system.json';
        $data = [];
        if (is_file($file)) {
            $decoded = json_decode((string) file_get_contents($file), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
        $data['version'] = $version;
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        Audit::log('admin.system', (int) $admin['id'], null, 'setting', null, ['version' => $version]);
        $this->succeed('System version updated to ' . $version . '. Users will be prompted to refresh.');
        $this->redirect('/admin?tab=maintenance');
    }

    public function referral(array $params): void
    {
        $this->csrf();
        $admin = $this->admin();
        $id = (int) ($params['id'] ?? 0);
        $referral = Db::one('SELECT * FROM referrals WHERE id = ? LIMIT 1', [$id]);
        if ($referral === null) {
            $this->fail('Referral link not found.');
            $this->redirect('/admin?tab=referrals');
        }

        $ownerPoints = max(0, min(1000, (int) $this->input('points_owner', 0)));
        $newPoints = max(0, min(1000, (int) $this->input('points_new', 0)));
        $active = (string) $this->input('active') === '1' ? 1 : 0;

        Db::run(
            'UPDATE referrals SET points_owner = ?, points_new = ?, is_active = ? WHERE id = ?',
            [$ownerPoints, $newPoints, $active, $id]
        );
        Audit::log('admin.referral_updated', (int) $admin['id'], null, 'referral', $id, [
            'points_owner' => $ownerPoints,
            'points_new' => $newPoints,
            'is_active' => $active,
        ]);
        $this->succeed('Referral link saved.');
        $this->redirect('/admin?tab=referrals');
    }

    private function findProject(int $id): array
    {
        $project = Db::one(
            'SELECT p.*, a.display_name AS owner_name, a.email AS owner_email,
                    (SELECT COUNT(*) FROM project_redirects r WHERE r.project_id = p.id) AS redirect_count,
                    (SELECT COUNT(*) FROM project_credentials c
                     WHERE c.project_id = p.id AND c.revoked_at IS NULL) AS credential_count,
                    (SELECT COUNT(*) FROM oauth_tokens t
                     WHERE t.project_id = p.id AND t.revoked_at IS NULL AND t.expires_at > NOW()) AS active_token_count
             FROM projects p
             INNER JOIN accounts a ON a.id = p.owner_id
             WHERE p.id = ?
             LIMIT 1',
            [$id]
        );

        if ($project === null) {
            App::notFound();
        }

        return $project;
    }
}
