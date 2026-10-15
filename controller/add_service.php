<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/middleware.php';
require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../helper/flash_helper.php';

/*
 * ---------------------------------------------------------
 * Request method
 * ---------------------------------------------------------
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/services');
    exit;
}


/*
 * ---------------------------------------------------------
 * Form action
 * ---------------------------------------------------------
 */

$formAction = $_POST['form_action'] ?? '';

if ($formAction !== 'add_service') {
    header('Location: ../admin/services');
    exit;
}


/*
 * ---------------------------------------------------------
 * CSRF validation
 * ---------------------------------------------------------
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
 * ---------------------------------------------------------
 * Get and validate dental service
 * ---------------------------------------------------------
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
 * ---------------------------------------------------------
 * Length validation
 * ---------------------------------------------------------
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
 * ---------------------------------------------------------
 * Check for duplicate dental service
 * ---------------------------------------------------------
 */

$sql = "
    SELECT id
    FROM dental_services
    WHERE dental_service = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    setFlash(
        'danger',
        'Unable to process the request. Please try again.'
    );

    header('Location: ../admin/services');
    exit;
}

$stmt->bind_param('s', $dentalService);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();

    setFlash(
        'warning',
        'A dental service with this name already exists.'
    );

    header('Location: ../admin/services');
    exit;
}

$stmt->close();


/*
 * ---------------------------------------------------------
 * Insert dental service
 * ---------------------------------------------------------
 */

$sql = "
    INSERT INTO dental_services (dental_service)
    VALUES (?)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    setFlash(
        'danger',
        'Unable to add the dental service. Please try again.'
    );

    header('Location: ../admin/services');
    exit;
}

$stmt->bind_param('s', $dentalService);

if ($stmt->execute()) {

    $stmt->close();

    setFlash(
        'success',
        'Dental service added successfully.'
    );

    header('Location: ../admin/services');
    exit;
}


/*
 * ---------------------------------------------------------
 * Insert failed
 * ---------------------------------------------------------
 */

$stmt->close();

setFlash(
    'danger',
    'Unable to add the dental service. Please try again.'
);

header('Location: ../admin/services');
exit;
