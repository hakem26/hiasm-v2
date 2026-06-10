<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';

Response::requireAuth();

$db     = getDB();
$action = post('action') ?: get('action');

match ($action) {
    'create_alloc' => actionCreateAlloc(),
    'update_alloc' => actionUpdateAlloc(),
    'delete_alloc' => actionDeleteAlloc(),
    'return'       => actionReturn(),
    default        => Response::error('عملیات نامعتبر است')
};

// ── ایجاد سند تخصیص جدید ─────────────────────────────────────
function actionCreateAlloc(): never {
    global $db;
    Response::requirePost();

    $ownerId = (int)post('owner_id');
    $dateJ   = post('date');
    $items   = json_decode(post('items'), true);

    if (!$ownerId || !$dateJ || empty($items)) {
        Response::error('اطلاعات ناقص است');
    }

    // فقط خودت یا ادمین می‌تونه ثبت کنه
    if ($ownerId !== currentUserId() && !hasRole(ROLE_ADMIN)) {
        Response::forbidden();
    }

    $dateM = fromJalali($dateJ);

    try {
        $db->beginTransaction();

        // ثبت سند
        $stmt = $db->prepare("
            INSERT INTO allocation_docs (owner_id, alloc_date) VALUES (?, ?)
        ");
        $stmt->execute([$ownerId, $dateM]);
        $docId = (int)$db->lastInsertId();

        // ثبت اقلام + بروزرسانی inventory
        foreach ($items as $item) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            if ($pid <= 0 || $qty <= 0) continue;

            $db->prepare("
                INSERT INTO allocation_items (doc_id, product_id, quantity) VALUES (?, ?, ?)
            ")->execute([$docId, $pid, $qty]);

            // بروزرسانی یا ایجاد موجودی
            $db->prepare("
                INSERT INTO inventory (owner_id, product_id, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ")->execute([$ownerId, $pid, $qty]);

            // ثبت تراکنش انبار
            $db->prepare("
                INSERT INTO inventory_transactions
                       (from_owner_id, to_owner_id, product_id, quantity, txn_type, created_by)
                VALUES (NULL, ?, ?, ?, 'receive', ?)
            ")->execute([$ownerId, $pid, $qty, currentUserId()]);
        }

        $db->commit();
        Response::success('سند تخصیص با موفقیت ثبت شد');

    } catch (Exception $e) {
        $db->rollBack();
        error_log('[ALLOC] ' . $e->getMessage());
        Response::error('خطا در ثبت سند');
    }
}

// ── ویرایش سند تخصیص ─────────────────────────────────────────
function actionUpdateAlloc(): never {
    global $db;
    Response::requirePost();

    $docId   = (int)post('doc_id');
    $ownerId = (int)post('owner_id');
    $dateJ   = post('date');
    $newItems = json_decode(post('items'), true);

    if (!$docId || !$ownerId || !$dateJ || empty($newItems)) {
        Response::error('اطلاعات ناقص است');
    }

    // بررسی مالکیت
    $doc = $db->prepare("SELECT * FROM allocation_docs WHERE id = ? AND owner_id = ?");
    $doc->execute([$docId, $ownerId]);
    $docRow = $doc->fetch();
    if (!$docRow && !hasRole(ROLE_ADMIN)) Response::forbidden();

    $dateM = fromJalali($dateJ);

    try {
        $db->beginTransaction();

        // اقلام قدیمی
        $oldItems = $db->prepare("SELECT * FROM allocation_items WHERE doc_id = ?");
        $oldItems->execute([$docId]);
        $oldItemsArr = $oldItems->fetchAll();

        // برگشت موجودی قدیمی
        foreach ($oldItemsArr as $old) {
            $db->prepare("
                UPDATE inventory SET quantity = quantity - ?
                WHERE owner_id = ? AND product_id = ?
            ")->execute([$old['quantity'], $ownerId, $old['product_id']]);

            // ثبت تراکنش برگشت
            $db->prepare("
                INSERT INTO inventory_transactions
                       (from_owner_id, to_owner_id, product_id, quantity, txn_type, created_by)
                VALUES (?, NULL, ?, ?, 'adjust', ?)
            ")->execute([$ownerId, $old['product_id'], $old['quantity'], currentUserId()]);
        }

        // حذف اقلام قدیمی
        $db->prepare("DELETE FROM allocation_items WHERE doc_id = ?")->execute([$docId]);

        // بروزرسانی تاریخ سند
        $db->prepare("UPDATE allocation_docs SET alloc_date = ? WHERE id = ?")
           ->execute([$dateM, $docId]);

        // ثبت اقلام جدید
        foreach ($newItems as $item) {
            $pid = (int)$item['product_id'];
            $qty = (int)$item['quantity'];
            if ($pid <= 0 || $qty <= 0) continue;

            $db->prepare("
                INSERT INTO allocation_items (doc_id, product_id, quantity) VALUES (?, ?, ?)
            ")->execute([$docId, $pid, $qty]);

            $db->prepare("
                INSERT INTO inventory (owner_id, product_id, quantity)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ")->execute([$ownerId, $pid, $qty]);

            $db->prepare("
                INSERT INTO inventory_transactions
                       (from_owner_id, to_owner_id, product_id, quantity, txn_type, created_by)
                VALUES (NULL, ?, ?, ?, 'receive', ?)
            ")->execute([$ownerId, $pid, $qty, currentUserId()]);
        }

        $db->commit();
        Response::success('سند با موفقیت بروزرسانی شد');

    } catch (Exception $e) {
        $db->rollBack();
        error_log('[ALLOC_UPDATE] ' . $e->getMessage());
        Response::error('خطا در بروزرسانی سند');
    }
}

// ── حذف سند تخصیص ────────────────────────────────────────────
function actionDeleteAlloc(): never {
    global $db;
    Response::requirePost();

    $docId = (int)post('doc_id');
    if (!$docId) Response::error('شناسه نامعتبر است');

    $doc = $db->prepare("SELECT * FROM allocation_docs WHERE id = ?");
    $doc->execute([$docId]);
    $docRow = $doc->fetch();
    if (!$docRow) Response::notFound();

    // فقط مالک یا ادمین
    if ($docRow['owner_id'] !== currentUserId() && !hasRole(ROLE_ADMIN)) {
        Response::forbidden();
    }

    try {
        $db->beginTransaction();

        $items = $db->prepare("SELECT * FROM allocation_items WHERE doc_id = ?");
        $items->execute([$docId]);

        foreach ($items->fetchAll() as $item) {
            $db->prepare("
                UPDATE inventory SET quantity = GREATEST(0, quantity - ?)
                WHERE owner_id = ? AND product_id = ?
            ")->execute([$item['quantity'], $docRow['owner_id'], $item['product_id']]);

            $db->prepare("
                INSERT INTO inventory_transactions
                       (from_owner_id, to_owner_id, product_id, quantity, txn_type, created_by)
                VALUES (?, NULL, ?, ?, 'adjust', ?)
            ")->execute([$docRow['owner_id'], $item['product_id'], $item['quantity'], currentUserId()]);
        }

        // cascade delete اقلام رو هم پاک می‌کنه
        $db->prepare("DELETE FROM allocation_docs WHERE id = ?")->execute([$docId]);

        $db->commit();
        Response::success('سند با موفقیت حذف شد');

    } catch (Exception $e) {
        $db->rollBack();
        Response::error('خطا در حذف سند');
    }
}

// ── بازگشت محصول به انبار شرکت ───────────────────────────────
function actionReturn(): never {
    global $db;
    Response::requirePost();

    $productId = (int)post('product_id');
    $ownerId   = (int)post('owner_id');
    $qty       = (int)post('qty');
    $dateJ     = post('date');

    if (!$productId || !$ownerId || $qty <= 0 || !$dateJ) {
        Response::error('اطلاعات ناقص است');
    }
    if ($ownerId !== currentUserId() && !hasRole(ROLE_ADMIN)) {
        Response::forbidden();
    }

    // بررسی موجودی کافی
    $inv = $db->prepare("SELECT quantity FROM inventory WHERE owner_id = ? AND product_id = ?");
    $inv->execute([$ownerId, $productId]);
    $current = (int)($inv->fetchColumn() ?: 0);

    if ($qty > $current) {
        Response::error("موجودی کافی نیست — موجودی فعلی: {$current} عدد");
    }

    try {
        $db->beginTransaction();

        // کسر از موجودی
        $db->prepare("
            UPDATE inventory SET quantity = quantity - ?
            WHERE owner_id = ? AND product_id = ?
        ")->execute([$qty, $ownerId, $productId]);

        // ثبت تراکنش بازگشت
        $db->prepare("
            INSERT INTO inventory_transactions
                   (from_owner_id, to_owner_id, product_id, quantity, txn_type, created_by)
            VALUES (?, NULL, ?, ?, 'transfer', ?)
        ")->execute([$ownerId, $productId, $qty, currentUserId()]);

        $db->commit();
        Response::success("{$qty} عدد با موفقیت به انبار برگشت داده شد");

    } catch (Exception $e) {
        $db->rollBack();
        Response::error('خطا در ثبت بازگشت');
    }
}
