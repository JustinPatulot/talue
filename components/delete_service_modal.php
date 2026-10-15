<div
    class="modal fade"
    tabindex="-1"
    id="deleteServiceModal"
    aria-labelledby="deleteServiceModalLabel"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-scrollable">

        <form
            action="../../controller/delete_service.php"
            method="POST">

            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="deleteServiceModalLabel">

                        Remove Service?

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
                        Are you sure you want to remove this dental service?
                    </p>

                    <!-- CSRF protection -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>">

                    <!-- Service ID populated by JavaScript -->
                    <input
                        type="hidden"
                        name="service_id"
                        id="deleteServiceId">

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