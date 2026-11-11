<?php
require_once __DIR__ . '/includes/helpers.php';

require_post();
$_SESSION = [];
session_destroy();
session_start();
flash('已登出。');
redirect('login.php');
