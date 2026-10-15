<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/mail_config.php';

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Asia/Manila');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Return a JSON response and terminate execution.
 */
function respond(bool $success, string $message, int $httpCode = 200): never
{
    http_response_code($httpCode);

    echo json_encode([
        'success' => $success,
        'message' => $message,
    ]);

    exit;
}

/**
 * Safely escape values used in HTML email content.
 */
function escapeEmailHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/**
 * Format an appointment date for email display.
 */
function formatAppointmentDate(string $date): string
{
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $dateObject
        ? $dateObject->format('F j, Y')
        : $date;
}

/**
 * Format an appointment time for email display.
 */
function formatAppointmentTime(string $time): string
{
    $timeObject = DateTimeImmutable::createFromFormat('!H:i:s', $time);

    return $timeObject
        ? $timeObject->format('g:i A')
        : $time;
}

/**
 * Notify administrators and dental assistants after a patient
 * successfully reschedules an appointment.
 *
 * Email failures must not reverse the committed booking update.
 */

// ---------------------------------------------------------
// Create in-app notifications for patient rescheduling
// Admin (1) and Dental Assistant (2)
// ---------------------------------------------------------

function createStaffPatientRescheduleNotifications(
    mysqli $conn,
    array $booking,
    string $oldDate,
    string $oldTime,
    string $newDate,
    string $newTime,
    string $reason
): void {
    // Retrieve Admin and Dental Assistant accounts only.
    $staffStmt = $conn->prepare("
        SELECT id
        FROM dentists
        WHERE role_id IN (1, 2)
    ");

    $staffStmt->execute();

    $result = $staffStmt->get_result();
    $recipientIds = [];

    while ($staff = $result->fetch_assoc()) {
        $recipientIds[] = (int) $staff['id'];
    }

    $staffStmt->close();

    if (empty($recipientIds)) {
        error_log(
            'Patient rescheduling in-app notification: no eligible staff accounts found.'
        );

        return;
    }

    $bookingId = (int) $booking['id'];
    $referenceNumber = (string) $booking['reference_number'];
    $patientName = (string) $booking['full_name'];

    $notificationType = 'booking_rescheduled';
    $title = 'Appointment Rescheduled';

    $formattedOldDate = formatAppointmentDate($oldDate);
    $formattedOldTime = formatAppointmentTime($oldTime);
    $formattedNewDate = formatAppointmentDate($newDate);
    $formattedNewTime = formatAppointmentTime($newTime);

    $message = sprintf(
        'Patient %s rescheduled appointment %s from %s at %s to %s at %s. Reason: %s. New schedule pending approval.',
        $patientName,
        $referenceNumber,
        $formattedOldDate,
        $formattedOldTime,
        $formattedNewDate,
        $formattedNewTime,
        $reason
    );

    $notificationStmt = $conn->prepare("
        INSERT INTO notifications (
            recipient_id,
            booking_id,
            doctor_change_request_id,
            notification_type,
            title,
            message,
            is_read
        ) VALUES (?, ?, NULL, ?, ?, ?, 0)
    ");

    foreach ($recipientIds as $recipientId) {
        $notificationStmt->bind_param(
            'iisss',
            $recipientId,
            $bookingId,
            $notificationType,
            $title,
            $message
        );

        $notificationStmt->execute();
    }

    $notificationStmt->close();
}

function notifyStaffOfPatientReschedule(
    mysqli $conn,
    array $booking,
    string $oldDate,
    string $oldTime,
    string $newDate,
    string $newTime,
    string $reason,
    string $mailFromAddress,
    string $mailFromName
): void {
    // Staff recipients: administrators only.
    // Admin and dental assistants (role_id = 2).
    $staffStmt = $conn->prepare(
        "SELECT id, dentist_name, email
     FROM dentists
     WHERE role_id IN (1, 2)
       AND email IS NOT NULL
       AND TRIM(email) <> ''"
    );

    $staffStmt->execute();
    $staffResult = $staffStmt->get_result();

    $staffMembers = [];

    while ($staff = $staffResult->fetch_assoc()) {
        $staffMembers[] = $staff;
    }

    $staffStmt->close();

    if (empty($staffMembers)) {
        error_log(
            'Patient rescheduling notification:     staff recipients found.'
        );

        return;
    }

    $reference = (string) $booking['reference_number'];
    $patientName = (string) $booking['full_name'];

    $formattedOldDate = formatAppointmentDate($oldDate);
    $formattedOldTime = formatAppointmentTime($oldTime);
    $formattedNewDate = formatAppointmentDate($newDate);
    $formattedNewTime = formatAppointmentTime($newTime);

    $subject = 'Patient Appointment Rescheduled - ' . $reference;

    $plainText =
        "A patient has rescheduled an appointment.\n\n" .
        "Appointment Reference: {$reference}\n" .
        "Patient Name: {$patientName}\n\n" .
        "Previous Schedule: {$formattedOldDate} at {$formattedOldTime}\n" .
        "New Schedule: {$formattedNewDate} at {$formattedNewTime}\n\n" .
        "Rescheduling Reason: {$reason}\n\n" .
        "Current Status: Pending\n\n" .
        "The new schedule is pending clinic approval.\n" .
        "Please review the appointment in the Talaue 88 Dental Clinic system.";

    $html = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Patient Appointment Rescheduled</title>
    </head>
    <body style="margin:0;padding:24px;background-color:#f4f6f8;
                 font-family:Arial,Helvetica,sans-serif;color:#212529;">
        <div style="max-width:620px;margin:0 auto;background:#ffffff;
                    border:1px solid #e5e7eb;border-radius:10px;
                    overflow:hidden;">
            <div style="padding:22px 26px;background:#f8f9fa;
                        border-bottom:1px solid #e5e7eb;">
                <h2 style="margin:0;font-size:21px;">
                    Patient Appointment Rescheduled
                </h2>
                <p style="margin:8px 0 0;color:#6c757d;font-size:14px;">
                    Talaue 88 Dental Clinic
                </p>
            </div>

            <div style="padding:26px;">
                <p style="margin-top:0;">Hello Staff,</p>

                <p>
                    A patient has requested a new appointment schedule.
                    Please review the details below.
                </p>

                <table style="width:100%;border-collapse:collapse;
                              margin:20px 0;font-size:14px;">
                    <tr>
                        <td style="padding:10px;border:1px solid #dee2e6;
                                   font-weight:bold;width:40%;">
                            Appointment Reference
                        </td>
                        <td style="padding:10px;border:1px solid #dee2e6;">'
        . escapeEmailHtml($reference) .
        '</td>
                    </tr>
                    <tr>
                        <td style="padding:10px;border:1px solid #dee2e6;
                                   font-weight:bold;">
                            Patient Name
                        </td>
                        <td style="padding:10px;border:1px solid #dee2e6;">'
        . escapeEmailHtml($patientName) .
        '</td>
                    </tr>
                    <tr>
                        <td style="padding:10px;border:1px solid #dee2e6;
                                   font-weight:bold;">
                            Previous Schedule
                        </td>
                        <td style="padding:10px;border:1px solid #dee2e6;">'
        . escapeEmailHtml(
            $formattedOldDate . ' at ' . $formattedOldTime
        ) .
        '</td>
                    </tr>
                    <tr>
                        <td style="padding:10px;border:1px solid #dee2e6;
                                   font-weight:bold;">
                            New Schedule
                        </td>
                        <td style="padding:10px;border:1px solid #dee2e6;">'
        . escapeEmailHtml(
            $formattedNewDate . ' at ' . $formattedNewTime
        ) .
        '</td>
                    </tr>
                    <tr>
                        <td style="padding:10px;border:1px solid #dee2e6;
                                   font-weight:bold;">
                            Rescheduling Reason
                        </td>
                        <td style="padding:10px;border:1px solid #dee2e6;">'
        . nl2br(escapeEmailHtml($reason)) .
        '</td>
                    </tr>
                    <tr>
                        <td style="padding:10px;border:1px solid #dee2e6;
                                   font-weight:bold;">
                            Current Status
                        </td>
                        <td style="padding:10px;border:1px solid #dee2e6;">
                            Pending
                        </td>
                    </tr>
                </table>

                <p style="padding:13px;background:#fff3cd;color:#664d03;
                          border-radius:6px;font-size:14px;">
                    The new schedule is pending clinic approval.
                    Please review the appointment in the clinic system.
                </p>

                <p style="margin-bottom:0;">
                    Regards,<br>
                    <strong>Talaue 88 Dental Clinic</strong>
                </p>
            </div>
        </div>
    </body>
    </html>';

    // Use the configured sender identity.
    $fromAddress = trim($mailFromAddress);
    $fromName = trim($mailFromName);

    if (
        $fromAddress === '' ||
        filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false
    ) {
        error_log(
            'Patient rescheduling notification: invalid or missing sender email configuration.'
        );

        return;
    }

    $boundary = '=_Talaue88_' . bin2hex(random_bytes(16));

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . mb_encode_mimeheader($fromName, 'UTF-8')
            . ' <' . $fromAddress . '>',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
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

    foreach ($staffMembers as $staff) {
        $staffId = (int) $staff['id'];

        try {
            $encryptedEmail = trim((string) $staff['email']);

            // Stored staff email addresses are encrypted.
            $recipientEmail = decryptEmail($encryptedEmail);

            if (
                !is_string($recipientEmail) ||
                filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) === false
            ) {
                error_log(
                    "Patient rescheduling notification: invalid decrypted email for staff ID {$staffId}."
                );

                continue;
            }

            $sent = mail(
                $recipientEmail,
                mb_encode_mimeheader($subject, 'UTF-8'),
                $message,
                implode("\r\n", $headers)
            );

            if (!$sent) {
                error_log(
                    "Patient rescheduling notification: mail() failed for staff ID {$staffId}."
                );
            }
        } catch (Throwable $emailException) {
            error_log(
                "Patient rescheduling notification failed for staff ID {$staffId}: " .
                    $emailException->getMessage()
            );

            // Continue notifying the remaining staff members.
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(false, 'Invalid request method.', 405);
}

try {
    // Validate CSRF token.
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($sessionToken) ||
        !is_string($submittedToken) ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        respond(
            false,
            'Your session has expired or the request is invalid. Please refresh the page and try again.',
            403
        );
    }

    // Read and validate submitted values.
    $bookingId = filter_var(
        $_POST['booking_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $referenceNumber = trim(
        is_string($_POST['reference_number'] ?? null)
            ? $_POST['reference_number']
            : ''
    );

    $newDate = trim(
        is_string($_POST['new_booking_date'] ?? null)
            ? $_POST['new_booking_date']
            : ''
    );

    $newTime = trim(
        is_string($_POST['new_booking_time'] ?? null)
            ? $_POST['new_booking_time']
            : ''
    );

    $reason = trim(
        is_string($_POST['reschedule_reason'] ?? null)
            ? $_POST['reschedule_reason']
            : ''
    );

    if (
        $bookingId === false ||
        $referenceNumber === '' ||
        $newDate === '' ||
        $newTime === '' ||
        $reason === ''
    ) {
        respond(false, 'Please complete all required fields.', 422);
    }

    if (strlen($referenceNumber) > 20) {
        respond(false, 'Invalid appointment reference number.', 422);
    }

    if (mb_strlen($reason, 'UTF-8') > 500) {
        respond(false, 'The reason must not exceed 500 characters.', 422);
    }

    // Strict date validation.
    $dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $newDate);
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (
        !$dateObject ||
        ($dateErrors !== false &&
            (
                $dateErrors['warning_count'] > 0 ||
                $dateErrors['error_count'] > 0
            )) ||
        $dateObject->format('Y-m-d') !== $newDate
    ) {
        respond(false, 'Please select a valid appointment date.', 422);
    }

    // Accept HH:MM and normalize to HH:MM:SS.
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $newTime)) {
        respond(false, 'Please select a valid appointment time.', 422);
    }

    $normalizedTime = $newTime . ':00';

    // Clinic hours: 10:00 AM through 7:00 PM.
    if ($normalizedTime < '10:00:00' || $normalizedTime > '19:00:00') {
        respond(
            false,
            'Appointments must be scheduled between 10:00 AM and 7:00 PM.',
            422
        );
    }

    // Reject past dates and times using Philippine local time.
    $now = new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Manila')
    );

    $requestedSchedule = new DateTimeImmutable(
        $newDate . ' ' . $normalizedTime,
        new DateTimeZone('Asia/Manila')
    );

    if ($requestedSchedule <= $now) {
        respond(
            false,
            'You cannot reschedule an appointment to a date or time that has already passed.',
            422
        );
    }

    $conn->begin_transaction();

    // Lock the booking while validating and updating it.
    // Include full_name for the staff notification email.
    $bookingStmt = $conn->prepare(
        "SELECT id, reference_number, full_name, dentist_id, branch_id,
                booking_date, booking_time, status
         FROM bookings
         WHERE id = ? AND reference_number = ?
         FOR UPDATE"
    );

    $bookingStmt->bind_param('is', $bookingId, $referenceNumber);
    $bookingStmt->execute();

    $booking = $bookingStmt->get_result()->fetch_assoc();
    $bookingStmt->close();

    if (!$booking) {
        $conn->rollback();

        respond(
            false,
            'Appointment not found. Please check your reference number.',
            404
        );
    }

    // Only Pending and Approved appointments can be rescheduled by patients.
    if (!in_array($booking['status'], ['Pending', 'Approved'], true)) {
        $conn->rollback();

        respond(
            false,
            'This appointment can no longer be rescheduled. Please check its current status.',
            409
        );
    }

    // Preserve the old schedule for the notification.
    $oldDate = (string) $booking['booking_date'];
    $oldTime = substr((string) $booking['booking_time'], 0, 8);

    // Reject an unchanged date and time.
    if (
        $newDate === $oldDate &&
        $normalizedTime === $oldTime
    ) {
        $conn->rollback();

        respond(
            false,
            'The new schedule is identical to your current appointment. Please choose a different date or time.',
            422
        );
    }

    // Prevent double-booking the same dentist, branch, date and time.
    // The current booking is excluded from the conflict check.
    $conflictStmt = $conn->prepare(
        "SELECT id
         FROM bookings
         WHERE dentist_id = ?
           AND branch_id = ?
           AND booking_date = ?
           AND booking_time = ?
           AND id <> ?
           AND status IN ('Pending', 'Approved', 'Rescheduled')
         LIMIT 1
         FOR UPDATE"
    );

    $dentistId = (int) $booking['dentist_id'];
    $branchId = (int) $booking['branch_id'];

    $conflictStmt->bind_param(
        'iissi',
        $dentistId,
        $branchId,
        $newDate,
        $normalizedTime,
        $bookingId
    );

    $conflictStmt->execute();
    $conflict = $conflictStmt->get_result()->fetch_assoc();
    $conflictStmt->close();

    if ($conflict) {
        $conn->rollback();

        respond(
            false,
            'This dentist already has an appointment at the selected branch, date and time. Please choose another schedule.',
            409
        );
    }

    // Pending remains Pending.
    // Approved returns to Pending for clinic approval.
    // Keep the existing reference number unchanged.
    $updateStmt = $conn->prepare(
        "UPDATE bookings
         SET booking_date = ?,
             booking_time = ?,
             reschedule_reason = ?,
             status = 'Pending'
         WHERE id = ?
           AND reference_number = ?"
    );

    $updateStmt->bind_param(
        'sssis',
        $newDate,
        $normalizedTime,
        $reason,
        $bookingId,
        $referenceNumber
    );

    $updateStmt->execute();
    $updateStmt->close();

    // Commit the booking update before sending notifications.
    $conn->commit();

    // Notification failures must not undo the successful reschedule
    // ---------------------------------------------------------
    // Notify staff after the reschedule transaction commits
    // ---------------------------------------------------------

    // 1. Create in-app notifications independently.
    try {
        createStaffPatientRescheduleNotifications(
            $conn,
            $booking,
            $oldDate,
            $oldTime,
            $newDate,
            $normalizedTime,
            $reason
        );
    } catch (Throwable $notificationException) {
        error_log(
            'Patient rescheduling in-app notification failed: ' .
                $notificationException->getMessage()
        );
    }

    // 2. Send email notifications independently.
    try {
        notifyStaffOfPatientReschedule(
            $conn,
            $booking,
            $oldDate,
            $oldTime,
            $newDate,
            $normalizedTime,
            $reason,
            (string) ($mailFromAddress ?? ''),
            (string) ($mailFromName ?? 'Talaue 88 Dental Clinic')
        );
    } catch (Throwable $emailException) {
        error_log(
            'Patient rescheduling email notification failed: ' .
                $emailException->getMessage()
        );
    }

    respond(
        true,
        'Your appointment has been rescheduled successfully. Its new schedule is pending clinic approval.'
    );
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
            // The transaction may already have been committed or rolled back.
        }
    }

    error_log('Patient rescheduling error: ' . $e->getMessage());

    respond(
        false,
        'Unable to reschedule your appointment right now. Please try again later.',
        500
    );
}
