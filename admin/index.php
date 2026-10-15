<?php

declare(strict_types=1);

require_once '../config/session_config.php';

// Retrieve flash messages from the session.
$signinError = $_SESSION['signin_error'] ?? '';
$signinUsername = $_SESSION['signin_username'] ?? '';

// Remove them after reading so they only appear once.
unset(
    $_SESSION['signin_error'],
    $_SESSION['signin_username']
);

require_once __DIR__ . '/../config/guest_middleware.php';

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Sign in - Talaue 88 - Dental Clinic</title>
    <link rel="icon" type="image/png" href="../assets/TALAUE88.png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.0/css/all.min.css"
        integrity="sha512-ApSLB1Pd3/bZN8fWB/RG9YhN/7bd9Hkf3AGaE2mPfebjrxagjuBtx2GcgdqIlJkUzwylBo61r9Xa9NmgBI0swA=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/decorations.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@6..144,1..1000&display=swap"
        rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="../node_modules/bootstrap/dist/css/bootstrap.min.css">
</head>

<style>
    .admin-signin-card {
        width: 100%;
        max-width: 420px;
        background-color: #f2f2f2;
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }
</style>

<body>

    <div class="tooth-rain" aria-hidden="true">
        <!-- Row 1 -->
        <span class="tooth" style="left: 2%; animation-duration: 8s; animation-delay: 0s;">🦷</span>
        <span class="tooth" style="left: 7%; animation-duration: 11s; animation-delay: 2s;">🦷</span>
        <span class="tooth" style="left: 12%; animation-duration: 9s; animation-delay: 4s;">🦷</span>
        <span class="tooth" style="left: 17%; animation-duration: 13s; animation-delay: 1s;">🦷</span>
        <span class="tooth" style="left: 22%; animation-duration: 10s; animation-delay: 5s;">🦷</span>

        <!-- Row 2 -->
        <span class="tooth" style="left: 27%; animation-duration: 12s; animation-delay: 3s;">🦷</span>
        <span class="tooth" style="left: 32%; animation-duration: 9s; animation-delay: 6s;">🦷</span>
        <span class="tooth" style="left: 37%; animation-duration: 14s; animation-delay: 2s;">🦷</span>
        <span class="tooth" style="left: 42%; animation-duration: 10s; animation-delay: 7s;">🦷</span>
        <span class="tooth" style="left: 47%; animation-duration: 12s; animation-delay: 4s;">🦷</span>

        <!-- Row 3 -->
        <span class="tooth" style="left: 52%; animation-duration: 8s; animation-delay: 1s;">🦷</span>
        <span class="tooth" style="left: 57%; animation-duration: 13s; animation-delay: 5s;">🦷</span>
        <span class="tooth" style="left: 62%; animation-duration: 9s; animation-delay: 3s;">🦷</span>
        <span class="tooth" style="left: 67%; animation-duration: 11s; animation-delay: 8s;">🦷</span>
        <span class="tooth" style="left: 72%; animation-duration: 10s; animation-delay: 6s;">🦷</span>

        <!-- Row 4 -->
        <span class="tooth" style="left: 77%; animation-duration: 14s; animation-delay: 2s;">🦷</span>
        <span class="tooth" style="left: 82%; animation-duration: 9s; animation-delay: 5s;">🦷</span>
        <span class="tooth" style="left: 87%; animation-duration: 12s; animation-delay: 1s;">🦷</span>
        <span class="tooth" style="left: 92%; animation-duration: 8s; animation-delay: 7s;">🦷</span>
        <span class="tooth" style="left: 97%; animation-duration: 11s; animation-delay: 4s;">🦷</span>
    </div>

    <?php include '../components/admin_navbar.php' ?>

    <main class="d-flex justify-content-center align-items-center"
        style="min-height: calc(100vh - 70px);">
        <div class="card admin-signin-card">
            <div class="card-body p-4">

                <img
                    src="../assets/TALAUE88.png"
                    alt="Talaue 88 Dental Clinic"
                    class="d-block mx-auto mb-4"
                    style="width: 100px; height: auto;">

                <h2 class="text-center mb-3">Admin Sign In</h2>

                <form action="../controller/signin.php" method="POST">

                    <div class="mb-3">
                        <label for="username" class="form-label">
                            Username
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            placeholder="user123"
                            value="<?= htmlspecialchars($signinUsername, ENT_QUOTES, 'UTF-8') ?>"
                            autocomplete="username"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">
                            Password
                        </label>

                        <input
                            type="password"
                            class="form-control"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required>
                    </div>

                    <?php if ($signinError !== ''): ?>
                        <div
                            class="alert alert-danger"
                            role="alert"
                            aria-live="polite">
                            <?= htmlspecialchars($signinError, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-warning w-100">
                        Sign In
                    </button>

                </form>

            </div>
        </div>
    </main>
</body>

</html>