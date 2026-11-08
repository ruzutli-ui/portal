<?php
require __DIR__ . '/_init.php';
unset($_SESSION['admin']);
redirect('login.php');
