document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('bookingCalendar');
    if (calendarEl) {

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
                
                // สร้าง HTML Content สำหรับ Popover
                const contentHtml = `
                    <div class="fw-bold calendar-pro mb-2 inbox-text">${props.projectName}</div>
                    <p class="mb-1"><i class="bi bi-building me-2"></i><strong>อาคาร:</strong> ${props.buildingName}</p>
                    <p class="mb-1"><i class="bi bi-geo-alt-fill me-2"></i><strong>สถานที่:</strong> ${props.facilityName}</p>
                    <p class="mb-1"><i class="bi bi-clock-fill me-2"></i><strong>เวลา:</strong> ${props.timeRange}</p>
                    <p class="mb-1"><i class="bi bi-person-fill me-2"></i><strong>ยื่นโดย:</strong> ${props.requestedBy}</p>
                    <p class="mb-1"><i class="bi bi-calendar-plus-fill me-2"></i><strong>วันที่ยื่นคำร้องขอ:</strong> ${props.requestDate}</p>
                    <p class="mb-0"><i class="bi bi-clipboard-check-fill me-2"></i><strong>สถานะ:</strong> <span class="badge ${props.statusClass}">${props.status}</span></p>
                `;

                // สร้าง Popover ด้วย Bootstrap 5
                info.el.popover = new bootstrap.Popover(info.el, {
                    title: 'รายละเอียดการขอใช้',
                    content: contentHtml,
                    trigger: 'hover',
                    placement: 'auto',
                    container: 'body',
                    html: true,
                    sanitize: false,
                    customClass: 'calendar-popover' // เพิ่ม custom class เผื่อต้องการสไตล์เพิ่มเติม
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