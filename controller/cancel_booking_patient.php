<?php

declare(strict_types=1);

require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/mail_config.php';

// ---------------------------------------------------------
// 1. Configure JSON response
// ---------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---------------------------------------------------------
// 2. JSON response helper
// ---------------------------------------------------------

function sendJsonResponse(
    bool $success,
    string $message,
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

// ---------------------------------------------------------
// 3. HTML escaping helper
// ---------------------------------------------------------

function escapeEmailHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

// ---------------------------------------------------------
// 4. Notify administrators and dental assistants
// ---------------------------------------------------------

// ---------------------------------------------------------
// Notify administrators and dental assistants in-app
// ---------------------------------------------------------

function createStaffCancellationNotifications(
    mysqli $conn,
    array $booking,
    string $cancellationReason
): void {
    $bookingId = (int) $booking['id'];
    $referenceNumber = (string) $booking['reference_number'];
    $patientName = (string) $booking['full_name'];

    // Find administrators and dental assistants.
    $stmt = $conn->prepare("
        SELECT id
        FROM dentists
        WHERE role_id IN (1, 2)
    ");

    $stmt->execute();

    $result = $stmt->get_result();
    $recipientIds = [];

    while ($staff = $result->fetch_assoc()) {
        $recipientIds[] = (int) $staff['id'];
    }

    $stmt->close();

    if (empty($recipientIds)) {
        error_log(
            'In-app cancellation notification: no eligible staff accounts found.'
        );

        return;
    }

    $notificationType = 'booking_cancelled';
    $title = 'Appointment Cancelled';

    $message = sprintf(
        'Patient %s cancelled appointment %s. Reason: %s',
        $patientName,
        $referenceNumber,
        $cancellationReason
    );

    $stmt = $conn->prepare("
        INSERT INTO notifications (
            recipient_id,
            booking_id,
            doctor_change_request_id,
            notification_type,
            title,
            message,
            is_read
        ) VALUES (
            ?, ?, NULL, ?, ?, ?, 0
        )
    ");

    foreach ($recipientIds as $recipientId) {
        $stmt->bind_param(
            'iisss',
            $recipientId,
            $bookingId,
            $notificationType,
            $title,
            $message
        );

        $stmt->execute();
    }

    $stmt->close();
}

function notifyStaffOfCancellation(
    mysqli $conn,
    array $booking,
    string $cancellationReason,
    string $mailFromAddress,
    string $mailFromName
): void {
    // Retrieve administrator and dental assistant accounts.
    $stmt = mysqli_prepare(
        $conn,
        "
        SELECT id, email, role_id
        FROM dentists
        WHERE role_id IN (1, 2)
          AND email IS NOT NULL
          AND TRIM(email) <> ''
        "
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $staffAccounts = [];

    while ($staff = mysqli_fetch_assoc($result)) {
        $staffAccounts[] = $staff;
    }

    mysqli_stmt_close($stmt);

    if (empty($staffAccounts)) {
        error_log(
            'Patient cancellation notification: no admin or dental assistant accounts found.'
        );

        return;
    }


    // Prepare booking details.
    $referenceNumber = (string) $booking['reference_number'];
    $patientName = (string) $booking['full_name'];
    $bookingDate = (string) $booking['booking_date'];

    // Convert the database time to 12-hour format with AM/PM.
    $rawBookingTime = (string) $booking['booking_time'];

    $timestamp = strtotime($rawBookingTime);

    $bookingTime = $timestamp !== false
        ? date('g:i A', $timestamp)
        : $rawBookingTime;


    $subject = 'Patient Appointment Cancelled - ' . $referenceNumber;

    // Escape dynamic values before inserting them into HTML.
    $safeReference = escapeEmailHtml($referenceNumber);
    $safePatientName = escapeEmailHtml($patientName);
    $safeBookingDate = escapeEmailHtml($bookingDate);
    $safeBookingTime = escapeEmailHtml($bookingTime);
    $safeReason = nl2br(escapeEmailHtml($cancellationReason));

    // -----------------------------------------------------
    // 4.1 HTML email
    // -----------------------------------------------------

    $htmlBody = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Appointment Cancellation</title>
    </head>
    <body style="margin:0;padding:24px;background:#f4f6f9;
                 font-family:Arial,Helvetica,sans-serif;color:#263238;">

        <div style="max-width:620px;margin:0 auto;background:#ffffff;
                    border:1px solid #e0e5eb;border-radius:10px;
                    overflow:hidden;">

            <div style="padding:24px;background:#fce8e6;
                        border-bottom:1px solid #f3cfcb;">
                <h2 style="margin:0;color:#b42318;font-size:22px;">
                    Appointment Cancelled
                </h2>

                <p style="margin:10px 0 0;color:#5f3532;font-size:14px;">
                    A patient has cancelled an appointment through
                    the Talaue 88 Dental Clinic website.
                </p>
            </div>

            <div style="padding:24px;">
                <p style="margin-top:0;">Hello,</p>

                <p>
                    This is an automated notification that a patient
                    has cancelled an appointment. Please review the
                    details below.
                </p>

                <table role="presentation"
                       style="width:100%;border-collapse:collapse;
                              font-size:14px;">

                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;
                                   font-weight:bold;width:40%;">
                            Reference Number
                        </td>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;">
                            ' . $safeReference . '
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;
                                   font-weight:bold;">
                            Patient Name
                        </td>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;">
                            ' . $safePatientName . '
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;
                                   font-weight:bold;">
                            Original Appointment Date
                        </td>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;">
                            ' . $safeBookingDate . '
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;
                                   font-weight:bold;">
                            Original Appointment Time
                        </td>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;">
                            ' . $safeBookingTime . '
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 8px;font-weight:bold;
                                   vertical-align:top;">
                            Cancellation Reason
                        </td>
                        <td style="padding:12px 8px;">
                            ' . $safeReason . '
                        </td>
                    </tr>

                </table>

                <p style="margin:24px 0 0;font-size:13px;color:#667085;">
                    This is an automated email from the Talaue 88
                    Dental Clinic appointment system. No reply is required.
                </p>
            </div>
        </div>
    </body>
    </html>';

    // -----------------------------------------------------
    // 4.2 Plain-text email fallback
    // -----------------------------------------------------

    $plainBody =
        "Talaue 88 Dental Clinic\n\n" .
        "A patient has cancelled an appointment.\n\n" .
        "Reference Number: {$referenceNumber}\n" .
        "Patient Name: {$patientName}\n" .
        "Original Appointment Date: {$bookingDate}\n" .
        "Original Appointment Time: {$bookingTime}\n" .
        "Cancellation Reason:\n{$cancellationReason}\n";

    // -----------------------------------------------------
    // 4.3 Build multipart email
    // -----------------------------------------------------

    $boundary = '=_Talaue88_' . bin2hex(random_bytes(16));

    $encodedSubject = '=?UTF-8?B?' .
        base64_encode($subject) .
        '?=';

    $encodedFromName = '=?UTF-8?B?' .
        base64_encode($mailFromName) .
        '?=';

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $encodedFromName . ' <' . $mailFromAddress . '>',
        'Content-Type: multipart/alternative; boundary="' .
            $boundary . '"',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $message =
        "--{$boundary}\r\n" .
        "Content-Type: text/plain; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: base64\r\n\r\n" .
        chunk_split(base64_encode($plainBody)) .
        "--{$boundary}\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: base64\r\n\r\n" .
        chunk_split(base64_encode($htmlBody)) .
        "--{$boundary}--\r\n";

    // -----------------------------------------------------
    // 4.4 Send a separate email to each staff member
    // -----------------------------------------------------

    foreach ($staffAccounts as $staff) {
        $staffId = (int) $staff['id'];
        $roleId = (int) $staff['role_id'];

        try {
            // Stored email addresses are encrypted.
            $decryptedEmail = decryptEmail(
                (string) $staff['email']
            );

            if (
                !is_string($decryptedEmail) ||
                filter_var(
                    trim($decryptedEmail),
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                error_log(
                    "Cancellation notification: invalid or undecryptable email for staff ID {$staffId}."
                );

                continue;
            }

            $recipientEmail = trim($decryptedEmail);

            $sent = mail(
                $recipientEmail,
                $encodedSubject,
                $message,
                implode("\r\n", $headers)
            );

            if (!$sent) {
                error_log(
                    "Cancellation notification: mail() failed for staff ID {$staffId}, role ID {$roleId}."
                );
            }
        } catch (Throwable $e) {
            error_log(
                "Cancellation notification failed for staff ID {$staffId}: " .
                    $e->getMessage()
            );
        }
    }
}

// ---------------------------------------------------------
// 5. Allow POST requests only
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    sendJsonResponse(
        false,
        'Invalid request method.',
        405
    );
}

// ---------------------------------------------------------
// 6. Validate CSRF token
// ---------------------------------------------------------

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrfToken) ||
    $csrfToken === '' ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    sendJsonResponse(
        false,
        'Invalid security token. Please refresh the page and try again.',
        403
    );
}

// ---------------------------------------------------------
// 7. Validate booking ID
// ---------------------------------------------------------

$bookingId = filter_var(
    $_POST['booking_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    $bookingId === false ||
    $bookingId === null ||
    $bookingId <= 0
) {
    sendJsonResponse(
        false,
        'Invalid booking ID.',
        422
    );
}

// ---------------------------------------------------------
// 8. Validate reference number
// ---------------------------------------------------------

$referenceNumber = $_POST['reference_number'] ?? '';

if (
    !is_string($referenceNumber) ||
    trim($referenceNumber) === '' ||
    strlen($referenceNumber) > 100
) {
    sendJsonResponse(
        false,
        'Invalid appointment reference number.',
        422
    );
}

$referenceNumber = trim($referenceNumber);

// ---------------------------------------------------------
// 9. Validate cancellation reason
// ---------------------------------------------------------

$cancellationReason = $_POST['cancellation_reason'] ?? '';

if (!is_string($cancellationReason)) {
    sendJsonResponse(
        false,
        'Invalid cancellation reason.',
        422
    );
}

$cancellationReason = trim($cancellationReason);

if ($cancellationReason === '') {
    sendJsonResponse(
        false,
        'Please provide a reason for cancelling the appointment.',
        422
    );
}

if (mb_strlen($cancellationReason) > 500) {
    sendJsonResponse(
        false,
        'The cancellation reason must not exceed 500 characters.',
        422
    );
}

// ---------------------------------------------------------
// 10. Begin database transaction
// ---------------------------------------------------------

$transactionStarted = false;

try {
    mysqli_begin_transaction($conn);
    $transactionStarted = true;

    // -----------------------------------------------------
    // 11. Retrieve and lock the booking
    // -----------------------------------------------------

    $stmt = mysqli_prepare(
        $conn,
        "
        SELECT
            id,
            reference_number,
            full_name,
            booking_date,
            booking_time,
            status
        FROM bookings
        WHERE id = ?
          AND reference_number = ?
        LIMIT 1
        FOR UPDATE
        "
    );

    mysqli_stmt_bind_param(
        $stmt,
        'is',
        $bookingId,
        $referenceNumber
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $booking = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    // -----------------------------------------------------
    // 12. Verify booking exists
    // -----------------------------------------------------

    if (!$booking) {
        throw new DomainException(
            'The appointment could not be found. Please check your reference number.'
        );
    }

    // -----------------------------------------------------
    // 13. Validate booking status
    // -----------------------------------------------------

    $currentStatus = (string) $booking['status'];

    $cancellableStatuses = [
        'Pending',
        'Approved',
        'Rescheduled',
    ];

    if (!in_array($currentStatus, $cancellableStatuses, true)) {
        if ($currentStatus === 'Cancelled') {
            throw new DomainException(
                'This appointment has already been cancelled.'
            );
        }

        if ($currentStatus === 'Completed') {
            throw new DomainException(
                'A completed appointment cannot be cancelled.'
            );
        }

        if ($currentStatus === 'No Show') {
            throw new DomainException(
                'A no-show appointment cannot be cancelled.'
            );
        }

        throw new DomainException(
            'This appointment cannot be cancelled in its current status.'
        );
    }

    // -----------------------------------------------------
    // 14. Update booking status
    // -----------------------------------------------------

    $stmt = mysqli_prepare(
        $conn,
        "
        UPDATE bookings
        SET
            status = 'Cancelled',
            cancellation_reason = ?
        WHERE id = ?
          AND reference_number = ?
          AND status IN ('Pending', 'Approved', 'Rescheduled')
        LIMIT 1
        "
    );

    mysqli_stmt_bind_param(
        $stmt,
        'sis',
        $cancellationReason,
        $bookingId,
        $referenceNumber
    );

    mysqli_stmt_execute($stmt);

    $affectedRows = mysqli_stmt_affected_rows($stmt);

    mysqli_stmt_close($stmt);

    // -----------------------------------------------------
    // 15. Verify update
    // -----------------------------------------------------

    if ($affectedRows !== 1) {
        throw new DomainException(
            'The appointment could not be cancelled. Its status may have changed. Please check the appointment again.'
        );
    }

    // -----------------------------------------------------
    // 16. Commit cancellation
    // -----------------------------------------------------

    mysqli_commit($conn);
    $transactionStarted = false;

    // -----------------------------------------------------
    // 17. Notify administrators and dental assistants
    // -----------------------------------------------------

    // 17.1 Create in-app notifications independently.
    try {
        createStaffCancellationNotifications(
            $conn,
            $booking,
            $cancellationReason
        );
    } catch (Throwable $notificationError) {
        error_log(
            'In-app patient cancellation notification failed: ' .
                $notificationError->getMessage()
        );
    }

    // 17.2 Send cancellation emails independently
    try {
        notifyStaffOfCancellation(
            $conn,
            $booking,
            $cancellationReason,
            $mailFromAddress,
            $mailFromName
        );
    } catch (Throwable $emailError) {
        // Do not undo a committed cancellation if email fails.
        error_log(
            'Patient cancellation notification processing failed: ' .
                $emailError->getMessage()
        );
    }

    // -----------------------------------------------------
    // 18. Return AJAX success response
    // -----------------------------------------------------

    sendJsonResponse(
        true,
        'Appointment cancelled successfully.'
    );
} catch (DomainException $e) {
    if ($transactionStarted) {
        mysqli_rollback($conn);
    }

    sendJsonResponse(
        false,
        $e->getMessage(),
        422
    );
} catch (Throwable $e) {
    if ($transactionStarted) {
        mysqli_rollback($conn);
    }

    error_log(
        'Patient appointment cancellation failed: ' .
            $e->getMessage()
    );

    sendJsonResponse(
        false,
        'Something went wrong while cancelling your appointment. Please try again later.',
        500
    );
}
