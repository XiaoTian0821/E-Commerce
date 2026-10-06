<?php
/**
 * PayPal Sandbox Configuration
 * 
 * Obtain sandbox credentials at https://developer.paypal.com/
 * Never commit real credentials to version control.
 */
define('PAYPAL_MODE',       'sandbox');           // 'sandbox' | 'live'
define('PAYPAL_CLIENT_ID',  'AaBbCcDdEeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZz');
define('PAYPAL_CLIENT_SECRET', 'EeFfGgHhIiJjKkLlMmNnOoPpQqRrSsTtUuVvWwXxYyZzAaBbCcDd');
define('PAYPAL_CURRENCY',   'USD');
define('PAYPAL_API_BASE',   'https://api.sandbox.paypal.com');
