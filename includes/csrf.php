<?php
/**
 * CSRF protection – standalone file for environments where functions.php
 * may not yet be loaded.
 */
defined('APP_START') or die();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
