<?php

require_once '../config/session_config.php';
require_once '../controller/connection.php';
require_once '../helper/flash_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/dentist-change-requests');
    exit;
}

// CSRF validation
if (
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    setFlash('danger', 'Invalid security token.');
    header('Location: ../admin/dentist-change-requests');
    exit;
}

// Validate dentist change request ID
$requestId = filter_input(
    INPUT_POST,
    'request_id',
    FILTER_VALIDATE_INT
);

if (!$requestId || $requestId <= 0) {
    setFlash('danger', 'Invalid dentist change request.');
    header('Location: ../admin/dentist-change-requests');
    exit;
}

// Start transaction
$conn->begin_transaction();

try {

    // Get pending dentist change request
    $stmt = $conn->prepare("
        SELECT
            id,
            booking_id,
            current_dentist_id,
            requested_dentist_id,
            status
        FROM doctor_change_requests
        WHERE id = ?
          AND status = 'Pending Approval'
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param('i', $requestId);
    $stmt->execute();

    $result = $stmt->get_result();
    $request = $result->fetch_assoc();

    $stmt->close();

    if (!$request) {
        throw new Exception('Dentist change request not found or has already been processed.');
    }

    $bookingId = (int) $request['booking_id'];
    $currentDentistId = (int) $request['current_dentist_id'];
    $requestedDentistId = (int) $request['requested_dentist_id'];

    // Verify the booking still exists and has the expected dentist
    $stmt = $conn->prepare("
        SELECT id, dentist_id
        FROM bookings
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();

    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    $stmt->close();

    if (!$booking) {
        throw new Exception('Associated appointment was not found.');
    }

    // Make sure the booking has not already changed dentist
    if ((int) $booking['dentist_id'] !== $currentDentistId) {
        throw new Exception(
            'The appointment dentist has already changed. This request can no longer be approved.'
        );
    }

    // Update booking to requested dentist
    $stmt = $conn->prepare("
        UPDATE bookings
        SET dentist_id = ?
        WHERE id = ?
          AND dentist_id = ?
    ");

    $stmt->bind_param(
        'iii',
        $requestedDentistId,
        $bookingId,
        $currentDentistId
    );

    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        throw new Exception('The appointment dentist could not be changed.');
    }

    $stmt->close();

    // Remove the pending dentist change request
    $stmt = $conn->prepare("
        DELETE FROM doctor_change_requests
        WHERE id = ?
          AND status = 'Pending Approval'
    ");

    $stmt->bind_param('i', $requestId);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        $stmt->close();
        throw new Exception('The dentist change request could not be removed.');
    }

    $stmt->close();

    // Commit both changes
    $conn->commit();

    setFlash(
        'success',
        'Dentist change request approved successfully. The appointment has been assigned to the requested dentist.'
    );
} catch (Throwable $e) {

    // Roll back everything if any operation fails
    $conn->rollback();

    setFlash(
        'danger',
        $e->getMessage()
    );
}

header('Location: ../admin/dentist-change-requests');
exit;
