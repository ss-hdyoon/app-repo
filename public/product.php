<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
start_session();

$st = db()->prepare('SELECT * FROM shop_product WHERE id = ? AND is_active = 1');
$st->execute([(int)($_GET['id'] ?? 0)]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    render_header('상품 없음');
    echo '<p class="empty">상품을 찾을 수 없습니다.</p>';
    render_footer();
    exit;
}
$soldOut = (int)$p['stock'] === 0;

render_header($p['name']);
?>
<nav class="crumb"><a href="/">홈</a> › <a href="/?category=<?= h($p['category']) ?>"><?= h($p['category']) ?></a> › <?= h($p['name']) ?></nav>

<div class="detail">
  <?= product_thumb($p, 'large') ?>
  <div class="detail-info">
    <p class="cat"><?= h($p['category']) ?> · <?= h($p['code']) ?></p>
    <h1><?= h($p['name']) ?></h1>
    <p class="price big">
      <?php if ($p['sale_price'] !== null): ?>
        <del><?= won((int)$p['price']) ?></del>
        <strong class="red"><?= won((int)$p['sale_price']) ?></strong>
        <span class="rate"><?= (int)round(100 - $p['sale_price'] / $p['price'] * 100) ?>%</span>
      <?php else: ?>
        <strong><?= won((int)$p['price']) ?></strong>
      <?php endif; ?>
    </p>
    <p class="desc"><?= nl2br(h($p['description'])) ?></p>
    <p class="ship">배송비 <?= won(SHIPPING_FEE) ?> (<?= won(FREE_SHIPPING_FROM) ?> 이상 무료)</p>

    <?php if ($soldOut): ?>
      <button class="btn wide" disabled>품절</button>
    <?php else: ?>
    <form method="post" action="/cart.php" class="buy">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
      <label>수량
        <input type="number" name="qty" value="1" min="1" max="<?= min(10, (int)$p['stock']) ?>">
      </label>
      <p class="stock">재고 <?= (int)$p['stock'] ?>개</p>
      <div class="btns">
        <button class="btn outline" name="next" value="cart">장바구니 담기</button>
        <button class="btn" name="next" value="checkout">바로 구매</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php render_footer();
