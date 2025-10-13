// js/admin-modal.js
document.addEventListener('DOMContentLoaded', function() {
    if (typeof bootstrap === 'undefined') {
        console.error("Bootstrap 5 is not loaded. admin-modal.js functionality may be impaired.");
        return;
    }

    // --- Generic Modal Functions (Global for reuse) ---
    const genericModalElement = document.getElementById('genericModal');
    let genericModal;
    if (genericModalElement) {
        genericModal = new bootstrap.Modal(genericModalElement);
    } else {
        console.error("Generic modal element #genericModal not found. Please ensure it's in your HTML.");
    }

    // Make these functions global so user-modal.js can use them
    window.showGenericModal = function(title, body, footerHtml, onHiddenCallback = null) {
        if (!genericModalElement || !genericModal) return;

        const modalTitle = genericModalElement.querySelector('#genericModalLabel');
        const modalBody = genericModalElement.querySelector('#genericModalBody');
        const modalFooter = genericModalElement.querySelector('#genericModalFooter');

        if (modalTitle) modalTitle.innerHTML = title;
        if (modalBody) modalBody.innerHTML = body;
        if (modalFooter) modalFooter.innerHTML = footerHtml;

        // Ensure only one hidden listener at a time
        const oldHiddenCallbackName = genericModalElement.dataset.onHiddenCallback;
        if (oldHiddenCallbackName && window[oldHiddenCallbackName]) {
             genericModalElement.removeEventListener('hidden.bs.modal', window[oldHiddenCallbackName]);
             delete window[oldHiddenCallbackName]; // Clean up old callback
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
    };

    window.showSuccessModal = function(message, onHiddenCallback = null) {
        const title = '<i class="bi bi-check-circle-fill text-success me-2"></i> สำเร็จ!';
        const body = `<p class="text-center">${message}</p>`;
        const footer = '<button type="button" class="btn btn-success" data-bs-dismiss="modal">ตกลง</button>';
        window.showGenericModal(title, body, footer, onHiddenCallback);
    };

    window.showErrorModal = function(message, onHiddenCallback = null) {
        const title = '<i class="bi bi-x-circle-fill text-danger me-2"></i> เกิดข้อผิดพลาด!';
        const body = `<p class="text-center text-danger">${message}</p>`;
        const footer = '<button type="button" class="btn btn-danger" data-bs-dismiss="modal">ปิด</button>';
        window.showGenericModal(title, body, footer, onHiddenCallback);
    };

    window.showConfirmModal = function(title, message, confirmCallback) {
        const body = `<p class="text-center">${message}</p>`;
        const footer = `
            <button type="button" class="btn btn-primary" id="confirmActionButton">ยืนยัน</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        `;
        window.showGenericModal(title, body, footer);

        const confirmBtn = genericModalElement.querySelector('#confirmActionButton');
        let confirmClickHandler = function() {
            genericModal.hide();
            confirmCallback(true);
            confirmBtn.removeEventListener('click', confirmClickHandler);
        };
        if (confirmBtn) confirmBtn.addEventListener('click', confirmClickHandler);

        const handleModalHidden = () => {
            if (confirmBtn) confirmBtn.removeEventListener('click', confirmClickHandler);
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
        window.showGenericModal(title, body, footer);

        const warningConfirmBtn = genericModalElement.querySelector('#warningConfirmButton');
        let warningConfirmClickHandler = function() {
            genericModal.hide();
            confirmCallback(true);
            warningConfirmBtn.removeEventListener('click', warningConfirmClickHandler);
        };
        if (warningConfirmBtn) warningConfirmBtn.addEventListener('click', warningConfirmClickHandler);

        const handleModalHidden = () => {
            if (warningConfirmBtn) warningConfirmBtn.removeEventListener('click', warningConfirmClickHandler);
            genericModalElement.removeEventListener('hidden.bs.modal', handleModalHidden);
        };
        genericModalElement.addEventListener('hidden.bs.modal', handleModalHidden);
    };


    // --- Handle URL parameters for status messages (from PHP redirects) ---
    // This part should remain in admin-modal.js as it's a general notification system
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const message = urlParams.get('message');

    if (status && message) {
        const decodedMessage = decodeURIComponent(message);
        const onHiddenCallback = () => {
             // Clean the URL to prevent the modal from reappearing on refresh
             const currentUrl = new URL(window.location);
             currentUrl.searchParams.delete('status');
             currentUrl.searchParams.delete('message');
             history.replaceState({}, document.title, currentUrl.toString());
        };

        if (status === 'success') {
            window.showSuccessModal(decodedMessage, onHiddenCallback);
        } else if (status === 'error') {
            window.showErrorModal(decodedMessage, onHiddenCallback);
        }
    }


    // --- Status Change Warning for Admin Data View (Admin-specific) ---
    function setupStatusToggleWarning(formId, toggleCheckboxId) {
        const form = document.getElementById(formId);
        const toggleCheckbox = document.getElementById(toggleCheckboxId);
        if (!form || !toggleCheckbox) return;

        const originalCheckedState = toggleCheckbox.checked;
        let statusChangeConfirmed = false;

        form.addEventListener('submit', function(e) {
            if (originalCheckedState && !toggleCheckbox.checked && !statusChangeConfirmed) {
                e.preventDefault();

                const itemType = toggleCheckbox.dataset.itemType;
                const itemName = toggleCheckbox.dataset.itemName;

                window.showWarningModal(
                    `<i class="bi bi-exclamation-triangle-fill text-warning me-2"></i> ยืนยันการปิดสถานะ${itemType}`,
                    `หากปิดสถานะ${itemType} <strong>${itemName}</strong> นี้ จะทำให้ผู้ใช้ทั่วไปไม่สามารถขอใช้งานได้ คุณแน่ใจหรือไม่?`,
                    function(confirmed) {
                        if (confirmed) {
                            statusChangeConfirmed = true;
                            form.submit();
                        } else {
                            toggleCheckbox.checked = true;
                            statusChangeConfirmed = false;
                            const label = toggleCheckbox.nextElementSibling;
                            if (label) {
                                label.textContent = toggleCheckbox.checked ? 'เปิด (พร้อมใช้งาน)' : 'ปิด (ไม่พร้อมใช้งาน)';
                            }
                        }
                    }
                );
            }
        });

        toggleCheckbox.addEventListener('change', function() {
            const label = toggleCheckbox.nextElementSibling;
            if (label) {
                label.textContent = this.checked ? 'เปิด (พร้อมใช้งาน)' : 'ปิด (ไม่พร้อมใช้งาน)';
            }
        });
    }

    // Initialize status toggle warnings (for admin data view only)
    setupStatusToggleWarning('editBuildingForm', 'status_building');
    setupStatusToggleWarning('editFacilityForm', 'available_facility');
    setupStatusToggleWarning('editEquipmentForm', 'available_equip');


    // --- Admin-specific Delete Confirmation Modal Logic ---
    const deleteConfirmationModalElement = document.getElementById('deleteConfirmationModal');
    if (deleteConfirmationModalElement) {
        deleteConfirmationModalElement.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const type = button.getAttribute('data-type');
            const redirectBuildingId = button.getAttribute('data-redirect-building-id'); // For facility deletion

            const deleteForm = document.getElementById('deleteForm');
            const deleteItemTypeInput = document.getElementById('deleteItemType');
            const deleteItemIdInput = document.getElementById('deleteItemId');
            const deleteModalMessage = document.getElementById('deleteModalMessage');
            const redirectBuildingIdInput = document.getElementById('redirectBuildingId');

            if (deleteItemTypeInput) deleteItemTypeInput.value = 'delete_' + type; // e.g., 'delete_building'
            if (deleteItemIdInput) deleteItemIdInput.value = id;
            if (redirectBuildingIdInput) redirectBuildingIdInput.value = redirectBuildingId; // This might be empty for building/equipment

            // Update modal message based on item type
            let message = `คุณแน่ใจหรือไม่ที่ต้องการลบ ${type} <strong>${name}</strong> นี้? การดำเนินการนี้ไม่สามารถย้อนกลับได้`;
            if (type === 'building') {
                message += " และจะลบสถานที่ทั้งหมดที่เกี่ยวข้องกับอาคารนี้ด้วย.";
            }
            if (deleteModalMessage) deleteModalMessage.innerHTML = `<p class="details-text">${message}</p>`;

            // Update modal title
            const modalTitle = deleteConfirmationModalElement.querySelector('.modal-title');
            if (modalTitle) {
                modalTitle.innerHTML = `<h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> ยืนยันการลบ${type}</h5>`;
            }
        });
    }

});