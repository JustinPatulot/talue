<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../controller/connection.php';
require_once __DIR__ . '/../../config/session_config.php';
require_once __DIR__ . '/../../helper/flash_helper.php';

function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
 * Pagination settings
 */
$entriesPerPage = 10;

$page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    [
        'options' => [
            'default' => 1,
            'min_range' => 1
        ]
    ]
);

$search = trim((string) ($_GET['search'] ?? ''));

/*
 * Count total dentist change requests
 */
if ($search !== '') {

    $countSql = "
        SELECT COUNT(*) AS total
        FROM doctor_change_requests dcr

        INNER JOIN bookings b
            ON b.id = dcr.booking_id

        INNER JOIN dentists current_d
            ON current_d.id = dcr.current_dentist_id

        INNER JOIN dentists requested_d
            ON requested_d.id = dcr.requested_dentist_id

        WHERE
            b.reference_number LIKE ?
            OR b.full_name LIKE ?
            OR current_d.dentist_name LIKE ?
            OR requested_d.dentist_name LIKE ?
            OR dcr.status LIKE ?
    ";

    $countStmt = $conn->prepare($countSql);

    if (!$countStmt) {
        die('Unable to prepare dentist change request count query: '
            . $conn->error);
    }

    $searchTerm = '%' . $search . '%';

    $countStmt->bind_param(
        'sssss',
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );
} else {

    $countSql = "
        SELECT COUNT(*) AS total
        FROM doctor_change_requests
    ";

    $countStmt = $conn->prepare($countSql);

    if (!$countStmt) {
        die('Unable to prepare dentist change request count query: '
            . $conn->error);
    }
}

if (!$countStmt->execute()) {
    die('Unable to execute dentist change request count query: '
        . $countStmt->error);
}

$countResult = $countStmt->get_result();

$totalRequests = (int) $countResult->fetch_assoc()['total'];

$countStmt->close();

/*
 * Calculate total pages
 */
$totalPages = max(
    1,
    (int) ceil($totalRequests / $entriesPerPage)
);

/*
 * Prevent invalid page numbers
 */
$page = min($page, $totalPages);

$offset = ($page - 1) * $entriesPerPage;

/*
 * Get dentist change requests
 */
if ($search !== '') {

    $sql = "
        SELECT
            dcr.id,
            dcr.booking_id,
            dcr.current_dentist_id,
            dcr.requested_dentist_id,
            dcr.reason,
            dcr.status,

            b.reference_number,
            b.full_name,

            current_d.dentist_name AS current_dentist_name,

            requested_d.dentist_name AS requested_dentist_name

        FROM doctor_change_requests dcr

        INNER JOIN bookings b
            ON b.id = dcr.booking_id

        INNER JOIN dentists current_d
            ON current_d.id = dcr.current_dentist_id

        INNER JOIN dentists requested_d
            ON requested_d.id = dcr.requested_dentist_id

        WHERE
            b.reference_number LIKE ?
            OR b.full_name LIKE ?
            OR current_d.dentist_name LIKE ?
            OR requested_d.dentist_name LIKE ?
            OR dcr.status LIKE ?

        ORDER BY dcr.id DESC

        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Unable to prepare dentist change request query: '
            . $conn->error);
    }

    $stmt->bind_param(
        'sssssii',
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $entriesPerPage,
        $offset
    );
} else {

    $sql = "
        SELECT
            dcr.id,
            dcr.booking_id,
            dcr.current_dentist_id,
            dcr.requested_dentist_id,
            dcr.reason,
            dcr.status,

            b.reference_number,
            b.full_name,

            current_d.dentist_name AS current_dentist_name,

            requested_d.dentist_name AS requested_dentist_name

        FROM doctor_change_requests dcr

        INNER JOIN bookings b
            ON b.id = dcr.booking_id

        INNER JOIN dentists current_d
            ON current_d.id = dcr.current_dentist_id

        INNER JOIN dentists requested_d
            ON requested_d.id = dcr.requested_dentist_id

        ORDER BY dcr.id DESC

        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Unable to prepare dentist change request query: '
            . $conn->error);
    }

    $stmt->bind_param(
        'ii',
        $entriesPerPage,
        $offset
    );
}

if (!$stmt->execute()) {
    die('Unable to execute dentist change request query: '
        . $stmt->error);
}

$result = $stmt->get_result();

if (!$result) {
    die('Unable to retrieve dentist change request results: '
        . $stmt->error);
}

$requests = [];

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Dentist Change Requests - Talaue 88 - Dental Clinic
    </title>

    <link
        rel="icon"
        type="image/png"
        href="../../assets/TALAUE88.png">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.0/css/all.min.css"
        integrity="sha512-ApSLB1Pd3/bZN8fWB/RG9YhN/7bd9Hkf3AGaE2mPfebjrxagjuBtx2GcgdqIlJkUzwylBo61r9Xa9NmgBI0swA=="
        crossorigin="anonymous"
        referrerpolicy="no-referrer">

    <link
        rel="stylesheet"
        href="../../node_modules/bootstrap/dist/css/bootstrap.min.css">

    <link
        rel="stylesheet"
        href="../../styles/admin_styles.css">

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@6..144,1..1000&display=swap"
        rel="stylesheet">

</head>

<body>

    <?php include '../../components/admin_navbar.php'; ?>

    <?php include '../../components/admin_floating_navbar.php'; ?>

    <?php include '../../components/change_dentist_approve_modal.php'; ?>

    <?php include '../../components/change_dentist_reject_modal.php'; ?>


    <h1 class="px-3 mb-4">
        Dentist Change Requests
    </h1>


    <div class="container-fluid px-3 admin-page-content">


        <!-- Search -->
        <div class="d-flex justify-content-center">

            <form
                method="GET"
                class="input-group admin-search">

                <input
                    type="text"
                    class="form-control"
                    id="search"
                    name="search"
                    placeholder="Search Requests"
                    value="<?= e($search) ?>">

                <button
                    class="btn btn-dark"
                    type="submit">

                    <i class="fa-solid fa-magnifying-glass me-1"></i>

                    Search

                </button>

            </form>

        </div>


        <!-- Flash Messages -->
        <?php

        $flashMessages = getFlashMessages();

        if (!empty($flashMessages)):
        ?>

            <div class="mt-3">

                <?php foreach ($flashMessages as $flash): ?>

                    <div
                        class="alert alert-<?= e($flash['type']) ?> flash-message"
                        role="alert">

                        <?= e($flash['message']) ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>


        <!-- Dentist Change Request Table -->
        <div class="admin-table-container mt-4">

            <div class="table-responsive">

                <table class="table admin-table mb-0">

                    <thead>

                        <tr>

                            <th scope="col">#</th>

                            <th scope="col">
                                Booking
                            </th>

                            <th scope="col">
                                Patient
                            </th>

                            <th scope="col">
                                Current Dentist
                            </th>

                            <th scope="col">
                                Requested Dentist
                            </th>

                            <th scope="col">
                                Reason
                            </th>

                            <th scope="col">
                                Status
                            </th>

                            <th
                                scope="col"
                                class="text-end">

                                Actions

                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($requests)): ?>

                            <tr>

                                <td
                                    colspan="8"
                                    class="text-center py-4">

                                    <?php if ($search !== ''): ?>

                                        No dentist change requests
                                        found matching your search.

                                    <?php else: ?>

                                        No dentist change requests found.

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($requests as $number => $request): ?>

                                <tr>

                                    <th scope="row">

                                        <?= $offset + $number + 1 ?>

                                    </th>


                                    <td>

                                        <?= e(
                                            (string) $request['reference_number']
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            (string) $request['full_name']
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            (string) $request['current_dentist_name']
                                        ) ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            (string) $request['requested_dentist_name']
                                        ) ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            trim((string) $request['reason']) !== ''
                                        ): ?>

                                            <?= e(
                                                (string) $request['reason']
                                            ) ?>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                No reason provided
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?= e(
                                            (string) $request['status']
                                        ) ?>

                                    </td>


                                    <td class="text-end admin-action-cell">

                                        <?php
                                        $status = (string) $request['status'];
                                        ?>

                                        <?php if ($status === 'Pending Approval'): ?>

                                            <div class="admin-actions">
                                                <!-- Approve -->
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-dark"
                                                    aria-label="Approve dentist change request"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#changeDentistApproveModal"
                                                    data-reference-number="<?= e(
                                                                                (string) $request['reference_number']
                                                                            ) ?>">

                                                    <i class="fa-solid fa-check"></i>

                                                </button>


                                                <!-- Reject -->
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    aria-label="Reject dentist change request"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#changeDentistRejectModal"
                                                    data-reference-number="<?= e(
                                                                                (string) $request['reference_number']
                                                                            ) ?>">

                                                    <i class=" fa-solid fa-xmark"></i>

                                                </button>
                                            </div>
                                        <?php else: ?>

                                            <span class="text-muted">
                                                —
                                            </span>

                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>

            <nav
                class="mt-4"
                aria-label="Dentist change request pagination">

                <ul class="pagination justify-content-center">


                    <!-- Previous -->
                    <li
                        class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                        <a
                            class="page-link"
                            href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>"
                            aria-label="Previous">

                            <i class="fa-solid fa-chevron-left"></i>

                        </a>

                    </li>


                    <!-- Page Numbers -->
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>

                        <li
                            class="page-item <?= $i === $page ? 'active' : '' ?>">

                            <a
                                class="page-link"
                                href="?page=<?= $i ?>&search=<?= urlencode($search) ?>">

                                <?= $i ?>

                            </a>

                        </li>

                    <?php endfor; ?>


                    <!-- Next -->
                    <li
                        class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                        <a
                            class="page-link"
                            href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>"
                            aria-label="Next">

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                    </li>

                </ul>

            </nav>

        <?php endif; ?>


        <!-- Entry Information -->
        <?php if ($totalRequests > 0): ?>

            <div class="text-center text-muted small mb-4 mt-3">

                Showing
                <?= $offset + 1 ?>
                -
                <?= min(
                    $offset + $entriesPerPage,
                    $totalRequests
                ) ?>
                of
                <?= $totalRequests ?>
                request<?= $totalRequests !== 1 ? 's' : '' ?>

            </div>

        <?php endif; ?>


    </div>


    <script>
        document.addEventListener('DOMContentLoaded', () => {

            document.querySelectorAll('.flash-message').forEach((message) => {

                message.addEventListener('animationend', (event) => {

                    if (event.animationName === 'flashMessageOut') {
                        message.remove();
                    }

                });

            });

        });
    </script>


    <script src="../../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>