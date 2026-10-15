document.addEventListener("DOMContentLoaded", () => {

    const form = document.getElementById("changeDentistForm");

    if (!form) {
        return;
    }

    const changeDentistModal =
        document.getElementById("changeDentistModal");

    const referenceInput =
        document.getElementById("changeDentistReference");

    const currentDentistInput =
        document.getElementById("currentDentist");

    const newDentistSelect =
        document.getElementById("newDentist");

    const reasonInput =
        document.getElementById("reason");

    const errorBox =
        document.getElementById("changeDentistError");

    const successBox =
        document.getElementById("changeDentistSuccess");

    const submitButton =
        document.getElementById("submitChangeDentistBtn");

    const submitText =
        document.getElementById("submitChangeDentistText");

    const submitSpinner =
        document.getElementById("submitChangeDentistSpinner");


    /*
    |--------------------------------------------------------------------------
    | Show Error
    |--------------------------------------------------------------------------
    */

    function showError(message) {

        successBox.classList.add("d-none");
        successBox.textContent = "";

        errorBox.textContent = message;
        errorBox.classList.remove("d-none");
    }


    /*
    |--------------------------------------------------------------------------
    | Show Success
    |--------------------------------------------------------------------------
    */

    function showSuccess(message) {

        errorBox.classList.add("d-none");
        errorBox.textContent = "";

        successBox.textContent = message;
        successBox.classList.remove("d-none");
    }


    /*
    |--------------------------------------------------------------------------
    | Reset Messages
    |--------------------------------------------------------------------------
    */

    function resetMessages() {

        errorBox.classList.add("d-none");
        errorBox.textContent = "";

        successBox.classList.add("d-none");
        successBox.textContent = "";
    }


    /*
    |--------------------------------------------------------------------------
    | Submit Button State
    |--------------------------------------------------------------------------
    */

    function setSubmitting(isSubmitting) {

        submitButton.disabled = isSubmitting;

        submitSpinner.classList.toggle(
            "d-none",
            !isSubmitting
        );

        submitText.textContent = isSubmitting
            ? "Submitting..."
            : "Submit Request";
    }


    /*
    |--------------------------------------------------------------------------
    | Disable Appointment Status Change Dentist Button
    |--------------------------------------------------------------------------
    */

    // No code, MOVED

    /*
    |--------------------------------------------------------------------------
    | Populate Change Dentist Modal
    |--------------------------------------------------------------------------
    */

    if (changeDentistModal) {

        changeDentistModal.addEventListener(
            "show.bs.modal",
            event => {

                const trigger = event.relatedTarget;

                if (!trigger) {
                    return;
                }

                const referenceNumber =
                    trigger.dataset.reference || "";

                const currentDentist =
                    trigger.dataset.currentDentist || "";

                const currentDentistId =
                    trigger.dataset.currentDentistId || "";


                referenceInput.value =
                    referenceNumber;

                currentDentistInput.value =
                    currentDentist;

                newDentistSelect.value = "";
                reasonInput.value = "";

                resetMessages();

                submitButton.disabled = false;

                submitText.textContent =
                    "Submit Request";

                submitSpinner.classList.add(
                    "d-none"
                );


                /*
                |--------------------------------------------------------------------------
                | Prevent Current Dentist From Being Selected
                |--------------------------------------------------------------------------
                */

                [...newDentistSelect.options].forEach(option => {

                    if (!option.value) {
                        return;
                    }

                    option.disabled =
                        option.value === currentDentistId;

                });

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Submit Change Dentist Request
    |--------------------------------------------------------------------------
    */

    form.addEventListener("submit", async event => {

        /*
         * THIS IS THE IMPORTANT LINE.
         *
         * It prevents the browser from submitting the
         * form normally and refreshing the page.
         */

        event.preventDefault();

        resetMessages();


        /*
        |--------------------------------------------------------------------------
        | Browser Validation
        |--------------------------------------------------------------------------
        */

        if (!form.checkValidity()) {

            form.reportValidity();

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | Start Loading
        |--------------------------------------------------------------------------
        */

        setSubmitting(true);


        try {

            const response = await fetch(
                "change-dentist-request.php",
                {
                    method: "POST",
                    body: new FormData(form),

                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    }
                }
            );


            /*
            |--------------------------------------------------------------------------
            | Read JSON
            |--------------------------------------------------------------------------
            */

            const data = await response.json();


            /*
            |--------------------------------------------------------------------------
            | Backend Error
            |--------------------------------------------------------------------------
            */

            if (!response.ok || !data.success) {

                showError(
                    data.message ||
                    "Unable to submit the dentist change request."
                );


                /*
                |--------------------------------------------------------------------------
                | Already Requested
                |--------------------------------------------------------------------------
                */

                if (data.already_requested) {

                    submitButton.disabled = true;

                    disableChangeDentistButton();

                } else {

                    /*
                     * Allow retry for ordinary errors.
                     */

                    setSubmitting(false);
                }

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            showSuccess(
                data.message ||
                "Your dentist change request has been submitted successfully."
            );


            /*
            |--------------------------------------------------------------------------
            | Request Can Only Be Submitted Once
            |--------------------------------------------------------------------------
            */

            submitButton.disabled = true;


            /*
            |--------------------------------------------------------------------------
            | Prevent Editing After Submission
            |--------------------------------------------------------------------------
            */

            newDentistSelect.disabled = true;
            reasonInput.disabled = true;


            /*
            |--------------------------------------------------------------------------
            | Disable Change Dentist Button In
            | Appointment Status Modal
            |--------------------------------------------------------------------------
            */

            disableChangeDentistButton();


            /*
            |--------------------------------------------------------------------------
            | Stop Spinner
            |--------------------------------------------------------------------------
            */

            submitSpinner.classList.add(
                "d-none"
            );

            submitText.textContent =
                "Request Submitted";


        } catch (error) {

            console.error(
                "Change dentist request error:",
                error
            );

            showError(
                "Something went wrong while submitting your request. Please try again."
            );

            setSubmitting(false);
        }

    });

});

document.addEventListener("DOMContentLoaded", () => {

    const form = document.getElementById("changeDentistForm");

    if (!form) {
        return;
    }

    const referenceInput = document.getElementById(
        "changeDentistReference"
    );

    const currentDentistInput = document.getElementById(
        "currentDentist"
    );

    const newDentistSelect = document.getElementById(
        "newDentist"
    );

    const reasonInput = document.getElementById(
        "reason"
    );

    const errorBox = document.getElementById(
        "changeDentistError"
    );

    const successBox = document.getElementById(
        "changeDentistSuccess"
    );

    const submitButton = document.getElementById(
        "submitChangeDentistBtn"
    );

    const submitText = document.getElementById(
        "submitChangeDentistText"
    );

    const submitSpinner = document.getElementById(
        "submitChangeDentistSpinner"
    );

    /*
    |--------------------------------------------------------------------------
    | Appointment Status Modal Change Dentist Button
    |--------------------------------------------------------------------------
    */

    const appointmentChangeDentistButton =
        document.getElementById("appointmentChangeDentistBtn");


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    function showError(message) {

        successBox.classList.add("d-none");
        successBox.textContent = "";

        errorBox.textContent = message;
        errorBox.classList.remove("d-none");
    }


    function showSuccess(message) {

        errorBox.classList.add("d-none");
        errorBox.textContent = "";

        successBox.textContent = message;
        successBox.classList.remove("d-none");
    }


    function resetMessages() {

        errorBox.classList.add("d-none");
        errorBox.textContent = "";

        successBox.classList.add("d-none");
        successBox.textContent = "";
    }


    function setSubmitting(isSubmitting) {

        submitButton.disabled = isSubmitting;

        submitSpinner.classList.toggle(
            "d-none",
            !isSubmitting
        );

        submitText.textContent = isSubmitting
            ? "Submitting..."
            : "Submit Request";
    }


    function disableChangeDentistButton() {

        if (!appointmentChangeDentistButton) {
            return;
        }

        appointmentChangeDentistButton.disabled = true;

        appointmentChangeDentistButton.setAttribute(
            "title",
            "A dentist change request has already been submitted."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Initialize Modal
    |--------------------------------------------------------------------------
    */

    const changeDentistModal =
        document.getElementById("changeDentistModal");

    changeDentistModal.addEventListener(
        "show.bs.modal",
        event => {

            const trigger = event.relatedTarget;

            if (!trigger) {
                return;
            }

            const referenceNumber =
                trigger.dataset.reference;

            const currentDentist =
                trigger.dataset.currentDentist;

            referenceInput.value =
                referenceNumber || "";

            currentDentistInput.value =
                currentDentist || "";

            newDentistSelect.value = "";
            reasonInput.value = "";

            resetMessages();

            submitButton.disabled = false;
            submitText.textContent = "Submit Request";
            submitSpinner.classList.add("d-none");

            /*
             * Make sure the current dentist is not selectable.
             */

            [...newDentistSelect.options].forEach(option => {

                if (
                    option.value &&
                    option.value === trigger.dataset.currentDentistId
                ) {
                    option.disabled = true;
                } else if (option.value) {
                    option.disabled = false;
                }

            });
        }
    );


    /*
    |--------------------------------------------------------------------------
    | Submit
    |--------------------------------------------------------------------------
    */

    form.addEventListener("submit", async event => {

        event.preventDefault();

        resetMessages();

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        setSubmitting(true);

        try {

            const response = await fetch(
                "change-dentist-request.php",
                {
                    method: "POST",
                    body: new FormData(form),
                    headers: {
                        "X-Requested-With": "XMLHttpRequest"
                    }
                }
            );

            const data = await response.json();

            if (!response.ok || !data.success) {

                showError(
                    data.message ||
                    "Unable to submit the dentist change request."
                );

                /*
                 * Backend says request already exists.
                 */

                if (data.already_requested) {

                    submitButton.disabled = true;

                    disableChangeDentistButton();
                }

                return;
            }


            /*
             * SUCCESS
             */

            showSuccess(
                data.message ||
                "Your dentist change request has been submitted successfully."
            );

            /*
             * The request can only be submitted once.
             */

            submitButton.disabled = true;

            /*
             * Prevent changing the submitted values.
             */

            newDentistSelect.disabled = true;
            reasonInput.disabled = true;

            /*
             * Disable Change Dentist in the appointment
             * status modal immediately.
             */

            disableChangeDentistButton();


        } catch (error) {

            console.error(
                "Change dentist request error:",
                error
            );

            showError(
                "Something went wrong while submitting your request. Please try again."
            );

        } finally {

            /*
             * Do not re-enable the submit button if the
             * request was successfully submitted.
             */

            if (!submitButton.disabled) {
                setSubmitting(false);
            } else {
                submitSpinner.classList.add("d-none");
                submitText.textContent = "Submit Request";
            }
        }

    });

});

async function checkDentistChangeRequest(referenceNumber) {

    const button = document.getElementById(
        "appointmentChangeDentistBtn"
    );

    if (!button) {
        return;
    }

    button.disabled = true;

    try {

        const response = await fetch(
            `change-dentist-status.php?reference_number=${encodeURIComponent(referenceNumber)}`,
            {
                headers: {
                    "X-Requested-With": "XMLHttpRequest"
                }
            }
        );

        const data = await response.json();

        if (!response.ok || !data.success) {
            return;
        }

        if (data.has_request) {

            button.disabled = true;

            button.setAttribute(
                "title",
                "A dentist change request has already been submitted."
            );

        } else {

            button.disabled = false;

            button.removeAttribute("title");
        }

    } catch (error) {

        console.error(
            "Unable to check dentist change request:",
            error
        );

        /*
         * Fail closed:
         * don't allow a potentially duplicate request
         * if we cannot verify the status.
         */

        button.disabled = true;
    }
}