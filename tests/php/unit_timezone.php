<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/core/bootstrap.php';

// PHP e MySQL precisam concordar na hora local da empresa (padrão: America/Campo_Grande)
$tz = date_default_timezone_get();
if ($tz !== (Config::app()['timezone'] ?? 'America/Campo_Grande')) {
    fwrite(STDERR, "Falha: fuso do PHP ({$tz}) diferente do configurado.\n");
    exit(1);
}
if (@fsockopen('127.0.0.1', 3306, $errno, $errstr, 1) !== false) {
    $dbNow = strtotime((string)Database::conn()->query('SELECT NOW()')->fetchColumn());
    if (abs($dbNow - time()) > 5) {
        fwrite(STDERR, "Falha: NOW() do MySQL difere da hora do PHP em " . ($dbNow - time()) . "s.\n");
        exit(1);
    }
}
echo "OK unit_timezone\n";
