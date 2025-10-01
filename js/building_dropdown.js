console.log("building_dropdown.js loaded and executing."); // เพิ่มบรรทัดนี้

document.addEventListener('DOMContentLoaded', function() {
    var statusModalElement = document.getElementById('statusModal');
    var statusModal = new bootstrap.Modal(statusModalElement);

    // Check for status parameters in URL
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const message = urlParams.get('message');
    const mainTab = urlParams.get('main_tab');
    const mode = urlParams.get('mode');
    const projectId = urlParams.get('project_id');
    const facilityReId = urlParams.get('facility_re_id');
    const equipReId = urlParams.get('equip_re_id');

    if (status && message) { 
        // Set modal content
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
});

document.addEventListener('DOMContentLoaded', function() {
    const projectIdSelect = document.getElementById('project_id');
    const facilityIdSelect = document.getElementById('facility_id');
    const currentMode = phpCurrentMode;
    const currentMainTab = phpCurrentMainTab;

    console.log("JS Debug: currentMainTab =", currentMainTab);
    console.log("JS Debug: currentMode =", currentMode);
    console.log("JS Debug: projectIdSelect exists =", !!projectIdSelect);
    console.log("JS Debug: facilityIdSelect exists =", !!facilityIdSelect);

    if (currentMainTab === 'user_requests' && projectIdSelect && facilityIdSelect && (currentMode === 'equipments_create' || currentMode === 'equipments_edit')) {
        console.log("JS Debug: All conditions for AJAX load met.");
        function loadFacilitiesForProject(projectId, initialFacilityId = null) {
            facilityIdSelect.innerHTML = '<option value="">-- เลือกสถานที่/อาคาร --</option>';

            if (projectId === "") {
                facilityIdSelect.innerHTML = '<option value="">-- เลือกโครงการเพื่อดูสถานที่ --</option>';
                return;
            }

            fetch(`?main_tab=${currentMainTab}&action=get_facilities_by_project&project_id=${projectId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok ' + response.statusText);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.length === 0) {
                        facilityIdSelect.innerHTML = '<option value="">ไม่พบสถานที่ที่ถูกขอใช้สำหรับโครงการนี้</option>';
                        facilityIdSelect.value = "";
                    } else {
                        data.forEach(facility => {
                            const option = document.createElement('option');
                            option.value = facility.facility_id;
                            option.textContent = facility.facility_name;
                            if (initialFacilityId && facility.facility_id == initialFacilityId) {
                                option.selected = true;
                            }
                            facilityIdSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching facilities:', error);
                    facilityIdSelect.innerHTML = '<option value="">เกิดข้อผิดพลาดในการโหลดสถานที่</option>';
                });
        }

        projectIdSelect.addEventListener('change', function() {
            loadFacilitiesForProject(this.value);
        });

        const initialProjectId = projectIdSelect.value;
        const initialFacilityId = facilityIdSelect.dataset.initialFacilityId;

        if (initialProjectId) {
            loadFacilitiesForProject(initialProjectId, initialFacilityId);
        }
    } else {
        console.warn("JS Debug: Conditions for AJAX load NOT met. Check main_tab, mode, and element existence. Current: mainTab=", currentMainTab, " mode=", currentMode);
    }
});