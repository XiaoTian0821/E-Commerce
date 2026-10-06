<?php
/**
 * Email / SMTP Configuration
 * 
 * Leave empty to disable SMTP (application still works).
 * Examples: Gmail, Outlook, generic SMTP.
 */
define('SMTP_ENABLED', false);

define('SMTP_HOST',      '');
define('SMTP_PORT',      587);
define('SMTP_USERNAME',  '');
define('SMTP_PASSWORD',  '');
define('SMTP_ENCRYPTION','tls');          // 'tls' | 'ssl'
define('SMTP_FROM_EMAIL','noreply@novamart.local');
define('SMTP_FROM_NAME', APP_NAME . ' Notifications');
