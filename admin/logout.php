<?php
require dirname(__DIR__) . '/config.php';
session_destroy();
header('Location: ' . url('admin/login.php'));
