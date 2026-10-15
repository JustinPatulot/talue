<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';

header('Content-Type: application/json; charset=utf-8');

function respond(
    bool $success,
    string $message,
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);

    exit;
}

/*
 * Only allow POST requests.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(
        false,
        'Invalid request method.',
        405
    );
}

/*
 * The password change must belong to the
 * currently authenticated dentist.
 */
$dentistId = $_SESSION['dentist_id'] ?? null;

if (
    $dentistId === null ||
    !is_numeric($dentistId)
) {
    respond(
        false,
        'You must be signed in to change your password.',
        401
    );
}

$dentistId = (int) $dentistId;

/*
 * Verify CSRF token.
 */
$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrfToken) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $csrfToken
    )
) {
    respond(
        false,
        'Invalid security token. Please refresh the page and try again.',
        403
    );
}

/*
 * Get submitted passwords.
 */
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (
    !is_string($currentPassword) ||
    !is_string($newPassword) ||
    !is_string($confirmPassword)
) {
    respond(
        false,
        'Invalid password data.',
        400
    );
}

/*
 * Password length validation.
 */
if (strlen($currentPassword) < 6) {
    respond(
        false,
        'Current password must be at least 6 characters.',
        400
    );
}

if (strlen($currentPassword) > 72) {
    respond(
        false,
        'Current password must not exceed 72 characters.',
        400
    );
}

if (strlen($newPassword) < 6) {
    respond(
        false,
        'New password must be at least 6 characters.',
        400
    );
}

if (strlen($newPassword) > 72) {
    respond(
        false,
        'New password must not exceed 72 characters.',
        400
    );
}

/*
 * Confirm new password.
 */
if ($newPassword !== $confirmPassword) {
    respond(
        false,
        'New password and confirmation do not match.',
        400
    );
}

/*
 * Prevent password reuse.
 */
if ($currentPassword === $newPassword) {
    respond(
        false,
        'New password must be different from your current password.',
        400
    );
}

/*
 * Get the authenticated dentist.
 *
 * IMPORTANT:
 * The dentist ID comes from the server-side
 * session, NOT from POST data.
 */
$stmt = $conn->prepare("
    SELECT
        id,
        password
    FROM dentists
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    respond(
        false,
        'Unable to process the password change.',
        500
    );
}

$stmt->bind_param(
    'i',
    $dentistId
);

$stmt->execute();

$result = $stmt->get_result();

$dentist = $result->fetch_assoc();

$stmt->close();

if (!$dentist) {
    respond(
        false,
        'Your account could not be found.',
        404
    );
}

/*
 * Verify current password.
 */
$storedPassword = $dentist['password'] ?? '';

if (
    !is_string($storedPassword) ||
    !password_verify(
        $currentPassword,
        $storedPassword
    )
) {
    respond(
        false,
        'The current password is incorrect.',
        400
    );
}

/*
 * Hash the new password.
 */
$newPasswordHash = password_hash(
    $newPassword,
    PASSWORD_DEFAULT
);

if ($newPasswordHash === false) {
    respond(
        false,
        'Unable to secure the new password.',
        500
    );
}

/*
 * Update ONLY the authenticated dentist's password.
 */
$updateStmt = $conn->prepare("
    UPDATE dentists
    SET password = ?
    WHERE id = ?
    LIMIT 1
");

if (!$updateStmt) {
    respond(
        false,
        'Unable to update your password.',
        500
    );
}

$updateStmt->bind_param(
    'si',
    $newPasswordHash,
    $dentistId
);

if (!$updateStmt->execute()) {

    $updateStmt->close();

    respond(
        false,
        'Unable to update your password. Please try again.',
        500
    );
}

$updateStmt->close();

/*
 * Regenerate the session ID after the
 * credential change.
 */
session_regenerate_id(true);

respond(
    true,
    'Your password has been changed successfully.'
);
