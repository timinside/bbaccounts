<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\App;
use BBA\Core\Auth;
use BBA\Core\Config;
use BBA\Core\Request;
use BBA\Core\Response;
use BBA\Models\Badge;
use BBA\Models\Comment;
use BBA\Models\Friend;
use BBA\Models\ProfileLike;
use BBA\Models\Project;
use BBA\Models\Showcase;
use BBA\Models\SocialIdentity;
use BBA\Models\Team;
use BBA\Models\User;
use BBA\Services\ForumNews;

final class HomeController extends Controller
{
    public function index(): void
    {
        $forumNews = ForumNews::latest();
        $this->view('home/index', [
            'title' => 'Accounts for your projects',
            'account' => Auth::user(),
            'forumNews' => $forumNews['items'],
            'forumNewsError' => $forumNews['error'],
        ], 'layouts/site');
    }

    public function docs(): void
    {
        $target = docs_url('/docs');
        if (trim((string) Config::get('docs_url', '')) !== '') {
            Response::redirect($target);
        }
        $this->abort(404);
    }

    public function docsInstallation(): void
    {
        $target = docs_url('/docs/installation');
        if (trim((string) Config::get('docs_url', '')) !== '') {
            Response::redirect($target);
        }
        $this->abort(404);
    }

    public function lang(array $params): void
    {
        $code = strtolower(trim((string) ($params['code'] ?? 'en')));
        $available = \BBA\Core\I18n::available();
        if (isset($available[$code]) && empty($available[$code]['planned'])) {
            \BBA\Core\I18n::remember($code);
        }
        $next = safe_next_path((string) Request::query('next', '/'));
        Response::redirect(url($next));
    }

    public function projects(): void
    {
        $q = Request::query('q', '');
        $query = trim(is_scalar($q) ? (string) $q : '');
        $status = in_array(Request::query('status'), ['active', 'disabled'], true)
            ? (string) Request::query('status')
            : '';
        $configured = in_array(Request::query('configured'), ['yes', 'no'], true)
            ? (string) Request::query('configured')
            : '';
        $result = Project::directory($query, $status, $configured, max(1, (int) Request::query('page', 1)));

        $this->view('home/directory', [
            'title' => 'Projects',
            'account' => Auth::user(),
            'kind' => 'projects',
            'items' => $result['rows'],
            'query' => $query,
            'status' => $status,
            'filter' => $configured,
            'page' => $result['page'],
            'pages' => $result['pages'],
            'total' => $result['total'],
        ], 'layouts/site');
    }

    public function directory(): void
    {
        User::restoreExpired();

        $q = Request::query('q', '');
        $query = trim(is_scalar($q) ? (string) $q : '');
        $role = in_array(Request::query('role'), ['admin', 'developer', 'tester', 'moderator', 'member'], true)
            ? (string) Request::query('role')
            : '';
        $status = in_array(Request::query('status'), ['active', 'suspended', 'banned'], true)
            ? (string) Request::query('status')
            : '';
        $result = User::directory($query, $role, $status, max(1, (int) Request::query('page', 1)));

        $this->view('home/directory', [
            'title' => 'Users',
            'account' => Auth::user(),
            'kind' => 'users',
            'items' => $result['rows'],
            'query' => $query,
            'status' => $status,
            'filter' => $role,
            'page' => $result['page'],
            'pages' => $result['pages'],
            'total' => $result['total'],
        ], 'layouts/site');
    }

    public function profile(array $params): void
    {
        $handle = trim((string) ($params['id'] ?? ''));
        $account = ctype_digit($handle) ? User::find((int) $handle) : User::findByUsername($handle);
        if ($account === null || (string) ($account['status'] ?? '') === 'deleted') {
            App::notFound();
        }
        $id = (int) $account['id'];

        $social = [];
        if ((int) ($account['share_social'] ?? 1) === 1) {
            foreach (SocialIdentity::forAccount($id) as $identity) {
                $username = trim((string) ($identity['username'] ?? ''));
                if ($username !== '') {
                    $social[(string) $identity['provider']] = $username;
                }
            }
        }

        Badge::syncAutomatic($account);
        $showcases = Showcase::visibleForAccount($id);
        $projects = [];
        foreach ($showcases as $showcase) {
            if ((string) $showcase['type'] === 'projects') {
                $projects = array_values(array_filter(
                    Project::forOwner($id),
                    static fn (array $project): bool => (string) $project['status'] === 'active'
                ));
                break;
            }
        }

        $viewer = Auth::user();
        $isOwner = $viewer !== null && (int) $viewer['id'] === $id;

        $friendState = null;
        $friendRequestId = 0;
        if ($viewer !== null && !$isOwner) {
            $relation = Friend::relation((int) $viewer['id'], $id);
            if ($relation !== null) {
                if ((string) $relation['status'] === 'accepted') {
                    $friendState = 'friends';
                } elseif ((int) $relation['requester_id'] === (int) $viewer['id']) {
                    $friendState = 'sent';
                } else {
                    $friendState = 'incoming';
                    $friendRequestId = (int) $relation['id'];
                }
            }
        }

        $isPrivate = (int) ($account['private_profile'] ?? 0) === 1;
        $fullView = !$isPrivate || $isOwner || $friendState === 'friends';
        if (!$fullView) {
            $social = [];
            $showcases = [];
            $projects = [];
        }

        $teams = [];
        foreach ($showcases as $showcase) {
            if ((string) $showcase['type'] !== 'team') {
                continue;
            }
            $includeSubscribed = (int) (Showcase::meta($showcase)['include_subscribed'] ?? 0) === 1;
            $teams[(int) $showcase['id']] = [
                'include_subscribed' => $includeSubscribed,
                'rows' => $fullView ? Team::forProfile($id, $includeSubscribed) : [],
            ];
        }

        // Studio is the only profile editor; the classic toggle is retired.
        $studioAllowed = $isOwner;
        $preview = $isOwner && (string) \BBA\Core\Request::query('preview', '') === '1';
        $studio = $studioAllowed && !$preview;
        $studioData = [];
        if ($studioAllowed) {
            $studioData = [
                'studioShowcases' => Showcase::forAccount($id),
                'studioBadges' => Badge::forAccount($id),
                'showcaseSlots' => Showcase::slotsFor($account),
                'slotCost' => Showcase::SLOT_COST,
                'mediaCost' => Showcase::MEDIA_COST,
                'showcaseDays' => (int) Config::get('account.showcase_days', 30),
                'showcasesAllowed' => showcases_allowed($account),
            ];
        }

        $this->view('home/profile', [
            'title' => (string) $account['display_name'],
            'account' => $viewer,
            'profile' => $account,
            'social' => $social,
            'badges' => $fullView ? Badge::visibleForAccount($id) : [],
            'showcases' => $showcases,
            'teams' => $teams,
            'projects' => $projects,
            'friends' => $fullView ? Friend::forAccount($id) : [],
            'friendState' => $friendState,
            'friendRequestId' => $friendRequestId,
            'privateView' => $isPrivate && !$fullView,
            'fullView' => $fullView,
            'likeCount' => $fullView ? ProfileLike::count($id) : 0,
            'liked' => ($viewer !== null && !$isOwner) ? ProfileLike::exists((int) $viewer['id'], $id) : false,
            'canLike' => $viewer !== null && !$isOwner && $fullView,
            'likers' => $fullView ? ProfileLike::likers($id) : [],
            'comments' => $fullView ? Comment::forTarget('profile', $id) : [],
        ] + ['studio' => $studio, 'studioAllowed' => $studioAllowed, 'preview' => $preview] + $studioData, 'layouts/site');
    }

    public function badge(array $params): void
    {
        $badge = Badge::findBySlug((string) ($params['slug'] ?? ''));
        if ($badge === null) {
            App::notFound();
        }

        $this->view('home/badge', [
            'title' => (string) $badge['name'],
            'account' => Auth::user(),
            'badge' => $badge,
            'holderCount' => Badge::holderCount((int) $badge['id']),
            'holders' => Badge::holders((int) $badge['id']),
        ], 'layouts/site');
    }

    public function rules(): void
    {
        $target = docs_url('/rules');
        if (trim((string) Config::get('docs_url', '')) !== '') {
            Response::redirect($target);
        }
        $this->abort(404);
    }

    public function terms(): void
    {
        $target = docs_url('/terms');
        if (trim((string) Config::get('docs_url', '')) !== '') {
            Response::redirect($target);
        }
        $this->abort(404);
    }

    public function leave(): void
    {
        $url = trim((string) \BBA\Core\Request::query('url', ''));
        if (preg_match('~^https?://~i', $url) !== 1) {
            $this->redirect('/');
        }

        $this->view('home/leave', [
            'title' => 'Leaving bbaccounts',
            'account' => Auth::user(),
            'destination' => $url,
            'host' => (string) (parse_url($url, PHP_URL_HOST) ?? ''),
        ], 'layouts/site');
    }

    public function privacy(): void
    {
        $target = docs_url('/privacy');
        if (trim((string) Config::get('docs_url', '')) !== '') {
            Response::redirect($target);
        }
        $this->abort(404);
    }

    public function unauthorized(): never
    {
        App::unauthorized();
    }

    public function paymentRequired(): never
    {
        App::paymentRequired();
    }

    public function unavailable(): never
    {
        App::unavailable();
    }

    public function avatar(array $params): never
    {
        $id = (int) ($params['id'] ?? 0);
        $account = $id > 0 ? User::find($id) : null;
        $name = $account === null ? '?' : (string) $account['display_name'];
        $initials = initials($name);
        [$from, $to] = User::palette(max(0, $id));
        $color = (string) ($account['avatar_color'] ?? '');
        $fill = preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? $color : null;

        $emoji = ($account['avatar_type'] ?? '') === 'preset' ? trim((string) ($account['avatar_value'] ?? '')) : '';
        $isEmoji = $emoji !== ''
            && preg_match('/^\d+$/', $emoji) !== 1
            && preg_match('/\p{Extended_Pictographic}/u', $emoji) === 1
            && mb_strlen($emoji) <= 16;
        $text = $isEmoji ? $emoji : $initials;
        $fontSize = $isEmoji ? 42 : 48;
        $fontFamily = $isEmoji
            ? "'Segoe UI Emoji','Apple Color Emoji','Noto Color Emoji','Twemoji Mozilla',sans-serif"
            : 'system-ui, -apple-system, Segoe UI, sans-serif';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="128" height="128" viewBox="0 0 128 128">'
            . ($fill === null
                ? '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">'
                    . '<stop offset="0" stop-color="' . $from . '"/><stop offset="1" stop-color="' . $to . '"/>'
                    . '</linearGradient></defs>'
                : '')
            . '<rect width="128" height="128" rx="64" fill="' . ($fill ?? 'url(#g)') . '"/>'
            . '<text x="64" y="64" dy=".36em" text-anchor="middle" font-family="' . $fontFamily . '" font-size="' . $fontSize . '" font-weight="600" fill="#ffffff">'
            . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</text></svg>';

        Response::send($svg, 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400, immutable',
        ]);
    }
}
