<?php
/**
 * PayPal Payment Processing Page
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/paypal.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$orderId = (int)($_GET['order_id'] ?? $_SESSION['pending_order_id'] ?? 0);
if (!$orderId) { redirect('public/orders.php', 'No order found.', 'danger'); }

$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$orderStmt->execute([$orderId, $_SESSION['user_id']]);
$order = $orderStmt->fetch();
if (!$order) { redirect('public/orders.php', 'Order not found.', 'danger'); }
if ($order['payment_method'] !== 'paypal') { redirect('public/checkout.php', 'Not a PayPal order.', 'warning'); }
if ($order['payment_status'] === 'paid') { redirect('public/payment_success.php?id=' . $orderId, 'This order is already paid.', 'info'); }

// ── PayPal API calls ──────────────────────────────────
function paypalAccessToken(): ?string
{
    $ch = curl_init(PAYPAL_API_BASE . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode(PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 200 || !$resp) return null;
    $data = json_decode($resp, true);
    return $data['access_token'] ?? null;
}

function paypalCreateOrder(float $amount, string $currency): ?string
{
    $token = paypalAccessToken();
    if (!$token) return null;
    $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => json_encode([
            'intent'        => 'CAPTURE',
            'purchase_units' => [[
                'amount' => [
                    'value'         => number_format($amount, 2, '.', ''),
                    'currency_code' => $currency,
                ],
            ]],
            'application_context' => [
                'return_url' => APP_URL . '/public/payment_success.php',
                'cancel_url' => APP_URL . '/public/payment_cancel.php',
            ],
        ]),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'PayPalRequestid: ' . bin2hex(random_bytes(16)),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 201) return null;
    $data = json_decode($resp, true);
    return $data['id'] ?? null;
}

$paypalOrderId = paypalCreateOrder((float)$order['total_amount'], PAYPAL_CURRENCY);

if (!$paypalOrderId) {
    logMessage('error', 'PayPal order creation failed for order #' . $order['order_number']);
    redirect('public/checkout.php', 'Could not initiate PayPal payment. Please try again.', 'danger');
}

// Store PayPal order ID for later capture
$_SESSION['paypal_order_id']  = $paypalOrderId;
$_SESSION['paypal_target']    = $orderId;

// Update payment record with PayPal order ID
$pdo->prepare("UPDATE payments SET transaction_id=? WHERE order_id=?")
    ->execute([$paypalOrderId, $orderId]);

// Render PayPal SDK
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PayPal Payment — <?= e(APP_NAME) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { background: #f4f6f9; display: flex; align-items: center; min-height: 100vh; }
    .pay-card { max-width: 480px; margin: auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.1); padding: 2rem; }
    .pay-amount { font-size: 2rem; font-weight: 700; color: #003087; }
  </style>
</head>
<body>
  <div class="pay-card text-center">
    <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_35x26.jpg"
         alt="PayPal" style="height:30px;margin-bottom:1rem">
    <h4 class="fw-bold mb-1">Complete Your Payment</h4>
    <p class="text-muted">Order <strong><?= e($order['order_number']) ?></strong></p>
    <div class="pay-amount mb-4"><?= formatCurrency((float)$order['total_amount']) ?></div>
    <div id="paypal-button-container"></div>
    <div class="mt-3">
      <a href="<?= APP_URL ?>/public/checkout.php" class="small text-muted">
        <i class="fas fa-arrow-left me-1"></i>Back to Checkout
      </a>
    </div>
  </div>

  <script src="https://www.paypal.com/sdk/js?client-id=<?= urlencode(PAYPAL_CLIENT_ID) ?>&currency=<?= urlencode(PAYPAL_CURRENCY) ?>&intent=capture"></script>
  <script>
    paypal.Buttons({
      style: { layout: 'vertical', color:  'blue', shape: 'pill', label: 'paypal' },
      createOrder: function() {
        return '<?= $paypalOrderId ?>';
      },
      onApprove: function(data, actions) {
        var url = '<?= APP_URL ?>/public/payment_success.php?token=' + encodeURIComponent(data.orderID);
        window.location.href = url;
      },
      onError: function(err) {
        console.error('PayPal error:', err);
        window.location.href = '<?= APP_URL ?>/public/payment_cancel.php';
      },
      onCancel: function() {
        window.location.href = '<?= APP_URL ?>/public/payment_cancel.php';
      }
    }).render('#paypal-button-container');
  </script>
</body>
</html>
