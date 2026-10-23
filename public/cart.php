<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['product_id'] ?? 0);
    $qty = max(0, min(10, (int)($_POST['qty'] ?? 0)));
    $cart = cart();

    if ($action === 'add' && $id > 0 && $qty > 0) {
        $st = db()->prepare('SELECT stock FROM shop_product WHERE id = ? AND is_active = 1');
        $st->execute([$id]);
        $stock = $st->fetchColumn();
        if ($stock === false) {
            flash('상품을 찾을 수 없습니다.');
            redirect('/');
        }
        $cart[$id] = min((int)$stock, ($cart[$id] ?? 0) + $qty, 10);
        if ($cart[$id] <= 0) {
            unset($cart[$id]);
            flash('품절된 상품입니다.');
        } else {
            flash('장바구니에 담았습니다.');
        }
        $_SESSION['cart'] = $cart;
        redirect(($_POST['next'] ?? '') === 'checkout' ? '/checkout.php' : '/cart.php');
    }
    if ($action === 'update' && isset($cart[$id])) {
        if ($qty === 0) {
            unset($cart[$id]);
        } else {
            $cart[$id] = $qty;
        }
        $_SESSION['cart'] = $cart;
    }
    if ($action === 'remove') {
        unset($cart[$id]);
        $_SESSION['cart'] = $cart;
    }
    redirect('/cart.php');
}

$lines = cart_lines();
$items = array_sum(array_column($lines, 'amount'));
$ship = shipping_fee($items);

render_header('장바구니');
?>
<h1 class="page-title">장바구니</h1>

<?php if (!$lines): ?>
  <div class="empty-box">
    <p>장바구니가 비어 있습니다.</p>
    <a class="btn" href="/">쇼핑 계속하기</a>
  </div>
<?php else: ?>
<div class="cart-layout">
  <table class="cart-table">
    <thead><tr><th>상품</th><th>가격</th><th>수량</th><th>금액</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lines as $l): $p = $l['product']; ?>
      <tr>
        <td class="item"><?= product_thumb($p, 'small') ?><a href="/product.php?id=<?= (int)$p['id'] ?>"><?= h($p['name']) ?></a></td>
        <td><?= won(effective_price($p)) ?></td>
        <td>
          <form method="post" class="qty-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <input type="number" name="qty" value="<?= (int)$l['qty'] ?>" min="0" max="10">
            <button class="btn small outline">변경</button>
          </form>
        </td>
        <td><strong><?= won($l['amount']) ?></strong></td>
        <td>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
            <button class="link">삭제</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <aside class="summary">
    <dl>
      <dt>상품 금액</dt><dd><?= won($items) ?></dd>
      <dt>배송비</dt><dd><?= $ship ? won($ship) : '무료' ?></dd>
      <dt class="total">결제 예정 금액</dt><dd class="total"><?= won($items + $ship) ?></dd>
    </dl>
    <?php if ($ship): ?><p class="hint"><?= won(FREE_SHIPPING_FROM - $items) ?> 더 담으면 무료배송</p><?php endif; ?>
    <a class="btn wide" href="/checkout.php">주문하기</a>
  </aside>
</div>
<?php endif; ?>
<?php render_footer();
