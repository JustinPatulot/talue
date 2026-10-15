<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/session_config.php';
require_once __DIR__ . '/../controller/connection.php';

?>

<div class="modal fade"
    id="addServiceModal"
    data-bs-keyboard="false"
    tabindex="-1"
    aria-labelledby="addServiceModalLabel"
    aria-hidden="true">

    <form action="../../controller/add_service.php" method="POST">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="addServiceModalLabel">
                        Add service
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
                        value="add_service">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>">

                    <label
                        for="dental_service"
                        class="form-label">

                        Dental Service:

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="dental_service"
                        name="dental_service"
                        required>

                </div>

                <div class="modal-footer">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Add Service

                    </button>

                </div>

            </div>
        </div>
    </form>
</div>