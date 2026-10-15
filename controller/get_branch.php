<?php

declare(strict_types=1);

require_once __DIR__ . '/connection.php';
require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../config/middleware.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);

    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id <= 0) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid branch ID.'
    ]);

    exit;
}

$stmt = $conn->prepare(
    'SELECT id, branch_name
     FROM branches
     WHERE id = ?
     LIMIT 1'
);

$stmt->bind_param('i', $id);
$stmt->execute();

$result = $stmt->get_result();
$branch = $result->fetch_assoc();

$stmt->close();

if (!$branch) {
    http_response_code(404);

    echo json_encode([
        'success' => false,
        'message' => 'Branch not found.'
    ]);

    exit;
}

echo json_encode([
    'success' => true,
    'branch' => [
        'id' => (int) $branch['id'],
        'branch_name' => $branch['branch_name']
    ]
]);
