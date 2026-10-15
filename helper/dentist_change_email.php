<?php

declare(strict_types=1);

require_once __DIR__ . '/mail_config.php';

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| Email Helpers
|--------------------------------------------------------------------------
*/

function escapeDentistChangeEmail(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function sendDentistChangeApprovalEmail(
    string $recipient,
    array $details
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
                'Dentist change approval email failed: '
                    . 'Invalid sender or recipient.'
            );

            return false;
        }

        $patient = (string) ($details['patient'] ?? 'Patient');
        $reference = (string) ($details['reference'] ?? '');
        $date = (string) ($details['date'] ?? '');
        $time = (string) ($details['time'] ?? '');
        $dentist = (string) ($details['dentist'] ?? '');

        $subject =
            'Talaue 88 Dental Clinic - Dentist Change Approved';

        $plainText =
            'Dear ' . $patient . ",\n\n"
            . "Good news! Your request to change the dentist "
            . "for your appointment has been approved.\n\n"
            . "Updated Appointment Details\n"
            . "Reference Number: " . $reference . "\n"
            . "Patient: " . $patient . "\n"
            . "Appointment Date: " . $date . "\n"
            . "Appointment Time: " . $time . "\n"
            . "New Dentist: " . $dentist . "\n"
            . "Status: Approved\n\n"
            . "Your appointment is now assigned to the dentist "
            . "listed above. Please keep your reference number "
            . "for future appointment inquiries.\n\n"
            . "Thank you,\n"
            . "Talaue 88 Dental Clinic\n"
            . "Designing Smiles, Defining Confidence";

        $safePatient = escapeDentistChangeEmail($patient);
        $safeReference = escapeDentistChangeEmail($reference);
        $safeDate = escapeDentistChangeEmail($date);
        $safeTime = escapeDentistChangeEmail($time);
        $safeDentist = escapeDentistChangeEmail($dentist);

        $html =
            '<!DOCTYPE html>'
            . '<html lang="en"><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width,'
            . ' initial-scale=1.0">'
            . '<title>Dentist Change Approved</title></head>'
            . '<body style="margin:0;padding:24px;background:#f4f6f8;'
            . 'font-family:Arial,Helvetica,sans-serif;color:#263238;">'
            . '<div style="max-width:600px;margin:0 auto;background:#fff;'
            . 'border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">'
            . '<div style="padding:24px;background:#176b5b;color:#fff;">'
            . '<h1 style="margin:0;font-size:24px;">'
            . 'Talaue 88 Dental Clinic</h1>'
            . '<p>Designing Smiles, Defining Confidence</p>'
            . '</div><div style="padding:28px;">'
            . '<h2 style="color:#176b5b;">Dentist Change Approved</h2>'
            . '<p>Dear ' . $safePatient . ',</p>'
            . '<p>Good news! Your request to change the dentist '
            . 'for your appointment has been approved.</p>'
            . '<div style="padding:18px;background:#f8fafc;'
            . 'border:1px solid #e5e7eb;border-radius:8px;">'
            . '<p><strong>Reference Number:</strong> '
            . $safeReference . '</p>'
            . '<p><strong>Patient:</strong> ' . $safePatient . '</p>'
            . '<p><strong>Appointment Date:</strong> '
            . $safeDate . '</p>'
            . '<p><strong>Appointment Time:</strong> '
            . $safeTime . '</p>'
            . '<p><strong>New Dentist:</strong> '
            . $safeDentist . '</p>'
            . '<p><strong>Status:</strong> Approved</p>'
            . '</div>'
            . '<p>Your appointment is now assigned to the new dentist '
            . 'listed above. Please keep your reference number '
            . 'for future appointment inquiries.</p>'
            . '<p>Thank you for choosing Talaue 88 Dental Clinic.</p>'
            . '<p style="color:#64748b;"><strong>'
            . 'Designing Smiles, Defining Confidence'
            . '</strong></p>'
            . '</div></div></body></html>';

        $boundary =
            '=_Talaue88DentistChange_' . bin2hex(random_bytes(16));

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . mb_encode_mimeheader($mailFromName, 'UTF-8')
                . ' <' . $mailFromAddress . '>',
            'Reply-To: ' . $mailFromAddress,
            'Content-Type: multipart/alternative; boundary="'
                . $boundary . '"',
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
                'Dentist change approval email was not accepted '
                    . 'by the mail server.'
            );
        }

        return $sent;
    } catch (Throwable $exception) {
        error_log(
            'Dentist change approval email error: '
                . $exception->getMessage()
        );

        return false;
    }
}

/**
 * Send an email when a patient's dentist change request is rejected.
 *
 * @param string $recipient
 * @param array  $details
 * @return bool
 */
function sendDentistChangeRejectionEmail(
    string $recipient,
    array $details
): bool {
    global $mailFromAddress, $mailFromName;

    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $patient = (string) ($details['patient'] ?? 'Patient');
    $reference = (string) ($details['reference'] ?? '');
    $date = (string) ($details['date'] ?? '');
    $time = (string) ($details['time'] ?? '');
    $dentist = (string) ($details['dentist'] ?? '');
    $reason = trim((string) ($details['reason'] ?? ''));

    if (
        $reference === '' ||
        $date === '' ||
        $time === '' ||
        $dentist === '' ||
        $reason === ''
    ) {
        return false;
    }

    /*
     * Escape dynamic values before inserting them into HTML.
     */

    $htmlPatient = htmlspecialchars(
        $patient,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $htmlReference = htmlspecialchars(
        $reference,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $htmlDate = htmlspecialchars(
        $date,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $htmlTime = htmlspecialchars(
        $time,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $htmlDentist = htmlspecialchars(
        $dentist,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $htmlReason = nl2br(
        htmlspecialchars(
            $reason,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        )
    );

    $subject = 'Update on Your Dentist Change Request - Talaue 88';

    $plainText = implode("\n", [
        'Dear ' . $patient . ',',
        '',
        'We are writing to inform you that your request to change your dentist has been rejected.',
        '',
        'Appointment Reference: ' . $reference,
        'Appointment Date: ' . $date,
        'Appointment Time: ' . $time,
        'Requested Dentist: ' . $dentist,
        '',
        'Reason for rejection:',
        $reason,
        '',
        'Your existing appointment details remain unchanged. If you need assistance, please contact Talaue 88 Dental Clinic.',
        '',
        'Thank you,',
        'Talaue 88 Dental Clinic',
    ]);

    $htmlMessage = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dentist Change Request Update</title>
    </head>
    <body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,Helvetica,sans-serif;color:#202124;">
        <div style="max-width:600px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
            <div style="padding:24px;background:#183b56;color:#ffffff;">
                <h1 style="margin:0;font-size:22px;">Talaue 88 Dental Clinic</h1>
                <p style="margin:8px 0 0;font-size:14px;">Dentist Change Request Update</p>
            </div>

            <div style="padding:24px;">
                <p>Dear <strong>' . $htmlPatient . '</strong>,</p>

                <p>
                    We regret to inform you that your request to change your
                    dentist has been <strong>rejected</strong>.
                </p>

                <div style="padding:16px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;">
                    <p style="margin:0 0 10px;"><strong>Appointment Reference:</strong> ' . $htmlReference . '</p>
                    <p style="margin:0 0 10px;"><strong>Appointment Date:</strong> ' . $htmlDate . '</p>
                    <p style="margin:0 0 10px;"><strong>Appointment Time:</strong> ' . $htmlTime . '</p>
                    <p style="margin:0;"><strong>Requested Dentist:</strong> ' . $htmlDentist . '</p>
                </div>

                <h3 style="margin:24px 0 8px;">Reason for rejection</h3>

                <div style="padding:14px;background:#fff7ed;border-left:4px solid #c2410c;border-radius:4px;line-height:1.6;">
                    ' . $htmlReason . '
                </div>

                <p style="margin-top:24px;">
                    Your existing appointment details remain unchanged.
                    If you need assistance or would like to discuss your
                    appointment, please contact Talaue 88 Dental Clinic.
                </p>

                <p style="margin-top:24px;">
                    Thank you,<br>
                    <strong>Talaue 88 Dental Clinic</strong>
                </p>
            </div>
        </div>
    </body>
    </html>';

    $boundary = '=_Talaue88_' . bin2hex(random_bytes(16));

    $fromAddress = filter_var(
        (string) $mailFromAddress,
        FILTER_VALIDATE_EMAIL
    );

    if (!$fromAddress) {
        error_log('Dentist change rejection email has an invalid sender address.');
        return false;
    }

    $fromName = (string) $mailFromName;

    $encodedFromName = '=?UTF-8?B?'
        . base64_encode($fromName)
        . '?=';

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $encodedFromName . ' <' . $fromAddress . '>',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    $message = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($plainText))
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($htmlMessage))
        . '--' . $boundary . "--\r\n";

    try {
        return mail(
            $recipient,
            '=?UTF-8?B?' . base64_encode($subject) . '?=',
            $message,
            implode("\r\n", $headers)
        );
    } catch (Throwable $e) {
        error_log(
            'Dentist change rejection email error: ' . $e->getMessage()
        );

        return false;
    }
}
