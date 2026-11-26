<?php
declare(strict_types=1);

namespace BBA\Controllers;

use BBA\Core\App;
use BBA\Core\Audit;
use BBA\Core\Auth;
use BBA\Core\Config;
use BBA\Core\Request;
use BBA\Core\Session;
use BBA\Models\Badge;
use BBA\Models\Project;
use BBA\Models\Team;
use BBA\Models\Verification;

final class DeveloperController extends Controller
{
    public function index(): void
    {
        $account = $this->developer();

        $this->view('developer/index', [
            'title' => 'Developer',
            'account' => $account,
            'projects' => Project::forOwner((int) $account['id']),
        ]);
    }

    public function create(): void
    {
        $this->view('developer/new', [
            'title' => 'New project',
            'account' => $this->developer(),
        ]);
    }

    public function store(): void
    {
        $this->csrf();
        $account = $this->developer();

        $name = trim((string) $this->input('name'));
        $description = trim((string) $this->input('description'));
        $domain = $this->normalizeDomain((string) $this->input('domain'));
        $accent = $this->normalizeAccent((string) $this->input('accent_color', '#5b6cff'));

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors['name'] = 'Use between 2 and 80 characters.';
        }
        if (mb_strlen($description) > 280) {
            $errors['description'] = 'Keep the description under 280 characters.';
        }
        if ($domain === null) {
            $errors['domain'] = 'Enter a domain or IP address, like app.example.com or bbaccounts.unaux.com.';
        }
        if (mb_strlen((string) $this->input('name')) > 80) {
            $errors['name'] = 'Use at most 80 characters.';
        }
        if ($errors !== []) {
            $this->back($errors, [
                'name' => $name,
                'description' => $description,
                'domain' => (string) $this->input('domain'),
                'accent_color' => $accent,
            ], '/developer/projects/new');
        }

        $slug = Project::makeSlug($name);
        $projectId = Project::create((int) $account['id'], $name, $slug, $description, (string) $domain, $accent);
        $credential = Project::createCredential($projectId, 'Default');
        Session::set('_new_secret', $credential);
        Audit::log('developer.project_created', (int) $account['id'], $projectId);
        $this->succeed('Project created. Save the client secret shown below - it cannot be recovered later.');

        $this->redirect('/developer/projects/' . $projectId);
    }

    public function show(array $params): void
    {
        $account = $this->developer();
        $project = $this->projectOrFail($params, (int) $account['id']);
        $secret = Session::pull('_new_secret');

        $this->view('developer/show', [
            'title' => (string) $project['name'],
            'account' => $account,
            'project' => $project,
            'redirects' => Project::redirects((int) $project['id']),
            'credentials' => Project::credentials((int) $project['id']),
            'consentCount' => Project::consentCount((int) $project['id']),
            'tokenCount' => Project::tokenCount((int) $project['id']),
            'teams' => Team::forOwner((int) $account['id']),
            'badges' => Badge::forProject((int) $project['id']),
            'verified' => Verification::for('project', (int) $project['id']) !== null,
            'newSecret' => is_array($secret) ? $secret : null,
        ]);
    }

    public function createBadge(array $params): void
    {
        $this->csrf();
        $account = $this->developer();
        $project = $this->projectOrFail($params, (int) $account['id']);
        if (Verification::for('project', (int) $project['id']) === null) {
            App::forbidden('Badges unlock after your project is verified.');
        }

        $name = trim((string) $this->input('name'));
        $description = trim((string) $this->input('description'));
        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
            $errors['name'] = 'Use between 2 and 60 characters.';
        }
        if (mb_strlen($description) > 160) {
            $errors['description'] = 'Keep the description under 160 characters.';
        }
        if ($errors !== []) {
            $this->back($errors, ['name' => $name, 'description' => $description]);
        }

        $badge = Badge::createForProject((int) $project['id'], $name, $description);
        if ($badge === null) {
            $this->back(['name' => 'Could not create that badge.']);
        }
        Audit::log('developer.badge_created', (int) $account['id'], (int) $project['id'], 'badge', (int) $badge['id']);
        $this->succeed('Badge created. You can issue it through the badges API.');

        $this->redirect('/developer/projects/' . (int) $project['id']);
    }

    public function update(array $params): void
    {
        $this->csrf();
        $account = $this->developer();
        $project = $this->projectOrFail($params, (int) $account['id']);

        $name = trim((string) $this->input('name'));
        $description = trim((string) $this->input('description'));
        $domain = $this->normalizeDomain((string) $this->input('domain'));
        $accent = $this->normalizeAccent((string) $this->input('accent_color', (string) $project['accent_color']));
        $loginUrl = trim((string) $this->input('login_url'));
        $teamId = (int) $this->input('team_id', 0);
        $iconTransparent = $this->input('icon_transparent') === '1' ? 1 : 0;
        $team = $teamId > 0 ? Team::find($teamId) : null;
        if ($team === null || (int) $team['owner_id'] !== (int) $account['id']) {
            $teamId = 0;
        }

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            $errors['name'] = 'Use between 2 and 80 characters.';
        }
        if (mb_strlen($description) > 280) {
            $errors['description'] = 'Keep the description under 280 characters.';
        }
        if ($domain === null) {
            $errors['domain'] = 'Enter a domain or IP address, like app.example.com or bbaccounts.unaux.com.';
        }
        if ($loginUrl !== '' && (preg_match('~^https?://[^\s]+$~i', $loginUrl) !== 1 || mb_strlen($loginUrl) > 500)) {
            $errors['login_url'] = 'Enter a full http:// or https:// link.';
        }
        if ($errors !== []) {
            $this->back($errors, [
                'name' => $name,
                'description' => $description,
                'domain' => (string) $this->input('domain'),
                'accent_color' => $accent,
                'login_url' => $loginUrl,
            ]);
        }

        Project::update((int) $project['id'], (int) $account['id'], [
            'name' => $name,
            'description' => $description,
            'domain' => (string) $domain,
            'accent_color' => $accent,
            'login_url' => $loginUrl !== '' ? $loginUrl : null,
            'team_id' => $teamId > 0 ? $teamId : null,
            'icon_transparent' => $iconTransparent,
        ]);
        Audit::log('developer.project_updated', (int) $account['id'], (int) $project['id']);
        $this->succeed('Project updated.');

        $this->redirect('/developer/projects/' . (int) $project['id']);
    }

    public function addRedirect(array $params): void
    {
        $this->csrf();
        $account = $this->developer();
        $project = $this->projectOrFail($params, (int) $account['id']);
        $uri = trim((string) $this->input('uri'));

        $valid = preg_match('~^https://[^\s]+$~i', $uri) === 1
            || preg_match('~^http://(localhost|\d{1,3}(?:\.\d{1,3}){3}|\[[0-9a-fA-F:]+\])(?::\d{1,5})?(?:/|$)~i', $uri) === 1;

        if (!$valid || mb_strlen($uri) > 500) {
            $this->back(['uri' => 'Use a full https:// URL. http:// is allowed only for localhost or an IP address, with an optional port.']);
        }
        if (Project::redirectExists((int) $project['id'], $uri)) {
            $this->back(['uri' => 'This redirect URI is already registered.']);
        }

        Project::addRedirect((int) $project['id'], $uri);
        Audit::log('developer.redirect_added', (int) $account['id'], (int) $project['id'], 'redirect', null, ['uri' => $uri]);
        $this->succeed('Redirect URI added.');

        $this->redirect('/developer/projects/' . (int) $project['id']);
    }

    public function removeRedirect(array $params): void
    {
        $this->csrf();
        $account = $this->developer();
        $project = $this->projectOrFail($params, (int) $account['id']);
        $id = (int) $this->input('redirect_id', 0);

        Project::removeRedirect($id, (int) $project['id']);
        Audit::log('developer.redirect_removed', (int) $account['id'], (int) $project['id'], 'redirect', $id);
        $this->succeed('Redirect URI removed.');

        $this->redirect('/developer/projects/' . (int) $project['id']);
    }

    public function createCredential(array $params): void
    {
        $this->csrf();
        $account = $this->developer();
        $project = $this->projectOrFail($params, (int) $account['id']);

        $label = trim((string) $this->input('label', 'New key'));
        $isPublic = (string) $this->input('is_public', '') === '1';
        if ($label === '' || mb_strlen($label) > 60) {
            $this->back(['label' => 'Use a label between 1 and 60 characters.']);
        }

        $active = 0;
        foreach (Project::credentials((int) $project['id']) as $credential) {
            if ($credential['revoked_at'] === null) {
                $active++;
            }
        }
        if ($active >= 10) {
            $this->back(['label' => 'Revoke an old key before creating another.']);
        }

        $secret = Project::createCredential((int) $project['id'], $label, $isPublic);
        Session::set('_new_secret', $secret);
        Audit::log('developer.credential_created', (int) $account['id'], (int) $project['id'], 'credential', (int) $secret['id'], [
            'public' => $isPublic,
        ]);
        $this->succeed($isPublic
            ? 'Public client created. It has no secret; use PKCE.'
            : 'New client secret created. Save it below - it cannot be recovered later.');

        $this->redirect('/developer/projects/' . (int) $project['id']);
    }

    public function revokeCredential(array $params): void
    {
        $this->csrf();
        $account = $this->developer();
        $project = $this->projectOrFail($params, (int) $account['id']);
        $id = (int) $this->input('credential_id', 0);

        Project::revokeCredential($id, (int) $project['id']);
        Audit::log('developer.credential_revoked', (int) $account['id'], (int) $project['id'], 'credential', $id);
        $this->succeed('Client secret revoked.');

        $this->redirect('/developer/projects/' . (int) $project['id']);
    }

    public function toggleStatus(array $params): void
    {
        $this->csrf();
        $account = $this->developer();
        $project = $this->projectOrFail($params, (int) $account['id']);
        $disable = (string) $project['status'] === 'active';

        $data = ['status' => $disable ? 'disabled' : 'active'];
        if ($disable) {
            $message = trim((string) $this->input('downtime_message'));
            if (mb_strlen($message) > 280) {
                $this->back(['downtime_message' => 'Keep the downtime message under 280 characters.']);
            }
            $data['downtime_message'] = $message !== '' ? $message : null;
        }

        Project::update((int) $project['id'], (int) $account['id'], $data);
        Audit::log('developer.project_' . ($disable ? 'disabled' : 'enabled'), (int) $account['id'], (int) $project['id']);
        $this->succeed($disable
            ? 'Project disabled. Sign-in is paused and its tokens no longer work.'
            : 'Project enabled. Sign-in works again.');

        $this->redirect('/developer/projects/' . (int) $project['id']);
    }

    public function uploadIcon(array $params): void
    {
        $this->csrf();
        $account = $this->developer();
        $project = $this->projectOrFail($params, (int) $account['id']);

        $file = $_FILES['icon'] ?? null;
        if (!is_array($file)) {
            $this->back(['icon' => 'Choose an image to upload.']);
        }

        $required = (int) Config::get('account.project_icon_days', 30);
        $approved = (int) ($project['icon_approved'] ?? 0) === 1;
        if (account_age_days($account) < $required && !$approved) {
            $this->back(['icon' => 'Custom icons unlock ' . $required . ' days after registration. An administrator can approve one sooner.']);
        }

        [$name, $error] = save_image_upload($file, 'projects');
        if ($error !== null) {
            $this->back(['icon' => $error]);
        }

        if (($project['icon_file'] ?? null) !== null) {
            delete_upload('projects', (string) $project['icon_file']);
        }
        Project::update((int) $project['id'], (int) $account['id'], [
            'icon_file' => $name,
            'icon_approved' => ($approved || account_age_days($account) >= $required) ? 1 : 0,
        ]);
        Audit::log('developer.project_icon_uploaded', (int) $account['id'], (int) $project['id'], 'project', (int) $project['id'], [
            'file' => $name,
        ]);
        $this->succeed('Project icon updated.');

        $this->redirect('/developer/projects/' . (int) $project['id']);
    }

    public function destroy(array $params): void
    {
        $this->csrf();
        $account = $this->developer();
        $project = $this->projectOrFail($params, (int) $account['id']);

        $password = (string) $this->input('password');
        $confirmation = trim((string) $this->input('confirmation'));

        $errors = [];
        if (!password_verify($password, (string) $account['password_hash'])) {
            $errors['password'] = 'Password is incorrect.';
        }
        if (!hash_equals((string) $project['name'], $confirmation)) {
            $errors['confirmation'] = 'Type the project name exactly.';
        }
        if ($errors !== []) {
            $this->back($errors, [], '/developer/projects/' . (int) $project['id']);
        }

        Project::delete((int) $project['id'], (int) $account['id']);
        Audit::log('developer.project_deleted', (int) $account['id'], null, 'project', (int) $project['id'], [
            'name' => $project['name'],
        ]);
        $this->succeed('Project deleted.');

        $this->redirect('/developer');
    }

    private function projectOrFail(array $params, int $ownerId): array
    {
        $id = (int) ($params['id'] ?? 0);
        $project = Project::findForOwner($id, $ownerId);
        if ($project === null) {
            App::notFound();
        }

        return $project;
    }

    private function normalizeDomain(string $domain): ?string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return null;
        }

        if (str_contains($domain, '://')) {
            $parts = parse_url($domain);
            if ($parts === false) {
                return null;
            }
            $domain = (string) ($parts['host'] ?? '');
            if (isset($parts['port'])) {
                $domain .= ':' . (int) $parts['port'];
            }
        }

        $domain = mb_strtolower(rtrim($domain, '/'));
        $host = $domain;
        $port = null;
        if (preg_match('/^([^:]+)(?::(\d{1,5}))?$/', $domain, $matches) === 1) {
            $host = $matches[1];
            $port = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : null;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        $name = preg_match('/^(?=.{1,190}$)([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $host) === 1
            || $host === 'localhost';
        if (!$ip && !$name) {
            return null;
        }
        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        return $host . ($port !== null ? ':' . $port : '');
    }

    private function normalizeAccent(string $accent): string
    {
        $accent = strtolower(trim($accent));

        return preg_match('/^#[0-9a-f]{6}$/', $accent) === 1 ? $accent : '#5b6cff';
    }
}
