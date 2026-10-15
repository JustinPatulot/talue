<div
    class="modal fade"
    tabindex="-1"
    id="bookingEditModal"
    aria-labelledby="bookingEditModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            action="../../controller/edit_booking.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="bookingEditModalLabel">

                        Update Appointment Status

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
                        Update the status of this approved appointment.
                        Please select the appropriate status.
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
                        id="editBookingId">

                    <!-- Appointment status -->
                    <div class="mb-3">

                        <label
                            for="bookingStatus"
                            class="form-label">

                            Appointment Status
                            <span class="text-danger">*</span>

                        </label>

                        <select
                            class="form-select"
                            name="status"
                            id="bookingStatus"
                            required>

                            <option value="" selected disabled>
                                Select status
                            </option>

                            <option value="Completed">
                                Completed
                            </option>

                            <option value="No Show">
                                No Show
                            </option>

                        </select>

                        <div class="form-text">
                            Select <strong>Completed</strong> if the patient attended the appointment,
                            or <strong>No Show</strong> if the patient did not attend.
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
                        class="btn btn-primary">

                        <i class="fa-solid fa-floppy-disk me-2"></i>
                        Save Changes

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const editModal =
            document.getElementById('bookingEditModal');

        if (!editModal) {
            console.error('Edit modal not found.');
            return;
        }

        const bookingIdInput =
            editModal.querySelector('#editBookingId');

        const bookingStatus =
            editModal.querySelector('#bookingStatus');

        console.log('Edit modal:', editModal);
        console.log('Edit booking ID input:', bookingIdInput);
        console.log('Booking status:', bookingStatus);

        editModal.addEventListener('show.bs.modal', function(event) {

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

            // Reset status whenever the modal opens
            bookingStatus.value = '';

        });

    });
</script>