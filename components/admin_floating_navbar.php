<?php
$current_page = basename(dirname($_SERVER['PHP_SELF']));

$is_dental_assistant = isset($_SESSION['role_id'])
    && (int) $_SESSION['role_id'] === 2;
?>

<nav class="admin-floating-navbar" aria-label="Administrator navigation">

    <!-- Dashboard -->
    <a href="../dashboard/"
        class="admin-floating-item <?= $current_page === 'dashboard' ? 'active' : '' ?>">
        <i class="fa-solid fa-house"></i>
        <span>Dashboard</span>
    </a>


    <!-- Dentists -->
    <?php if (!$is_dental_assistant): ?>
        <a href="../dentists/"
            class="admin-floating-item <?= $current_page === 'dentists' ? 'active' : '' ?>">
            <i class="fa-solid fa-user-doctor"></i>
            <span>Dentists</span>
        </a>
    <?php endif; ?>


    <!-- Bookings -->
    <a href="../bookings/"
        class="admin-floating-item <?= $current_page === 'bookings' ? 'active' : '' ?>">
        <i class="fa-solid fa-calendar-check"></i>
        <span>Bookings</span>
    </a>


    <!-- Branches -->
    <?php if (!$is_dental_assistant): ?>
        <a href="../branches/"
            class="admin-floating-item <?= $current_page === 'branches' ? 'active' : '' ?> admin-mobile-more-item">
            <i class="fa-solid fa-building"></i>
            <span>Branches</span>
        </a>
    <?php endif; ?>


    <!-- Services -->
    <?php if (!$is_dental_assistant): ?>
        <a href="../services/"
            class="admin-floating-item <?= $current_page === 'services' ? 'active' : '' ?> admin-mobile-more-item">
            <i class="fa-solid fa-tooth"></i>
            <span>Services</span>
        </a>
    <?php endif; ?>


    <!-- Dentist Changes -->
    <?php if (!$is_dental_assistant): ?>
        <a href="../dentist-change-requests/"
            class="admin-floating-item <?= $current_page === 'dentist-change-requests' ? 'active' : '' ?> admin-mobile-more-item">
            <i class="fa-solid fa-user-pen"></i>
            <span>Dentist Changes</span>
        </a>
    <?php endif; ?>


    <!-- More -->
    <?php if (!$is_dental_assistant): ?>
        <div class="admin-floating-more-wrapper dropdown">

            <button type="button"
                class="admin-floating-item admin-floating-more dropdown-toggle"
                data-bs-toggle="dropdown"
                aria-expanded="false">

                <i class="fa-solid fa-ellipsis"></i>
                <span>More</span>

            </button>


            <!-- Mobile More Menu -->
            <ul class="dropdown-menu admin-more-menu">

                <li>
                    <a class="dropdown-item" href="../branches/">
                        <i class="fa-solid fa-building me-2"></i>
                        Branches
                    </a>
                </li>

                <li>
                    <a class="dropdown-item" href="../services/">
                        <i class="fa-solid fa-tooth me-2"></i>
                        Services
                    </a>
                </li>

                <li>
                    <a class="dropdown-item" href="../dentist-change-requests/">
                        <i class="fa-solid fa-user-pen me-2"></i>
                        Dentist Changes
                    </a>
                </li>

            </ul>

        </div>
    <?php endif; ?>

</nav>