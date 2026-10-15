<?php

declare(strict_types=1);

require_once __DIR__ . '/config/session_config.php';
require_once __DIR__ . '/controller/connection.php';
require_once __DIR__ . '/helper/flash_helper.php';

function e(string $value): string
{
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}


if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

/*
|--------------------------------------------------------------------------
| Fetch dentists for appointment booking
|--------------------------------------------------------------------------
| Only retrieve information that is safe and necessary for the
| public appointment form.
|--------------------------------------------------------------------------
*/

$dentists = [];

$sql = "
    SELECT id, dentist_name
    FROM dentists
    ORDER BY dentist_name ASC
";

$result = mysqli_query($conn, $sql);

if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $dentists[] = $row;
  }

  mysqli_free_result($result);
}

/*
|--------------------------------------------------------------------------
| Fetch branches for appointment booking
|--------------------------------------------------------------------------
| Only retrieve information that is safe and necessary for the
| public appointment form.
|--------------------------------------------------------------------------
*/

$branches = [];

$sql = "
    SELECT id, branch_name
    FROM branches
    ORDER BY branch_name ASC
";

$result = mysqli_query($conn, $sql);

if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $branches[] = $row;
  }

  mysqli_free_result($result);
}

// SERVICES
$services = [];

$sql = "
    SELECT id, dental_service
    FROM dental_services
    ORDER BY dental_service ASC
";

$result = mysqli_query($conn, $sql);

if ($result) {
  while ($row = mysqli_fetch_assoc($result)) {
    $services[] = $row;
  }

  mysqli_free_result($result);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Talaue 88 - Dental Clinic</title>
  <link rel="icon" type="image/png" href="assets/TALAUE88.png">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.0/css/all.min.css"
    integrity="sha512-ApSLB1Pd3/bZN8fWB/RG9YhN/7bd9Hkf3AGaE2mPfebjrxagjuBtx2GcgdqIlJkUzwylBo61r9Xa9NmgBI0swA=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />

  <link rel="stylesheet" href="styles/appointment.css">
  <link rel="stylesheet" href="styles/navbar.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Google+Sans+Flex:opsz,wght@6..144,1..1000&display=swap"
    rel="stylesheet">

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="./node_modules/bootstrap/dist/css/bootstrap.min.css">
</head>

<body>

  <!-- Modal -->
  <?php include 'components/appointment_status_modal.php' ?>

  <?php include 'components/change_dentist_modal.php' ?>

  <?php include 'components/booking_reschedule_patient_modal.php' ?>

  <?php include 'components/booking_cancel_patient_modal.php' ?>

  <!-- Navbar -->
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm fixed-top">
    <div class="container">
      <img src="assets/TALAUE88.png" alt="Talaue 88 Dental Clinic" class="navbar-logo">
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar"
        aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mainNavbar">
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <li class="nav-item"><a class="nav-link" href="index.html#home">HOME</a></li>
          <li class="nav-item"><a class="nav-link" href="index.html#about">ABOUT</a></li>
          <li class="nav-item"><a class="nav-link" href="index.html#services">SERVICES</a></li>
          <li class="nav-item"><a class="nav-link" href="index.html#contact">CONTACT</a></li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Appointment Header -->
  <div class="appointment-header">
    <h1>Book an Appointment</h1>
    <h5>
      Need a consultation with a dentist? You can schedule an appointment!<br><br>
      Please fill in the required fields, pick a date and time, your dentist and a branch. <br>

      Have an appointment already? Check your appointment status here.
    </h5>

    <button
      type="button"
      class="btn btn-warning btn-sm"
      data-bs-toggle="modal"
      data-bs-target="#appointmentStatusModal">

      Check Appointment Status

    </button>
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
  </div>

  <!-- Appointment Form -->
  <div class="wrapper">
    <form action="controller/enter_booking.php" method="POST">

      <input
        type="hidden"
        name="csrf_token"
        value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
      <!-- Kaliwang column -->
      <div>
        <label for=" fullname">Full name:*</label>
        <input type="text" id="full_name" name="full_name" placeholder="Juan Dela Cruz " required>

        <label class="mt-3" for="Number">Phone number*</label>
        <input type="number" id="phone_number" name="phone_number" placeholder="09991234567" required>

        <label class="mt-3" for="email">Email*</label>
        <input type="email" id="email" name="email" placeholder="juan@example.com" required>


        <label class="mt-3" for="Dental Service">Dental Service*</label>
        <select id="Dental Service" name="dent_service_id" required>
          <option value="" selected disabled>-Select Service-</option>

          <?php foreach ($services as $service): ?>
            <option value="<?= htmlspecialchars((string) $service['id'], ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($service['dental_service'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>

        </select>
        <p class="email-note">
          Your e‑mail will be used for appointment confirmation and important updates to your schedule.
        </p>

      </div>

      <!-- Kanang column -->
      <div>
        <label for="Dentist">Dentist*</label>
        <select id="Dentist" name="dentist_id" required>
          <option value="" selected disabled>-Select Dentist-</option>

          <?php foreach ($dentists as $dentist): ?>
            <option value="<?= htmlspecialchars((string) $dentist['id'], ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($dentist['dentist_name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>

        </select>


        <!-- Date & Time side by side -->
        <div class="date-time-group">
          <div>
            <label class="mt-3" for="booking-date">Date *</label>
            <input type="date" id="booking-date" name="booking-date">
          </div>
          <div>
            <label class="mt-3" for="booking-time">Time*:</label>
            <input type="time" id="booking-time" name="booking-time">
          </div>
        </div>

        <label class="mt-3" for="Branch">Branch*</label>
        <select id="Branch" name="branch_id" required>
          <option value="" selected disabled>-Select Branch-</option>

          <?php foreach ($branches as $branch): ?>
            <option value="<?= htmlspecialchars((string) $branch['id'], ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($branch['branch_name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>

        </select>
        <div class="privacy-consent mt-4">
          <input type="checkbox" id="privacy_consent" name="privacy_consent" required>
          <label for="privacy">
            I agree to the collection and processing of my personal information for appointment scheduling and
            notifications in accordance with the Data Privacy Act of 2012.
          </label>
        </div>

        <br><br>
        <input type="submit" id="confirmBtn" value="CONFIRM">
      </div>
    </form>
  </div>

  <!-- Footer -->
  <footer class="footer">
    <div class="container">
      <p>&copy; 2026 Talaue 88 Dental Clinic</p>
    </div>
    <a href="https://www.facebook.com/profile.php?id=61581983441620" target="_blank" rel="noopener noreferrer"
      class="footer-link">
      <i class="fa-brands fa-facebook"></i> Follow us on Facebook
    </a>
  </footer>

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

  <script src="js/appointment-workflow.js"></script>

  <!-- Bootstrap JS Bundle (Includes Popper for dropdowns) -->
  <script src="./node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>