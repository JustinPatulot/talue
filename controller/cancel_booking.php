<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helper/flash_helper.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/mail_config.php';

date_default_timezone_set('Asia/Manila');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Escape dynamic values for HTML email content.
 */
function escapeCancellationEmailHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Format a date for email display.
 */
function formatCancellationEmailDate(string $date): string
{
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $dateObject
        ? $dateObject->format('F j, Y')
        : $date;
}

/**
 * Format an appointment time for email display.
 */
function formatCancellationEmailTime(string $time): string
{
    $timeObject = DateTimeImmutable::createFromFormat(
        '!H:i:s',
        substr($time, 0, 8)
    );

    return $timeObject
        ? $timeObject->format('g:i A')
        : $time;
}

/**
 * Notify the patient after a clinic-side cancellation.
 *
 * Email failures must never reverse a committed cancellation.
 */
function notifyPatientOfClinicCancellation(
    array $booking,
    string $cancellationReason,
    string $mailFromAddress,
    string $mailFromName
): bool {
    $bookingId = (int) $booking['id'];

    try {
        // Patient email addresses are stored encrypted.
        $encryptedEmail = trim((string) ($booking['email'] ?? ''));

        if ($encryptedEmail === '') {
            error_log(
                "Clinic cancellation email: missing patient email for booking ID {$bookingId}."
            );

            return false;
        }

        $patientEmail = decryptEmail($encryptedEmail);

        if (
            !is_string($patientEmail) ||
            filter_var($patientEmail, FILTER_VALIDATE_EMAIL) === false
        ) {
            error_log(
                "Clinic cancellation email: invalid decrypted patient email for booking ID {$bookingId}."
            );

            return false;
        }

        $fromAddress = trim($mailFromAddress);
        $fromName = trim($mailFromName);

        if (
            $fromAddress === '' ||
            filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false
        ) {
            error_log(
                'Clinic cancellation email: invalid sender email configuration.'
            );

            return false;
        }

        $reference = (string) $booking['reference_number'];
        $patientName = (string) $booking['full_name'];

        $appointmentDate = formatCancellationEmailDate(
            (string) $booking['booking_date']
        );

        $appointmentTime = formatCancellationEmailTime(
            (string) $booking['booking_time']
        );

        $subject = 'Appointment Cancellation Notice - ' . $reference;

        // Plain-text version.
        $plainText =
            "Dear {$patientName},\n\n" .
            "We regret to inform you that your appointment with " .
            "Talaue 88 Dental Clinic has been cancelled by the clinic.\n\n" .
            "Appointment Reference: {$reference}\n" .
            "Appointment Date: {$appointmentDate}\n" .
            "Appointment Time: {$appointmentTime}\n" .
            "Status: Cancelled\n\n" .
            "Reason for Cancellation:\n{$cancellationReason}\n\n" .
            "We apologize for any inconvenience this may cause. " .
            "Please contact Talaue 88 Dental Clinic if you would like " .
            "assistance with arranging another appointment.\n\n" .
            "Regards,\n" .
            "Talaue 88 Dental Clinic";

        // HTML version.
        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Appointment Cancellation Notice</title>
        </head>
        <body style="margin:0;padding:24px;background-color:#f4f6f8;
                     font-family:Arial,Helvetica,sans-serif;color:#212529;">
            <div style="max-width:620px;margin:0 auto;background:#ffffff;
                        border:1px solid #e5e7eb;border-radius:10px;
                        overflow:hidden;">

                <div style="padding:22px 26px;background:#f8f9fa;
                            border-bottom:1px solid #e5e7eb;">
                    <h2 style="margin:0;font-size:21px;">
                        Appointment Cancellation Notice
                    </h2>
                    <p style="margin:8px 0 0;color:#6c757d;font-size:14px;">
                        Talaue 88 Dental Clinic
                    </p>
                </div>

                <div style="padding:26px;">
                    <p style="margin-top:0;">Dear '
            . escapeCancellationEmailHtml($patientName) .
            ',</p>

                    <p>
                        We regret to inform you that your appointment
                        has been cancelled by the clinic.
                    </p>

                    <table style="width:100%;border-collapse:collapse;
                                  margin:20px 0;font-size:14px;">
                        <tr>
                            <td style="padding:10px;border:1px solid #dee2e6;
                                       font-weight:bold;width:40%;">
                                Appointment Reference
                            </td>
                            <td style="padding:10px;border:1px solid #dee2e6;">'
            . escapeCancellationEmailHtml($reference) .
            '</td>
                        </tr>

                        <tr>
                            <td style="padding:10px;border:1px solid #dee2e6;
                                       font-weight:bold;">
                                Appointment Date
                            </td>
                            <td style="padding:10px;border:1px solid #dee2e6;">'
            . escapeCancellationEmailHtml($appointmentDate) .
            '</td>
                        </tr>

                        <tr>
                            <td style="padding:10px;border:1px solid #dee2e6;
                                       font-weight:bold;">
                                Appointment Time
                            </td>
                            <td style="padding:10px;border:1px solid #dee2e6;">'
            . escapeCancellationEmailHtml($appointmentTime) .
            '</td>
                        </tr>

                        <tr>
                            <td style="padding:10px;border:1px solid #dee2e6;
                                       font-weight:bold;">
                                Status
                            </td>
                            <td style="padding:10px;border:1px solid #dee2e6;">
                                Cancelled
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:10px;border:1px solid #dee2e6;
                                       font-weight:bold;">
                                Reason for Cancellation
                            </td>
                            <td style="padding:10px;border:1px solid #dee2e6;">'
            . nl2br(
                escapeCancellationEmailHtml($cancellationReason)
            ) .
            '</td>
                        </tr>
                    </table>

                    <p>
                        We apologize for any inconvenience this may cause.
                        Please contact Talaue 88 Dental Clinic if you
                        would like assistance with arranging another
                        appointment.
                    </p>

                    <p style="margin-bottom:0;">
                        Regards,<br>
                        <strong>Talaue 88 Dental Clinic</strong>
                    </p>
                </div>
            </div>
        </body>
        </html>';

        $boundary = '=_Talaue88_' . bin2hex(random_bytes(16));

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . mb_encode_mimeheader($fromName, 'UTF-8')
                . ' <' . $fromAddress . '>',
            'Content-Type: multipart/alternative; boundary="' .
                $boundary . '"',
        ];

        $message =
            "--{$boundary}\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $plainText . "\r\n\r\n" .
            "--{$boundary}\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $html . "\r\n\r\n" .
            "--{$boundary}--\r\n";

        $sent = mail(
            $patientEmail,
            mb_encode_mimeheader($subject, 'UTF-8'),
            $message,
            implode("\r\n", $headers)
        );

        if (!$sent) {
            error_log(
                "Clinic cancellation email: mail() failed for booking ID {$bookingId}."
            );

            return false;
        }

        return true;
    } catch (Throwable $emailException) {
        error_log(
            "Clinic cancellation email failed for booking ID {$bookingId}: " .
                $emailException->getMessage()
        );

        return false;
    }
}

// ---------------------------------------------------------
// 1. Allow POST requests only
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    header('Location: ../admin/bookings');
    exit;
}

// ---------------------------------------------------------
// 2. CSRF protection
// ---------------------------------------------------------

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrfToken) ||
    $csrfToken === '' ||
    empty($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    setFlash('danger', 'Invalid security token. Please try again.');
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

if ($bookingId === false || $bookingId === null || $bookingId <= 0) {
    setFlash('danger', 'Invalid booking ID.');
    header('Location: ../admin/bookings');
    exit;
}

// ---------------------------------------------------------
// 4. Validate cancellation reason
// ---------------------------------------------------------

$cancellationReason = trim(
    is_string($_POST['cancellation_reason'] ?? null)
        ? $_POST['cancellation_reason']
        : ''
);

if ($cancellationReason === '') {
    setFlash(
        'danger',
        'Please provide a reason for cancelling the appointment.'
    );

    header('Location: ../admin/bookings');
    exit;
}

if (mb_strlen($cancellationReason, 'UTF-8') > 500) {
    setFlash(
        'danger',
        'The cancellation reason must not exceed 500 characters.'
    );

    header('Location: ../admin/bookings');
    exit;
}

// ---------------------------------------------------------
// 5. Start transaction and lock the booking
// ---------------------------------------------------------

$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    // -----------------------------------------------------
    // 6. Fetch booking details and lock the row
    // -----------------------------------------------------

    $stmt = $conn->prepare(
        "SELECT
            id,
            reference_number,
            full_name,
            email,
            booking_date,
            booking_time,
            status
         FROM bookings
         WHERE id = ?
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();

    $booking = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$booking) {
        throw new RuntimeException(
            'The appointment could not be found.'
        );
    }

    // -----------------------------------------------------
    // 7. Validate current booking status
    // -----------------------------------------------------

    $currentStatus = (string) $booking['status'];

    $cancellableStatuses = [
        'Pending',
        'Approved',
        'Rescheduled',
    ];

    if (!in_array($currentStatus, $cancellableStatuses, true)) {
        if ($currentStatus === 'Cancelled') {
            throw new RuntimeException(
                'This appointment has already been cancelled.'
            );
        }

        if ($currentStatus === 'Completed') {
            throw new RuntimeException(
                'A completed appointment cannot be cancelled.'
            );
        }

        if ($currentStatus === 'No Show') {
            throw new RuntimeException(
                'A no-show appointment cannot be cancelled.'
            );
        }

        throw new RuntimeException(
            'This appointment cannot be cancelled in its current status.'
        );
    }

    // -----------------------------------------------------
    // 8. Update booking status and cancellation reason
    // -----------------------------------------------------

    $stmt = $conn->prepare(
        "UPDATE bookings
         SET status = 'Cancelled',
             cancellation_reason = ?
         WHERE id = ?
           AND status IN ('Pending', 'Approved', 'Rescheduled')
         LIMIT 1"
    );

    $stmt->bind_param(
        'si',
        $cancellationReason,
        $bookingId
    );

    $stmt->execute();

    $affectedRows = $stmt->affected_rows;

    $stmt->close();

    if ($affectedRows !== 1) {
        throw new RuntimeException(
            'The appointment could not be cancelled because its status changed.'
        );
    }

    // -----------------------------------------------------
    // 9. Commit before sending email
    // -----------------------------------------------------

    $conn->commit();
    $transactionStarted = false;

    // -----------------------------------------------------
    // 10. Notify the patient
    // -----------------------------------------------------

    $emailSent = notifyPatientOfClinicCancellation(
        $booking,
        $cancellationReason,
        (string) ($mailFromAddress ?? ''),
        (string) ($mailFromName ?? 'Talaue 88 Dental Clinic')
    );

    // Cancellation remains successful if email fails.
    if ($emailSent) {
        setFlash(
            'success',
            'Appointment cancelled successfully.'
        );
    } else {
        setFlash(
            'warning',
            'Appointment cancelled successfully, but the patient notification email could not be sent. Please check the server error log.'
        );
    }
} catch (Throwable $e) {
    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(
                'Clinic cancellation rollback error: ' .
                    $rollbackException->getMessage()
            );
        }
    }

    error_log(
        'Clinic booking cancellation error: ' . $e->getMessage()
    );

    setFlash(
        'danger',
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'Unable to cancel the appointment right now. Please try again later.'
    );
}

// ---------------------------------------------------------
// 11. Return to bookings page
// ---------------------------------------------------------

header('Location: ../admin/bookings');
exit;
