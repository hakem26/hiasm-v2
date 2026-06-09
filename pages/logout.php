<?php
define('HIASM_ENTRY', true);
require_once __DIR__ . '/../core/init.php';
require_once BASE_PATH . '/core/middleware.php';

logout();
redirect(BASE_URL . '/pages/login.php');