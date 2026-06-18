<?php
define('HIASM_ENTRY', true);

ob_start();
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../core/init.php';

header('Content-Type: application/json; charset=utf-8');

try {
    Response::requireAuth();

    require_once BASE_PATH . '/core/queries/partners.php';
    $partnerQuery = new PartnerQuery();

    $action = post('action') ?: get('action');

    if ($action === 'delete') {
        Response::requirePost();
        Response::requireAuth('partners.manage');

        $partnerId = (int)post('partner_id');
        if (!$partnerId) {
            ob_end_clean();
            Response::error('شناسه نامعتبر');
        }

        $partner = $partnerQuery->getById($partnerId);
        if (!$partner) {
            ob_end_clean();
            Response::error('جفت کاری یافت نشد');
        }

        // بررسی وجود work_details ثبت‌شده برای این جفت
        $db = getDB();
        $check = $db->prepare("SELECT COUNT(*) FROM work_details WHERE partner_id = ?");
        $check->execute([$partnerId]);
        $count = (int)$check->fetchColumn();

        if ($count > 0) {
            ob_end_clean();
            Response::error("برای این جفت $count روز کاری ثبت شده — ابتدا روزهای کاری را حذف کنید");
        }

        $deleted = $partnerQuery->delete($partnerId);
        ob_end_clean();

        if ($deleted) {
            Response::success('جفت کاری حذف شد');
        } else {
            Response::error('خطا در حذف');
        }
    }

    ob_end_clean();
    Response::error('عملیات نامشخص');

} catch (Throwable $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'خطای سرور: ' . $e->getMessage()
    ]);
    exit;
}
