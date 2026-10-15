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

$search = trim((string) ($_GET['dentist'] ?? ''));

/*
 * Count total dentists
 */
if ($search !== '') {

    $countSql = "
        SELECT COUNT(*) AS total
        FROM dentists
        WHERE dentist_name LIKE ?
           OR username LIKE ?
    ";

    $countStmt = $conn->prepare($countSql);

    if (!$countStmt) {
        die('Unable to prepare dentist count query: ' . $conn->error);
    }

    $searchTerm = '%' . $search . '%';

    $countStmt->bind_param(
        'ss',
        $searchTerm,
        $searchTerm
    );
} else {

    $countSql = "
        SELECT COUNT(*) AS total
        FROM dentists
    ";

    $countStmt = $conn->prepare($countSql);

    if (!$countStmt) {
        die('Unable to prepare dentist count query: ' . $conn->error);
    }
}

if (!$countStmt->execute()) {
    die('Unable to execute dentist count query: ' . $countStmt->error);
}

$countResult = $countStmt->get_result();

$totalDentists = (int) $countResult->fetch_assoc()['total'];

$countStmt->close();

/*
 * Calculate total pages
 */
$totalPages = max(
    1,
    (int) ceil($totalDentists / $entriesPerPage)
);

/*
 * Prevent invalid page numbers
 */
$page = min($page, $totalPages);

$offset = ($page - 1) * $entriesPerPage;

/*
 * Get dentists for current page
 */
if ($search !== '') {

    $sql = "
        SELECT id, dentist_name, username
        FROM dentists
        WHERE dentist_name LIKE ?
           OR username LIKE ?
        ORDER BY dentist_name ASC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Unable to prepare dentist query: ' . $conn->error);
    }

    $stmt->bind_param(
        'ssii',
        $searchTerm,
        $searchTerm,
        $entriesPerPage,
        $offset
    );
} else {

    $sql = "
        SELECT id, dentist_name, username
        FROM dentists
        ORDER BY dentist_name ASC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Unable to prepare dentist query: ' . $conn->error);
    }

    $stmt->bind_param(
        'ii',
        $entriesPerPage,
        $offset
    );
}

if (!$stmt->execute()) {
    die('Unable to execute dentist query: ' . $stmt->error);
}

$result = $stmt->get_result();

if (!$result) {
    die('Unable to retrieve dentist results: ' . $stmt->error);
}

$dentists = [];

while ($row = $result->fetch_assoc()) {
    $dentists[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dentists - Talaue 88 - Dental Clinic</title>

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

    <?php include '../../components/add_dentist_modal.php'; ?>

    <?php include '../../components/delete_dentist_modal.php'; ?>

    <?php include '../../components/edit_dentist_modal.php'; ?>

    <h1 class="px-3 mb-4">Dentists</h1>

    <div class="container-fluid px-3 admin-page-content">

        <!-- Search + Add Dentist -->
        <div class="d-flex justify-content-center">

            <!-- Search -->
            <form
                method="GET"
                class="input-group admin-search">

                <input
                    type="text"
                    class="form-control"
                    id="dentist"
                    name="dentist"
                    placeholder="Search Dentist"
                    value="<?= e($search) ?>">

                <button
                    class="btn btn-dark"
                    type="submit">

                    <i class="fa-solid fa-magnifying-glass me-1"></i>
                    Search

                </button>

            </form>

            <!-- Add Dentist -->
            <button
                type="button"
                class="btn btn-dark ms-2"
                title="Add Dentist"
                data-bs-toggle="modal"
                data-bs-target="#addDentistModal">

                <i class="fa-solid fa-plus"></i>

            </button>

        </div>

        <!-- Flash Messages - Remodeled -->
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

        <!-- Dentist Table -->
        <div class="admin-table-container mt-4">

            <div class="table-responsive">

                <table class="table admin-table mb-0">

                    <thead>

                        <tr>

                            <th scope="col">#</th>

                            <th scope="col">Name</th>

                            <th scope="col">Username</th>

                            <th
                                scope="col"
                                class="text-end">
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($dentists)): ?>

                            <tr>

                                <td
                                    colspan="4"
                                    class="text-center py-4">

                                    <?php if ($search !== ''): ?>

                                        No dentists found matching your search.

                                    <?php else: ?>

                                        No dentists found.

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($dentists as $number => $dentist): ?>

                                <tr>

                                    <th scope="row">
                                        <?= $offset + $number + 1 ?>
                                    </th>

                                    <td>
                                        <?= e((string) $dentist['dentist_name']) ?>
                                    </td>

                                    <td>
                                        <?= e((string) $dentist['username']) ?>
                                    </td>

                                    <td class="text-end admin-actions-cell">

                                        <?php $currentDentistId = (int) ($_SESSION['dentist_id'] ?? 0);
                                        $isCurrentDentist = $currentDentistId === (int) $dentist['id']; ?>

                                        <?php if (!$isCurrentDentist): ?>

                                            <div class="admin-actions">

                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-dark edit-dentist-btn"
                                                    aria-label="Edit dentist"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editDentistModal"
                                                    data-dentist-id="<?= (int) $dentist['id'] ?>">

                                                    <i class="fa-solid fa-pen"></i>

                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-outline-danger"
                                                    aria-label="Delete dentist"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#deleteDentistModal"
                                                    data-dentist-id="<?= (int) $dentist['id'] ?>"
                                                    data-dentist-name="<?= e((string) $dentist['dentist_name']) ?>">

                                                    <i class="fa-solid fa-trash"></i>

                                                </button>

                                            </div>

                                        <?php else: ?>

                                            <span class="text-muted small">
                                                Current account
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
                aria-label="Dentist pagination">

                <ul class="pagination justify-content-center">

                    <!-- Previous -->
                    <li
                        class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                        <a
                            class="page-link"
                            href="?page=<?= $page - 1 ?>&dentist=<?= urlencode($search) ?>"
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
                                href="?page=<?= $i ?>&dentist=<?= urlencode($search) ?>">

                                <?= $i ?>

                            </a>

                        </li>

                    <?php endfor; ?>

                    <!-- Next -->
                    <li
                        class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                        <a
                            class="page-link"
                            href="?page=<?= $page + 1 ?>&dentist=<?= urlencode($search) ?>"
                            aria-label="Next">

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                    </li>

                </ul>

            </nav>

        <?php endif; ?>

        <!-- Entry Information -->
        <?php if ($totalDentists > 0): ?>

            <div class="text-center text-muted small mb-4 mt-3">

                Showing
                <?= $offset + 1 ?>
                -
                <?= min(
                    $offset + $entriesPerPage,
                    $totalDentists
                ) ?>
                of
                <?= $totalDentists ?>
                dentist<?= $totalDentists !== 1 ? 's' : '' ?>

            </div>

        <?php endif; ?>

    </div>

    <script src="../../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

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

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            const deleteDentistModal =
                document.getElementById('deleteDentistModal');

            const deleteDentistId =
                document.getElementById('deleteDentistId');

            deleteDentistModal.addEventListener(
                'show.bs.modal',
                (event) => {

                    const button = event.relatedTarget;

                    const dentistId =
                        button.getAttribute('data-dentist-id');

                    deleteDentistId.value = dentistId;
                }
            );

        });
    </script>

</body>

</html>