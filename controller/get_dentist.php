<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../helper/encryption.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);

    exit;
}

/*
 * ---------------------------------------------------------
 * Validate dentist ID
 * ---------------------------------------------------------
 */

$dentistId = filter_input(
    INPUT_POST,
    'dentist_id',
    FILTER_VALIDATE_INT
);

if (!$dentistId || $dentistId < 1) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid dentist ID.'
    ]);

    exit;
}

/*
 * ---------------------------------------------------------
 * Fetch dentist
 * ---------------------------------------------------------
 */

$sql = "
    SELECT
        id,
        dentist_name,
        username,
        email,
        password,
        role_id
    FROM dentists
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare database query.'
    ]);

    exit;
}

$stmt->bind_param('i', $dentistId);

if (!$stmt->execute()) {
    $stmt->close();

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to retrieve dentist.'
    ]);

    exit;
}

$result = $stmt->get_result();

$dentist = $result->fetch_assoc();

$stmt->close();

if (!$dentist) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Dentist not found.'
    ]);

    exit;
}

/*
 * ---------------------------------------------------------
 * Determine whether the dentist has an account
 * ---------------------------------------------------------
 */

$hasAccount =
    !empty($dentist['username']) &&
    !empty($dentist['email']) &&
    !empty($dentist['password']);

/*
 * ---------------------------------------------------------
 * Decrypt email only if an account exists
 * ---------------------------------------------------------
 */

$email = null;

if ($dentist['email'] !== null && $dentist['email'] !== '') {

    $email = decryptEmail($dentist['email']);

    if ($email === null) {
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Unable to decrypt dentist email.'
        ]);

        exit;
    }
}

/*
 * ---------------------------------------------------------
 * Return dentist data
 * ---------------------------------------------------------
 */

echo json_encode([
    'success' => true,

    'dentist' => [
        'id' => (int) $dentist['id'],

        'dentist_name' =>
            $dentist['dentist_name'],

        'username' =>
            $dentist['username'],

        'email' =>
            $email,

        'role_id' =>
            (int) $dentist['role_id'],

        'has_account' =>
            $hasAccount
    ]
]);