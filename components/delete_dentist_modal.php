<div
    class="modal fade"
    tabindex="-1"
    id="deleteDentistModal"
    aria-labelledby="deleteDentistModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            action="../../controller/delete_dentist.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="deleteDentistModalLabel">

                        Remove Dentist?

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
                        Are you sure you want to remove this dentist?
                    </p>

                    <!-- CSRF protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>">

                    <!-- Dentist ID populated by JavaScript -->
                    <input
                        type="hidden"
                        name="dentist_id"
                        id="deleteDentistId">

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

                        Remove

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>