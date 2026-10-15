<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../helper/flash_helper.php';

/*
|--------------------------------------------------------------------------
| Only allow POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/services');
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF validation
|--------------------------------------------------------------------------
*/

$csrfToken = $_POST['csrf_token'] ?? '';

if (
    !is_string($csrfToken) ||
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $csrfToken)
) {
    setFlash(
        'danger',
        'Invalid security token. Please try again.'
    );

    header('Location: ../admin/services');
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate form action
|--------------------------------------------------------------------------
*/

$formAction = $_POST['form_action'] ?? '';

if ($formAction !== 'edit_service') {
    setFlash(
        'danger',
        'Invalid form submission.'
    );

    header('Location: ../admin/services');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get and validate service ID
|--------------------------------------------------------------------------
*/

$id = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id <= 0) {
    setFlash(
        'danger',
        'Invalid service ID.'
    );

    header('Location: ../admin/services');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get and validate dental service
|--------------------------------------------------------------------------
*/

$dentalService = trim(
    (string) ($_POST['dental_service'] ?? '')
);

if ($dentalService === '') {
    setFlash(
        'danger',
        'Dental service is required.'
    );

    header('Location: ../admin/services');
    exit;
}

/*
|--------------------------------------------------------------------------
| Check dental service length
|--------------------------------------------------------------------------
*/

if (mb_strlen($dentalService) > 255) {
    setFlash(
        'danger',
        'Dental service must not exceed 255 characters.'
    );

    header('Location: ../admin/services');
    exit;
}

/*
|--------------------------------------------------------------------------
| Check if dental service exists
|--------------------------------------------------------------------------
*/

$checkStmt = $conn->prepare(
    'SELECT id
     FROM dental_services
     WHERE id = ?
     LIMIT 1'
);

if (!$checkStmt) {
    setFlash(
        'danger',
        'Unable to process the dental service update.'
    );

    header('Location: ../admin/services');
    exit;
}

$checkStmt->bind_param('i', $id);
$checkStmt->execute();

$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    $checkStmt->close();

    setFlash(
        'danger',
        'Dental service not found.'
    );

    header('Location: ../admin/services');
    exit;
}

$checkStmt->close();

/*
|--------------------------------------------------------------------------
| Check for duplicate dental service
|--------------------------------------------------------------------------
|
| Exclude the current service ID so editing a service without changing
| its name does not trigger the duplicate check.
|
*/

$duplicateStmt = $conn->prepare(
    'SELECT id
     FROM dental_services
     WHERE dental_service = ?
     AND id != ?
     LIMIT 1'
);

if (!$duplicateStmt) {
    setFlash(
        'danger',
        'Unable to process the dental service update.'
    );

    header('Location: ../admin/services');
    exit;
}

$duplicateStmt->bind_param(
    'si',
    $dentalService,
    $id
);

$duplicateStmt->execute();

$duplicateResult = $duplicateStmt->get_result();

if ($duplicateResult->num_rows > 0) {
    $duplicateStmt->close();

    setFlash(
        'danger',
        'A dental service with this name already exists.'
    );

    header('Location: ../admin/services');
    exit;
}

$duplicateStmt->close();

/*
|--------------------------------------------------------------------------
| Update dental service
|--------------------------------------------------------------------------
*/

$updateStmt = $conn->prepare(
    'UPDATE dental_services
     SET dental_service = ?
     WHERE id = ?'
);

if (!$updateStmt) {
    setFlash(
        'danger',
        'Unable to update dental service.'
    );

    header('Location: ../admin/services');
    exit;
}

$updateStmt->bind_param(
    'si',
    $dentalService,
    $id
);

if ($updateStmt->execute()) {

    if ($updateStmt->affected_rows > 0) {

        setFlash(
            'success',
            'Dental service updated successfully.'
        );
    } else {

        /*
         * The service exists, but its value was not changed.
         */
        setFlash(
            'info',
            'No changes were made to the dental service.'
        );
    }
} else {

    setFlash(
        'danger',
        'Failed to update dental service.'
    );
}

$updateStmt->close();

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ../admin/services');
exit;
