<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';

/*
 * ---------------------------------------------------------
 * Fetch roles
 * ---------------------------------------------------------
 */

$roles = [];

$sql = "
    SELECT id, role_name
    FROM roles
    ORDER BY id ASC
";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $roles[] = $row;
    }
}


if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<div class="modal fade"
    id="editDentistModal"
    data-bs-keyboard="false"
    tabindex="-1"
    aria-labelledby="editDentistModalLabel"
    aria-hidden="true">

    <form id="editDentistForm" action="../../controller/edit_dentist.php" method="POST">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="editDentistModalLabel">
                        Edit dentist
                    </h5>

                    <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>
                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        id="edit_dentist_id"
                        name="dentist_id">

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

                    <label for="edit_dentist_name" class="form-label">
                        Dentist's Name:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="edit_dentist_name"
                        name="dentist_name"
                        required>

                    <label for="edit_username" class="form-label mt-3">
                        Username:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="edit_username"
                        name="username">

                    <label for="edit_email" class="form-label mt-3">
                        Email:
                    </label>

                    <input
                        type="email"
                        class="form-control"
                        id="edit_email"
                        name="email">

                    <label for="edit_password" class="form-label mt-3">
                        Password:
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="edit_password"
                        name="password"
                        placeholder="Leave blank to keep current password">

                    <label for="edit_role_id" class="form-label mt-3">
                        Role:
                    </label>

                    <select
                        class="form-select"
                        id="edit_role_id"
                        name="role_id"
                        required>

                        <option value="" selected disabled>
                            Select role
                        </option>

                        <?php foreach ($roles as $role): ?>
                            <option
                                value="<?= htmlspecialchars(
                                            (string) $role['id'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>">
                                <?= htmlspecialchars(
                                    $role['role_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="modal-footer">
                    <button
                        type="submit"
                        class="btn btn-primary">
                        Edit Dentist
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const editButtons =
            document.querySelectorAll('.edit-dentist-btn');

        const editForm =
            document.getElementById('editDentistForm');

        const dentistIdInput =
            editForm.querySelector('#edit_dentist_id');

        const dentistNameInput =
            editForm.querySelector('#edit_dentist_name');

        const usernameInput =
            editForm.querySelector('#edit_username');

        const emailInput =
            editForm.querySelector('#edit_email');

        const passwordInput =
            editForm.querySelector('#edit_password');

        const roleSelect =
            editForm.querySelector('#edit_role_id');


        /*
         * ---------------------------------------------------------
         * Determine whether the selected role is Dentist
         * ---------------------------------------------------------
         */

        function isDentistRole() {

            const selectedOption =
                roleSelect.options[roleSelect.selectedIndex];

            if (!selectedOption) {
                return false;
            }

            return (
                selectedOption.textContent
                .trim()
                .toLowerCase() === 'dentist'
            );
        }


        /*
         * ---------------------------------------------------------
         * Update credential fields based on selected role
         * ---------------------------------------------------------
         *
         * Dentist:
         * - Username disabled
         * - Email disabled
         * - Password disabled
         *
         * Dental Assistant / Administrator:
         * - Username enabled + required
         * - Email enabled + required
         * - Password enabled + optional
         *
         */

        function updateCredentialFields() {

            const isDentist =
                isDentistRole();


            usernameInput.disabled =
                isDentist;

            emailInput.disabled =
                isDentist;

            passwordInput.disabled =
                isDentist;


            usernameInput.required = !isDentist;

            emailInput.required = !isDentist;

            /*
             * Password is intentionally never required.
             *
             * Blank password means:
             * keep the current password.
             */

            passwordInput.required =
                false;
        }


        /*
         * ---------------------------------------------------------
         * Clear credential fields
         * ---------------------------------------------------------
         */

        function clearCredentialFields() {

            usernameInput.value = '';
            emailInput.value = '';
            passwordInput.value = '';
        }


        /*
         * ---------------------------------------------------------
         * Handle role changes
         * ---------------------------------------------------------
         */

        roleSelect.addEventListener(
            'change',
            function() {

                /*
                 * When changing to Dentist,
                 * clear all credential fields.
                 */

                if (isDentistRole()) {
                    clearCredentialFields();
                }

                /*
                 * Apply the appropriate
                 * enabled/disabled and required state.
                 */

                updateCredentialFields();
            }
        );


        /*
         * ---------------------------------------------------------
         * Open Edit Dentist modal
         * ---------------------------------------------------------
         */

        editButtons.forEach(function(button) {

            button.addEventListener(
                'click',
                async function() {

                    const dentistId =
                        this.dataset.dentistId;


                    console.log(
                        'Loading dentist ID:',
                        dentistId
                    );


                    /*
                     * Reset modal before loading data.
                     */

                    dentistIdInput.value = '';
                    dentistNameInput.value = '';

                    clearCredentialFields();

                    roleSelect.value = '';


                    /*
                     * Temporarily disable credential fields
                     * while dentist information is loading.
                     */

                    usernameInput.disabled = true;
                    emailInput.disabled = true;
                    passwordInput.disabled = true;

                    usernameInput.required = false;
                    emailInput.required = false;
                    passwordInput.required = false;


                    try {

                        const formData =
                            new FormData();

                        formData.append(
                            'dentist_id',
                            dentistId
                        );


                        const response =
                            await fetch(
                                '../../controller/get_dentist.php', {
                                    method: 'POST',
                                    body: formData,
                                    credentials: 'same-origin'
                                }
                            );


                        /*
                         * Read the raw response first.
                         *
                         * This makes PHP errors easier to
                         * identify than directly using response.json().
                         */

                        const responseText =
                            await response.text();


                        console.log(
                            'get_dentist.php response:',
                            responseText
                        );


                        /*
                         * -------------------------------------------------
                         * HTTP error
                         * -------------------------------------------------
                         */

                        if (!response.ok) {

                            console.error(
                                'HTTP error:',
                                response.status,
                                responseText
                            );

                            alert(
                                'Server error (' +
                                response.status +
                                '). Check the browser console.'
                            );

                            return;
                        }


                        /*
                         * -------------------------------------------------
                         * Parse JSON
                         * -------------------------------------------------
                         */

                        let data;

                        try {

                            data =
                                JSON.parse(responseText);

                        } catch (jsonError) {

                            console.error(
                                'Invalid JSON returned by get_dentist.php:',
                                jsonError
                            );

                            console.error(
                                'Raw response:',
                                responseText
                            );

                            alert(
                                'The server returned an invalid response. ' +
                                'Check the browser console.'
                            );

                            return;
                        }


                        /*
                         * -------------------------------------------------
                         * Backend error
                         * -------------------------------------------------
                         */

                        if (!data.success) {

                            console.error(
                                'get_dentist.php returned an error:',
                                data
                            );

                            alert(
                                data.message ||
                                'Failed to load dentist.'
                            );

                            return;
                        }


                        /*
                         * -------------------------------------------------
                         * Populate dentist information
                         * -------------------------------------------------
                         */

                        dentistIdInput.value =
                            data.dentist.id ?? '';

                        dentistNameInput.value =
                            data.dentist.dentist_name ?? '';


                        /*
                         * Existing username and email are loaded
                         * for non-Dentist accounts.
                         *
                         * Password is NEVER retrieved.
                         */

                        usernameInput.value =
                            data.dentist.username ?? '';

                        emailInput.value =
                            data.dentist.email ?? '';

                        passwordInput.value =
                            '';


                        /*
                         * Populate existing role.
                         */

                        roleSelect.value =
                            data.dentist.role_id ?? '';


                        /*
                         * If the existing account is already a Dentist,
                         * credentials must remain blank and disabled.
                         *
                         * Otherwise, existing username/email remain
                         * visible, editable, and required.
                         */

                        if (isDentistRole()) {
                            clearCredentialFields();
                        }

                        updateCredentialFields();

                    } catch (error) {

                        console.error(
                            'AJAX error:',
                            error
                        );

                        alert(
                            'An error occurred while loading dentist information. ' +
                            'Check the browser console.'
                        );
                    }
                }
            );
        });

    });
</script>