<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../helper/flash_helper.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/mail_config.php';

date_default_timezone_set('Asia/Manila');

$redirectUrl = '../admin/bookings';

/**
 * Escape values before inserting them into HTML email content.
 */
function escapeApprovalEmailHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Format the appointment date for the email.
 */
function formatApprovalEmailDate(string $date): string
{
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $parsedDate
        ? $parsedDate->format('F j, Y')
        : $date;
}

/**
 * Format the appointment time for the email.
 */
function formatApprovalEmailTime(string $time): string
{
    $parsedTime = DateTimeImmutable::createFromFormat('!H:i:s', $time);

    if (!$parsedTime) {
        $parsedTime = DateTimeImmutable::createFromFormat('!H:i', $time);
    }

    return $parsedTime
        ? $parsedTime->format('g:i A')
        : $time;
}

/**
 * Notify the patient that their appointment has been approved.
 *
 * Returns true when PHP's mail() function accepts the message.
 * This does not guarantee final delivery to the patient's inbox.
 */
function notifyPatientOfApproval(
    array $booking,
    string $encryptedEmail
): bool {
    global $mailFromAddress, $mailFromName;

    try {
        if (
            empty($mailFromAddress) ||
            empty($mailFromName) ||
            !filter_var($mailFromAddress, FILTER_VALIDATE_EMAIL)
        ) {
            error_log('Booking approval email failed: Invalid sender configuration.');
            return false;
        }

        // Decrypt the patient's stored email address.
        $patientEmail = decryptEmail($encryptedEmail);

        if (
            !is_string($patientEmail) ||
            !filter_var($patientEmail, FILTER_VALIDATE_EMAIL)
        ) {
            error_log(
                'Booking approval email failed: Invalid patient email for booking ID '
                    . (int) $booking['id']
            );

            return false;
        }

        $patientName = trim((string) ($booking['full_name'] ?? 'Patient'));

        if ($patientName === '') {
            $patientName = 'Patient';
        }

        $referenceNumber = (string) ($booking['reference_number'] ?? '');
        $appointmentDate = formatApprovalEmailDate(
            (string) ($booking['booking_date'] ?? '')
        );
        $appointmentTime = formatApprovalEmailTime(
            (string) ($booking['booking_time'] ?? '')
        );

        $subject = 'Appointment Approved - Talaue 88 Dental Clinic';

        $boundary = '=_Talaue88Approval_' . bin2hex(random_bytes(16));

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . mb_encode_mimeheader($mailFromName, 'UTF-8')
                . ' <' . $mailFromAddress . '>',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        $plainText =
            "Hello {$patientName},\n\n"
            . "Good news! Your appointment at Talaue 88 Dental Clinic "
            . "has been approved.\n\n"
            . "Appointment Details\n"
            . "Reference Number: {$referenceNumber}\n"
            . "Date: {$appointmentDate}\n"
            . "Time: {$appointmentTime}\n"
            . "Status: Approved\n\n"
            . "Please keep your reference number for future inquiries. "
            . "If you need to make changes to your appointment, please "
            . "use the clinic's appointment status page or contact the clinic.\n\n"
            . "Thank you for choosing Talaue 88 Dental Clinic.\n\n"
            . "Designing Smiles, Defining Confidence";

        $safePatientName = escapeApprovalEmailHtml($patientName);
        $safeReferenceNumber = escapeApprovalEmailHtml($referenceNumber);
        $safeAppointmentDate = escapeApprovalEmailHtml($appointmentDate);
        $safeAppointmentTime = escapeApprovalEmailHtml($appointmentTime);

        $html =
            '<!DOCTYPE html>'
            . '<html lang="en">'
            . '<head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Appointment Approved</title></head>'
            . '<body style="margin:0;padding:24px;background:#f4f6f8;'
            . 'font-family:Arial,Helvetica,sans-serif;color:#263238;">'
            . '<div style="max-width:600px;margin:0 auto;background:#ffffff;'
            . 'border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">'
            . '<div style="padding:24px;background:#176b5b;color:#ffffff;">'
            . '<h1 style="margin:0;font-size:24px;">Talaue 88 Dental Clinic</h1>'
            . '<p style="margin:8px 0 0;">Designing Smiles, Defining Confidence</p>'
            . '</div>'
            . '<div style="padding:28px;">'
            . '<h2 style="margin-top:0;color:#176b5b;">Appointment Approved</h2>'
            . '<p>Hello ' . $safePatientName . ',</p>'
            . '<p>Good news! Your appointment has been approved by the clinic.</p>'
            . '<div style="padding:18px;background:#f8fafc;'
            . 'border:1px solid #e5e7eb;border-radius:8px;">'
            . '<p><strong>Reference Number:</strong> ' . $safeReferenceNumber . '</p>'
            . '<p><strong>Date:</strong> ' . $safeAppointmentDate . '</p>'
            . '<p><strong>Time:</strong> ' . $safeAppointmentTime . '</p>'
            . '<p><strong>Status:</strong> '
            . '<span style="color:#176b5b;font-weight:bold;">Approved</span></p>'
            . '</div>'
            . '<p>Please keep your reference number for future inquiries. '
            . 'If you need to make changes to your appointment, please use '
            . 'the clinic\'s appointment status page or contact the clinic.</p>'
            . '<p>Thank you for choosing Talaue 88 Dental Clinic.</p>'
            . '<p style="margin-bottom:0;color:#64748b;">'
            . '<strong>Designing Smiles, Defining Confidence</strong></p>'
            . '</div></div></body></html>';

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
                'Booking approval email could not be accepted by the mail server. Booking ID: '
                    . (int) $booking['id']
            );
        }

        return $sent;
    } catch (Throwable $exception) {
        error_log(
            'Booking approval email error for booking ID '
                . (int) ($booking['id'] ?? 0)
                . ': ' . $exception->getMessage()
        );

        return false;
    }
}

// Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $redirectUrl);
    exit;
}

// Validate CSRF token.
if (
    !isset($_SESSION['csrf_token'], $_POST['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    setFlash('danger', 'Invalid security token.');
    header('Location: ' . $redirectUrl);
    exit;
}

// Validate booking ID.
$bookingId = filter_input(
    INPUT_POST,
    'booking_id',
    FILTER_VALIDATE_INT
);

if (!$bookingId || $bookingId <= 0) {
    setFlash('danger', 'Invalid booking.');
    header('Location: ' . $redirectUrl);
    exit;
}

try {
    // Lock the booking to prevent simultaneous status changes.
    $conn->begin_transaction();

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

        setFlash('danger', 'Booking not found.');
        header('Location: ' . $redirectUrl);
        exit;
    }

    // Only Pending appointments can be approved.
    if ($booking['status'] !== 'Pending') {
        $conn->rollback();

        setFlash(
            'danger',
            'Only pending appointments can be approved.'
        );

        header('Location: ' . $redirectUrl);
        exit;
    }

    // Update the status using the exact enum capitalization.
    $stmt = $conn->prepare("
        UPDATE bookings
        SET status = 'Approved'
        WHERE id = ?
          AND status = 'Pending'
    ");

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();

    $approved = ($stmt->affected_rows === 1);

    $stmt->close();

    if (!$approved) {
        $conn->rollback();

        setFlash(
            'danger',
            'The appointment could not be approved. It may have already been updated.'
        );

        header('Location: ' . $redirectUrl);
        exit;
    }

    // Commit the approval before attempting to send the email.
    $conn->commit();

    // Email failure must not undo a successful approval.
    $emailSent = notifyPatientOfApproval(
        $booking,
        (string) $booking['email']
    );

    if ($emailSent) {
        setFlash(
            'success',
            'Appointment approved successfully.'
        );
    } else {
        setFlash(
            'warning',
            'Appointment approved successfully, but the patient notification could not be sent. Please check the email configuration and server logs.'
        );
    }
} catch (Throwable $exception) {
    try {
        if ($conn->errno === 0 || $conn->ping()) {
            $conn->rollback();
        }
    } catch (Throwable $rollbackException) {
        error_log(
            'Booking approval rollback error: '
                . $rollbackException->getMessage()
        );
    }

    error_log(
        'Booking approval error for booking ID '
            . (int) $bookingId
            . ': ' . $exception->getMessage()
    );

    setFlash(
        'danger',
        'An unexpected error occurred while approving the appointment.'
    );
}

header('Location: ' . $redirectUrl);
exit;
