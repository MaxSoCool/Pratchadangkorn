// js/admin-modal.js
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
    }

    window.showSuccessModal = function(message, onHiddenCallback = null) {
        const title = '<i class="bi bi-check-circle-fill text-success me-2"></i> สำเร็จ!';
        const body = `<p class="text-center">${message}</p>`;
        const footer = '<button type="button" class="btn btn-success" data-bs-dismiss="modal">ตกลง</button>';
        showGenericModal(title, body, footer, onHiddenCallback);
    };

    window.showErrorModal = function(message, onHiddenCallback = null) {
        const title = '<i class="bi bi-x-circle-fill text-danger me-2"></i> เกิดข้อผิดพลาด!';
        const body = `<p class="text-center text-danger">${message}</p>`;
        const footer = '<button type="button" class="btn btn-danger" data-bs-dismiss="modal">ปิด</button>';
        showGenericModal(title, body, footer, onHiddenCallback);
    };

    window.showConfirmModal = function(title, message, confirmCallback) {
        const body = `<p class="text-center">${message}</p>`;
        const footer = `
            <button type="button" class="btn btn-primary" id="confirmActionButton">ยืนยัน</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        `;
        showGenericModal(title, body, footer);

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
        showGenericModal(title, body, footer);

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
            showSuccessModal(decodedMessage, onHiddenCallback);
        } else if (status === 'error') {
            showErrorModal(decodedMessage, onHiddenCallback);
        }
    }

    // --- Logic for Add/Edit Form Confirmation Modals ---
    function setupFormConfirmation(formId, submitBtnId, actionType, itemType, nameElementId, additionalNameData = {}) {
        const form = document.getElementById(formId);
        const submitBtn = document.getElementById(submitBtnId);

        if (!form || !submitBtn) return;

        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Perform client-side validation first
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            let itemName = '';
            const nameElement = document.getElementById(nameElementId);

            if (nameElement && nameElement.tagName === 'SELECT') {
                itemName = nameElement.options[nameElement.selectedIndex].text;
            } else if (nameElement) {
                itemName = nameElement.value;
            } else {
                const h2Element = form.closest('.form-section')?.querySelector('h2');
                if (h2Element) {
                    itemName = h2Element.textContent.split(': ')[1] || h2Element.textContent;
                }
            }
            
            // For requests, prepend the project name if it's available and relevant
            if (itemType.includes('คำร้องขอ') && nameElementId === 'project_id' && nameElement) {
                 const selectedProjectText = nameElement.options[nameElement.selectedIndex].text;
                 itemName = `โครงการ "${selectedProjectText}"`;
                 if (itemType === 'คำร้องขอสถานที่') {
                    const facilitySelect = document.getElementById('facility_id');
                    if (facilitySelect && facilitySelect.selectedIndex > 0) {
                        itemName += ` สำหรับสถานที่ "${facilitySelect.options[facilitySelect.selectedIndex].text}"`;
                    }
                 } else if (itemType === 'คำร้องขออุปกรณ์') {
                    const equipSelect = document.getElementById('equip_id');
                    if (equipSelect && equipSelect.selectedIndex > 0) {
                        itemName += ` สำหรับอุปกรณ์ "${equipSelect.options[equipSelect.selectedIndex].text}"`;
                    }
                 }
            }


            const title = `<i class="bi bi-question-circle-fill text-info me-2"></i> ยืนยันการ${actionType}${itemType}`;
            const message = `คุณแน่ใจหรือไม่ที่จะ${actionType}${itemType} <strong>${itemName}</strong> นี้?`;

            window.showConfirmModal(title, message, function(confirmed) {
                if (confirmed) {
                    // Create a temporary hidden input to ensure the 'action' value is sent
                    const hiddenActionInput = document.createElement('input');
                    hiddenActionInput.type = 'hidden';
                    hiddenActionInput.name = 'action'; // PHP will look for $_POST['action']
                    hiddenActionInput.value = submitBtn.value; // The value from the button
                    form.appendChild(hiddenActionInput);

                    form.submit(); // Programmatically submit the form
                }
            });
        });
    }

    // Initialize add/edit form confirmations for user-project-page.php
    setupFormConfirmation('createProjectForm', 'submitCreateProject', 'สร้าง', 'โครงการ', 'project_name');
    setupFormConfirmation('editProjectForm', 'submitEditProject', 'แก้ไข', 'โครงการ', 'project_name');

    setupFormConfirmation('createBuildingForm', 'submitCreateBuilding', 'สร้าง', 'คำร้องขอสถานที่', 'project_id');
    setupFormConfirmation('editBuildingForm', 'submitEditBuilding', 'แก้ไข', 'คำร้องขอสถานที่', 'project_id');

    setupFormConfirmation('createEquipmentForm', 'submitCreateEquipment', 'สร้าง', 'คำร้องขออุปกรณ์', 'project_id');
    setupFormConfirmation('editEquipmentForm', 'submitEditEquipment', 'แก้ไข', 'คำร้องขออุปกรณ์', 'project_id');


    // --- Status Change Warning for Admin Data View (from original) ---
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

    // Initialize status toggle warnings (for admin data view only, if present)
    setupStatusToggleWarning('editBuildingForm', 'status_building'); // Assumes these forms and elements exist
    setupStatusToggleWarning('editFacilityForm', 'available_facility');
    setupStatusToggleWarning('editEquipmentForm', 'available_equip');


    // --- Delete/Cancel Confirmation Modals Logic (for user-project-page.php) ---
    function setupActionModal(modalId, formId, itemIdInputId, actionInputId, action, nameDataAttribute, typeDataAttribute) {
        const modalElement = document.getElementById(modalId);
        if (!modalElement) return;

        modalElement.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const itemId = button.getAttribute('data-id');
            const itemName = button.getAttribute(nameDataAttribute);
            const itemType = button.getAttribute(typeDataAttribute);

            const form = document.getElementById(formId);
            const itemIdInput = form.querySelector(`#${itemIdInputId}`);
            const actionInput = form.querySelector(`#${actionInputId}`);

            if (itemIdInput) itemIdInput.value = itemId;
            if (actionInput) actionInput.value = action;

            const modalTitleElement = modalElement.querySelector('.modal-title');
            const modalBodyElement = modalElement.querySelector('.modal-body');

            if (modalTitleElement && modalBodyElement) {
                if (action.includes('delete')) {
                    modalTitleElement.innerHTML = `<h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> ยืนยันการลบ${itemType}</h5>`;
                    let bodyText = `คุณแน่ใจหรือไม่ว่าต้องการลบ${itemType} <strong>${itemName}</strong> นี้? การดำเนินการนี้ไม่สามารถย้อนกลับได้.`;
                    if (itemType === 'โครงการ') {
                        bodyText += " และจะลบคำร้องขอที่เกี่ยวข้องทั้งหมดด้วย.";
                    }
                    modalBodyElement.innerHTML = `<p class="details-text">${bodyText}</p>`;
                } else if (action.includes('cancel')) {
                    modalTitleElement.innerHTML = `<h5 class="modal-title text-dark"><i class="bi bi-x-circle"></i> ยืนยันการยกเลิก${itemType}</h5>`;
                    let bodyText = `คุณแน่ใจหรือไม่ว่าต้องการยกเลิก${itemType} <strong>${itemName}</strong> นี้? การดำเนินการนี้จะเปลี่ยนสถานะ${itemType}เป็น "ยกเลิก".`;
                    if (itemType === 'โครงการ') {
                        bodyText += " และจะยกเลิกคำร้องขอที่เกี่ยวข้องทั้งหมดด้วย.";
                    }
                    modalBodyElement.innerHTML = `<p class="details-text">${bodyText}</p>`;
                }
            }
        });
    }

    // Project Delete Modal
    setupActionModal('deleteProjectModal', 'deleteProjectForm', 'delete_project_id', 'delete_action_project', 'delete_project', 'data-project-name', 'data-type');
    // Project Cancel Modal
    setupActionModal('cancelProjectModal', 'cancelProjectForm', 'cancel_project_id', 'cancel_action_project', 'cancel_project', 'data-project-name', 'data-type');

    // Building Request Delete Modal
    setupActionModal('deleteBuildingRequestModal', 'deleteBuildingRequestForm', 'delete_fr_id', 'delete_action_fr', 'delete_building_request', 'data-facility-name', 'data-type');
    // Building Request Cancel Modal
    setupActionModal('cancelBuildingRequestModal', 'cancelBuildingRequestForm', 'cancel_fr_id', 'cancel_action_fr', 'cancel_building_request', 'data-facility-name', 'data-type');

    // Equipment Request Delete Modal
    setupActionModal('deleteEquipmentRequestModal', 'deleteEquipmentRequestForm', 'delete_er_id', 'delete_action_er', 'delete_equipment_request', 'data-equip-name', 'data-type');
    // Equipment Request Cancel Modal
    setupActionModal('cancelEquipmentRequestModal', 'cancelEquipmentRequestForm', 'cancel_er_id', 'cancel_action_er', 'cancel_equipment_request', 'data-equip-name', 'data-type');


    // Logic for handling file removal in edit project form
    document.querySelectorAll('.remove-existing-file').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.input-group').remove();
            // The hidden input that stores retained files will automatically exclude this one
        });
    });

});