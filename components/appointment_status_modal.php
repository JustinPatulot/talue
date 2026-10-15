<div
    class="modal fade"
    tabindex="-1"
    id="appointmentStatusModal"
    aria-labelledby="appointmentStatusModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            id="appointmentStatusForm"
            action="ajax/appointment_status.php"
            method="POST"
            novalidate>

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="appointmentStatusModalLabel">

                        Appointment Status

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>

                <div class="modal-body">

                    <!-- Search Section -->
                    <div id="appointmentStatusSearch">

                        <p>
                            To check your appointment status,
                            enter the reference number found in your email.
                        </p>

                        <!-- CSRF protection -->
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_token']) ?>">

                        <!-- Reference Number -->
                        <div class="input-group">

                            <input
                                type="text"
                                class="form-control"
                                name="reference_number"
                                id="referenceNumber"
                                placeholder="e.g. T88-ABC123"
                                maxlength="20"
                                autocomplete="off"
                                required>

                            <button
                                type="submit"
                                class="btn btn-warning"
                                id="checkAppointmentBtn">

                                <span id="checkAppointmentText">
                                    Check
                                </span>

                                <span
                                    id="checkAppointmentSpinner"
                                    class="spinner-border spinner-border-sm d-none"
                                    aria-hidden="true">
                                </span>

                            </button>

                        </div>

                        <!-- Error -->
                        <div
                            id="appointmentStatusError"
                            class="alert alert-danger mt-3 d-none"
                            role="alert">
                        </div>

                    </div>

                    <!-- Result Section -->
                    <div
                        id="appointmentStatusResult"
                        class="d-none">

                        <div class="text-center mb-4">

                            <div
                                id="appointmentStatusIcon"
                                class="mb-2">
                            </div>

                            <h5 class="mb-1">
                                Appointment Status
                            </h5>

                            <div
                                id="appointmentStatusBadge"
                                class="badge">
                            </div>

                        </div>

                        <!-- Appointment Information -->
                        <div class="list-group">

                            <div class="list-group-item">

                                <small class="text-muted">
                                    Reference Number
                                </small>

                                <div
                                    id="resultReference"
                                    class="fw-semibold">
                                </div>

                            </div>

                            <div class="list-group-item">

                                <small class="text-muted">
                                    Patient
                                </small>

                                <div
                                    id="resultName"
                                    class="fw-semibold">
                                </div>

                            </div>

                            <div class="list-group-item">

                                <small class="text-muted">
                                    Service
                                </small>

                                <div
                                    id="resultService"
                                    class="fw-semibold">
                                </div>

                            </div>

                            <div class="list-group-item">

                                <small class="text-muted">
                                    Dentist
                                </small>

                                <div
                                    id="resultDentist"
                                    class="fw-semibold">
                                </div>

                            </div>

                            <div class="list-group-item">

                                <small class="text-muted">
                                    Branch
                                </small>

                                <div
                                    id="resultBranch"
                                    class="fw-semibold">
                                </div>

                            </div>

                            <div class="list-group-item">

                                <small class="text-muted">
                                    Appointment Date
                                </small>

                                <div
                                    id="resultDate"
                                    class="fw-semibold">
                                </div>

                            </div>

                            <div class="list-group-item">

                                <small class="text-muted">
                                    Appointment Time
                                </small>

                                <div
                                    id="resultTime"
                                    class="fw-semibold">
                                </div>

                            </div>

                        </div>

                        <!-- Appointment Actions -->
                        <div
                            id="appointmentActions"
                            class="d-grid gap-2 mt-4 d-none">

                            <button
                                type="button"
                                class="btn btn-outline-warning"
                                id="changeDentistBtn"
                                disabled
                                data-bs-toggle="modal"
                                data-bs-target="#changeDentistModal">

                                Change Dentist

                            </button>

                            <button
                                type="button"
                                class="btn btn-warning"
                                id="rescheduleAppointmentBtn"
                                data-bs-toggle="modal"
                                data-bs-target="#bookingReschedulePatientModal">

                                Reschedule

                            </button>

                            <button
                                type="button"
                                class="btn btn-outline-danger"
                                id="cancelAppointmentBtn"
                                data-bs-toggle="modal"
                                data-bs-target="#bookingCancelPatientModal">

                                Cancel Appointment

                            </button>

                        </div>


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
                        type="button"
                        class="btn btn-warning d-none"
                        id="checkAnotherAppointment">

                        Check Another

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>