<div
    class="modal fade"
    tabindex="-1"
    id="bookingDeleteModal"
    aria-labelledby="bookingDeleteModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            action="../../controller/delete_booking.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="bookingDeleteModalLabel">

                        Delete the appointment?

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
                        Do you want to permanently delete this appointment?
                    </p>

                    <p class="text-danger small mb-0">
                        This action cannot be undone.
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
                        id="deleteBookingId">

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

                        Delete

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const deleteModal = document.getElementById('bookingDeleteModal');
        const bookingIdInput = document.getElementById('deleteBookingId');

        deleteModal.addEventListener('show.bs.modal', function(event) {

            const button = event.relatedTarget;

            if (!button) {
                return;
            }

            const bookingId = button.getAttribute('data-booking-id');

            console.log('Delete booking ID:', bookingId);

            bookingIdInput.value = bookingId || '';
        });

    });
</script>