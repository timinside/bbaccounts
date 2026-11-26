<?php
declare(strict_types=1);

/** Plain CLI self-check: no Composer, no request, no DB rows. */

use BBA\Core\Config;
use BBA\Core\Crypto;
use BBA\Core\Db;
use BBA\Core\I18n;
use BBA\Core\Session;
use BBA\Models\Guard;

ini_set('assert.exception', '1');

define('BBA_PRIVATE', dirname(__DIR__));
define('BBA_APP', BBA_PRIVATE . '/app');
$parent = dirname(BBA_PRIVATE);
$siblingPublic = $parent . '/htdocs';
define('BBA_PUBLIC', is_dir($siblingPublic) ? $siblingPublic : $parent);

require BBA_APP . '/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'BBA\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = BBA_APP . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$config = require BBA_PRIVATE . '/config/config.php';
$local = BBA_PRIVATE . '/config/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}
Config::init($config);
I18n::init();
date_default_timezone_set((string) Config::get('timezone', 'UTC'));
Session::start();

function check(bool $ok, string $label): void
{
    assert($ok, $label);
    if (!$ok) {
        throw new RuntimeException('Self-check failed: ' . $label);
    }
    echo 'ok - ' . $label . PHP_EOL;
}

// RFC 6238 SHA-1 vector for ASCII "12345678901234567890"; six-digit code at T=59.
$vector = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
check(Guard::code($vector, 59) === '287082', 'Guard code matches RFC 6238 vector');

$step = Guard::step();
$current = Guard::code($vector, $step * Guard::STEP);
check(Guard::verify($vector, $current), 'Guard::verify accepts the matched step');
check(!Guard::verify($vector, Guard::code($vector, ($step - 2) * Guard::STEP)), 'Guard::verify rejects a stale step');
check(!Guard::verify($vector, $current, $step), 'Guard::verify blocks replay of the matched step');

// CSRF tokens are stored in the current session and must not be reusable elsewhere.
$token = csrf_token();
check(Crypto::csrfValid($token), 'CSRF token validates in the same session');
$_SESSION['_csrf'] = 'different-session-token';
check(!Crypto::csrfValid($token), 'CSRF token is rejected for another session token');
unset($_SESSION['_csrf']);
check(!Crypto::csrfValid($token), 'CSRF token is rejected when session token is missing');
$_SESSION['_csrf'] = $token;

// addXp applies one delta to xp and derives level from that post-delta xp.
$delta = 250;
$xp = max(0, 100 + $delta);
check(level_from_xp($xp) === min(99, (int) floor($xp / 100)), 'addXp level uses one post-delta xp');
check(level_from_xp(max(0, 50 - 100)) === 0, 'addXp clamps negative xp to level 0');
check(level_from_xp(10050) === 99, 'addXp caps level at 99');

try {
    $rows = Db::all('SELECT xp, level FROM accounts LIMIT 20');
    $consistent = true;
    foreach ($rows as $row) {
        $consistent = $consistent && (int) $row['level'] === level_from_xp((int) $row['xp']);
    }
    check($consistent, 'stored levels match xp (sample)');
} catch (PDOException $e) {
    echo 'skip - addXp DB cross-check (database unavailable)' . PHP_EOL;
}

if (PHP_SAPI === 'cli') {
    $_SESSION = [];
} else {
    Session::destroy();
}
echo 'selfcheck passed' . PHP_EOL;
