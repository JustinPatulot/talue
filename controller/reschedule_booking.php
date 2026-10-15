<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../helper/flash_helper.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/mail_config.php';

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Manila');

$timezone = new DateTimeZone('Asia/Manila');

$clinicOpeningTime = '10:00';
$clinicClosingTime = '19:00';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| Email Helper Functions
|--------------------------------------------------------------------------
*/

/**
 * Escape dynamic values for HTML emails.
 */
function escapeRescheduleEmailHtml(string $value): string
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
function formatRescheduleEmailDate(string $date): string
{
    $dateObject = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $date
    );

    return $dateObject
        ? $dateObject->format('F j, Y')
        : $date;
}

/**
 * Format an appointment time for email display.
 */
function formatRescheduleEmailTime(string $time): string
{
    $timeObject = DateTimeImmutable::createFromFormat(
        '!H:i:s',
        substr($time, 0, 8)
    );

    return $timeObject
        ? $timeObject->format('g:i A')
        : $time;
}

/*
|--------------------------------------------------------------------------
| Patient Rescheduling Email
|--------------------------------------------------------------------------
|
| Called only after the database transaction has committed.
| Email failure must not undo the booking update.
|
*/

function notifyPatientOfClinicReschedule(
    array $booking,
    string $newDate,
    string $newTime,
    string $reason,
    string $mailFromAddress,
    string $mailFromName
): bool {
    $bookingId = (int) $booking['id'];

    try {
        /*
        |--------------------------------------------------------------------------
        | Validate Sender
        |--------------------------------------------------------------------------
        */

        $fromAddress = trim($mailFromAddress);
        $fromName = trim($mailFromName);

        if (
            $fromAddress === '' ||
            filter_var(
                $fromAddress,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            error_log(
                'Clinic rescheduling email: invalid sender configuration.'
            );

            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Decrypt Patient Email
        |--------------------------------------------------------------------------
        */

        $encryptedEmail = trim(
            (string) ($booking['email'] ?? '')
        );

        if ($encryptedEmail === '') {
            error_log(
                "Clinic rescheduling email: missing patient email for booking ID {$bookingId}."
            );

            return false;
        }

        $patientEmail = decryptEmail($encryptedEmail);

        if (
            !is_string($patientEmail) ||
            filter_var(
                $patientEmail,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            error_log(
                "Clinic rescheduling email: invalid decrypted patient email for booking ID {$bookingId}."
            );

            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Prepare Appointment Details
        |--------------------------------------------------------------------------
        */

        $patientName = (string) $booking['full_name'];

        $reference = (string) $booking['reference_number'];

        $oldDate = formatRescheduleEmailDate(
            (string) $booking['booking_date']
        );

        $oldTime = formatRescheduleEmailTime(
            (string) $booking['booking_time']
        );

        $formattedNewDate = formatRescheduleEmailDate(
            $newDate
        );

        $formattedNewTime = formatRescheduleEmailTime(
            $newTime . ':00'
        );

        $subject =
            'Your Appointment Has Been Rescheduled - ' . $reference;

        /*
        |--------------------------------------------------------------------------
        | Plain-Text Email
        |--------------------------------------------------------------------------
        */

        $plainText =
            "Dear {$patientName},\n\n" .
            "Talaue 88 Dental Clinic has rescheduled your appointment.\n\n" .
            "Appointment Reference: {$reference}\n" .
            "Previous Schedule: {$oldDate} at {$oldTime}\n" .
            "New Official Schedule: {$formattedNewDate} at {$formattedNewTime}\n" .
            "Status: Rescheduled\n\n" .
            "Reason for Rescheduling:\n{$reason}\n\n" .
            "Please take note of your new official appointment schedule. " .
            "If you have any questions, please contact the clinic.\n\n" .
            "Regards,\n" .
            "Talaue 88 Dental Clinic";

        /*
        |--------------------------------------------------------------------------
        | HTML Email
        |--------------------------------------------------------------------------
        */

        $html = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Appointment Rescheduled</title>
        </head>

        <body style="margin:0;padding:24px;background-color:#f4f6f8;
                     font-family:Arial,Helvetica,sans-serif;color:#212529;">

            <div style="max-width:620px;margin:0 auto;background:#ffffff;
                        border:1px solid #e5e7eb;border-radius:10px;
                        overflow:hidden;">

                <div style="padding:22px 26px;background:#f8f9fa;
                            border-bottom:1px solid #e5e7eb;">

                    <h2 style="margin:0;font-size:21px;">
                        Your Appointment Has Been Rescheduled
                    </h2>

                    <p style="margin:8px 0 0;color:#6c757d;font-size:14px;">
                        Talaue 88 Dental Clinic
                    </p>
                </div>

                <div style="padding:26px;">

                    <p style="margin-top:0;">
                        Dear '
            . escapeRescheduleEmailHtml($patientName) .
            ',
                    </p>

                    <p>
                        Talaue 88 Dental Clinic has rescheduled your
                        appointment. Please review your updated schedule
                        below.
                    </p>

                    <table style="width:100%;border-collapse:collapse;
                                  margin:20px 0;font-size:14px;">

                        <tr>
                            <td style="padding:10px;border:1px solid #dee2e6;
                                       font-weight:bold;width:40%;">
                                Appointment Reference
                            </td>

                            <td style="padding:10px;border:1px solid #dee2e6;">'
            . escapeRescheduleEmailHtml($reference) .
            '</td>
                        </tr>

                        <tr>
                            <td style="padding:10px;border:1px solid #dee2e6;
                                       font-weight:bold;">
                                Previous Schedule
                            </td>

                            <td style="padding:10px;border:1px solid #dee2e6;">'
            . escapeRescheduleEmailHtml(
                $oldDate . ' at ' . $oldTime
            ) .
            '</td>
                        </tr>

                        <tr>
                            <td style="padding:10px;border:1px solid #dee2e6;
                                       font-weight:bold;">
                                New Official Schedule
                            </td>

                            <td style="padding:10px;border:1px solid #dee2e6;">'
            . escapeRescheduleEmailHtml(
                $formattedNewDate . ' at ' . $formattedNewTime
            ) .
            '</td>
                        </tr>

                        <tr>
                            <td style="padding:10px;border:1px solid #dee2e6;
                                       font-weight:bold;">
                                Status
                            </td>

                            <td style="padding:10px;border:1px solid #dee2e6;">
                                Rescheduled
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:10px;border:1px solid #dee2e6;
                                       font-weight:bold;">
                                Reason for Rescheduling
                            </td>

                            <td style="padding:10px;border:1px solid #dee2e6;">'
            . nl2br(
                escapeRescheduleEmailHtml($reason)
            ) .
            '</td>
                        </tr>

                    </table>

                    <p style="padding:13px;background:#d1e7dd;color:#0f5132;
                              border-radius:6px;font-size:14px;">
                        Your new schedule is now the official appointment
                        schedule. Please take note of the updated date and
                        time.
                    </p>

                    <p>
                        If you have any questions or concerns, please
                        contact Talaue 88 Dental Clinic.
                    </p>

                    <p style="margin-bottom:0;">
                        Regards,<br>
                        <strong>Talaue 88 Dental Clinic</strong>
                    </p>

                </div>
            </div>
        </body>
        </html>';

        /*
        |--------------------------------------------------------------------------
        | Build Multipart Email
        |--------------------------------------------------------------------------
        */

        $boundary = '=_Talaue88_' . bin2hex(random_bytes(16));

        $headers = [
            'MIME-Version: 1.0',

            'From: ' .
                mb_encode_mimeheader($fromName, 'UTF-8') .
                ' <' . $fromAddress . '>',

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

        /*
        |--------------------------------------------------------------------------
        | Send Email
        |--------------------------------------------------------------------------
        */

        $sent = mail(
            $patientEmail,
            mb_encode_mimeheader($subject, 'UTF-8'),
            $message,
            implode("\r\n", $headers)
        );

        if (!$sent) {
            error_log(
                "Clinic rescheduling email: mail() failed for booking ID {$bookingId}."
            );

            return false;
        }

        return true;
    } catch (Throwable $emailException) {
        error_log(
            "Clinic rescheduling email error for booking ID {$bookingId}: " .
                $emailException->getMessage()
        );

        return false;
    }
}

/*
|--------------------------------------------------------------------------
| 1. Allow POST Requests Only
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request.');

    header('Location: ../admin/bookings');
    exit;
}

/*
|--------------------------------------------------------------------------
| 2. CSRF Protection
|--------------------------------------------------------------------------
*/

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrfToken) ||
    $csrfToken === '' ||
    !isset($_SESSION['csrf_token']) ||
    !is_string($_SESSION['csrf_token']) ||
    $_SESSION['csrf_token'] === '' ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    setFlash(
        'danger',
        'Invalid request. Please refresh the page and try again.'
    );

    header('Location: ../admin/bookings');
    exit;
}

/*
|--------------------------------------------------------------------------
| 3. Validate Booking ID
|--------------------------------------------------------------------------
*/

$bookingId = filter_var(
    $_POST['booking_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($bookingId === false || $bookingId === null) {
    setFlash('danger', 'Invalid booking.');

    header('Location: ../admin/bookings');
    exit;
}

/*
|--------------------------------------------------------------------------
| 4. Read Submitted Values
|--------------------------------------------------------------------------
*/

$newBookingDate = trim(
    is_string($_POST['new_booking_date'] ?? null)
        ? $_POST['new_booking_date']
        : ''
);

$newBookingTime = trim(
    is_string($_POST['new_booking_time'] ?? null)
        ? $_POST['new_booking_time']
        : ''
);

$rescheduleReason = trim(
    is_string($_POST['reschedule_reason'] ?? null)
        ? $_POST['reschedule_reason']
        : ''
);

/*
|--------------------------------------------------------------------------
| 5. Validate Rescheduling Reason
|--------------------------------------------------------------------------
*/

if ($rescheduleReason === '') {
    setFlash(
        'danger',
        'Please provide a reason for rescheduling the appointment.'
    );

    header('Location: ../admin/bookings');
    exit;
}

if (mb_strlen($rescheduleReason, 'UTF-8') > 500) {
    setFlash(
        'danger',
        'The reschedule reason must not exceed 500 characters.'
    );

    header('Location: ../admin/bookings');
    exit;
}

/*
|--------------------------------------------------------------------------
| 6. Validate Appointment Date
|--------------------------------------------------------------------------
*/

$dateObject = DateTimeImmutable::createFromFormat(
    '!Y-m-d',
    $newBookingDate,
    $timezone
);

$dateErrors = DateTimeImmutable::getLastErrors();

if (
    !$dateObject ||
    (
        $dateErrors !== false &&
        (
            $dateErrors['warning_count'] > 0 ||
            $dateErrors['error_count'] > 0
        )
    ) ||
    $dateObject->format('Y-m-d') !== $newBookingDate
) {
    setFlash(
        'danger',
        'Please select a valid appointment date.'
    );

    header('Location: ../admin/bookings');
    exit;
}

/*
|--------------------------------------------------------------------------
| 7. Validate Appointment Time
|--------------------------------------------------------------------------
*/

$timeObject = DateTimeImmutable::createFromFormat(
    '!H:i',
    $newBookingTime,
    $timezone
);

$timeErrors = DateTimeImmutable::getLastErrors();

if (
    !$timeObject ||
    (
        $timeErrors !== false &&
        (
            $timeErrors['warning_count'] > 0 ||
            $timeErrors['error_count'] > 0
        )
    ) ||
    $timeObject->format('H:i') !== $newBookingTime
) {
    setFlash(
        'danger',
        'Please select a valid appointment time.'
    );

    header('Location: ../admin/bookings');
    exit;
}

/*
|--------------------------------------------------------------------------
| 8. Validate Clinic Operating Hours
|--------------------------------------------------------------------------
*/

if (
    $newBookingTime < $clinicOpeningTime ||
    $newBookingTime > $clinicClosingTime
) {
    setFlash(
        'danger',
        'Please select an appointment time between 10:00 AM and 7:00 PM.'
    );

    header('Location: ../admin/bookings');
    exit;
}

/*
|--------------------------------------------------------------------------
| 9. Reject Past Appointments
|--------------------------------------------------------------------------
*/

$appointmentDateTime = DateTimeImmutable::createFromFormat(
    '!Y-m-d H:i',
    $newBookingDate . ' ' . $newBookingTime,
    $timezone
);

$now = new DateTimeImmutable('now', $timezone);

if (
    !$appointmentDateTime ||
    $appointmentDateTime <= $now
) {
    setFlash(
        'danger',
        'Please select a future appointment date and time.'
    );

    header('Location: ../admin/bookings');
    exit;
}

/*
|--------------------------------------------------------------------------
| 10. Begin Transaction
|--------------------------------------------------------------------------
*/

$transactionStarted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    /*
    |--------------------------------------------------------------------------
    | 11. Retrieve and Lock Booking
    |--------------------------------------------------------------------------
    |
    | Include patient email, reference, name and old schedule for the email.
    | FOR UPDATE prevents simultaneous updates to this booking row.
    |
    */

    $stmt = $conn->prepare(
        "
        SELECT
            id,
            reference_number,
            full_name,
            email,
            dentist_id,
            branch_id,
            booking_date,
            booking_time,
            status
        FROM bookings
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();

    $booking = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    /*
    |--------------------------------------------------------------------------
    | 12. Check Booking Existence
    |--------------------------------------------------------------------------
    */

    if (!$booking) {
        throw new RuntimeException(
            'The booking could not be found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 13. Validate Current Status
    |--------------------------------------------------------------------------
    |
    | Only Pending and Approved bookings can be rescheduled by the clinic.
    | Rescheduled, Completed, Cancelled and No Show are not eligible.
    |
    */

    if (
        !in_array(
            $booking['status'],
            ['Pending', 'Approved'],
            true
        )
    ) {
        throw new RuntimeException(
            'This booking can no longer be rescheduled.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 14. Reject Unchanged Schedule
    |--------------------------------------------------------------------------
    */

    $currentDate = (string) $booking['booking_date'];

    $currentTime = substr(
        (string) $booking['booking_time'],
        0,
        5
    );

    if (
        $newBookingDate === $currentDate &&
        $newBookingTime === $currentTime
    ) {
        throw new RuntimeException(
            'The new appointment date and time must be different from the current schedule.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 15. Check Appointment Slot Availability
    |--------------------------------------------------------------------------
    |
    | Keep the existing dentist and branch.
    | Rescheduled bookings occupy their official appointment slots.
    |
    */

    $dentistId = (int) $booking['dentist_id'];
    $branchId = (int) $booking['branch_id'];

    $normalizedTime = $newBookingTime . ':00';

    $stmt = $conn->prepare(
        "
        SELECT id
        FROM bookings
        WHERE dentist_id = ?
          AND branch_id = ?
          AND booking_date = ?
          AND booking_time = ?
          AND status IN (
              'Pending',
              'Approved',
              'Rescheduled'
          )
          AND id <> ?
        LIMIT 1
        FOR UPDATE
        "
    );

    $stmt->bind_param(
        'iissi',
        $dentistId,
        $branchId,
        $newBookingDate,
        $normalizedTime,
        $bookingId
    );

    $stmt->execute();

    $conflictingBooking = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if ($conflictingBooking) {
        throw new RuntimeException(
            'The selected date and time is already booked for this dentist and branch. Please select another schedule.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 16. Update Booking
    |--------------------------------------------------------------------------
    |
    | The new schedule becomes official.
    | The status becomes Rescheduled.
    | Save the clinic's rescheduling reason.
    |
    */

    $stmt = $conn->prepare(
        "
        UPDATE bookings
        SET
            booking_date = ?,
            booking_time = ?,
            reschedule_reason = ?,
            status = 'Rescheduled'
        WHERE id = ?
          AND status IN ('Pending', 'Approved')
        LIMIT 1
        "
    );

    $stmt->bind_param(
        'sssi',
        $newBookingDate,
        $normalizedTime,
        $rescheduleReason,
        $bookingId
    );

    $stmt->execute();

    $affectedRows = $stmt->affected_rows;

    $stmt->close();

    if ($affectedRows !== 1) {
        throw new RuntimeException(
            'The appointment could not be rescheduled because its status changed.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 17. Commit Transaction
    |--------------------------------------------------------------------------
    |
    | Do not send the email until the database update is committed.
    |
    */

    $conn->commit();
    $transactionStarted = false;

    /*
    |--------------------------------------------------------------------------
    | 18. Notify Patient
    |--------------------------------------------------------------------------
    |
    | Email failure must not reverse the committed booking update.
    |
    */

    $emailSent = notifyPatientOfClinicReschedule(
        $booking,
        $newBookingDate,
        $newBookingTime,
        $rescheduleReason,
        (string) ($mailFromAddress ?? ''),
        (string) ($mailFromName ?? 'Talaue 88 Dental Clinic')
    );

    /*
    |--------------------------------------------------------------------------
    | 19. Success or Notification Warning
    |--------------------------------------------------------------------------
    */

    if ($emailSent) {
        setFlash(
            'success',
            'Appointment rescheduled successfully. The new schedule is now official.'
        );
    } else {
        setFlash(
            'warning',
            'Appointment rescheduled successfully, but the patient notification email could not be sent. Please check the server error log.'
        );
    }
} catch (Throwable $e) {
    /*
    |--------------------------------------------------------------------------
    | 20. Roll Back Only an Active Transaction
    |--------------------------------------------------------------------------
    */

    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(
                'Clinic rescheduling rollback error: ' .
                    $rollbackException->getMessage()
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 21. Log Error and Set Flash Message
    |--------------------------------------------------------------------------
    */

    error_log(
        'Clinic booking rescheduling error: ' . $e->getMessage()
    );

    if ($e instanceof RuntimeException) {
        setFlash('danger', $e->getMessage());
    } else {
        setFlash(
            'danger',
            'Unable to reschedule the appointment right now. Please try again later.'
        );
    }
}

/*
|--------------------------------------------------------------------------
| 22. Return to Bookings Page
|--------------------------------------------------------------------------
*/

header('Location: ../admin/bookings');
exit;
