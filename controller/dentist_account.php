<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../helper/encryption.php';

header('Content-Type: application/json; charset=utf-8');


/**
 * JSON response helper.
 */
function respond(
    bool $success,
    string $message,
    int $statusCode = 200
): void {
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);

    exit;
}


/*
 * Only POST requests are allowed.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(
        false,
        'Invalid request method.',
        405
    );
}


/*
 * Make sure a dentist is authenticated.
 */
$dentistId = $_SESSION['dentist_id'] ?? null;

if ($dentistId === null) {
    respond(
        false,
        'Your session has expired. Please log in again.',
        401
    );
}


/*
 * CSRF protection.
 */
$sessionToken = $_SESSION['csrf_token'] ?? '';
$postToken = $_POST['csrf_token'] ?? '';

if (
    empty($sessionToken) ||
    empty($postToken) ||
    !hash_equals($sessionToken, $postToken)
) {
    respond(
        false,
        'Invalid security token. Please refresh the page and try again.',
        403
    );
}


/*
 * Confirm the requested action.
 */
$formAction = $_POST['form_action'] ?? '';

if ($formAction !== 'edit_dentist') {
    respond(
        false,
        'Invalid account action.',
        400
    );
}


/*
 * Get submitted values.
 */
$dentistName = trim($_POST['dentist_name'] ?? '');
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');


/*
 * Validate dentist name.
 */
if ($dentistName === '') {
    respond(
        false,
        "Dentist's name is required.",
        422
    );
}


/*
 * Validate username.
 */
if ($username === '') {
    respond(
        false,
        'Username is required.',
        422
    );
}


/*
 * Validate email.
 */
if (
    $email === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL)
) {
    respond(
        false,
        'Please enter a valid email address.',
        422
    );
}


/*
 * Check username uniqueness.
 *
 * Exclude the currently logged-in dentist.
 */
$stmt = $conn->prepare("
    SELECT id
    FROM dentists
    WHERE username = ?
      AND id != ?
    LIMIT 1
");

$stmt->bind_param(
    'si',
    $username,
    $dentistId
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();

    respond(
        false,
        'That username is already being used.',
        409
    );
}

$stmt->close();


/*
 * Check email uniqueness using email_hash.
 *
 * The actual email remains encrypted in the database.
 */
$emailHash = hashEmail($email);

$stmt = $conn->prepare("
    SELECT id
    FROM dentists
    WHERE email_hash = ?
      AND id != ?
    LIMIT 1
");

$stmt->bind_param(
    'si',
    $emailHash,
    $dentistId
);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();

    respond(
        false,
        'That email address is already being used.',
        409
    );
}

$stmt->close();


/*
 * Encrypt the email before storing it.
 */
$encryptedEmail = encryptEmail($email);


/*
 * Update the logged-in dentist's account.
 */
$stmt = $conn->prepare("
    UPDATE dentists
    SET
        dentist_name = ?,
        username = ?,
        email = ?,
        email_hash = ?
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param(
    'ssssi',
    $dentistName,
    $username,
    $encryptedEmail,
    $emailHash,
    $dentistId
);

if (!$stmt->execute()) {
    $stmt->close();

    respond(
        false,
        'Unable to update your account. Please try again.',
        500
    );
}

$affectedRows = $stmt->affected_rows;

$stmt->close();


/*
 * Account update successful.
 */
respond(
    true,
    $affectedRows > 0
        ? 'Account updated successfully.'
        : 'No changes were made.'
);
