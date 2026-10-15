<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../controller/connection.php';
require_once __DIR__ . '/../../config/session_config.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../helper/flash_helper.php';
require_once __DIR__ . '/../../helper/encryption.php';

function e(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

/*
|--------------------------------------------------------------------------
| Pagination settings
|--------------------------------------------------------------------------
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

$search = trim((string) ($_GET['booking'] ?? ''));

/*
|--------------------------------------------------------------------------
| Count total bookings
|--------------------------------------------------------------------------
|
| Searchable fields:
| - Full name
| - Dental service
| - Dentist
| - Branch
| - Status
|
| Email and phone number are intentionally not included because
| they are encrypted and should not be searched using LIKE.
|
*/

if ($search !== '') {

    $countSql = "
        SELECT COUNT(*) AS total
        FROM bookings b

        INNER JOIN dental_services ds
            ON b.dent_service_id = ds.id

        INNER JOIN dentists d
            ON b.dentist_id = d.id

        INNER JOIN branches br
            ON b.branch_id = br.id

        WHERE
            b.full_name LIKE ?
            OR ds.dental_service LIKE ?
            OR d.dentist_name LIKE ?
            OR br.branch_name LIKE ?
            OR b.status LIKE ?
    ";

    $countStmt = $conn->prepare($countSql);

    if (!$countStmt) {
        die('Unable to prepare booking count query: '
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
        FROM bookings
    ";

    $countStmt = $conn->prepare($countSql);

    if (!$countStmt) {
        die('Unable to prepare booking count query: '
            . $conn->error);
    }
}

if (!$countStmt->execute()) {
    die('Unable to execute booking count query: '
        . $countStmt->error);
}

$countResult = $countStmt->get_result();

$totalBookings = (int) $countResult->fetch_assoc()['total'];

$countStmt->close();

/*
|--------------------------------------------------------------------------
| Calculate total pages
|--------------------------------------------------------------------------
*/

$totalPages = max(
    1,
    (int) ceil(
        $totalBookings / $entriesPerPage
    )
);

/*
|--------------------------------------------------------------------------
| Prevent invalid page numbers
|--------------------------------------------------------------------------
*/

$page = min(
    $page,
    $totalPages
);

$offset = ($page - 1) * $entriesPerPage;

/*
|--------------------------------------------------------------------------
| Get bookings for current page
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql = "
        SELECT
            b.id,
            b.full_name,
            b.phone_number,
            b.email,
            b.dent_service_id,
            b.dentist_id,
            b.booking_date,
            b.booking_time,
            b.branch_id,
            b.status,

            ds.dental_service,

            d.dentist_name,

            br.branch_name

        FROM bookings b

        INNER JOIN dental_services ds
            ON b.dent_service_id = ds.id

        INNER JOIN dentists d
            ON b.dentist_id = d.id

        INNER JOIN branches br
            ON b.branch_id = br.id

        WHERE
            b.full_name LIKE ?
            OR ds.dental_service LIKE ?
            OR d.dentist_name LIKE ?
            OR br.branch_name LIKE ?
            OR b.status LIKE ?

        ORDER BY
            b.booking_date DESC,
            b.booking_time DESC

        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Unable to prepare booking query: '
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
            b.id,
            b.full_name,
            b.phone_number,
            b.email,
            b.dent_service_id,
            b.dentist_id,
            b.booking_date,
            b.booking_time,
            b.branch_id,
            b.status,

            ds.dental_service,

            d.dentist_name,

            br.branch_name

        FROM bookings b

        INNER JOIN dental_services ds
            ON b.dent_service_id = ds.id

        INNER JOIN dentists d
            ON b.dentist_id = d.id

        INNER JOIN branches br
            ON b.branch_id = br.id

        ORDER BY
            b.booking_date DESC,
            b.booking_time DESC

        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die('Unable to prepare booking query: '
            . $conn->error);
    }

    $stmt->bind_param(
        'ii',
        $entriesPerPage,
        $offset
    );
}

if (!$stmt->execute()) {
    die('Unable to execute booking query: '
        . $stmt->error);
}

$result = $stmt->get_result();

if (!$result) {
    die('Unable to retrieve booking results: '
        . $stmt->error);
}

$bookings = [];

while ($row = $result->fetch_assoc()) {
    /*
    |--------------------------------------------------------------------------
    | Decrypt sensitive booking information
    |--------------------------------------------------------------------------
    */

    $row['phone_number'] = decryptData(
        (string) $row['phone_number']
    ) ?? '';

    $row['email'] = decryptEmail(
        (string) $row['email']
    ) ?? '';

    $bookings[] = $row;
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

    <title>Bookings - Talaue 88 - Dental Clinic</title>

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

    <!-- Modals -->
    <?php include '../../components/booking_approve_modal.php'; ?>

    <?php include '../../components/booking_delete_modal.php'; ?>

    <?php include '../../components/booking_edit_modal.php'; ?>

    <?php include '../../components/booking_reschedule_modal.php'; ?>

    <?php include '../../components/booking_cancel_modal.php'; ?>

    <h1 class="px-3 mb-4">Bookings</h1>

    <div class="container-fluid px-3 admin-page-content">

        <!-- Search -->
        <div class="d-flex justify-content-center">

            <form
                method="GET"
                class="input-group admin-search">

                <input
                    type="text"
                    class="form-control"
                    id="booking"
                    name="booking"
                    placeholder="Search Booking"
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

        <!-- Booking Table -->
        <div class="admin-table-container mt-4">

            <div class="table-responsive">

                <table class="table admin-table mb-0">

                    <thead>

                        <tr>

                            <th scope="col">#</th>

                            <th scope="col">Full Name</th>

                            <th scope="col">Phone Number</th>

                            <th scope="col">Email</th>

                            <th scope="col">Dental Service</th>

                            <th scope="col">Dentist</th>

                            <th scope="col">Date</th>

                            <th scope="col">Time</th>

                            <th scope="col">Branch</th>

                            <th scope="col">Status</th>

                            <th
                                scope="col"
                                class="text-end">

                                Actions

                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($bookings)): ?>

                            <tr>

                                <td
                                    colspan="11"
                                    class="text-center py-4">

                                    <?php if ($search !== ''): ?>

                                        No bookings found matching your search.

                                    <?php else: ?>

                                        No bookings found.

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($bookings as $number => $booking): ?>

                                <?php

                                $status = trim(
                                    (string) $booking['status']
                                );

                                $statusClass = match ($status) {

                                    'Pending' =>
                                    'text-bg-warning',

                                    'Approved' =>
                                    'text-bg-success',

                                    'Completed' =>
                                    'text-bg-primary',

                                    'Cancelled' =>
                                    'text-bg-danger',

                                    'No Show' =>
                                    'text-bg-secondary',

                                    'Rescheduled' =>
                                    'text-bg-info',

                                    default =>
                                    'text-bg-secondary'
                                };

                                ?>

                                <tr>

                                    <th scope="row">
                                        <?= $offset + $number + 1 ?>
                                    </th>

                                    <td>
                                        <?= e(
                                            (string) $booking['full_name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) $booking['phone_number']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) $booking['email']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) $booking['dental_service']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) $booking['dentist_name']
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            date('M. j, Y', strtotime($booking['booking_date']))
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            date('h:i A', strtotime($booking['booking_time']))
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            (string) $booking['branch_name']
                                        ) ?>
                                    </td>

                                    <td>

                                        <span
                                            class="badge <?= e($statusClass) ?>">

                                            <?= e($status) ?>

                                        </span>

                                    </td>

                                    <td class="text-center">
                                        <div class="dropdown">
                                            <button
                                                type="button"
                                                class="btn btn-light btn-sm"
                                                data-bs-toggle="dropdown"
                                                data-bs-popper-config='{"strategy":"fixed"}'
                                                aria-expanded="false"
                                                aria-label="Booking actions">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>

                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php if (!in_array($booking['status'], ['Approved', 'Completed', 'No Show', 'Cancelled', 'Rescheduled'], true)): ?>
                                                    <li>
                                                        <button
                                                            class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#bookingApproveModal"
                                                            data-booking-id="<?= (int) $booking['id'] ?>">
                                                            <i class="fa-solid fa-check me-2"></i>
                                                            Approve
                                                        </button>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if (!in_array($booking['status'], ['Approved', 'Completed', 'No Show', 'Cancelled', 'Rescheduled'], true)): ?>
                                                    <li>
                                                        <button
                                                            class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#bookingCancelModal"
                                                            data-booking-id="<?= (int) $booking['id'] ?>">
                                                            <i class="fa-solid fa-xmark me-2"></i>
                                                            Cancel
                                                        </button>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if (in_array($booking['status'], ['Approved', 'Rescheduled'], true)): ?>
                                                    <li>
                                                        <button
                                                            class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#bookingEditModal"
                                                            data-booking-id="<?= (int) $booking['id'] ?>">
                                                            <i class="fa-solid fa-pen me-2"></i>
                                                            Edit Status
                                                        </button>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if (!in_array($booking['status'], ['Completed', 'No Show', 'Cancelled', 'Rescheduled'], true)): ?>
                                                    <li>
                                                        <button
                                                            type="button"
                                                            class="dropdown-item"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#bookingRescheduleModal"
                                                            data-booking-id="<?= (int) $booking['id'] ?>">

                                                            <i class="fa-solid fa-calendar-days me-2"></i>
                                                            Reschedule
                                                        </button>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if (in_array($booking['status'], ['Completed', 'Cancelled', 'No Show'], true)): ?>
                                                    <li>
                                                        <button
                                                            type="button"
                                                            class="dropdown-item text-danger"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#bookingDeleteModal"
                                                            data-booking-id="<?= (int) $booking['id'] ?>">
                                                            <i class="fa-solid fa-trash me-2"></i>
                                                            Delete
                                                        </button>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
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
                aria-label="Booking pagination">

                <ul class="pagination justify-content-center">

                    <!-- Previous -->
                    <li
                        class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">

                        <a
                            class="page-link"
                            href="?page=<?= $page - 1 ?>&booking=<?= urlencode($search) ?>"
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
                                href="?page=<?= $i ?>&booking=<?= urlencode($search) ?>">

                                <?= $i ?>

                            </a>

                        </li>

                    <?php endfor; ?>

                    <!-- Next -->
                    <li
                        class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">

                        <a
                            class="page-link"
                            href="?page=<?= $page + 1 ?>&booking=<?= urlencode($search) ?>"
                            aria-label="Next">

                            <i class="fa-solid fa-chevron-right"></i>

                        </a>

                    </li>

                </ul>

            </nav>

        <?php endif; ?>

        <!-- Entry Information -->
        <?php if ($totalBookings > 0): ?>

            <div class="text-center text-muted small mb-4 mt-3">

                Showing
                <?= $offset + 1 ?>
                -
                <?= min(
                    $offset + $entriesPerPage,
                    $totalBookings
                ) ?>
                of
                <?= $totalBookings ?>
                booking<?= $totalBookings !== 1 ? 's' : '' ?>

            </div>

        <?php endif; ?>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            document
                .querySelectorAll('.flash-message')
                .forEach((message) => {

                    message.addEventListener(
                        'animationend',
                        (event) => {

                            if (
                                event.animationName ===
                                'flashMessageOut'
                            ) {
                                message.remove();
                            }

                        }
                    );

                });

        });
    </script>

    <script src="../../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>