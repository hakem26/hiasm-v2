<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin('products.view');

require_once BASE_PATH . '/core/queries/products.php';
require_once BASE_PATH . '/core/queries/work_months.php';
require_once BASE_PATH . '/core/queries/partners.php';

$productQuery    = new ProductQuery();
$workMonthQuery  = new WorkMonthQuery();
$partnerQuery    = new PartnerQuery();

// فیلترها
$workMonthId  = (int)get('work_month_id');
$roleFilter   = get('role_filter', 'all');   // all | leader | seller
$partnerId    = (int)get('partner_id');

// لیست ماه‌های کاری برای فیلتر
$workMonths = $workMonthQuery->getAll();

// جفت‌های کاری بر اساس نقش
$partners = [];
if ($workMonthId > 0) {
    $partners = $partnerQuery->getByWorkMonth($workMonthId, $roleFilter, currentUserId());
}

// گزارش فروش
$soldData   = [];
$totalQty   = 0;
$totalAmt   = 0;

if ($workMonthId > 0) {
    $filters = ['work_month_id' => $workMonthId];
    if ($partnerId > 0) $filters['partner_id'] = $partnerId;

    $soldData = $productQuery->getSoldReport($filters);
    foreach ($soldData as $row) {
        $totalQty += (int)$row['total_qty'];
        $totalAmt += (float)$row['total_amount'];
    }
}

$pageTitle = 'فروش محصولات';
$apiUrl    = BASE_URL . '/api/products.php';
require_once BASE_PATH . '/includes/header.php';
?>

<div class="page-header mb-3">
  <div class="row align-items-center">
    <div class="col">
      <h2 class="page-title">
        <i class="ti ti-chart-bar me-2 text-primary"></i>لیست محصولات فروخته‌شده
      </h2>
    </div>
  </div>
</div>

<!-- فیلترها -->
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" id="filter-form">
      <div class="row g-3 align-items-end">

        <div class="col-md-3">
          <label class="form-label">ماه کاری</label>
          <select name="work_month_id" class="form-select" id="sel-month">
            <option value="">— انتخاب کنید —</option>
            <?php foreach ($workMonths as $wm): ?>
              <option value="<?= $wm['work_month_id'] ?>"
                <?= $workMonthId == $wm['work_month_id'] ? 'selected' : '' ?>>
                <?= e($wm['title']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">نقش در همکاری</label>
          <select name="role_filter" class="form-select" id="sel-role">
            <option value="all"    <?= $roleFilter === 'all'    ? 'selected' : '' ?>>همه</option>
            <option value="leader" <?= $roleFilter === 'leader' ? 'selected' : '' ?>>سرگروه</option>
            <option value="seller" <?= $roleFilter === 'seller' ? 'selected' : '' ?>>زیرگروه</option>
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label">همکار</label>
          <select name="partner_id" class="form-select" id="sel-partner">
            <option value="">— همه —</option>
            <?php foreach ($partners as $p): ?>
              <option value="<?= $p['partner_id'] ?>"
                <?= $partnerId == $p['partner_id'] ? 'selected' : '' ?>>
                <?= e($p['leader_name']) ?>
                <?= $p['seller_name'] ? ' + ' . e($p['seller_name']) : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-3">
          <button type="submit" class="btn btn-primary w-100">
            <i class="ti ti-filter me-1"></i>اعمال فیلتر
          </button>
        </div>

      </div>
    </form>
  </div>
</div>

<?php if ($workMonthId > 0): ?>
<!-- آمار کلی -->
<div class="row row-cards mb-3">
  <div class="col-sm-6">
    <div class="card">
      <div class="card-body text-center">
        <div class="text-muted mb-1">تعداد کل فروش</div>
        <div class="h2 num"><?= number_format($totalQty) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6">
    <div class="card">
      <div class="card-body text-center">
        <div class="text-muted mb-1">مبلغ کل فروش</div>
        <div class="h2 num"><?= number_format($totalAmt) ?>
          <small class="text-muted fs-4">تومان</small>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- جدول فروش -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-vcenter card-table">
        <thead>
          <tr>
            <th>محصول</th>
            <th class="text-center">قیمت واحد</th>
            <th class="text-center">تعداد</th>
            <th class="text-center">مبلغ کل</th>
            <th class="text-center">سفارشات</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($soldData)): ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">
                داده‌ای برای نمایش وجود ندارد
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($soldData as $row): ?>
              <tr>
                <td><?= e($row['product_name']) ?></td>
                <td class="text-center num"><?= number_format((float)$row['unit_price']) ?></td>
                <td class="text-center num"><?= number_format((int)$row['total_qty']) ?></td>
                <td class="text-center num"><?= number_format((float)$row['total_amount']) ?></td>
                <td class="text-center">
                  <a href="<?= BASE_URL ?>/modules/products/sold_detail.php?product_id=<?= $row['product_id'] ?>&work_month_id=<?= $workMonthId ?><?= $partnerId ? '&partner_id='.$partnerId : '' ?>"
                     class="btn btn-sm btn-icon btn-ghost-primary" title="مشاهده سفارشات">
                    <i class="ti ti-eye"></i>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card">
  <div class="card-body text-center py-5 text-muted">
    <i class="ti ti-filter mb-3" style="font-size:2.5rem"></i>
    <p>برای مشاهده گزارش، ابتدا ماه کاری را انتخاب کنید</p>
  </div>
</div>
<?php endif; ?>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // با تغییر ماه کاری، فرم خودکار submit بشه
  document.getElementById('sel-month').addEventListener('change', function() {
    document.getElementById('filter-form').submit();
  });
  document.getElementById('sel-role').addEventListener('change', function() {
    document.getElementById('filter-form').submit();
  });
});
</script>
