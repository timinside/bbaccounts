<?php
declare(strict_types=1);
// bbaccounts setup/updater. Asks DB info every time; never stores credentials.

session_start();
$root = __DIR__;
$private = is_dir($root . '/private') ? $root . '/private' : dirname($root) . '/private';
$configPath = $private . '/config/config.php';
$examplePath = $private . '/config/config.example.php';
$schemaPath = $private . '/database/schema.sql';
$upgradePath = $private . '/database/production-upgrade.sql';

function h(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function setup_csrf(): string { if (empty($_SESSION['_setup_csrf'])) $_SESSION['_setup_csrf'] = bin2hex(random_bytes(16)); return $_SESSION['_setup_csrf']; }
function setup_check_csrf(): void { $t = (string)($_POST['_csrf'] ?? ''); if ($t === '' || !hash_equals((string)($_SESSION['_setup_csrf'] ?? ''), $t)) { http_response_code(400); exit('Bad CSRF token.'); } }
function split_sql(string $sql): array {
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
    $sql = preg_replace('~/\*.*?\*/~s', '', $sql);
    $out = []; $buf = '';
    foreach (preg_split("/\r\n|\n|\r/", $sql) as $line) {
        $t = trim($line);
        if ($t === '' || str_starts_with($t, '--') || str_starts_with($t, '#')) continue;
        $buf .= $line . "\n";
        if (preg_match('/;\s*$/', $t)) { $stmt = trim($buf); $stmt = preg_replace('/;\s*$/', '', $stmt); if ($stmt !== '') $out[] = $stmt; $buf = ''; }
    }
    $stmt = trim($buf); if ($stmt !== '') $out[] = $stmt;
    return $out;
}

$mode = (string)($_POST['mode'] ?? $_GET['mode'] ?? 'install');
if (!in_array($mode, ['install','update'], true)) $mode = 'install';
$errors = []; $ok = ''; $ran = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    setup_check_csrf();
    $dbHost = trim((string)($_POST['db_host'] ?? 'localhost'));
    $dbPort = (int)($_POST['db_port'] ?? 3306);
    $dbName = trim((string)($_POST['db_name'] ?? ''));
    $dbUser = trim((string)($_POST['db_user'] ?? ''));
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $appUrl = trim((string)($_POST['app_url'] ?? ''));
    $writeConfig = isset($_POST['write_config']);
    $appKey = trim((string)($_POST['app_key'] ?? ''));

    if ($dbName === '' || $dbUser === '' || $dbHost === '') $errors[] = 'Database host, name, and user are required.';
    if ($appUrl !== '' && !preg_match('#^https?://#i', $appUrl)) $errors[] = 'App URL must start with http:// or https://';
    if (is_file($configPath)) {
        $current = @require $configPath;
        $currentKey = is_array($current) ? (string)($current['key'] ?? '') : '';
        if ($currentKey !== '' && !hash_equals($currentKey, $appKey)) {
            $errors[] = 'App key is required for updates on an installed site (copy "key" from private/config/config.php).';
        }
    }

    $pdo = null;
    if ($errors === []) {
        try {
            $dsn = 'mysql:host=' . $dbHost . ';port=' . $dbPort . ';dbname=' . $dbName . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('SET NAMES utf8mb4');
        } catch (Throwable $e) {
            $errors[] = 'DB connection failed: ' . $e->getMessage();
        }
    }

    if ($errors === [] && $pdo) {
        $sqlFile = $mode === 'update' ? $upgradePath : $schemaPath;
        if (!is_file($sqlFile)) {
            $errors[] = 'SQL file not found: ' . basename($sqlFile);
        } else {
            try {
                $sql = (string)file_get_contents($sqlFile);
                foreach (split_sql($sql) as $stmt) {
                    $pdo->exec($stmt);
                    $ran[] = strtok(ltrim($stmt), " \n\t(") . '...';
                }
                $ok = $mode === 'update' ? 'Update SQL executed successfully.' : 'Installation SQL executed successfully.';
            } catch (Throwable $e) {
                $errors[] = 'SQL error: ' . $e->getMessage();
            }
        }
    }

    if ($errors === [] && $writeConfig) {
        if (!is_file($examplePath)) {
            $errors[] = 'config.example.php not found; cannot write config.php.';
        } else {
            $cfg = require $examplePath;
            $cfg['db']['host'] = $dbHost;
            $cfg['db']['port'] = $dbPort;
            $cfg['db']['name'] = $dbName;
            $cfg['db']['user'] = $dbUser;
            $cfg['db']['pass'] = $dbPass;
            if ($appUrl !== '') $cfg['url'] = rtrim($appUrl, '/');
            if (empty($cfg['key'])) $cfg['key'] = bin2hex(random_bytes(32));
            $export = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($cfg, true) . ";\n";
            if (@file_put_contents($configPath, $export) === false) {
                $errors[] = 'Could not write config.php. Check permissions on private/config/.';
            } else {
                $ok .= ' config.php was written.';
            }
        }
    }

    if ($errors === [] && $pdo) {
        // Never keep creds around
        $dbPass = '';
        $_POST['db_pass'] = '';
    }
}

$csrf = setup_csrf();
$installed = is_file($configPath);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>bbaccounts setup</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0b0d12;color:#e7edf5;margin:0;padding:24px}
    .wrap{max-width:760px;margin:0 auto}.card{background:#151923;border:1px solid #273043;border-radius:12px;padding:20px;margin:16px 0}
    label{display:block;margin:10px 0 4px;color:#a9b4c4;font-size:14px}input,select{width:100%;box-sizing:border-box;padding:10px 12px;border-radius:8px;border:1px solid #334055;background:#0f131b;color:#e7edf5}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.btn{background:#4f8cff;color:#fff;border:0;padding:10px 14px;border-radius:8px;cursor:pointer;font-weight:600}
    .err{background:#3a1616;border:1px solid #6b2222;padding:12px;border-radius:8px;margin:12px 0}.ok{background:#163a1f;border:1px solid #226b35;padding:12px;border-radius:8px;margin:12px 0}
    .muted{color:#a9b4c4;font-size:14px} code{background:#0f131b;padding:2px 5px;border-radius:4px}
  </style>
</head>
<body><div class="wrap">
  <h1>bbaccounts setup</h1>
  <p class="muted">This script asks for database credentials every time and does not store them. For the app to run, it can write <code>private/config/config.php</code>.</p>
  <?php foreach ($errors as $e): ?><div class="err"><?= h($e) ?></div><?php endforeach; ?>
  <?php if ($ok !== ''): ?><div class="ok"><?= h($ok) ?><?php if ($ran): ?><details><summary>Statements (<?= count($ran) ?>)</summary><pre style="white-space:pre-wrap"><?= h(implode("\n", array_slice($ran,0,50))) ?></pre></details><?php endif; ?></div><?php endif; ?>
  <div class="card">
    <form method="post">
      <input type="hidden" name="_csrf" value="<?= h($csrf) ?>">
      <label>Mode</label>
      <select name="mode">
        <option value="install" <?= $mode==='install'?'selected':'' ?>>Install (schema.sql)</option>
        <option value="update" <?= $mode==='update'?'selected':'' ?>>Update (production-upgrade.sql)</option>
      </select>
      <div class="row">
        <div><label>DB host</label><input name="db_host" value="<?= h((string)($_POST['db_host'] ?? 'localhost')) ?>" required></div>
        <div><label>DB port</label><input name="db_port" type="number" value="<?= h((string)($_POST['db_port'] ?? '3306')) ?>" required></div>
      </div>
      <div class="row">
        <div><label>DB name</label><input name="db_name" value="<?= h((string)($_POST['db_name'] ?? '')) ?>" required></div>
        <div><label>DB user</label><input name="db_user" value="<?= h((string)($_POST['db_user'] ?? '')) ?>" required></div>
      </div>
      <label>DB password</label>
      <input name="db_pass" type="password" value="" autocomplete="off">
      <label>App URL (optional, for config.php)</label>
      <input name="app_url" value="<?= h((string)($_POST['app_url'] ?? 'https://')) ?>" placeholder="https://example.com">
      <?php if ($installed): ?>
      <label>App key (required for installed sites)</label>
      <input name="app_key" type="password" value="" autocomplete="off" placeholder="Copy &quot;key&quot; from private/config/config.php">
      <?php endif; ?>
      <label style="margin-top:14px"><input type="checkbox" name="write_config" value="1" <?= isset($_POST['write_config'])||(!$installed)?'checked':'' ?> style="width:auto"> Write/update <code>private/config/config.php</code> with these DB settings (password will be stored in config.php so the app can connect; the setup script does not store it elsewhere).</label>
      <p class="muted">Install runs <code>schema.sql</code>. Update runs <code>production-upgrade.sql</code> (idempotent). Never run schema.sql on an existing production DB.</p>
      <button class="btn" type="submit">Run</button>
    </form>
  </div>
  <p class="muted">After install/update, delete or protect <code>setup.php</code> if your hosting exposes it publicly.</p>
</div></body></html>
