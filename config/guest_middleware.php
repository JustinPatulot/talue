<?php

declare(strict_types=1);

require_once __DIR__ . '/session_config.php';

/*
 * Guest middleware
 * Prevents already authenticated users from
 * accessing the administrator sign-in page.
 */

if (isset($_SESSION['dentist_id'], $_SESSION['role_id'])) {

    if ((int) $_SESSION['role_id'] === 1) {
        header('Location: /talaue_88_project/admin/dashboard/');
        exit;
    }

    http_response_code(403);
    exit('Access denied.');
}