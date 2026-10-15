<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function notificationJson(array $data, int $status = 200): never
{
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

try {
    if (!isset($_SESSION['dentist_id'])) {
        notificationJson([
            'success' => false,
            'message' => 'Authentication required.'
        ], 401);
    }

    $recipientId = (int) $_SESSION['dentist_id'];

    if ($recipientId <= 0) {
        notificationJson([
            'success' => false,
            'message' => 'Invalid session.'
        ], 401);
    }

    // Always retrieve the role from the database.
    $roleStmt = $conn->prepare("
        SELECT role_id
        FROM dentists
        WHERE id = ?
        LIMIT 1
    ");

    $roleStmt->bind_param('i', $recipientId);
    $roleStmt->execute();

    $roleResult = $roleStmt->get_result();
    $staff = $roleResult->fetch_assoc();

    $roleStmt->close();

    if (!$staff || !in_array((int) $staff['role_id'], [1, 2], true)) {
        notificationJson([
            'success' => false,
            'message' => 'You are not authorized to view these notifications.'
        ], 403);
    }

    $roleId = (int) $staff['role_id'];

    // Assistants cannot view dentist-change notifications.
    $typeCondition = $roleId === 1
        ? ''
        : " AND notification_type <> 'dentist_change_requested' ";

    $countSql = "
        SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(is_read = 0), 0) AS unread_count
        FROM notifications
        WHERE recipient_id = ?
        {$typeCondition}
    ";

    $countStmt = $conn->prepare($countSql);
    $countStmt->bind_param('i', $recipientId);
    $countStmt->execute();

    $counts = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();

    $listSql = "
        SELECT
            n.id,
            n.booking_id,
            n.doctor_change_request_id,
            n.notification_type,
            n.title,
            n.message,
            n.is_read,
            n.created_at,
            b.reference_number
        FROM notifications AS n
        LEFT JOIN bookings AS b
            ON b.id = n.booking_id
        WHERE n.recipient_id = ?
        {$typeCondition}
        ORDER BY n.created_at DESC, n.id DESC
        LIMIT 10
    ";

    $listStmt = $conn->prepare($listSql);
    $listStmt->bind_param('i', $recipientId);
    $listStmt->execute();

    $result = $listStmt->get_result();
    $notifications = [];

    while ($row = $result->fetch_assoc()) {
        $typeMap = [
            'booking_created' => 'booking',
            'booking_cancelled' => 'cancel',
            'booking_rescheduled' => 'reschedule',
            'dentist_change_requested' => 'change'
        ];

        $row['id'] = (int) $row['id'];
        $row['booking_id'] = $row['booking_id'] !== null
            ? (int) $row['booking_id']
            : null;
        $row['doctor_change_request_id'] =
            $row['doctor_change_request_id'] !== null
            ? (int) $row['doctor_change_request_id']
            : null;
        $row['is_read'] = (bool) $row['is_read'];
        $row['type'] = $typeMap[$row['notification_type']] ?? 'booking';

        $notifications[] = $row;
    }

    $listStmt->close();

    notificationJson([
        'success' => true,
        'counts' => [
            'all' => (int) $counts['total_count'],
            'unread' => (int) $counts['unread_count']
        ],
        'notifications' => $notifications,
        'server_time' => date('c')
    ]);
} catch (Throwable $exception) {
    error_log('Notification fetch error: ' . $exception->getMessage());

    notificationJson([
        'success' => false,
        'message' => 'Unable to load notifications.'
    ], 500);
}
