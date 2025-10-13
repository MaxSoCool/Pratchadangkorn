// js/user-modal.js
document.addEventListener('DOMContentLoaded', function() {
    // ตรวจสอบว่าฟังก์ชัน showXModal ที่อยู่ใน admin-modal.js ถูกโหลดแล้ว
    if (typeof window.showSuccessModal !== 'function') {
        console.error("Common modal functions (showSuccessModal, etc.) are not loaded. Ensure admin-modal.js is loaded before user-modal.js.");
        return;
    }

    // --- Logic for Add/Edit Form Confirmation Modals (User-specific) ---
    // Function definition
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

    // --- Delete/Cancel Confirmation Modals Logic (User-specific) ---
    // Function definition
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

    // --- Logic for handling file removal in edit project form (User-specific) ---
    document.querySelectorAll('.remove-existing-file').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.input-group').remove();
            // The hidden input that stores retained files will automatically exclude this one
        });
    });

    // --- Calls for user-specific modals ---

    // Initialize add/edit form confirmations for user-project-page.php
    setupFormConfirmation('createProjectForm', 'submitCreateProject', 'สร้าง', 'โครงการ', 'project_name');
    setupFormConfirmation('editProjectForm', 'submitEditProject', 'แก้ไข', 'โครงการ', 'project_name');

    setupFormConfirmation('createBuildingForm', 'submitCreateBuilding', 'สร้าง', 'คำร้องขอสถานที่', 'project_id');
    setupFormConfirmation('editBuildingForm', 'submitEditBuilding', 'แก้ไข', 'คำร้องขอสถานที่', 'project_id');

    setupFormConfirmation('createEquipmentForm', 'submitCreateEquipment', 'สร้าง', 'คำร้องขออุปกรณ์', 'project_id');
    setupFormConfirmation('editEquipmentForm', 'submitEditEquipment', 'แก้ไข', 'คำร้องขออุปกรณ์', 'project_id');

    // Initialize Delete/Cancel Confirmation Modals Logic for user-project-page.php
    setupActionModal('deleteProjectModal', 'deleteProjectForm', 'delete_project_id', 'delete_action_project', 'delete_project', 'data-project-name', 'data-type');
    setupActionModal('cancelProjectModal', 'cancelProjectForm', 'cancel_project_id', 'cancel_action_project', 'cancel_project', 'data-project-name', 'data-type');

    setupActionModal('deleteBuildingRequestModal', 'deleteBuildingRequestForm', 'delete_fr_id', 'delete_action_fr', 'delete_building_request', 'data-facility-name', 'data-type');
    setupActionModal('cancelBuildingRequestModal', 'cancelBuildingRequestForm', 'cancel_fr_id', 'cancel_action_fr', 'cancel_building_request', 'data-facility-name', 'data-type');

    setupActionModal('deleteEquipmentRequestModal', 'deleteEquipmentRequestForm', 'delete_er_id', 'delete_action_er', 'delete_equipment_request', 'data-equip-name', 'data-type');
    setupActionModal('cancelEquipmentRequestModal', 'cancelEquipmentRequestForm', 'cancel_er_id', 'cancel_action_er', 'cancel_equipment_request', 'data-equip-name', 'data-type');

});