<?php

declare(strict_types=1);

include '../config/session_config.php';
include '../config/config.php';
include '../controller/connection.php';

$MAX_ACCOUNT_ATTEMPTS = 5;
$ACCOUNT_LOCK_MINUTES = 15;

$MAX_IP_ATTEMPTS = 15;
$IP_WINDOW_MINUTES = 15;

/**
 * Store a sign-in error in the session and redirect
 * back to the sign-in page.
 */
function signinError(string $message, string $username = ''): never
{
    $_SESSION['signin_error'] = $message;
    $_SESSION['signin_username'] = $username;

    header('Location: ../admin/');
    exit;
}

// --------------------------------------------------
// Only accept POST requests
// --------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/');
    exit;
}

// --------------------------------------------------
// Get submitted credentials
// --------------------------------------------------

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    signinError(
        'Please enter your username and password.',
        $username
    );
}

// --------------------------------------------------
// Get client IP
// --------------------------------------------------

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// --------------------------------------------------
// Hash identifiers for rate limiting
// --------------------------------------------------

$usernameHash = hash_hmac(
    'sha256',
    strtolower($username),
    IP_HASH_SECRET
);

$ipHash = hash_hmac(
    'sha256',
    $ipAddress,
    IP_HASH_SECRET
);

// --------------------------------------------------
// Remove old login attempts
// --------------------------------------------------

$cleanupStmt = $conn->prepare("
    DELETE FROM login_attempts
    WHERE attempted_at < DATE_SUB(
        NOW(),
        INTERVAL ? MINUTE
    )
");

$cleanupStmt->bind_param(
    'i',
    $IP_WINDOW_MINUTES
);

$cleanupStmt->execute();
$cleanupStmt->close();

// --------------------------------------------------
// Check IP rate limit
// --------------------------------------------------

$ipStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM login_attempts
    WHERE ip_hash = ?
      AND attempted_at >= DATE_SUB(
          NOW(),
          INTERVAL ? MINUTE
      )
");

$ipStmt->bind_param(
    'si',
    $ipHash,
    $IP_WINDOW_MINUTES
);

$ipStmt->execute();
$ipStmt->bind_result($ipAttempts);
$ipStmt->fetch();
$ipStmt->close();

if ($ipAttempts >= $MAX_IP_ATTEMPTS) {
    signinError(
        'Too many sign-in attempts. Please try again later.',
        $username
    );
}

// --------------------------------------------------
// Check account-specific rate limit
// --------------------------------------------------

$accountStmt = $conn->prepare("
    SELECT COUNT(*)
    FROM login_attempts
    WHERE username_hash = ?
      AND attempted_at >= DATE_SUB(
          NOW(),
          INTERVAL ? MINUTE
      )
");

$accountStmt->bind_param(
    'si',
    $usernameHash,
    $ACCOUNT_LOCK_MINUTES
);

$accountStmt->execute();
$accountStmt->bind_result($accountAttempts);
$accountStmt->fetch();
$accountStmt->close();

if ($accountAttempts >= $MAX_ACCOUNT_ATTEMPTS) {
    signinError(
        'Too many sign-in attempts. Please try again later.',
        $username
    );
}

// --------------------------------------------------
// Find dentist
// --------------------------------------------------

$stmt = $conn->prepare("
    SELECT
        id,
        dentist_name,
        username,
        email,
        password,
        role_id
    FROM dentists
    WHERE username = ?
    LIMIT 1
");

$stmt->bind_param('s', $username);
$stmt->execute();

$result = $stmt->get_result();
$dentist = $result->fetch_assoc();

$stmt->close();

// --------------------------------------------------
// Invalid username OR missing password
// --------------------------------------------------

if (!$dentist || empty($dentist['password'])) {

    $attemptStmt = $conn->prepare("
        INSERT INTO login_attempts
            (username_hash, ip_hash)
        VALUES (?, ?)
    ");

    $attemptStmt->bind_param(
        'ss',
        $usernameHash,
        $ipHash
    );

    $attemptStmt->execute();
    $attemptStmt->close();

    signinError(
        'Invalid username or password.',
        $username
    );
}

// --------------------------------------------------
// Verify bcrypt password
// --------------------------------------------------

if (!password_verify($password, $dentist['password'])) {

    $attemptStmt = $conn->prepare("
        INSERT INTO login_attempts
            (username_hash, ip_hash)
        VALUES (?, ?)
    ");

    $attemptStmt->bind_param(
        'ss',
        $usernameHash,
        $ipHash
    );

    $attemptStmt->execute();
    $attemptStmt->close();

    signinError(
        'Invalid username or password.',
        $username
    );
}

// --------------------------------------------------
// Successful authentication
// --------------------------------------------------

session_regenerate_id(true);

$_SESSION['dentist_id'] = (int) $dentist['id'];
$_SESSION['dentist_name'] = $dentist['dentist_name'];
$_SESSION['username'] = $dentist['username'];
$_SESSION['email'] = $dentist['email'];
$_SESSION['role_id'] = (int) $dentist['role_id'];

// --------------------------------------------------
// Remove failed attempts for this account
// --------------------------------------------------

$clearStmt = $conn->prepare("
    DELETE FROM login_attempts
    WHERE username_hash = ?
");

$clearStmt->bind_param(
    's',
    $usernameHash
);

$clearStmt->execute();
$clearStmt->close();

// --------------------------------------------------
// Redirect based on role
// --------------------------------------------------

if ((int) $dentist['role_id'] === 2) {
    // Dental Assistant
    header('Location: ../dental-assistant/dashboard/');
    exit;
}

// Dentist / Administrator
header('Location: ../admin/dashboard/');
exit;
