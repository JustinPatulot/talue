<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helper/flash_helper.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/mail_config.php';

date_default_timezone_set('Asia/Manila');

$redirectUrl = '../admin/bookings';

/*
|--------------------------------------------------------------------------
| Email Helpers
|--------------------------------------------------------------------------
*/

function escapeFinalBookingEmailHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function formatFinalBookingEmailDate(string $date): string
{
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $parsedDate
        ? $parsedDate->format('F j, Y')
        : $date;
}

function formatFinalBookingEmailTime(string $time): string
{
    $parsedTime = DateTimeImmutable::createFromFormat('!H:i:s', $time);

    if (!$parsedTime) {
        $parsedTime = DateTimeImmutable::createFromFormat('!H:i', $time);
    }

    return $parsedTime
        ? $parsedTime->format('g:i A')
        : $time;
}

/*
|--------------------------------------------------------------------------
| Notify Patient of Final Appointment Status
|--------------------------------------------------------------------------
|
| Completed = completion notification
| No Show   = no-show notification
|
| Returns true if mail() accepts the message.
| This does not guarantee delivery to the patient's inbox.
|
*/

function notifyPatientOfFinalBookingStatus(
    array $booking,
    string $encryptedEmail,
    string $status
): bool {
    global $mailFromAddress, $mailFromName;

    try {
        if (
            empty($mailFromAddress) ||
            empty($mailFromName) ||
            !filter_var($mailFromAddress, FILTER_VALIDATE_EMAIL)
        ) {
            error_log(
                'Final booking email failed: Invalid sender configuration.'
            );

            return false;
        }

        if (!in_array($status, ['Completed', 'No Show'], true)) {
            error_log('Final booking email failed: Invalid status.');
            return false;
        }

        // Decrypt the patient's stored email address.
        $patientEmail = decryptEmail($encryptedEmail);

        if (
            !is_string($patientEmail) ||
            !filter_var($patientEmail, FILTER_VALIDATE_EMAIL)
        ) {
            error_log(
                'Final booking email failed: Invalid patient email. Booking ID: '
                    . (int) ($booking['id'] ?? 0)
            );

            return false;
        }

        $patientName = trim(
            (string) ($booking['full_name'] ?? 'Patient')
        );

        if ($patientName === '') {
            $patientName = 'Patient';
        }

        $referenceNumber = (string) ($booking['reference_number'] ?? '');

        $appointmentDate = formatFinalBookingEmailDate(
            (string) ($booking['booking_date'] ?? '')
        );

        $appointmentTime = formatFinalBookingEmailTime(
            (string) ($booking['booking_time'] ?? '')
        );

        /*
        |--------------------------------------------------------------------------
        | Status-Specific Email Content
        |--------------------------------------------------------------------------
        */

        if ($status === 'Completed') {
            $subject = 'Appointment Completed - Talaue 88 Dental Clinic';

            $plainText =
                "Hello {$patientName},\n\n"
                . "Thank you for visiting Talaue 88 Dental Clinic. "
                . "Your appointment has been marked as completed.\n\n"
                . "Appointment Details\n"
                . "Reference Number: {$referenceNumber}\n"
                . "Date: {$appointmentDate}\n"
                . "Time: {$appointmentTime}\n"
                . "Status: Completed\n\n"
                . "Thank you for choosing Talaue 88 Dental Clinic. "
                . "We appreciate your trust in our care.\n\n"
                . "Designing Smiles, Defining Confidence";

            $heading = 'Appointment Completed';

            $messageText =
                'Thank you for visiting Talaue 88 Dental Clinic. '
                . 'Your appointment has been marked as completed.';
        } else {
            $subject = 'Appointment No-Show Notice - Talaue 88 Dental Clinic';

            $plainText =
                "Hello {$patientName},\n\n"
                . "Our records indicate that you did not attend your "
                . "scheduled appointment at Talaue 88 Dental Clinic. "
                . "The appointment has been marked as No Show.\n\n"
                . "Appointment Details\n"
                . "Reference Number: {$referenceNumber}\n"
                . "Date: {$appointmentDate}\n"
                . "Time: {$appointmentTime}\n"
                . "Status: No Show\n\n"
                . "If you believe this record is incorrect or would like "
                . "to arrange another appointment, please contact the clinic.\n\n"
                . "Thank you,\n"
                . "Talaue 88 Dental Clinic\n\n"
                . "Designing Smiles, Defining Confidence";

            $heading = 'Appointment No-Show Notice';

            $messageText =
                'Our records indicate that you did not attend your scheduled '
                . 'appointment. The appointment has been marked as No Show. '
                . 'If you believe this record is incorrect or would like to '
                . 'arrange another appointment, please contact the clinic.';
        }

        /*
        |--------------------------------------------------------------------------
        | HTML Email
        |--------------------------------------------------------------------------
        */

        $safePatientName = escapeFinalBookingEmailHtml($patientName);
        $safeReferenceNumber = escapeFinalBookingEmailHtml($referenceNumber);
        $safeAppointmentDate = escapeFinalBookingEmailHtml($appointmentDate);
        $safeAppointmentTime = escapeFinalBookingEmailHtml($appointmentTime);
        $safeHeading = escapeFinalBookingEmailHtml($heading);
        $safeMessageText = escapeFinalBookingEmailHtml($messageText);
        $safeStatus = escapeFinalBookingEmailHtml($status);

        $html =
            '<!DOCTYPE html>'
            . '<html lang="en">'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $safeHeading . '</title>'
            . '</head>'
            . '<body style="margin:0;padding:24px;background:#f4f6f8;'
            . 'font-family:Arial,Helvetica,sans-serif;color:#263238;">'
            . '<div style="max-width:600px;margin:0 auto;background:#ffffff;'
            . 'border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">'
            . '<div style="padding:24px;background:#176b5b;color:#ffffff;">'
            . '<h1 style="margin:0;font-size:24px;">'
            . 'Talaue 88 Dental Clinic</h1>'
            . '<p style="margin:8px 0 0;">'
            . 'Designing Smiles, Defining Confidence</p>'
            . '</div>'
            . '<div style="padding:28px;">'
            . '<h2 style="margin-top:0;color:#176b5b;">'
            . $safeHeading . '</h2>'
            . '<p>Hello ' . $safePatientName . ',</p>'
            . '<p>' . $safeMessageText . '</p>'
            . '<div style="padding:18px;background:#f8fafc;'
            . 'border:1px solid #e5e7eb;border-radius:8px;">'
            . '<p><strong>Reference Number:</strong> '
            . $safeReferenceNumber . '</p>'
            . '<p><strong>Date:</strong> '
            . $safeAppointmentDate . '</p>'
            . '<p><strong>Time:</strong> '
            . $safeAppointmentTime . '</p>'
            . '<p><strong>Status:</strong> '
            . '<span style="font-weight:bold;">'
            . $safeStatus . '</span></p>'
            . '</div>'
            . '<p>Thank you for choosing Talaue 88 Dental Clinic.</p>'
            . '<p style="margin-bottom:0;color:#64748b;">'
            . '<strong>Designing Smiles, Defining Confidence</strong>'
            . '</p>'
            . '</div></div></body></html>';

        /*
        |--------------------------------------------------------------------------
        | Multipart Email
        |--------------------------------------------------------------------------
        */

        $boundary = '=_Talaue88Final_' . bin2hex(random_bytes(16));

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . mb_encode_mimeheader($mailFromName, 'UTF-8')
                . ' <' . $mailFromAddress . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        $message =
            '--' . $boundary . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $plainText . "\r\n"
            . '--' . $boundary . "\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: 8bit\r\n\r\n"
            . $html . "\r\n"
            . '--' . $boundary . "--\r\n";

        $sent = mail(
            $patientEmail,
            mb_encode_mimeheader($subject, 'UTF-8'),
            $message,
            implode("\r\n", $headers)
        );

        if (!$sent) {
            error_log(
                'Final booking email was not accepted by the mail server. '
                    . 'Booking ID: ' . (int) ($booking['id'] ?? 0)
                    . '; Status: ' . $status
            );
        }

        return $sent;
    } catch (Throwable $exception) {
        error_log(
            'Final booking email error. Booking ID: '
                . (int) ($booking['id'] ?? 0)
                . '; Status: ' . $status
                . '; Error: ' . $exception->getMessage()
        );

        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('danger', 'Invalid request method.');
    header('Location: ' . $redirectUrl);
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
    !is_string($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    setFlash(
        'danger',
        'Invalid security token. Please try again.'
    );

    header('Location: ' . $redirectUrl);
    exit;
}

/*
|--------------------------------------------------------------------------
| Input Validation
|--------------------------------------------------------------------------
*/

$bookingId = filter_input(
    INPUT_POST,
    'booking_id',
    FILTER_VALIDATE_INT
);

$status = $_POST['status'] ?? '';

if (
    $bookingId === false ||
    $bookingId === null ||
    $bookingId <= 0
) {
    setFlash('danger', 'Invalid booking ID.');
    header('Location: ' . $redirectUrl);
    exit;
}

$allowedStatuses = [
    'Completed',
    'No Show',
];

if (
    !is_string($status) ||
    !in_array($status, $allowedStatuses, true)
) {
    setFlash('danger', 'Invalid appointment status.');
    header('Location: ' . $redirectUrl);
    exit;
}

/*
|--------------------------------------------------------------------------
| Verify and Update Booking
|--------------------------------------------------------------------------
*/

$transactionStarted = false;
$transactionCommitted = false;

try {
    $conn->begin_transaction();
    $transactionStarted = true;

    // Lock the booking while checking and updating its status.
    $stmt = $conn->prepare("
        SELECT
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
        FOR UPDATE
    ");

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();

    $result = $stmt->get_result();
    $booking = $result->fetch_assoc();

    $stmt->close();

    if (!$booking) {
        $conn->rollback();
        $transactionStarted = false;

        setFlash('danger', 'Booking not found.');
        header('Location: ' . $redirectUrl);
        exit;
    }

    // Only Approved or Rescheduled bookings may be finalized.
    if (
        $booking['status'] !== 'Approved' &&
        $booking['status'] !== 'Rescheduled'
    ) {
        $conn->rollback();
        $transactionStarted = false;

        setFlash(
            'danger',
            'Only approved or rescheduled appointments can be updated.'
        );

        header('Location: ' . $redirectUrl);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE bookings
        SET status = ?
        WHERE id = ?
          AND status IN ('Approved', 'Rescheduled')
    ");

    $stmt->bind_param('si', $status, $bookingId);
    $stmt->execute();

    $updated = ($stmt->affected_rows === 1);

    $stmt->close();

    if (!$updated) {
        $conn->rollback();
        $transactionStarted = false;

        setFlash(
            'danger',
            'The appointment could not be updated. Its status may have changed.'
        );

        header('Location: ' . $redirectUrl);
        exit;
    }

    // Save the status before attempting to send an email.
    $conn->commit();
    $transactionCommitted = true;
    $transactionStarted = false;

    /*
    |--------------------------------------------------------------------------
    | Send Status-Specific Patient Email
    |--------------------------------------------------------------------------
    */

    $emailSent = notifyPatientOfFinalBookingStatus(
        $booking,
        (string) $booking['email'],
        $status
    );

    if ($emailSent) {
        if ($status === 'Completed') {
            setFlash(
                'success',
                'Appointment marked as completed.'
            );
        } else {
            setFlash(
                'success',
                'Appointment marked as No Show.'
            );
        }
    } else {
        if ($status === 'Completed') {
            setFlash(
                'warning',
                'Appointment marked as completed, but the patient email could not be sent. Please check the email configuration and server logs.'
            );
        } else {
            setFlash(
                'warning',
                'Appointment marked as No Show, but the patient email could not be sent. Please check the email configuration and server logs.'
            );
        }
    }
} catch (Throwable $exception) {
    if ($transactionStarted && !$transactionCommitted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(
                'Final booking rollback error: '
                    . $rollbackException->getMessage()
            );
        }
    }

    error_log(
        'Final booking status update failed. Booking ID: '
            . (int) $bookingId
            . '; Error: ' . $exception->getMessage()
    );

    setFlash(
        'danger',
        'An unexpected error occurred while updating the appointment.'
    );
}

header('Location: ' . $redirectUrl);
exit;
