<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../helper/flash_helper.php';
require_once __DIR__ . '/../helper/encryption.php';


/*
 * ---------------------------------------------------------
 * Request method
 * ---------------------------------------------------------
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    exit('Method Not Allowed');
}


/*
 * ---------------------------------------------------------
 * CSRF validation
 * ---------------------------------------------------------
 */

$sessionCsrfToken = $_SESSION['csrf_token'] ?? '';
$submittedCsrfToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($submittedCsrfToken) ||
    $sessionCsrfToken === '' ||
    !hash_equals($sessionCsrfToken, $submittedCsrfToken)
) {
    setFlash('danger', 'Invalid or expired security token.');

    header('Location: ../admin/dentists');
    exit;
}


/*
 * ---------------------------------------------------------
 * Get submitted values
 * ---------------------------------------------------------
 */

$dentistId = filter_input(
    INPUT_POST,
    'dentist_id',
    FILTER_VALIDATE_INT
);

$dentistName = trim(
    (string) ($_POST['dentist_name'] ?? '')
);

$username = trim(
    (string) ($_POST['username'] ?? '')
);

$email = strtolower(
    trim((string) ($_POST['email'] ?? ''))
);

$password = (string) ($_POST['password'] ?? '');

$roleId = filter_input(
    INPUT_POST,
    'role_id',
    FILTER_VALIDATE_INT
);

$formAction = $_POST['form_action'] ?? '';


/*
 * ---------------------------------------------------------
 * Validate form action
 * ---------------------------------------------------------
 */

if ($formAction !== 'edit_dentist') {
    setFlash('danger', 'Invalid form submission.');

    header('Location: ../admin/dentists');
    exit;
}


/*
 * ---------------------------------------------------------
 * Validate dentist ID
 * ---------------------------------------------------------
 */

if ($dentistId === false || $dentistId === null || $dentistId <= 0) {
    setFlash('danger', 'Invalid dentist ID.');

    header('Location: ../admin/dentists');
    exit;
}


/*
 * ---------------------------------------------------------
 * Validate dentist name
 * ---------------------------------------------------------
 */

if ($dentistName === '') {
    setFlash('danger', 'Dentist name is required.');

    header('Location: ../admin/dentists');
    exit;
}

if (mb_strlen($dentistName) > 255) {
    setFlash('danger', 'Dentist name is too long.');

    header('Location: ../admin/dentists');
    exit;
}


/*
 * ---------------------------------------------------------
 * Validate role
 * ---------------------------------------------------------
 */

if ($roleId === false || $roleId === null || $roleId <= 0) {
    setFlash('danger', 'Please select a valid role.');

    header('Location: ../admin/dentists');
    exit;
}


/*
 * ---------------------------------------------------------
 * Fetch selected role
 * ---------------------------------------------------------
 */

$roleStmt = $conn->prepare("
    SELECT id, role_name
    FROM roles
    WHERE id = ?
    LIMIT 1
");

if (!$roleStmt) {
    setFlash('danger', 'Unable to valide the selected role.');

    header('Location: ../admin/dentists');
    exit;
}

$roleStmt->bind_param(
    'i',
    $roleId
);

$roleStmt->execute();

$roleResult = $roleStmt->get_result();

$role = $roleResult->fetch_assoc();

$roleStmt->close();


if (!$role) {
    setFlash('danger', 'The selected role does not exist.');

    header('Location: ../admin/dentists');
    exit;
}


$roleName = strtolower(
    trim((string) $role['role_name'])
);

$isDentist = ($roleName === 'dentist');


/*
 * ---------------------------------------------------------
 * Dentist role
 * ---------------------------------------------------------
 *
 * Dentists do not use:
 * - username
 * - email
 * - password
 *
 * Therefore, these values are explicitly cleared.
 *
 * ---------------------------------------------------------
 */

if ($isDentist) {

    $username = '';
    $email = '';
    $password = '';
}


/*
 * ---------------------------------------------------------
 * Non-Dentist credential validation
 * ---------------------------------------------------------
 */

if (!$isDentist) {

    /*
     * Username required
     */

    if ($username === '') {
        setFlash('danger', 'Username is required for this role.');

        header('Location: ../admin/dentists');
        exit;
    }


    /*
     * Username length
     */

    $usernameLength = mb_strlen($username);

    if ($usernameLength < 3 || $usernameLength > 50) {
        setFlash(
            'danger',
            'Username must be between 3 and 50 characters.'
        );

        header('Location: ../admin/dentists');
        exit;
    }


    /*
     * Email required
     */

    if ($email === '') {
        setFlash('danger', 'E-mail is required for this role.');

        header('Location: ../admin/dentists');
        exit;
    }


    /*
     * Validate email
     */

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        setFlash('danger', 'Please enter a valid email address.');

        header('Location: ../admin/dentists');
        exit;
    }


    /*
     * Email length
     */

    if (mb_strlen($email) > 255) {
        setFlash('danger', 'Email address is too long.');

        header('Location: ../admin/dentists');
        exit;
    }
}


/*
 * ---------------------------------------------------------
 * Verify dentist exists
 * ---------------------------------------------------------
 */

$existingStmt = $conn->prepare("
    SELECT id, username, email, password
    FROM dentists
    WHERE id = ?
    LIMIT 1
");

if (!$existingStmt) {
    setFlash('danger', 'Unable to load the existing dentist record.');

    header('Location: ../admin/dentists');
    exit;
}

$existingStmt->bind_param(
    'i',
    $dentistId
);

$existingStmt->execute();

$existingResult = $existingStmt->get_result();

$existingDentist = $existingResult->fetch_assoc();

$existingStmt->close();


if (!$existingDentist) {
    setFlash('danger', 'Dentist record not found.');

    header('Location: ../admin/dentists');
    exit;
}

/*
 * ---------------------------------------------------------
 * Require password when converting a Dentist
 * to a login-enabled role
 * ---------------------------------------------------------
 *
 * Existing Dentist:
 *     password = NULL
 *
 * Existing Dental Assistant / Administrator:
 *     password contains a password hash
 *
 * Therefore:
 * - Dentist -> Dentist: allowed without password
 * - Dentist -> Dental Assistant/Admin: password required
 * - Existing login-enabled account -> another
 *   login-enabled role: blank password keeps current password
 *
 * ---------------------------------------------------------
 */

if (
    !$isDentist &&
    empty($existingDentist['password']) &&
    $password === ''
) {
    setFlash(
        'danger',
        'A password is required when changing a Dentist to a login-enabled role.'
    );

    header('Location: ../admin/dentists');
    exit;
}


/*
 * ---------------------------------------------------------
 * Check username uniqueness
 * ---------------------------------------------------------
 */

if (!$isDentist) {

    $usernameStmt = $conn->prepare("
        SELECT id
        FROM dentists
        WHERE username = ?
          AND id <> ?
        LIMIT 1
    ");

    if (!$usernameStmt) {
        setFlash('danger', 'Unable to validate the username.');

        header('Location: ../admin/dentists');
        exit;
    }

    $usernameStmt->bind_param(
        'si',
        $username,
        $dentistId
    );

    $usernameStmt->execute();

    $usernameResult = $usernameStmt->get_result();

    $usernameExists = $usernameResult->num_rows > 0;

    $usernameStmt->close();


    if ($usernameExists) {
        setFlash('danger', 'That username is already in use.');

        header('Location: ../admin/dentists');
        exit;
    }
}


/*
 * ---------------------------------------------------------
 * Check email uniqueness
 * ---------------------------------------------------------
 *
 * Email is encrypted in the database, so the encrypted
 * value cannot be compared directly.
 *
 * Instead, email_hash contains a deterministic SHA-256 hash
 * of the normalized email address.
 *
 * ---------------------------------------------------------
 */

$emailHash = '';

if (!$isDentist) {

    $encryptedEmail = encryptEmail($email);

    $emailHash = hashEmail($email);

    $emailStmt = $conn->prepare("
        SELECT id
        FROM dentists
        WHERE email_hash = ?
          AND id <> ?
        LIMIT 1
    ");

    if (!$emailStmt) {
        setFlash('danger', 'Unable to validate the email address.');

        header('Location: ../admin/dentists');
        exit;
    }

    $emailStmt->bind_param(
        'si',
        $emailHash,
        $dentistId
    );

    $emailStmt->execute();

    $emailResult = $emailStmt->get_result();

    $emailExists = $emailResult->num_rows > 0;

    $emailStmt->close();

    if ($emailExists) {
        setFlash('danger', 'That email address is already in use.');

        header('Location: ../admin/dentists');
        exit;
    }
}


/*
 * ---------------------------------------------------------
 * Prepare password
 * ---------------------------------------------------------
 *
 * Blank password:
 *     Keep existing password.
 *
 * New password:
 *     Generate a new password hash.
 *
 * Dentist:
 *     Password is cleared because dentist accounts do not
 *     use credentials.
 *
 * ---------------------------------------------------------
 */

$newPasswordHash = null;

if (!$isDentist && $password !== '') {

    /*
     * Optional maximum length protection.
     *
     * password_hash() accepts long strings, but imposing a
     * reasonable application-level limit prevents accidental
     * oversized submissions.
     */

    if (mb_strlen($password) > 72) {
        setFlash('danger', 'Password must not exceed 72 characters.');

        header('Location: ../admin/dentists');
        exit;
    }


    $newPasswordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );


    if ($newPasswordHash === false) {
        setFlash('danger', 'Unable to securely process the new password.');

        header('Location: ../admin/dentists');
        exit;
    }
}


/*
 * ---------------------------------------------------------
 * Update database
 * ---------------------------------------------------------
 */

try {

    $conn->begin_transaction();


    /*
     * -----------------------------------------------------
     * Dentist role
     * -----------------------------------------------------
     *
     * Clear credentials.
     */

    if ($isDentist) {

        $updateStmt = $conn->prepare("
            UPDATE dentists
            SET
                dentist_name = ?,
                username = NULL,
                email = NULL,
                email_hash = NULL,
                password = NULL,
                role_id = ?
            WHERE id = ?
        ");

        if (!$updateStmt) {
            throw new RuntimeException(
                'Failed to prepare dentist update.'
            );
        }

        $updateStmt->bind_param(
            'sii',
            $dentistName,
            $roleId,
            $dentistId
        );
    }


    /*
 * -----------------------------------------------------
 * Non-Dentist role + new password
 * -----------------------------------------------------
 */ elseif ($newPasswordHash !== null) {

        $updateStmt = $conn->prepare("
        UPDATE dentists
        SET
            dentist_name = ?,
            username = ?,
            email = ?,
            email_hash = ?,
            password = ?,
            role_id = ?
        WHERE id = ?
    ");

        if (!$updateStmt) {
            throw new RuntimeException(
                'Failed to prepare credential update: ' . $conn->error
            );
        }

        $updateStmt->bind_param(
            'ssssssi',
            $dentistName,
            $username,
            $encryptedEmail,
            $emailHash,
            $newPasswordHash,
            $roleId,
            $dentistId
        );
    }


    /*
     * -----------------------------------------------------
     * Non-Dentist role + keep existing password
     * -----------------------------------------------------
     */ else {

        $updateStmt = $conn->prepare("
            UPDATE dentists
            SET
                dentist_name = ?,
                username = ?,
                email = ?,
                email_hash = ?,
                role_id = ?
            WHERE id = ?
        ");

        if (!$updateStmt) {
            throw new RuntimeException(
                'Failed to prepare dentist update.'
            );
        }

        $updateStmt->bind_param(
            'ssssii',
            $dentistName,
            $username,
            $encryptedEmail,
            $emailHash,
            $roleId,
            $dentistId
        );
    }


    /*
     * -----------------------------------------------------
     * Execute update
     * -----------------------------------------------------
     */

    if (!$updateStmt->execute()) {
        throw new RuntimeException(
            'Failed to update dentist record.'
        );
    }


    $updateStmt->close();

    $conn->commit();


    /*
     * -----------------------------------------------------
     * Success
     * -----------------------------------------------------
     */

    setFlash('success', 'Dentist information updated successfully.');
} catch (Throwable $e) {

    /*
     * -----------------------------------------------------
     * Rollback
     * -----------------------------------------------------
     */

    if ($conn->errno === 0) {
        /*
         * No action needed here.
         *
         * The transaction is rolled back below.
         */
    }

    $conn->rollback();


    /*
     * Log the technical error server-side.
     *
     * Do NOT expose database errors to the user.
     */

    error_log(
        'Edit dentist error: ' . $e->getMessage()
    );


    setFlash('danger', 'Unable to update the dentist information. Please try again.');
}


/*
 * ---------------------------------------------------------
 * Redirect
 * ---------------------------------------------------------
 */

header('Location: ../admin/dentists');
exit;
