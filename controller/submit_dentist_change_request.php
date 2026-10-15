<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/mail_config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---------------------------------------------------------
// 1. JSON response helper
// ---------------------------------------------------------

function respond(
    bool $success,
    string $message,
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode(
        [
            'success' => $success,
            'message' => $message
        ],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}

// ---------------------------------------------------------
// 2. HTML escaping helper
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
// 3. Notify administrators about a dentist-change request
// ---------------------------------------------------------

// ---------------------------------------------------------
// Notify administrators about a dentist-change request in-app
// ---------------------------------------------------------

function createAdminDentistChangeNotifications(
    mysqli $conn,
    int $bookingId,
    string $referenceNumber,
    string $patientName,
    string $requestedDentist,
    string $reason
): void {
    // Retrieve administrators only (role_id = 1).
    $stmt = $conn->prepare("
        SELECT id
        FROM dentists
        WHERE role_id = 1
    ");

    $stmt->execute();

    $result = $stmt->get_result();
    $adminIds = [];

    while ($admin = $result->fetch_assoc()) {
        $adminIds[] = (int) $admin['id'];
    }

    $stmt->close();

    if (empty($adminIds)) {
        error_log(
            'Dentist-change in-app notification: no administrators found.'
        );

        return;
    }

    $notificationType = 'dentist_change_requested';
    $title = 'Dentist Change Request';

    $message = sprintf(
        'Patient %s requested a dentist change for appointment %s to %s. Reason: %s',
        $patientName,
        $referenceNumber,
        $requestedDentist,
        $reason
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
        ) VALUES (?, ?, NULL, ?, ?, ?, 0)
    ");

    foreach ($adminIds as $adminId) {
        $stmt->bind_param(
            'iisss',
            $adminId,
            $bookingId,
            $notificationType,
            $title,
            $message
        );

        $stmt->execute();
    }

    $stmt->close();
}

function notifyAdminOfDentistChange(
    mysqli $conn,
    array $details,
    string $mailFromAddress,
    string $mailFromName
): void {

    /*
     * Dentist-change requests are sent to administrators only.
     * role_id = 1: Administrator
     *
     * Email addresses are encrypted in the database.
     */

    $fromAddress = trim($mailFromAddress);
    $fromName = trim($mailFromName);

    if (
        filter_var($fromAddress, FILTER_VALIDATE_EMAIL) === false ||
        $fromName === ''
    ) {
        error_log(
            'Dentist-change notification: invalid sender configuration.'
        );

        return;
    }

    // -----------------------------------------------------
    // Retrieve administrator accounts
    // -----------------------------------------------------

    $stmt = $conn->prepare("
        SELECT id, email
        FROM dentists
        WHERE role_id = 1
          AND email IS NOT NULL
          AND TRIM(email) <> ''
    ");

    $stmt->execute();

    $result = $stmt->get_result();
    $administrators = [];

    while ($admin = $result->fetch_assoc()) {
        $administrators[] = $admin;
    }

    $stmt->close();

    if (empty($administrators)) {
        error_log(
            'Dentist-change notification: no administrator accounts found.'
        );

        return;
    }

    // -----------------------------------------------------
    // Prepare notification details
    // -----------------------------------------------------

    $referenceNumber = (string) $details['reference_number'];
    $patientName = (string) $details['patient_name'];
    $bookingDate = (string) $details['booking_date'];

    // Format appointment time as 12-hour time with AM/PM.
    $rawBookingTime = (string) $details['booking_time'];

    $timestamp = strtotime($rawBookingTime);

    $bookingTime = $timestamp !== false
        ? date('g:i A', $timestamp)
        : $rawBookingTime;

    $currentDentist = (string) $details['current_dentist'];
    $requestedDentist = (string) $details['requested_dentist'];
    $reason = (string) $details['reason'];

    $subject = 'Dentist Change Request Pending Approval - ' .
        $referenceNumber;

    // -----------------------------------------------------
    // HTML email
    // -----------------------------------------------------

    $htmlBody = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport"
              content="width=device-width, initial-scale=1.0">
        <title>Dentist Change Request</title>
    </head>

    <body style="margin:0;padding:24px;background:#f4f6f9;
                 font-family:Arial,Helvetica,sans-serif;color:#263238;">

        <div style="max-width:620px;margin:0 auto;background:#ffffff;
                    border:1px solid #e0e5eb;border-radius:10px;
                    overflow:hidden;">

            <div style="padding:24px;background:#fff4d6;
                        border-bottom:1px solid #f2dfaa;">

                <h2 style="margin:0;color:#8a5b00;font-size:22px;">
                    Dentist Change Request
                </h2>

                <p style="margin:10px 0 0;color:#654b20;font-size:14px;">
                    A patient has submitted a request to change their
                    dentist. Administrator review is required.
                </p>

            </div>

            <div style="padding:24px;">

                <p style="margin-top:0;">Hello Administrator,</p>

                <p>
                    A new dentist-change request has been submitted
                    through the Talaue 88 Dental Clinic website.
                    Please review the request in the administration panel.
                </p>

                <p style="display:inline-block;padding:8px 12px;
                          background:#fff4d6;color:#8a5b00;
                          border-radius:6px;font-size:13px;font-weight:bold;">
                    Pending Approval
                </p>

                <table role="presentation"
                       style="width:100%;border-collapse:collapse;
                              font-size:14px;">

                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;
                                   font-weight:bold;width:42%;">
                            Appointment Reference
                        </td>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;">
                            ' . escapeEmailHtml($referenceNumber) . '
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;
                                   font-weight:bold;">
                            Patient Name
                        </td>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;">
                            ' . escapeEmailHtml($patientName) . '
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;
                                   font-weight:bold;">
                            Appointment Date
                        </td>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;">
                            ' . escapeEmailHtml($bookingDate) . '
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;
                                   font-weight:bold;">
                            Appointment Time
                        </td>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;">
                            ' . escapeEmailHtml($bookingTime) . '
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;
                                   font-weight:bold;">
                            Current Dentist
                        </td>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;">
                            ' . escapeEmailHtml($currentDentist) . '
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;
                                   font-weight:bold;">
                            Requested Dentist
                        </td>
                        <td style="padding:12px 8px;border-bottom:1px solid #eee;">
                            ' . escapeEmailHtml($requestedDentist) . '
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:12px 8px;font-weight:bold;
                                   vertical-align:top;">
                            Patient Reason
                        </td>
                        <td style="padding:12px 8px;">
                            ' . nl2br(escapeEmailHtml($reason)) . '
                        </td>
                    </tr>

                </table>

                <p style="margin:24px 0 0;font-size:13px;color:#667085;">
                    Please sign in to the Talaue 88 Dental Clinic
                    administration panel to review this request.
                </p>

                <p style="margin:16px 0 0;font-size:12px;color:#98a2b3;">
                    This is an automated notification. Please do not
                    reply directly to this email.
                </p>

            </div>
        </div>

    </body>
    </html>';

    // -----------------------------------------------------
    // Plain-text email fallback
    // -----------------------------------------------------

    $plainBody =
        "Talaue 88 Dental Clinic\n\n" .
        "A patient has submitted a dentist-change request.\n\n" .
        "Status: Pending Approval\n" .
        "Appointment Reference: {$referenceNumber}\n" .
        "Patient Name: {$patientName}\n" .
        "Appointment Date: {$bookingDate}\n" .
        "Appointment Time: {$bookingTime}\n" .
        "Current Dentist: {$currentDentist}\n" .
        "Requested Dentist: {$requestedDentist}\n\n" .
        "Patient Reason:\n{$reason}\n\n" .
        "Please sign in to the administration panel to review this request.\n";

    // -----------------------------------------------------
    // Construct multipart email
    // -----------------------------------------------------

    $boundary = '=_Talaue88_' . bin2hex(random_bytes(16));

    $encodedSubject = '=?UTF-8?B?' .
        base64_encode($subject) .
        '?=';

    $encodedFromName = '=?UTF-8?B?' .
        base64_encode($fromName) .
        '?=';

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $encodedFromName . ' <' . $fromAddress . '>',
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
    // Send one email per administrator
    // -----------------------------------------------------

    foreach ($administrators as $admin) {

        $adminId = (int) $admin['id'];

        try {

            $decryptedEmail = decryptEmail(
                (string) $admin['email']
            );

            if (
                !is_string($decryptedEmail) ||
                filter_var(
                    trim($decryptedEmail),
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                error_log(
                    "Dentist-change notification: invalid or " .
                        "undecryptable email for administrator ID {$adminId}."
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
                    "Dentist-change notification: mail() failed " .
                        "for administrator ID {$adminId}."
                );
            }
        } catch (Throwable $e) {

            error_log(
                "Dentist-change notification failed for administrator " .
                    "ID {$adminId}: " . $e->getMessage()
            );
        }
    }
}

// ---------------------------------------------------------
// 4. Allow POST requests only
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');

    respond(false, 'Invalid request method.', 405);
}

// ---------------------------------------------------------
// 5. Validate CSRF token
// ---------------------------------------------------------

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    !is_string($csrfToken) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    respond(
        false,
        'Your session has expired. Please refresh the page and try again.',
        403
    );
}

// ---------------------------------------------------------
// 6. Validate input
// ---------------------------------------------------------

$referenceNumber = $_POST['reference_number'] ?? '';

if (
    !is_string($referenceNumber) ||
    trim($referenceNumber) === '' ||
    strlen(trim($referenceNumber)) > 100
) {
    respond(false, 'Invalid appointment reference number.', 422);
}

$referenceNumber = trim($referenceNumber);

$requestedDentistId = filter_var(
    $_POST['dentist_id'] ?? null,
    FILTER_VALIDATE_INT
);

$reason = $_POST['reason'] ?? '';

if (
    !is_string($reason) ||
    $requestedDentistId === false ||
    $requestedDentistId === null ||
    $requestedDentistId <= 0
) {
    respond(
        false,
        'Please provide a valid appointment, dentist, and reason.',
        422
    );
}

$reason = trim($reason);

if ($reason === '') {
    respond(
        false,
        'Please provide a reason for the dentist change.',
        422
    );
}

if (mb_strlen($reason) > 500) {
    respond(
        false,
        'The reason must be 500 characters or fewer.',
        422
    );
}

// ---------------------------------------------------------
// 7. Begin transaction
// ---------------------------------------------------------

$transactionStarted = false;

try {

    $conn->begin_transaction();
    $transactionStarted = true;

    // Lock the booking while validating the request.

    $stmt = $conn->prepare("
        SELECT
            id,
            reference_number,
            full_name,
            dentist_id,
            branch_id,
            booking_date,
            booking_time,
            status
        FROM bookings
        WHERE reference_number = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param('s', $referenceNumber);
    $stmt->execute();

    $booking = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$booking) {
        throw new RuntimeException(
            'Appointment could not be found.'
        );
    }

    if ($booking['status'] !== 'Pending') {
        throw new RuntimeException(
            'A dentist change request can only be submitted while the appointment is pending.'
        );
    }

    $bookingId = (int) $booking['id'];
    $currentDentistId = (int) $booking['dentist_id'];
    $branchId = (int) $booking['branch_id'];
    $bookingDate = (string) $booking['booking_date'];
    $bookingTime = (string) $booking['booking_time'];

    if ($requestedDentistId === $currentDentistId) {
        throw new RuntimeException(
            'Please select a different dentist.'
        );
    }

    // -----------------------------------------------------
    // 8. Verify requested dentist
    // -----------------------------------------------------

    $stmt = $conn->prepare("
        SELECT id, dentist_name
        FROM dentists
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $requestedDentistId);
    $stmt->execute();

    $requestedDentist = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$requestedDentist) {
        throw new RuntimeException(
            'The requested dentist could not be found.'
        );
    }

    // -----------------------------------------------------
    // 9. Retrieve current dentist name
    // -----------------------------------------------------

    $stmt = $conn->prepare("
        SELECT dentist_name
        FROM dentists
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $currentDentistId);
    $stmt->execute();

    $currentDentist = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$currentDentist) {
        throw new RuntimeException(
            'The current dentist could not be found.'
        );
    }

    // -----------------------------------------------------
    // 10. Check requested dentist availability
    // -----------------------------------------------------

    $stmt = $conn->prepare("
        SELECT id
        FROM bookings
        WHERE dentist_id = ?
          AND branch_id = ?
          AND booking_date = ?
          AND booking_time = ?
          AND id <> ?
          AND status IN ('Pending', 'Approved')
        LIMIT 1
    ");

    $stmt->bind_param(
        'iissi',
        $requestedDentistId,
        $branchId,
        $bookingDate,
        $bookingTime,
        $bookingId
    );

    $stmt->execute();

    $conflictingBooking = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if ($conflictingBooking) {
        throw new RuntimeException(
            'The selected dentist is already booked at this branch, date, and time. Please choose another dentist.'
        );
    }

    // -----------------------------------------------------
    // 11. Check for an existing pending request
    // -----------------------------------------------------

    $stmt = $conn->prepare("
        SELECT id
        FROM doctor_change_requests
        WHERE booking_id = ?
          AND status = 'Pending Approval'
        LIMIT 1
    ");

    $stmt->bind_param('i', $bookingId);
    $stmt->execute();

    $pendingRequest = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if ($pendingRequest) {

        $conn->rollback();
        $transactionStarted = false;

        http_response_code(409);

        echo json_encode(
            [
                'success' => false,
                'already_requested' => true,
                'message' =>
                'A dentist change request is already pending for this appointment.'
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        exit;
    }

    // -----------------------------------------------------
    // 12. Insert dentist-change request
    // -----------------------------------------------------

    $status = 'Pending Approval';

    $stmt = $conn->prepare("
        INSERT INTO doctor_change_requests (
            booking_id,
            current_dentist_id,
            requested_dentist_id,
            reason,
            status
        ) VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'iiiss',
        $bookingId,
        $currentDentistId,
        $requestedDentistId,
        $reason,
        $status
    );

    $stmt->execute();
    $stmt->close();

    // -----------------------------------------------------
    // 13. Commit before sending email
    // -----------------------------------------------------

    $conn->commit();
    $transactionStarted = false;

    // -----------------------------------------------------
    // 14. Notify administrators using mail_config.php
    // -----------------------------------------------------

    // 14.1 Create in-app notifications for administrators only.
    try {
        createAdminDentistChangeNotifications(
            $conn,
            $bookingId,
            $referenceNumber,
            (string) $booking['full_name'],
            (string) $requestedDentist['dentist_name'],
            $reason
        );
    } catch (Throwable $notificationError) {
        error_log(
            'Dentist-change in-app notification failed: ' .
                $notificationError->getMessage()
        );
    }

    //14.2 email
    try {

        notifyAdminOfDentistChange(
            $conn,
            [
                'reference_number' => $referenceNumber,
                'patient_name' => (string) $booking['full_name'],
                'booking_date' => $bookingDate,
                'booking_time' => $bookingTime,
                'current_dentist' =>
                (string) $currentDentist['dentist_name'],
                'requested_dentist' =>
                (string) $requestedDentist['dentist_name'],
                'reason' => $reason,
            ],
            $mailFromAddress,
            $mailFromName
        );
    } catch (Throwable $emailError) {

        // Do not undo a successfully committed request because
        // email notification failed.

        error_log(
            'Dentist-change notification processing failed: ' .
                $emailError->getMessage()
        );
    }

    // -----------------------------------------------------
    // 15. Return AJAX success response
    // -----------------------------------------------------

    respond(
        true,
        'Your dentist change request has been submitted successfully.'
    );
} catch (Throwable $error) {

    if ($transactionStarted) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log(
                'Dentist-change transaction rollback failed: ' .
                    $rollbackError->getMessage()
            );
        }
    }

    if ($error instanceof RuntimeException) {
        respond(false, $error->getMessage(), 422);
    }

    error_log(
        'Dentist-change request submission failed: ' .
            $error->getMessage()
    );

    respond(
        false,
        'Unable to submit the dentist change request. Please try again.',
        500
    );
}
