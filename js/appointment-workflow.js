
document.addEventListener("DOMContentLoaded", () => {
    let cancelledBookingIds = new Set();
    const statusForm = document.getElementById("appointmentStatusForm");
    const statusError = document.getElementById("appointmentStatusError");
    const checkButton = document.getElementById("checkAppointmentBtn");
    const checkText = document.getElementById("checkAppointmentText");
    const checkSpinner = document.getElementById("checkAppointmentSpinner");
    const searchSection = document.getElementById("appointmentStatusSearch");
    const resultSection = document.getElementById("appointmentStatusResult");
    const checkAnotherButton = document.getElementById("checkAnotherAppointment");

    const appointmentActions = document.getElementById("appointmentActions");
    const changeDentistButton = document.getElementById("changeDentistBtn");
    const rescheduleButton = document.getElementById("rescheduleAppointmentBtn");
    const cancelButton = document.getElementById("cancelAppointmentBtn");
    const statusModal = document.getElementById("appointmentStatusModal");

    let statusRequestController = null;

    /*
    |--------------------------------------------------------------------------
    | Change Dentist Modal Elements
    |--------------------------------------------------------------------------
    */

    const changeForm = document.getElementById("changeDentistForm");
    const changeReference = document.getElementById("changeDentistReference");
    const currentDentist = document.getElementById("currentDentist");
    const newDentist = document.getElementById("newDentist");
    const reason = document.getElementById("reason");
    const changeError = document.getElementById("changeDentistError");
    const changeSuccess = document.getElementById("changeDentistSuccess");
    const submitButton = document.getElementById("submitChangeDentistBtn");
    const submitText = document.getElementById("submitChangeDentistText");
    const submitSpinner = document.getElementById("submitChangeDentistSpinner");

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    function setMessage(element, message) {
        if (!element) {
            return;
        }

        element.textContent = message;
        element.classList.toggle("d-none", !message);
    }

    function setCheckLoading(isLoading) {
        checkButton.disabled = isLoading;
        checkSpinner.classList.toggle("d-none", !isLoading);
        checkText.textContent = isLoading ? "Checking..." : "Check";
    }

    function setSubmitLoading(isLoading) {
        submitButton.disabled = isLoading;
        submitSpinner.classList.toggle("d-none", !isLoading);

        submitText.textContent = isLoading
            ? "Submitting..."
            : "Submit Request";
    }

    function resetStatusLookup() {
        if (statusRequestController) {
            statusRequestController.abort();
            statusRequestController = null;
        }

        statusForm.reset();

        searchSection.classList.remove("d-none");
        resultSection.classList.add("d-none");
        checkAnotherButton.classList.add("d-none");
        appointmentActions.classList.add("d-none");

        hideChangeDentistButton();

        setMessage(statusError, "");
        setCheckLoading(false);
    }

    /*
    |--------------------------------------------------------------------------
    | Change Dentist Button States
    |--------------------------------------------------------------------------
    |
    | Visible:
    |     Pending only
    |
    | Enabled:
    |     Pending with no existing request
    |
    | Disabled:
    |     Pending with an existing request or verification error
    |
    | Hidden:
    |     Every other appointment status
    |
    */

    function hideChangeDentistButton() {
        if (!changeDentistButton) {
            return;
        }

        changeDentistButton.classList.add("d-none");
        changeDentistButton.disabled = true;
        changeDentistButton.removeAttribute("title");
    }

    function showChangeDentistButtonAsChecking() {
        changeDentistButton.classList.remove("d-none");
        changeDentistButton.disabled = true;
        changeDentistButton.title =
            "Checking dentist change request availability...";
    }

    function showChangeDentistButtonAsAvailable() {
        changeDentistButton.classList.remove("d-none");
        changeDentistButton.disabled = false;
        changeDentistButton.removeAttribute("title");
    }

    function showChangeDentistButtonAsRequested() {
        changeDentistButton.classList.remove("d-none");
        changeDentistButton.disabled = true;
        changeDentistButton.title =
            "A dentist change request has already been submitted.";
    }

    /*
    |--------------------------------------------------------------------------
    | Check Dentist Change Request Availability
    |--------------------------------------------------------------------------
    */

    async function updateDentistChangeAvailability(referenceNumber) {
        showChangeDentistButtonAsChecking();

        try {
            const response = await fetch(
                `controller/change_dentist_status.php?reference_number=${encodeURIComponent(referenceNumber)}`,
                {
                    method: "GET",
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    credentials: "same-origin"
                }
            );

            const data = await response.json();

            console.log("Dentist change status:", data);

            if (!response.ok || !data.success) {
                changeDentistButton.disabled = true;
                changeDentistButton.title =
                    "Unable to verify dentist change request availability.";

                return;
            }

            if (data.has_request === true) {
                showChangeDentistButtonAsRequested();
                return;
            }

            showChangeDentistButtonAsAvailable();

        } catch (error) {
            console.error(
                "Unable to check dentist change request:",
                error
            );

            changeDentistButton.disabled = true;
            changeDentistButton.title =
                "Unable to verify dentist change request availability.";
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Appointment Action Buttons
    |--------------------------------------------------------------------------
    */


    async function updateAppointmentButtons(appointment) {
        const status = appointment.status;
        const bookingId = String(appointment.booking_id ?? "");

        /*
        |--------------------------------------------------------------------------
        | Synchronize Cancellation Lock With The Latest Server Status
        |--------------------------------------------------------------------------
        |
        | Cancelled: keep the cancellation lock.
        | Any other status: remove the stale cancellation lock.
        |
        */

        if (bookingId) {
            if (status === "Cancelled") {
                cancelledBookingIds.add(bookingId);
            } else {
                cancelledBookingIds.delete(bookingId);
            }
        }

        const alreadyCancelled = status === "Cancelled";

        /*
        |--------------------------------------------------------------------------
        | Change Dentist
        |--------------------------------------------------------------------------
        */

        if (status === "Pending") {
            await updateDentistChangeAvailability(
                appointment.reference_number
            );
        } else {
            hideChangeDentistButton();
        }

        /*
        |--------------------------------------------------------------------------
        | Reschedule
        |--------------------------------------------------------------------------
        */

        const canReschedule = ["Pending", "Approved"].includes(status);

        rescheduleButton.classList.toggle(
            "d-none",
            !canReschedule
        );

        rescheduleButton.disabled = !canReschedule;

        /*
        |--------------------------------------------------------------------------
        | Cancel Appointment
        |--------------------------------------------------------------------------
        */

        const canCancel = [
            "Pending",
            "Approved",
            "Rescheduled"
        ].includes(status);

        cancelButton.classList.toggle(
            "d-none",
            !canCancel
        );

        cancelButton.disabled = !canCancel;

        cancelButton.setAttribute(
            "aria-disabled",
            String(!canCancel)
        );

        cancelButton.dataset.cancelled = String(alreadyCancelled);

        if (alreadyCancelled) {
            cancelButton.title =
                "This appointment has already been cancelled.";
        } else {
            cancelButton.removeAttribute("title");
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Prevent Clicking an Unavailable Cancellation Button
    |--------------------------------------------------------------------------
    |
    | This works for both <button> and <a> elements.
    | stopImmediatePropagation prevents the click from reaching
    | Bootstrap's delegated data-api modal handler.
    |
    */

    cancelButton.addEventListener("click", event => {
        const unavailable =
            cancelButton.disabled ||
            cancelButton.dataset.cancelled === "true" ||
            cancelButton.getAttribute("aria-disabled") === "true";

        if (unavailable) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Appointment Status Lookup
    |--------------------------------------------------------------------------
    */

    statusForm.addEventListener("submit", async event => {
        event.preventDefault();

        setMessage(statusError, "");

        if (!statusForm.checkValidity()) {
            statusForm.reportValidity();
            return;
        }

        setCheckLoading(true);

        const requestController = new AbortController();
        statusRequestController = requestController;

        try {
            const response = await fetch(
                statusForm.action,
                {
                    method: "POST",
                    body: new FormData(statusForm),
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": "application/json"
                    },
                    credentials: "same-origin",
                    signal: requestController.signal
                }
            );

            const result = await response.json();

            if (
                !response.ok ||
                !result.success ||
                !result.data
            ) {
                setMessage(
                    statusError,
                    result.message ||
                    "Unable to find that appointment."
                );

                return;
            }

            const appointment = result.data;

            /*
            |--------------------------------------------------------------------------
            | Appointment Information
            |--------------------------------------------------------------------------
            */

            const fields = {
                resultReference: appointment.reference_number,
                resultName: appointment.full_name,
                resultService: appointment.service,
                resultDentist: appointment.dentist,
                resultBranch: appointment.branch,
                resultDate: appointment.booking_date,
                resultTime: appointment.booking_time
            };

            Object.entries(fields).forEach(([id, value]) => {
                const element = document.getElementById(id);

                if (element) {
                    element.textContent = value || "Not available";
                }
            });

            /*
            |--------------------------------------------------------------------------
            | Status Badge
            |--------------------------------------------------------------------------
            */

            const badge = document.getElementById(
                "appointmentStatusBadge"
            );

            if (badge) {
                badge.textContent = appointment.status || "Unknown";
                badge.className = "badge";

                badge.classList.add(
                    appointment.status === "Approved"
                        ? "bg-success"
                        : appointment.status === "Cancelled"
                            ? "bg-danger"
                            : "bg-warning"
                );

                if (
                    appointment.status === "Pending" ||
                    appointment.status === "Rescheduled"
                ) {
                    badge.classList.add("text-dark");
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Status Icon
            |--------------------------------------------------------------------------
            */

            const icon = document.getElementById(
                "appointmentStatusIcon"
            );

            if (icon) {
                icon.innerHTML =
                    appointment.status === "Approved"
                        ? '<i class="fa-solid fa-circle-check text-success" aria-hidden="true"></i>'
                        : appointment.status === "Cancelled"
                            ? '<i class="fa-solid fa-circle-xmark text-danger" aria-hidden="true"></i>'
                            : '<i class="fa-solid fa-clock text-warning" aria-hidden="true"></i>';
            }

            /*
            |--------------------------------------------------------------------------
            | Store Appointment Identifiers
            |--------------------------------------------------------------------------
            */

            const bookingId = String(appointment.booking_id ?? "");
            const referenceNumber = String(
                appointment.reference_number ?? ""
            );

            console.log("Appointment identifiers received:", {
                booking_id: bookingId,
                reference_number: referenceNumber
            });

            if (!bookingId || !referenceNumber) {
                setMessage(
                    statusError,
                    "The appointment was found, but its booking ID or reference number is missing from the server response."
                );

                console.error(
                    "Missing appointment identifiers:",
                    appointment
                );

                return;
            }

            // Change Dentist
            changeDentistButton.dataset.reference = referenceNumber;
            changeDentistButton.dataset.currentDentist =
                appointment.dentist || "";

            changeDentistButton.dataset.currentDentistId = String(
                appointment.dentist_id ?? ""
            );

            // Reschedule Appointment
            rescheduleButton.dataset.bookingId = bookingId;
            rescheduleButton.dataset.referenceNumber = referenceNumber;

            // Cancel Appointment
            cancelButton.dataset.bookingId = bookingId;
            cancelButton.dataset.referenceNumber = referenceNumber;

            console.log("Appointment action buttons populated:", {
                rescheduleBookingId: rescheduleButton.dataset.bookingId,
                rescheduleReferenceNumber:
                    rescheduleButton.dataset.referenceNumber,
                cancelBookingId: cancelButton.dataset.bookingId,
                cancelReferenceNumber:
                    cancelButton.dataset.referenceNumber
            });

            /*
            |--------------------------------------------------------------------------
            | Show Result
            |--------------------------------------------------------------------------
            */

            searchSection.classList.add("d-none");
            resultSection.classList.remove("d-none");
            checkAnotherButton.classList.remove("d-none");
            appointmentActions.classList.remove("d-none");

            /*
            |--------------------------------------------------------------------------
            | Apply Button Conditions
            |--------------------------------------------------------------------------
            */

            await updateAppointmentButtons(appointment);

        } catch (error) {
            if (error.name === "AbortError") {
                return;
            }

            console.error(
                "Appointment status lookup error:",
                error
            );

            setMessage(
                statusError,
                "Something went wrong while checking your appointment. Please try again."
            );

        } finally {
            if (statusRequestController === requestController) {
                statusRequestController = null;
                setCheckLoading(false);
            }
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Check Another Appointment
    |--------------------------------------------------------------------------
    */

    checkAnotherButton.addEventListener(
        "click",
        resetStatusLookup
    );

    statusModal.addEventListener(
        "hidden.bs.modal",
        resetStatusLookup
    );

    /*
    |--------------------------------------------------------------------------
    | Change Dentist Modal
    |--------------------------------------------------------------------------
    */

    document
        .getElementById("changeDentistModal")
        .addEventListener("show.bs.modal", event => {
            const trigger = event.relatedTarget;

            if (!trigger) {
                return;
            }

            changeReference.value =
                trigger.dataset.reference || "";

            currentDentist.value =
                trigger.dataset.currentDentist || "";

            newDentist.value = "";
            reason.value = "";

            newDentist.disabled = false;
            reason.disabled = false;

            submitButton.disabled = false;

            submitText.textContent = "Submit Request";
            submitSpinner.classList.add("d-none");

            setMessage(changeError, "");
            setMessage(changeSuccess, "");

            /*
            |--------------------------------------------------------------------------
            | Prevent Current Dentist From Being Selected
            |--------------------------------------------------------------------------
            */

            [...newDentist.options].forEach(option => {
                if (option.value) {
                    option.disabled =
                        option.value ===
                        trigger.dataset.currentDentistId;
                }
            });
        });

    /*
    |--------------------------------------------------------------------------
    | Submit Change Dentist Request
    |--------------------------------------------------------------------------
    */

    changeForm.addEventListener("submit", async event => {
        event.preventDefault();

        setMessage(changeError, "");
        setMessage(changeSuccess, "");

        if (!changeForm.checkValidity()) {
            changeForm.reportValidity();
            return;
        }

        setSubmitLoading(true);

        let requestLocked = false;

        try {
            const response = await fetch(
                changeForm.action,
                {
                    method: "POST",
                    body: new FormData(changeForm),
                    headers: {
                        "X-Requested-With": "XMLHttpRequest",
                        "Accept": "application/json"
                    },
                    credentials: "same-origin"
                }
            );

            const result = await response.json();

            /*
            |--------------------------------------------------------------------------
            | Backend Error
            |--------------------------------------------------------------------------
            */

            if (!response.ok || !result.success) {
                setMessage(
                    changeError,
                    result.message ||
                    "Unable to submit the dentist change request."
                );

                if (result.already_requested) {
                    requestLocked = true;

                    newDentist.disabled = true;
                    reason.disabled = true;

                    submitText.textContent =
                        "Request Already Submitted";

                    showChangeDentistButtonAsRequested();
                }

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            requestLocked = true;

            setMessage(
                changeSuccess,
                result.message ||
                "Your dentist change request has been submitted successfully."
            );

            newDentist.disabled = true;
            reason.disabled = true;

            submitText.textContent = "Request Submitted";

            showChangeDentistButtonAsRequested();

        } catch (error) {
            console.error(
                "Change dentist request error:",
                error
            );

            setMessage(
                changeError,
                "Something went wrong while submitting your request. Please try again."
            );

        } finally {
            submitSpinner.classList.add("d-none");

            if (!requestLocked) {
                setSubmitLoading(false);
            }
        }
    });

    /*
    |--------------------------------------------------------------------------
    | Patient Cancellation Modal Elements
    |--------------------------------------------------------------------------
    */

    const cancelModal = document.getElementById(
        "bookingCancelPatientModal"
    );

    const cancelForm = document.getElementById(
        "bookingCancelPatientForm"
    );

    const cancelBookingId = document.getElementById(
        "cancelBookingId"
    );

    const cancelReferenceNumber = document.getElementById(
        "cancelReferenceNumber"
    );

    const cancellationReason = document.getElementById(
        "cancellationReason"
    );

    const cancelError = document.getElementById(
        "cancelPatientError"
    );

    const cancelSuccess = document.getElementById(
        "cancelPatientSuccess"
    );

    const cancelSubmitButton = document.getElementById(
        "cancelPatientSubmitBtn"
    );

    const cancelSubmitText = document.getElementById(
        "cancelPatientSubmitText"
    );

    const cancelSubmitLoading = document.getElementById(
        "cancelPatientSubmitLoading"
    );

    /*
    |--------------------------------------------------------------------------
    | Cancellation Helpers
    |--------------------------------------------------------------------------
    */

    function clearCancellationMessages() {
        setMessage(cancelError, "");
        setMessage(cancelSuccess, "");
    }



    function setCancellationLoading(isLoading) {
        cancelSubmitButton.disabled = isLoading;

        // Show only the appropriate label.
        cancelSubmitText.classList.toggle("d-none", isLoading);
        cancelSubmitLoading.classList.toggle("d-none", !isLoading);

        if (isLoading) {
            cancelSubmitLoading.querySelector("span.spinner-border")
                ?.classList.add("me-2");
        }
    }

    function markAppointmentCancelled() {
        const bookingId = String(cancelBookingId.value || "");

        if (bookingId) {
            cancelledBookingIds.add(bookingId);
        }

        cancelButton.dataset.cancelled = "true";
        cancelButton.disabled = true;
        cancelButton.setAttribute("aria-disabled", "true");
        cancelButton.classList.add("d-none");
        cancelButton.title = "This appointment has already been cancelled.";

        rescheduleButton.disabled = true;
        rescheduleButton.classList.add("d-none");

        hideChangeDentistButton();

        // Also lock the modal's submit button after success.
        cancelSubmitButton.disabled = true;
        cancelSubmitText.textContent = "Appointment Cancelled";
        cancelSubmitLoading.classList.add("d-none");
    }

    /*
    |--------------------------------------------------------------------------
    | Populate Cancellation Modal
    |--------------------------------------------------------------------------
    */

    if (
        cancelModal &&
        cancelForm &&
        cancelBookingId &&
        cancelReferenceNumber &&
        cancellationReason &&
        cancelError &&
        cancelSuccess &&
        cancelSubmitButton &&
        cancelSubmitText &&
        cancelSubmitLoading &&
        cancelButton
    ) {
        cancelModal.addEventListener(
            "show.bs.modal",
            event => {
                /*
                 * Refuse to open the modal for a cancelled appointment.
                 */
                if (
                    cancelButton.dataset.cancelled === "true" ||
                    cancelButton.getAttribute("aria-disabled") === "true"
                ) {
                    event.preventDefault();
                    return;
                }

                cancelForm.reset();

                clearCancellationMessages();
                setCancellationLoading(false);

                const trigger =
                    event.relatedTarget || cancelButton;

                const bookingId =
                    trigger.dataset.bookingId ||
                    cancelButton.dataset.bookingId ||
                    "";

                const referenceNumber =
                    trigger.dataset.referenceNumber ||
                    cancelButton.dataset.referenceNumber ||
                    "";

                cancelBookingId.value = bookingId;
                cancelReferenceNumber.value = referenceNumber;

                if (!bookingId || !referenceNumber) {
                    setMessage(
                        cancelError,
                        "Appointment details are missing. Please close this modal and check your appointment status again."
                    );

                    cancelSubmitButton.disabled = true;
                    return;
                }


                cancelSubmitButton.disabled = false;
                cancelSubmitText.textContent = "Submit Cancellation";
                cancelSubmitText.classList.remove("d-none");
                cancelSubmitLoading.classList.add("d-none");


                console.log("Cancellation modal identifiers:", {
                    booking_id: cancelBookingId.value,
                    reference_number: cancelReferenceNumber.value
                });
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Submit Cancellation Using AJAX
        |--------------------------------------------------------------------------
        */

        cancelForm.addEventListener(
            "submit",
            async event => {
                event.preventDefault();

                clearCancellationMessages();

                if (!cancelForm.reportValidity()) {
                    return;
                }

                if (
                    !cancelBookingId.value ||
                    !cancelReferenceNumber.value
                ) {
                    setMessage(
                        cancelError,
                        "Appointment details are missing. Please check your appointment again."
                    );

                    return;
                }

                if (cancelSubmitButton.disabled) {
                    return;
                }

                setCancellationLoading(true);

                try {
                    const response = await fetch(
                        cancelForm.action,
                        {
                            method: "POST",
                            body: new FormData(cancelForm),
                            headers: {
                                "Accept": "application/json",
                                "X-Requested-With": "XMLHttpRequest"
                            },
                            credentials: "same-origin"
                        }
                    );

                    let result;

                    try {
                        result = await response.json();
                    } catch {
                        throw new Error(
                            "The server returned an invalid response. Please try again."
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Backend Error
                    |--------------------------------------------------------------------------
                    */

                    if (!response.ok || !result.success) {
                        setMessage(
                            cancelError,
                            result.message ||
                            "Unable to cancel the appointment. Please try again."
                        );

                        return;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Cancellation Successful
                    |--------------------------------------------------------------------------
                    */

                    setMessage(
                        cancelSuccess,
                        result.message ||
                        "Appointment cancelled successfully."
                    );

                    /*
                     * Update the visible status without refreshing.
                     */
                    const badge = document.getElementById(
                        "appointmentStatusBadge"
                    );

                    if (badge) {
                        badge.textContent = "Cancelled";
                        badge.className = "badge bg-danger";
                    }

                    const icon = document.getElementById(
                        "appointmentStatusIcon"
                    );

                    if (icon) {
                        icon.innerHTML =
                            '<i class="fa-solid fa-circle-xmark text-danger" aria-hidden="true"></i>';
                    }

                    /*
                     * Immediately hide and disable all incompatible
                     * actions. The cancel button's click guard also
                     * prevents Bootstrap from reopening the modal.
                     */
                    markAppointmentCancelled();

                    /*
                     * Clear the reason but retain the booking identifiers
                     * until the modal is closed.
                     */
                    cancellationReason.value = "";

                    /*
                     * Notify other components if necessary.
                     */
                    document.dispatchEvent(
                        new CustomEvent(
                            "patientAppointmentCancelled",
                            {
                                detail: {
                                    bookingId: cancelBookingId.value,
                                    referenceNumber:
                                        cancelReferenceNumber.value
                                }
                            }
                        )
                    );

                } catch (error) {
                    console.error(
                        "Patient cancellation error:",
                        error
                    );

                    setMessage(
                        cancelError,
                        error instanceof Error
                            ? error.message
                            : "A network error occurred. Please try again."
                    );

                }
                finally {
                    if (cancelButton.dataset.cancelled === "true") {
                        cancelSubmitButton.disabled = true;

                        cancelSubmitText.textContent = "Appointment Cancelled";
                        cancelSubmitText.classList.remove("d-none");

                        cancelSubmitLoading.classList.add("d-none");
                    } else {
                        setCancellationLoading(false);
                        cancelSubmitText.textContent = "Submit Cancellation";
                    }
                }
            }
        );

    } else {
        console.error(
            "Cancellation modal setup failed:",
            {
                cancelModal: !!cancelModal,
                cancelForm: !!cancelForm,
                cancelBookingId: !!cancelBookingId,
                cancelReferenceNumber: !!cancelReferenceNumber,
                cancellationReason: !!cancellationReason,
                cancelError: !!cancelError,
                cancelSuccess: !!cancelSuccess,
                cancelSubmitButton: !!cancelSubmitButton,
                cancelSubmitText: !!cancelSubmitText,
                cancelSubmitLoading: !!cancelSubmitLoading,
                cancelButton: !!cancelButton
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Initial State
    |--------------------------------------------------------------------------
    */

    hideChangeDentistButton();
});
