<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/connection.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);

    exit;
}

$referenceNumber = trim($_GET['reference_number'] ?? '');

if ($referenceNumber === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Reference number is required.'
    ]);

    exit;
}

$stmt = $conn->prepare("
    SELECT
        b.id AS booking_id,
        b.dentist_id,
        d.dentist_name
    FROM bookings b
    LEFT JOIN dentists d
        ON d.id = b.dentist_id
    WHERE b.reference_number = ?
    LIMIT 1
");

$stmt->bind_param(
    's',
    $referenceNumber
);

$stmt->execute();

$result = $stmt->get_result();
$booking = $result->fetch_assoc();

$stmt->close();

if (!$booking) {
    echo json_encode([
        'success' => false,
        'message' => 'Appointment could not be found.'
    ]);

    exit;
}

$bookingId = (int) $booking['booking_id'];

$stmt = $conn->prepare("
    SELECT
        id,
        status
    FROM doctor_change_requests
    WHERE booking_id = ?
      AND status = 'Pending Approval'
    LIMIT 1
");

$stmt->bind_param(
    'i',
    $bookingId
);

$stmt->execute();

$result = $stmt->get_result();
$request = $result->fetch_assoc();

$stmt->close();

echo json_encode([
    'success' => true,
    'has_request' => (bool) $request,
    'request_status' => $request['status'] ?? null
]);

exit;
