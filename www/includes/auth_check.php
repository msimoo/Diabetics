<?php
/**
 * Auth Check - Include this at the top of every protected page
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/helpers.php';

require_login();
?>
