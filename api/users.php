<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';

Response::requireAjax();
Response::requireAuth('users.manage');

require_once BASE_PATH . '/core/queries/users.php';
$userQuery = new UserQuery();

$action = post('action');

match ($action) {
    'list'   => actionList(),
    'toggle' => actionToggle(),
    'delete' => actionDelete(),
    default  => Response::error('عملیات نامعتبر است')
};

// ── لیست کاربران ─────────────────────────────────────────────
function actionList(): never {
    global $userQuery;
    $users = $userQuery->getAllWithRole();
    // تبدیل تاریخ به شمسی
    foreach ($users as &$u) {
        $u['created_at_jalali'] = toJalali($u['created_at']);
        unset($u['password']); // هیچ‌وقت هش رمز رو نفرست
    }
    Response::success('', $users);
}

// ── تغییر وضعیت فعال/غیرفعال ─────────────────────────────────
function actionToggle(): never {
    global $userQuery;
    $id = (int)post('id');

    if ($id <= 0) Response::error('شناسه نامعتبر است');

    // جلوگیری از غیرفعال کردن خودمون
    if ($id === currentUserId()) {
        Response::error('نمی‌توانید حساب خودتان را غیرفعال کنید');
    }

    $user = $userQuery->findById($id);
    if (!$user) Response::notFound('کاربر یافت نشد');

    $userQuery->toggleActive($id);
    $status = $user['is_active'] ? 'غیرفعال' : 'فعال';
    Response::success("کاربر {$status} شد");
}

// ── حذف کاربر ────────────────────────────────────────────────
function actionDelete(): never {
    global $userQuery;
    $id = (int)post('id');

    if ($id <= 0) Response::error('شناسه نامعتبر است');
    if ($id === currentUserId()) Response::error('نمی‌توانید حساب خودتان را حذف کنید');

    $user = $userQuery->findById($id);
    if (!$user) Response::notFound('کاربر یافت نشد');

    // به جای حذف واقعی، غیرفعال کن (حفظ تاریخچه)
    $userQuery->update($id, ['is_active' => 0]);
    Response::success('کاربر با موفقیت حذف شد');
}