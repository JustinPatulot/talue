<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';
require_once __DIR__ . '/../helper/encryption.php';

/*
 * Generate a CSRF token if one does not already exist.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$dentistId = $_SESSION['dentist_id'] ?? null;

$dentistName = '';
$username = '';
$email = '';

if ($dentistId !== null) {

    $stmt = $conn->prepare("
        SELECT
            dentist_name,
            username,
            email
        FROM dentists
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $dentistId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($dentist = $result->fetch_assoc()) {

        $dentistName = $dentist['dentist_name'] ?? '';
        $username = $dentist['username'] ?? '';

        /*
         * Email is encrypted in the database.
         */
        if (!empty($dentist['email'])) {

            $decryptedEmail = decryptEmail($dentist['email']);

            if ($decryptedEmail !== null) {
                $email = $decryptedEmail;
            }
        }
    }

    $stmt->close();
}
?>

<div class="modal fade"
    id="accountSettingsModal"
    data-bs-keyboard="false"
    tabindex="-1"
    aria-labelledby="editDentistModalLabel"
    aria-hidden="true">

    <form
        id="accountSettingsForm"
        action="../../controller/dentist_account.php"
        method="POST">

        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="editDentistModalLabel">
                        Account Settings
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="form_action"
                        value="edit_dentist">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>">

                    <label
                        for="edit_dentist_name"
                        class="form-label">
                        Dentist's Name:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="edit_dentist_name"
                        name="dentist_name"
                        value="<?= htmlspecialchars(
                                    $dentistName,
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>"
                        required>

                    <label
                        for="edit_username"
                        class="form-label mt-3">
                        Username:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="edit_username"
                        name="username"
                        value="<?= htmlspecialchars(
                                    $username,
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>"
                        required>

                    <label
                        for="edit_email"
                        class="form-label mt-3">
                        Email:
                    </label>

                    <input
                        type="email"
                        class="form-control"
                        id="edit_email"
                        name="email"
                        value="<?= htmlspecialchars(
                                    $email,
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>"
                        required>

                    <div
                        id="accountSettingsMessage"
                        class="mt-3"
                        role="alert"
                        aria-live="polite">
                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="submit"
                        class="btn btn-secondary">
                        Update Account
                    </button>

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-toggle="modal"
                        data-bs-target="#changePasswordModal">
                        Change Password
                    </button>

                </div>

            </div>
        </div>

    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const form = document.getElementById('accountSettingsForm');
        const message = document.getElementById('accountSettingsMessage');

        if (!form) {
            return;
        }

        form.addEventListener('submit', async function(event) {

            event.preventDefault();

            message.textContent = '';
            message.className = 'mt-3';

            const submitButton = form.querySelector(
                'button[type="submit"]'
            );

            const originalText = submitButton.textContent;

            submitButton.disabled = true;
            submitButton.textContent = 'Updating...';

            try {

                const response = await fetch(
                    form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                );

                const data = await response.json();

                if (data.success) {

                    message.textContent = data.message;
                    message.classList.add('text-success');

                } else {

                    message.textContent = data.message;
                    message.classList.add('text-danger');
                }

            } catch (error) {

                console.error(
                    'Account update error:',
                    error
                );

                message.textContent =
                    'Something went wrong. Please try again.';

                message.classList.add('text-danger');

            } finally {

                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        });

    });
</script>