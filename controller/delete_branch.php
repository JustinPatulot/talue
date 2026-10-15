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

function redirectToBranches(): never
{
    header('Location: ../admin/branches/');
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

    redirectToBranches();
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

    redirectToBranches();
}

/*
 * ---------------------------------------------------------
 * Retrieve branch ID
 * ---------------------------------------------------------
 */

$branchId = filter_input(
    INPUT_POST,
    'branch_id',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'min_range' => 1
        ]
    ]
);

if ($branchId === false || $branchId === null) {
    setFlash(
        'danger',
        'Invalid branch ID.'
    );

    redirectToBranches();
}

$branchId = (int) $branchId;

/*
 * ---------------------------------------------------------
 * Begin transaction
 * ---------------------------------------------------------
 */

if (!$conn->begin_transaction()) {
    error_log(
        'Delete branch transaction start failed: ' .
            $conn->error
    );

    setFlash(
        'danger',
        'Unable to process branch deletion. Please try again.'
    );

    redirectToBranches();
}

try {

    /*
     * -----------------------------------------------------
     * Verify branch exists and lock its record
     * -----------------------------------------------------
     */

    $checkSql = "
        SELECT id, branch_name
        FROM branches
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ";

    $checkStmt = $conn->prepare($checkSql);

    if (!$checkStmt) {
        throw new RuntimeException(
            'Branch existence check prepare failed: ' .
                $conn->error
        );
    }

    $checkStmt->bind_param('i', $branchId);

    if (!$checkStmt->execute()) {
        $error = $checkStmt->error;
        $checkStmt->close();

        throw new RuntimeException(
            'Branch existence check failed: ' . $error
        );
    }

    $result = $checkStmt->get_result();
    $branch = $result->fetch_assoc();

    $checkStmt->close();

    if (!$branch) {
        $conn->rollback();

        setFlash(
            'warning',
            'Branch not found.'
        );

        redirectToBranches();
    }

    /*
     * -----------------------------------------------------
     * Reject deletion if any booking references this branch
     *
     * All booking statuses are checked to preserve history.
     * -----------------------------------------------------
     */

    $bookingSql = "
        SELECT id
        FROM bookings
        WHERE branch_id = ?
        LIMIT 1
    ";

    $bookingStmt = $conn->prepare($bookingSql);

    if (!$bookingStmt) {
        throw new RuntimeException(
            'Branch booking validation prepare failed: ' .
                $conn->error
        );
    }

    $bookingStmt->bind_param('i', $branchId);

    if (!$bookingStmt->execute()) {
        $error = $bookingStmt->error;
        $bookingStmt->close();

        throw new RuntimeException(
            'Branch booking validation failed: ' . $error
        );
    }

    $bookingResult = $bookingStmt->get_result();
    $hasBookings = $bookingResult->num_rows > 0;

    $bookingStmt->close();

    if ($hasBookings) {
        $conn->rollback();

        setFlash(
            'danger',
            'This branch cannot be deleted because it is assigned to one or more bookings. Resolve the related appointments before removing this branch.'
        );

        redirectToBranches();
    }

    /*
     * -----------------------------------------------------
     * Delete branch
     * -----------------------------------------------------
     */

    $deleteSql = "
        DELETE FROM branches
        WHERE id = ?
    ";

    $deleteStmt = $conn->prepare($deleteSql);

    if (!$deleteStmt) {
        throw new RuntimeException(
            'Delete branch prepare failed: ' .
                $conn->error
        );
    }

    $deleteStmt->bind_param('i', $branchId);

    if (!$deleteStmt->execute()) {
        $error = $deleteStmt->error;
        $deleteStmt->close();

        throw new RuntimeException(
            'Delete branch execution failed: ' . $error
        );
    }

    $affectedRows = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($affectedRows !== 1) {
        throw new RuntimeException(
            'Delete branch affected rows mismatch. Expected 1, got ' .
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
            'Delete branch transaction commit failed: ' .
                $conn->error
        );
    }
} catch (Throwable $e) {

    $conn->rollback();

    error_log(
        'Delete branch failed: ' . $e->getMessage()
    );

    setFlash(
        'danger',
        'Unable to delete branch. Please try again.'
    );

    redirectToBranches();
}

/*
 * ---------------------------------------------------------
 * Success
 * ---------------------------------------------------------
 */

setFlash(
    'success',
    'Branch "' . $branch['branch_name'] .
        '" was deleted successfully.'
);

redirectToBranches();
