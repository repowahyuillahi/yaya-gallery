<?php
require_once __DIR__ . '/app/config.php';

session_destroy();
redirect(u('login.php'));
