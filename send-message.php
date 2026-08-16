<?php
// ======================================
// PJU JEPARA - WA NOTIFICATION SERVER
// Thorik123 - Skripsi 2024
// ======================================

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$number = $_GET['number'] ?? '';
$message = urldecode($_GET['message'] ?? '');

if (empty($number) || empty($message)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing number or message'
    ]);
    exit;
}

// Clean number
$number = str_replace(['+', ' ', '-', '.'], '', $number);
if (!preg_match('/^62\d{10,13}$/', $number)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Invalid number format'
    ]);
    exit;
}

// WhatsApp Web
$waUrl = "https://wa.me/{$number}?text=" . urlencode($message);

echo json_encode([
    'status' => 'success',
    'whatsapp_url' => $waUrl,
    'number' => $number,
    'message_preview' => substr($message, 0, 50) . '...'
]);
?>