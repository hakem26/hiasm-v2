<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin();

$db      = getDB();
$ownerId = currentUserId();

$docs = $db->prepare("
    SELECT id, alloc_date
    FROM   allocation_docs
    WHERE  owner_id = ?
    ORDER  BY alloc_date DESC, id DESC
");
$docs->execute([$ownerId]);
$allocDocs = $docs->fetchAll();

$pageTitle = 'مدیریت تخصیص محصولات';
$apiUrl    = BASE_URL . '/api/inventory.php';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/stock.php"
         class="btn btn-ghost-secondary btn-sm">
        <i class="ti ti-arrow-right me-1"></i>بازگشت
      </a>
    </div>
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-clipboard-list me-2 text-primary"></i>مدیریت تخصیص محصولات
      </h2>
    </div>
    <div class="col-auto">
      <a href="<?= BASE_URL ?>/modules/products/allocation_add.php"
         class="btn btn-primary">
        <i class="ti ti-plus me-1"></i>ایجاد سند جدید
      </a>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-responsive">
    <table class="table table-vcenter card-table">
      <thead>
        <tr>
          <th width="60">ردیف</th>
          <th>تاریخ تخصیص</th>
          <th class="text-center">عملیات</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($allocDocs)): ?>
          <tr>
            <td colspan="3" class="text-center text-muted py-5">
              <i class="ti ti-clipboard-x mb-2" style="font-size:2rem"></i>
              <p>سندی ثبت نشده — از دکمه «ایجاد سند جدید» شروع کنید</p>
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($allocDocs as $i => $doc): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td class="ltr"><?= toJalali($doc['alloc_date']) ?></td>
              <td class="text-center">
                <a href="<?= BASE_URL ?>/modules/products/allocation_view.php?id=<?= $doc['id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-info" title="نمایش">
                  <i class="ti ti-eye"></i>
                </a>
                <a href="<?= BASE_URL ?>/modules/products/allocation_add.php?edit_id=<?= $doc['id'] ?>"
                   class="btn btn-sm btn-icon btn-ghost-primary" title="ویرایش سند">
                  <i class="ti ti-edit"></i>
                </a>
                <button onclick="deleteDoc(<?= $doc['id'] ?>)"
                        class="btn btn-sm btn-icon btn-ghost-danger" title="حذف سند">
                  <i class="ti ti-trash"></i>
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
var API_URL = <?= json_encode($apiUrl) ?>;

function deleteDoc(id) {
  if (!hiasm.confirm('این سند تخصیص حذف شود؟\nتوجه: تأثیر مستقیم بر موجودی دارد!')) return;
  hiasm.post(API_URL, { action: 'delete_alloc', doc_id: id })
    .then(function(res) {
      hiasm.toast(res.message, res.success ? 'success' : 'error');
      if (res.success) location.reload();
    });
}
</script>
