<?php
// Used by the Verify stage: 200 only if this release can reach the DB and read the
// shop tables. Prints the release that served the request (T6).
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
$out = 'release=' . release_id() . ' host=' . (gethostname() ?: '');
try {
    $n = (int)db()->query('SELECT COUNT(*) FROM shop_product')->fetchColumn();
    echo "$out db=ok products=$n\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "$out db=error\n";
}
