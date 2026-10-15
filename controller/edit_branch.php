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
    header('Location: ../admin/branches');
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

    header('Location: ../admin/branches');
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate form action
|--------------------------------------------------------------------------
*/

$formAction = $_POST['form_action'] ?? '';

if ($formAction !== 'edit_branch') {
    setFlash(
        'danger',
        'Invalid form submission.'
    );

    header('Location: ../view/admin/branches.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get and validate branch ID
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
        'Invalid branch ID.'
    );

    header('Location: ../admin/branches');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get and validate branch name
|--------------------------------------------------------------------------
*/

$branchName = trim($_POST['branch_name'] ?? '');

if ($branchName === '') {
    setFlash(
        'danger',
        'Branch name is required.'
    );

    header('Location: ../admin/branches');
    exit;
}

/*
|--------------------------------------------------------------------------
| Check branch name length
|--------------------------------------------------------------------------
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
|--------------------------------------------------------------------------
| Check if branch exists
|--------------------------------------------------------------------------
*/

$checkStmt = $conn->prepare(
    'SELECT id
     FROM branches
     WHERE id = ?
     LIMIT 1'
);

if (!$checkStmt) {
    setFlash(
        'danger',
        'Unable to process the branch update.'
    );

    header('Location: ../admin/branches');
    exit;
}

$checkStmt->bind_param('i', $id);
$checkStmt->execute();

$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    $checkStmt->close();

    setFlash(
        'danger',
        'Branch not found.'
    );

    header('Location: ../admin/branches');
    exit;
}

$checkStmt->close();

/*
|--------------------------------------------------------------------------
| Check for duplicate branch name
|--------------------------------------------------------------------------
|
| Exclude the current branch ID so editing a branch without changing
| its name does not trigger the duplicate check.
|
*/

$duplicateStmt = $conn->prepare(
    'SELECT id
     FROM branches
     WHERE branch_name = ?
     AND id != ?
     LIMIT 1'
);

if (!$duplicateStmt) {
    setFlash(
        'danger',
        'Unable to process the branch update.'
    );

    header('Location: ../admin/branches');
    exit;
}

$duplicateStmt->bind_param(
    'si',
    $branchName,
    $id
);

$duplicateStmt->execute();

$duplicateResult = $duplicateStmt->get_result();

if ($duplicateResult->num_rows > 0) {
    $duplicateStmt->close();

    setFlash(
        'danger',
        'A branch with this name already exists.'
    );

    header('Location: ../admin/branches');
    exit;
}

$duplicateStmt->close();

/*
|--------------------------------------------------------------------------
| Update branch
|--------------------------------------------------------------------------
*/

$updateStmt = $conn->prepare(
    'UPDATE branches
     SET branch_name = ?
     WHERE id = ?'
);

if (!$updateStmt) {
    setFlash(
        'danger',
        'Unable to update branch.'
    );

    header('Location: ../admin/branches');
    exit;
}

$updateStmt->bind_param(
    'si',
    $branchName,
    $id
);

if ($updateStmt->execute()) {

    if ($updateStmt->affected_rows > 0) {
        setFlash(
            'success',
            'Branch updated successfully.'
        );
    } else {
        /*
         * The branch exists, but its value was not changed.
         */
        setFlash(
            'info',
            'No changes were made to the branch.'
        );
    }
} else {
    setFlash(
        'danger',
        'Failed to update branch.'
    );
}

$updateStmt->close();

/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

header('Location: ../admin/branches');
exit;
