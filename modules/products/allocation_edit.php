<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../../core/init.php';
requireLogin();

$productId = (int)get('product_id');
$dateJ     = toEnglishDigits(get('date'));
$ownerId   = currentUserId();

if (!$productId || !$dateJ) {
    redirect(BASE_URL . '/modules/products/stock.php');
}

$dateM = fromJalali($dateJ);
$db    = getDB();

// پیدا کردن سندی که این محصول رو در این تاریخ داره
$stmt = $db->prepare("
    SELECT ad.id
    FROM   allocation_docs ad
    JOIN   allocation_items ai ON ai.doc_id = ad.id
    WHERE  ad.owner_id = ? AND ad.alloc_date = ? AND ai.product_id = ?
    LIMIT  1
");
$stmt->execute([$ownerId, $dateM, $productId]);
$docId = $stmt->fetchColumn();

if ($docId) {
    redirect(BASE_URL . '/modules/products/allocation_add.php?edit_id=' . $docId);
} else {
    setFlash('error', 'سند تخصیصی برای این محصول در این تاریخ یافت نشد — ممکن است موجودی از طریق سند دیگری ثبت شده باشد');
    redirect(BASE_URL . '/modules/products/allocation.php');
}
