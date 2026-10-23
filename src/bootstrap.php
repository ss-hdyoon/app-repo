<?php
// Shared setup for every page: DB connection, DB-backed sessions, cart, CSRF,
// formatting helpers and the page layout.
declare(strict_types=1);

const APP_INI = '/etc/deploy/app.ini';
const FREE_SHIPPING_FROM = 50000;
const SHIPPING_FEE = 3000;

const ORDER_STATUS = [
    'paid'      => '결제완료',
    'preparing' => '배송준비중',
    'shipping'  => '배송중',
    'delivered' => '배송완료',
    'cancelled' => '주문취소',
];

// releases/<commit>/src -> <commit>. __DIR__ has symlinks resolved, so this is the
// release actually serving the request, not whatever current points to now.
function release_id(): string
{
    return basename(dirname(__DIR__));
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $c = parse_ini_file(APP_INI, false, INI_SCANNER_RAW);
    if ($c === false) {
        throw new RuntimeException('DB 설정 파일을 읽을 수 없습니다.');
    }
    $pdo = new PDO(
        "mysql:host={$c['host']};dbname={$c['database']};charset=utf8mb4",
        $c['user'],
        $c['password'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $pdo;
}

final class DbSessionHandler implements SessionHandlerInterface
{
    public function __construct(private PDO $pdo) {}

    public function open(string $path, string $name): bool { return true; }

    public function close(): bool { return true; }

    public function read(string $id): string|false
    {
        $st = $this->pdo->prepare('SELECT data FROM shop_session WHERE id = ?');
        $st->execute([$id]);
        $data = $st->fetchColumn();
        return $data === false ? '' : (string)$data;
    }

    public function write(string $id, string $data): bool
    {
        $st = $this->pdo->prepare('REPLACE INTO shop_session (id, data, updated_at) VALUES (?, ?, ?)');
        return $st->execute([$id, $data, time()]);
    }

    public function destroy(string $id): bool
    {
        return $this->pdo->prepare('DELETE FROM shop_session WHERE id = ?')->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $st = $this->pdo->prepare('DELETE FROM shop_session WHERE updated_at < ?');
        $st->execute([time() - $max_lifetime]);
        return $st->rowCount();
    }
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_save_handler(new DbSessionHandler(db()), true);
    session_name('ASICSMOCK');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function won(int $amount): string
{
    return number_format($amount) . '원';
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $m = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $m;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('잘못된 요청입니다. 페이지를 새로고침한 뒤 다시 시도해 주세요.');
    }
}

function effective_price(array $p): int
{
    return (int)($p['sale_price'] ?? $p['price']);
}

/** @return array<int,int> product_id => qty */
function cart(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_count(): int
{
    return array_sum(cart());
}

/** Cart lines joined with current product data. */
function cart_lines(): array
{
    $cart = cart();
    if (!$cart) {
        return [];
    }
    $ids = array_keys($cart);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare("SELECT * FROM shop_product WHERE id IN ($in) AND is_active = 1");
    $st->execute($ids);
    $lines = [];
    foreach ($st->fetchAll() as $p) {
        $qty = $cart[(int)$p['id']];
        $lines[] = ['product' => $p, 'qty' => $qty, 'amount' => effective_price($p) * $qty];
    }
    return $lines;
}

function shipping_fee(int $items_amount): int
{
    return ($items_amount === 0 || $items_amount >= FREE_SHIPPING_FROM) ? 0 : SHIPPING_FEE;
}

function product_thumb(array $p, string $size = ''): string
{
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', $p['color']) ? $p['color'] : '#001e62';
    return '<div class="thumb ' . h($size) . '" style="--c:' . $color . '">'
        . '<svg viewBox="0 0 120 60" aria-hidden="true"><path d="M8 44c10-2 22-14 30-24 4 6 14 10 26 10 16 0 30 6 46 10 3 1 4 4 3 7l-1 3H10c-3 0-5-3-2-6z"/></svg>'
        . '<span>' . h($p['name']) . '</span></div>';
}

function render_header(string $title): void
{
    $count = cart_count();
    $msg = flash();
    ?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> | ASICS MOCK STORE</title>
  <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
  <div class="topbar">샌드박스 모의 쇼핑몰입니다. 실제 결제와 배송은 일어나지 않습니다. · 5만원 이상 무료배송</div>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="logo" href="/">ASICS <span>MOCK STORE</span></a>
      <nav class="gnb">
        <a href="/?category=러닝">러닝</a>
        <a href="/?category=레이싱">레이싱</a>
        <a href="/?category=트레일">트레일</a>
        <a href="/?category=테니스">테니스</a>
        <a href="/?category=라이프스타일">라이프스타일</a>
      </nav>
      <div class="util">
        <a href="/orders.php">주문조회</a>
        <a class="cart-link" href="/cart.php">장바구니<?php if ($count): ?><b><?= $count ?></b><?php endif; ?></a>
      </div>
    </div>
  </header>
  <main class="wrap">
    <?php if ($msg): ?><div class="flash"><?= h($msg) ?></div><?php endif; ?>
<?php
}

function render_footer(): void
{
    ?>
  </main>
  <footer class="site-footer">
    <div class="wrap">
      <p>ASICS MOCK STORE · CI/CD DB 마이그레이션 샌드박스 테스트용</p>
      <p class="meta">release=<?= h(release_id()) ?> · host=<?= h(gethostname() ?: '') ?></p>
    </div>
  </footer>
</body>
</html>
<?php
}
