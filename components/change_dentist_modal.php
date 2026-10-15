<!-- Change Dentist Modal -->
<div
    class="modal fade"
    tabindex="-1"
    id="changeDentistModal"
    aria-labelledby="changeDentistModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            id="changeDentistForm"
            action="controller/submit_dentist_change_request.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="changeDentistModalLabel">

                        Change Dentist

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>

                <div class="modal-body">

                    <p class="text-muted">
                        Select your preferred dentist for this appointment.
                    </p>

                    <!-- CSRF protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>">

                    <!-- Reference Number -->
                    <div class="mb-3">

                        <label
                            for="changeDentistReference"
                            class="form-label">

                            Reference Number

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="changeDentistReference"
                            name="reference_number"
                            readonly>

                    </div>

                    <!-- Current Dentist -->
                    <div class="mb-3">

                        <label
                            class="form-label">

                            Current Dentist

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="currentDentist"
                            readonly>

                    </div>

                    <!-- New Dentist -->
                    <div class="mb-3">

                        <label
                            for="newDentist"
                            class="form-label">

                            Preferred Dentist

                        </label>

                        <select
                            class="form-select"
                            id="newDentist"
                            name="dentist_id"
                            required>

                            <option
                                value=""
                                selected
                                disabled>

                                Select a dentist

                            </option>

                            <?php foreach ($dentists as $dentist): ?>

                                <option value="<?= e($dentist['id']) ?>">
                                    <?= e($dentist['dentist_name']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                        <div class="form-text">
                            Your request will be reviewed by the clinic.
                        </div>

                        <label
                            for="changeDentistReference"
                            class="form-label mt-3">

                            Reason

                        </label>

                        <textarea
                            class="form-control"
                            name="reason"
                            id="reason"
                            rows="4"
                            maxlength="500"
                            placeholder="Enter the reason for changing dentist..."
                            required></textarea>

                    </div>

                    <!-- Error -->
                    <div
                        id="changeDentistError"
                        class="alert alert-danger d-none"
                        role="alert">
                    </div>

                    <!-- Success -->
                    <div
                        id="changeDentistSuccess"
                        class="alert alert-success d-none"
                        role="alert">
                    </div>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Close

                    </button>

                    <button
                        type="submit"
                        class="btn btn-warning"
                        id="submitChangeDentistBtn">

                        <span id="submitChangeDentistText">
                            Submit Request
                        </span>

                        <span
                            id="submitChangeDentistSpinner"
                            class="spinner-border spinner-border-sm d-none"
                            aria-hidden="true">
                        </span>

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>