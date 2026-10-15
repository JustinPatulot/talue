<div class="modal fade"
    id="editServiceModal"
    data-bs-keyboard="false"
    tabindex="-1"
    aria-labelledby="editServiceModalLabel"
    aria-hidden="true">

    <form
        id="editServiceForm"
        action="../../controller/edit_service.php"
        method="POST">

        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="editServiceModalLabel">

                        Edit Service

                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>

                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        id="edit_service_id"
                        name="id">

                    <input
                        type="hidden"
                        name="form_action"
                        value="edit_service">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>">

                    <label
                        for="edit_dental_service"
                        class="form-label">

                        Dental Service:

                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="edit_dental_service"
                        name="dental_service"
                        required>

                </div>

                <div class="modal-footer">

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Edit Service

                    </button>

                </div>

            </div>
        </div>

    </form>

</div>


<script>
    document.addEventListener('DOMContentLoaded', function() {

        const editServiceModal =
            document.getElementById('editServiceModal');

        const editServiceId =
            document.getElementById('edit_service_id');

        const editDentalService =
            document.getElementById('edit_dental_service');

        const editServiceForm =
            document.getElementById('editServiceForm');

        document.querySelectorAll('.edit-service-btn').forEach(function(button) {

            button.addEventListener('click', async function() {

                const serviceId = this.dataset.serviceId;

                if (!serviceId) {
                    return;
                }

                // Clear previous values while loading
                editServiceId.value = '';
                editDentalService.value = '';

                try {

                    const response = await fetch(
                        `../../controller/get_service.php?id=${encodeURIComponent(serviceId)}`, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            }
                        }
                    );

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(
                            data.message || 'Failed to retrieve dental service.'
                        );
                    }

                    editServiceId.value =
                        data.service.id;

                    editDentalService.value =
                        data.service.dental_service;

                } catch (error) {

                    console.error(
                        'Error loading dental service:',
                        error
                    );

                    editServiceId.value = '';
                    editDentalService.value = '';

                    alert(
                        error.message ||
                        'Unable to load dental service information.'
                    );

                    // Close modal if loading failed
                    const modal =
                        bootstrap.Modal.getInstance(
                            editServiceModal
                        );

                    if (modal) {
                        modal.hide();
                    }

                }

            });

        });

    });
</script>