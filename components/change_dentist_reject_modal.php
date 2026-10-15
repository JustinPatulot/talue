<div
    class="modal fade"
    tabindex="-1"
    id="changeDentistRejectModal"
    aria-labelledby="changeDentistRejectModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            action="../../controller/change_dentist_reject.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="changeDentistRejectModalLabel">

                        Reject the Request?

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>


                <div class="modal-body">

                    <p class="mb-3">

                        Are you sure you want to reject this
                        dentist change request? Please provide a
                        reason so the user can be informed via email.

                    </p>


                    <!-- CSRF protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>">


                    <!-- Action -->
                    <input
                        type="hidden"
                        name="action"
                        value="reject">


                    <!-- Booking reference number populated by JavaScript -->
                    <input
                        type="hidden"
                        name="reference_number"
                        id="changeDentistRejectReferenceNumber">


                    <!-- Rejection reason -->
                    <div class="mb-3">

                        <label
                            for="changeDentistRejectReason"
                            class="form-label">

                            Reason for Rejection

                            <span class="text-danger">*</span>

                        </label>

                        <textarea
                            class="form-control"
                            name="reason"
                            id="changeDentistRejectReason"
                            rows="4"
                            maxlength="1000"
                            required
                            placeholder="Enter the reason for rejecting this dentist change request..."></textarea>

                        <div class="form-text">
                            This reason will be included in the notification
                            sent to the user.
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

                        Reject

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {

        const rejectModal = document.getElementById(
            'changeDentistRejectModal'
        );

        const referenceNumberInput = document.getElementById(
            'changeDentistRejectReferenceNumber'
        );

        const reasonInput = document.getElementById(
            'changeDentistRejectReason'
        );


        if (
            !rejectModal ||
            !referenceNumberInput ||
            !reasonInput
        ) {
            return;
        }


        rejectModal.addEventListener(
            'show.bs.modal',
            function(event) {

                const button = event.relatedTarget;

                if (!button) {
                    return;
                }


                const referenceNumber = button.getAttribute(
                    'data-reference-number'
                );


                referenceNumberInput.value =
                    referenceNumber ?? '';


                // Clear previous reason whenever the modal opens
                reasonInput.value = '';

            }
        );

    });
</script>