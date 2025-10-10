document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('bookingCalendar');
    if (calendarEl) {

        // Helper function for Thai date formatting in JavaScript
        function formatThaiDateJS(dateStr) {
            if (!dateStr || dateStr === '0000-00-00' || dateStr.toLowerCase() === 'null') {
                return '-';
            }
            const date = new Date(dateStr + 'T00:00:00'); // Add T00:00:00 to ensure correct timezone interpretation
            if (isNaN(date.getTime())) { // Check for "Invalid Date"
                return '-';
            }
            const options = {
                day: 'numeric',
                month: 'short',
                year: 'numeric'
            };
            // Use a specific locale for formatting, e.g., 'th-TH' for Thai
            const formattedDate = date.toLocaleDateString('th-TH', options);
            // Convert year to Buddhist calendar
            const year = date.getFullYear();
            const thaiYear = year + 543;
            return formattedDate.replace(year.toString(), thaiYear.toString());
        }

        const calendar = new FullCalendar.Calendar(calendarEl, {
            // === การตั้งค่าหลัก ===
            initialView: 'dayGridMonth',
            locale: 'th',
            dayMaxEvents: true, // แสดง +more เมื่อมี event เยอะเกินไป
            
            // === Toolbar ที่สวยงามขึ้น ===
            headerToolbar: {
                left: 'prev,today',
                center: 'title',
                right: 'next'
            },

            // === ทำให้ปุ่มดูเหมือน Bootstrap ===
            buttonText: {
                today:    'วันนี้',
                month:    'เดือน',
                week:     'สัปดาห์',
                list:     'รายการ'
            },
            
            // === Events Data ===
            events: calendarEvents,

            // === การจัดการเมื่อ Event ถูก Render (สำคัญที่สุด) ===
            eventDidMount: function(info) {
                const props = info.event.extendedProps;
                
                // Format dates for display
                const usageStartDateFormatted = formatThaiDateJS(props.usageStartDateRaw);
                const usageEndDateFormatted = formatThaiDateJS(props.usageEndDateRaw);
                const prepareStartDateFormatted = formatThaiDateJS(props.prepareStartDateRaw);
                const prepareEndDateFormatted = formatThaiDateJS(props.prepareEndDateRaw);

                // สร้าง HTML Content สำหรับ Popover
                let contentHtml = `
                    <div class="fw-bold calendar-pro mb-2 inbox-text">${props.projectName}</div>
                    <p class="mb-1"><i class="bi bi-building me-2"></i><strong>อาคาร:</strong> ${props.buildingName}</p>
                    <p class="mb-1"><i class="bi bi-geo-alt-fill me-2"></i><strong>สถานที่:</strong> ${props.facilityName}</p>
                    <p class="mb-1"><i class="bi bi-person-fill me-2"></i><strong>ยื่นโดย:</strong> ${props.requestedBy}</p>
                    <p class="mb-1"><i class="bi bi-calendar-plus-fill me-2"></i><strong>วันที่ยื่นคำร้องขอ:</strong> ${props.requestDate}</p>
                    <p class="mb-0"><i class="bi bi-clipboard-check-fill me-2"></i><strong>สถานะ:</strong> <span class="badge ${props.statusClass}">${props.status}</span></p>
                    <hr class="my-2">
                `;

                // ส่วนของช่วงเวลาใช้งาน
                contentHtml += `
                    <div class="calendar-period-title mb-1">ช่วงเวลาใช้งาน:</div>
                    <p class="mb-1"><i class="bi bi-calendar-event me-2"></i><strong>วันที่:</strong> ${usageStartDateFormatted} ถึง ${usageEndDateFormatted}</p>
                    <p class="mb-1"><i class="bi bi-clock-fill me-2"></i><strong>เวลา:</strong> ${props.usageTimeRange || 'เวลาไม่ระบุ'}</p> <!-- ใช้ค่าที่แก้ไขจาก PHP หรือ 'เวลาไม่ระบุ' -->
                `;

                // ส่วนของช่วงเวลาเตรียมงาน (แสดงเฉพาะเมื่อมีข้อมูล)
                if (props.prepareStartDateRaw && props.prepareStartDateRaw !== '0000-00-00') {
                    contentHtml += `
                        <hr class="my-2">
                        <div class="calendar-period-title mb-1">ช่วงเวลาเตรียมงาน:</div>
                        <p class="mb-1"><i class="bi bi-calendar-event me-2"></i><strong>วันที่:</strong> ${prepareStartDateFormatted} ถึง ${prepareEndDateFormatted}</p>
                        <p class="mb-1"><i class="bi bi-clock-fill me-2"></i><strong>เวลา:</strong> ${props.prepareTimeRange || 'เวลาไม่ระบุ'}</p> <!-- ใช้ค่าที่แก้ไขจาก PHP หรือ 'เวลาไม่ระบุ' -->
                    `;
                }

                // สร้าง Popover ด้วย Bootstrap 5
                info.el.popover = new bootstrap.Popover(info.el, {
                    title: 'รายละเอียดการขอใช้',
                    content: contentHtml,
                    trigger: 'hover',
                    placement: 'auto',
                    container: 'body',
                    html: true,
                    sanitize: false,
                    customClass: 'calendar-popover'
                });
            }
        });

        const calendarModal = document.getElementById('calendarModal');
        calendarModal.addEventListener('shown.bs.modal', function () {
            // หน่วงเวลาเล็กน้อยเพื่อให้ Modal ขยายตัวเสร็จก่อน Render
            setTimeout(function() {
                calendar.render();
                calendar.updateSize(); // ปรับขนาดให้พอดีกับ Modal
            }, 10); 
        });
    }
});