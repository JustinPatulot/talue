<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/flash_helper.php';
require_once __DIR__ . '/../helper/mail_config.php';

date_default_timezone_set('Asia/Manila');

$timezone = new DateTimeZone('Asia/Manila');
$redirectUrl = '../appointment.php';

$clinicOpeningTime = '10:00';
$clinicClosingTime = '19:00';

/*
|--------------------------------------------------------------------------
| Shared Helpers
|--------------------------------------------------------------------------
*/

function bookingFormRedirect(
    string $type,
    string $message,
    string $url = '../appointment.php'
): never {
    setFlash($type, $message);
    header('Location: ' . $url);
    exit;
}

function escapeBookingEmailHtml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function formatBookingEmailDate(string $date): string
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);

    return $parsed ? $parsed->format('F j, Y') : $date;
}

function formatBookingEmailTime(string $time): string
{
    $parsed = DateTimeImmutable::createFromFormat('!H:i:s', $time);

    if (!$parsed) {
        $parsed = DateTimeImmutable::createFromFormat('!H:i', $time);
    }

    return $parsed ? $parsed->format('g:i A') : $time;
}

/*
|--------------------------------------------------------------------------
| Email Helper
|--------------------------------------------------------------------------
*/

function sendClinicBookingEmail(
    string $recipient,
    string $subject,
    string $plainText,
    string $html
): bool {
    global $mailFromAddress, $mailFromName;

    try {
        if (
            empty($mailFromAddress) ||
            empty($mailFromName) ||
            !filter_var($mailFromAddress, FILTER_VALIDATE_EMAIL) ||
            !filter_var($recipient, FILTER_VALIDATE_EMAIL)
        ) {
            error_log(
                'Booking email failed: Invalid sender or recipient.'
            );

            return false;
        }

        $boundary = '=_Talaue88Booking_' . bin2hex(random_bytes(16));

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . mb_encode_mimeheader($mailFromName, 'UTF-8')
                . ' <' . $mailFromAddress . '>',
            'Reply-To: ' . $mailFromAddress,
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
            $recipient,
            mb_encode_mimeheader($subject, 'UTF-8'),
            $message,
            implode("\r\n", $headers)
        );

        if (!$sent) {
            error_log(
                'Booking email was not accepted by the mail server. '
                    . 'Recipient: ' . $recipient
            );
        }

        return $sent;
    } catch (Throwable $exception) {
        error_log(
            'Booking email error: ' . $exception->getMessage()
        );

        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Booking Email Details
|--------------------------------------------------------------------------
*/

function buildBookingEmailDetails(
    string $referenceNumber,
    string $fullName,
    string $bookingDate,
    string $bookingTime,
    string $status,
    string $serviceName,
    string $dentistName,
    string $branchName
): array {
    return [
        'reference' => $referenceNumber,
        'patient' => $fullName,
        'date' => formatBookingEmailDate($bookingDate),
        'time' => formatBookingEmailTime($bookingTime),
        'status' => $status,
        'service' => $serviceName,
        'dentist' => $dentistName,
        'branch' => $branchName,
    ];
}

/*
|--------------------------------------------------------------------------
| Build HTML Email
|--------------------------------------------------------------------------
*/

function buildBookingEmailHtml(
    string $heading,
    string $messageText,
    array $details
): string {
    $safeHeading = escapeBookingEmailHtml($heading);
    $safeMessage = escapeBookingEmailHtml($messageText);

    $html =
        '<!DOCTYPE html>'
        . '<html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<title>' . $safeHeading . '</title></head>'
        . '<body style="margin:0;padding:24px;background:#f4f6f8;'
        . 'font-family:Arial,Helvetica,sans-serif;color:#263238;">'
        . '<div style="max-width:600px;margin:0 auto;background:#ffffff;'
        . 'border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">'
        . '<div style="padding:24px;background:#176b5b;color:#ffffff;">'
        . '<h1 style="margin:0;font-size:24px;">Talaue 88 Dental Clinic</h1>'
        . '<p style="margin:8px 0 0;">Designing Smiles, Defining Confidence</p>'
        . '</div><div style="padding:28px;">'
        . '<h2 style="margin-top:0;color:#176b5b;">' . $safeHeading . '</h2>'
        . '<p>' . $safeMessage . '</p>'
        . '<div style="padding:18px;background:#f8fafc;'
        . 'border:1px solid #e5e7eb;border-radius:8px;">';

    $labels = [
        'reference' => 'Reference Number',
        'patient' => 'Patient',
        'date' => 'Appointment Date',
        'time' => 'Appointment Time',
        'status' => 'Status',
        'service' => 'Dental Service',
        'dentist' => 'Dentist',
        'branch' => 'Branch',
    ];

    foreach ($labels as $key => $label) {
        if (!isset($details[$key])) {
            continue;
        }

        $html .=
            '<p><strong>'
            . escapeBookingEmailHtml($label)
            . ':</strong> '
            . escapeBookingEmailHtml((string) $details[$key])
            . '</p>';
    }

    $html .=
        '</div><p>Thank you for choosing Talaue 88 Dental Clinic.</p>'
        . '<p style="margin-bottom:0;color:#64748b;">'
        . '<strong>Designing Smiles, Defining Confidence</strong>'
        . '</p></div></div></body></html>';

    return $html;
}

/*
|--------------------------------------------------------------------------
| Notify Patient of New Booking
|--------------------------------------------------------------------------
*/

function notifyPatientOfNewBooking(
    string $patientEmail,
    array $details
): bool {
    $subject =
        'Talaue 88 Dental Clinic - Appointment Booking Confirmation';

    $plainText =
        'Dear ' . $details['patient'] . ",\n\n"
        . "Thank you for booking an appointment with Talaue 88 Dental Clinic.\n"
        . "Your appointment request has been successfully submitted and is "
        . "currently pending confirmation from the clinic.\n\n"
        . "Appointment Details\n"
        . "Reference Number: " . $details['reference'] . "\n"
        . "Patient: " . $details['patient'] . "\n"
        . "Date: " . $details['date'] . "\n"
        . "Time: " . $details['time'] . "\n"
        . "Dental Service: " . $details['service'] . "\n"
        . "Dentist: " . $details['dentist'] . "\n"
        . "Branch: " . $details['branch'] . "\n"
        . "Status: Pending\n\n"
        . "Please keep your reference number for checking your appointment status.\n\n"
        . "Thank you,\nTalaue 88 Dental Clinic\n"
        . "Designing Smiles, Defining Confidence";

    $html = buildBookingEmailHtml(
        'Appointment Request Received',
        'Thank you for booking with Talaue 88 Dental Clinic. '
            . 'Your appointment request has been received and is pending confirmation.',
        $details
    );

    return sendClinicBookingEmail(
        $patientEmail,
        $subject,
        $plainText,
        $html
    );
}

/*
|--------------------------------------------------------------------------
| Email Admins and Dental Assistants
|--------------------------------------------------------------------------
|
| Role 1: Admin
| Role 2: Dental Assistant
|
| Staff email addresses are encrypted in the database.
|--------------------------------------------------------------------------
*/

function notifyClinicStaffOfNewBooking(
    mysqli $conn,
    array $details
): array {
    $sentCount = 0;
    $failedCount = 0;

    $stmt = $conn->prepare("
        SELECT id, dentist_name, email, role_id
        FROM dentists
        WHERE role_id IN (1, 2)
          AND email IS NOT NULL
          AND email <> ''
    ");

    if (!$stmt) {
        error_log(
            'Staff booking email query preparation failed: '
                . $conn->error
        );

        return [0, 1];
    }

    if (!$stmt->execute()) {
        error_log(
            'Staff booking email query failed: '
                . $stmt->error
        );

        $stmt->close();

        return [0, 1];
    }

    $result = $stmt->get_result();
    $recipients = [];

    while ($staff = $result->fetch_assoc()) {
        try {
            $staffEmail = decryptEmail((string) $staff['email']);

            if (
                !is_string($staffEmail) ||
                !filter_var($staffEmail, FILTER_VALIDATE_EMAIL)
            ) {
                error_log(
                    'Invalid decrypted staff email. Staff ID: '
                        . (int) $staff['id']
                );

                $failedCount++;
                continue;
            }

            $emailKey = strtolower($staffEmail);

            if (isset($recipients[$emailKey])) {
                continue;
            }

            $recipients[$emailKey] = [
                'email' => $staffEmail,
                'name' => (string) $staff['dentist_name'],
                'role_id' => (int) $staff['role_id'],
            ];
        } catch (Throwable $exception) {
            error_log(
                'Unable to decrypt staff email. Staff ID: '
                    . (int) $staff['id']
                    . '. Error: ' . $exception->getMessage()
            );

            $failedCount++;
        }
    }

    $stmt->close();

    foreach ($recipients as $recipient) {
        $roleName = $recipient['role_id'] === 1
            ? 'Administrator'
            : 'Dental Assistant';

        $subject =
            'New Appointment Booking - Talaue 88 Dental Clinic';

        $plainText =
            "Hello {$recipient['name']},\n\n"
            . "A new appointment request has been submitted through "
            . "the Talaue 88 Dental Clinic website.\n\n"
            . "New Appointment Details\n"
            . "Reference Number: " . $details['reference'] . "\n"
            . "Patient: " . $details['patient'] . "\n"
            . "Date: " . $details['date'] . "\n"
            . "Time: " . $details['time'] . "\n"
            . "Dental Service: " . $details['service'] . "\n"
            . "Dentist: " . $details['dentist'] . "\n"
            . "Branch: " . $details['branch'] . "\n"
            . "Status: Pending\n\n"
            . "Please sign in to the clinic system to review this booking.\n\n"
            . "Recipient role: {$roleName}\n"
            . "Talaue 88 Dental Clinic";

        $html = buildBookingEmailHtml(
            'New Appointment Booking',
            'A new appointment request has been submitted. '
                . 'Please sign in to the clinic system to review the booking.',
            $details
        );

        if (
            sendClinicBookingEmail(
                $recipient['email'],
                $subject,
                $plainText,
                $html
            )
        ) {
            $sentCount++;
        } else {
            $failedCount++;
        }
    }

    return [$sentCount, $failedCount];
}

/*
|--------------------------------------------------------------------------
| Create In-App Notifications
|--------------------------------------------------------------------------
|
| Creates a booking_created notification for every Admin and
| Dental Assistant. These appear in the notification bell.
|--------------------------------------------------------------------------
*/

function createStaffBookingNotifications(
    mysqli $conn,
    int $bookingId,
    array $details
): array {
    $createdCount = 0;
    $failedCount = 0;

    $title = 'New Appointment Booking';

    $message = sprintf(
        '%s booked %s for %s at %s. Reference: %s.',
        $details['patient'],
        $details['service'],
        $details['date'],
        $details['time'],
        $details['reference']
    );

    $staffStmt = $conn->prepare("
        SELECT id
        FROM dentists
        WHERE role_id IN (1, 2)
    ");

    if (!$staffStmt) {
        error_log(
            'Staff notification query preparation failed: '
                . $conn->error
        );

        return [0, 1];
    }

    if (!$staffStmt->execute()) {
        error_log(
            'Staff notification query failed: '
                . $staffStmt->error
        );

        $staffStmt->close();

        return [0, 1];
    }

    $staffResult = $staffStmt->get_result();
    $staffIds = [];

    while ($staff = $staffResult->fetch_assoc()) {
        $staffIds[] = (int) $staff['id'];
    }

    $staffStmt->close();

    if ($staffIds === []) {
        return [0, 0];
    }

    $notificationStmt = $conn->prepare("
        INSERT INTO notifications (
            recipient_id,
            booking_id,
            doctor_change_request_id,
            notification_type,
            title,
            message,
            is_read,
            read_at,
            created_at
        )
        VALUES (?, ?, NULL, 'booking_created', ?, ?, 0, NULL, NOW())
    ");

    if (!$notificationStmt) {
        error_log(
            'Staff notification insert preparation failed: '
                . $conn->error
        );

        return [0, count($staffIds)];
    }

    foreach ($staffIds as $staffId) {
        $notificationStmt->bind_param(
            'iiss',
            $staffId,
            $bookingId,
            $title,
            $message
        );

        if ($notificationStmt->execute()) {
            $createdCount++;
        } else {
            $failedCount++;

            error_log(
                'Failed to create booking notification for staff ID '
                    . $staffId
                    . ': '
                    . $notificationStmt->error
            );
        }
    }

    $notificationStmt->close();

    return [$createdCount, $failedCount];
}

/*
|--------------------------------------------------------------------------
| CSRF Token
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Booking Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($submittedToken) ||
        !is_string($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $submittedToken)
    ) {
        bookingFormRedirect(
            'danger',
            'Invalid security token. Please try again.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Retrieve Form Data
    |--------------------------------------------------------------------------
    */

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $phoneNumber = trim((string) ($_POST['phone_number'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    $dentServiceId = filter_input(
        INPUT_POST,
        'dent_service_id',
        FILTER_VALIDATE_INT
    );

    $dentistId = filter_input(
        INPUT_POST,
        'dentist_id',
        FILTER_VALIDATE_INT
    );

    $branchId = filter_input(
        INPUT_POST,
        'branch_id',
        FILTER_VALIDATE_INT
    );

    $bookingDate = trim((string) ($_POST['booking-date'] ?? ''));
    $bookingTime = trim((string) ($_POST['booking-time'] ?? ''));

    $privacyConsent = isset($_POST['privacy_consent']);

    /*
    |--------------------------------------------------------------------------
    | Required Fields
    |--------------------------------------------------------------------------
    */

    if ($fullName === '') {
        bookingFormRedirect('danger', 'Please enter your full name.');
    }

    if ($phoneNumber === '') {
        bookingFormRedirect('danger', 'Please enter your phone number.');
    }

    if ($email === '') {
        bookingFormRedirect('danger', 'Please enter your email address.');
    }

    if (
        $dentServiceId === false ||
        $dentServiceId === null ||
        $dentServiceId <= 0
    ) {
        bookingFormRedirect('danger', 'Please select a valid dental service.');
    }

    if (
        $dentistId === false ||
        $dentistId === null ||
        $dentistId <= 0
    ) {
        bookingFormRedirect('danger', 'Please select a valid dentist.');
    }

    if (
        $branchId === false ||
        $branchId === null ||
        $branchId <= 0
    ) {
        bookingFormRedirect('danger', 'Please select a valid branch.');
    }

    if ($bookingDate === '') {
        bookingFormRedirect('danger', 'Please select an appointment date.');
    }

    if ($bookingTime === '') {
        bookingFormRedirect('danger', 'Please select an appointment time.');
    }

    if (!$privacyConsent) {
        bookingFormRedirect(
            'danger',
            'Please agree to the privacy consent before continuing.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Name, Email and Phone
    |--------------------------------------------------------------------------
    */

    if (mb_strlen($fullName) > 150) {
        bookingFormRedirect('danger', 'Full name is too long.');
    }

    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        mb_strlen($email) > 254
    ) {
        bookingFormRedirect('danger', 'Please enter a valid email address.');
    }

    $normalizedPhone = preg_replace('/\D+/', '', $phoneNumber);

    if (
        $normalizedPhone === null ||
        strlen($normalizedPhone) < 10 ||
        strlen($normalizedPhone) > 15
    ) {
        bookingFormRedirect('danger', 'Please enter a valid phone number.');
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Appointment Date
    |--------------------------------------------------------------------------
    */

    $dateObject = DateTime::createFromFormat(
        '!Y-m-d',
        $bookingDate,
        $timezone
    );

    $dateErrors = DateTime::getLastErrors();

    if (
        !$dateObject ||
        (
            $dateErrors !== false &&
            (
                $dateErrors['warning_count'] > 0 ||
                $dateErrors['error_count'] > 0
            )
        ) ||
        $dateObject->format('Y-m-d') !== $bookingDate
    ) {
        bookingFormRedirect(
            'danger',
            'Please select a valid appointment date.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Appointment Time
    |--------------------------------------------------------------------------
    */

    $timeObject = DateTime::createFromFormat(
        '!H:i',
        $bookingTime,
        $timezone
    );

    $timeErrors = DateTime::getLastErrors();

    if (
        !$timeObject ||
        (
            $timeErrors !== false &&
            (
                $timeErrors['warning_count'] > 0 ||
                $timeErrors['error_count'] > 0
            )
        ) ||
        $timeObject->format('H:i') !== $bookingTime
    ) {
        bookingFormRedirect(
            'danger',
            'Please select a valid appointment time.'
        );
    }

    if (
        $bookingTime < $clinicOpeningTime ||
        $bookingTime > $clinicClosingTime
    ) {
        bookingFormRedirect(
            'danger',
            'Please select an appointment time between 10:00 AM and 7:00 PM.'
        );
    }

    $appointmentDateTime = DateTime::createFromFormat(
        '!Y-m-d H:i',
        $bookingDate . ' ' . $bookingTime,
        $timezone
    );

    $now = new DateTime('now', $timezone);

    if (!$appointmentDateTime || $appointmentDateTime <= $now) {
        bookingFormRedirect(
            'danger',
            'Please select a future appointment date and time.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Verify Selected Service, Dentist and Branch
    |--------------------------------------------------------------------------
    */

    $checks = [
        [
            'sql' => 'SELECT id FROM dental_services WHERE id = ? LIMIT 1',
            'id' => $dentServiceId,
            'message' => 'The selected dental service is no longer available.',
        ],
        [
            'sql' => 'SELECT id FROM dentists WHERE id = ? LIMIT 1',
            'id' => $dentistId,
            'message' => 'The selected dentist is no longer available.',
        ],
        [
            'sql' => 'SELECT id FROM branches WHERE id = ? LIMIT 1',
            'id' => $branchId,
            'message' => 'The selected branch is no longer available.',
        ],
    ];

    foreach ($checks as $check) {
        $stmt = $conn->prepare($check['sql']);

        if (!$stmt) {
            error_log(
                'Booking validation prepare failed: ' . $conn->error
            );

            bookingFormRedirect(
                'danger',
                'Unable to process your booking right now.'
            );
        }

        $checkId = (int) $check['id'];

        $stmt->bind_param('i', $checkId);
        $stmt->execute();

        $result = $stmt->get_result();
        $exists = $result->fetch_assoc();

        $stmt->close();

        if (!$exists) {
            bookingFormRedirect('danger', $check['message']);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Retrieve Details for Notification Emails
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT
            ds.dental_service,
            d.dentist_name,
            b.branch_name
        FROM dental_services ds
        CROSS JOIN dentists d
        CROSS JOIN branches b
        WHERE ds.id = ?
          AND d.id = ?
          AND b.id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        error_log(
            'Appointment detail query preparation failed: '
                . $conn->error
        );

        bookingFormRedirect(
            'danger',
            'Unable to retrieve appointment details.'
        );
    }

    $stmt->bind_param(
        'iii',
        $dentServiceId,
        $dentistId,
        $branchId
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $appointmentDetails = $result->fetch_assoc();

    $stmt->close();

    if (!$appointmentDetails) {
        bookingFormRedirect(
            'danger',
            'The selected appointment details are no longer available.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Reference Number
    |--------------------------------------------------------------------------
    */

    $referenceNumber = '';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $candidate = 'T88-' . strtoupper(bin2hex(random_bytes(8)));

        $stmt = $conn->prepare("
            SELECT id
            FROM bookings
            WHERE reference_number = ?
            LIMIT 1
        ");

        if (!$stmt) {
            bookingFormRedirect(
                'danger',
                'Unable to create your booking reference.'
            );
        }

        $stmt->bind_param('s', $candidate);
        $stmt->execute();

        $result = $stmt->get_result();
        $exists = $result->fetch_assoc();

        $stmt->close();

        if (!$exists) {
            $referenceNumber = $candidate;
            break;
        }
    }

    if ($referenceNumber === '') {
        bookingFormRedirect(
            'danger',
            'Unable to generate a booking reference. Please try again.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Check Appointment Slot
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        SELECT id
        FROM bookings
        WHERE dentist_id = ?
          AND branch_id = ?
          AND booking_date = ?
          AND booking_time = ?
          AND status IN ('Pending', 'Approved', 'Rescheduled')
        LIMIT 1
    ");

    if (!$stmt) {
        bookingFormRedirect(
            'danger',
            'Unable to check appointment availability.'
        );
    }

    $stmt->bind_param(
        'iiss',
        $dentistId,
        $branchId,
        $bookingDate,
        $bookingTime
    );

    $stmt->execute();

    $result = $stmt->get_result();
    $existingBooking = $result->fetch_assoc();

    $stmt->close();

    if ($existingBooking) {
        bookingFormRedirect(
            'warning',
            'The selected dentist is already booked for that date and time. '
                . 'Please choose another schedule.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Encrypt Personal Information
    |--------------------------------------------------------------------------
    */

    try {
        $encryptedPhone = encryptData($normalizedPhone);
        $encryptedEmail = encryptEmail($email);
    } catch (Throwable $exception) {
        error_log(
            'Booking encryption failed: ' . $exception->getMessage()
        );

        bookingFormRedirect(
            'danger',
            'Unable to securely process your personal information.'
        );
    }

    if (
        !is_string($encryptedPhone) ||
        $encryptedPhone === '' ||
        !is_string($encryptedEmail) ||
        $encryptedEmail === ''
    ) {
        bookingFormRedirect(
            'danger',
            'Unable to securely process your personal information.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Insert Booking
    |--------------------------------------------------------------------------
    */

    $status = 'Pending';
    $bookingId = 0;

    try {
        $conn->begin_transaction();

        $stmt = $conn->prepare("
            INSERT INTO bookings (
                reference_number,
                full_name,
                phone_number,
                email,
                dent_service_id,
                dentist_id,
                branch_id,
                booking_date,
                booking_time,
                status
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if (!$stmt) {
            throw new RuntimeException(
                'Unable to prepare booking insert: ' . $conn->error
            );
        }

        $stmt->bind_param(
            'ssssiiisss',
            $referenceNumber,
            $fullName,
            $encryptedPhone,
            $encryptedEmail,
            $dentServiceId,
            $dentistId,
            $branchId,
            $bookingDate,
            $bookingTime,
            $status
        );

        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();

            throw new RuntimeException(
                'Booking insert failed: ' . $error
            );
        }

        $bookingId = (int) $conn->insert_id;

        $stmt->close();
        $conn->commit();
    } catch (Throwable $exception) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackException) {
            error_log(
                'Booking rollback failed: '
                    . $rollbackException->getMessage()
            );
        }

        error_log(
            'Booking creation failed: ' . $exception->getMessage()
        );

        bookingFormRedirect(
            'danger',
            'Unable to create your booking. Please try again.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Notification Details
    |--------------------------------------------------------------------------
    */

    $details = buildBookingEmailDetails(
        $referenceNumber,
        $fullName,
        $bookingDate,
        $bookingTime,
        $status,
        (string) $appointmentDetails['dental_service'],
        (string) $appointmentDetails['dentist_name'],
        (string) $appointmentDetails['branch_name']
    );

    /*
    |--------------------------------------------------------------------------
    | Notify Patient by Email
    |--------------------------------------------------------------------------
    */

    $patientEmailSent = notifyPatientOfNewBooking(
        $email,
        $details
    );

    /*
    |--------------------------------------------------------------------------
    | Create In-App Notifications for Staff
    |--------------------------------------------------------------------------
    */

    [$staffNotificationsCreated, $staffNotificationsFailed] =
        createStaffBookingNotifications(
            $conn,
            $bookingId,
            $details
        );

    /*
    |--------------------------------------------------------------------------
    | Email Admins and Dental Assistants
    |--------------------------------------------------------------------------
    */

    [$staffEmailsSent, $staffEmailsFailed] =
        notifyClinicStaffOfNewBooking(
            $conn,
            $details
        );

    /*
    |--------------------------------------------------------------------------
    | Log Notification Summary
    |--------------------------------------------------------------------------
    */

    error_log(
        'New booking notification summary. '
            . 'Booking ID: ' . $bookingId
            . '; In-app created: ' . $staffNotificationsCreated
            . '; In-app failed: ' . $staffNotificationsFailed
            . '; Staff emails sent: ' . $staffEmailsSent
            . '; Staff emails failed: ' . $staffEmailsFailed
            . '; Patient email sent: ' . ($patientEmailSent ? 'yes' : 'no')
    );

    /*
    |--------------------------------------------------------------------------
    | Regenerate CSRF Token
    |--------------------------------------------------------------------------
    */

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    /*
    |--------------------------------------------------------------------------
    | Final Flash Message
    |--------------------------------------------------------------------------
    */

    if ($patientEmailSent) {
        setFlash(
            'success',
            'Your appointment request was submitted successfully. '
                . 'Please keep your reference number: '
                . $referenceNumber
        );
    } else {
        setFlash(
            'warning',
            'Your appointment request was saved, but the confirmation '
                . 'email could not be sent. Please keep your reference number: '
                . $referenceNumber
        );
    }

    header('Location: ' . $redirectUrl);
    exit;
}

/*
|--------------------------------------------------------------------------
| Fetch Dentists
|--------------------------------------------------------------------------
*/

$dentists = [];

$result = $conn->query("
    SELECT id, dentist_name
    FROM dentists
    ORDER BY dentist_name ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dentists[] = $row;
    }

    $result->free();
}

/*
|--------------------------------------------------------------------------
| Fetch Branches
|--------------------------------------------------------------------------
*/

$branches = [];

$result = $conn->query("
    SELECT id, branch_name
    FROM branches
    ORDER BY branch_name ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $branches[] = $row;
    }

    $result->free();
}

/*
|--------------------------------------------------------------------------
| Fetch Dental Services
|--------------------------------------------------------------------------
*/

$services = [];

$result = $conn->query("
    SELECT id, dental_service
    FROM dental_services
    ORDER BY dental_service ASC
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }

    $result->free();
}
