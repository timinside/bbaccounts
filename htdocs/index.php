<?php
declare(strict_types=1);

$private = is_dir(__DIR__ . '/private') ? __DIR__ . '/private' : dirname(__DIR__) . '/private';
if (!is_file($private . '/config/config.php')) {
    header('Location: /setup.php', true, 302);
    exit;
}

$bootstrap = __DIR__ . '/private/app/bootstrap.php';
if (!is_file($bootstrap)) {
    $bootstrap = dirname(__DIR__) . '/private/app/bootstrap.php';
}
require $bootstrap;
