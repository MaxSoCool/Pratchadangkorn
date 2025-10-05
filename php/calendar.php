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

    $stmt = null; // กำหนดค่าเริ่มต้นเป็น null

    if ($view_mode === 'building_detail' && !empty($building_id)) {
        $sql .= " AND f.building_id = ?";
        $stmt = $db_conn->prepare($sql);
        // ตรวจสอบว่า prepare สำเร็จก่อนเรียก bind_param
        if ($stmt === false) {
            error_log("Calendar SQL Prepare Error (with building_id): " . $db_conn->error);
            return json_encode([]);
        }
        // เรียก bind_param เฉพาะเมื่อมี placeholder '?'
        if (!$stmt->bind_param("i", $building_id)) {
            error_log("Calendar SQL Bind Param Error (building_id): " . $stmt->error);
            $stmt->close(); // ปิด statement ที่ล้มเหลว
            return json_encode([]);
        }
    } else {
        // สำหรับโหมด 'buildings' หรือ 'building_detail' ที่ไม่มี building_id ที่ถูกต้อง
        $stmt = $db_conn->prepare($sql);
        // ตรวจสอบว่า prepare สำเร็จ
        if ($stmt === false) {
            error_log("Calendar SQL Prepare Error (no building_id): " . $db_conn->error);
            return json_encode([]);
        }
        // ไม่ต้องเรียก bind_param ที่นี่ เพราะไม่มี placeholder '?'
    }
    
    // ที่จุดนี้ $stmt รับประกันว่าเป็นอ็อบเจกต์ mysqli_stmt ที่เตรียมไว้แล้ว หรือฟังก์ชันได้ exit ไปแล้ว
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
    window.calendarEvents = <?php echo $calendar_events_json; ?>;
</script>
<script src="calendar.js"></script>