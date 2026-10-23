<?php
// Order detail. Visible to the session that placed the order, or to anyone who
// enters the matching order number and phone number on orders.php.
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
start_session();

$order = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM shop_order WHERE order_no = ? AND buyer_phone = ?');
    $st->execute([trim((string)($_POST['order_no'] ?? '')), preg_replace('/\D/', '', (string)($_POST['buyer_phone'] ?? ''))]);
    $order = $st->fetch() ?: null;
    if (!$order) {
        flash('일치하는 주문이 없습니다. 주문번호와 휴대폰 번호를 확인해 주세요.');
        redirect('/orders.php');
    }
    $_SESSION['my_orders'][] = $order['order_no'];
} else {
    $no = (string)($_GET['no'] ?? '');
    if (in_array($no, $_SESSION['my_orders'] ?? [], true)) {
        $st = db()->prepare('SELECT * FROM shop_order WHERE order_no = ?');
        $st->execute([$no]);
        $order = $st->fetch() ?: null;
    }
    if (!$order) {
        redirect('/orders.php');
    }
}

$st = db()->prepare('SELECT * FROM shop_order_item WHERE order_id = ? ORDER BY id');
$st->execute([$order['id']]);
$items = $st->fetchAll();
$payLabels = ['card' => '신용카드 (모의)', 'bank' => '무통장입금 (모의)', 'kakaopay' => '간편결제 (모의)'];

render_header('주문 상세');
?>
<?php if (isset($_GET['done'])): ?>
<div class="done-box">
  <h1>주문이 완료되었습니다</h1>
  <p>주문번호 <strong><?= h($order['order_no']) ?></strong></p>
</div>
<?php else: ?>
<h1 class="page-title">주문 상세</h1>
<?php endif; ?>

<div class="order-detail">
  <section>
    <h2>주문 정보</h2>
    <dl class="kv">
      <dt>주문번호</dt><dd><?= h($order['order_no']) ?></dd>
      <dt>주문일시</dt><dd><?= h($order['created_at']) ?></dd>
      <dt>주문상태</dt><dd><span class="status s-<?= h($order['status']) ?>"><?= h(ORDER_STATUS[$order['status']] ?? $order['status']) ?></span></dd>
      <dt>결제수단</dt><dd><?= h($payLabels[$order['pay_method']] ?? $order['pay_method']) ?></dd>
    </dl>
  </section>
  <section>
    <h2>배송지</h2>
    <dl class="kv">
      <dt>받는 분</dt><dd><?= h($order['buyer_name']) ?> · <?= h($order['buyer_phone']) ?></dd>
      <dt>주소</dt><dd>(<?= h($order['zipcode']) ?>) <?= h($order['address1']) ?> <?= h($order['address2']) ?></dd>
      <dt>배송 메모</dt><dd><?= h($order['delivery_memo'] ?? '-') ?></dd>
    </dl>
  </section>
  <section class="span2">
    <h2>주문 상품</h2>
    <table class="cart-table">
      <thead><tr><th>상품</th><th>가격</th><th>수량</th><th>금액</th></tr></thead>
      <tbody>
      <?php foreach ($items as $i): ?>
        <tr><td><?= h($i['product_name']) ?></td><td><?= won((int)$i['unit_price']) ?></td><td><?= (int)$i['qty'] ?></td><td><?= won((int)$i['line_amount']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <dl class="kv right">
      <dt>상품 금액</dt><dd><?= won((int)$order['items_amount']) ?></dd>
      <dt>배송비</dt><dd><?= (int)$order['shipping_fee'] ? won((int)$order['shipping_fee']) : '무료' ?></dd>
      <dt class="total">결제 금액</dt><dd class="total"><?= won((int)$order['total_amount']) ?></dd>
    </dl>
  </section>
</div>
<p class="center"><a class="btn outline" href="/">쇼핑 계속하기</a></p>
<?php render_footer();
