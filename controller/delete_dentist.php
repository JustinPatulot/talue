<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../helper/flash_helper.php';

/*
 * ---------------------------------------------------------
 * Request method
 * ---------------------------------------------------------
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

/*
 * ---------------------------------------------------------
 * Redirect helper
 * ---------------------------------------------------------
 */

function redirectToDentists(): never
{
    header('Location: ../admin/dentists/');
    exit;
}

/*
 * ---------------------------------------------------------
 * CSRF validation
 * ---------------------------------------------------------
 */

$submittedCsrfToken = $_POST['csrf_token'] ?? '';
$sessionCsrfToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($submittedCsrfToken) ||
    !is_string($sessionCsrfToken) ||
    $sessionCsrfToken === '' ||
    !hash_equals($sessionCsrfToken, $submittedCsrfToken)
) {
    setFlash(
        'danger',
        'Invalid security token. Please try again.'
    );

    redirectToDentists();
}

/*
 * ---------------------------------------------------------
 * Retrieve dentist ID
 * ---------------------------------------------------------
 */

$dentistId = filter_input(
    INPUT_POST,
    'dentist_id',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($dentistId === false || $dentistId === null) {
    setFlash(
        'danger',
        'Invalid dentist selected.'
    );

    redirectToDentists();
}

$dentistId = (int) $dentistId;

/*
 * ---------------------------------------------------------
 * Prevent deleting the currently logged-in administrator
 * ---------------------------------------------------------
 */

$currentDentistId = (int) ($_SESSION['dentist_id'] ?? 0);

if ($dentistId === $currentDentistId) {
    setFlash(
        'danger',
        'You cannot remove your own administrator account.'
    );

    redirectToDentists();
}

/*
 * ---------------------------------------------------------
 * Begin transaction
 * ---------------------------------------------------------
 */

if (!$conn->begin_transaction()) {
    error_log(
        'Delete dentist transaction start failed: ' .
            $conn->error
    );

    setFlash(
        'danger',
        'Unable to process dentist removal. Please try again.'
    );

    redirectToDentists();
}

try {

    /*
     * -----------------------------------------------------
     * Lock and verify dentist exists
     * -----------------------------------------------------
     */

    $checkSql = "
        SELECT id
        FROM dentists
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ";

    $checkStmt = $conn->prepare($checkSql);

    if (!$checkStmt) {
        throw new RuntimeException(
            'Dentist existence check prepare failed: ' .
                $conn->error
        );
    }

    $checkStmt->bind_param('i', $dentistId);

    if (!$checkStmt->execute()) {
        $error = $checkStmt->error;
        $checkStmt->close();

        throw new RuntimeException(
            'Dentist existence check failed: ' . $error
        );
    }

    $checkResult = $checkStmt->get_result();
    $dentistExists = $checkResult->num_rows === 1;

    $checkStmt->close();

    if (!$dentistExists) {
        $conn->rollback();

        setFlash(
            'danger',
            'Dentist not found.'
        );

        redirectToDentists();
    }

    /*
     * -----------------------------------------------------
     * Reject deletion if assigned to any booking
     *
     * All statuses are checked to preserve booking history
     * and prevent orphaned appointment records.
     * -----------------------------------------------------
     */

    $bookingSql = "
        SELECT id
        FROM bookings
        WHERE dentist_id = ?
        LIMIT 1
    ";

    $bookingStmt = $conn->prepare($bookingSql);

    if (!$bookingStmt) {
        throw new RuntimeException(
            'Booking validation prepare failed: ' .
                $conn->error
        );
    }

    $bookingStmt->bind_param('i', $dentistId);

    if (!$bookingStmt->execute()) {
        $error = $bookingStmt->error;
        $bookingStmt->close();

        throw new RuntimeException(
            'Booking validation failed: ' . $error
        );
    }

    $bookingResult = $bookingStmt->get_result();
    $hasBookings = $bookingResult->num_rows > 0;

    $bookingStmt->close();

    if ($hasBookings) {
        $conn->rollback();

        setFlash(
            'danger',
            'This dentist cannot be deleted because they are assigned to one or more bookings. Reassign or resolve the bookings before removing this dentist.'
        );

        redirectToDentists();
    }

    /*
     * -----------------------------------------------------
     * Reject deletion if dentist is requested in a pending
     * dentist change request.
     * -----------------------------------------------------
     */

    $requestSql = "
        SELECT id
        FROM doctor_change_requests
        WHERE requested_dentist_id = ?
          AND status = 'Pending Approval'
        LIMIT 1
    ";

    $requestStmt = $conn->prepare($requestSql);

    if (!$requestStmt) {
        throw new RuntimeException(
            'Dentist change request validation prepare failed: ' .
                $conn->error
        );
    }

    $requestStmt->bind_param('i', $dentistId);

    if (!$requestStmt->execute()) {
        $error = $requestStmt->error;
        $requestStmt->close();

        throw new RuntimeException(
            'Dentist change request validation failed: ' .
                $error
        );
    }

    $requestResult = $requestStmt->get_result();
    $hasPendingRequests = $requestResult->num_rows > 0;

    $requestStmt->close();

    if ($hasPendingRequests) {
        $conn->rollback();

        setFlash(
            'danger',
            'This dentist cannot be deleted because there is a pending dentist change request assigned to them. Resolve the request before removing this dentist.'
        );

        redirectToDentists();
    }

    /*
     * -----------------------------------------------------
     * Delete dentist
     * -----------------------------------------------------
     */

    $deleteSql = "
        DELETE FROM dentists
        WHERE id = ?
    ";

    $deleteStmt = $conn->prepare($deleteSql);

    if (!$deleteStmt) {
        throw new RuntimeException(
            'Delete dentist prepare failed: ' .
                $conn->error
        );
    }

    $deleteStmt->bind_param('i', $dentistId);

    if (!$deleteStmt->execute()) {
        $error = $deleteStmt->error;
        $deleteStmt->close();

        throw new RuntimeException(
            'Delete dentist execution failed: ' . $error
        );
    }

    $affectedRows = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($affectedRows !== 1) {
        throw new RuntimeException(
            'Delete dentist affected rows mismatch. Expected 1, got ' .
                $affectedRows
        );
    }

    /*
     * -----------------------------------------------------
     * Commit transaction
     * -----------------------------------------------------
     */

    if (!$conn->commit()) {
        throw new RuntimeException(
            'Delete dentist transaction commit failed: ' .
                $conn->error
        );
    }
} catch (Throwable $e) {

    $conn->rollback();

    error_log(
        'Delete dentist failed: ' . $e->getMessage()
    );

    setFlash(
        'danger',
        'Unable to remove dentist. Please resolve any related records and try again.'
    );

    redirectToDentists();
}

/*
 * ---------------------------------------------------------
 * Success
 * ---------------------------------------------------------
 */

setFlash(
    'success',
    'Dentist removed successfully.'
);

redirectToDentists();
