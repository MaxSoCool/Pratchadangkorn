<?php

function getCalendarEventsAsJson($db_conn, $view_mode = 'buildings', $building_id = null) {
    if (!$db_conn || $db_conn->connect_error) {
        error_log("Calendar Error: Invalid database connection provided.");
        return json_encode([]);
    }
    $events = [];
    $sql = "SELECT p.project_name, b.building_name, b.building_id, f.facility_name, fr.start_date, fr.end_date, fr.start_time, fr.end_time, CONCAT(u.user_THname, ' ', u.user_THsur) AS user_full_name, fr.approve, fr.writed_status, fr.request_date
            FROM facilities_requests fr
            JOIN project p ON fr.project_id = p.project_id
            JOIN facilities f ON fr.facility_id = f.facility_id
            JOIN buildings b ON f.building_id = b.building_id
            JOIN user u ON p.nontri_id = u.nontri_id
            WHERE (fr.approve IN ('อนุมัติ', 'ไม่อนุมัติ') OR fr.writed_status = 'ส่งคำร้องขอ')";

    if ($view_mode === 'building_detail' && !empty($building_id)) {
        $sql .= " AND f.building_id = ?";
        $stmt = $db_conn->prepare($sql);
        $stmt->bind_param("i", $building_id);
    } else {
        $stmt = $db_conn->prepare($sql);
    }
    
    if (!$stmt) {
        error_log("Calendar SQL Prepare Error: " . $db_conn->error);
        return json_encode([]);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $status_text = $row['approve'] ?? $row['writed_status'];
        $event_class = 'event-pending';
        if ($row['approve'] === 'อนุมัติ') $event_class = 'event-approved';
        elseif ($row['approve'] === 'ไม่อนุมัติ') $event_class = 'event-rejected';
        
        $events[] = [
            'title' => $row['facility_name'],
            'start' => $row['start_date'],
            'end'   => date('Y-m-d', strtotime($row['end_date'] . ' +1 day')),
            'className' => $event_class,
            'extendedProps' => [
                'projectName'   => htmlspecialchars($row['project_name']),
                'buildingName' => htmlspecialchars($row['building_id']) . ' ' . htmlspecialchars($row['building_name']),
                'facilityName'  => htmlspecialchars($row['facility_name']),
                'timeRange'     => date('H:i', strtotime($row['start_time'])) . ' - ' . date('H:i', strtotime($row['end_time'])),
                'requestedBy'   => htmlspecialchars($row['user_full_name']),
                'status'        => htmlspecialchars($status_text),
                'requestDate'   => formatThaiDate($row['request_date']),
                'statusClass'   => $event_class 
            ]
        ];
    }
    $stmt->close();
    return json_encode($events);
}

function formatThaiDate($date_str, $include_time = true) {
    if (empty($date_str)) return "-";
    $dt = new DateTime($date_str);
    $thai_months = [
        "", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
        "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
    ];
    $d = (int)$dt->format('j');
    $m = (int)$dt->format('n');
    $y = (int)$dt->format('Y') + 543; 
    $output = "{$d} {$thai_months[$m]} {$y}";
    if ($include_time) {
        $time = $dt->format('H:i');
        $output .= " {$time}";
    }
    return $output;
}

$calendar_mode = $mode ?? 'buildings';
$building_id_for_calendar = ($calendar_mode === 'building_detail' && isset($_GET['building_id'])) ? (int)$_GET['building_id'] : null;
$calendar_events_json = getCalendarEventsAsJson($conn, $calendar_mode, $building_id_for_calendar);
?>

<!-- HTML for the Modal -->
<div class="modal fade" id="calendarModal" tabindex="-1" aria-labelledby="calendarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calendarModalLabel">
                    <i class="bi bi-calendar3"></i> ปฏิทินการขอใช้อาคารสถานที่
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="bookingCalendar"></div>
            </div>
            <!-- Modal Footer -->
            <div class="modal-footer justify-content-start">
                <div class="d-flex align-items-center calendar-des">
                    <span class="me-3"><strong>คำอธิบายสี:</strong></span>
                    <span class="badge event-approved me-2">อนุมัติ</span>
                    <span class="badge event-rejected me-2">ไม่อนุมัติ</span>
                    <span class="badge event-pending me-2">รอดำเนินการ</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('bookingCalendar');
    if (calendarEl) {
        const calendarEvents = <?php echo $calendar_events_json; ?>;

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
                    <div class="fw-bold calendar-pro mb-2">${props.projectName}</div>
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
</script>