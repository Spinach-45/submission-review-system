<?php
require_once __DIR__ . '/includes/helpers.php';

$user = current_user();
redirect($user ? home_path($user) : 'login.php');
