<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';

header('Content-Type: application/json; charset=UTF-8');

/*
|--------------------------------------------------------------------------
| JSON Response
|--------------------------------------------------------------------------
*/

function jsonResponse(
    bool $success,
    string $message,
    ?array $data = null,
    int $statusCode = 200
): never {

    http_response_code($statusCode);

    echo json_encode(
        [
            'success' => $success,
            'message' => $message,
            'data'    => $data,
        ],
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| Request Method
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    jsonResponse(
        false,
        'Invalid request method.',
        null,
        405
    );
}

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$sessionToken = $_SESSION['csrf_token'] ?? '';

$requestToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($sessionToken) ||
    !is_string($requestToken) ||
    $sessionToken === '' ||
    $requestToken === '' ||
    !hash_equals($sessionToken, $requestToken)
) {

    jsonResponse(
        false,
        'Invalid security token.',
        null,
        403
    );
}

/*
|--------------------------------------------------------------------------
| Reference Number
|--------------------------------------------------------------------------
*/

$referenceNumber = strtoupper(
    trim(
        (string) ($_POST['reference_number'] ?? '')
    )
);

if ($referenceNumber === '') {

    jsonResponse(
        false,
        'Please enter your reference number.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| Reference Number Format
|--------------------------------------------------------------------------
|
| Example:
| T88-6D219C7C8473CE96
|
| T88-
| followed by exactly 16 hexadecimal characters
|
*/

if (!preg_match('/^T88-[A-F0-9]{16}$/', $referenceNumber)) {

    jsonResponse(
        false,
        'Please enter a valid reference number.',
        null,
        422
    );
}

/*
|--------------------------------------------------------------------------
| Database Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        b.id,
        b.reference_number,
        b.dentist_id,
        b.full_name,
        b.booking_date,
        b.booking_time,
        b.status,
        ds.dental_service,
        d.dentist_name AS dentist_name,
        br.branch_name

    FROM bookings AS b

    LEFT JOIN dental_services AS ds
        ON ds.id = b.dent_service_id

    LEFT JOIN dentists AS d
        ON d.id = b.dentist_id

    LEFT JOIN branches AS br
        ON br.id = b.branch_id

    WHERE b.reference_number = ?

    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);

if ($stmt === false) {

    error_log(
        'Appointment status prepare error: ' .
            mysqli_error($conn)
    );

    jsonResponse(
        false,
        'Unable to process your request.',
        null,
        500
    );
}

mysqli_stmt_bind_param(
    $stmt,
    's',
    $referenceNumber
);

if (!mysqli_stmt_execute($stmt)) {

    error_log(
        'Appointment status execute error: ' .
            mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    jsonResponse(
        false,
        'Unable to retrieve the appointment.',
        null,
        500
    );
}

$result = mysqli_stmt_get_result($stmt);

if ($result === false) {

    error_log(
        'Appointment status result error: ' .
            mysqli_stmt_error($stmt)
    );

    mysqli_stmt_close($stmt);

    jsonResponse(
        false,
        'Unable to retrieve the appointment.',
        null,
        500
    );
}

$appointment = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Not Found
|--------------------------------------------------------------------------
*/

if (!$appointment) {

    jsonResponse(
        false,
        'No appointment was found using that reference number.',
        null,
        404
    );
}

/*
|--------------------------------------------------------------------------
| Format Date
|--------------------------------------------------------------------------
*/

$formattedDate = date(
    'F j, Y',
    strtotime(
        (string) $appointment['booking_date']
    )
);

/*
|--------------------------------------------------------------------------
| Format Time
|--------------------------------------------------------------------------
*/

$formattedTime = date(
    'g:i A',
    strtotime(
        (string) $appointment['booking_time']
    )
);

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

jsonResponse(
    true,
    'Appointment found.',
    [
        'booking_id'       => $appointment['id'],
        'reference_number' => $appointment['reference_number'],
        'dentist_id'       => $appointment['dentist_id'],
        'full_name'        => $appointment['full_name'],
        'service'          => $appointment['dental_service'],
        'dentist'          => $appointment['dentist_name'],
        'branch'           => $appointment['branch_name'],
        'booking_date'     => $formattedDate,
        'booking_time'     => $formattedTime,
        'status'           => $appointment['status'],
    ]
);
