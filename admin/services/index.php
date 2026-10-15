<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../controller/connection.php';
require_once __DIR__ . '/../../config/session_config.php';
require_once __DIR__ . '/../../helper/flash_helper.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

$search = trim((string) ($_GET['service'] ?? ''));

/*
 * Count total dental services
 */
if ($search !== '') {

    $countSql = "
        SELECT COUNT(*) AS total
        FROM dental_services
        WHERE dental_service LIKE ?
    ";

    $countStmt = $conn->prepare($countSql);

    if (!$countStmt) {
        die('Unable to prepare service count query: ' . $conn->error);
    }

    $searchTerm = '%' . $search . '%';

    $countStmt->bind_param(
        's',
        $searchTerm
    );
} else {

    $countSql = "
        SELECT COUNT(*) AS total
        FROM dental_services
    ";

    $countStmt = $conn->prepare($countSql);

    if (!$countStmt) {
        die('Unable to prepare service count query: ' . $conn->error);
    }
}

if (!$countStmt->execute()) {
    die('Unable to execute service count query: ' . $countStmt->error);
}

$countResult = $countStmt->get_result();

$totalServices = (int) $countResult->fetch_assoc()['total'];

$countStmt->close();

/*
 * Calculate total pages
 */
$totalPages = max(
    1,
    (int) ceil($totalServices / $entriesPerPage)
);

/*
 * Prevent invalid page numbers
 */
$page = min($page, $totalPages);

$offset = ($page - 1) * $entriesPerPage;

/*
 * Get dental services for current page
 */
if ($search !== '') {

    $sql = "
        SELECT id, dental_service
        FROM dental_services
        WHERE dental_service LIKE ?
        ORDER BY dental_service ASC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Unable to prepare service query: ' . $conn->error);
    }

    $stmt->bind_param(
        'sii',
        $searchTerm,
        $entriesPerPage,
        $offset
    );
} else {

    $sql = "
        SELECT id, dental_service
        FROM dental_services
        ORDER BY dental_service ASC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Unable to prepare service query: ' . $conn->error);
    }

    $stmt->bind_param(
        'ii',
        $entriesPerPage,
        $offset
    );
}

if (!$stmt->execute()) {
    die('Unable to execute service query: ' . $stmt->error);
}

$result = $stmt->get_result();

if (!$result) {
    die('Unable to retrieve service results: ' . $stmt->error);
}

$services = [];

while ($row = $result->fetch_assoc()) {
    $services[] = $row;
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

    <title>Services - Talaue 88 - Dental Clinic</title>

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

    <?php include '../../components/add_service_modal.php'; ?>

    <?php include '../../components/delete_service_modal.php'; ?>

    <?php include '../../components/edit_service_modal.php'; ?>

    <h1 class="px-3 mb-4">Services</h1>

    <div class="container-fluid px-3 admin-page-content">

        <!-- Search + Add Service -->
        <div class="d-flex justify-content-center">

            <!-- Search -->
            <form
                method="GET"
                class="input-group admin-search">

                <input
                    type="text"
                    class="form-control"
                    id="service"
                    name="service"
                    placeholder="Search Service"
                    value="<?= e($search) ?>">

                <button
                    class="btn btn-dark"
                    type="submit">

                    <i class="fa-solid fa-magnifying-glass me-1"></i>
                    Search

                </button>

            </form>

            <!-- Add Service -->
            <button
                type="button"
                class="btn btn-dark ms-2"
                title="Add Service"
                data-bs-toggle="modal"
                data-bs-target="#addServiceModal">

                <i class="fa-solid fa-plus"></i>

            </button>

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

        <!-- Service Table -->
        <div class="admin-table-container mt-4">

            <div class="table-responsive">

                <table class="table admin-table mb-0">

                    <thead>

                        <tr>

                            <th scope="col">#</th>

                            <th scope="col">Dental Service</th>

                            <th
                                scope="col"
                                class="text-end">
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($services)): ?>

                            <tr>

                                <td
                                    colspan="3"
                                    class="text-center py-4">

                                    <?php if ($search !== ''): ?>

                                        No services found matching your search.

                                    <?php else: ?>

                                        No services found.

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($services as $number => $service): ?>

                                <tr>

                                    <th scope="row">
                                        <?= $offset + $number + 1 ?>
                                    </th>

                                    <td>
                                        <?= e((string) $service['dental_service']) ?>
                                    </td>

                                    <td class="text-end">

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-dark edit-service-btn"
                                            aria-label="Edit service"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editServiceModal"
                                            data-service-id="<?= (int) $service['id'] ?>">

                                            <i class="fa-solid fa-pen"></i>

                                        </button>

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            aria-label="Delete service"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteServiceModal"
                                            data-service-id="<?= (int) $service['id'] ?>"
                                            data-service-name="<?= e((string) $service['dental_service']) ?>">

                                            <i class="fa-solid fa-trash"></i>

                                        </button>

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
                aria-label="Service pagination">

                <ul class="pagination justify-content-center">

                    <!-- Previous -->
                    <li
                        class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                        <a
                            class="page-link"
                            href="?page=<?= $page - 1 ?>&service=<?= urlencode($search) ?>"
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
                                href="?page=<?= $i ?>&service=<?= urlencode($search) ?>">

                                <?= $i ?>

                            </a>

                        </li>

                    <?php endfor; ?>

                    <!-- Next -->
                    <li
                        class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                        <a
                            class="page-link"
                            href="?page=<?= $page + 1 ?>&service=<?= urlencode($search) ?>"
                            aria-label="Next">

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                    </li>

                </ul>

            </nav>

        <?php endif; ?>

        <!-- Entry Information -->
        <?php if ($totalServices > 0): ?>

            <div class="text-center text-muted small mb-4 mt-3">

                Showing
                <?= $offset + 1 ?>
                -
                <?= min(
                    $offset + $entriesPerPage,
                    $totalServices
                ) ?>
                of
                <?= $totalServices ?>
                service<?= $totalServices !== 1 ? 's' : '' ?>

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

    <script>
        document.querySelectorAll('[data-bs-target="#deleteServiceModal"]').forEach((button) => {

            button.addEventListener('click', () => {

                const serviceId = button.getAttribute('data-service-id');

                document.getElementById('deleteServiceId').value = serviceId;

            });

        });
    </script>

</body>

</html>