<?php
// Admin: first visit creates the admin account (no password is shipped in the
// repo), then login, order list with status change, and product stock.
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
start_session();

$pdo = db();
$hasAdmin = (int)$pdo->query('SELECT COUNT(*) FROM shop_admin')->fetchColumn() > 0;
$admin = $_SESSION['admin'] ?? null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $user = trim((string)($_POST['username'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if ($action === 'setup' && !$hasAdmin) {
        if (!preg_match('/^[a-z0-9_]{3,30}$/', $user) || strlen($pass) < 8) {
            $error = '아이디는 영문 소문자·숫자 3~30자, 비밀번호는 8자 이상으로 입력해 주세요.';
        } else {
            $pdo->prepare('INSERT INTO shop_admin (username, password_hash) VALUES (?, ?)')
                ->execute([$user, password_hash($pass, PASSWORD_DEFAULT)]);
            session_regenerate_id(true);
            $_SESSION['admin'] = $user;
            redirect('/admin/');
        }
    }
    if ($action === 'login') {
        $st = $pdo->prepare('SELECT password_hash FROM shop_admin WHERE username = ?');
        $st->execute([$user]);
        $hash = $st->fetchColumn();
        if ($hash !== false && password_verify($pass, (string)$hash)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = $user;
            redirect('/admin/');
        }
        $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
    }
    if ($action === 'logout') {
        unset($_SESSION['admin']);
        redirect('/admin/');
    }
    if ($action === 'status' && $admin) {
        $status = (string)($_POST['status'] ?? '');
        if (isset(ORDER_STATUS[$status])) {
            $pdo->prepare('UPDATE shop_order SET status = ? WHERE id = ?')->execute([$status, (int)($_POST['order_id'] ?? 0)]);
            flash('주문 상태를 변경했습니다.');
        }
        redirect('/admin/?' . http_build_query(['status' => $_POST['filter'] ?? '']));
    }
}

render_header('관리자');

if (!$admin):
?>
<div class="lookup-layout narrow">
  <form method="post" class="lookup">
    <?= csrf_field() ?>
    <?php if (!$hasAdmin): ?>
      <h2>관리자 계정 만들기</h2>
      <p class="hint">처음 접속했습니다. 사용할 관리자 계정을 만들어 주세요.</p>
      <input type="hidden" name="action" value="setup">
    <?php else: ?>
      <h2>관리자 로그인</h2>
      <input type="hidden" name="action" value="login">
    <?php endif; ?>
    <?php if ($error): ?><div class="errors"><p><?= h($error) ?></p></div><?php endif; ?>
    <label>아이디<input name="username" required autocomplete="username"></label>
    <label>비밀번호<input type="password" name="password" required autocomplete="<?= $hasAdmin ? 'current-password' : 'new-password' ?>"></label>
    <button class="btn wide"><?= $hasAdmin ? '로그인' : '계정 만들기' ?></button>
  </form>
</div>
<?php
    render_footer();
    exit;
endif;

$filter = (string)($_GET['status'] ?? '');
$sql = 'SELECT o.*, (SELECT COUNT(*) FROM shop_order_item i WHERE i.order_id = o.id) AS item_count FROM shop_order o';
$args = [];
if (isset(ORDER_STATUS[$filter])) {
    $sql .= ' WHERE o.status = ?';
    $args[] = $filter;
}
$st = $pdo->prepare($sql . ' ORDER BY o.id DESC LIMIT 100');
$st->execute($args);
$orders = $st->fetchAll();

$stats = $pdo->query("SELECT COUNT(*) AS cnt, COALESCE(SUM(total_amount), 0) AS amount FROM shop_order WHERE status <> 'cancelled' AND DATE(created_at) = CURRENT_DATE")->fetch();
$products = $pdo->query('SELECT id, code, name, stock, COALESCE(sale_price, price) AS price FROM shop_product ORDER BY id')->fetchAll();
?>
<div class="admin-head">
  <h1 class="page-title">관리자 <small><?= h($admin) ?></small></h1>
  <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button class="btn small outline">로그아웃</button></form>
</div>

<div class="stats">
  <div><p>오늘 주문</p><strong><?= (int)$stats['cnt'] ?>건</strong></div>
  <div><p>오늘 매출</p><strong><?= won((int)$stats['amount']) ?></strong></div>
  <div><p>품절 상품</p><strong><?= count(array_filter($products, fn($p) => (int)$p['stock'] === 0)) ?>개</strong></div>
</div>

<nav class="tabs">
  <a href="/admin/" class="<?= $filter === '' ? 'on' : '' ?>">전체</a>
  <?php foreach (ORDER_STATUS as $k => $label): ?>
  <a href="/admin/?status=<?= $k ?>" class="<?= $filter === $k ? 'on' : '' ?>"><?= h($label) ?></a>
  <?php endforeach; ?>
</nav>

<table class="cart-table admin-table">
  <thead><tr><th>주문번호</th><th>주문일시</th><th>주문자</th><th>상품수</th><th>금액</th><th>배송 메모</th><th>상태</th></tr></thead>
  <tbody>
  <?php foreach ($orders as $o): ?>
    <tr>
      <td><?= h($o['order_no']) ?></td>
      <td><?= h($o['created_at']) ?></td>
      <td><?= h($o['buyer_name']) ?><br><small><?= h($o['buyer_phone']) ?></small></td>
      <td><?= (int)$o['item_count'] ?></td>
      <td><?= won((int)$o['total_amount']) ?></td>
      <td><?= h($o['delivery_memo'] ?? '-') ?></td>
      <td>
        <form method="post" class="status-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
          <input type="hidden" name="filter" value="<?= h($filter) ?>">
          <select name="status" onchange="this.form.submit()">
            <?php foreach (ORDER_STATUS as $k => $label): ?>
            <option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$orders): ?><tr><td colspan="7" class="empty">주문이 없습니다.</td></tr><?php endif; ?>
  </tbody>
</table>

<h2 class="section-title">상품 재고</h2>
<table class="cart-table admin-table">
  <thead><tr><th>코드</th><th>상품명</th><th>판매가</th><th>재고</th></tr></thead>
  <tbody>
  <?php foreach ($products as $p): ?>
    <tr><td><?= h($p['code']) ?></td><td><?= h($p['name']) ?></td><td><?= won((int)$p['price']) ?></td>
      <td class="<?= (int)$p['stock'] === 0 ? 'red' : '' ?>"><?= (int)$p['stock'] ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php render_footer();
