<div class="modal fade"
    id="editBranchModal"
    data-bs-keyboard="false"
    tabindex="-1"
    aria-labelledby="editBranchModalLabel"
    aria-hidden="true">

    <form id="editBranchForm" action="../../controller/edit_branch.php" method="POST">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title" id="editDentistModalLabel">
                        Edit branch
                    </h5>

                    <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close">
                    </button>
                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        id="edit_branch_id"
                        name="id">

                    <input
                        type="hidden"
                        name="form_action"
                        value="edit_branch">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                                    $_SESSION['csrf_token'],
                                    ENT_QUOTES | ENT_SUBSTITUTE,
                                    'UTF-8'
                                ) ?>">

                    <label for="edit_dentist_name" class="form-label">
                        Branch Name:
                    </label>

                    <input
                        type="text"
                        class="form-control"
                        id="edit_branch_name"
                        name="branch_name"
                        required>

                </div>

                <div class="modal-footer">
                    <button
                        type="submit"
                        class="btn btn-primary">
                        Edit Branch
                    </button>
                </div>

            </div>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const editBranchModal = document.getElementById('editBranchModal');
        const editBranchId = document.getElementById('edit_branch_id');
        const editBranchName = document.getElementById('edit_branch_name');
        const editBranchForm = document.getElementById('editBranchForm');

        document.querySelectorAll('.edit-branch-btn').forEach(function(button) {

            button.addEventListener('click', async function() {

                const branchId = this.dataset.branchId;

                if (!branchId) {
                    return;
                }

                // Clear previous values while loading
                editBranchId.value = '';
                editBranchName.value = '';

                try {
                    const response = await fetch(
                        `../../controller/get_branch.php?id=${encodeURIComponent(branchId)}`, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            }
                        }
                    );

                    const data = await response.json();

                    if (!response.ok || !data.success) {
                        throw new Error(
                            data.message || 'Failed to retrieve branch.'
                        );
                    }

                    editBranchId.value = data.branch.id;
                    editBranchName.value = data.branch.branch_name;

                } catch (error) {
                    console.error('Error loading branch:', error);

                    editBranchId.value = '';
                    editBranchName.value = '';

                    alert(
                        error.message || 'Unable to load branch information.'
                    );

                    // Close modal if loading failed
                    const modal = bootstrap.Modal.getInstance(editBranchModal);

                    if (modal) {
                        modal.hide();
                    }
                }
            });
        });

    });
</script>