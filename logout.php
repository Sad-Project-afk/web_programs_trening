<?php
require __DIR__ . '/storage.php';

$_SESSION = [];
session_destroy();

header('Location: login.php');
exit;
