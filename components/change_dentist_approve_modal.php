<div
    class="modal fade"
    tabindex="-1"
    id="changeDentistApproveModal"
    aria-labelledby="changeDentistApproveModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            action="../../controller/change_dentist_request.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="changeDentistApproveModalLabel">

                        Approve the Request?

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>


                <div class="modal-body">

                    <p class="mb-0">

                        Are you sure you want to approve this
                        dentist change request?

                    </p>


                    <!-- CSRF protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>">


                    <!-- Booking reference number populated by JavaScript -->
                    <input
                        type="hidden"
                        name="reference_number"
                        id="changeDentistApproveReferenceNumber">

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
                        class="btn btn-success">

                        Approve

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {

        const approveModal = document.getElementById(
            'changeDentistApproveModal'
        );

        const referenceNumberInput = document.getElementById(
            'changeDentistApproveReferenceNumber'
        );


        if (!approveModal || !referenceNumberInput) {
            return;
        }


        approveModal.addEventListener(
            'show.bs.modal',
            function(event) {

                const button = event.relatedTarget;

                if (!button) {
                    return;
                }


                const referenceNumber = button.getAttribute(
                    'data-reference-number'
                );


                referenceNumberInput.value = referenceNumber ?? '';

            }
        );

    });
</script>