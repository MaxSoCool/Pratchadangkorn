console.log("building_dropdown.js loaded and executing. v2");

document.addEventListener('DOMContentLoaded', function() {
    // --- Modal Status Display Logic (keep as is) ---
    var statusModalElement = document.getElementById('statusModal');
    if (statusModalElement) {
        var statusModal = new bootstrap.Modal(statusModalElement);
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const message = urlParams.get('message');
        const mainTab = urlParams.get('main_tab');
        const mode = urlParams.get('mode');
        const projectId = urlParams.get('project_id');
        const facilityReId = urlParams.get('facility_re_id');
        const equipReId = urlParams.get('equip_re_id');

        if (status && message) {
            statusModalElement.querySelector('.modal-header').className = 'modal-header ' + (status === 'success' ? 'bg-success' : 'bg-danger') + ' text-white';
            statusModalElement.querySelector('.modal-title').innerText = (status === 'success' ? 'สำเร็จ!' : 'ข้อผิดพลาด!');
            statusModalElement.querySelector('.modal-body').innerText = message;
            statusModalElement.querySelector('.modal-footer .btn').className = 'btn ' + (status === 'success' ? 'btn-success' : 'btn-danger');
            statusModal.show();

            let newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
            let paramsToKeep = [];
            if (mainTab) paramsToKeep.push(`main_tab=${mainTab}`);
            if (mode) paramsToKeep.push(`mode=${mode}`);
            if (projectId) paramsToKeep.push(`project_id=${projectId}`);
            if (facilityReId) paramsToKeep.push(`facility_re_id=${facilityReId}`);
            if (equipReId) paramsToKeep.push(`equip_re_id=${equipReId}`);
            if (paramsToKeep.length > 0) {
                newUrl += '?' + paramsToKeep.join('&');
            }
            window.history.replaceState({}, document.title, newUrl);
        }
    }


    // phpCurrentMode and phpCurrentMainTab are assumed to be globally available from PHP injection

    // --- Logic for Equipment Requests (Project -> Facilities) ---
    const equipProjectIdSelect = document.querySelector('form[action*="equipments"] #project_id');
    const equipFacilityIdSelect = document.querySelector('form[action*="equipments"] #facility_id');

    if (phpCurrentMainTab === 'user_requests' && equipProjectIdSelect && equipFacilityIdSelect && (phpCurrentMode === 'equipments_create' || phpCurrentMode === 'equipments_edit')) {
        console.log("JS Debug (Equipment): Initializing Project -> Facilities dropdown logic.");

        function loadEquipFacilitiesForProject(projectId, initialFacilityId = null) {
            equipFacilityIdSelect.innerHTML = '<option value="">-- กำลังโหลดสถานที่... --</option>';
            equipFacilityIdSelect.disabled = true;

            if (projectId === "") {
                equipFacilityIdSelect.innerHTML = '<option value="">-- เลือกโครงการเพื่อดูสถานที่ --</option>';
                return;
            }

            fetch(`?main_tab=${phpCurrentMainTab}&action=get_facilities_by_project&project_id=${projectId}`)
                .then(response => {
                    if (!response.ok) { throw new Error('Network response was not ok ' + response.statusText); }
                    return response.json();
                })
                .then(data => {
                    equipFacilityIdSelect.innerHTML = '<option value="">-- เลือกสถานที่/อาคารที่นำไปใช้งาน --</option>';
                    if (data.length === 0) {
                        equipFacilityIdSelect.innerHTML = '<option value="">ไม่พบสถานที่ที่เคยขอใช้สำหรับโครงการนี้</option>';
                        equipFacilityIdSelect.value = "";
                        equipFacilityIdSelect.disabled = true;
                    } else {
                        data.forEach(facility => {
                            const option = document.createElement('option');
                            option.value = facility.facility_id;
                            option.textContent = facility.facility_name;
                            if (initialFacilityId && facility.facility_id == initialFacilityId) {
                                option.selected = true;
                            }
                            equipFacilityIdSelect.appendChild(option);
                        });
                        equipFacilityIdSelect.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error fetching facilities for equipment request:', error);
                    equipFacilityIdSelect.innerHTML = '<option value="">เกิดข้อผิดพลาดในการโหลดสถานที่</option>';
                    equipFacilityIdSelect.disabled = true;
                });
        }

        equipProjectIdSelect.addEventListener('change', function() {
            loadEquipFacilitiesForProject(this.value);
        });

        const initialEquipProjectId = equipProjectIdSelect.value;
        const initialEquipFacilityId = equipFacilityIdSelect.dataset.initialFacilityId;
        if (initialEquipProjectId) {
            loadEquipFacilitiesForProject(initialEquipProjectId, initialEquipFacilityId);
        } else {
            equipFacilityIdSelect.disabled = true;
        }
    }

    // --- Logic for Building Requests (Building -> Facilities) ---
    const buildingIdSelect = document.querySelector('form[action*="buildings"] #building_id');
    const facilityIdSelect = document.querySelector('form[action*="buildings"] #facility_id');

    if (phpCurrentMainTab === 'user_requests' && buildingIdSelect && facilityIdSelect && (phpCurrentMode === 'buildings_create' || phpCurrentMode === 'buildings_edit')) {
        console.log("JS Debug (Building): Initializing Building -> Facilities dropdown logic.");

        function loadFacilitiesByBuilding(buildingId, facilityToSelect = null) {
            facilityIdSelect.innerHTML = '<option value="">-- กำลังโหลดสถานที่... --</option>';
            facilityIdSelect.disabled = true;

            if (!buildingId) {
                facilityIdSelect.innerHTML = '<option value="">-- เลือกอาคารก่อน --</option>';
                return;
            }

            console.log(`Fetching facilities for building ID: ${buildingId}`);
            fetch(`?main_tab=${phpCurrentMainTab}&action=get_facilities_by_building&building_id=${buildingId}`)
                .then(response => {
                    if (!response.ok) { throw new Error('Network response was not ok ' + response.statusText); }
                    return response.json();
                })
                .then(data => {
                    facilityIdSelect.innerHTML = '<option value="">-- เลือกสถานที่ที่ต้องการขอใช้ --</option>';
                    if (data.length === 0) {
                        facilityIdSelect.innerHTML = '<option value="">ไม่พบสถานที่ในอาคารนี้</option>';
                        facilityIdSelect.disabled = true;
                    } else {
                        data.forEach(facility => {
                            const option = document.createElement('option');
                            option.value = facility.facility_id;
                            option.textContent = facility.facility_name;
                            // Check if this facility should be selected
                            if (facilityToSelect && facility.facility_id == facilityToSelect) {
                                option.selected = true;
                                console.log(`Pre-selecting facility: ${facilityToSelect}`);
                            }
                            facilityIdSelect.appendChild(option);
                        });
                        facilityIdSelect.disabled = false; // Enable dropdown
                    }
                })
                .catch(error => {
                    console.error('Error fetching facilities by building:', error);
                    facilityIdSelect.innerHTML = '<option value="">เกิดข้อผิดพลาดในการโหลดสถานที่</option>';
                    facilityIdSelect.disabled = true;
                });
        }

        buildingIdSelect.addEventListener('change', function() {
            loadFacilitiesByBuilding(this.value);
        });

        // --- IMPROVED INITIAL LOAD LOGIC ---
        function initializeBuildingForm() {
            const currentlySelectedBuilding = buildingIdSelect.value;
            console.log("JS Debug (Building): Initializing form. Currently selected building value:", currentlySelectedBuilding);

            if (currentlySelectedBuilding) {
                let facilityToSelect = null;
                // For edit mode, the primary source is the data attribute from DB
                if (phpCurrentMode === 'buildings_edit') {
                    facilityToSelect = facilityIdSelect.dataset.initialFacilityId;
                }
                // For create mode (after validation error), get it from the new data attribute we added
                else if (phpCurrentMode === 'buildings_create') {
                    facilityToSelect = facilityIdSelect.dataset.initialFacilityIdFromPost;
                }
                
                console.log(`JS Debug (Building): Calling loadFacilitiesByBuilding with building=${currentlySelectedBuilding}, facilityToSelect=${facilityToSelect}`);
                loadFacilitiesByBuilding(currentlySelectedBuilding, facilityToSelect);
            } else {
                facilityIdSelect.disabled = true;
                console.log("JS Debug (Building): No building selected initially. Facility dropdown is disabled.");
            }
        }
        
        // Run the initialization function
        initializeBuildingForm();
    }
});