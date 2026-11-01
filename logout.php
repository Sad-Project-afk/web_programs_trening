<?php
require __DIR__ . '/storage.php';

setcookie('fitlog_remember', '', ['expires' => time() - 3600, 'path' => '/']);
setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => '/']);
$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
