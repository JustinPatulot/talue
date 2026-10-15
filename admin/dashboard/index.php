<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../config/session_config.php';
require_once __DIR__ . '/../../controller/connection.php';


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


function statusBadgeClass(string $status): string
{
    return match ($status) {

        'Pending'
        => 'text-bg-warning',

        'Approved'
        => 'text-bg-success',

        'Completed'
        => 'text-bg-primary',

        'Cancelled'
        => 'text-bg-danger',

        'No Show'
        => 'text-bg-dark',

        'Rescheduled'
        => 'text-bg-info',

        default
        => 'text-bg-secondary',
    };
}


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Total Appointments
|--------------------------------------------------------------------------
*/

$totalAppointments = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM bookings
");

if ($result) {

    $row = $result->fetch_assoc();

    $totalAppointments = (int) (
        $row['total'] ?? 0
    );
}


/*
|--------------------------------------------------------------------------
| Pending Appointments
|--------------------------------------------------------------------------
*/

$pendingAppointments = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM bookings
    WHERE status = 'Pending'
");

if ($result) {

    $row = $result->fetch_assoc();

    $pendingAppointments = (int) (
        $row['total'] ?? 0
    );
}


/*
|--------------------------------------------------------------------------
| Active Dentists
|--------------------------------------------------------------------------
|
| roles:
|
| 1 = Admin
| 2 = Dental Assistant
| 3 = Dentist
|
*/

$dentistCount = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM dentists
    WHERE role_id = 3
");

if ($result) {

    $row = $result->fetch_assoc();

    $dentistCount = (int) (
        $row['total'] ?? 0
    );
}


/*
|--------------------------------------------------------------------------
| Unique Patients
|--------------------------------------------------------------------------
|
| Currently based on unique patient names in bookings.
|
*/

$patientCount = 0;

$result = $conn->query("
    SELECT COUNT(DISTINCT full_name) AS total
    FROM bookings
");

if ($result) {

    $row = $result->fetch_assoc();

    $patientCount = (int) (
        $row['total'] ?? 0
    );
}


/*
|--------------------------------------------------------------------------
| Appointment Status Counts
|--------------------------------------------------------------------------
*/

$statusCounts = [

    'Pending'     => 0,
    'Approved'    => 0,
    'Completed'   => 0,
    'Cancelled'   => 0,
    'No Show'     => 0,
    'Rescheduled' => 0,

];


$result = $conn->query("
    SELECT
        status,
        COUNT(*) AS total
    FROM bookings
    GROUP BY status
");


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $status = $row['status'] ?? '';

        if (isset($statusCounts[$status])) {

            $statusCounts[$status] = (int) (
                $row['total'] ?? 0
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| Status Percentages
|--------------------------------------------------------------------------
*/

$statusTotal = array_sum($statusCounts);

$statusPercentages = [];


foreach ($statusCounts as $status => $count) {

    $statusPercentages[$status] = $statusTotal > 0
        ? round(($count / $statusTotal) * 100)
        : 0;
}


/*
|--------------------------------------------------------------------------
| Recent Appointments
|--------------------------------------------------------------------------
|
| Latest 5 bookings.
|
| bookings.dentist_id -> dentists.id
|
*/

$recentAppointments = [];


$result = $conn->query("
    SELECT
        b.reference_number,
        b.full_name,
        b.booking_date,
        b.status,
        d.dentist_name

    FROM bookings b

    LEFT JOIN dentists d
        ON d.id = b.dentist_id

    ORDER BY b.created_at DESC

    LIMIT 5
");


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $recentAppointments[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Recent Activity
|--------------------------------------------------------------------------
|
| Uses the latest booking records for now.
|
| A dedicated audit/activity table can be added later
| if you want to track individual administrator actions.
|
*/

$recentActivity = [];


$result = $conn->query("
    SELECT
        reference_number,
        full_name,
        status,
        created_at

    FROM bookings

    ORDER BY created_at DESC

    LIMIT 3
");


if ($result) {

    while ($row = $result->fetch_assoc()) {

        $recentActivity[] = $row;
    }
}


/*
|--------------------------------------------------------------------------
| Appointment Status Display
|--------------------------------------------------------------------------
*/

$statusDisplay = [

    'Pending' => [
        'color' => 'warning',
        'icon'  => 'fa-clock',
    ],

    'Approved' => [
        'color' => 'success',
        'icon'  => 'fa-circle-check',
    ],

    'Completed' => [
        'color' => 'primary',
        'icon'  => 'fa-check-double',
    ],

    'Rescheduled' => [
        'color' => 'info',
        'icon'  => 'fa-calendar-days',
    ],

    'Cancelled' => [
        'color' => 'danger',
        'icon'  => 'fa-circle-xmark',
    ],

    'No Show' => [
        'color' => 'dark',
        'icon'  => 'fa-user-xmark',
    ],

];

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Administrator Dashboard - Talaue 88 - Dental Clinic
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


    <!-- Bootstrap CSS -->

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

    <h1 class="px-3 mb-4">
        Administrator Dashboard
    </h1>


    <div class="container-fluid px-3">


        <!-- =========================================================
             Summary Cards
        ========================================================== -->

        <div class="row g-4 mb-4">


            <!-- Total Appointments -->

            <div class="col-12 col-sm-6 col-xl-3">

                <div
                    class="card dashboard-card h-100 shadow-sm border-0">

                    <div class="card-body">

                        <div
                            class="d-flex justify-content-between align-items-start">


                            <div>

                                <p class="text-muted mb-1">
                                    Total Appointments
                                </p>


                                <h2 class="fw-bold mb-0">
                                    <?= $totalAppointments ?>
                                </h2>

                            </div>


                            <div class="dashboard-icon">

                                <i
                                    class="fa-solid fa-calendar-check">
                                </i>

                            </div>


                        </div>


                        <small class="text-muted">
                            All appointment records
                        </small>


                    </div>

                </div>

            </div>


            <!-- Pending Appointments -->

            <div class="col-12 col-sm-6 col-xl-3">

                <div
                    class="card dashboard-card h-100 shadow-sm border-0">

                    <div class="card-body">


                        <div
                            class="d-flex justify-content-between align-items-start">


                            <div>

                                <p class="text-muted mb-1">
                                    Pending Appointments
                                </p>


                                <h2 class="fw-bold mb-0">
                                    <?= $pendingAppointments ?>
                                </h2>

                            </div>


                            <div class="dashboard-icon">

                                <i class="fa-solid fa-clock"></i>

                            </div>


                        </div>


                        <small class="text-warning">
                            Requires attention
                        </small>


                    </div>

                </div>

            </div>


            <!-- Dentists -->

            <div class="col-12 col-sm-6 col-xl-3">

                <div
                    class="card dashboard-card h-100 shadow-sm border-0">

                    <div class="card-body">


                        <div
                            class="d-flex justify-content-between align-items-start">


                            <div>

                                <p class="text-muted mb-1">
                                    Dentists
                                </p>


                                <h2 class="fw-bold mb-0">
                                    <?= $dentistCount ?>
                                </h2>

                            </div>


                            <div class="dashboard-icon">

                                <i
                                    class="fa-solid fa-user-doctor">
                                </i>

                            </div>


                        </div>


                        <small class="text-muted">
                            Active dentists
                        </small>


                    </div>

                </div>

            </div>


            <!-- Patients -->

            <div class="col-12 col-sm-6 col-xl-3">

                <div
                    class="card dashboard-card h-100 shadow-sm border-0">

                    <div class="card-body">


                        <div
                            class="d-flex justify-content-between align-items-start">


                            <div>

                                <p class="text-muted mb-1">
                                    Patients
                                </p>


                                <h2 class="fw-bold mb-0">
                                    <?= $patientCount ?>
                                </h2>

                            </div>


                            <div class="dashboard-icon">

                                <i class="fa-solid fa-users"></i>

                            </div>


                        </div>


                        <small class="text-muted">
                            Unique patients
                        </small>


                    </div>

                </div>

            </div>


        </div>



        <!-- =========================================================
             Main Dashboard Content
        ========================================================== -->

        <div class="row g-4">


            <!-- Recent Appointments -->

            <div class="col-12 col-xl-8">


                <div
                    class="card dashboard-section shadow-sm border-0">


                    <div
                        class="card-header bg-white py-3">


                        <div
                            class="d-flex justify-content-between align-items-center">


                            <div>

                                <h5 class="mb-1 fw-bold">
                                    Recent Appointments
                                </h5>


                                <small class="text-muted">
                                    Latest appointment activity
                                </small>

                            </div>


                            <a
                                href="../bookings/"
                                class="btn btn-sm btn-outline-primary">

                                View All

                            </a>


                        </div>

                    </div>


                    <div class="table-responsive">


                        <table
                            class="table table-hover align-middle mb-0">


                            <thead>

                                <tr>

                                    <th>
                                        Reference
                                    </th>

                                    <th>
                                        Patient
                                    </th>

                                    <th>
                                        Dentist
                                    </th>

                                    <th>
                                        Date
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php if (empty($recentAppointments)): ?>


                                    <tr>

                                        <td
                                            colspan="5"
                                            class="text-center text-muted py-4">

                                            No appointments found.

                                        </td>

                                    </tr>


                                <?php else: ?>


                                    <?php foreach (
                                        $recentAppointments
                                        as $appointment
                                    ): ?>


                                        <tr>


                                            <td>

                                                <strong>

                                                    <?= e(
                                                        $appointment['reference_number']
                                                    ) ?>

                                                </strong>

                                            </td>


                                            <td>

                                                <?= e(
                                                    $appointment['full_name']
                                                ) ?>

                                            </td>


                                            <td>

                                                <?= e(
                                                    $appointment['dentist_name'] ?? 'Unknown'
                                                ) ?>

                                            </td>


                                            <td>

                                                <?= date(
                                                    'M d, Y',
                                                    strtotime(
                                                        $appointment['booking_date']
                                                    )
                                                ) ?>

                                            </td>


                                            <td>

                                                <span
                                                    class="badge <?= statusBadgeClass(
                                                                        $appointment['status']
                                                                    ) ?>">

                                                    <?= e(
                                                        $appointment['status']
                                                    ) ?>

                                                </span>

                                            </td>


                                        </tr>


                                    <?php endforeach; ?>


                                <?php endif; ?>


                            </tbody>


                        </table>

                    </div>

                </div>

            </div>



            <!-- =====================================================
                 Quick Actions
            ====================================================== -->

            <div class="col-12 col-xl-4">


                <div class="card shadow-sm border-0">


                    <div
                        class="card-header bg-white py-3">


                        <h5 class="fw-bold mb-1">
                            Quick Actions
                        </h5>


                        <small class="text-muted">
                            Frequently used administrator functions
                        </small>


                    </div>


                    <div class="card-body">


                        <div class="d-grid gap-2">


                            <a
                                href="../bookings/"
                                class="btn btn-outline-primary text-start">

                                <i
                                    class="fa-solid fa-calendar-check me-2">
                                </i>

                                Manage Appointments

                            </a>


                            <a
                                href="../dentists/"
                                class="btn btn-outline-primary text-start">

                                <i
                                    class="fa-solid fa-user-doctor me-2">
                                </i>

                                Manage Dentists

                            </a>


                            <a
                                href="../services/"
                                class="btn btn-outline-primary text-start">

                                <i
                                    class="fa-solid fa-tooth me-2">
                                </i>

                                Manage Services

                            </a>


                            <a
                                href="../branches/"
                                class="btn btn-outline-primary text-start">

                                <i
                                    class="fa-solid fa-building me-2">
                                </i>

                                Manage Branches

                            </a>


                        </div>

                    </div>

                </div>

            </div>


        </div>



        <!-- =========================================================
             Appointment Status + Recent Activity
        ========================================================== -->

        <div class="row g-4 mt-1">


            <!-- Appointment Status -->

            <div class="col-12 col-lg-6">


                <div class="card shadow-sm border-0">


                    <div
                        class="card-header bg-white py-3">


                        <h5 class="fw-bold mb-1">
                            Appointment Status
                        </h5>


                        <small class="text-muted">
                            Current appointment distribution
                        </small>


                    </div>


                    <div class="card-body">


                        <?php foreach (
                            $statusDisplay
                            as $status => $display
                        ): ?>


                            <div class="mb-3">


                                <div
                                    class="d-flex justify-content-between mb-1">


                                    <span>
                                        <?= e($status) ?>
                                    </span>


                                    <strong>
                                        <?= $statusPercentages[$status] ?>%
                                    </strong>


                                </div>


                                <div class="progress">


                                    <div
                                        class="progress-bar bg-<?= e(
                                                                    $display['color']
                                                                ) ?>"
                                        role="progressbar"
                                        style="width: <?= $statusPercentages[$status] ?>%"
                                        aria-valuenow="<?= $statusPercentages[$status] ?>"
                                        aria-valuemin="0"
                                        aria-valuemax="100">

                                    </div>


                                </div>


                            </div>


                        <?php endforeach; ?>


                        <?php if ($statusTotal === 0): ?>

                            <small class="text-muted">
                                No appointment data available.
                            </small>

                        <?php endif; ?>


                    </div>

                </div>

            </div>



            <!-- Recent Activity -->

            <div class="col-12 col-lg-6">


                <div class="card shadow-sm border-0">


                    <div
                        class="card-header bg-white py-3">


                        <h5 class="fw-bold mb-1">
                            Recent Activity
                        </h5>


                        <small class="text-muted">
                            Latest appointment activity
                        </small>


                    </div>


                    <div class="card-body">


                        <?php if (empty($recentActivity)): ?>


                            <p class="text-muted mb-0">
                                No recent activity.
                            </p>


                        <?php else: ?>


                            <?php foreach (
                                $recentActivity
                                as $activity
                            ): ?>


                                <div
                                    class="d-flex gap-3 mb-3">


                                    <i
                                        class="fa-solid fa-calendar-check text-primary mt-1">
                                    </i>


                                    <div>


                                        <strong>

                                            Appointment
                                            <?= e(
                                                $activity['status']
                                            ) ?>

                                        </strong>


                                        <br>


                                        <small class="text-muted">

                                            <?= e(
                                                $activity['reference_number']
                                            ) ?>

                                            —

                                            <?= e(
                                                $activity['full_name']
                                            ) ?>

                                            <br>

                                            <?= date(
                                                'M d, Y h:i A',
                                                strtotime(
                                                    $activity['created_at']
                                                )
                                            ) ?>

                                        </small>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        <?php endif; ?>


                    </div>

                </div>

            </div>


        </div>


    </div>


    <script
        src="../../node_modules/bootstrap/dist/js/bootstrap.bundle.min.js">
    </script>


</body>

</html>