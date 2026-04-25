<?php
require_once __DIR__ . '/config/bootstrap.php';
Auth::logout();
redirect('login.php');
