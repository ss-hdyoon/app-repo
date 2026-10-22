<?php
// Reads delivery_memo, which 20261022_02 adds. Prints the release ID and host so
// Verify (and T6) can tell which code each server is serving.
$release = basename(realpath(__DIR__ . '/..'));
$host = gethostname();
header('Content-Type: text/plain; charset=utf-8');

$cfg = parse_ini_file('/etc/deploy/app.ini', false, INI_SCANNER_RAW);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = new mysqli($cfg['host'], $cfg['user'], $cfg['password'], $cfg['database']);
    $rows = $db->query('SELECT id, item_name, delivery_memo FROM test_order ORDER BY id LIMIT 10');
    echo "release=$release host=$host\n";
    foreach ($rows as $r) {
        echo "{$r['id']}\t{$r['item_name']}\t{$r['delivery_memo']}\n";
    }
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    echo "release=$release host=$host\nDB error: " . $e->getMessage() . "\n";
}
