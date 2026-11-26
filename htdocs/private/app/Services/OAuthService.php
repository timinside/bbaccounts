<?php
declare(strict_types=1);

namespace BBA\Services;

use BBA\Core\Audit;
use BBA\Core\Crypto;
use BBA\Core\Db;
use BBA\Core\Request;
use BBA\Models\Project;
use BBA\Models\SocialIdentity;
use BBA\Models\User;
use BBA\Models\Verification;

final class OAuthService
{
    public const SCOPES = ['profile', 'email', 'guard', 'points', 'friends', 'badges', 'teams', 'cells'];
    public const DEFAULT_SCOPES = ['profile', 'email', 'points', 'friends', 'badges', 'teams', 'cells'];
    public const POINTS_MAX = 10000;
    public const CODE_TTL = 600;
    public const TOKEN_TTL = 3600;
    public const REFRESH_TTL = 2592000;

    public static function normalizeScope(?string $scope): array
    {
        if ($scope === null || trim($scope) === '') {
            return self::DEFAULT_SCOPES;
        }

        $requested = preg_split('/\s+/', strtolower(trim($scope))) ?: [];
        $requested = array_values(array_unique(array_filter($requested, static fn (string $item): bool => $item !== '')));

        foreach ($requested as $item) {
            if (!in_array($item, self::SCOPES, true)) {
                throw new OAuthException('invalid_scope', 'Requested scope is not supported.');
            }
        }

        return $requested === [] ? self::DEFAULT_SCOPES : $requested;
    }

    public static function authorizeParams(array $input): array
    {
        $clientId = trim((string) ($input['client_id'] ?? ''));
        $redirectUri = trim((string) ($input['redirect_uri'] ?? ''));
        $responseType = trim((string) ($input['response_type'] ?? ''));
        $state = isset($input['state']) ? (string) $input['state'] : null;
        $challenge = isset($input['code_challenge']) ? trim((string) $input['code_challenge']) : null;
        $method = isset($input['code_challenge_method']) ? strtoupper(trim((string) $input['code_challenge_method'])) : null;

        if ($clientId === '') {
            throw new OAuthException('invalid_request', 'client_id is required.', 400);
        }
        if ($redirectUri === '') {
            throw new OAuthException('invalid_request', 'redirect_uri is required.', 400);
        }

        $project = Project::findByClientId($clientId);
        if ($project === null) {
            throw new OAuthException('invalid_client', 'Unknown client, or every credential for it has been revoked.', 400);
        }

        self::assertUsable($project);

        if (!Project::redirectExists((int) $project['id'], $redirectUri)) {
            throw new OAuthException('invalid_request', 'redirect_uri is not registered for this client.', 400);
        }

        if ($responseType !== 'code') {
            throw new OAuthException('unsupported_response_type', 'Only response_type=code is supported.');
        }

        $scope = self::normalizeScope(isset($input['scope']) ? (string) $input['scope'] : null);

        if ($challenge !== null && $challenge !== '') {
            if ($method === null || $method === '') {
                $method = 'S256';
            }
            if (!in_array($method, ['S256', 'plain'], true)) {
                throw new OAuthException('invalid_request', 'Unsupported code_challenge_method.');
            }
            if ($method === 'plain' && strlen($challenge) < 43) {
                throw new OAuthException('invalid_request', 'A plain code_challenge must be at least 43 characters.');
            }
            if ($method === 'S256' && !preg_match('/^[A-Za-z0-9_-]{43,128}$/', $challenge)) {
                throw new OAuthException('invalid_request', 'Invalid code_challenge.');
            }
        } else {
            $challenge = null;
            $method = null;
        }

        if ((int) ($project['is_public'] ?? 0) === 1 && $challenge === null) {
            throw new OAuthException('invalid_request', 'Public clients must use PKCE with code_challenge.');
        }

        return [
            'project' => $project,
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(' ', $scope),
            'scopes' => $scope,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => $method,
        ];
    }

    private static function assertUsable(array $project): void
    {
        if ((string) ($project['project_status'] ?? $project['status'] ?? 'active') !== 'active') {
            throw new OAuthException('invalid_client', 'This project is not active. Its owner or an administrator disabled it.', 400);
        }

        if (!Project::ownerAvailable($project)) {
            $reason = trim((string) ($project['owner_status_reason'] ?? ''));
            $message = "Creator's account of this OAuth was banned or frozen, so this app cannot sign anyone in.";
            if ($reason !== '') {
                $message .= ' Reason: ' . $reason;
            }

            throw new OAuthException('invalid_client', $message, 400);
        }
    }

    public static function consentFor(int $accountId, int $projectId): ?array
    {
        return Db::one('SELECT * FROM oauth_consents WHERE account_id = ? AND project_id = ? LIMIT 1', [
            $accountId,
            $projectId,
        ]);
    }

    public static function consentCovers(?array $consent, array $scopes): bool
    {
        if ($consent === null) {
            return false;
        }

        $granted = preg_split('/\s+/', (string) $consent['scope']) ?: [];

        return array_diff($scopes, $granted) === [];
    }

    public static function saveConsent(int $accountId, int $projectId, string $scope): void
    {
        Db::run(
            'INSERT INTO oauth_consents (account_id, project_id, scope) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE scope = VALUES(scope), updated_at = NOW()',
            [$accountId, $projectId, $scope]
        );
    }

    public static function issueCode(array $params, int $accountId): string
    {
        $code = Crypto::token(32);
        $project = $params['project'];

        Db::insert('oauth_codes', [
            'code_hash' => Crypto::hash($code),
            'project_id' => (int) $project['id'],
            'account_id' => $accountId,
            'redirect_uri' => $params['redirect_uri'],
            'scope' => $params['scope'],
            'code_challenge' => $params['code_challenge'],
            'code_challenge_method' => $params['code_challenge_method'],
            'expires_at' => date('Y-m-d H:i:s', time() + self::CODE_TTL),
        ]);

        Audit::log('oauth.code_issued', $accountId, (int) $project['id']);

        return $code;
    }

    public static function redirectWithCode(string $redirectUri, string $code, ?string $state): never
    {
        $query = ['code' => $code];
        if ($state !== null && $state !== '') {
            $query['state'] = $state;
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';
        \BBA\Core\Response::redirect($redirectUri . $separator . http_build_query($query));
    }

    public static function redirectWithError(string $redirectUri, string $error, string $description, ?string $state): never
    {
        $query = ['error' => $error, 'error_description' => $description];
        if ($state !== null && $state !== '') {
            $query['state'] = $state;
        }

        $separator = str_contains($redirectUri, '?') ? '&' : '?';
        \BBA\Core\Response::redirect($redirectUri . $separator . http_build_query($query));
    }

    public static function clientCredentials(): array
    {
        $clientId = trim((string) Request::input('client_id', ''));
        $clientSecret = Request::input('client_secret');

        $header = Request::header('Authorization');
        if ($header !== null && preg_match('/^Basic\s+(.+)$/i', trim($header), $matches)) {
            $decoded = base64_decode(trim($matches[1]), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$basicId, $basicSecret] = explode(':', $decoded, 2);
                if ($clientId === '') {
                    $clientId = $basicId;
                }
                if ($clientSecret === null || $clientSecret === '') {
                    $clientSecret = $basicSecret;
                }
            }
        }

        return [
            'client_id' => $clientId,
            'client_secret' => is_string($clientSecret) ? $clientSecret : null,
        ];
    }

    public static function authenticateClient(string $clientId, ?string $clientSecret, bool $allowPublic = false): array
    {
        if ($clientId === '') {
            throw new OAuthException('invalid_client', 'Client authentication is required.', 401);
        }

        $project = Project::findByClientId($clientId);
        if ($project === null) {
            throw new OAuthException('invalid_client', 'Unknown client, or every credential for it has been revoked.', 401);
        }

        self::assertUsable($project);

        if ($clientSecret === null || $clientSecret === '') {
            if ($allowPublic && (int) ($project['is_public'] ?? 0) === 1) {
                return $project;
            }

            throw new OAuthException('invalid_client', 'Client secret is required.', 401);
        }

        if (!Crypto::equals((string) $project['secret_hash'], Crypto::hash($clientSecret))) {
            throw new OAuthException('invalid_client', 'Invalid client credentials.', 401);
        }

        return $project;
    }

    public static function exchangeCode(array $input): array
    {
        $code = trim((string) ($input['code'] ?? ''));
        $redirectUri = trim((string) ($input['redirect_uri'] ?? ''));
        $verifier = isset($input['code_verifier']) ? trim((string) $input['code_verifier']) : null;
        $credentials = self::clientCredentials();

        if ($code === '') {
            throw new OAuthException('invalid_request', 'code is required.');
        }
        if ($redirectUri === '') {
            throw new OAuthException('invalid_request', 'redirect_uri is required.');
        }

        $row = Db::one(
            'SELECT c.*, p.status AS project_status, p.owner_id
             FROM oauth_codes c
             INNER JOIN projects p ON p.id = c.project_id
             WHERE c.code_hash = ? AND c.used_at IS NULL AND c.expires_at > NOW()
             LIMIT 1',
            [Crypto::hash($code)]
        );

        if ($row === null) {
            throw new OAuthException('invalid_grant', 'The authorization code is invalid or expired.');
        }

        $project = Project::findByClientId($credentials['client_id']);
        if ($project === null || (int) $project['id'] !== (int) $row['project_id']) {
            throw new OAuthException('invalid_grant', 'The authorization code does not belong to this client.');
        }

        self::assertUsable($project);

        if ($credentials['client_secret'] !== null && $credentials['client_secret'] !== '') {
            if (!Crypto::equals((string) $project['secret_hash'], Crypto::hash($credentials['client_secret']))) {
                throw new OAuthException('invalid_client', 'Invalid client credentials.', 401);
            }
        } elseif ((int) ($project['is_public'] ?? 0) !== 1 || empty($row['code_challenge'])) {
            throw new OAuthException('invalid_client', 'Client authentication is required.', 401);
        }

        if ((int) ($project['is_public'] ?? 0) === 1 && empty($row['code_challenge'])) {
            throw new OAuthException('invalid_grant', 'Public clients must use PKCE.');
        }

        if (!hash_equals((string) $row['redirect_uri'], $redirectUri)) {
            throw new OAuthException('invalid_grant', 'redirect_uri does not match the authorization request.');
        }

        if (!empty($row['code_challenge'])) {
            if ($verifier === null || $verifier === '') {
                throw new OAuthException('invalid_grant', 'code_verifier is required.');
            }
            $expected = $row['code_challenge_method'] === 'plain'
                ? $verifier
                : Crypto::codeChallenge($verifier);
            if (!hash_equals((string) $row['code_challenge'], $expected)) {
                throw new OAuthException('invalid_grant', 'PKCE verification failed.');
            }
        }

        $used = Db::run('UPDATE oauth_codes SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [(int) $row['id']]);
        if ($used->rowCount() !== 1) {
            throw new OAuthException('invalid_grant', 'The authorization code has already been used.');
        }

        $tokens = self::issueTokens(
            (int) $row['project_id'],
            (int) $row['account_id'],
            (string) $row['scope'],
            (int) $project['credential_id']
        );

        Audit::log('oauth.token_issued', (int) $row['account_id'], (int) $row['project_id']);

        return $tokens;
    }

    public static function refresh(array $input): array
    {
        $refreshToken = trim((string) ($input['refresh_token'] ?? ''));
        if ($refreshToken === '') {
            throw new OAuthException('invalid_request', 'refresh_token is required.');
        }

        $credentials = self::clientCredentials();
        $project = self::authenticateClient($credentials['client_id'], $credentials['client_secret'], true);

        $row = Db::one(
            'SELECT * FROM oauth_tokens WHERE refresh_hash = ? AND revoked_at IS NULL AND refresh_expires_at > NOW() LIMIT 1',
            [Crypto::hash($refreshToken)]
        );

        if ($row === null || (int) $row['project_id'] !== (int) $project['id']) {
            throw new OAuthException('invalid_grant', 'The refresh token is invalid or expired.');
        }

        $access = Crypto::token(32);
        $newRefresh = Crypto::token(32);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL);
        $refreshExpiresAt = date('Y-m-d H:i:s', time() + self::REFRESH_TTL);

        Db::run(
            'UPDATE oauth_tokens
             SET token_hash = ?, refresh_hash = ?, expires_at = ?, refresh_expires_at = ?, last_used_at = NOW()
             WHERE id = ?',
            [Crypto::hash($access), Crypto::hash($newRefresh), $expiresAt, $refreshExpiresAt, (int) $row['id']]
        );

        Project::touchCredential((int) $project['credential_id']);
        Audit::log('oauth.token_refreshed', (int) $row['account_id'], (int) $project['id']);

        return [
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => self::TOKEN_TTL,
            'refresh_token' => $newRefresh,
            'scope' => (string) $row['scope'],
        ];
    }

    private static function issueTokens(int $projectId, int $accountId, string $scope, int $credentialId): array
    {
        $access = Crypto::token(32);
        $refresh = Crypto::token(32);

        Db::insert('oauth_tokens', [
            'token_hash' => Crypto::hash($access),
            'refresh_hash' => Crypto::hash($refresh),
            'project_id' => $projectId,
            'account_id' => $accountId,
            'scope' => $scope,
            'expires_at' => date('Y-m-d H:i:s', time() + self::TOKEN_TTL),
            'refresh_expires_at' => date('Y-m-d H:i:s', time() + self::REFRESH_TTL),
        ]);

        Project::touchCredential($credentialId);

        return [
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => self::TOKEN_TTL,
            'refresh_token' => $refresh,
            'scope' => $scope,
        ];
    }

    /** Token carrying the requested API scope, issued to a verified project. */
    public static function apiToken(string $bearer, string $scope): array
    {
        if ($bearer === '') {
            throw new OAuthException('invalid_token', 'A bearer token is required.', 401);
        }

        $row = Db::one(
            'SELECT t.id, t.project_id, t.account_id, t.scope, a.points, a.status, a.status_until
             FROM oauth_tokens t
             INNER JOIN accounts a ON a.id = t.account_id
             WHERE t.token_hash = ? AND t.revoked_at IS NULL AND t.expires_at > NOW()
             LIMIT 1',
            [Crypto::hash($bearer)]
        );

        if ($row === null || !User::isAvailable($row)) {
            throw new OAuthException('invalid_token', 'The access token is invalid or expired.', 401);
        }

        $scopes = preg_split('/\s+/', (string) $row['scope']) ?: [];
        if (!in_array($scope, $scopes, true)) {
            throw new OAuthException('insufficient_scope', 'This token does not grant the ' . $scope . ' scope.', 403);
        }
        if (Verification::for('project', (int) $row['project_id']) === null) {
            throw new OAuthException('forbidden', 'This project is not verified.', 403);
        }

        Db::run('UPDATE oauth_tokens SET last_used_at = NOW() WHERE id = ?', [(int) $row['id']]);

        return $row;
    }

    public static function userinfo(string $bearer): array
    {
        if ($bearer === '') {
            throw new OAuthException('invalid_token', 'A bearer token is required.', 401);
        }

        $row = Db::one(
            'SELECT t.*, a.email, a.display_name, a.username, a.description, a.avatar_type, a.avatar_value, a.status, a.status_until,
                    a.share_social, a.updated_at AS account_updated_at
             FROM oauth_tokens t
             INNER JOIN accounts a ON a.id = t.account_id
             WHERE t.token_hash = ? AND t.revoked_at IS NULL AND t.expires_at > NOW()
             LIMIT 1',
            [Crypto::hash($bearer)]
        );

        if ($row === null || !User::isAvailable($row)) {
            throw new OAuthException('invalid_token', 'The access token is invalid or expired.', 401);
        }

        Db::run('UPDATE oauth_tokens SET last_used_at = NOW() WHERE id = ?', [(int) $row['id']]);

        $scopes = preg_split('/\s+/', (string) $row['scope']) ?: [];
        $data = ['sub' => (string) $row['account_id']];

        if (in_array('profile', $scopes, true)) {
            $data['display_name'] = (string) $row['display_name'];
            $data['username'] = (string) ($row['username'] ?? '');
            $data['description'] = (string) ($row['description'] ?? '');
            $data['avatar_url'] = avatar_url([
                'id' => (int) $row['account_id'],
                'avatar_type' => $row['avatar_type'],
                'avatar_value' => $row['avatar_value'],
                'updated_at' => $row['account_updated_at'],
            ]);

            if ((int) ($row['share_social'] ?? 1) === 1) {
                $social = [];
                foreach (SocialIdentity::forAccount((int) $row['account_id']) as $identity) {
                    if ((int) ($identity['is_visible'] ?? 1) !== 1) {
                        continue;
                    }
                    $username = trim((string) ($identity['username'] ?? ''));
                    if ($username !== '') {
                        $social[(string) $identity['provider']] = $username;
                    }
                }
                if ($social !== []) {
                    $data['social'] = $social;
                }
            }
        }

        if (in_array('email', $scopes, true)) {
            $data['email'] = (string) $row['email'];
            $data['email_verified'] = false;
        }

        $data['updated_at'] = date('c', strtotime((string) $row['account_updated_at']));

        return $data;
    }

    public static function revoke(array $input): void
    {
        $token = trim((string) ($input['token'] ?? ''));
        if ($token === '') {
            throw new OAuthException('invalid_request', 'token is required.');
        }

        $credentials = self::clientCredentials();
        $project = self::authenticateClient($credentials['client_id'], $credentials['client_secret'], true);

        Db::run(
            'UPDATE oauth_tokens SET revoked_at = NOW()
             WHERE (token_hash = ? OR refresh_hash = ?) AND project_id = ? AND revoked_at IS NULL',
            [Crypto::hash($token), Crypto::hash($token), (int) $project['id']]
        );

        Audit::log('oauth.token_revoked');
    }

    public static function revokeAccountProject(int $accountId, int $projectId): void
    {
        Db::run('DELETE FROM oauth_consents WHERE account_id = ? AND project_id = ?', [$accountId, $projectId]);
        Db::run(
            'UPDATE oauth_tokens SET revoked_at = NOW() WHERE account_id = ? AND project_id = ? AND revoked_at IS NULL',
            [$accountId, $projectId]
        );
        Db::run(
            'UPDATE oauth_codes SET used_at = NOW() WHERE account_id = ? AND project_id = ? AND used_at IS NULL',
            [$accountId, $projectId]
        );
    }
}
