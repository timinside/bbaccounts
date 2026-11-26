<?php
declare(strict_types=1);

// Minimal bbaccounts example: Authorization Code + PKCE for a public client.
// 1. Register a project and add http://localhost:8100/callback as a redirect URI.
// 2. Set BBA_CLIENT_ID (environment variable or below).
// 3. Run: php -S localhost:8100 examples/php-web/index.php
// Override BBA_BASE, BBA_CLIENT_ID, and BBA_REDIRECT with environment variables.
//
// ProFreeHost/unaux bypass: if you get an anti-bot __test challenge (HTML with
// slowAES.decrypt) instead of JSON, solve it and retry with Cookie: __test=<value>.
// Helper: python examples/python/bb-unaux.py URL [GET|POST] [BODY]
// See examples/README.md.

session_start();

define('BBA_BASE', rtrim(getenv('BBA_BASE') ?: 'https://bbaccounts.unaux.com', '/'));
define('BBA_CLIENT_ID', getenv('BBA_CLIENT_ID') ?: 'YOUR_CLIENT_ID');
define('BBA_REDIRECT', getenv('BBA_REDIRECT') ?: 'http://localhost:8100/callback');

function base64url(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function http(string $method, string $url, array $data = [], array $headers = []): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
    ]);
    if ($method === 'POST') {
        curl_setopt($handle, CURLOPT_POST, true);
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($handle, CURLOPT_HTTPHEADER, array_merge(['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'], $headers));
    }
    $body = (string) curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    return ['status' => $status, 'json' => json_decode($body, true) ?: []];
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/callback') {
    if (($_GET['state'] ?? '') !== ($_SESSION['state'] ?? null)) {
        http_response_code(400);
        exit('State mismatch. Start again.');
    }
    $result = http('POST', BBA_BASE . '/oauth/token', [
        'grant_type' => 'authorization_code',
        'client_id' => BBA_CLIENT_ID,
        'redirect_uri' => BBA_REDIRECT,
        'code' => (string) ($_GET['code'] ?? ''),
        'code_verifier' => (string) ($_SESSION['verifier'] ?? ''),
    ]);
    if ($result['status'] !== 200 || empty($result['json']['access_token'])) {
        http_response_code(400);
        echo '<h1>Token error</h1><pre>' . htmlspecialchars(json_encode($result['json'], JSON_PRETTY_PRINT)) . '</pre>';
        exit;
    }

    $me = http('GET', BBA_BASE . '/oauth/userinfo', [], ['Authorization: Bearer ' . $result['json']['access_token']]);
    $_SESSION = [];
    echo '<h1>Signed in</h1><pre>' . htmlspecialchars(json_encode($me['json'], JSON_PRETTY_PRINT)) . '</pre><p><a href="/">Start over</a></p>';
    exit;
}

if ($path === '/logout' || isset($_GET['logout'])) {
    $_SESSION = [];
    header('Location: /');
    exit;
}

$verifier = base64url(random_bytes(48));
$challenge = base64url(hash('sha256', $verifier, true));
$state = base64url(random_bytes(24));
$_SESSION['verifier'] = $verifier;
$_SESSION['state'] = $state;

$authorize = BBA_BASE . '/oauth/authorize?' . http_build_query([
    'response_type' => 'code',
    'client_id' => BBA_CLIENT_ID,
    'redirect_uri' => BBA_REDIRECT,
    'scope' => 'profile email',
    'state' => $state,
    'code_challenge' => $challenge,
    'code_challenge_method' => 'S256',
], '', '&', PHP_QUERY_RFC3986);
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>bbaccounts PHP example</title></head>
<body style="font:16px system-ui;max-width:680px;margin:60px auto;padding:0 20px">
    <h1>bbaccounts PHP example</h1>
    <p>This page uses OAuth 2.0 with PKCE and no client secret.</p>
    <p><a href="<?= htmlspecialchars($authorize) ?>">Sign in with bbaccounts</a></p>
</body>
</html>
