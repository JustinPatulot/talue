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
    id="addDentistModal"
    data-bs-keyboard="false"
    tabindex="-1"
    aria-labelledby="addDentistModalLabel"
    aria-hidden="true">

    <form action="../../controller/add_dentist.php" method="POST">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="addDentistModalLabel">
                        Add dentist
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
                        name="form_action"
                        value="add_dentist">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>">

                    <label for="dentist_name" class="form-label">
                        Dentist's Name:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="dentist_name"
                        name="dentist_name"
                        required>

                    <label for="username" class="form-label mt-3">
                        Username:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="username"
                        name="username"
                        disabled>

                    <label for="email" class="form-label mt-3">
                        Email:
                    </label>

                    <input
                        type="email"
                        class="form-control"
                        id="email"
                        name="email"
                        disabled>

                    <label for="password" class="form-label mt-3">
                        Password:
                    </label>

                    <input
                        type="password"
                        class="form-control"
                        id="password"
                        name="password"
                        disabled>

                    <label for="role_id" class="form-label mt-3">
                        Role:
                    </label>

                    <select
                        class="form-select"
                        id="role_id"
                        name="role_id"
                        required>

                        <option value="" selected disabled>
                            Select role
                        </option>

                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars((string) $role['id'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($role['role_name'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="modal-footer">
                    <button
                        type="submit"
                        class="btn btn-primary">
                        Add Dentist
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const roleSelect = document.getElementById('role_id');
        const usernameInput = document.getElementById('username');
        const emailInput = document.getElementById('email');
        const passwordInput = document.getElementById('password');

        function updateAccountFields() {
            const selectedRole =
                roleSelect.options[roleSelect.selectedIndex];

            const roleName = selectedRole ?
                selectedRole.textContent.trim().toLowerCase() :
                '';

            const isDentist = roleName === 'dentist';
            const hasRole = !!roleSelect.value;

            const disableAccountFields = isDentist || !hasRole;

            usernameInput.disabled = disableAccountFields;
            emailInput.disabled = disableAccountFields;
            passwordInput.disabled = disableAccountFields;

            usernameInput.required = !disableAccountFields;
            emailInput.required = !disableAccountFields;
            passwordInput.required = !disableAccountFields;

            if (isDentist) {
                usernameInput.value = '';
                emailInput.value = '';
                passwordInput.value = '';
            }
        }

        roleSelect.addEventListener('change', updateAccountFields);

        updateAccountFields();
    });
</script>