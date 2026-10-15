<div
    class="modal fade"
    tabindex="-1"
    id="bookingRescheduleModal"
    aria-labelledby="bookingRescheduleModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            action="../../controller/reschedule_booking.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="bookingRescheduleModalLabel">

                        Reschedule the appointment?

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
                        for the reschedule. The patient will be notified.
                    </p>

                    <!-- CSRF protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>">

                    <!-- Booking ID populated by JavaScript -->
                    <input
                        type="hidden"
                        name="booking_id"
                        id="rescheduleBookingId">

                    <!-- New appointment date -->
                    <div class="mb-3">

                        <label
                            for="rescheduleDate"
                            class="form-label">

                            New appointment date
                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="date"
                            class="form-control"
                            name="new_booking_date"
                            id="rescheduleDate"
                            required>

                        <div class="form-text">
                            Select the new date for the appointment.
                        </div>

                    </div>

                    <!-- New appointment time -->
                    <div class="mb-3">

                        <label
                            for="rescheduleTime"
                            class="form-label">

                            New appointment time
                            <span class="text-danger">*</span>

                        </label>

                        <input
                            type="time"
                            class="form-control"
                            name="new_booking_time"
                            id="rescheduleTime"
                            required>

                        <div class="form-text">
                            Select the new time for the appointment.
                        </div>

                    </div>

                    <!-- Reschedule reason -->
                    <div class="mb-3">

                        <label
                            for="rescheduleReason"
                            class="form-label">

                            Reason for rescheduling
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
                            Please provide a clear reason that can be shared with the patient.
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
                        type="submit"
                        class="btn btn-warning">

                        <i class="fa-solid fa-calendar-days me-2"></i>
                        Reschedule Appointment

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const rescheduleModal =
            document.getElementById('bookingRescheduleModal');

        if (!rescheduleModal) {
            console.error('Reschedule modal not found.');
            return;
        }

        const bookingIdInput =
            rescheduleModal.querySelector('#rescheduleBookingId');

        const rescheduleDate =
            rescheduleModal.querySelector('#rescheduleDate');

        const rescheduleTime =
            rescheduleModal.querySelector('#rescheduleTime');

        const rescheduleReason =
            rescheduleModal.querySelector('#rescheduleReason');

        console.log('Reschedule modal:', rescheduleModal);
        console.log('Reschedule booking ID input:', bookingIdInput);
        console.log('Reschedule date:', rescheduleDate);
        console.log('Reschedule time:', rescheduleTime);
        console.log('Reschedule reason:', rescheduleReason);

        rescheduleModal.addEventListener(
            'show.bs.modal',
            function(event) {

                const button = event.relatedTarget;

                console.log('Triggering button:', button);

                if (!button) {
                    console.error('No triggering button found.');
                    return;
                }

                const bookingId =
                    button.getAttribute('data-booking-id');

                console.log('Booking ID found:', bookingId);

                if (!bookingId) {
                    console.error('Booking ID is missing.');
                    return;
                }

                bookingIdInput.value = bookingId;

                console.log(
                    'Hidden input value:',
                    bookingIdInput.value
                );

                // Reset form fields
                rescheduleDate.value = '';
                rescheduleTime.value = '';
                rescheduleReason.value = '';

            }
        );

    });
</script>