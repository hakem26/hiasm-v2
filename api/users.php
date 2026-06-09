<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';

Response::requireAjax();
Response::requireAuth('users.manage');

require_once BASE_PATH . '/core/queries/users.php';
$userQuery = new UserQuery();

$action = post('action') ?: get('action');

match ($action) {
    'list'   => actionList(),
    'toggle' => actionToggle(),
    'delete' => actionDelete(),
    default  => Response::error('عملیات نامعتبر است')
};

function actionList(): never {
    global $userQuery;
    $users = $userQuery->getAllWithRole();
    foreach ($users as &$u) {
        $u['created_at_jalali'] = toJalali($u['created_at']);
        unset($u['password']);
    }
    Response::success('', $users);
}

function actionToggle(): never {
    global $userQuery;
    $id = (int)post('id');

    if ($id <= 0) Response::error('شناسه نامعتبر است');
    if ($id === currentUserId()) Response::error('نمی‌توانید وضعیت حساب خودتان را تغییر دهید');

    $user = $userQuery->findById($id);
    if (!$user) Response::notFound('کاربر یافت نشد');

    $userQuery->toggleActive($id);
    $newStatus = $user['is_active'] ? 'غیرفعال' : 'فعال';
    Response::success("کاربر {$newStatus} شد");
}

function actionDelete(): never {
    global $userQuery;
    $id = (int)post('id');

    if ($id <= 0) Response::error('شناسه نامعتبر است');
    if ($id === currentUserId()) Response::error('نمی‌توانید حساب خودتان را حذف کنید');

    $user = $userQuery->findById($id);
    if (!$user) Response::notFound('کاربر یافت نشد');

    // همیشه soft delete — تاریخچه حفظ می‌شه
    $userQuery->update($id, ['is_active' => 0]);
    Response::success('کاربر غیرفعال شد');
}