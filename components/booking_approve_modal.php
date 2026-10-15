<div
    class="modal fade"
    tabindex="-1"
    id="bookingApproveModal"
    aria-labelledby="bookingApproveModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            action="../../controller/approve_booking.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="bookingApproveModalLabel">

                        Approve the appointment?

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
                        Do youu want to approve appointment?
                    </p>

                    <!-- CSRF protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>">

                    <!-- Dentist ID populated by JavaScript -->
                    <input
                        type="hidden"
                        name="booking_id"
                        id="bookingId">

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
                        class="btn btn-secondary">

                        Approve

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const approveModal = document.getElementById('bookingApproveModal');
        const bookingIdInput = document.getElementById('bookingId');

        approveModal.addEventListener('show.bs.modal', function(event) {

            const button = event.relatedTarget;

            const bookingId = button.getAttribute('data-booking-id');

            bookingIdInput.value = bookingId;
        });

    });
</script>