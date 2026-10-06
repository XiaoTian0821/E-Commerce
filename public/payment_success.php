<?php
/**
 * Payment Success — Capture PayPal payment and finalize order
 */
define('APP_START', true);
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/paypal.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$paypalToken = trim($_GET['token'] ?? '');
$orderRefId  = (int)($_GET['id'] ?? $_SESSION['paypal_target'] ?? 0);

if (!$paypalToken || !$orderRefId) {
    redirect('public/orders.php', 'Payment information missing.', 'danger');
}

// Verify order belongs to user
$orderStmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=? AND payment_status='pending'");
$orderStmt->execute([$orderRefId, $_SESSION['user_id']]);
$order = $orderStmt->fetch();
if (!$order) {
    redirect('public/orders.php', 'Order not found or already processed.', 'warning');
}

// ── Capture PayPal payment ──
function paypalCaptureOrder(string $token): ?array
{
    $ch = curl_init(PAYPAL_API_BASE . '/v2/checkout/orders/' . urlencode($token) . '/capture');
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => '{}',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . ($_SESSION['paypal_access_token'] ?? ''),
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    // Get fresh token
    $getToken = curl_init(PAYPAL_API_BASE . '/v1/oauth2/token');
    curl_setopt_array($getToken, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode(PAYPAL_CLIENT_ID . ':' . PAYPAL_CLIENT_SECRET),
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);
    $tokResp = curl_exec($getToken);
    curl_close($getToken);
    $tokData = json_decode($tokResp, true);
    $accessToken = $tokData['access_token'] ?? '';

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
        'PayPalRequestid: ' . bin2hex(random_bytes(16)),
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($http !== 201) return null;
    return json_decode($resp, true);
}

$captureResult = paypalCaptureOrder($paypalToken);

if (!$captureResult || $captureResult['status'] !== 'COMPLETED') {
    logMessage('error', 'PayPal capture failed for token ' . $paypalToken . ': ' . print_r($captureResult, true));
    redirect('public/payment_cancel.php', 'Payment could not be completed. Please contact support.', 'danger');
}

$txnId  = $captureResult['id'] ?? $paypalToken;
$amount = (float)($captureResult['purchase_units'][0]['payments']['captures'][0]['value'] ?? $order['total_amount']);

try {
    $pdo->beginTransaction();

    // Update payment record
    $updPay = $pdo->prepare("
        UPDATE payments SET status='completed', transaction_id=?, amount=?, updated_at=NOW()
        WHERE order_id=?
    ");
    $updPay->execute([$txnId, $amount, $orderRefId]);

    // Update order
    $updOrd = $pdo->prepare("
        UPDATE orders SET payment_status='paid', order_status='processing', updated_at=NOW()
        WHERE id=?
    ");
    $updOrd->execute([$orderRefId]);

    // Decrement stock
    $items = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id=?");
    $items->execute([$orderRefId]);
    foreach ($items->fetchAll() as $oi) {
        $dec = $pdo->prepare("UPDATE products SET stock=stock-? WHERE id=? AND stock>=?");
        $dec->execute([(int)$oi['quantity'], $oi['product_id'], (int)$oi['quantity']]);
    }

    $pdo->commit();
    unset($_SESSION['paypal_order_id'], $_SESSION['paypal_target'], $_SESSION['paypal_access_token']);

    redirect('public/payment_success.php?id=' . $orderRefId, 'Payment successful! Your order has been confirmed.', 'success');
} catch (PDOException $e) {
    $pdo?->rollBack();
    logMessage('error', 'Payment commit error: ' . $e->getMessage());
    redirect('public/orders.php', 'Payment recorded but there was an error. Please contact support.', 'danger');
}
?>
