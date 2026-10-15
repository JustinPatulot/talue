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
    header('Location: ../admin/branches');
    exit;
}


/*
 * ---------------------------------------------------------
 * Form action
 * ---------------------------------------------------------
 */

$formAction = $_POST['form_action'] ?? '';

if ($formAction !== 'add_branch') {
    header('Location: ../admin/branches');
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

    header('Location: ../admin/branches');
    exit;
}


/*
 * ---------------------------------------------------------
 * Get and validate branch name
 * ---------------------------------------------------------
 */

$branchName = trim((string) ($_POST['branch_name'] ?? ''));

if ($branchName === '') {
    setFlash(
        'danger',
        'Branch name is required.'
    );

    header('Location: ../admin/branches');
    exit;
}


/*
 * ---------------------------------------------------------
 * Length validation
 * ---------------------------------------------------------
 */

if (mb_strlen($branchName) > 255) {
    setFlash(
        'danger',
        'Branch name must not exceed 255 characters.'
    );

    header('Location: ../admin/branches');
    exit;
}


/*
 * ---------------------------------------------------------
 * Check for duplicate branch name
 * ---------------------------------------------------------
 */

$sql = "
    SELECT id
    FROM branches
    WHERE branch_name = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    setFlash(
        'danger',
        'Unable to process the request. Please try again.'
    );

    header('Location: ../admin/branches');
    exit;
}

$stmt->bind_param('s', $branchName);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->close();

    setFlash(
        'warning',
        'A branch with this name already exists.'
    );

    header('Location: ../admin/branches');
    exit;
}

$stmt->close();


/*
 * ---------------------------------------------------------
 * Insert branch
 * ---------------------------------------------------------
 */

$sql = "
    INSERT INTO branches (branch_name)
    VALUES (?)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    setFlash(
        'danger',
        'Unable to add the branch. Please try again.'
    );

    header('Location: ../admin/branches');
    exit;
}

$stmt->bind_param('s', $branchName);

if ($stmt->execute()) {

    $stmt->close();

    setFlash(
        'success',
        'Branch added successfully.'
    );

    header('Location: ../admin/branches');
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
    'Unable to add the branch. Please try again.'
);

header('Location: ../admin/branches');
exit;
