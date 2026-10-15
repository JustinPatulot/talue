<div
    class="modal fade"
    tabindex="-1"
    id="bookingReschedulePatientModal"
    aria-labelledby="bookingReschedulePatientModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            id="bookingReschedulePatientForm"
            action="controller/reschedule_booking_patient.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="bookingReschedulePatientModalLabel">

                        Reschedule the Appointment?

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
                        Are you sure you want to reschedule this appointment?
                        Please select a new date and time and provide a reason
                        for the reschedule.
                    </p>

                    <p class="text-muted">
                        If an approved appointment is rescheduled, it will be
                        returned to <strong>Pending</strong> for clinic approval.
                    </p>

                    <!-- CSRF protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>">

                    <!--
                        Existing booking ID.
                        Used internally by the backend.
                    -->
                    <input
                        type="hidden"
                        name="booking_id"
                        id="rescheduleBookingId">

                    <!--
                        Existing reference number.
                        This MUST remain unchanged after rescheduling.
                    -->
                    <div class="mb-3">

                        <label
                            for="rescheduleReference"
                            class="form-label">

                            Reference Number

                        </label>

                        <input
                            type="text"
                            class="form-control"
                            name="reference_number"
                            id="rescheduleReference"
                            readonly>

                        <div class="form-text">
                            Your existing reference number will remain the same.
                        </div>

                    </div>

                    <!-- New appointment date -->
                    <div class="mb-3">

                        <label
                            for="rescheduleDate"
                            class="form-label">

                            New Appointment Date
                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="new_booking_date"
                            id="rescheduleDate"
                            required>

                        <div class="form-text">
                            Select the new date for your appointment.
                        </div>

                    </div>

                    <!-- New appointment time -->
                    <div class="mb-3">

                        <label
                            for="rescheduleTime"
                            class="form-label">

                            New Appointment Time
                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="time"
                            class="form-control"
                            name="new_booking_time"
                            id="rescheduleTime"
                            required>

                        <div class="form-text">
                            Select the new time for your appointment.
                        </div>

                    </div>

                    <!-- Reschedule reason -->
                    <div class="mb-3">

                        <label
                            for="rescheduleReason"
                            class="form-label">

                            Reason for Rescheduling
                            <span class="text-danger">*</span>

                        </label>

                        <textarea
                            class="form-control"
                            name="reschedule_reason"
                            id="rescheduleReason"
                            rows="4"
                            maxlength="500"
                            placeholder="Enter the reason for rescheduling this appointment..."
                            required></textarea>

                        <div class="form-text">
                            Please provide a clear reason that can be shared
                            with the clinic staff.
                        </div>

                    </div>

                    <!-- Error -->
                    <div
                        id="rescheduleError"
                        class="alert alert-danger d-none"
                        role="alert">
                    </div>

                    <!-- Success -->
                    <div
                        id="rescheduleSuccess"
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
                        id="submitRescheduleBtn">

                        <span id="submitRescheduleText">
                            Reschedule Appointment
                        </span>

                        <span
                            id="submitRescheduleSpinner"
                            class="spinner-border spinner-border-sm d-none"
                            aria-hidden="true">
                        </span>

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const rescheduleModal =
            document.getElementById('bookingReschedulePatientModal');

        if (!rescheduleModal) {
            console.error('Patient reschedule modal not found.');
            return;
        }

        const form =
            document.getElementById('bookingReschedulePatientForm');

        const bookingIdInput =
            document.getElementById('rescheduleBookingId');

        const referenceInput =
            document.getElementById('rescheduleReference');

        const rescheduleDate =
            document.getElementById('rescheduleDate');

        const rescheduleTime =
            document.getElementById('rescheduleTime');

        const rescheduleReason =
            document.getElementById('rescheduleReason');

        const errorBox =
            document.getElementById('rescheduleError');

        const successBox =
            document.getElementById('rescheduleSuccess');

        const submitButton =
            document.getElementById('submitRescheduleBtn');

        const submitText =
            document.getElementById('submitRescheduleText');

        const submitSpinner =
            document.getElementById('submitRescheduleSpinner');


        /*
         * Populate the modal from the button that opened it.
         *
         * Expected button attributes:
         *
         * data-booking-id="123"
         * data-reference-number="T88-6D219C7C8473CE96"
         */
        rescheduleModal.addEventListener(
            'show.bs.modal',
            function() {

                const button =
                    document.getElementById('rescheduleAppointmentBtn');

                if (!button) {
                    console.error(
                        'Reschedule appointment button was not found.'
                    );
                    return;
                }

                const bookingId =
                    button.dataset.bookingId || '';

                const referenceNumber =
                    button.dataset.referenceNumber || '';


                if (!bookingId) {
                    console.error(
                        'Booking ID is missing from the reschedule button.'
                    );
                    return;
                }

                if (!referenceNumber) {
                    console.error(
                        'Reference number is missing from the reschedule button.'
                    );
                    return;
                }


                /*
                 * Preserve the existing booking identifiers.
                 */
                bookingIdInput.value = bookingId;
                referenceInput.value = referenceNumber;


                /*
                 * Reset patient-entered fields.
                 */
                rescheduleDate.value = '';
                rescheduleTime.value = '';
                rescheduleReason.value = '';


                /*
                 * Clear previous messages.
                 */
                errorBox.textContent = '';
                errorBox.classList.add('d-none');

                successBox.textContent = '';
                successBox.classList.add('d-none');


                /*
                 * Restore submit button.
                 */
                submitButton.disabled = false;

                submitText.classList.remove('d-none');
                submitSpinner.classList.add('d-none');

            }
        );

        /*
         * Prevent accidental double submission.
         */

        form.addEventListener('submit', async function(event) {
            event.preventDefault();

            // Clear previous messages.
            errorBox.textContent = '';
            errorBox.classList.add('d-none');

            successBox.textContent = '';
            successBox.classList.add('d-none');

            // Validate required fields before sending.
            if (!form.reportValidity()) {
                return;
            }

            submitButton.disabled = true;
            submitText.classList.add('d-none');
            submitSpinner.classList.remove('d-none');

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(
                        result.message || 'Unable to reschedule your appointment.'
                    );
                }

                successBox.textContent = result.message;
                successBox.classList.remove('d-none');

                // Clear the patient-entered fields after success.
                rescheduleDate.value = '';
                rescheduleTime.value = '';
                rescheduleReason.value = '';

                // Keep the modal open so the patient can read the result.
                // The existing reference number and booking ID remain intact.

            } catch (error) {
                errorBox.textContent =
                    error instanceof Error ?
                    error.message :
                    'A network error occurred. Please try again.';

                errorBox.classList.remove('d-none');

            } finally {
                submitButton.disabled = false;
                submitText.classList.remove('d-none');
                submitSpinner.classList.add('d-none');
            }
        });


    });
</script>