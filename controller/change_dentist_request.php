<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../helper/flash_helper.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/dentist_change_email.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| Redirect Helper
|--------------------------------------------------------------------------
*/

$redirectUrl = '../admin/dentist-change-requests';

/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$redirectUrl}");
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Validation
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
| Validate Input
|--------------------------------------------------------------------------
*/

$referenceNumber = $_POST['reference_number'] ?? '';

if (!is_string($referenceNumber)) {
    $referenceNumber = '';
}

$referenceNumber = trim($referenceNumber);

if ($referenceNumber === '') {
    setFlash('danger', 'Booking reference number is required.');
    header("Location: {$redirectUrl}");
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Booking
|--------------------------------------------------------------------------
*/

try {
    $stmt = $conn->prepare("
        SELECT
            id,
            reference_number,
            full_name,
            email,
            booking_date,
            booking_time,
            branch_id,
            dentist_id,
            status
        FROM bookings
        WHERE reference_number = ?
        LIMIT 1
    ");

    $stmt->bind_param('s', $referenceNumber);
    $stmt->execute();

    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    $stmt->close();
} catch (Throwable $e) {
    error_log('Load booking for dentist change approval failed: ' . $e->getMessage());

    setFlash(
        'danger',
        'Unable to process the dentist change request. Please try again.'
    );

    header("Location: {$redirectUrl}");
    exit;
}

if (!$booking) {
    setFlash('danger', 'Appointment could not be found.');
    header("Location: {$redirectUrl}");
    exit;
}

$bookingId = (int) $booking['id'];

/*
|--------------------------------------------------------------------------
| Validate Booking Status
|--------------------------------------------------------------------------
|
| Dentist changes can only be approved while the booking is Pending.
|
*/

if ((string) $booking['status'] !== 'Pending') {
    setFlash(
        'danger',
        'This dentist change request cannot be approved because the appointment is no longer pending.'
    );

    header("Location: {$redirectUrl}");
    exit;
}

/*
|--------------------------------------------------------------------------
| Load Pending Dentist Change Request
|--------------------------------------------------------------------------
*/

try {
    $stmt = $conn->prepare("
        SELECT
            id,
            current_dentist_id,
            requested_dentist_id,
            status
        FROM doctor_change_requests
        WHERE booking_id = ?
          AND status = 'Pending Approval'
        LIMIT 1
    ");

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();

    $result = $stmt->get_result();
    $changeRequest = $result->fetch_assoc();

    $stmt->close();
} catch (Throwable $e) {
    error_log('Load dentist change request failed: ' . $e->getMessage());

    setFlash(
        'danger',
        'Unable to process the dentist change request. Please try again.'
    );

    header("Location: {$redirectUrl}");
    exit;
}

if (!$changeRequest) {
    setFlash(
        'danger',
        'No pending dentist change request was found for this appointment.'
    );

    header("Location: {$redirectUrl}");
    exit;
}

$requestId = (int) $changeRequest['id'];
$currentDentistId = (int) $changeRequest['current_dentist_id'];
$requestedDentistId = (int) $changeRequest['requested_dentist_id'];

/*
|--------------------------------------------------------------------------
| Validate Requested Dentist
|--------------------------------------------------------------------------
*/

try {
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
} catch (Throwable $e) {
    error_log('Validate requested dentist failed: ' . $e->getMessage());

    setFlash(
        'danger',
        'Unable to validate the requested dentist. Please try again.'
    );

    header("Location: {$redirectUrl}");
    exit;
}

if (!$requestedDentist) {
    setFlash(
        'danger',
        'The requested dentist could not be found.'
    );

    header("Location: {$redirectUrl}");
    exit;
}

if ($requestedDentistId === $currentDentistId) {
    setFlash(
        'danger',
        'The requested dentist is already assigned to this appointment.'
    );

    header("Location: {$redirectUrl}");
    exit;
}

/*
|--------------------------------------------------------------------------
| Approve Dentist Change
|--------------------------------------------------------------------------
|
| 1. Begin transaction.
| 2. Lock and revalidate the booking and request.
| 3. Check the requested dentist's schedule.
| 4. Update the booking.
| 5. Delete the approved request.
| 6. Commit.
| 7. Send the patient email after commit.
|
*/

$transactionStarted = false;
$approvalSucceeded = false;
$emailSent = false;
$failureMessage = 'Unable to approve the dentist change request. Please try again.';

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    /*
     * Lock the booking and revalidate its current state.
     */

    $stmt = $conn->prepare("
        SELECT
            id,
            dentist_id,
            branch_id,
            booking_date,
            booking_time,
            status
        FROM bookings
        WHERE id = ?
          AND reference_number = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param(
        'is',
        $bookingId,
        $referenceNumber
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $lockedBooking = $result->fetch_assoc();

    $stmt->close();

    if (!$lockedBooking) {
        throw new DomainException(
            'The appointment could not be found or has changed.'
        );
    }

    if ((string) $lockedBooking['status'] !== 'Pending') {
        throw new DomainException(
            'This dentist change request cannot be approved because the appointment is no longer pending.'
        );
    }

    if ((int) $lockedBooking['dentist_id'] !== $currentDentistId) {
        throw new DomainException(
            'The dentist change request no longer matches the current appointment.'
        );
    }

    /*
     * Lock and revalidate the pending request.
     */

    $stmt = $conn->prepare("
        SELECT id
        FROM doctor_change_requests
        WHERE id = ?
          AND booking_id = ?
          AND current_dentist_id = ?
          AND requested_dentist_id = ?
          AND status = 'Pending Approval'
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param(
        'iiii',
        $requestId,
        $bookingId,
        $currentDentistId,
        $requestedDentistId
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $lockedRequest = $result->fetch_assoc();

    $stmt->close();

    if (!$lockedRequest) {
        throw new DomainException(
            'The dentist change request is no longer pending or has changed.'
        );
    }

    /*
     * Check for another active appointment at the same
     * dentist, branch, date, and time.
     *
     * Exclude the booking being changed.
     */

    $stmt = $conn->prepare("
        SELECT id
        FROM bookings
        WHERE dentist_id = ?
          AND branch_id = ?
          AND booking_date = ?
          AND booking_time = ?
          AND id <> ?
          AND status IN ('Pending', 'Approved', 'Rescheduled')
        LIMIT 1
    ");

    $stmt->bind_param(
        'iissi',
        $requestedDentistId,
        $lockedBooking['branch_id'],
        $lockedBooking['booking_date'],
        $lockedBooking['booking_time'],
        $bookingId
    );

    $stmt->execute();

    $conflictResult = $stmt->get_result();
    $hasConflict = $conflictResult->num_rows > 0;

    $stmt->close();

    if ($hasConflict) {
        throw new DomainException(
            'Cannot approve the dentist change. The requested dentist already has an appointment at this branch, date, and time.'
        );
    }

    /*
     * Update booking dentist.
     */

    $stmt = $conn->prepare("
        UPDATE bookings
        SET dentist_id = ?
        WHERE id = ?
          AND dentist_id = ?
          AND status = 'Pending'
    ");

    $stmt->bind_param(
        'iii',
        $requestedDentistId,
        $bookingId,
        $currentDentistId
    );

    $stmt->execute();

    $bookingAffectedRows = $stmt->affected_rows;

    $stmt->close();

    if ($bookingAffectedRows !== 1) {
        throw new DomainException(
            'The appointment could not be updated. Please refresh and try again.'
        );
    }

    /*
     * Delete the approved request.
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

    $requestAffectedRows = $stmt->affected_rows;

    $stmt->close();

    if ($requestAffectedRows !== 1) {
        throw new DomainException(
            'The dentist change request could not be removed. Please refresh and try again.'
        );
    }

    /*
     * Commit all database changes.
     */

    $conn->commit();
    $transactionStarted = false;
    $approvalSucceeded = true;
} catch (Throwable $e) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(
                'Dentist change approval rollback failed: '
                    . $rollbackException->getMessage()
            );
        }

        $transactionStarted = false;
    }

    error_log(
        'Dentist change approval failed: ' . $e->getMessage()
    );

    if ($e instanceof DomainException) {
        $failureMessage = $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Send Patient Email After Successful Commit
|--------------------------------------------------------------------------
*/

if ($approvalSucceeded) {
    try {
        $patientEmail = decryptEmail(
            (string) $booking['email']
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
                'reference' => (string) $booking['reference_number'],
                'patient' => (string) $booking['full_name'],
                'date' => $parsedDate
                    ? $parsedDate->format('F j, Y')
                    : $appointmentDate,
                'time' => $parsedTime
                    ? $parsedTime->format('g:i A')
                    : $appointmentTime,
                'dentist' => (string) $requestedDentist['dentist_name'],
            ];

            $emailSent = sendDentistChangeApprovalEmail(
                $patientEmail,
                $emailDetails
            );
        }
    } catch (Throwable $emailException) {
        error_log(
            'Dentist change approved, but patient email failed: '
                . $emailException->getMessage()
        );
    }

    if ($emailSent) {
        setFlash(
            'success',
            'Dentist change request approved successfully. The patient has been notified by email.'
        );
    } else {
        setFlash(
            'warning',
            'Dentist change request approved successfully, but the patient email notification could not be sent.'
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
