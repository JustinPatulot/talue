<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../helper/flash_helper.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/dentist_change_email.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$redirectUrl = '../admin/dentist-change-requests/';

/*
|--------------------------------------------------------------------------
| Only Allow POST Requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    header("Location: {$redirectUrl}");
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
*/

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrfToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals((string) $_SESSION['csrf_token'], $csrfToken)
) {
    setFlash(
        'danger',
        'Your session has expired. Please refresh the page and try again.'
    );

    header("Location: {$redirectUrl}");
    exit;
}

/*
|--------------------------------------------------------------------------
| Request Values
|--------------------------------------------------------------------------
*/

$referenceNumber = $_POST['reference_number'] ?? '';
$reason = $_POST['reason'] ?? '';

$referenceNumber = is_string($referenceNumber)
    ? trim($referenceNumber)
    : '';

$reason = is_string($reason)
    ? trim($reason)
    : '';

/*
|--------------------------------------------------------------------------
| Validate Input
|--------------------------------------------------------------------------
*/

if ($referenceNumber === '') {
    setFlash('danger', 'Booking reference number is required.');
    header("Location: {$redirectUrl}");
    exit;
}

if ($reason === '') {
    setFlash('danger', 'A reason for rejection is required.');
    header("Location: {$redirectUrl}");
    exit;
}

if (mb_strlen($reason) > 1000) {
    setFlash(
        'danger',
        'The rejection reason must not exceed 1000 characters.'
    );

    header("Location: {$redirectUrl}");
    exit;
}

/*
|--------------------------------------------------------------------------
| Initialize Transaction State
|--------------------------------------------------------------------------
*/

$transactionStarted = false;
$rejectionSucceeded = false;
$emailSent = false;

$failureMessage = 'Unable to reject the dentist change request. Please try again.';

$booking = null;
$lockedBooking = null;
$request = null;
$requestedDentist = null;

try {
    /*
    |--------------------------------------------------------------------------
    | Begin Transaction
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();
    $transactionStarted = true;

    /*
    |--------------------------------------------------------------------------
    | Find and Lock Booking
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            reference_number,
            full_name,
            email,
            booking_date,
            booking_time,
            dentist_id,
            status
        FROM bookings
        WHERE reference_number = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param('s', $referenceNumber);
    $stmt->execute();

    $result = $stmt->get_result();
    $lockedBooking = $result->fetch_assoc();

    $stmt->close();

    if (!$lockedBooking) {
        throw new DomainException(
            'The appointment could not be found.'
        );
    }

    $bookingId = (int) $lockedBooking['id'];

    /*
    |--------------------------------------------------------------------------
    | Validate Booking Status
    |--------------------------------------------------------------------------
    */

    if ((string) $lockedBooking['status'] !== 'Pending') {
        throw new DomainException(
            'This dentist change request cannot be rejected because the appointment is no longer pending.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Find and Lock Pending Dentist Change Request
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            current_dentist_id,
            requested_dentist_id,
            reason,
            status
        FROM doctor_change_requests
        WHERE booking_id = ?
          AND status = 'Pending Approval'
        ORDER BY id DESC
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();

    $result = $stmt->get_result();
    $request = $result->fetch_assoc();

    $stmt->close();

    if (!$request) {
        throw new DomainException(
            'There is no pending dentist change request to reject.'
        );
    }

    $requestId = (int) $request['id'];
    $currentDentistId = (int) $request['current_dentist_id'];
    $requestedDentistId = (int) $request['requested_dentist_id'];

    /*
    |--------------------------------------------------------------------------
    | Verify Request Matches Current Booking
    |--------------------------------------------------------------------------
    */

    if ((int) $lockedBooking['dentist_id'] !== $currentDentistId) {
        throw new DomainException(
            'The dentist change request no longer matches the current appointment.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get Requested Dentist Name Before Deletion
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            id,
            dentist_name
        FROM dentists
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $requestedDentistId);
    $stmt->execute();

    $result = $stmt->get_result();
    $requestedDentist = $result->fetch_assoc();

    $stmt->close();

    if (!$requestedDentist) {
        throw new DomainException(
            'The requested dentist could not be found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Rejected Request
    |--------------------------------------------------------------------------
    |
    | The rejection reason comes from the administrator's submitted
    | reason field. The request is deleted only after validation.
    |
    */

    $stmt = $conn->prepare("
        DELETE FROM doctor_change_requests
        WHERE id = ?
          AND booking_id = ?
          AND status = 'Pending Approval'
    ");

    $stmt->bind_param(
        'ii',
        $requestId,
        $bookingId
    );

    $stmt->execute();

    $affectedRows = $stmt->affected_rows;

    $stmt->close();

    if ($affectedRows !== 1) {
        throw new DomainException(
            'The dentist change request could not be rejected. It may have already been processed.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    |
    | Email is sent only after the database transaction commits.
    |
    */

    $conn->commit();
    $transactionStarted = false;
    $rejectionSucceeded = true;
} catch (Throwable $e) {
    /*
    |--------------------------------------------------------------------------
    | Roll Back on Failure
    |--------------------------------------------------------------------------
    */

    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(
                'Dentist change rejection rollback failed: '
                    . $rollbackException->getMessage()
            );
        }

        $transactionStarted = false;
    }

    error_log(
        'Dentist change rejection failed: '
            . $e->getMessage()
    );

    if ($e instanceof DomainException) {
        $failureMessage = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Send Rejection Email After Successful Commit
|--------------------------------------------------------------------------
*/

if ($rejectionSucceeded && $lockedBooking && $requestedDentist) {
    try {
        $patientEmail = decryptEmail(
            (string) $lockedBooking['email']
        );

        if (
            is_string($patientEmail) &&
            filter_var($patientEmail, FILTER_VALIDATE_EMAIL)
        ) {
            $appointmentDate = (string) $lockedBooking['booking_date'];
            $appointmentTime = (string) $lockedBooking['booking_time'];

            $parsedDate = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                $appointmentDate
            );

            $parsedTime = DateTimeImmutable::createFromFormat(
                '!H:i:s',
                $appointmentTime
            );

            if (!$parsedTime) {
                $parsedTime = DateTimeImmutable::createFromFormat(
                    '!H:i',
                    $appointmentTime
                );
            }

            $emailDetails = [
                'reference' => (string) $lockedBooking['reference_number'],
                'patient' => (string) $lockedBooking['full_name'],
                'date' => $parsedDate
                    ? $parsedDate->format('F j, Y')
                    : $appointmentDate,
                'time' => $parsedTime
                    ? $parsedTime->format('g:i A')
                    : $appointmentTime,
                'current_dentist' => (string) $currentDentistId,
                'dentist' => (string) $requestedDentist['dentist_name'],
                'reason' => $reason,
            ];

            $emailSent = sendDentistChangeRejectionEmail(
                $patientEmail,
                $emailDetails
            );
        }
    } catch (Throwable $emailException) {
        error_log(
            'Dentist change rejected, but patient email failed: '
                . $emailException->getMessage()
        );
    }

    if ($emailSent) {
        setFlash(
            'success',
            'Dentist change request rejected successfully. The patient has been notified by email.'
        );
    } else {
        setFlash(
            'warning',
            'Dentist change request rejected successfully, but the patient email notification could not be sent.'
        );
    }
} else {
    setFlash('danger', $failureMessage);
}

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header("Location: {$redirectUrl}");
exit;
