<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\App;
use BBA\Core\Audit;
use BBA\Core\Auth;
use BBA\Core\Config;
use BBA\Core\Db;
use BBA\Core\RateLimiter;
use BBA\Core\Request;
use BBA\Core\Response;
use BBA\Core\View;
use BBA\Models\Badge;
use BBA\Models\Comment;
use BBA\Models\Friend;
use BBA\Models\Guard;
use BBA\Models\Team;
use BBA\Models\Project;
use BBA\Models\ProjectCell;
use BBA\Models\Showcase;
use BBA\Models\User;
use BBA\Services\OAuthException;
use BBA\Services\OAuthService;

final class OAuthController extends Controller
{
    public function authorize(): void
    {
        try {
            $params = OAuthService::authorizeParams($_GET);
        } catch (OAuthException $e) {
            $this->errorPage($e->error, $e->description);

            return;
        }

        if (!Auth::check()) {
            $this->view('oauth/login', [
                'title' => 'Sign in to ' . $params['project']['name'],
                'params' => $params,
                'fields' => $this->fields($params),
                'next' => '/oauth/authorize?' . http_build_query($_GET),
            ], 'layouts/auth');

            return;
        }

        $account = Auth::user();
        $owner = User::find((int) $params['project']['owner_id']);
        $ownerWarning = $owner !== null && (int) $owner['id'] !== (int) $account['id']
            ? trim((string) ($owner['warning_text'] ?? ''))
            : '';
        $consent = OAuthService::consentFor((int) $account['id'], (int) $params['project']['id']);
        $prompt = strtolower(trim((string) ($_GET['prompt'] ?? '')));
        $needsConsent = $prompt === 'consent' || !OAuthService::consentCovers($consent, $params['scopes'])
            || $ownerWarning !== '';

        if ($needsConsent) {
            $this->view('oauth/consent', [
                'title' => 'Continue to ' . $params['project']['name'],
                'params' => $params,
                'fields' => $this->fields($params),
                'account' => $account,
                'ownerWarning' => $ownerWarning,
                'ownerName' => (string) ($owner['display_name'] ?? ''),
                'switchUrl' => '/account/switch?next=' . rawurlencode('/oauth/authorize?' . http_build_query($_GET)),
            ], 'layouts/auth');

            return;
        }

        $code = OAuthService::issueCode($params, (int) $account['id']);
        OAuthService::redirectWithCode($params['redirect_uri'], $code, $params['state']);
    }

    public function consent(): void
    {
        $this->csrf();
        $input = array_merge($_POST, Request::json() ?? []);

        try {
            $params = OAuthService::authorizeParams($input);
        } catch (OAuthException $e) {
            $this->errorPage($e->error, $e->description);

            return;
        }

        if (!Auth::check()) {
            $query = http_build_query(array_intersect_key($input, array_flip([
                'client_id', 'redirect_uri', 'response_type', 'scope', 'state', 'code_challenge', 'code_challenge_method', 'prompt',
            ])));

            $this->redirect('/login?next=' . rawurlencode('/oauth/authorize?' . $query));
        }

        $account = Auth::user();
        $decision = (string) ($input['decision'] ?? 'deny');

        if ($decision !== 'allow') {
            Audit::log('oauth.consent_denied', (int) $account['id'], (int) $params['project']['id']);
            OAuthService::redirectWithError($params['redirect_uri'], 'access_denied', 'The user denied access.', $params['state']);
        }

        OAuthService::saveConsent((int) $account['id'], (int) $params['project']['id'], $params['scope']);
        Audit::log('oauth.consent_granted', (int) $account['id'], (int) $params['project']['id'], null, null, [
            'scope' => $params['scope'],
        ]);

        $code = OAuthService::issueCode($params, (int) $account['id']);
        OAuthService::redirectWithCode($params['redirect_uri'], $code, $params['state']);
    }

    public function token(): void
    {
        Response::cors();

        if (Request::method() !== 'POST') {
            Response::json(['error' => 'invalid_request', 'error_description' => 'Use POST.'], 405);

            return;
        }

        if (!RateLimiter::attempt('oauth_token', Request::ip(), (int) Config::get('limits.token', 30), 60)) {
            Response::json(['error' => 'slow_down', 'error_description' => 'Too many token requests.'], 429, [
                'Retry-After' => '60',
            ]);

            return;
        }

        $input = array_merge($_POST, Request::json() ?? []);
        $grant = (string) ($input['grant_type'] ?? '');

        try {
            $result = match ($grant) {
                'authorization_code' => OAuthService::exchangeCode($input),
                'refresh_token' => OAuthService::refresh($input),
                default => throw new OAuthException('unsupported_grant_type', 'Unsupported grant_type.'),
            };
        } catch (OAuthException $e) {
            $headers = in_array($e->error, ['invalid_client', 'invalid_token'], true)
                ? ['WWW-Authenticate' => 'Basic realm="bbaccounts"']
                : [];
            Response::json(['error' => $e->error, 'error_description' => $e->description], $e->status, $headers);

            return;
        }

        Response::json($result);
    }

    public function userinfo(): void
    {
        Response::cors();

        $token = Request::bearerToken();
        try {
            $data = OAuthService::userinfo($token ?? '');
        } catch (OAuthException $e) {
            Response::json(['error' => $e->error, 'error_description' => $e->description], $e->status, [
                'WWW-Authenticate' => 'Bearer error="invalid_token"',
            ]);

            return;
        }

        Response::json($data);
    }

    /** Verified-project API token, or a JSON error response. */
    private function requireApiToken(string $scope): ?array
    {
        $token = Request::bearerToken();
        try {
            return OAuthService::apiToken($token ?? '', $scope);
        } catch (OAuthException $e) {
            Response::json(['error' => $e->error, 'error_description' => $e->description], $e->status, [
                'WWW-Authenticate' => 'Bearer error="' . $e->error . '"',
            ]);
        }
    }

    /** Guard secret and current code for a verified companion app. */
    public function guard(): void
    {
        Response::cors();
        $row = $this->requireApiToken('guard');
        if ($row === null) {
            return;
        }

        $account = User::find((int) $row['account_id']);
        if ($account === null) {
            Response::json(['error' => 'not_found', 'error_description' => 'Unknown account.'], 404);

            return;
        }

        $enabled = Guard::enabled($account);
        $secret = $enabled ? Guard::secretFor($account) : '';
        Response::json([
            'enabled' => $enabled,
            'secret' => $secret,
            'code' => $enabled ? Guard::code($secret) : '',
            'digits' => Guard::DIGITS,
            'period' => Guard::STEP,
            'expires_in' => Guard::expiresIn(),
        ]);
    }

    public function guardOptions(): void
    {
        Response::cors();
        Response::send('', 204);
    }

    /** Balance lookup and point changes for verified projects. */
    public function points(): void
    {
        Response::cors();

        $row = $this->requireApiToken('points');
        if ($row === null) {
            return;
        }

        $accountId = (int) $row['account_id'];
        $balance = (int) $row['points'];

        if (Request::method() === 'POST') {
            $input = Request::json() ?? [];
            $raw = $input['amount'] ?? null;
            if (filter_var($raw, FILTER_VALIDATE_INT) === false) {
                Response::json(['error' => 'invalid_request', 'error_description' => 'amount must be a whole number.'], 400);

                return;
            }
            $amount = (int) $raw;
            if ($amount === 0 || abs($amount) > OAuthService::POINTS_MAX) {
                Response::json([
                    'error' => 'invalid_request',
                    'error_description' => 'amount must be between -' . OAuthService::POINTS_MAX . ' and ' . OAuthService::POINTS_MAX . ', excluding zero.',
                ], 400);

                return;
            }

            User::addPoints($accountId, $amount);
            $balance = (int) Db::value('SELECT points FROM accounts WHERE id = ?', [$accountId]);
            Audit::log('api.points_changed', $accountId, null, 'account', $accountId, [
                'amount' => $amount,
                'project_id' => (int) $row['project_id'],
            ]);
        }

        Response::json(['points' => $balance]);
    }

    public function pointsOptions(): void
    {
        Response::cors();
        Response::send('', 204);
    }

    /** Friend list and requests for verified projects. */
    public function friends(array $params = []): void
    {
        Response::cors();

        $row = $this->requireApiToken('friends');
        if ($row === null) {
            return;
        }

        $accountId = (int) $row['account_id'];
        $method = Request::method();
        $input = $method === 'GET' ? [] : (Request::json() ?? $_POST);

        if ($method === 'POST' && !isset($params['id'])) {
            $username = mb_strtolower(trim((string) ($input['username'] ?? '')));
            $target = $username !== '' ? User::findByUsername($username) : null;
            if ($target === null) {
                Response::json(['error' => 'invalid_request', 'error_description' => 'Unknown username.'], 404);

                return;
            }

            $state = Friend::request($accountId, (int) $target['id']);
            if ($state === 'full') {
                Response::json(['error' => 'limit_reached', 'error_description' => 'A friend list is full.'], 409);

                return;
            }
            if ($state === 'self') {
                Response::json(['error' => 'invalid_request', 'error_description' => 'You cannot friend yourself.'], 400);

                return;
            }

            Response::json(['state' => $state, 'username' => (string) $target['username']]);
        }

        if (isset($params['id'])) {
            $friendId = (int) $params['id'];
            if ($method !== 'POST') {
                Response::json(['error' => 'invalid_request', 'error_description' => 'Use POST.'], 405);

                return;
            }
            if (!Friend::remove($accountId, $friendId)) {
                Response::json(['error' => 'not_found', 'error_description' => 'No friendship or request with that account.'], 404);

                return;
            }
            Response::json(['removed' => true, 'account_id' => $friendId]);
        }

        if ($method !== 'GET') {
            Response::json(['error' => 'invalid_request', 'error_description' => 'Use GET or POST.'], 405);

            return;
        }

        $friends = array_map(static fn (array $friend): array => [
            'id' => (int) $friend['id'],
            'username' => (string) ($friend['username'] ?? ''),
            'display_name' => (string) $friend['display_name'],
            'avatar_url' => avatar_url($friend),
            'profile_url' => url('/u/' . rawurlencode((string) ($friend['username'] ?? $friend['id']))),
        ], Friend::forAccount($accountId));

        Response::json([
            'count' => count($friends),
            'limit' => Friend::limitFor($accountId),
            'friends' => $friends,
            'incoming' => Friend::pendingIncoming($accountId),
            'outgoing' => Friend::pendingOutgoing($accountId),
        ]);
    }

    public function friendsOptions(): void
    {
        Response::cors();
        Response::send('', 204);
    }

    /** Public profile fields; token optional when username is provided. */
    public function profile(): void
    {
        Response::cors();
        $token = Request::bearerToken();
        $handle = mb_strtolower(trim((string) Request::query('username', '')));
        $row = null;
        if ($token !== null && $token !== '') {
            $row = $this->requireApiToken('profile');
            if ($row === null) {
                return;
            }
        } elseif ($handle === '') {
            Response::json(['error' => 'invalid_token', 'error_description' => 'A bearer token is required when username is not provided.'], 401, [
                'WWW-Authenticate' => 'Bearer error="invalid_token"',
            ]);
            return;
        }

        $account = $handle !== ''
            ? User::findByUsername($handle)
            : User::find((int) $row['account_id']);
        if ($account === null || (string) ($account['status'] ?? '') === 'deleted') {
            Response::json(['error' => 'not_found', 'error_description' => 'Unknown account.'], 404);

            return;
        }

        $id = (int) $account['id'];
        $viewerId = $row !== null ? (int) $row['account_id'] : 0;
        $self = $viewerId > 0 && $id === $viewerId;
        $relation = ($viewerId > 0 && !$self) ? Friend::relation($viewerId, $id) : null;
        $friend = $relation !== null && (string) $relation['status'] === 'accepted';
        $full = (int) ($account['private_profile'] ?? 0) !== 1 || $self || $friend;
        $none = 'none';

        $banner = trim((string) ($account['profile_banner'] ?? ''));
        $background = trim((string) ($account['profile_background'] ?? ''));
        $data = [
            'display_name' => (string) $account['display_name'],
            'username' => (string) ($account['username'] ?? ''),
            'avatar_url' => avatar_url($account),
            'banner_url' => $banner !== '' ? url('/uploads/banners/' . rawurlencode($banner)) : $none,
            'background_url' => $background !== '' ? url('/uploads/banners/' . rawurlencode($background)) : $none,
            'description' => $none,
            'status_text' => $none,
            'status_icon' => $none,
            'steam_id64' => $none,
            'telegram' => $none,
            'discord' => $none,
            'email' => $none,
            'level' => $none,
            'created_at' => $none,
            'last_online' => $none,
            'showcases' => $none,
        ];

        if ($full) {
            $description = trim((string) ($account['description'] ?? ''));
            $statusText = trim((string) ($account['status_text'] ?? ''));
            $statusIcon = trim((string) ($account['status_icon'] ?? ''));
            $data['description'] = $description !== '' ? $description : $none;
            $data['status_text'] = $statusText !== '' ? $statusText : $none;
            $data['status_icon'] = $statusIcon !== '' ? $statusIcon : $none;
            $data['level'] = level_info($account)['label'];

            $created = strtotime((string) ($account['created_at'] ?? ''));
            $online = strtotime((string) ($account['last_login_at'] ?? ''));
            $data['created_at'] = $created === false ? $none : date('c', $created);
            $data['last_online'] = $online === false ? $none : date('c', $online);

            if ((int) ($account['share_social'] ?? 1) === 1) {
                foreach (profile_contacts($account) as $contact) {
                    $kind = (string) $contact['kind'];
                    $value = (string) $contact['value'];
                    if (array_key_exists($kind, $data)) {
                        $data[$kind] = $value !== '' ? $value : $none;
                    }
                    if ($kind === 'steam' && preg_match('/^\d{17}$/', $value) === 1) {
                        $data['steam_id64'] = $value;
                    }
                }
            }

            $showcases = [];
            foreach (Showcase::visibleForAccount($id) as $showcase) {
                $showcases[] = ['type' => (string) $showcase['type'], 'title' => (string) $showcase['title']];
            }
            $data['showcases'] = $showcases === [] ? $none : $showcases;
        }

        Response::json($data);
    }

    public function profileOptions(): void
    {
        Response::cors();
        Response::send('', 204);
    }

    /** Badges created and issued by this verified project. */
    public function badges(): void
    {
        Response::cors();
        $row = $this->requireApiToken('badges');
        if ($row === null) {
            return;
        }

        $projectId = (int) $row['project_id'];
        if (Request::method() !== 'POST') {
            $badges = array_map(static fn (array $badge): array => [
                'id' => (int) $badge['id'],
                'slug' => (string) $badge['slug'],
                'name' => (string) $badge['name'],
                'description' => (string) ($badge['description'] ?? ''),
            ], Badge::forProject($projectId));
            Response::json(['count' => count($badges), 'badges' => $badges]);

            return;
        }

        $input = Request::json() ?? [];
        $name = trim((string) ($input['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            Response::json(['error' => 'invalid_request', 'error_description' => 'name must be 2-60 characters.'], 400);

            return;
        }
        $badge = Badge::createForProject($projectId, $name, trim((string) ($input['description'] ?? '')));
        if ($badge === null) {
            Response::json(['error' => 'invalid_request', 'error_description' => 'Could not create that badge.'], 400);

            return;
        }
        Audit::log('api.badge_created', (int) $row['account_id'], $projectId, 'badge', (int) $badge['id']);
        Response::json([
            'id' => (int) $badge['id'],
            'slug' => (string) $badge['slug'],
            'name' => (string) $badge['name'],
            'url' => url('/badges/' . rawurlencode((string) $badge['slug'])),
        ], 201);
    }

    public function issueBadge(array $params): void
    {
        Response::cors();
        $row = $this->requireApiToken('badges');
        if ($row === null) {
            return;
        }

        $raw = trim((string) ($params['id'] ?? $params['slug'] ?? ''));
        $badge = ctype_digit($raw) ? Badge::findById((int) $raw) : Badge::findBySlug($raw);
        if ($badge === null || !Badge::ownedByProject((int) $badge['id'], (int) $row['project_id'])) {
            Response::json(['error' => 'not_found', 'error_description' => 'Unknown badge for this project.'], 404);

            return;
        }

        $input = Request::json() ?? [];
        $username = mb_strtolower(trim((string) ($input['username'] ?? '')));
        $target = $username !== '' ? User::findByUsername($username) : null;
        if ($target === null || (string) ($target['status'] ?? '') === 'deleted') {
            Response::json(['error' => 'invalid_request', 'error_description' => 'Unknown username.'], 404);

            return;
        }

        Badge::assign((int) $target['id'], (int) $badge['id'], (int) $row['account_id']);
        Audit::log('api.badge_issued', (int) $target['id'], (int) $row['project_id'], 'badge', (int) $badge['id']);
        Response::json([
            'account_id' => (int) $target['id'],
            'username' => (string) $target['username'],
            'badge_id' => (int) $badge['id'],
            'slug' => (string) $badge['slug'],
        ]);
    }

    public function badgesOptions(): void
    {
        Response::cors();
        Response::send('', 204);
    }

    /** Team membership and subscriptions for bbchat's automatic chats. */
    public function teams(): void
    {
        Response::cors();
        $row = $this->requireApiToken('teams');
        if ($row === null) {
            return;
        }
        Response::json(Team::apiForAccount((int) $row['account_id']));
    }

    public function teamsOptions(): void
    {
        Response::cors();
        Response::send('', 204);
    }

    public function revoke(): void
    {
        Response::cors();

        if (Request::method() !== 'POST') {
            Response::json(['error' => 'invalid_request', 'error_description' => 'Use POST.'], 405);

            return;
        }

        $input = array_merge($_POST, Request::json() ?? []);
        try {
            OAuthService::revoke($input);
        } catch (OAuthException $e) {
            Response::json(['error' => $e->error, 'error_description' => $e->description], $e->status);

            return;
        }

        Response::json(['revoked' => true]);
    }

    public function tokenOptions(): void
    {
        Response::cors();
        Response::send('', 204);
    }

    public function userinfoOptions(): void
    {
        Response::cors();
        Response::send('', 204);
    }

    public function revokeOptions(): void
    {
        Response::cors();
        Response::send('', 204);
    }

    public function projectPage(array $params): void
    {
        $project = Project::findBySlug((string) ($params['slug'] ?? ''));
        if ($project === null) {
            App::notFound();
        }

        $owner = User::find((int) $project['owner_id']);
        $team = (int) ($project['team_id'] ?? 0) > 0 ? Team::find((int) $project['team_id']) : null;
        $this->view('oauth/project', [
            'title' => (string) $project['name'],
            'project' => $project,
            'owner' => $owner,
            'team' => $team,
            'ownerWarning' => trim((string) ($owner['warning_text'] ?? '')),
            'active' => (string) $project['status'] === 'active' && Project::ownerAvailable($project),
            'configured' => Project::configured((int) $project['id']),
            'viewer' => Auth::user(),
            'comments' => Comment::forTarget('project', (int) $project['id']),
        ], 'layouts/site');
    }

    /** Project storage cells. Verified project scope: cells. */
    public function cells(): void
    {
        Response::cors();
        $row = $this->requireApiToken('cells');
        if ($row === null) {
            return;
        }
        $projectId = (int) $row['project_id'];
        if (Request::method() === 'POST') {
            $this->saveCell($projectId);
            return;
        }

        $username = mb_strtolower(trim((string) Request::query('username', '')));
        $key = trim((string) Request::query('key', ''));
        $scope = trim((string) Request::query('scope', ''));
        $accountId = -1;
        if ($username !== '') {
            $target = User::findByUsername($username);
            if ($target === null || (string) ($target['status'] ?? '') === 'deleted') {
                Response::json(['error' => 'invalid_request', 'error_description' => 'Unknown username.'], 404);
                return;
            }
            $accountId = (int) $target['id'];
        } elseif ($scope === 'project') {
            $accountId = 0;
        }
        $result = ProjectCell::list($projectId, $accountId, $key, max(1, (int) Request::query('page', 1)), max(1, (int) Request::query('limit', 50)));
        $cells = array_map(static function (array $cell): array {
            $data = ProjectCell::serialize($cell);
            unset($data['value']);
            return $data;
        }, $result['rows']);
        Response::json([
            'count' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'cells' => $cells,
        ]);
    }

    public function cell(array $params): void
    {
        Response::cors();
        $row = $this->requireApiToken('cells');
        if ($row === null) {
            return;
        }
        $projectId = (int) $row['project_id'];
        $cell = ProjectCell::findById((int) ($params['id'] ?? 0));
        if ($cell === null || (int) $cell['project_id'] !== $projectId) {
            Response::json(['error' => 'not_found', 'error_description' => 'Unknown cell for this project.'], 404);
            return;
        }

        $method = Request::method();
        if ($method === 'GET') {
            Response::json(ProjectCell::serialize($cell));
            return;
        }
        if ($method === 'DELETE') {
            ProjectCell::delete((int) $cell['id']);
            Response::json(['deleted' => true, 'id' => (int) $cell['id']]);
            return;
        }
        if ($method === 'PUT' || $method === 'POST') {
            $input = Request::json() ?? [];
            $value = (string) ($input['value'] ?? '');
            if ($error = ProjectCell::validateValue($value)) {
                Response::json(['error' => 'invalid_request', 'error_description' => $error], 400);
                return;
            }
            $updated = ProjectCell::updateValue((int) $cell['id'], $value);
            Response::json($updated !== null ? ProjectCell::serialize($updated) : ['error' => 'not_found'], $updated !== null ? 200 : 404);
            return;
        }

        Response::json(['error' => 'invalid_request', 'error_description' => 'Unsupported method.'], 405);
    }

    public function cellsOptions(): void
    {
        Response::cors();
        Response::send('', 204);
    }

    private function saveCell(int $projectId): void
    {
        $input = Request::json() ?? [];
        $key = trim((string) ($input['key'] ?? ''));
        $value = (string) ($input['value'] ?? '');
        $username = mb_strtolower(trim((string) ($input['username'] ?? '')));

        if ($error = ProjectCell::validateKey($key)) {
            Response::json(['error' => 'invalid_request', 'error_description' => $error], 400);
            return;
        }
        if ($error = ProjectCell::validateValue($value)) {
            Response::json(['error' => 'invalid_request', 'error_description' => $error], 400);
            return;
        }

        $accountId = 0;
        if ($username !== '') {
            $target = User::findByUsername($username);
            if ($target === null || (string) ($target['status'] ?? '') === 'deleted') {
                Response::json(['error' => 'invalid_request', 'error_description' => 'Unknown username.'], 404);
                return;
            }
            $accountId = (int) $target['id'];
        }

        $cell = ProjectCell::upsert($projectId, $accountId, $key, $value);
        Response::json(ProjectCell::serialize($cell), 201);
    }

    private function fields(array $params): array
    {
        return array_filter([
            'client_id' => $params['client_id'],
            'redirect_uri' => $params['redirect_uri'],
            'response_type' => 'code',
            'scope' => $params['scope'],
            'state' => $params['state'],
            'code_challenge' => $params['code_challenge'],
            'code_challenge_method' => $params['code_challenge_method'],
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function errorPage(string $error, string $description): never
    {
        Response::html(View::render('oauth/error', [
            'title' => 'Authorization error',
            'error' => $error,
            'description' => $description,
        ], 'layouts/plain'), 400);
    }
}
