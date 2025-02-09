<?php
require_once '../includes/config.php';
check_auth(['admin']);

// Ana dashboard'a yönlendir
header('Location: dashboard.php');
exit;
?>