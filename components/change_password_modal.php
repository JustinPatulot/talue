<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';

/*
 * Generate a CSRF token if one does not already exist.
 */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div
    class="modal fade"
    id="changePasswordModal"
    data-bs-keyboard="false"
    tabindex="-1"
    aria-labelledby="changePasswordModalLabel"
    aria-hidden="true">

    <form
        id="changePasswordForm"
        action="../../ajax/change_password.php"
        method="POST"
        novalidate>

        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="changePasswordModalLabel">
                        Change Password
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
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>">

                    <label
                        for="current_password"
                        class="form-label">
                        Current Password:
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="current_password"
                        name="current_password"
                        minlength="6"
                        autocomplete="current-password"
                        required>

                    <label
                        for="new_password"
                        class="form-label mt-3">
                        New Password:
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="new_password"
                        name="new_password"
                        minlength="6"
                        autocomplete="new-password"
                        required>

                    <div class="form-text">
                        Password must be at least 6 characters.
                    </div>

                    <label
                        for="confirm_password"
                        class="form-label mt-3">
                        Confirm New Password:
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="6"
                        autocomplete="new-password"
                        required>

                    <div
                        id="changePasswordMessage"
                        class="mt-3"
                        role="alert"
                        aria-live="polite">
                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="submit"
                        class="btn btn-secondary">
                        Change Password
                    </button>

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">
                        Cancel
                    </button>

                </div>

            </div>
        </div>

    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const form = document.getElementById('changePasswordForm');
        const message = document.getElementById('changePasswordMessage');

        if (!form) {
            return;
        }

        form.addEventListener('submit', async function(event) {

            event.preventDefault();

            message.textContent = '';
            message.className = 'mt-3';

            const currentPassword =
                document.getElementById('current_password').value;

            const newPassword =
                document.getElementById('new_password').value;

            const confirmPassword =
                document.getElementById('confirm_password').value;

            if (currentPassword.length < 6) {

                message.textContent =
                    'Current password must be at least 6 characters.';

                message.classList.add('text-danger');

                return;
            }

            if (newPassword.length < 6) {

                message.textContent =
                    'New password must be at least 6 characters.';

                message.classList.add('text-danger');

                return;
            }

            if (newPassword !== confirmPassword) {

                message.textContent =
                    'New password and confirmation do not match.';

                message.classList.add('text-danger');

                return;
            }

            if (currentPassword === newPassword) {

                message.textContent =
                    'New password must be different from your current password.';

                message.classList.add('text-danger');

                return;
            }

            const submitButton = form.querySelector(
                'button[type="submit"]'
            );

            const originalText = submitButton.textContent;

            submitButton.disabled = true;
            submitButton.textContent = 'Changing...';

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

                    form.reset();

                } else {

                    message.textContent = data.message;
                    message.classList.add('text-danger');
                }

            } catch (error) {

                console.error(
                    'Change password error:',
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