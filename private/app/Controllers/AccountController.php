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
use BBA\Core\Session;
use BBA\Models\Badge;
use BBA\Models\Friend;
use BBA\Models\Guard;
use BBA\Models\ProfileLike;
use BBA\Models\RecoveryCode;
use BBA\Models\Referral;
use BBA\Models\Showcase;
use BBA\Models\SocialIdentity;
use BBA\Models\Team;
use BBA\Models\Ticket;
use BBA\Models\User;
use BBA\Services\ForumNews;
use BBA\Services\OAuthService;
use BBA\Services\SocialAuth;

final class AccountController extends Controller
{
    public function index(): void
    {
        $account = $this->auth();
        $accountId = (int) $account['id'];

        $consents = Db::all(
            'SELECT c.scope, c.created_at, c.updated_at, p.id AS project_id, p.name, p.slug, p.domain, p.accent_color
             FROM oauth_consents c
             INNER JOIN projects p ON p.id = c.project_id
             WHERE c.account_id = ?
             ORDER BY c.updated_at DESC',
            [$accountId]
        );

        $projectCount = (int) $account['is_developer'] === 1
            ? (int) Db::value('SELECT COUNT(*) FROM projects WHERE owner_id = ?', [$accountId])
            : 0;

        $notifications = Db::all(
            'SELECT * FROM notifications
             WHERE account_id = ?
             ORDER BY id DESC LIMIT 10',
            [$accountId]
        );
        if ($notifications !== []) {
            Db::run('UPDATE notifications SET read_at = NOW() WHERE account_id = ? AND read_at IS NULL', [$accountId]);
        }

        $testerDays = max(1, (int) Config::get('account.tester_days', 30));
        $age = account_age_days($account);

        $forumNews = ForumNews::latest();
        $this->view('account/index', [
            'title' => 'Overview',
            'account' => $account,
            'points' => (int) ($account['points'] ?? 0),
            'consents' => $consents,
            'activity' => User::recentActivity($accountId, 3),
            'projectCount' => $projectCount,
            'notifications' => $notifications,
            'forumNews' => $forumNews['items'],
            'forumNewsError' => $forumNews['error'],
        ]);
    }

    public function notifications(): void
    {
        $account = $this->auth();
        $notifications = Db::all(
            'SELECT * FROM notifications WHERE account_id = ? ORDER BY id DESC LIMIT 100',
            [(int) $account['id']]
        );
        Db::run('UPDATE notifications SET read_at = NOW() WHERE account_id = ? AND read_at IS NULL', [(int) $account['id']]);

        $this->view('account/notifications', [
            'title' => 'Notifications',
            'account' => $account,
            'notifications' => $notifications,
        ]);
    }

    public function deleteNotification(): void
    {
        $this->csrf();
        $account = $this->auth();
        $id = (int) $this->input('notification_id', 0);

        Db::run('DELETE FROM notifications WHERE id = ? AND account_id = ?', [$id, (int) $account['id']]);
        $this->succeed('Notification deleted.');

        $this->redirect('/account/notifications');
    }

    public function deleteAllNotifications(): void
    {
        $this->csrf();
        $account = $this->auth();

        Db::run('DELETE FROM notifications WHERE account_id = ?', [(int) $account['id']]);
        $this->succeed('All notifications deleted.');

        $this->redirect('/account/notifications');
    }

    public function profile(): void
    {
        $account = $this->auth();
        $testerDays = max(1, (int) Config::get('account.tester_days', 30));
        $adminDays = max(1, (int) Config::get('account.admin_days', 90));
        $age = account_age_days($account);
        $superIds = array_map('intval', (array) Config::get('admin.super_ids', []));
        Badge::syncAutomatic($account);

        $this->view('account/profile', [
            'title' => 'Profile',
            'account' => $account,
            'testerEligible' => (int) $account['is_tester'] !== 1 && $age >= $testerDays,
            'testerDaysLeft' => max(0, $testerDays - $age),
            'testerDays' => $testerDays,
            'testerRequested' => Ticket::hasOpen((int) $account['id'], 'tester'),
            'testerTicketId' => Ticket::openId((int) $account['id'], 'tester'),
            'isSuper' => in_array((int) $account['id'], $superIds, true),
            'adminDays' => $adminDays,
            'adminDaysLeft' => max(0, $adminDays - $age),
            'adminEligible' => $age >= $adminDays,
            'developerRequested' => Ticket::hasOpen((int) $account['id'], 'developer'),
            'developerTicketId' => Ticket::openId((int) $account['id'], 'developer'),
            'adminRequested' => Ticket::hasOpen((int) $account['id'], 'admin'),
            'adminTicketId' => Ticket::openId((int) $account['id'], 'admin'),
            'showcases' => Showcase::forAccount((int) $account['id']),
            'showcasesAllowed' => showcases_allowed($account),
            'showcaseDays' => (int) Config::get('account.showcase_days', 30),
            'showcaseSlots' => Showcase::slotsFor($account),
            'slotCost' => Showcase::SLOT_COST,
            'mediaCost' => Showcase::MEDIA_COST,
            'badges' => Badge::forAccount((int) $account['id']),
            'friends' => Friend::forAccount((int) $account['id']),
        ]);
    }

    public function friends(): void
    {
        $account = $this->auth();

        $this->view('account/friends', [
            'title' => 'Friends',
            'account' => $account,
            'friends' => Friend::forAccount((int) $account['id']),
            'incoming' => Friend::pendingIncoming((int) $account['id']),
            'outgoing' => Friend::pendingOutgoing((int) $account['id']),
            'friendLimit' => Friend::limitFor((int) $account['id']),
            'friendMax' => Friend::MAX,
            'friendCost' => Friend::SLOT_COST,
            'friendSlot' => Friend::SLOT,
        ]);
    }

    public function referrals(): void
    {
        $account = $this->auth();
        $id = (int) $account['id'];

        $this->view('account/referrals', [
            'title' => 'Referrals',
            'account' => $account,
            'links' => Referral::forAccount($id),
            'slots' => Referral::slotsFor($id),
            'invited' => Referral::invited($id),
        ]);
    }

    public function createReferral(): void
    {
        $this->csrf();
        $account = $this->auth();
        $days = (int) $this->input('days', Referral::DEFAULT_DAYS);

        if ($days < Referral::DAYS_MIN || $days > Referral::DAYS_MAX) {
            $this->fail('Choose how many days the link stays active (' . Referral::DAYS_MIN . '–' . Referral::DAYS_MAX . ').');
            $this->redirect('/account/referrals');
        }

        $cost = Referral::dayCost($days);
        if ((int) $account['points'] < $cost) {
            $this->fail('A ' . $days . '-day link costs ' . $cost . ' points. You need ' . ($cost - (int) $account['points']) . ' more points.');
            $this->redirect('/account/referrals');
        }

        $link = Referral::create((int) $account['id'], $days);
        if ($link === null) {
            $this->fail('Every slot is taken. Buy another slot or delete an existing link.');
            $this->redirect('/account/referrals');
        }

        Audit::log('account.referral_created', (int) $account['id'], null, 'referral', (int) $link['id'], ['days' => $days, 'cost' => $cost]);
        $this->succeed('Referral link created for ' . $days . ' days.' . ($cost > 0 ? ' ' . $cost . ' points spent.' : ''));
        $this->redirect('/account/referrals');
    }

    public function deleteReferral(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $id = (int) ($params['id'] ?? 0);

        if (!Referral::remove($id, (int) $account['id'])) {
            $this->fail('Referral link not found.');
            $this->redirect('/account/referrals');
        }

        Audit::log('account.referral_deleted', (int) $account['id'], null, 'referral', $id);
        $this->succeed('Referral link deleted. The slot is free again.');
        $this->redirect('/account/referrals');
    }

    public function buyReferralSlot(): void
    {
        $this->csrf();
        $account = $this->auth();
        if (!Referral::buySlot($account)) {
            $this->fail('You need ' . Referral::SLOT_COST . ' points and fewer than ' . Referral::SLOT_MAX . ' referral slots.');
            $this->redirect('/account/referrals');
        }

        Audit::log('account.referral_slot_bought', (int) $account['id']);
        $this->succeed('Referral slot added.');
        $this->redirect('/account/referrals');
    }

    public function buyFriendSlot(): void
    {
        $this->csrf();
        $account = $this->auth();
        if (!Friend::buySlot($account)) {
            $this->fail('You need ' . Friend::SLOT_COST . ' points and fewer than ' . Friend::MAX . ' friends to buy more slots.');
            $this->redirect('/account/friends');
        }

        Audit::log('account.friend_slot_bought', (int) $account['id']);
        $this->succeed('Friend limit increased by ' . Friend::SLOT . '.');
        $this->redirect('/account/friends');
    }

    public function updatePrivacy(): void
    {
        $this->csrf();
        $account = $this->auth();
        $private = $this->input('private_profile') === '1' ? 1 : 0;
        if ($private !== (int) ($account['private_profile'] ?? 0)) {
            if ($private === 1) {
                Showcase::saveVisible((int) $account['id']);
            } else {
                Showcase::restoreVisible((int) $account['id']);
            }
        }

        User::update((int) $account['id'], [
            'private_profile' => $private,
            'share_social' => $this->input('share_social') === '1' ? 1 : 0,
            'show_role_admin' => $this->input('show_role_admin') === '1' ? 1 : 0,
            'show_role_tester' => $this->input('show_role_tester') === '1' ? 1 : 0,
            'show_role_developer' => $this->input('show_role_developer') === '1' ? 1 : 0,
            'show_role_moderator' => $this->input('show_role_moderator') === '1' ? 1 : 0,
        ]);
        Audit::log('account.privacy_updated', (int) $account['id']);
        $this->succeed('Privacy preferences saved.');

        $this->afterEdit('/account/profile');
    }

    /** Inline description edit from the public profile, using the same 280-character rule. */
    public function updateInlineDescription(): void
    {
        $this->csrf();
        $account = $this->auth();
        $description = trim((string) $this->input('description'));
        if (mb_strlen($description) > 280) {
            $this->fail('Use at most 280 characters.');
        } else {
            User::update((int) $account['id'], ['description' => $description !== '' ? $description : null]);
            Audit::log('account.description_updated', (int) $account['id'], null, 'account', (int) $account['id']);
            $this->succeed('Description saved.');
        }
        $next = safe_next_path($this->input('next'));
        $this->redirect($next !== '' ? $next : '/u/' . (string) ($account['username'] ?? $account['id']));
    }

    public function switchAccount(): void
    {
        $account = $this->auth();
        $accounts = array_values(array_filter(
            Auth::switchAccounts(),
            static fn (array $row): bool => (int) $row['id'] !== (int) $account['id']
        ));

        $this->view('account/switch', [
            'title' => 'Switch account',
            'account' => $account,
            'accounts' => $accounts,
            'next' => safe_next_path(Request::query('next', '')),
        ]);
    }

    public function switchTo(): void
    {
        $this->csrf();
        $account = $this->auth();
        $id = (int) $this->input('account_id', 0);
        $next = safe_next_path($this->input('next'));

        if ($id === (int) $account['id'] || !Auth::switchToAccount($id)) {
            $this->fail('That account is no longer available. Sign in to it again.');
            $this->redirect('/account/switch' . ($next !== '' ? '?next=' . rawurlencode($next) : ''));
        }

        $this->succeed('Account switched.');
        $this->redirect($next !== '' ? $next : '/account');
    }

    public function requestTester(): void
    {
        $this->csrf();
        $account = $this->auth();
        $id = (int) $account['id'];
        $testerDays = max(1, (int) Config::get('account.tester_days', 30));

        if ((int) ($account['is_tester'] ?? 0) === 1 || account_age_days($account) < $testerDays || Ticket::hasOpen($id, 'tester')) {
            $this->fail('You cannot apply for tester access right now.');
            $this->redirect('/account/profile#tester');
        }
        if (!level_ok($account, 'tester')) {
            $this->fail(level_gate_error('tester'));
            $this->redirect('/account/profile#tester');
        }

        $note = mb_substr(trim((string) $this->input('note')), 0, 500);
        $body = "Tester application from " . $account['email'] . " (@" . (string) ($account['username'] ?? '') . ").";
        if ($note !== '') {
            $body .= "\n" . $note;
        }
        Ticket::create($id, 'tester', $body);
        Audit::log('account.tester_applied', $id, null, 'account', $id);
        $this->succeed('Application sent to the moderators.');
        $this->redirect('/account/profile#tester');
    }

    public function requestDeveloper(): void
    {
        $this->csrf();
        $account = $this->auth();
        $id = (int) $account['id'];
        if ((int) ($account['is_developer'] ?? 0) === 1 || Ticket::hasOpen($id, 'developer')) {
            $this->fail('You cannot apply for developer access right now.');
            $this->redirect('/account/profile#roles');
        }
        if (!level_ok($account, 'developer')) {
            $this->fail(level_gate_error('developer'));
            $this->redirect('/account/profile#roles');
        }

        Ticket::create($id, 'developer', 'Developer application from ' . $account['email'] . ' (@' . (string) ($account['username'] ?? '') . ').');
        Audit::log('account.developer_applied', $id, null, 'account', $id);
        $this->succeed('Developer application sent to the moderators.');
        $this->redirect('/account/profile#roles');
    }

    public function requestAdmin(): void
    {
        $this->csrf();
        $account = $this->auth();
        $id = (int) $account['id'];
        if ((int) ($account['is_admin'] ?? 0) === 1 || Ticket::hasOpen($id, 'admin')) {
            $this->fail('You cannot apply for administrator access right now.');
            $this->redirect('/account/profile#roles');
        }
        if (!level_ok($account, 'admin')) {
            $this->fail(level_gate_error('admin'));
            $this->redirect('/account/profile#roles');
        }
        $adminDays = max(1, (int) Config::get('account.admin_days', 90));
        if (account_age_days($account) < $adminDays) {
            $this->fail('Administrator applications need an account at least ' . $adminDays . ' days old.');
            $this->redirect('/account/profile#roles');
        }

        Ticket::create($id, 'admin', 'Administrator application from ' . $account['email'] . ' (@' . (string) ($account['username'] ?? '') . '). Only a Super Account can approve it.');
        Audit::log('account.admin_applied', $id, null, 'account', $id);
        $this->succeed('Administrator application sent. Only the Super Account approves it.');
        $this->redirect('/account/profile#roles');
    }

    public function requestDeletion(): void
    {
        $this->csrf();
        $account = $this->auth();
        $id = (int) $account['id'];

        if (Ticket::hasOpen($id, 'deletion')) {
            $this->fail('You already have an open deletion request.');
            $this->redirect('/account/security');
        }

        $code = RecoveryCode::verify($id, (string) $this->input('recovery_key'));
        if ($code === null) {
            $this->back(['recovery_key' => 'Enter one unused recovery key.'], [], '/account/security');
        }

        Ticket::create($id, 'deletion', "Deletion request for " . $account['email'] . ". Recovery key verified (unused key #" . $code . ").");
        RecoveryCode::markUsed((int) $code);
        Audit::log('account.deletion_requested', $id, null, 'account', $id);
        $this->succeed('Deletion request sent to the moderators. Nothing is removed until it is approved.');
        $this->redirect('/account/security');
    }

    public function help(): void
    {
        $account = $this->auth();
        $id = (int) $account['id'];

        $this->view('account/help', [
            'title' => 'Support',
            'tickets' => Ticket::forAccount($id, 'help'),
        ]);
    }

    public function ticket(array $params): void
    {
        $account = $this->auth();
        $ticket = Ticket::find((int) ($params['id'] ?? 0));
        if ($ticket === null || (int) $ticket['account_id'] !== (int) $account['id']) {
            App::notFound();
        }

        $this->view('account/ticket', [
            'title' => 'Ticket #' . $ticket['id'],
            'ticket' => $ticket,
            'messages' => Ticket::messages((int) $ticket['id']),
        ]);
    }

    public function openHelp(): void
    {
        $this->csrf();
        $account = $this->auth();
        $body = mb_substr(trim((string) $this->input('body')), 0, 2000);
        if ($body === '') {
            $this->back(['body' => 'Write your question first.'], [], '/account/help');
        }

        $id = Ticket::create((int) $account['id'], 'help', $body);
        Audit::log('account.help_opened', (int) $account['id'], null, 'ticket', $id);
        $this->succeed('Support chat opened. A moderator will reply here.');
        $this->redirect('/account/tickets/' . $id);
    }

    public function replyTicket(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $ticket = Ticket::find((int) ($params['id'] ?? 0));
        $body = trim((string) $this->input('body'));
        if ($ticket === null || (int) $ticket['account_id'] !== (int) $account['id'] || $body === '') {
            $this->fail('Write a reply first.');
            $this->redirect('/account/tickets/' . (int) ($params['id'] ?? 0));
        }

        Ticket::message((int) $ticket['id'], (int) $account['id'], $body);
        $this->succeed('Reply sent.');
        $this->redirect('/account/tickets/' . (int) $ticket['id']);
    }

    public function cancelTicket(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $ticket = Ticket::find((int) ($params['id'] ?? 0));
        if ($ticket === null || (int) $ticket['account_id'] !== (int) $account['id']) {
            App::notFound();
        }
        if ((string) $ticket['status'] !== 'open') {
            $this->fail('That request is already closed.');
            $this->redirect('/account/tickets/' . (int) $ticket['id']);
        }

        $type = (string) $ticket['type'];
        Ticket::resolve((int) $ticket['id']);
        Audit::log('account.ticket_cancelled', (int) $account['id'], null, 'ticket', (int) $ticket['id']);
        $this->succeed('Request cancelled.');
        $this->redirect(match ($type) {
            'tester' => '/account/profile#tester',
            'deletion' => '/account/security',
            default => '/account/help',
        });
    }

    public function updateProfile(): void
    {
        $this->csrf();
        $account = $this->auth();

        $name = trim((string) $this->input('display_name'));
        $username = mb_strtolower(trim((string) $this->input('username')));
        // Keep the existing description when an inline form posts without that field.
        $description = array_key_exists('description', $_POST)
            ? trim((string) $this->input('description'))
            : (string) ($account['description'] ?? '');
        $avatar = (string) $this->input('avatar', 'initials');
        $emoji = trim((string) $this->input('avatar_emoji'));
        $color = strtolower((string) $this->input('avatar_color', ''));
        $useDefaultColor = (string) $this->input('use_default_color', '') === '1';
        $file = $_FILES['avatar_file'] ?? null;
        $hasUpload = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        $errors = [];
        if ($username !== (string) ($account['username'] ?? '') && !level_ok($account, 'handle')) {
            $errors['username'] = level_gate_error('handle');
        }
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $errors['display_name'] = 'Use between 2 and 60 characters.';
        }
        if (!User::usernameValid($username)) {
            $errors['username'] = 'Use 3-30 characters: letters, numbers, and underscores, starting with a letter.';
        } elseif (User::usernameTaken($username, (int) $account['id'])) {
            $errors['username'] = 'This username is already taken.';
        }
        if (mb_strlen($description) > 280) {
            $errors['description'] = 'Keep the description under 280 characters.';
        } elseif (contains_link($description)) {
            Audit::log('moderation.link_blocked', (int) $account['id'], null, 'account', (int) $account['id'], ['where' => 'profile_description', 'body' => mb_substr($description, 0, 200)]);
            if (!Ticket::hasOpen((int) $account['id'], 'help')) {
                Ticket::create((int) $account['id'], 'help', 'Automated moderation report: attempted to put a link/domain in profile description. Blocked content: "' . mb_substr($description, 0, 500) . '"');
            }
            $errors['description'] = 'Links and domains are not allowed in profile description.';
        }

        $legacyEmoji = [
            '1' => '😀', '2' => '😎', '3' => '🦊', '4' => '🐱', '5' => '🐼', '6' => '🚀',
            '7' => '🌙', '8' => '⚡', '9' => '🎮', '10' => '🎧', '11' => '🌸', '12' => '🍀',
        ];
        $avatarType = 'initials';
        $avatarValue = null;
        if (!$hasUpload && $avatar === 'upload') {
            if (($account['avatar_type'] ?? '') === 'upload' && !empty($account['avatar_value'])) {
                $avatarType = 'upload';
                $avatarValue = (string) $account['avatar_value'];
            } else {
                $errors['avatar'] = 'Choose a valid avatar.';
            }
        } elseif (!$hasUpload && $avatar === 'emoji') {
            if (preg_match('/\p{Extended_Pictographic}/u', $emoji) === 1 && mb_strlen($emoji) <= 16) {
                $avatarType = 'preset';
                $avatarValue = $emoji;
            } else {
                $errors['avatar'] = 'Choose an emoji for your avatar.';
            }
        } elseif (!$hasUpload && $avatar !== 'initials') {
            if (preg_match('/^preset:(\d{1,2})$/', $avatar, $matches) === 1 && isset($legacyEmoji[$matches[1]])) {
                $avatarType = 'preset';
                $avatarValue = $legacyEmoji[$matches[1]];
            } else {
                $errors['avatar'] = 'Choose a valid avatar.';
            }
        }

        $avatarColor = null;
        if (!$useDefaultColor) {
            if (preg_match('/^#[0-9a-f]{6}$/', $color) === 1) {
                $avatarColor = $color;
            } else {
                $errors['avatar_color'] = 'Choose a valid color.';
            }
        }

        $contacts = [];
        $contactFields = [
            'telegram' => ['label' => 'Telegram username', 'pattern' => '/^[a-z0-9_.]{3,32}$/i', 'hint' => 'Use 3-32 letters, numbers, dots, or underscores.'],
            'discord' => ['label' => 'Discord username', 'pattern' => '/^[a-z0-9_.]{2,32}$/i', 'hint' => 'Use 2-32 letters, numbers, dots, or underscores.'],
            'steam' => ['label' => 'Steam ID', 'pattern' => '/^[A-Za-z0-9_]{2,64}$/', 'hint' => 'Use your SteamID64 or custom URL name.'],
        ];
        foreach ($contactFields as $kind => $rule) {
            $value = trim((string) $this->input('contact_' . $kind));
            if ($value === '') {
                continue;
            }
            if (preg_match($rule['pattern'], $value) !== 1) {
                $errors['contact_' . $kind] = $rule['hint'];
                continue;
            }
            $contacts[] = ['kind' => $kind, 'value' => $value];
        }
        foreach (['contact_email1' => 'email', 'contact_email2' => 'email'] as $field => $kind) {
            $value = mb_strtolower(trim((string) $this->input($field)));
            if ($value === '') {
                continue;
            }
            if (!valid_email($value) || mb_strlen($value) > 254) {
                $errors[$field] = 'Enter a valid email address.';
                continue;
            }
            $contacts[] = ['kind' => $kind, 'value' => $value];
        }
        if ($contacts !== [] && !level_ok($account, 'contacts')) {
            $errors['contact_telegram'] = level_gate_error('contacts');
        }

        $bannerColor = null;
        $bannerColorInput = strtolower((string) $this->input('profile_banner_color', ''));
        // The color input always submits a value; its displayed default means "no color" here.
        if ($bannerColorInput === '#2f5cff' && (string) ($account['profile_banner_color'] ?? '') === '') {
            $bannerColorInput = '';
        }
        if ($bannerColorInput !== '') {
            if (preg_match('/^#[0-9a-f]{6}$/', $bannerColorInput) === 1) {
                $bannerColor = $bannerColorInput;
            } else {
                $errors['profile_banner_color'] = 'Choose a valid banner color.';
            }
        }
        if ($this->input('remove_banner') === '1') {
            $bannerColor = null;
        }
        $bannerBlur = max(0, min(20, (int) $this->input('profile_banner_blur', 0)));
        $bannerDisabled = $this->input('profile_banner_disabled') === '1' ? 1 : 0;
        $backgroundBlur = max(0, min(20, (int) $this->input('profile_background_blur', 0)));
        $cardOpacity = max(20, min(100, (int) $this->input('profile_card_opacity', 100)));
        $cardBlur = $this->input('profile_card_blur') === '1' ? 1 : 0;

        $bannerFile = $_FILES['profile_banner_file'] ?? null;
        $backgroundFile = $_FILES['profile_background_file'] ?? null;
        $bannerChanged = ($bannerFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            || ($backgroundFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
            || $this->input('remove_banner') === '1' || $this->input('remove_background') === '1'
            || ($bannerColorInput !== '' && $bannerColor !== (string) ($account['profile_banner_color'] ?? ''))
            || $bannerBlur !== (int) ($account['profile_banner_blur'] ?? 0)
            || $backgroundBlur !== (int) ($account['profile_background_blur'] ?? 0)
            || $cardOpacity !== (int) ($account['profile_card_opacity'] ?? 100);
        if ($bannerChanged && !level_ok($account, 'banner')) {
            $errors['profile_banner_file'] = level_gate_error('banner');
        }

        $newBanner = null;
        $bannerFile = $_FILES['profile_banner_file'] ?? null;
        $hasBannerUpload = is_array($bannerFile) && ($bannerFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasBannerUpload && $errors === []) {
            [$newBanner, $bannerError] = save_image_upload($bannerFile, 'banners');
            if ($bannerError !== null) {
                $errors['profile_banner_file'] = $bannerError;
            }
        }

        $newBackground = null;
        $backgroundFile = $_FILES['profile_background_file'] ?? null;
        $hasBackgroundUpload = is_array($backgroundFile) && ($backgroundFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasBackgroundUpload && $errors === []) {
            [$newBackground, $backgroundError] = save_image_upload($backgroundFile, 'banners');
            if ($backgroundError !== null) {
                $errors['profile_background_file'] = $backgroundError;
            }
        }

        $newFile = null;
        if ($hasUpload && $errors === []) {
            $required = (int) Config::get('account.avatar_upload_days', 14);
            if ((int) ($account['is_admin'] ?? 0) !== 1 && account_age_days($account) < $required) {
                $errors['avatar_file'] = 'Image uploads unlock ' . $required . ' days after registration. You can use a preset or initials until then.';
            } elseif (!level_ok($account, 'avatar')) {
                $errors['avatar_file'] = level_gate_error('avatar');
            } else {
                [$newFile, $uploadError] = save_image_upload($file, 'avatars');
                if ($uploadError !== null) {
                    $errors['avatar_file'] = $uploadError;
                }
            }
        }

        if ($errors !== []) {
            if ($newFile !== null) {
                delete_upload('avatars', $newFile);
            }
            if ($newBanner !== null) {
                delete_upload('banners', $newBanner);
            }
            if ($newBackground !== null) {
                delete_upload('banners', $newBackground);
            }
            $this->back($errors, [
                'display_name' => $name,
                'username' => $username,
                'description' => $description,
                'avatar_color' => $color,
                'avatar' => $avatar,
                'avatar_emoji' => $emoji,
                'contact_telegram' => (string) $this->input('contact_telegram'),
                'contact_discord' => (string) $this->input('contact_discord'),
                'contact_steam' => (string) $this->input('contact_steam'),
                'contact_email1' => (string) $this->input('contact_email1'),
                'contact_email2' => (string) $this->input('contact_email2'),
                'profile_banner_color' => $bannerColorInput,
                'profile_banner_blur' => $bannerBlur,
                'profile_banner_disabled' => $bannerDisabled,
                'profile_background_blur' => $backgroundBlur,
                'profile_card_opacity' => $cardOpacity,
                'profile_card_blur' => $cardBlur,
            ]);
        }

        if ($newFile !== null) {
            $avatarType = 'upload';
            $avatarValue = $newFile;
            if (($account['avatar_type'] ?? '') === 'upload') {
                delete_upload('avatars', (string) ($account['avatar_value'] ?? ''));
            }
        } elseif ($avatarType !== 'upload' && ($account['avatar_type'] ?? '') === 'upload') {
            delete_upload('avatars', (string) ($account['avatar_value'] ?? ''));
        }

        $banner = (string) ($account['profile_banner'] ?? '');
        if ($newBanner !== null) {
            if ($banner !== '') {
                delete_upload('banners', $banner);
            }
            $banner = $newBanner;
        } elseif ($this->input('remove_banner') === '1' && $banner !== '') {
            delete_upload('banners', $banner);
            $banner = '';
        }

        $background = (string) ($account['profile_background'] ?? '');
        if ($newBackground !== null) {
            if ($background !== '') {
                delete_upload('banners', $background);
            }
            $background = $newBackground;
        } elseif ($this->input('remove_background') === '1' && $background !== '') {
            delete_upload('banners', $background);
            $background = '';
        }

        User::update((int) $account['id'], [
            'display_name' => $name,
            'username' => $username,
            'description' => $description !== '' ? $description : null,
            'avatar_type' => $avatarType,
            'avatar_value' => $avatarValue,
            'avatar_color' => $avatarColor,
            'contacts' => $contacts === [] ? null : json_encode($contacts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'profile_banner' => $banner !== '' ? $banner : null,
            'profile_banner_color' => $bannerColor,
            'profile_banner_disabled' => $bannerDisabled,
            'profile_background' => $background !== '' ? $background : null,
            'profile_banner_blur' => $bannerBlur,
            'profile_background_blur' => $backgroundBlur,
            'profile_card_opacity' => $cardOpacity,
            'profile_card_blur' => $cardBlur,
        ]);
        Audit::log('account.profile_updated', (int) $account['id']);
        if ($this->input('save_all') === '1') {
            $this->saveProfileExtras($account);
        }
        $this->succeed('Profile updated.');

        $this->afterEdit('/account/profile');
    }

    /** The rest of the profile settings page: privacy, visibility, order, width and beta flags. */
    private function saveProfileExtras(array $account): void
    {
        $id = (int) $account['id'];
        $private = $this->input('private_profile') === '1' ? 1 : 0;
        $privacyChanged = $private !== (int) ($account['private_profile'] ?? 0);
        if ($privacyChanged) {
            $private === 1 ? Showcase::saveVisible($id) : Showcase::restoreVisible($id);
        }
        User::update($id, [
            'private_profile' => $private,
            'share_social' => $this->input('share_social') === '1' ? 1 : 0,
            'show_role_admin' => $this->input('show_role_admin') === '1' ? 1 : 0,
            'show_role_tester' => $this->input('show_role_tester') === '1' ? 1 : 0,
            'show_role_developer' => $this->input('show_role_developer') === '1' ? 1 : 0,
            'show_role_moderator' => $this->input('show_role_moderator') === '1' ? 1 : 0,
        ]);
        Audit::log('account.privacy_updated', $id);

        foreach (Badge::forAccount($id) as $badge) {
            Badge::setVisibility($id, (int) $badge['id'], in_array((string) $badge['id'], (array) $this->input('visible_badges', []), true));
        }
        $hasVisibility = $this->input('showcase_visibility_present') === '1';
        if ($hasVisibility && $private !== 1 && !$privacyChanged) {
            $visible = (array) $this->input('visible_showcases', []);
            foreach (Showcase::forAccount($id) as $showcase) {
                $show = in_array((string) $showcase['id'], $visible, true);
                Showcase::setVisibility($id, (int) $showcase['id'], $show);
            }
        }

        $widths = (array) $this->input('showcase_width', []);
        $opacities = (array) $this->input('showcase_opacity', []);
        $includeSubscribed = $this->input('teams_include_subscribed') === '1' ? 1 : 0;
        foreach (Showcase::forAccount($id) as $showcase) {
            $fresh = Showcase::find((int) $showcase['id'], $id) ?? $showcase;
            $meta = Showcase::meta($fresh);
            $changed = false;
            if (isset($widths[$showcase['id']])) {
                $meta['width'] = isset(Showcase::WIDTHS[(string) $widths[$showcase['id']]]) ? (string) $widths[$showcase['id']] : 'normal';
                $changed = true;
            }
            if (isset($opacities[$showcase['id']])) {
                $meta['opacity'] = max(20, min(100, (int) $opacities[$showcase['id']]));
                $changed = true;
            }
            if ((string) $showcase['type'] === 'team') {
                $meta['include_subscribed'] = $includeSubscribed;
                $changed = true;
            }
            if ($changed) {
                Showcase::setMeta($showcase, $meta);
            }
        }
        $order = (array) $this->input('showcase_order', []);
        if ($order !== []) {
            Showcase::reorder($id, $order);
        }

    }

    public function updateEmail(): void
    {
        $this->csrf();
        $account = $this->auth();

        $password = (string) $this->input('password');
        $email = mb_strtolower(trim((string) $this->input('email')));

        $errors = [];
        if (!valid_email($email) || mb_strlen($email) > 254) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif (User::emailTaken($email, (int) $account['id'])) {
            $errors['email'] = 'This email is already in use.';
        }
        if (!password_verify($password, (string) $account['password_hash'])) {
            $errors['password'] = (int) $account['password_set'] === 0
                ? 'Set a password in Security before changing your email.'
                : 'Password is incorrect.';
        }
        if ($errors !== []) {
            $this->back($errors, ['email' => $email]);
        }

        User::update((int) $account['id'], [
            'email' => $email,
            'email_verified_at' => null,
        ]);
        Audit::log('account.email_updated', (int) $account['id'], null, 'account', (int) $account['id']);
        $this->succeed('Email updated.');

        $this->redirect('/account/profile');
    }

    public function security(): void
    {
        $account = $this->auth();
        $accountId = (int) $account['id'];

        $sessions = Db::all(
            'SELECT id, device_id, ip, user_agent, app_name, label, last_active_at, expires_at, created_at
             FROM auth_sessions
             WHERE account_id = ? AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY last_active_at DESC',
            [$accountId]
        );

        $accountsByDevice = [];
        foreach (array_unique(array_column($sessions, 'device_id')) as $deviceId) {
            $accountsByDevice[(string) $deviceId] = Db::all(
                'SELECT DISTINCT a.id, a.display_name, a.username, a.avatar_type, a.avatar_value, a.avatar_color, a.updated_at
                 FROM auth_sessions s INNER JOIN accounts a ON a.id = s.account_id
                 WHERE s.device_id = ? AND s.revoked_at IS NULL AND s.expires_at > NOW()
                 ORDER BY a.last_login_at DESC, a.id ASC',
                [$deviceId]
            );
        }

        $deviceAccounts = [];
        foreach (array_merge(Auth::deviceAccounts(), Auth::switchAccounts()) as $row) {
            $deviceAccounts[(int) $row['id']] = $row;
        }
        $deviceAccounts = array_values($deviceAccounts);
        if (!array_filter($deviceAccounts, static fn (array $row): bool => (int) $row['id'] === $accountId)) {
            array_unshift($deviceAccounts, $account);
        }
        $switchableIds = array_map(static fn (array $row): int => (int) $row['id'], Auth::switchAccounts());

        $activity = Db::all(
            'SELECT action, ip, created_at FROM audit_log
             WHERE actor_id = ? AND action LIKE \'auth.%\'
             ORDER BY id DESC LIMIT 20',
            [$accountId]
        );

        $this->view('account/security', [
            'title' => 'Security',
            'account' => $account,
            'sessions' => $sessions,
            'accountsByDevice' => $accountsByDevice,
            'activity' => $activity,
            'guardEnabled' => Guard::enabled($account),
            'guardSecret' => Guard::enabled($account) ? '' : Guard::secretFor($account),
            'guardUri' => Guard::enabled($account) ? '' : Guard::uri(Guard::secretFor($account), (string) $account['email']),
            'recoveryCount' => RecoveryCode::countUnused($accountId),
            'deviceAccounts' => $deviceAccounts,
            'switchableIds' => $switchableIds,
            'deletionKeyCount' => RecoveryCode::countUnused($accountId),
            'deletionTicketId' => Ticket::openId($accountId, 'deletion'),
            'telegramConfigured' => trim((string) Config::get('telegram.bot_token', '')) !== '',
            'telegramBotUsername' => trim((string) Config::get('telegram.bot_username', '')),
            'telegramLinked' => trim((string) ($account['telegram_chat_id'] ?? '')) !== '',
            'telegramUsername' => trim((string) ($account['telegram_username'] ?? '')),
            'telegram2fa' => (int) ($account['telegram_2fa'] ?? 0) === 1,
            'telegramNotifyLogin' => (int) ($account['telegram_notify_login'] ?? 1) === 1,
            'telegramNotifySecurity' => (int) ($account['telegram_notify_security'] ?? 1) === 1,
            'telegramLinkCode' => trim((string) ($account['telegram_link_code'] ?? '')),
            'telegramLinkExpires' => (string) ($account['telegram_link_expires_at'] ?? ''),
        ]);
    }

    public function startGuard(): void
    {
        $this->csrf();
        $account = $this->auth();
        $secret = Guard::newSecret();

        User::update((int) $account['id'], [
            'guard_secret' => Crypto::encrypt($secret),
            'guard_enabled' => 0,
            'guard_last_step' => 0,
        ]);
        Audit::log('account.guard_started', (int) $account['id']);
        $this->succeed('Guard secret generated. Enter a current code to enable it.');

        $this->redirect('/account/security');
    }

    public function enableGuard(): void
    {
        $this->csrf();
        $account = $this->auth();
        $secret = Guard::secretFor($account);
        $code = (string) $this->input('code');

        if ($secret === '' || !Guard::verify($secret, $code, 0, true)) {
            $this->back(['code' => 'That code does not match the Guard secret.']);
        }

        // Enrollment code is not consumed; the next sign-in may use it.
        User::update((int) $account['id'], [
            'guard_enabled' => 1,
            'guard_last_step' => 0,
        ]);
        Audit::log('account.guard_enabled', (int) $account['id']);
        $this->succeed('Guard is enabled. Sign-ins now need a current code.');
        Auth::telegramNotify($account, 'telegram_notify_security', 'bbaccounts: authenticator 2FA was enabled on your account.');

        $this->redirect('/account/security');
    }

    public function disableGuard(): void
    {
        $this->csrf();
        $account = $this->auth();
        $secret = Guard::secretFor($account);
        $code = (string) $this->input('code');

        if ($secret === '' || !Guard::verify($secret, $code, 0, true)) {
            $this->back(['code' => 'That code does not match the Guard secret.']);
        }

        User::update((int) $account['id'], [
            'guard_secret' => null,
            'guard_enabled' => 0,
            'guard_last_step' => 0,
        ]);
        Audit::log('account.guard_disabled', (int) $account['id']);
        $this->succeed('Guard is disabled.');
        Auth::telegramNotify($account, 'telegram_notify_security', 'bbaccounts: authenticator 2FA was disabled on your account.');

        $this->redirect('/account/security');
    }

    public function telegramLink(): void
    {
        $this->csrf();
        $account = $this->auth();
        $token = trim((string) Config::get('telegram.bot_token', ''));
        if ($token === '') {
            $this->fail('Telegram bot is not configured on this server.');
            $this->redirect('/account/security');
        }

        $code = strtoupper(bin2hex(random_bytes(4)));
        User::update((int) $account['id'], [
            'telegram_link_code' => $code,
            'telegram_link_expires_at' => date('Y-m-d H:i:s', time() + 900),
        ]);
        Audit::log('account.telegram_link_started', (int) $account['id']);
        $this->succeed('Telegram link code created. Send it to the bot within 15 minutes.');
        $this->redirect('/account/security#telegram');
    }

    public function telegramUnlink(): void
    {
        $this->csrf();
        $account = $this->auth();
        $chatId = trim((string) ($account['telegram_chat_id'] ?? ''));
        User::update((int) $account['id'], [
            'telegram_chat_id' => null,
            'telegram_username' => null,
            'telegram_2fa' => 0,
            'telegram_notify_login' => 1,
            'telegram_notify_security' => 1,
            'telegram_login_code' => null,
            'telegram_login_expires_at' => null,
            'telegram_link_code' => null,
            'telegram_link_expires_at' => null,
        ]);
        if ($chatId !== '') {
            \BBA\Controllers\AuthController::telegramSend($chatId, 'This Telegram account was unlinked from bbaccounts.');
        }
        Audit::log('account.telegram_unlinked', (int) $account['id']);
        $this->succeed('Telegram disconnected.');
        $this->redirect('/account/security#telegram');
    }

    public function telegramSettings(): void
    {
        $this->csrf();
        $account = $this->auth();
        if (trim((string) ($account['telegram_chat_id'] ?? '')) === '') {
            $this->fail('Link Telegram first.');
            $this->redirect('/account/security#telegram');
        }

        User::update((int) $account['id'], [
            'telegram_2fa' => $this->input('telegram_2fa') === '1' ? 1 : 0,
            'telegram_notify_login' => $this->input('telegram_notify_login') === '1' ? 1 : 0,
            'telegram_notify_security' => $this->input('telegram_notify_security') === '1' ? 1 : 0,
        ]);
        Audit::log('account.telegram_settings_updated', (int) $account['id']);
        $this->succeed('Telegram settings saved.');
        $this->redirect('/account/security#telegram');
    }

    public function telegramWebhook(): void
    {
        $secret = trim((string) Config::get('telegram.webhook_secret', ''));
        if ($secret !== '') {
            $header = (string) (Request::header('X-Telegram-Bot-Api-Secret-Token') ?? '');
            if (!hash_equals($secret, $header)) {
                \BBA\Core\Response::json(['ok' => false], 403);
                return;
            }
        }

        $update = Request::json();
        if (!is_array($update)) {
            \BBA\Core\Response::json(['ok' => true]);
            return;
        }

        $message = $update['message'] ?? null;
        if (!is_array($message)) {
            \BBA\Core\Response::json(['ok' => true]);
            return;
        }

        $text = trim((string) ($message['text'] ?? ''));
        $chatId = (string) (($message['chat']['id'] ?? ''));
        $tgUsername = trim((string) (($message['from']['username'] ?? '')));
        if ($chatId === '' || $text === '') {
            \BBA\Core\Response::json(['ok' => true]);
            return;
        }

        if (preg_match('/^\/start(?:\s+([A-Fa-f0-9]+))?$/', $text, $m) === 1) {
            $code = strtoupper(trim((string) ($m[1] ?? '')));
            if ($code === '') {
                \BBA\Controllers\AuthController::telegramSend($chatId, "Send the link code from your bbaccounts Security page:\n/start YOUR_CODE");
                \BBA\Core\Response::json(['ok' => true]);
                return;
            }

            $account = Db::one(
                'SELECT id, telegram_chat_id FROM accounts WHERE telegram_link_code = ? AND telegram_link_expires_at > NOW() LIMIT 1',
                [$code]
            );
            if ($account === null) {
                \BBA\Controllers\AuthController::telegramSend($chatId, 'This link code is invalid or expired. Generate a new one in bbaccounts Security.');
                \BBA\Core\Response::json(['ok' => true]);
                return;
            }

            User::update((int) $account['id'], [
                'telegram_chat_id' => $chatId,
                'telegram_username' => $tgUsername !== '' ? $tgUsername : null,
                'telegram_link_code' => null,
                'telegram_link_expires_at' => null,
            ]);
            Audit::log('account.telegram_linked', (int) $account['id']);
            \BBA\Controllers\AuthController::telegramSend($chatId, "Telegram linked to your bbaccounts account. You can now enable Telegram codes and notifications in Security.");
        }

        \BBA\Core\Response::json(['ok' => true]);
    }

    public function updatePassword(): void
    {
        $this->csrf();
        $account = $this->auth();

        $current = (string) $this->input('current_password');
        $password = (string) $this->input('password');
        $confirm = (string) $this->input('password_confirmation');
        $hasPassword = (int) ($account['password_set'] ?? 1) === 1;

        $errors = [];
        if ($hasPassword && !password_verify($current, (string) $account['password_hash'])) {
            $errors['current_password'] = 'Current password is incorrect.';
        }
        if (mb_strlen($password) < 10) {
            $errors['password'] = 'Use at least 10 characters.';
        }
        if (!hash_equals($password, $confirm)) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }
        if ($errors !== []) {
            $this->back($errors);
        }

        User::update((int) $account['id'], [
            'password_hash' => Crypto::passwordHash($password),
            'password_set' => 1,
        ]);
        Auth::revokeOtherSessions((int) $account['id'], (int) $account['auth_session_id']);
        Audit::log($hasPassword ? 'account.password_changed' : 'account.password_set', (int) $account['id']);
        $this->succeed($hasPassword
            ? 'Password changed. Other sessions were signed out.'
            : 'Password set. You can now sign in with your email and password.');
        Auth::telegramNotify($account, 'telegram_notify_security', 'bbaccounts: your password was changed. Other sessions were signed out.');

        $this->redirect('/account/security');
    }

    public function recoveryCodes(): void
    {
        $account = $this->auth();
        $codes = Session::get('_recovery_codes', []);
        if (!is_array($codes) || $codes === []) {
            $this->fail('Generate new recovery codes to see them.');

            $this->redirect('/account/security');
        }

        $next = (string) Request::query('next', '');
        if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
            $next = '';
        }

        $this->view('account/recovery-codes', [
            'title' => 'Recovery codes',
            'account' => $account,
            'codes' => $codes,
            'next' => $next,
        ]);
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->csrf();
        $account = $this->auth();
        $password = (string) $this->input('password');

        if (!password_verify($password, (string) $account['password_hash'])) {
            $this->back(['password' => (int) $account['password_set'] === 0
                ? 'Set a password in Security before generating new codes.'
                : 'Password is incorrect.']);
        }

        $codes = RecoveryCode::generate((int) $account['id']);
        Audit::log('account.recovery_codes_regenerated', (int) $account['id']);
        Session::set('_recovery_codes', $codes);
        $this->succeed('New recovery codes generated. Old codes no longer work.');

        $this->redirect('/account/recovery-codes');
    }

    public function revokeSession(): void
    {
        $this->csrf();
        $account = $this->auth();
        $sessionId = (int) $this->input('session_id', 0);

        if ($sessionId === (int) $account['auth_session_id']) {
            Auth::logout();

            $this->redirect('/login?logout=1');
        }

        Auth::revokeSession($sessionId, (int) $account['id']);
        Audit::log('account.session_revoked', (int) $account['id'], null, 'session', $sessionId);
        $this->succeed('Session signed out.');

        $this->redirect('/account/security');
    }

    public function renameSession(): void
    {
        $this->csrf();
        $account = $this->auth();
        $sessionId = (int) $this->input('session_id', 0);
        $label = mb_substr(trim((string) $this->input('label', '')), 0, 60);

        $exists = Db::value(
            'SELECT id FROM auth_sessions WHERE id = ? AND account_id = ? AND revoked_at IS NULL',
            [$sessionId, (int) $account['id']]
        );
        if ($exists === null) {
            $this->fail('That session is no longer active.');
            $this->redirect('/account/security');
        }

        Db::update('auth_sessions', ['label' => $label !== '' ? $label : null], 'id = ? AND account_id = ?', [
            $sessionId,
            (int) $account['id'],
        ]);
        Audit::log('account.session_renamed', (int) $account['id'], null, 'session', $sessionId);
        $this->succeed($label !== '' ? 'Session renamed.' : 'Session name reset.');

        $this->redirect('/account/security');
    }

    public function revokeOtherSessions(): void
    {
        $this->csrf();
        $account = $this->auth();
        Auth::revokeOtherSessions((int) $account['id'], (int) $account['auth_session_id']);
        Audit::log('account.other_sessions_revoked', (int) $account['id']);
        $this->succeed('All other sessions were signed out.');

        $this->redirect('/account/security');
    }

    public function apps(): void
    {
        $account = $this->auth();
        $consents = Db::all(
            'SELECT c.scope, c.created_at, c.updated_at, p.id AS project_id, p.name, p.slug, p.domain, p.accent_color,
                    (SELECT COUNT(*) FROM oauth_tokens t WHERE t.account_id = c.account_id AND t.project_id = c.project_id AND t.revoked_at IS NULL AND t.expires_at > NOW()) AS active_tokens
             FROM oauth_consents c
             INNER JOIN projects p ON p.id = c.project_id
             WHERE c.account_id = ?
             ORDER BY c.updated_at DESC',
            [(int) $account['id']]
        );

        $this->view('account/apps', [
            'title' => 'Authorized apps',
            'account' => $account,
            'consents' => $consents,
        ]);
    }

    public function revokeApp(): void
    {
        $this->csrf();
        $account = $this->auth();
        $projectId = (int) $this->input('project_id', 0);

        $consent = Db::one('SELECT id FROM oauth_consents WHERE account_id = ? AND project_id = ?', [
            (int) $account['id'],
            $projectId,
        ]);
        if ($consent === null) {
            $this->back([], [], '/account/apps');
        }

        OAuthService::revokeAccountProject((int) $account['id'], $projectId);
        Audit::log('account.access_revoked', (int) $account['id'], $projectId);
        $this->succeed('Access revoked. The app can no longer use your account.');

        $this->redirect('/account/apps');
    }

    public function connections(): void
    {
        $account = $this->auth();
        $identities = SocialIdentity::forAccount((int) $account['id']);
        $linked = [];
        foreach ($identities as $identity) {
            $linked[(string) $identity['provider']] = $identity;
        }

        $providers = [];
        foreach (SocialAuth::names() as $provider => $name) {
            $providers[$provider] = [
                'name' => $name,
                'enabled' => SocialAuth::enabled($provider),
                'placeholder' => SocialAuth::placeholder($provider),
                'identity' => $linked[$provider] ?? null,
            ];
        }

        $this->view('account/connections', [
            'title' => 'Connections',
            'account' => $account,
            'tab' => Request::query('tab', 'password') === 'social' ? 'social' : 'password',
            'providers' => $providers,
            'identityCount' => count($identities),
            'authBetaEnabled' => (int) ($account['is_tester'] ?? 0) === 1
                && in_array('auth', array_filter(explode(',', (string) ($account['beta_features'] ?? ''))), true),
        ]);
    }

    public function unlinkSocial(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $provider = (string) ($params['provider'] ?? '');
        if (!SocialAuth::exists($provider)) {
            App::notFound();
        }

        $identity = Db::one(
            'SELECT * FROM auth_identities WHERE account_id = ? AND provider = ? LIMIT 1',
            [(int) $account['id'], $provider]
        );
        if ($identity === null) {
            $this->back([], [], '/account/connections?tab=social');
        }

        if ((int) $account['password_set'] !== 1 && SocialIdentity::countForAccount((int) $account['id']) <= 1) {
            $this->back(
                ['social' => 'Set a password before removing your only way to sign in.'],
                [],
                '/account/connections?tab=social'
            );
        }

        SocialIdentity::unlink((int) $identity['id'], (int) $account['id']);
        Audit::log('account.social_unlinked', (int) $account['id'], null, 'identity', (int) $identity['id'], [
            'provider' => $provider,
        ]);
        $this->succeed(SocialAuth::name($provider) . ' disconnected.');

        $this->redirect('/account/connections?tab=social');
    }

    /** Live uniqueness check for the studio handle editor. */
    public function checkHandle(): void
    {
        $account = $this->auth();
        $username = mb_strtolower(trim((string) Request::query('username', '')));
        $same = $username === (string) ($account['username'] ?? '');
        $available = $same || (!User::usernameTaken($username, (int) $account['id']) && User::usernameValid($username));
        $this->json(['available' => $available, 'valid' => User::usernameValid($username)]);
    }

    /** Pick up to five accepted friends for the fixed Best friends showcase. */
    public function updateBestFriends(): void
    {
        $this->csrf();
        $account = $this->auth();
        $showcase = Showcase::findType((int) $account['id'], 'bestfriends');
        if ($showcase === null) {
            $this->fail('The Best friends showcase is not enabled.');
            $this->afterEdit('/account/profile#showcases');
        }

        $friendIds = array_map('intval', array_column(Friend::forAccount((int) $account['id']), 'id'));
        $selected = array_map('intval', (array) $this->input('bestfriends', []));
        $selected = array_values(array_slice(array_unique(array_intersect($selected, $friendIds)), 0, Friend::BEST_FRIENDS_MAX));
        Showcase::setBody((int) $showcase['id'], (int) $account['id'], (string) json_encode($selected));
        Audit::log('account.best_friends_updated', (int) $account['id'], null, 'showcase', (int) $showcase['id']);
        $this->succeed('Best friends saved.');
        $this->afterEdit('/account/profile#showcases');
    }

    public function updateBadgeVisibility(): void
    {
        $this->csrf();
        $account = $this->auth();
        $visible = (array) $this->input('visible_badges', []);

        foreach (Badge::forAccount((int) $account['id']) as $badge) {
            Badge::setVisibility((int) $account['id'], (int) $badge['id'], in_array((string) $badge['id'], $visible, true));
        }

        $this->succeed('Badge visibility saved.');
        $this->afterEdit('/account/profile#badges');
    }

    public function storeShowcase(): void
    {
        $this->csrf();
        $account = $this->auth();

        if ((int) ($account['private_profile'] ?? 0) === 1) {
            $this->fail('Turn off the private profile before adding showcases.');
            $this->afterEdit('/account/profile#showcases');
        }
        if (!showcases_allowed($account)) {
            $this->fail('Showcases unlock ' . (int) Config::get('account.showcase_days', 30) . ' days after registration.');
            $this->afterEdit('/account/profile#showcases');
        }

        $type = (string) $this->input('type', 'text');
        $title = trim((string) $this->input('showcase_title'));
        $body = trim((string) $this->input('showcase_text'));
        $mediaFile = null;
        $meta = null;
        $review = ['project' => '', 'url' => '', 'rating' => 5, 'text' => ''];

        $errors = [];
        if (!in_array($type, Showcase::TYPES, true) || in_array($type, Showcase::FIXED_TYPES, true) || $type === 'team') {
            $errors['type'] = 'Choose a showcase type.';
        }
        $typeGate = match ($type) {
            'text' => 'text', 'media' => 'media', 'repos' => 'repos', 'links' => 'links', 'reviews' => 'reviews',
            default => null,
        };
        if ($typeGate !== null && !level_ok($account, $typeGate)) {
            $errors['type'] = level_gate_error($typeGate);
        }
        $existingTypes = array_column(Showcase::forAccount((int) $account['id']), 'type');
        if (in_array($type, $existingTypes, true)) {
            $errors['type'] = 'You already have this showcase. Turn it off or edit it instead.';
        }

        if ($type === 'bestfriends') {
            $friendIds = array_map('intval', array_column(Friend::forAccount((int) $account['id']), 'id'));
            $selected = array_map('intval', (array) $this->input('bestfriends', []));
            $selected = array_values(array_slice(array_unique(array_intersect($selected, $friendIds)), 0, Friend::BEST_FRIENDS_MAX));
            $body = (string) json_encode($selected);
        }
        if ($type === 'text' && (mb_strlen($title) < 2 || mb_strlen($title) > 60)) {
            $errors['title'] = 'Use between 2 and 60 characters.';
        }
        if ($title !== '' && mb_strlen($title) > 60) {
            $errors['title'] = 'Use at most 60 characters.';
        }
        if ($type === 'text' && (mb_strlen($body) < 2 || mb_strlen($body) > 500)) {
            $errors['body'] = 'Use between 2 and 500 characters.';
        }
        if ($type === 'media') {
            [$mediaFile, $mediaError] = save_media_upload((array) ($_FILES['media_file'] ?? []));
            if ($mediaError !== null) {
                $errors['media'] = $mediaError;
            } elseif ($mediaFile === null) {
                $errors['media'] = 'Choose an image or video to upload.';
            }
            if ((int) ($account['points'] ?? 0) < Showcase::MEDIA_COST) {
                $errors['form'] = 'Media costs ' . Showcase::MEDIA_COST . ' points. Earn points from likes and friendships.';
            }
        }
        if ($type === 'repos') {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $body) ?: [])));
            if ($lines === [] || count($lines) > 5) {
                $errors['body'] = 'Add between 1 and 5 GitHub repository links, one per line. Add a note after | to pin why it matters.';
            }
            $repos = [];
            foreach ($lines as $line) {
                [$url, $note] = array_pad(explode('|', $line, 2), 2, '');
                $url = trim($url);
                $note = trim($note);
                if (preg_match('~^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/?$~', $url) !== 1) {
                    $errors['body'] = 'Use full GitHub links like https://github.com/owner/repo.';
                    break;
                }
                if (mb_strlen($note) > 120) {
                    $errors['body'] = 'Keep each repository note under 120 characters.';
                    break;
                }
                $repos[] = $note === '' ? $url : $url . ' | ' . $note;
            }
            $body = implode("\n", $repos);
        }
        if ($type === 'links') {
            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $body) ?: [])));
            if ($lines === [] || count($lines) > 10) {
                $errors['body'] = 'Add between 1 and 10 links, one per line as URL | label.';
            }
            $links = [];
            foreach ($lines as $line) {
                [$url, $label] = array_pad(explode('|', $line, 2), 2, '');
                $url = trim($url);
                $label = trim($label);
                if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $url) || mb_strlen($url) > 300) {
                    $errors['body'] = 'Use full http:// or https:// links.';
                    break;
                }
                if ($label === '' || mb_strlen($label) > 40) {
                    $errors['body'] = 'Add a label after each link, up to 40 characters.';
                    break;
                }
                $links[] = $url . ' | ' . $label;
            }
            $body = implode("\n", $links);
        }
        if ($type === 'reviews') {
            [$review, $reviewErrors] = $this->reviewInput();
            $errors += $reviewErrors;
            if ($reviewErrors === []) {
                $meta = (string) json_encode(['reviews' => [$review]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        if (Showcase::count((int) $account['id']) >= Showcase::slotsFor($account)) {
            $errors['form'] = 'You have no free showcase slots. Buy one with points or remove a showcase.';
        }
        if ($errors !== []) {
            delete_upload('media', $mediaFile);
            $this->back($errors, [
                'type' => $type,
                'showcase_title' => $title,
                'showcase_text' => $body,
                'review_project' => $review['project'],
                'review_url' => $review['url'],
                'review_rating' => $review['rating'],
                'review_text' => $review['text'],
            ], '/account/profile#showcases');
        }

        if ($title === '') {
            $title = Showcase::DEFAULT_TITLES[$type] ?? 'Showcase';
        }
        if ($type === 'media') {
            User::addPoints((int) $account['id'], -Showcase::MEDIA_COST);
            $storedBody = $mediaFile;
        } else {
            $storedBody = in_array($type, ['text', 'repos', 'links', 'bestfriends'], true) ? $body : null;
        }
        Showcase::create((int) $account['id'], $type, $title, $storedBody, $meta);
        Audit::log('account.showcase_added', (int) $account['id']);
        $this->succeed('Showcase added.');
        $this->afterEdit('/account/profile#showcases');
    }

    /** Inline edit of an existing showcase title and text body. */
    public function updateShowcase(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $showcase = Showcase::find((int) ($params['id'] ?? 0), (int) $account['id']);
        if ($showcase === null) {
            $this->fail('Showcase not found.');
            $this->afterEdit('/account/profile#showcases');
        }
        $type = (string) $showcase['type'];
        if (in_array($type, Showcase::FIXED_TYPES, true) || !in_array($type, ['text', 'repos', 'links'], true)) {
            $this->fail('This showcase cannot be edited here.');
            $this->afterEdit('/account/profile#showcases');
        }
        if (!level_ok($account, $type)) {
            $this->fail(level_gate_error($type));
            $this->afterEdit('/account/profile#showcases');
        }

        $title = trim((string) $this->input('showcase_title'));
        $body = trim((string) $this->input('showcase_text'));
        $errors = [];
        if (mb_strlen($title) > 60) {
            $errors['title'] = 'Use at most 60 characters.';
        }
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $body) ?: [])));
        if ($type === 'text' && (mb_strlen($body) < 2 || mb_strlen($body) > 500)) {
            $errors['body'] = 'Use between 2 and 500 characters.';
        }
        if ($type === 'repos') {
            if ($lines === [] || count($lines) > 5) {
                $errors['body'] = 'Add between 1 and 5 GitHub repository links, one per line.';
            }
            foreach ($lines as $line) {
                [$url, $note] = array_pad(explode('|', $line, 2), 2, '');
                if (preg_match('~^https://github\.com/[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+/?$~', trim($url)) !== 1 || mb_strlen(trim($note)) > 120) {
                    $errors['body'] = 'Use full GitHub links like https://github.com/owner/repo with an optional note after |.';
                    break;
                }
            }
        }
        if ($type === 'links') {
            if ($lines === [] || count($lines) > 10) {
                $errors['body'] = 'Add between 1 and 10 links, one per line as URL | label.';
            }
            foreach ($lines as $line) {
                [$url, $label] = array_pad(explode('|', $line, 2), 2, '');
                $url = trim($url);
                $label = trim($label);
                if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('~^https?://~i', $url) || $label === '' || mb_strlen($label) > 40) {
                    $errors['body'] = 'Use full http:// or https:// links with a label after | (up to 40 characters).';
                    break;
                }
            }
        }
        if ($errors !== []) {
            $this->back($errors, [
                'showcase_title' => $title,
                'showcase_text' => $body,
            ], '/account/profile#showcases');
        }

        Db::update('showcases', [
            'title' => $title !== '' ? $title : (Showcase::DEFAULT_TITLES[$type] ?? 'Showcase'),
            'body' => $body,
        ], 'id = ? AND account_id = ?', [(int) $showcase['id'], (int) $account['id']]);
        Audit::log('account.showcase_updated', (int) $account['id'], null, 'showcase', (int) $showcase['id']);
        $this->succeed('Showcase saved.');
        $this->afterEdit('/account/profile#showcases');
    }

    public function updateShowcaseWidth(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $showcase = Showcase::find((int) ($params['id'] ?? 0), (int) $account['id']);
        if ($showcase === null) {
            $this->fail('Showcase not found.');
            $this->afterEdit('/account/profile#showcases');
        }

        Showcase::setWidth($showcase, (string) $this->input('width'));
        $this->succeed('Showcase width saved.');
        $this->afterEdit('/account/profile#showcases');
    }

    public function storeShowcaseReview(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $showcase = Showcase::find((int) ($params['id'] ?? 0), (int) $account['id']);
        if ($showcase === null || (string) $showcase['type'] !== 'reviews') {
            $this->fail('That showcase does not accept reviews.');
            $this->afterEdit('/account/profile#showcases');
        }
        if (!level_ok($account, 'reviews')) {
            $this->fail(level_gate_error('reviews'));
            $this->afterEdit('/account/profile#showcases');
        }
        if (count(Showcase::reviews($showcase)) >= Showcase::REVIEW_MAX) {
            $this->fail('This showcase already has ' . Showcase::REVIEW_MAX . ' reviews.');
            $this->afterEdit('/account/profile#showcases');
        }

        [$review, $errors] = $this->reviewInput();
        if ($errors !== []) {
            $this->back($errors, [
                'review_project' => $review['project'],
                'review_url' => $review['url'],
                'review_rating' => $review['rating'],
                'review_text' => $review['text'],
            ], '/account/profile#showcases');
        }

        Showcase::addReview($showcase, $review);
        Audit::log('account.showcase_review_added', (int) $account['id'], null, 'showcase', (int) $showcase['id']);
        $this->succeed('Review added.');
        $this->afterEdit('/account/profile#showcases');
    }

    public function deleteShowcaseReview(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $showcase = Showcase::find((int) ($params['id'] ?? 0), (int) $account['id']);
        if ($showcase === null || (string) $showcase['type'] !== 'reviews') {
            $this->fail('That showcase does not accept reviews.');
            $this->afterEdit('/account/profile#showcases');
        }

        Showcase::deleteReview($showcase, (int) ($params['index'] ?? -1));
        Audit::log('account.showcase_review_deleted', (int) $account['id'], null, 'showcase', (int) $showcase['id']);
        $this->succeed('Review removed.');
        $this->afterEdit('/account/profile#showcases');
    }

    public function buyShowcaseSlot(): void
    {
        $this->csrf();
        $account = $this->auth();
        if (!Showcase::buySlot($account)) {
            $this->fail('You need ' . Showcase::SLOT_COST . ' points and fewer than ' . Showcase::SLOT_MAX . ' slots to buy another showcase slot.');
            $this->afterEdit('/account/profile#showcases');
        }

        Audit::log('account.showcase_slot_bought', (int) $account['id']);
        $this->succeed('Showcase slot added.');
        $this->afterEdit('/account/profile#showcases');
    }

    public function toggleLike(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $handle = trim((string) ($params['id'] ?? ''));
        $target = ctype_digit($handle) ? User::find((int) $handle) : User::findByUsername($handle);
        if ($target === null || (string) ($target['status'] ?? '') === 'deleted' || (int) $target['id'] === (int) $account['id']) {
            App::notFound();
        }

        $liked = ProfileLike::toggle((int) $account['id'], (int) $target['id']);
        $this->succeed($liked ? 'Profile liked.' : 'Like removed.');
        $this->redirect(account_url($target));
    }

    /** Toggle a single showcase from the studio editor without touching siblings. */
    public function toggleShowcaseVisibility(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        if ((int) ($account['private_profile'] ?? 0) === 1) {
            $this->fail('Showcases stay disabled while your profile is private.');
            $this->afterEdit('/account/profile#showcases');
        }
        $showcase = Showcase::find((int) ($params['id'] ?? 0), (int) $account['id']);
        if ($showcase === null) {
            $this->fail('Showcase not found.');
            $this->afterEdit('/account/profile#showcases');
        }
        Showcase::setVisibility((int) $account['id'], (int) $showcase['id'], $this->input('visible') === '1');
        $this->succeed($this->input('visible') === '1' ? 'Showcase shown.' : 'Showcase hidden.');
        $this->afterEdit('/account/profile#showcases');
    }

    public function updateShowcaseVisibility(): void
    {
        $this->csrf();
        $account = $this->auth();
        if ((int) ($account['private_profile'] ?? 0) === 1) {
            $this->fail('Showcases stay disabled while your profile is private.');
            $this->afterEdit('/account/profile#showcases');
        }
        $visible = (array) $this->input('visible_showcases', []);

        foreach (Showcase::forAccount((int) $account['id']) as $showcase) {
            $show = in_array((string) $showcase['id'], $visible, true);
            Showcase::setVisibility((int) $account['id'], (int) $showcase['id'], $show);
        }

        $this->succeed('Showcase visibility saved.');
        $this->afterEdit('/account/profile#showcases');
    }

    public function deleteShowcase(array $params): void
    {
        $this->csrf();
        $account = $this->auth();
        $showcase = Showcase::find((int) ($params['id'] ?? 0), (int) $account['id']);
        if ($showcase === null) {
            $this->fail('That showcase does not exist.');
            $this->afterEdit('/account/profile#showcases');
        }
        if (in_array((string) $showcase['type'], Showcase::FIXED_TYPES, true)) {
            $this->fail('This showcase can be turned off, but not removed.');
            $this->afterEdit('/account/profile#showcases');
        }
        // Team showcase is optional and can be hidden or removed even while you have teams.

        if ((string) $showcase['type'] === 'media') {
            delete_upload('media', (string) ($showcase['body'] ?? ''));
        }
        Showcase::delete((int) $showcase['id'], (int) $account['id']);
        Audit::log('account.showcase_deleted', (int) $account['id']);
        $this->succeed('Showcase removed. Media points are not refunded.');
        $this->afterEdit('/account/profile#showcases');
    }

    public function updateConnectionVisibility(): void
    {
        $this->csrf();
        $account = $this->auth();
        $visible = (array) $this->input('visible', []);

        foreach (SocialIdentity::forAccount((int) $account['id']) as $identity) {
            SocialIdentity::setVisibility(
                (int) $identity['id'],
                (int) $account['id'],
                in_array((string) $identity['provider'], $visible, true)
            );
        }

        Audit::log('account.social_visibility_updated', (int) $account['id']);
        $this->succeed('Provider visibility saved.');

        $this->redirect('/account/connections?tab=social');
    }

    /** @return array{0: array{project: string, url: string, rating: int, text: string}, 1: array<string, string>} */
    private function reviewInput(): array
    {
        $project = trim((string) $this->input('review_project'));
        $url = trim((string) $this->input('review_url'));
        $rating = (int) $this->input('review_rating', 0);
        $text = trim((string) $this->input('review_text'));
        $errors = [];

        if (mb_strlen($project) < 2 || mb_strlen($project) > 60) {
            $errors['review_project'] = 'Use a project name between 2 and 60 characters.';
        }
        if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || preg_match('~^https?://~i', $url) !== 1 || mb_strlen($url) > 300)) {
            $errors['review_url'] = 'Use a full http:// or https:// link, or leave it empty.';
        }
        if ($rating < 1 || $rating > 5) {
            $errors['review_rating'] = 'Choose a rating from 1 to 5 stars.';
        }
        if (mb_strlen($text) < 2 || mb_strlen($text) > 500) {
            $errors['review_text'] = 'Use a review between 2 and 500 characters.';
        }

        return [['project' => $project, 'url' => $url, 'rating' => $rating, 'text' => $text], $errors];
    }
}
