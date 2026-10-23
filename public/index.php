<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
start_session();

$category = (string)($_GET['category'] ?? '');
$sort = (string)($_GET['sort'] ?? 'new');
$orderBy = match ($sort) {
    'low'  => 'COALESCE(sale_price, price) ASC',
    'high' => 'COALESCE(sale_price, price) DESC',
    default => 'id DESC',
};

$sql = 'SELECT * FROM shop_product WHERE is_active = 1';
$args = [];
if ($category !== '') {
    $sql .= ' AND category = ?';
    $args[] = $category;
}
$st = db()->prepare("$sql ORDER BY $orderBy");
$st->execute($args);
$products = $st->fetchAll();

render_header($category ?: '전체 상품');
?>
<?php if ($category === ''): ?>
<section class="hero">
  <div>
    <p class="eyebrow">NEW SEASON</p>
    <h1>더 멀리, 더 가볍게</h1>
    <p>러닝부터 라이프스타일까지. 샌드박스에서 주문 흐름을 그대로 시험해 보세요.</p>
    <a class="btn" href="/?category=러닝">러닝화 보기</a>
  </div>
</section>
<?php endif; ?>

<div class="list-head">
  <h2><?= h($category ?: '전체 상품') ?> <small><?= count($products) ?>개</small></h2>
  <form method="get" class="sort">
    <?php if ($category !== ''): ?><input type="hidden" name="category" value="<?= h($category) ?>"><?php endif; ?>
    <select name="sort" onchange="this.form.submit()">
      <option value="new" <?= $sort === 'new' ? 'selected' : '' ?>>신상품순</option>
      <option value="low" <?= $sort === 'low' ? 'selected' : '' ?>>낮은 가격순</option>
      <option value="high" <?= $sort === 'high' ? 'selected' : '' ?>>높은 가격순</option>
    </select>
  </form>
</div>

<div class="grid">
  <?php foreach ($products as $p): ?>
  <a class="card" href="/product.php?id=<?= (int)$p['id'] ?>">
    <?= product_thumb($p) ?>
    <?php if ((int)$p['stock'] === 0): ?><span class="badge soldout">품절</span>
    <?php elseif ($p['sale_price'] !== null): ?><span class="badge sale">SALE</span><?php endif; ?>
    <div class="card-body">
      <p class="cat"><?= h($p['category']) ?></p>
      <p class="name"><?= h($p['name']) ?></p>
      <p class="price">
        <?php if ($p['sale_price'] !== null): ?>
          <del><?= won((int)$p['price']) ?></del> <strong class="red"><?= won((int)$p['sale_price']) ?></strong>
        <?php else: ?>
          <strong><?= won((int)$p['price']) ?></strong>
        <?php endif; ?>
      </p>
    </div>
  </a>
  <?php endforeach; ?>
  <?php if (!$products): ?><p class="empty">상품이 없습니다.</p><?php endif; ?>
</div>
<?php render_footer();
