<?php

function getCalendarEventsAsJson($db_conn, $view_mode = 'buildings', $building_id = null) {
    if (!$db_conn || $db_conn->connect_error) {
        error_log("Calendar Error: Invalid database connection provided.");
        return json_encode([]);
    }
    $events = [];
    $sql = "SELECT p.project_name, b.building_name, b.building_id, f.facility_name, 
                   fr.start_date, fr.end_date, fr.start_time, fr.end_time, 
                   fr.prepare_start_date, fr.prepare_end_date, fr.prepare_start_time, fr.prepare_end_time,
                   CONCAT(u.user_name, ' ', u.user_sur) AS user_full_name, 
                   fr.approve, fr.writed_status, fr.request_date
            FROM facilities_requests fr
            JOIN project p ON fr.project_id = p.project_id
            JOIN facilities f ON fr.facility_id = f.facility_id
            JOIN buildings b ON f.building_id = b.building_id
            JOIN user u ON p.nontri_id = u.nontri_id
            WHERE (fr.approve IN ('อนุมัติ', 'ไม่อนุมัติ') OR fr.writed_status = 'ส่งคำร้องขอ')"; // 'ส่งคำร้องขอ' คือสถานะรอดำเนินการใน DB

    $stmt = null; 

    if ($view_mode === 'building_detail' && !empty($building_id)) {
        $sql .= " AND f.building_id = ?";
        $stmt = $db_conn->prepare($sql);
        if ($stmt === false) {
            error_log("Calendar SQL Prepare Error (with building_id): " . $db_conn->error);
            return json_encode([]);
        }
        if (!$stmt->bind_param("i", $building_id)) {
            error_log("Calendar SQL Bind Param Error (building_id): " . $stmt->error);
            $stmt->close();
            return json_encode([]);
        }
    } else {
        $stmt = $db_conn->prepare($sql);
        if ($stmt === false) {
            error_log("Calendar SQL Prepare Error (no building_id): " . $db_conn->error);
            return json_encode([]);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $status_text = $row['approve'] ?? $row['writed_status'];
        $event_class = 'event-pending';
        if ($row['approve'] === 'อนุมัติ') $event_class = 'event-approved';
        elseif ($row['approve'] === 'ไม่อนุมัติ') $event_class = 'event-rejected';
        // 'ส่งคำร้องขอ' และค่าอื่นๆ ที่ไม่ใช่ 'อนุมัติ'/'ไม่อนุมัติ' จะใช้ event-pending

        // --- แก้ไขส่วนเวลา: ตรวจสอบและฟอร์แมตอย่างปลอดภัย ---
        $usageTimeRange = 'เวลาไม่ระบุ';
        $startTimeObj = strtotime($row['start_time']);
        $endTimeObj = strtotime($row['end_time']);
        if ($startTimeObj !== false && $endTimeObj !== false) {
            $usageTimeRange = date('H:i', $startTimeObj) . ' - ' . date('H:i', $endTimeObj);
        }

        $prepareTimeRange = 'เวลาไม่ระบุ'; // ค่าเริ่มต้น
        $prepareStartTimeObj = strtotime($row['prepare_start_time']);
        $prepareEndTimeObj = strtotime($row['prepare_end_time']);
        if (!empty($row['prepare_start_time']) && !empty($row['prepare_end_time']) && $prepareStartTimeObj !== false && $prepareEndTimeObj !== false) {
            $prepareTimeRange = date('H:i', $prepareStartTimeObj) . ' - ' . date('H:i', $prepareEndTimeObj);
        }
        // --- สิ้นสุดการแก้ไขส่วนเวลา ---
        
        // ข้อมูลพื้นฐานที่ใช้ร่วมกันใน popover
        $common_props = [
            'projectName'           => htmlspecialchars($row['project_name']),
            'buildingName'          => htmlspecialchars($row['building_id']) . ' ' . htmlspecialchars($row['building_name']),
            'facilityName'          => htmlspecialchars($row['facility_name']),
            'requestedBy'           => htmlspecialchars($row['user_full_name']),
            'requestDate'           => formatThaiDate($row['request_date'], true),
            'status'                => htmlspecialchars($status_text),
            'statusClass'           => $event_class,
            
            // Raw dates/times for usage period
            'usageStartDateRaw'     => $row['start_date'],
            'usageEndDateRaw'       => $row['end_date'],
            'usageTimeRange'        => $usageTimeRange, // ใช้ค่าที่แก้ไขแล้ว
            
            // Raw dates/times for preparation period
            'prepareStartDateRaw'   => $row['prepare_start_date'],
            'prepareEndDateRaw'     => $row['prepare_end_date'],
            'prepareTimeRange'      => $prepareTimeRange, // ใช้ค่าที่แก้ไขแล้ว
        ];

        // 1. Event สำหรับช่วงเวลาใช้งานจริง
        $events[] = [
            'title'     => 'ใช้งาน: ' . $row['facility_name'], // เพิ่มคำอธิบาย "ใช้งาน:"
            'start'     => $row['start_date'],
            'end'       => date('Y-m-d', strtotime($row['end_date'] . ' +1 day')), // FullCalendar end date is exclusive
            'className' => $event_class,
            'extendedProps' => array_merge($common_props, [
                'isPreparationEvent' => false,
            ])
        ];

        // 2. Event สำหรับช่วงเวลาเตรียมการ (ถ้ามีข้อมูล)
        if (!empty($row['prepare_start_date']) && !empty($row['prepare_end_date']) && $row['prepare_start_date'] !== '0000-00-00') { // เพิ่มเช็ค 0000-00-00
            $events[] = [
                'title'     => 'เตรียม: ' . $row['facility_name'], // เพิ่มคำอธิบาย "เตรียม:"
                'start'     => $row['prepare_start_date'],
                'end'       => date('Y-m-d', strtotime($row['prepare_end_date'] . ' +1 day')), // FullCalendar end date is exclusive
                'className' => 'event-preparation', // Class ใหม่สำหรับ event เตรียมการ
                'extendedProps' => array_merge($common_props, [
                    'isPreparationEvent' => true,
                ])
            ];
        }
    }
    $stmt->close();
    return json_encode($events);
}

function formatThaiDate($date_str, $include_time = true) {
    if (empty($date_str) || $date_str == '0000-00-00' || $date_str == '0000-00-00 00:00:00') return "-";
    
    // ตรวจสอบความถูกต้องของวันที่ก่อนสร้าง DateTime object
    try {
        $dt = new DateTime($date_str);
    } catch (Exception $e) {
        error_log("Invalid date string for formatThaiDate: " . $date_str . " Error: " . $e->getMessage());
        return "-";
    }

    $thai_months = [
        "", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
        "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
    ];
    $d = (int)$dt->format('j');
    $m = (int)$dt->format('n');
    $y = (int)$dt->format('Y') + 543; 
    $output = "{$d} {$thai_months[$m]} {$y}";
    if ($include_time && $dt->format('H:i') !== '00:00') {
        $time = $dt->format('H:i');
        $output .= " เวลา {$time}";
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
            <div class="modal-body list-text">
                <div id="bookingCalendar"></div>
            </div>
            <!-- Modal Footer -->
            <div class="modal-footer justify-content-start">
                <div class="d-flex align-items-center calendar-des">
                    <span class="me-3"><strong>คำอธิบายสี:</strong></span>
                    <span class="badge event-approved me-2">อนุมัติ</span>
                    <span class="badge event-rejected me-2">ไม่อนุมัติ</span>
                    <span class="badge event-pending me-2">ส่งคำร้อง / รอดำเนินการ</span> <!-- แก้ไขข้อความ -->
                    <span class="badge event-preparation me-2">เตรียมการ</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.calendarEvents = <?php echo $calendar_events_json; ?>;
</script>
<script src="../js/calendar.js"></script>