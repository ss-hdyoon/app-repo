<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
start_session();

$mine = array_values(array_unique($_SESSION['my_orders'] ?? []));
$recent = [];
if ($mine) {
    $in = implode(',', array_fill(0, count($mine), '?'));
    $st = db()->prepare("SELECT order_no, created_at, total_amount, status FROM shop_order WHERE order_no IN ($in) ORDER BY id DESC");
    $st->execute($mine);
    $recent = $st->fetchAll();
}

render_header('주문조회');
?>
<h1 class="page-title">주문조회</h1>

<div class="lookup-layout">
  <form method="post" action="/order.php" class="lookup">
    <?= csrf_field() ?>
    <h2>비회원 주문조회</h2>
    <label>주문번호<input name="order_no" placeholder="A261023ABC123" required></label>
    <label>휴대폰 번호<input name="buyer_phone" placeholder="010-1234-5678" required></label>
    <button class="btn wide">조회하기</button>
  </form>

  <?php if ($recent): ?>
  <section class="recent">
    <h2>이 브라우저의 최근 주문</h2>
    <table class="cart-table">
      <thead><tr><th>주문번호</th><th>주문일시</th><th>금액</th><th>상태</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $o): ?>
        <tr>
          <td><a href="/order.php?no=<?= urlencode($o['order_no']) ?>"><?= h($o['order_no']) ?></a></td>
          <td><?= h($o['created_at']) ?></td>
          <td><?= won((int)$o['total_amount']) ?></td>
          <td><span class="status s-<?= h($o['status']) ?>"><?= h(ORDER_STATUS[$o['status']] ?? $o['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>
</div>
<?php render_footer();
