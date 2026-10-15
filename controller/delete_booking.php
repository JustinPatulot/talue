<?php

declare(strict_types=1);

require_once '../config/session_config.php';
require_once '../controller/connection.php';
require_once '../helper/flash_helper.php';

// ---------------------------------------------------------
// 1. Allow POST requests only
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/bookings');
    exit;
}

// ---------------------------------------------------------
// 2. CSRF validation
// ---------------------------------------------------------

if (
    !isset($_POST['csrf_token']) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    setFlash('danger', 'Invalid security token.');
    header('Location: ../admin/bookings');
    exit;
}

// ---------------------------------------------------------
// 3. Validate booking ID
// ---------------------------------------------------------

$bookingId = filter_input(
    INPUT_POST,
    'booking_id',
    FILTER_VALIDATE_INT
);

if (!$bookingId || $bookingId <= 0) {
    setFlash('danger', 'Invalid booking.');
    header('Location: ../admin/bookings');
    exit;
}

// ---------------------------------------------------------
// 4. Check if booking exists
// ---------------------------------------------------------

$stmt = $conn->prepare("
    SELECT id
    FROM bookings
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param('i', $bookingId);
$stmt->execute();

$result = $stmt->get_result();
$booking = $result->fetch_assoc();

$stmt->close();

if (!$booking) {
    setFlash('danger', 'Booking not found.');
    header('Location: ../admin/bookings');
    exit;
}

// ---------------------------------------------------------
// 5. Delete booking
// ---------------------------------------------------------

$stmt = $conn->prepare("
    DELETE FROM bookings
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param('i', $bookingId);
$stmt->execute();

// ---------------------------------------------------------
// 6. Check deletion result
// ---------------------------------------------------------

if ($stmt->affected_rows === 1) {

    setFlash(
        'success',
        'Appointment deleted successfully.'
    );
} else {

    setFlash(
        'danger',
        'The appointment could not be deleted.'
    );
}

$stmt->close();

// ---------------------------------------------------------
// 7. Redirect back to bookings
// ---------------------------------------------------------

header('Location: ../admin/bookings');
exit;
