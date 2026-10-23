<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
start_session();

$lines = cart_lines();
if (!$lines) {
    flash('장바구니가 비어 있습니다.');
    redirect('/cart.php');
}

$form = [
    'buyer_name' => '', 'buyer_phone' => '', 'buyer_email' => '',
    'zipcode' => '', 'address1' => '', 'address2' => '', 'delivery_memo' => '', 'pay_method' => 'card',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach ($form as $k => $_) {
        $form[$k] = trim((string)($_POST[$k] ?? ''));
    }
    if ($form['buyer_name'] === '') $errors[] = '주문자 이름을 입력해 주세요.';
    if (!preg_match('/^01[0-9]-?\d{3,4}-?\d{4}$/', $form['buyer_phone'])) $errors[] = '휴대폰 번호 형식을 확인해 주세요.';
    if (!filter_var($form['buyer_email'], FILTER_VALIDATE_EMAIL)) $errors[] = '이메일 형식을 확인해 주세요.';
    if (!preg_match('/^\d{5}$/', $form['zipcode'])) $errors[] = '우편번호 5자리를 입력해 주세요.';
    if ($form['address1'] === '') $errors[] = '주소를 입력해 주세요.';
    if (!in_array($form['pay_method'], ['card', 'bank', 'kakaopay'], true)) $errors[] = '결제 수단을 선택해 주세요.';
    if (mb_strlen($form['delivery_memo']) > 200) $errors[] = '배송 메모는 200자 이내로 입력해 주세요.';

    if (!$errors) {
        $pdo = db();
        $cart = cart();
        try {
            $pdo->beginTransaction();
            // Lock the rows so two orders cannot both take the last item.
            $ids = array_keys($cart);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("SELECT * FROM shop_product WHERE id IN ($in) AND is_active = 1 FOR UPDATE");
            $st->execute($ids);
            $items = 0;
            $rows = [];
            foreach ($st->fetchAll() as $p) {
                $qty = $cart[(int)$p['id']];
                if ((int)$p['stock'] < $qty) {
                    throw new DomainException("{$p['name']} 재고가 부족합니다. (남은 수량 {$p['stock']}개)");
                }
                $price = effective_price($p);
                $rows[] = [$p, $qty, $price, $price * $qty];
                $items += $price * $qty;
            }
            if (!$rows) {
                throw new DomainException('주문할 수 있는 상품이 없습니다.');
            }
            $ship = shipping_fee($items);
            $orderNo = 'A' . date('ymd') . strtoupper(bin2hex(random_bytes(3)));

            $pdo->prepare(
                'INSERT INTO shop_order (order_no, buyer_name, buyer_phone, buyer_email, zipcode, address1, address2,
                   delivery_memo, pay_method, items_amount, shipping_fee, total_amount, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'paid\')'
            )->execute([
                $orderNo, $form['buyer_name'], preg_replace('/\D/', '', $form['buyer_phone']), $form['buyer_email'],
                $form['zipcode'], $form['address1'], $form['address2'],
                $form['delivery_memo'] === '' ? null : $form['delivery_memo'],
                $form['pay_method'], $items, $ship, $items + $ship,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $insItem = $pdo->prepare(
                'INSERT INTO shop_order_item (order_id, product_id, product_name, unit_price, qty, line_amount)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $decStock = $pdo->prepare('UPDATE shop_product SET stock = stock - ? WHERE id = ?');
            foreach ($rows as [$p, $qty, $price, $amount]) {
                $insItem->execute([$orderId, $p['id'], $p['name'], $price, $qty, $amount]);
                $decStock->execute([$qty, $p['id']]);
            }
            $pdo->commit();

            $_SESSION['cart'] = [];
            $_SESSION['my_orders'][] = $orderNo;
            redirect('/order.php?no=' . urlencode($orderNo) . '&done=1');
        } catch (DomainException $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

$items = array_sum(array_column($lines, 'amount'));
$ship = shipping_fee($items);
$payLabels = ['card' => '신용카드 (모의)', 'bank' => '무통장입금 (모의)', 'kakaopay' => '간편결제 (모의)'];

render_header('주문서 작성');
?>
<h1 class="page-title">주문서 작성</h1>

<?php if ($errors): ?>
<div class="errors"><?php foreach ($errors as $e): ?><p><?= h($e) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<form method="post" class="checkout-layout">
  <?= csrf_field() ?>
  <div class="checkout-form">
    <section>
      <h2>주문자 정보</h2>
      <label>이름<input name="buyer_name" value="<?= h($form['buyer_name']) ?>" required></label>
      <label>휴대폰<input name="buyer_phone" value="<?= h($form['buyer_phone']) ?>" placeholder="010-1234-5678" required></label>
      <label>이메일<input type="email" name="buyer_email" value="<?= h($form['buyer_email']) ?>" required></label>
    </section>
    <section>
      <h2>배송지</h2>
      <label>우편번호<input name="zipcode" value="<?= h($form['zipcode']) ?>" placeholder="06236" maxlength="5" required></label>
      <label>주소<input name="address1" value="<?= h($form['address1']) ?>" placeholder="서울시 강남구 테헤란로 123" required></label>
      <label>상세주소<input name="address2" value="<?= h($form['address2']) ?>" placeholder="4층"></label>
      <label>배송 메모<input name="delivery_memo" value="<?= h($form['delivery_memo']) ?>" placeholder="문 앞에 놓아 주세요" maxlength="200"></label>
    </section>
    <section>
      <h2>결제 수단</h2>
      <div class="pay-options">
        <?php foreach ($payLabels as $k => $label): ?>
        <label class="radio"><input type="radio" name="pay_method" value="<?= $k ?>" <?= $form['pay_method'] === $k ? 'checked' : '' ?>> <?= h($label) ?></label>
        <?php endforeach; ?>
      </div>
      <p class="hint">모의 결제입니다. 주문 즉시 결제완료로 처리됩니다.</p>
    </section>
  </div>

  <aside class="summary">
    <h2>주문 상품</h2>
    <ul class="mini-lines">
      <?php foreach ($lines as $l): ?>
      <li><span><?= h($l['product']['name']) ?> × <?= (int)$l['qty'] ?></span><span><?= won($l['amount']) ?></span></li>
      <?php endforeach; ?>
    </ul>
    <dl>
      <dt>상품 금액</dt><dd><?= won($items) ?></dd>
      <dt>배송비</dt><dd><?= $ship ? won($ship) : '무료' ?></dd>
      <dt class="total">최종 결제 금액</dt><dd class="total"><?= won($items + $ship) ?></dd>
    </dl>
    <button class="btn wide"><?= won($items + $ship) ?> 결제하기</button>
  </aside>
</form>
<?php render_footer();
