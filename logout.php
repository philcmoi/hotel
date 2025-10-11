<?php
require_once 'config.php';
require_once 'auth.php';

$auth = new Auth();
$auth->logout();
redirect('login.php');
?>