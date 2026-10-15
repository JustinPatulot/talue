<div
    class="modal fade"
    tabindex="-1"
    id="bookingCancelModal"
    aria-labelledby="bookingCancelModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            action="../../controller/cancel_booking.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="bookingCancelModalLabel">

                        Cancel the appointment?

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
                        Please provide a reason for the cancellation.
                        The patient will be notified.
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
                        id="cancelBookingId">

                    <!-- Cancellation reason -->
                    <div class="mb-3">

                        <label
                            for="cancellationReason"
                            class="form-label">

                            Reason for cancellation
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
                        class="btn btn-danger">

                        <i class="fa-solid fa-ban me-2"></i>
                        Cancel Appointment

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const cancelModal = document.getElementById('bookingCancelModal');

        if (!cancelModal) {
            console.error('Cancel modal not found.');
            return;
        }

        const bookingIdInput =
            cancelModal.querySelector('#cancelBookingId');

        const cancellationReason =
            cancelModal.querySelector('#cancellationReason');

        console.log('Cancel modal:', cancelModal);
        console.log('Cancel booking ID input:', bookingIdInput);
        console.log('Cancellation reason:', cancellationReason);

        cancelModal.addEventListener('show.bs.modal', function(event) {

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

            cancellationReason.value = '';

        });

    });
</script>