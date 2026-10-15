<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../helper/flash_helper.php';

/*
 * ---------------------------------------------------------
 * Redirect helper
 * ---------------------------------------------------------
 */

function redirectToServices(): never
{
    header('Location: ../admin/services/');
    exit;
}

/*
 * ---------------------------------------------------------
 * Request method
 * ---------------------------------------------------------
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    setFlash(
        'danger',
        'Invalid request method.'
    );

    redirectToServices();
}

/*
 * ---------------------------------------------------------
 * CSRF validation
 * ---------------------------------------------------------
 */

$csrfToken = $_POST['csrf_token'] ?? '';
$sessionCsrfToken = $_SESSION['csrf_token'] ?? '';

if (
    !is_string($csrfToken) ||
    !is_string($sessionCsrfToken) ||
    $sessionCsrfToken === '' ||
    !hash_equals($sessionCsrfToken, $csrfToken)
) {
    setFlash(
        'danger',
        'Invalid security token. Please try again.'
    );

    redirectToServices();
}

/*
 * ---------------------------------------------------------
 * Retrieve service ID
 * ---------------------------------------------------------
 */

$serviceId = filter_input(
    INPUT_POST,
    'service_id',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($serviceId === false || $serviceId === null) {
    setFlash(
        'danger',
        'Invalid dental service ID.'
    );

    redirectToServices();
}

$serviceId = (int) $serviceId;

/*
 * ---------------------------------------------------------
 * Begin transaction
 * ---------------------------------------------------------
 */

if (!$conn->begin_transaction()) {
    error_log(
        'Delete dental service transaction start failed: ' .
            $conn->error
    );

    setFlash(
        'danger',
        'Unable to process dental service deletion. Please try again.'
    );

    redirectToServices();
}

try {

    /*
     * -----------------------------------------------------
     * Verify service exists and lock its record
     * -----------------------------------------------------
     */

    $checkSql = "
        SELECT id, dental_service
        FROM dental_services
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ";

    $checkStmt = $conn->prepare($checkSql);

    if (!$checkStmt) {
        throw new RuntimeException(
            'Dental service existence check prepare failed: ' .
                $conn->error
        );
    }

    $checkStmt->bind_param('i', $serviceId);

    if (!$checkStmt->execute()) {
        $error = $checkStmt->error;
        $checkStmt->close();

        throw new RuntimeException(
            'Dental service existence check failed: ' . $error
        );
    }

    $result = $checkStmt->get_result();
    $service = $result->fetch_assoc();

    $checkStmt->close();

    if (!$service) {
        $conn->rollback();

        setFlash(
            'warning',
            'Dental service not found.'
        );

        redirectToServices();
    }

    /*
     * -----------------------------------------------------
     * Reject deletion if any booking references this service
     *
     * Check all statuses to preserve appointment history.
     * -----------------------------------------------------
     */

    $bookingSql = "
        SELECT id
        FROM bookings
        WHERE dent_service_id = ?
        LIMIT 1
    ";

    $bookingStmt = $conn->prepare($bookingSql);

    if (!$bookingStmt) {
        throw new RuntimeException(
            'Service booking validation prepare failed: ' .
                $conn->error
        );
    }

    $bookingStmt->bind_param('i', $serviceId);

    if (!$bookingStmt->execute()) {
        $error = $bookingStmt->error;
        $bookingStmt->close();

        throw new RuntimeException(
            'Service booking validation failed: ' . $error
        );
    }

    $bookingResult = $bookingStmt->get_result();
    $hasBookings = $bookingResult->num_rows > 0;

    $bookingStmt->close();

    if ($hasBookings) {
        $conn->rollback();

        setFlash(
            'danger',
            'This dental service cannot be deleted because it is assigned to one or more bookings. Resolve the related appointments before removing this service.'
        );

        redirectToServices();
    }

    /*
     * -----------------------------------------------------
     * Delete dental service
     * -----------------------------------------------------
     */

    $deleteSql = "
        DELETE FROM dental_services
        WHERE id = ?
    ";

    $deleteStmt = $conn->prepare($deleteSql);

    if (!$deleteStmt) {
        throw new RuntimeException(
            'Delete dental service prepare failed: ' .
                $conn->error
        );
    }

    $deleteStmt->bind_param('i', $serviceId);

    if (!$deleteStmt->execute()) {
        $error = $deleteStmt->error;
        $deleteStmt->close();

        throw new RuntimeException(
            'Delete dental service execution failed: ' . $error
        );
    }

    $affectedRows = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($affectedRows !== 1) {
        throw new RuntimeException(
            'Delete dental service affected rows mismatch. Expected 1, got ' .
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
            'Delete dental service transaction commit failed: ' .
                $conn->error
        );
    }
} catch (Throwable $e) {

    $conn->rollback();

    error_log(
        'Delete dental service failed: ' . $e->getMessage()
    );

    setFlash(
        'danger',
        'Unable to delete dental service. Please try again.'
    );

    redirectToServices();
}

/*
 * ---------------------------------------------------------
 * Success
 * ---------------------------------------------------------
 */

setFlash(
    'success',
    'Dental service "' . $service['dental_service'] .
        '" was deleted successfully.'
);

redirectToServices();
