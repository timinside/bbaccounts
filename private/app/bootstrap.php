<?php
declare(strict_types=1);

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
$system = BBA_PRIVATE . '/storage/system.json';
if (is_file($system)) {
    $override = json_decode((string) file_get_contents($system), true);
    if (is_array($override)) {
        foreach (['version'] as $key) {
            if (array_key_exists($key, $override) && is_string($override[$key]) && $override[$key] !== '') {
                $config[$key] = $override[$key];
            }
        }
    }
}

BBA\Core\Config::init($config);
BBA\Core\I18n::init();

date_default_timezone_set((string) BBA\Core\Config::get('timezone', 'UTC'));
mb_internal_encoding('UTF-8');

$debug = (bool) BBA\Core\Config::get('debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BBA_PRIVATE . '/storage/logs/php-error.log');

set_exception_handler(static function (Throwable $e): void {
    BBA\Core\App::handleException($e);
});

BBA\Core\Session::start();
BBA\Core\App::run();
