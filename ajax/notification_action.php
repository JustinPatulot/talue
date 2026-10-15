<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Return a JSON response and stop execution.
 */
function notificationActionJson(
    array $data,
    int $status = 200
): never {
    http_response_code($status);

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

try {
    // ---------------------------------------------------------
    // 1. Validate HTTP method
    // ---------------------------------------------------------

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');

        notificationActionJson([
            'success' => false,
            'message' => 'POST required.'
        ], 405);
    }

    // ---------------------------------------------------------
    // 2. Authenticate staff account
    // ---------------------------------------------------------

    if (!isset($_SESSION['dentist_id'])) {
        notificationActionJson([
            'success' => false,
            'message' => 'Authentication required.'
        ], 401);
    }

    $recipientId = (int) $_SESSION['dentist_id'];

    if ($recipientId < 1) {
        notificationActionJson([
            'success' => false,
            'message' => 'Invalid account.'
        ], 401);
    }

    // ---------------------------------------------------------
    // 3. Validate CSRF token
    // ---------------------------------------------------------

    $sessionToken = $_SESSION['notification_csrf'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($sessionToken) ||
        !is_string($submittedToken) ||
        $sessionToken === '' ||
        !hash_equals($sessionToken, $submittedToken)
    ) {
        notificationActionJson([
            'success' => false,
            'message' => 'Security token invalid. Refresh the page and try again.'
        ], 403);
    }

    // ---------------------------------------------------------
    // 4. Retrieve and validate staff role
    // ---------------------------------------------------------

    $roleStmt = $conn->prepare("
        SELECT role_id
        FROM dentists
        WHERE id = ?
        LIMIT 1
    ");

    $roleStmt->bind_param('i', $recipientId);
    $roleStmt->execute();

    $staff = $roleStmt->get_result()->fetch_assoc();
    $roleStmt->close();

    if (
        !$staff ||
        !in_array((int) $staff['role_id'], [1, 2], true)
    ) {
        notificationActionJson([
            'success' => false,
            'message' => 'Not authorized.'
        ], 403);
    }

    $roleId = (int) $staff['role_id'];

    // ---------------------------------------------------------
    // 5. Validate action
    // ---------------------------------------------------------

    $action = $_POST['action'] ?? '';

    if (!is_string($action)) {
        notificationActionJson([
            'success' => false,
            'message' => 'Invalid action.'
        ], 400);
    }

    /*
     * Admin (role 1): all notification types.
     * Dental assistant (role 2): excludes dentist-change requests.
     */
    $typeCondition = $roleId === 1
        ? ''
        : " AND notification_type <> 'dentist_change_requested' ";

    // ---------------------------------------------------------
    // 6. Mark all visible notifications as read
    // ---------------------------------------------------------

    if ($action === 'read_all') {
        $sql = "
            UPDATE notifications
            SET is_read = 1,
                read_at = CURRENT_TIMESTAMP
            WHERE recipient_id = ?
              AND is_read = 0
              {$typeCondition}
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $recipientId);
        $stmt->execute();
        $stmt->close();

        notificationActionJson([
            'success' => true,
            'message' => 'Notifications marked as read.'
        ]);
    }

    // ---------------------------------------------------------
    // 7. Clear all visible notifications
    // ---------------------------------------------------------

    if ($action === 'clear_all') {
        $sql = "
            DELETE FROM notifications
            WHERE recipient_id = ?
            {$typeCondition}
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $recipientId);
        $stmt->execute();

        $deletedCount = $stmt->affected_rows;

        $stmt->close();

        notificationActionJson([
            'success' => true,
            'message' => 'Notifications cleared successfully.',
            'deleted_count' => $deletedCount
        ]);
    }


    // ---------------------------------------------------------
    // 8. Validate individual notification actions
    // ---------------------------------------------------------

    if (!in_array($action, ['read', 'unread', 'delete'], true)) {
        notificationActionJson([
            'success' => false,
            'message' => 'Unsupported action.'
        ], 400);
    }

    $notificationId = filter_input(
        INPUT_POST,
        'notification_id',
        FILTER_VALIDATE_INT
    );

    if (!$notificationId || $notificationId < 1) {
        notificationActionJson([
            'success' => false,
            'message' => 'Invalid notification ID.'
        ], 400);
    }


    // ---------------------------------------------------------
    // 9. Delete individual notification
    // ---------------------------------------------------------

    if ($action === 'delete') {
        $sql = "
            DELETE FROM notifications
            WHERE id = ?
              AND recipient_id = ?
              {$typeCondition}
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            'ii',
            $notificationId,
            $recipientId
        );

        $stmt->execute();
        $deletedCount = $stmt->affected_rows;
        $stmt->close();

        if ($deletedCount !== 1) {
            notificationActionJson([
                'success' => false,
                'message' => 'Notification not found or you do not have permission to clear it.'
            ], 404);
        }

        notificationActionJson([
            'success' => true,
            'message' => 'Notification cleared successfully.'
        ]);
    }

    // ---------------------------------------------------------
    // 10. Update individual notification
    // ---------------------------------------------------------

    $isRead = $action === 'read' ? 1 : 0;

    $sql = "
        UPDATE notifications
        SET is_read = ?,
            read_at = CASE
                WHEN ? = 1 THEN CURRENT_TIMESTAMP
                ELSE NULL
            END
        WHERE id = ?
          AND recipient_id = ?
          {$typeCondition}
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        'iiii',
        $isRead,
        $isRead,
        $notificationId,
        $recipientId
    );

    $stmt->execute();
    $stmt->close();

    /*
     * Do not reveal whether another user's notification exists.
     */
    notificationActionJson([
        'success' => true,
        'message' => 'Notification updated.'
    ]);
} catch (Throwable $exception) {
    error_log(
        'Notification action error: ' . $exception->getMessage()
    );

    notificationActionJson([
        'success' => false,
        'message' => 'Unable to process notification action.'
    ], 500);
}
