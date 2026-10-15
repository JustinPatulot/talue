<div
    class="modal fade"
    tabindex="-1"
    id="bookingCancelPatientModal"
    aria-labelledby="bookingCancelPatientModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            id="bookingCancelPatientForm"
            action="controller/cancel_booking_patient.php"
            method="POST"
            novalidate>

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="bookingCancelPatientModalLabel">
                        Cancel the Appointment?
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>

                <div class="modal-body">

                    <p>
                        Are you sure you want to cancel this appointment?
                        Please provide a reason for cancellation.
                        The clinic staff will be notified.
                        This action cannot be reversed.
                    </p>

                    <!-- CSRF protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token'] ?? '') ?>">

                    <!-- Booking identifiers -->
                    <input
                        type="hidden"
                        name="booking_id"
                        id="cancelBookingId">

                    <div class="mb-3">

                        <label
                            for="cancelReferenceNumber"
                            class="form-label">
                            Reference Number
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="reference_number"
                            id="cancelReferenceNumber"
                            readonly>

                    </div>

                    <!-- Cancellation reason -->
                    <div class="mb-3">

                        <label
                            for="cancellationReason"
                            class="form-label">
                            Reason for Cancellation
                            <span class="text-danger">*</span>
                        </label>

                        <textarea
                            class="form-control"
                            name="cancellation_reason"
                            id="cancellationReason"
                            rows="4"
                            maxlength="500"
                            placeholder="Enter the reason for cancelling this appointment..."
                            required></textarea>

                        <div class="form-text">
                            Maximum 500 characters.
                        </div>

                    </div>

                    <!-- Error -->
                    <div
                        id="cancelPatientError"
                        class="alert alert-danger d-none"
                        role="alert"
                        aria-live="polite">
                    </div>

                    <!-- Success -->
                    <div
                        id="cancelPatientSuccess"
                        class="alert alert-success d-none"
                        role="alert"
                        aria-live="polite">
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
                        class="btn btn-danger"
                        id="cancelPatientSubmitBtn">

                        <span id="cancelPatientSubmitText">
                            <i class="fa-solid fa-ban me-2" aria-hidden="true"></i>
                            Cancel Appointment
                        </span>

                        <span id="cancelPatientSubmitLoading" class="d-none">
                            <span
                                class="spinner-border spinner-border-sm me-2"
                                aria-hidden="true">
                            </span>
                            Cancelling...
                        </span>

                    </button>


                </div>

            </div>

        </form>

    </div>

</div>