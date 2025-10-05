document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap === 'undefined') {
        console.error("Bootstrap 5 is not loaded. admin-modal.js functionality may be impaired.");
        return;
    }

    // --- Generic Modal Functions ---
    const genericModalElement = document.getElementById('genericModal');
    let genericModal;
    if (genericModalElement) {
        genericModal = new bootstrap.Modal(genericModalElement);
    } else {
        console.error("Generic modal element #genericModal not found. Please ensure it's in your HTML.");
    }

    function showGenericModal(title, body, footerHtml, onHiddenCallback = null) {
        if (!genericModalElement || !genericModal) return;

        const modalTitle = genericModalElement.querySelector('#genericModalLabel');
        const modalBody = genericModalElement.querySelector('#genericModalBody');
        const modalFooter = genericModalElement.querySelector('#genericModalFooter');

        if (modalTitle) modalTitle.innerHTML = title;
        if (modalBody) modalBody.innerHTML = body;
        if (modalFooter) modalFooter.innerHTML = footerHtml;

        // Ensure only one hidden listener at a time
        const oldHiddenCallback = genericModalElement.dataset.onHiddenCallback;
        if (oldHiddenCallback) {
             genericModalElement.removeEventListener('hidden.bs.modal', window[oldHiddenCallback]);
        }
        if (onHiddenCallback) {
            // Store a unique name for the callback to remove it later if needed
            const callbackName = 'genericModalHiddenCallback_' + Date.now();
            window[callbackName] = onHiddenCallback;
            genericModalElement.addEventListener('hidden.bs.modal', window[callbackName]);
            genericModalElement.dataset.onHiddenCallback = callbackName;
        } else {
             delete genericModalElement.dataset.onHiddenCallback;
        }

        genericModal.show();
    }

    window.showSuccessModal = function(message) {
        const title = '<i class="bi bi-check-circle-fill text-success me-2"></i> สำเร็จ!';
        const body = `<p class="text-center">${message}</p>`;
        const footer = '<button type="button" class="btn btn-success" data-bs-dismiss="modal">ตกลง</button>';
        showGenericModal(title, body, footer);
    };

    window.showErrorModal = function(message) {
        const title = '<i class="bi bi-x-circle-fill text-danger me-2"></i> เกิดข้อผิดพลาด!';
        const body = `<p class="text-center text-danger">${message}</p>`;
        const footer = '<button type="button" class="btn btn-danger" data-bs-dismiss="modal">ปิด</button>';
        showGenericModal(title, body, footer);
    };

    window.showConfirmModal = function(title, message, confirmCallback) {
        const body = `<p class="text-center">${message}</p>`;
        const footer = `
            <button type="button" class="btn btn-primary" id="confirmActionButton">ยืนยัน</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        `;
        showGenericModal(title, body, footer); // Don't pass hidden callback, handle button click directly

        const confirmBtn = genericModalElement.querySelector('#confirmActionButton');
        let confirmClickHandler; // Declare outside to make it accessible for removal
        confirmClickHandler = function() {
            genericModal.hide();
            confirmCallback(true);
            confirmBtn.removeEventListener('click', confirmClickHandler); // Remove listener after use
        };
        if (confirmBtn) confirmBtn.addEventListener('click', confirmClickHandler);

        // Handle case where modal is closed without clicking confirm
        const handleModalHidden = () => {
            confirmBtn.removeEventListener('click', confirmClickHandler);
            genericModalElement.removeEventListener('hidden.bs.modal', handleModalHidden);
        };
        genericModalElement.addEventListener('hidden.bs.modal', handleModalHidden);
    };

    window.showWarningModal = function(title, message, confirmCallback) {
        const body = `<p class="text-center text-dark">${message}</p>`;
        const footer = `
            <button type="button" class="btn btn-warning" id="warningConfirmButton">ยืนยัน</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        `;
        showGenericModal(title, body, footer); // Don't pass hidden callback, handle button click directly

        const warningConfirmBtn = genericModalElement.querySelector('#warningConfirmButton');
        let warningConfirmClickHandler; // Declare outside
        warningConfirmClickHandler = function() {
            genericModal.hide();
            confirmCallback(true);
            warningConfirmBtn.removeEventListener('click', warningConfirmClickHandler); // Remove listener
        };
        if (warningConfirmBtn) warningConfirmBtn.addEventListener('click', warningConfirmClickHandler);

        // Handle case where modal is closed without clicking confirm
        const handleModalHidden = () => {
            warningConfirmBtn.removeEventListener('click', warningConfirmClickHandler);
            genericModalElement.removeEventListener('hidden.bs.modal', handleModalHidden);
        };
        genericModalElement.addEventListener('hidden.bs.modal', handleModalHidden);
    };


    // --- Handle URL parameters for status messages (from PHP redirects) ---
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const message = urlParams.get('message');

    if (status && message) {
        const decodedMessage = decodeURIComponent(message);
        if (status === 'success') {
            showSuccessModal(decodedMessage);
        } else if (status === 'error') {
            showErrorModal(decodedMessage);
        }
        // Clean the URL to prevent the modal from reappearing on refresh
        history.replaceState({}, document.title, window.location.pathname + window.location.search.replace(/(&|\?)status=[^&]*|&message=[^&]*/g, ''));
    }

    // --- Logic for Add Form Confirmation Modals ---
    function setupAddFormConfirmation(formId, submitBtnId, itemType, nameInputId) {
        const form = document.getElementById(formId);
        const submitBtn = document.getElementById(submitBtnId);
        const nameInput = document.getElementById(nameInputId); // Input field for the item's name

        if (!form || !submitBtn || !nameInput) return;

        submitBtn.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent default button action (which is none, since type="button")

            // Basic client-side validation for required fields before showing modal
            if (!form.checkValidity()) {
                form.reportValidity(); // Show native browser validation messages
                return;
            }

            const itemName = nameInput.value;
            const message = `คุณแน่ใจหรือไม่ที่จะสร้าง ${itemType} <strong>${itemName}</strong> นี้?`;
            window.showConfirmModal(
                `<i class="bi bi-plus-circle-fill text-primary me-2"></i> ยืนยันการสร้าง${itemType}`,
                message,
                function(confirmed) {
                    if (confirmed) {
                        form.submit(); // Programmatically submit the form if confirmed
                    }
                }
            );
        });
    }

    // Initialize add form confirmations
    setupAddFormConfirmation('addBuildingForm', 'submitAddBuilding', 'อาคาร', 'building_name');
    setupAddFormConfirmation('addFacilityForm', 'submitAddFacility', 'สถานที่', 'facility_name');
    setupAddFormConfirmation('addEquipmentForm', 'submitAddEquipment', 'อุปกรณ์', 'equip_name');


    // --- Logic for Status Change Warning Modals ---
    function setupStatusToggleWarning(formId, toggleCheckboxId) {
        const form = document.getElementById(formId);
        const toggleCheckbox = document.getElementById(toggleCheckboxId);
        if (!form || !toggleCheckbox) return;

        // Store original status when the page loads
        const originalCheckedState = toggleCheckbox.checked;
        let statusChangeConfirmed = false; // Flag to prevent multiple modals for the same submit

        form.addEventListener('submit', function(e) {
            // Only intervene if status is changing from 'yes' to 'no' AND it hasn't been confirmed yet
            if (originalCheckedState && !toggleCheckbox.checked && !statusChangeConfirmed) {
                e.preventDefault(); // Stop immediate submission

                const itemType = toggleCheckbox.dataset.itemType; // e.g., 'อาคาร', 'สถานที่', 'อุปกรณ์'
                const itemName = toggleCheckbox.dataset.itemName; // e.g., building_name

                window.showWarningModal(
                    `<i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> ยืนยันการปิดสถานะ${itemType}`,
                    `หากปิดสถานะ${itemType} <strong>${itemName}</strong> นี้ จะทำให้ผู้ใช้ทั่วไปไม่สามารถขอใช้งานได้ คุณแน่ใจหรือไม่?`,
                    function(confirmed) {
                        if (confirmed) {
                            statusChangeConfirmed = true; // Set flag
                            form.submit(); // Programmatically submit the form
                        } else {
                            toggleCheckbox.checked = true; // Revert checkbox if cancelled
                            statusChangeConfirmed = false; // Reset flag
                            // Update label text back if the state was reverted
                            const label = toggleCheckbox.nextElementSibling;
                            if (label) {
                                label.textContent = toggleCheckbox.checked ? 'เปิด (พร้อมใช้งาน)' : 'ปิด (ไม่พร้อมใช้งาน)';
                            }
                        }
                    }
                );
            }
            // If already confirmed, or not changing to 'no', or was already 'no', allow submission
        });

        // Add an event listener to update the label text dynamically when the switch is toggled
        toggleCheckbox.addEventListener('change', function() {
            const label = toggleCheckbox.nextElementSibling;
            if (label) {
                label.textContent = this.checked ? 'เปิด (พร้อมใช้งาน)' : 'ปิด (ไม่พร้อมใช้งาน)';
            }
        });
    }

    // Initialize status toggle warnings for edit forms
    setupStatusToggleWarning('editBuildingForm', 'status_building');
    setupStatusToggleWarning('editFacilityForm', 'available_facility');
    setupStatusToggleWarning('editEquipmentForm', 'available_equip');


    // --- Generic Delete Confirmation Modal Logic (unchanged, just ensure it works with new generic modal functions) ---
    const deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
    if (deleteConfirmationModal) {
        deleteConfirmationModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const deleteId = button.getAttribute('data-id');
            const deleteType = button.getAttribute('data-type'); // e.g., 'building', 'facility', 'equipment'
            const deleteName = button.getAttribute('data-name'); // Optional, for better message
            const redirectBuildingId = button.getAttribute('data-redirect-building-id'); // For facility deletion

            const modalBodyMessage = deleteConfirmationModal.querySelector('#deleteModalMessage');
            const deleteForm = deleteConfirmationModal.querySelector('#deleteForm');
            const deleteIdInput = deleteForm.querySelector('#deleteItemId');
            const deleteTypeInput = deleteForm.querySelector('#deleteItemType');
            const redirectBuildingIdInput = deleteForm.querySelector('#redirectBuildingId');

            let messageText = `คุณต้องการที่จะลบ <strong>${deleteName || deleteId}</strong> ใช่หรือไม่? <br>การดำเนินการนี้ไม่สามารถย้อนกลับได้`;
            if (deleteType === 'building') {
                messageText = `คุณต้องการที่จะลบอาคาร <strong>${deleteName || deleteId}</strong> ใช่หรือไม่? <br>การดำเนินการนี้จะลบข้อมูลภายในอาคารและสถานที่ที่เกี่ยวข้องทั้งหมด <strong>(หากมีสถานที่อยู่ภายในอาคาร)</strong> และจะไม่สามารถย้อนกลับได้`;
            } else if (deleteType === 'facility') {
                messageText = `คุณต้องการที่จะลบสถานที่ <strong>${deleteName || deleteId}</strong> ใช่หรือไม่? <br>การดำเนินการนี้จะลบข้อมูลภายในสถานที่ทั้งหมด</strong> และจะไม่สามารถย้อนกลับได้`;
            } else if (deleteType === 'equipment') {
                messageText = `คุณต้องการที่จะลบอุปกรณ์ <strong>${deleteName || deleteId}</strong> ใช่หรือไม่? <br>การดำเนินการนี้จะลบข้อมูลภายในอุปกรณ์ทั้งหมด</strong> และจะไม่สามารถย้อนกลับได้`;
            }

            if (modalBodyMessage) modalBodyMessage.innerHTML = `<p class="details-text">${messageText}</p>`;
            if (deleteIdInput) deleteIdInput.value = deleteId;
            if (deleteTypeInput) deleteTypeInput.value = `delete_${deleteType}`;
            if (redirectBuildingIdInput) {
                 redirectBuildingIdInput.value = redirectBuildingId || '';
            }
        });
    }
});