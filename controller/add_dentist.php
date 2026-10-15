<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../helper/encryption.php';
require_once __DIR__ . '/../helper/flash_helper.php';


/*
 * ---------------------------------------------------------
 * CSRF token
 * ---------------------------------------------------------
 */

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


/*
 * ---------------------------------------------------------
 * Handle Add Dentist
 * ---------------------------------------------------------
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formAction = $_POST['form_action'] ?? '';

    if ($formAction === 'add_dentist') {

        $hasError = false;


        /*
         * -----------------------------------------------------
         * CSRF
         * -----------------------------------------------------
         */

        $csrfToken = $_POST['csrf_token'] ?? '';

        if (
            !is_string($csrfToken) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $csrfToken)
        ) {
            setFlash(
                'danger',
                'Invalid request. Please refresh the page and try again.'
            );

            header('Location: ../admin/dentists');
            exit;
        }


        /*
         * -----------------------------------------------------
         * Input
         * -----------------------------------------------------
         */

        $dentistName = trim(
            (string) ($_POST['dentist_name'] ?? '')
        );

        $username = trim(
            (string) ($_POST['username'] ?? '')
        );

        $email = trim(
            (string) ($_POST['email'] ?? '')
        );

        $password = (string) ($_POST['password'] ?? '');

        $roleId = filter_input(
            INPUT_POST,
            'role_id',
            FILTER_VALIDATE_INT
        );


        /*
         * -----------------------------------------------------
         * Dentist name
         * -----------------------------------------------------
         */

        if ($dentistName === '') {

            $hasError = true;

            setFlash(
                'danger',
                'Dentist name is required.'
            );

        } elseif (mb_strlen($dentistName) > 150) {

            $hasError = true;

            setFlash(
                'danger',
                'Dentist name must not exceed 150 characters.'
            );
        }


        /*
         * -----------------------------------------------------
         * Role
         * -----------------------------------------------------
         */

        if (
            !$hasError &&
            (
                $roleId === false ||
                $roleId === null ||
                $roleId <= 0
            )
        ) {
            $hasError = true;

            setFlash(
                'danger',
                'Please select a valid role.'
            );
        }


        /*
         * -----------------------------------------------------
         * Get role from database
         * -----------------------------------------------------
         */

        $roleName = null;

        if (!$hasError) {

            $roleStmt = $conn->prepare(
                'SELECT role_name
                 FROM roles
                 WHERE id = ?
                 LIMIT 1'
            );

            if (!$roleStmt) {

                $hasError = true;

                setFlash(
                    'danger',
                    'Unable to process the request.'
                );

            } else {

                $roleStmt->bind_param(
                    'i',
                    $roleId
                );

                if (!$roleStmt->execute()) {

                    $hasError = true;

                    setFlash(
                        'danger',
                        'Unable to process the request.'
                    );

                } else {

                    $roleResult = $roleStmt->get_result();

                    $roleRow = $roleResult->fetch_assoc();

                    if (!$roleRow) {

                        $hasError = true;

                        setFlash(
                            'danger',
                            'The selected role does not exist.'
                        );

                    } else {

                        $roleName = trim(
                            (string) $roleRow['role_name']
                        );
                    }
                }

                $roleStmt->close();
            }
        }


        /*
         * -----------------------------------------------------
         * Determine Dentist role
         * -----------------------------------------------------
         */

        $isDentist = (
            !$hasError &&
            $roleName !== null &&
            strcasecmp($roleName, 'Dentist') === 0
        );


        /*
         * -----------------------------------------------------
         * Validate account fields
         * -----------------------------------------------------
         */

        if (!$hasError) {

            if ($isDentist) {

                /*
                 * Dentist does not have an account.
                 */

                $username = null;
                $email = null;
                $password = null;

            } else {

                /*
                 * Username
                 */

                if ($username === '') {

                    $hasError = true;

                    setFlash(
                        'danger',
                        'Username is required for this role.'
                    );

                } elseif (
                    !preg_match(
                        '/^[A-Za-z0-9._-]{3,50}$/',
                        $username
                    )
                ) {

                    $hasError = true;

                    setFlash(
                        'danger',
                        'Username must be 3–50 characters and may only contain letters, numbers, dots, underscores, and hyphens.'
                    );
                }


                /*
                 * Email
                 */

                if (!$hasError) {

                    if ($email === '') {

                        $hasError = true;

                        setFlash(
                            'danger',
                            'Email is required for this role.'
                        );

                    } elseif (
                        !filter_var(
                            $email,
                            FILTER_VALIDATE_EMAIL
                        )
                    ) {

                        $hasError = true;

                        setFlash(
                            'danger',
                            'Please enter a valid email address.'
                        );

                    } elseif (mb_strlen($email) > 255) {

                        $hasError = true;

                        setFlash(
                            'danger',
                            'Email address is too long.'
                        );
                    }
                }


                /*
                 * Password
                 */

                if (!$hasError) {

                    if ($password === '') {

                        $hasError = true;

                        setFlash(
                            'danger',
                            'Password is required for this role.'
                        );

                    } elseif (strlen($password) < 6) {

                        $hasError = true;

                        setFlash(
                            'danger',
                            'Password must be at least 6 characters.'
                        );

                    } elseif (strlen($password) > 72) {

                        $hasError = true;

                        setFlash(
                            'danger',
                            'Password must not exceed 72 characters.'
                        );
                    }
                }
            }
        }


        /*
         * -----------------------------------------------------
         * Duplicate username/email
         * -----------------------------------------------------
         */

        $emailHash = null;

        if (
            !$hasError &&
            !$isDentist
        ) {

            /*
             * Generate deterministic email hash.
             */

            $emailHash = hashEmail($email);


            /*
             * Check duplicate username.
             */

            $usernameStmt = $conn->prepare(
                'SELECT id
                 FROM dentists
                 WHERE username = ?
                 LIMIT 1'
            );

            if (!$usernameStmt) {

                $hasError = true;

                setFlash(
                    'danger',
                    'Unable to process the request.'
                );

            } else {

                $usernameStmt->bind_param(
                    's',
                    $username
                );

                if (!$usernameStmt->execute()) {

                    $hasError = true;

                    setFlash(
                        'danger',
                        'Unable to process the request.'
                    );

                } else {

                    $usernameResult =
                        $usernameStmt->get_result();

                    if ($usernameResult->num_rows > 0) {

                        $hasError = true;

                        setFlash(
                            'danger',
                            'The username is already taken.'
                        );
                    }
                }

                $usernameStmt->close();
            }


            /*
             * Check duplicate email.
             */

            if (!$hasError) {

                $emailStmt = $conn->prepare(
                    'SELECT id
                     FROM dentists
                     WHERE email_hash = ?
                     LIMIT 1'
                );

                if (!$emailStmt) {

                    $hasError = true;

                    setFlash(
                        'danger',
                        'Unable to process the request.'
                    );

                } else {

                    $emailStmt->bind_param(
                        's',
                        $emailHash
                    );

                    if (!$emailStmt->execute()) {

                        $hasError = true;

                        setFlash(
                            'danger',
                            'Unable to process the request.'
                        );

                    } else {

                        $emailResult =
                            $emailStmt->get_result();

                        if ($emailResult->num_rows > 0) {

                            $hasError = true;

                            setFlash(
                                'danger',
                                'The email is already registered.'
                            );
                        }
                    }

                    $emailStmt->close();
                }
            }
        }


        /*
         * -----------------------------------------------------
         * Password hash
         * -----------------------------------------------------
         */

        $passwordHash = null;

        if (
            !$hasError &&
            !$isDentist
        ) {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            if ($passwordHash === false) {

                $hasError = true;

                setFlash(
                    'danger',
                    'Unable to securely process the password.'
                );
            }
        }


        /*
         * -----------------------------------------------------
         * Encrypt email
         * -----------------------------------------------------
         */

        $encryptedEmail = null;

        if (
            !$hasError &&
            !$isDentist
        ) {

            $encryptedEmail = encryptEmail($email);

            if ($encryptedEmail === '') {

                $hasError = true;

                setFlash(
                    'danger',
                    'Unable to securely process the email address.'
                );
            }
        }


        /*
         * -----------------------------------------------------
         * Insert
         * -----------------------------------------------------
         */

        if (!$hasError) {

            $conn->begin_transaction();

            try {

                if ($isDentist) {

                    $insertStmt = $conn->prepare(
                        'INSERT INTO dentists
                        (
                            dentist_name,
                            username,
                            email,
                            password,
                            role_id
                        )
                        VALUES
                        (
                            ?,
                            NULL,
                            NULL,
                            NULL,
                            ?
                        )'
                    );

                    if (!$insertStmt) {
                        throw new RuntimeException(
                            'Failed to prepare insert.'
                        );
                    }

                    $insertStmt->bind_param(
                        'si',
                        $dentistName,
                        $roleId
                    );

                } else {

                    $insertStmt = $conn->prepare(
                        'INSERT INTO dentists
                        (
                            dentist_name,
                            username,
                            email,
                            email_hash,
                            password,
                            role_id
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?
                        )'
                    );

                    if (!$insertStmt) {
                        throw new RuntimeException(
                            'Failed to prepare insert.'
                        );
                    }

                    $insertStmt->bind_param(
                        'sssssi',
                        $dentistName,
                        $username,
                        $encryptedEmail,
                        $emailHash,
                        $passwordHash,
                        $roleId
                    );
                }


                if (!$insertStmt->execute()) {
                    throw new RuntimeException(
                        'Failed to execute insert.'
                    );
                }

                $insertStmt->close();

                $conn->commit();


                /*
                 * -------------------------------------------------
                 * Success
                 * -------------------------------------------------
                 */

                setFlash(
                    'success',
                    'Dentist added successfully.'
                );


                /*
                 * Rotate CSRF token.
                 */

                $_SESSION['csrf_token'] =
                    bin2hex(random_bytes(32));


                /*
                 * PRG
                 */

                header('Location: ../admin/dentists');
                exit;

            } catch (Throwable $e) {

                if ($conn->in_transaction) {
                    $conn->rollback();
                }

                /*
                 * Generic user-facing error.
                 */

                setFlash(
                    'danger',
                    'Unable to add the dentist. Please try again.'
                );

                /*
                 * Log the actual exception server-side.
                 *
                 * Do not expose $e->getMessage() to users.
                 */

                error_log(
                    'Add dentist failed: ' . $e->getMessage()
                );

                header('Location: ../admin/dentists');
                exit;
            }
        }


        /*
         * -----------------------------------------------------
         * Validation failure
         * -----------------------------------------------------
         */

        if ($hasError) {

            /*
             * Redirect so refreshing the page does not
             * resubmit the POST request.
             */

            header('Location: ../admin/dentists');
            exit;
        }
    }
}


/*
 * ---------------------------------------------------------
 * Fetch roles
 * ---------------------------------------------------------
 */

$roles = [];

$sql = "
    SELECT id, role_name
    FROM roles
    ORDER BY id ASC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
}
