<?php

declare(strict_types=1);

require_once __DIR__ . '/session_config.php';

/*
 * Administrator middleware
 * Allows access only to authenticated dentists
 * with role_id = 1.
 */

if (!isset($_SESSION['dentist_id'], $_SESSION['role_id'])) {
    header('Location: /talaue_88_project/admin/');
    exit;
}

if ((int) $_SESSION['role_id'] !== 1) {
    http_response_code(403);
    exit('Access denied.');
}