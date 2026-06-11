<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin();

$docId   = (int)get('id');
$ownerId = currentUserId();
$db      = getDB();

$doc = $db->prepare("SELECT * FROM allocation_docs WHERE id = ? AND owner_id = ?");
$doc->execute([$docId, $ownerId]);
$docRow = $doc->fetch();

if (!$docRow && !hasRole(ROLE_ADMIN)) {
    setFlash('error', 'سند یافت نشد');
    redirect(BASE_URL . '/modules/products/allocation.php');
}
if (!$docRow) {
    $doc = $db->prepare("SELECT * FROM allocation_docs WHERE id = ?");
    $doc->execute([$docId]);
    $docRow = $doc->fetch();
    if (!$docRow) {
        setFlash('error', 'سند یافت نشد');
        redirect(BASE_URL . '/modules/products/allocation.php');
    }
}

$items = $db->prepare("
    SELECT ai.*, p.product_name
    FROM   allocation_items ai
    JOIN   products p ON p.product_id = ai.product_id
    WHERE  ai.doc_id = ?
    ORDER  BY p.product_name
");
$items->execute([$docId]);
$itemsArr = $items->fetchAll();
$totalQty = array_sum(array_column($itemsArr, 'quantity'));

$pageTitle = 'مشاهده سند تخصیص';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/allocation.php"
         class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت به لیست
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-clipboard-list me-2 text-primary"></i>
        سند تخصیص — <span class="ltr"><?= toJalali($docRow['alloc_date']) ?></span>
      </h2>
    </div>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/allocation_add.php?edit_id=<?= $docId ?>"
         class="btn btn-primary btn-sm">
        <i class="ti ti-edit me-1"></i>ویرایش سند
      </a>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3 class="card-title">اقلام این سند</h3>
    <div class="card-options text-muted small">
      مجموع: <strong class="num"><?= number_format($totalQty) ?></strong> عدد
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th>محصول</th>
          <th class="text-center">تعداد تخصیص‌یافته</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($itemsArr)): ?>
          <tr><td colspan="2" class="text-center text-muted py-4">آیتمی ثبت نشده</td></tr>
        <?php else: ?>
          <?php foreach ($itemsArr as $item): ?>
            <tr>
              <td><?= e($item['product_name']) ?></td>
              <td class="text-center num fw-bold"><?= number_format((int)$item['quantity']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
