<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';

?>


<div class="modal fade"
    id="addBranchModal"
    data-bs-keyboard="false"
    tabindex="-1"
    aria-labelledby="addBranchModalLabel"
    aria-hidden="true">

    <form action="../../controller/add_branch.php" method="POST">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="addBranchModalLabel">
                        Add branch
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
                        value="add_branch">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>">

                    <label for="branch_name" class="form-label">
                        Branch Name:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="branch_name"
                        name="branch_name"
                        required>

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