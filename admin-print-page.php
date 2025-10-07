<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'เจ้าหน้าที่') {
    header("Location: login.php");
    exit();
}

$user_id = htmlspecialchars($_SESSION['staff_id'] ?? null);
$staff_name = htmlspecialchars($_SESSION['user_display_name'] ?? null);
$staff_sur = htmlspecialchars($_SESSION['user_display_sur'] ?? null);
$user_role = htmlspecialchars($_SESSION['role'] ?? null);

include 'database/database.php';

if (!function_exists('getThaiMonname')) {
    function getThaiMonname($month_num) {
        $thai_months_full = [
            '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
            '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
            '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
        ];
        return $thai_months_full[sprintf('%02d', $month_num)] ?? '';
    }
}

if (!function_exists('formatThaiDatePartForPrint')) {
    function formatThaiDatePartForPrint($date_str, $part) {
        if (!$date_str || $date_str === '0000-00-00 00:00:00' || $date_str === '0000-00-00') {
            return '';
        }
        $dt = new DateTime($date_str);
        if ($part === 'day') return $dt->format('d');
        if ($part === 'month') return getThaiMonname($dt->format('m'));
        if ($part === 'year') return $dt->format('Y') + 543; // Buddhist year
        if ($part === 'time') return $dt->format('H:i');
        return '';
    }
}

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$request_type = isset($_GET['type']) ? $_GET['type'] : ''; // 'facility' or 'equipment'
$request_data = null;
$errors = [];

if ($request_id > 0) {
    if ($request_type === 'facility') {
        $sql = "SELECT
                    fr.facility_re_id AS request_id, fr.project_id, fr.prepare_start_time, fr.prepare_end_time,
                    fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time,
                    fr.start_date AS main_start_date, fr.end_date AS main_end_date, fr.agree,
                    fr.writed_status, fr.request_date, fr.approve, fr.approve_date, fr.approve_detail,
                    f.facility_name, f.building_id, b.building_name,
                    p.project_name, p.project_des, p.activity_type_id, at.activity_type_name, p.advisor_name,
                    CONCAT(u.user_name, ' ', u.user_sur) AS user_name, u.nontri_id,
                    ut.user_type_name AS requester_role, u.position AS requester_position, /* เปลี่ยน u.role เป็น ut.user_type_name */
                    p.phone_num AS user_phone_num, fd.fa_de_name,
                    CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name, s.staff_id
                FROM facilities_requests fr
                JOIN facilities f ON fr.facility_id = f.facility_id
                JOIN buildings b ON f.building_id = b.building_id
                JOIN project p ON fr.project_id = p.project_id
                JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                JOIN user u ON p.nontri_id = u.nontri_id
                JOIN user_type ut ON u.user_type_id = ut.user_type_id /* เพิ่ม JOIN กับ user_type */
                JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
                LEFT JOIN staff s ON fr.staff_id = s.staff_id
                WHERE fr.facility_re_id = ?";
    } elseif ($request_type === 'equipment') {
        $sql = "SELECT
                    er.equip_re_id AS request_id, er.project_id, er.start_date AS main_start_date, er.end_date AS main_end_date, er.quantity, er.transport,
                    er.writed_status, er.request_date, er.approve, er.approve_date, er.approve_detail, er.agree,
                    e.equip_name, e.measure,
                    p.project_name, p.project_des, p.activity_type_id, at.activity_type_name, p.advisor_name,
                    CONCAT(u.user_name, ' ', u.user_sur) AS user_name, u.nontri_id,
                    ut.user_type_name AS requester_role, u.position AS requester_position, /* เปลี่ยน u.role เป็น ut.user_type_name */
                    p.phone_num AS user_phone_num, fd.fa_de_name,
                    COALESCE(f.facility_name, 'ไม่ระบุ') AS facility_name_used,
                    CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name, s.staff_id
                FROM equipments_requests er
                JOIN equipments e ON er.equip_id = e.equip_id
                JOIN project p ON er.project_id = p.project_id
                LEFT JOIN facilities f ON er.facility_id = f.facility_id
                JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                JOIN user u ON p.nontri_id = u.nontri_id
                JOIN user_type ut ON u.user_type_id = ut.user_type_id /* เพิ่ม JOIN กับ user_type */
                JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
                LEFT JOIN staff s ON er.staff_id = s.staff_id
                WHERE er.equip_re_id = ?";
    } else {
        $errors[] = "ประเภทคำร้องไม่ถูกต้อง (ต้องเป็น 'facility' หรือ 'equipment').";
        $request_type = null; // Invalidate type to prevent further processing
    }

    if ($request_type && empty($errors)) {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $request_data = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        } else {
            error_log("Failed to prepare statement for admin-print-page.php: " . $conn->error);
            $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $conn->error;
        }
    }
} else {
    $errors[] = "ไม่พบรหัสคำร้อง";
}

$conn->close();

if (empty($request_data)) {
    $request_data = [];
    if (empty($errors)) { // Only add if no other errors already
        $errors[] = "ไม่พบข้อมูลสำหรับคำร้องนี้";
    }
}

// Extract and prepare data for the form (common for both types, or specifically for each)
$request_dt = new DateTime($request_data['request_date'] ?? 'now');
$requester_name = htmlspecialchars($request_data['user_name'] ?? '');
$requester_role = htmlspecialchars($request_data['requester_role'] ?? ''); // ดึงบทบาทผู้ขอจาก ut.user_type_name
$requester_position = htmlspecialchars($request_data['requester_position'] ?? ''); // ดึงตำแหน่งผู้ขอ
$faculty_name = htmlspecialchars($request_data['fa_de_name'] ?? ''); // ใช้สำหรับคณะ/หน่วยงาน
$requester_phone = htmlspecialchars($request_data['user_phone_num'] ?? '');
$project_name = htmlspecialchars($request_data['project_name'] ?? '');
$project_description = htmlspecialchars($request_data['project_des'] ?? '');
$activity_type_name = htmlspecialchars($request_data['activity_type_name'] ?? '');
$agree_reuse = $request_data['agree'] ?? null;

// Dates/Times for Facilities Request
$prepare_start_date = $request_data['prepare_start_date'] ?? null;
$prepare_end_date = $request_data['prepare_end_date'] ?? null;
$prepare_start_time = $request_data['prepare_start_time'] ?? null;
$prepare_end_time = $request_data['prepare_end_time'] ?? null;
$event_start_time = $request_data['start_time'] ?? null;
$event_end_time = $request_data['end_time'] ?? null;

$facility_name_display = htmlspecialchars($request_data['facility_name'] ?? ''); // For facility requests
$building_name_display = htmlspecialchars($request_data['building_name'] ?? ''); // For facility requests

// Dates/Times & specific fields for Equipment Request
$equip_start_date = $request_data['main_start_date'] ?? null; // For equipment requests
$equip_end_date = $request_data['main_end_date'] ?? null; // For equipment requests
$equip_name_display = htmlspecialchars($request_data['equip_name'] ?? '');
$measure_display = htmlspecialchars($request_data['measure'] ?? '');
$quantity_display = htmlspecialchars($request_data['quantity'] ?? '');
$facility_name_used_display = htmlspecialchars($request_data['facility_name_used'] ?? ''); // Location for equipment
$transport_option = $request_data['transport'] ?? null;


// Common dates (main event dates, used for both but named differently in DB)
$main_start_date = $request_data['main_start_date'] ?? null;
$main_end_date = $request_data['main_end_date'] ?? null;


$advisor_name = htmlspecialchars($request_data['advisor_name'] ?? '');

$approve_status = $request_data['approve'] ?? null;
$approve_date_dt = ($request_data['approve_date'] ?? null) ? new DateTime($request_data['approve_date']) : null;
$staff_name_approved = htmlspecialchars($request_data['staff_name'] ?? '');
$approve_detail = htmlspecialchars($request_data['approve_detail'] ?? ''); // Added for both types
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'; ?>
    <!-- REQUIRED: html2pdf.js library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <!-- REQUIRED: Google Fonts - Sarabun -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">

    <style>
        /* Apply Sarabun explicitly to the body for PDF rendering and general page display */
        body {
            font-family: 'Sarabun', sans-serif !important;
            font-size: 9.5pt; /* Base font size, further reduced for A4 fit */
            line-height: 1.3; /* Tighter line height */
            color: #000;
            background-color: #fff;
        }

        .form-container {
            max-width: 210mm; /* A4 width */
            margin: 8mm auto; /* Reduced A4 margins (top/bottom, left/right) */
            border: 1px solid #000; /* Main border for the form */
            padding: 3mm; /* Further reduced padding inside the main border */
            box-sizing: border-box;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px; /* Reduced margin */
            padding-bottom: 2px; /* Reduced padding */
            border-bottom: 1px solid #dee2e6;
            margin: 0 4mm; /* Adjusted inner margins */
        }

        .header .logo-container {
            flex-shrink: 0;
            width: 65px; /* Slightly smaller logo area */
            text-align: left;
            margin-right: 6px; /* Reduced margin */
        }

        .header .logo {
            width: 50px; /* Further smaller logo */
            height: auto;
            display: block;
        }

        .header .title-info {
            flex-grow: 1;
            text-align: center;
            line-height: 1.1; /* Tighter line height */
        }

        .header .title-info h1 {
            font-size: 11.5pt; /* Further adjusted for A4 fit */
            margin: 0;
            font-weight: bold;
        }

        .header .title-info p {
            font-size: 8.5pt; /* Further adjusted for A4 fit */
            margin: 0;
        }

        .header .doc-id {
            flex-shrink: 0;
            width: 65px; /* Slightly smaller doc ID area */
            text-align: right;
            font-weight: bold;
            font-size: 8.5pt; /* Further adjusted for A4 fit */
            white-space: nowrap;
        }

        .section-header-text {
            font-weight: bold;
            font-size: 9.5pt; /* Main section header size */
            margin-top: 6px; /* Reduced margin */
            margin-bottom: 3px; /* Reduced margin */
            padding-bottom: 1px; /* Reduced padding */
            border-bottom: 1px solid #000;
        }

        .section-content {
            padding: 0 3mm; /* Further reduced padding */
        }

        .section-content p, .section-content div {
            margin-top: 3px; /* Reduced margin */
            margin-bottom: 3px; /* Reduced margin */
            line-height: 1.3;
            font-size: 9pt; /* Adjusted for A4 fit */
        }

        /* Specific adjustments for list items in sections */
        .section-content ol li {
            font-size: 9pt; /* Adjusted for A4 fit */
            line-height: 1.25; /* Tighter line height */
            margin-bottom: 2px; /* Reduced margin between list items */
        }

        .form-group .label-text {
            flex-shrink: 0;
            margin-right: 3px; /* Reduced margin */
            white-space: nowrap;
            font-size: 9pt; /* Adjusted for A4 fit */
            line-height: 1.3;
        }

        /* Table styling for equipment list */
        .equipment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px; /* Reduced margin */
            margin-bottom: 10px; /* Reduced margin */
            font-size: 8.5pt; /* Adjusted for A4 fit */
        }
        .equipment-table th, .equipment-table td {
            border: 1px solid #000;
            padding: 1.5px 3px; /* Further reduced padding */
            text-align: center;
            line-height: 1.15; /* Tighter line height */
        }
        .equipment-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .equipment-table td:nth-child(2) {
            text-align: left;
        }

        .option-group {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            flex-grow: 1;
            font-size: 9pt; /* Adjusted for A4 fit */
            line-height: 1.3;
        }
        .option-group .form-check-label {
            line-height: 1.3; /* Ensure label line-height matches */
        }

        .option-item {
            display: flex;
            align-items: baseline;
            margin-right: 10px; /* Reduced margin */
            white-space: nowrap;
        }

        .description-line {
            flex-grow: 1;
            min-height: 1.2em;
            padding: 0 2px;
            font-size: 9pt; /* Adjusted for A4 fit */
            line-height: 1.3;
            white-space: normal;
            word-break: break-word; /* Ensure text wraps */
            text-align: left;
        }

        .signature-area {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 8mm; /* Reduced margin */
            padding-top: 3mm; /* Reduced padding */
            border-top: 1px dotted #000;
            font-size: 9pt; /* Adjusted for A4 fit */
        }

        .signature-box {
            flex: 1;
            min-width: 30%; /* For 3 boxes */
            max-width: 33%; /* For 3 boxes */
            text-align: center;
            margin: 2mm 0.5mm; /* Reduced margin */
        }

        /* Adjust width for 2 boxes */
        .signature-area.two-columns .signature-box {
            min-width: 45%;
            max-width: 50%;
        }

        .signature-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 70px; /* Further reduced min-width */
            padding: 0 1.5mm; /* Reduced padding */
            text-align: center;
            font-size: 9pt; /* Adjusted for A4 fit */
            line-height: 1.3;
            margin-bottom: 1mm; /* Reduced margin */
        }

        .signature-label-text {
            display: block;
            font-size: 8.5pt; /* Adjusted for A4 fit */
            margin-top: 0.3mm; /* Reduced margin */
        }

        .section2-approval {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px; /* Reduced margin */
            font-size: 9pt; /* Adjusted for A4 fit */
        }
        .section2-approval > div {
            flex: 1;
        }
        .approval-options {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            font-size: 9pt; /* Adjusted for A4 fit */
            line-height: 1.3;
        }
        .approval-options .option-item {
            margin-right: 10px; /* Reduced margin */
        }
        .approval-title {
            font-weight: bold;
            margin-bottom: 3px; /* Reduced margin */
            font-size: 9.5pt; /* Adjusted for A4 fit */
        }

        .staff-signature-area {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin-top: 6mm; /* Reduced margin */
            font-size: 9pt; /* Adjusted for A4 fit */
        }
        .staff-signature-box {
            flex: 1;
            min-width: 45%;
            max-width: 50%;
            text-align: center;
            margin: 2mm 0.5mm; /* Reduced margin */
        }
        .staff-signature-box .signature-line {
            margin-bottom: 1mm;
        }
        .staff-signature-box .signature-date-group {
            margin-top: 2mm; /* Reduced margin */
            justify-content: flex-start;
            display: flex;
            align-items: center;
        }

        .notes {
            margin-top: 6mm; /* Reduced margin */
            padding-top: 3mm; /* Reduced padding */
            border-top: 1px solid #ccc;
            font-size: 8.5pt; /* Adjusted for A4 fit */
            line-height: 1.2; /* Tighter line height */
            page-break-inside: avoid; /* Keep notes together */
        }
        .notes ol {
            list-style-type: decimal;
            padding-left: 10mm; /* Reduced padding */
            margin: 0;
        }
        .notes ol li {
            margin-bottom: 0.5mm; /* Reduced margin */
            line-height: 1.2;
        }
        .notes p {
            font-size: 9pt; /* Adjusted for A4 fit */
            font-weight: bold;
            margin-bottom: 2px; /* Reduced margin */
        }
        .notes p:first-child {
            margin-bottom: 2px;
        }

        input[type="radio"].custom-radio-checkbox,
        input[type="checkbox"].custom-radio-checkbox {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            display: inline-block;
            vertical-align: middle;
            width: 10px; /* Further smaller checkbox/radio */
            height: 10px;
            margin-right: 3px; /* Reduced margin */
            border: 1px solid #000;
            box-sizing: border-box;
            position: relative;
            top: -1px;
        }

        input[type="radio"].custom-radio-checkbox:checked::before {
            content: '';
            display: block;
            position: absolute;
            top: 2px;
            left: 2px;
            width: 4px; /* Adjusted inner circle size */
            height: 4px;
            background-color: #000;
            border-radius: 50%;
        }

        input[type="checkbox"].custom-radio-checkbox:checked::before {
            content: '\2713';
            display: block;
            position: absolute;
            top: -3px;
            left: 0px;
            font-size: 10px; /* Adjusted checkmark size */
            color: #000;
            font-weight: bold;
        }

        /* --- Page Break and Printing Adjustments --- */
        .section {
            page-break-inside: avoid; /* Prevent a section from breaking across pages */
            margin-bottom: 8px; /* Add some space between sections */
        }
        /* Force a page break AFTER the first major section (Part 1 for Requester) */
        .section:nth-of-type(1) {
            page-break-after: always;
        }

        /* Styles for PDF loader */
        #pdf-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            color: white;
            flex-direction: column;
            font-size: 1.2em;
        }
        #pdf-loading-overlay .spinner-border {
            width: 3rem;
            height: 3rem;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container mt-4 mb-4">
        <?php if (!empty($errors)): ?>
            <div class="form-container alert alert-danger border-danger text-center" role="alert">
                <h3 class="alert-heading">เกิดข้อผิดพลาดในการแสดงแบบฟอร์ม:</h3>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php else: ?>
            <!-- PDF Loading Overlay -->
            <div id="pdf-loading-overlay" style="display: none;">
                <div class="spinner-border text-light" role="status">
                    <span class="visually-hidden">กำลังสร้าง PDF...</span>
                </div>
                <span>กำลังสร้างเอกสาร PDF... โปรดรอสักครู่</span>
            </div>

            <div class="form-container shadow-sm" id="mainContent">
                <div class="header">
                    <div class="logo-container">
                        <img src="images/ku_print_logo.png" alt="โลโก้ มหาวิทยาลัยเกษตรศาสตร์ วิทยาเขตเฉลิมพระเกียรติ จังหวัดสกลนคร" class="logo">
                    </div>
                    <div class="title-info">
                        <?php if ($request_type === 'facility'): ?>
                            <h1 class="fs-5 fw-bold mb-0">แบบขอใช้อาคาร/สถานที่ (สำหรับ<?php echo ($requester_role === 'บุคลากร') ? 'บุคลากร' : 'นิสิต'; ?>)</h1>
                            <p class="fs-6 mb-0">สำนักงานวิทยาเขต กองบริการกลาง งานอาคารสถานที่และยานพาหนะ</p>
                            <p class="fs-6 mb-0">โทร. 0-4272-5089 โทรสาร 0-4272-5088</p>
                        <?php elseif ($request_type === 'equipment'): ?>
                            <h1 class="fs-5 fw-bold mb-0">แบบขอใช้พัสดุ-อุปกรณ์ (สำหรับ<?php echo ($requester_role === 'บุคลากร') ? 'บุคลากร' : 'นิสิต'; ?>)</h1>
                            <p class="fs-6 mb-0">สำนักงานวิทยาเขต กองบริการกลาง งานอาคารสถานที่และยานพาหนะ</p>
                            <p class="fs-6 mb-0">โทร. 0-4272-5089 โทรสาร 0-4272-5088</p>
                        <?php endif; ?>
                    </div>
                    <div class="doc-id">
                        <?php echo ($request_type === 'facility') ? 'OCC04-08.1' : 'OCC 03-03.03'; ?>
                    </div>
                </div>

                <div class="section mb-4">
                    <div class="section-header-text">ส่วนที่ 1 สำหรับผู้ขอใช้บริการ</div>
                    <div class="section-content">
                        <div class="d-flex justify-content-end mb-2">
                            วันที่ <?php echo formatThaiDatePartForPrint($request_dt->format('Y-m-d'), 'day'); ?>
                            เดือน <?php echo formatThaiDatePartForPrint($request_dt->format('Y-m-d'), 'month'); ?>
                            พ.ศ. <?php echo formatThaiDatePartForPrint($request_dt->format('Y-m-d'), 'year'); ?>
                        </div>
                        <p class="mb-2">เรียน หัวหน้างานอาคารสถานที่และยานพาหนะ</p>
                        <br>

                        <div class="tab">
                            ข้าพเจ้า <?php echo $requester_name; ?>
                            <?php if ($requester_role === 'บุคลากร'): ?>
                                ตำแหน่ง <?php echo $requester_position; ?>
                                หน่วยงาน <?php echo $faculty_name; ?>
                            <?php else: ?>
                                คณะ <?php echo $faculty_name; ?>
                            <?php endif; ?>
                            โทร <?php echo $requester_phone; ?>
                        </div>

                        <?php if ($request_type === 'facility'): ?>
                            <div>
                                มีความประสงค์ขอใช้สถานที่ในโครงการ/งาน/หน่วย <?php echo $project_name; ?>
                            </div>

                            <div>
                                สถานที่ใช้งานคือ <?php echo $facility_name_display; ?>
                                ระหว่างวันที่
                                <?php echo formatThaiDatePartForPrint($main_start_date, 'day'); ?>
                                เดือน <?php echo formatThaiDatePartForPrint($main_start_date, 'month'); ?>
                                พ.ศ. <?php echo formatThaiDatePartForPrint($main_start_date, 'year'); ?>
                                ถึง
                                <?php echo formatThaiDatePartForPrint($main_end_date, 'day'); ?>
                                เดือน <?php echo formatThaiDatePartForPrint($main_end_date, 'month'); ?>
                                พ.ศ. <?php echo formatThaiDatePartForPrint($main_end_date, 'year'); ?>
                            </div>

                            <div>
                                วันที่เตรียมงาน วันที่ <?php echo formatThaiDatePartForPrint($prepare_start_date, 'day'); ?>
                                เดือน <?php echo formatThaiDatePartForPrint($prepare_start_date, 'month'); ?>
                                พ.ศ. <?php echo formatThaiDatePartForPrint($prepare_start_date, 'year'); ?>
                                ถึง
                                <?php echo formatThaiDatePartForPrint($prepare_end_date, 'day'); ?>
                                เดือน <?php echo formatThaiDatePartForPrint($prepare_end_date, 'month'); ?> พ.ศ. <?php echo formatThaiDatePartForPrint($prepare_end_date, 'year'); ?>
                                <br>
                                ระหว่างเวลา <?php echo formatThaiDatePartForPrint($prepare_start_time, 'time'); ?> น. ถึง <?php echo formatThaiDatePartForPrint($prepare_end_time, 'time'); ?> น.
                            </div>

                            <div>
                                วันที่จัดงาน วันที่ <?php echo formatThaiDatePartForPrint($main_start_date, 'day'); ?>
                                เดือน <?php echo formatThaiDatePartForPrint($main_start_date, 'month'); ?>
                                พ.ศ. <?php echo formatThaiDatePartForPrint($main_start_date, 'year'); ?>
                                ถึง
                                <?php echo formatThaiDatePartForPrint($main_end_date, 'day'); ?>
                                เดือน <?php echo formatThaiDatePartForPrint($main_end_date, 'month'); ?>
                                พ.ศ. <?php echo formatThaiDatePartForPrint($main_end_date, 'year'); ?>
                                <br>
                                ระหว่างเวลา <?php echo formatThaiDatePartForPrint($event_start_time, 'time'); ?> น. ถึง <?php echo formatThaiDatePartForPrint($event_end_time, 'time'); ?> น.
                            </div>

                            <p class="mt-3 mb-2">และโปรดดำเนินการดังนี้</p>

                            <div class="form-item-block">
                                <ol class="list-unstyled ps-0 mb-0">
                                    <li class="d-flex align-items-baseline mb-2">
                                        <span class="me-1">1.</span>
                                        <div class="option-group">
                                            <span class="label-text me-2">อาคาร/สถานที่</span>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input custom-radio-checkbox" type="radio" name="building_option" id="buildingOption1" <?php echo ($facility_name_display || $building_name_display) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="buildingOption1">
                                                    <span class="data-text d-inline-block border-dotted-custom" style="min-width: 150px;"><?php echo $building_name_display . ($building_name_display && $facility_name_display ? ' ' : '') . $facility_name_display; ?></span>
                                                </label>
                                            </div>
                                            <span class="label-text ms-3 me-2">อื่นๆ</span>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input custom-radio-checkbox" type="radio" name="building_option" id="buildingOption2" <?php echo (!($facility_name_display || $building_name_display)) ? 'checked' : ''; ?>onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="buildingOption2">
                                                    <span class="data-text d-inline-block border-dotted-custom" style="min-width: 100px;"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="d-flex align-items-baseline mb-2">
                                        <span class="me-1">2.</span>
                                        <div class="option-group">
                                            <span class="label-text me-2">ประเภทของกิจกรรม</span>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input custom-radio-checkbox" type="radio" name="activity_type" id="activityType1" <?php echo ($activity_type_name == 'การเรียนการสอน') ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="activityType1">การเรียนการสอน (นอกตารางเรียน)</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input custom-radio-checkbox" type="radio" name="activity_type" id="activityType2" <?php echo ($activity_type_name == 'กิจกรรมพิเศษ' || $activity_type_name == 'กิจกรรมนิสิต' || $activity_type_name == 'การประชุม' || ($activity_type_name != 'การเรียนการสอน' && $activity_type_name != '')) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="activityType2">กิจกรรมพิเศษ</label>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="d-flex align-items-baseline mb-2">
                                        <span class="me-1">3.</span>
                                        <p class="mb-0 ps-0 d-flex align-items-baseline flex-grow-1">
                                            <span class="label-text flex-shrink-0 me-2">ให้แนบรายละเอียดและชื่อของกิจกรรม/โครงการที่อนุมัติแล้วมาด้วย</span>
                                            <span class="description-line border-dotted-custom flex-grow-1"><?php echo $project_description; ?></span>
                                        </p>
                                    </li>
                                    <li class="d-flex align-items-baseline mb-2">
                                        <span class="me-1">4.</span>
                                        <div class="option-group">
                                            <span class="label-text me-2">ยินยอมให้นำวัสดุ โครงป้ายไวนิล นำไปใช้ Reuse</span>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input custom-radio-checkbox" type="checkbox" name="reuse_option" id="reuseOption1" <?php echo ($agree_reuse == 1) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="reuseOption1">ยินยอม</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input custom-radio-checkbox" type="checkbox" name="reuse_option" id="reuseOption2" <?php echo ($agree_reuse == 0) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="reuseOption2">ไม่ยินยอม (ต้องเก็บคืนภายใน 7 วัน)</label>
                                            </div>
                                        </div>
                                    </li>
                                </ol>
                            </div>
                        <?php elseif ($request_type === 'equipment'): ?>
                            <div>
                                มีความประสงค์จะยืมอุปกรณ์/วัสดุ/ครุภัณฑ์ ของงานอาคารสถานที่ เพื่อใช้ในงาน/โครงการ <?php echo $project_name; ?>
                            </div>

                            <div>
                                สถานที่ที่ใช้งานคือ <?php echo $facility_name_used_display; ?>
                                ระหว่างวันที่
                                <?php echo formatThaiDatePartForPrint($main_start_date, 'day'); ?>
                                เดือน <?php echo formatThaiDatePartForPrint($main_start_date, 'month'); ?>
                                พ.ศ. <?php echo formatThaiDatePartForPrint($main_start_date, 'year'); ?>
                                ถึง
                                <?php echo formatThaiDatePartForPrint($main_end_date, 'day'); ?>
                                เดือน <?php echo formatThaiDatePartForPrint($main_end_date, 'month'); ?>
                                พ.ศ. <?php echo formatThaiDatePartForPrint($main_end_date, 'year'); ?>
                            </div>

                            <p class="mt-3 mb-2">โดยมีรายละเอียดดังต่อไปนี้</p>

                            <table class="equipment-table">
                                <thead>
                                    <tr>
                                        <th style="width: 10%;">ลำดับ</th>
                                        <th style="width: 35%;">รายการ</th>
                                        <th style="width: 15%;">หน่วย</th>
                                        <th style="width: 15%;">จำนวน</th>
                                        <th style="width: 25%;">หมายเหตุ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>1</td>
                                        <td><?php echo $equip_name_display; ?></td>
                                        <td><?php echo $measure_display; ?></td>
                                        <td><?php echo $quantity_display; ?></td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>

                            <div class="notes-section-equipment">
                                <p class="fw-bold mb-1">หมายเหตุ:</p>
                                <ol class="list-unstyled ps-0 mb-0">
                                    <li class="d-flex align-items-baseline mb-1">
                                        <span class="me-1">1.</span>
                                        <div class="option-group flex-grow-1">
                                            <span class="label-text me-2">ยินยอมให้นำวัสดุ โครงป้ายไวนิล อุปกรณ์อื่นๆ นำไปใช้ Reuse</span>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input custom-radio-checkbox" type="checkbox" name="reuse_option" id="reuseOption1" <?php echo ($agree_reuse == 1) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="reuseOption1">ยินยอม</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input custom-radio-checkbox" type="checkbox" name="reuse_option" id="reuseOption2" <?php echo ($agree_reuse == 0) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="reuseOption2">ไม่ยินยอม (ต้องเก็บ,รื้อถอน ภายใน 7 วัน)</label>
                                            </div>
                                        </div>
                                    </li>
                                    <li class="d-flex align-items-baseline mb-1">
                                        <span class="me-1">2.</span>
                                        <div class="option-group flex-grow-1">
                                            <span class="label-text me-2">ต้องการการขนย้ายจากงานอาคารสถานที่และยานพาหนะ</span>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input custom-radio-checkbox" type="radio" name="transport_option" id="transportOption1" <?php echo ($transport_option == 1) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="transportOption1">ต้องการ</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input custom-radio-checkbox" type="radio" name="transport_option" id="transportOption2" <?php echo ($transport_option == 0) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="transportOption2">ไม่ต้องการ</label>
                                            </div>
                                        </div>
                                    </li>
                                </ol>
                            </div>
                        <?php endif; ?>

                        <p class="mt-3 mb-0">ทั้งนี้ข้าพเจ้าจะรับผิดชอบต่อความเสียหายของวัสดุอุปกรณ์ภายในอาคาร/สถานที่ ที่ขอใช้บริการ และจะควบคุมการจัด<br>
                        สถานที่ ทั้งก่อนและหลังเสร็จงานให้อยู่ในสภาพเดิม และเก็บขยะ เศษอุปกรณ์ รอบบริเวณให้เรียบร้อยก่อนติดต่อรับบัตรคืน</p>

                        <?php
                            $signature_area_class = ($requester_role === 'บุคลากร') ? 'signature-area two-columns' : 'signature-area';
                        ?>
                        <div class="<?php echo $signature_area_class; ?> d-flex justify-content-between flex-wrap mt-5 pt-3 border-top border-dark border-dotted-custom fs-6">
                            <div class="signature-box">
                                <span class="label-text d-block">ลงชื่อ</span>
                                <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"><?php echo $requester_name; ?></span>
                                <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span>)</span>
                                <span class="signature-label-text d-block mt-1">ผู้ขอใช้บริการ</span>
                            </div>
                            <div class="signature-box">
                                <span class="label-text d-block">ลงชื่อ</span>
                                <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"></span>
                                <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span>)</span>
                                <span class="signature-label-text d-block mt-1">หัวหน้าหน่วยพัฒนาและกิจกรรมนิสิต</span>
                            </div>
                            <?php if ($requester_role !== 'บุคลากร'): // ซ่อนช่องอาจารย์ที่ปรึกษาสำหรับบุคลากร ?>
                                <div class="signature-box">
                                    <span class="label-text d-block">ลงชื่อ</span>
                                    <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"><?php echo $advisor_name; ?></span>
                                    <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span>)</span>
                                    <span class="signature-label-text d-block mt-1">อาจารย์ที่ปรึกษา</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 2 สำหรับเจ้าหน้าที่ -->
                <div class="section mb-4">
                    <div class="section-header-text">ส่วนที่ 2 สำหรับเจ้าหน้าที่</div>
                    <div class="section-content">
                        <?php if ($request_type === 'facility'): ?>
                            <div class="section2-approval d-flex justify-content-between align-items-start mb-3">
                                <div class="flex-grow-1 me-3">
                                    <p class="mb-0 lh-sm">1.เรียน หัวหน้างานอาคารสถานที่และยานพาหนะ<br>เพื่อโปรดพิจารณา</p>
                                </div>
                                <div class="flex-grow-1">
                                    <p class="approval-title fw-bold mb-2">2.ผู้มีอำนาจอนุมัติ</p>
                                    <div class="approval-options d-flex align-items-baseline flex-wrap">
                                        <div class="option-item form-check form-check-inline">
                                            <input type="radio" name="approval_status" id="approveOption1" class="form-check-input custom-radio-checkbox" <?php echo ($approve_status == 'อนุมัติ') ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                            <label class="form-check-label" for="approveOption1">อนุมัติ</label>
                                        </div>
                                        <div class="option-item form-check form-check-inline">
                                            <input type="radio" name="approval_status" id="approveOption2" class="form-check-input custom-radio-checkbox" <?php echo ($approve_status == 'ไม่อนุมัติ') ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                            <label class="form-check-label" for="approveOption2">ไม่อนุมัติ</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="staff-signature-area d-flex justify-content-around flex-wrap mt-4 fs-6">
                                <div class="staff-signature-box">
                                    <span class="label-text d-block">ลงชื่อ</span>
                                    <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"><?php echo $staff_name_approved; ?></span>
                                    <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span>)</span>
                                    <span class="signature-label-text d-block mt-1">ผู้จัดการกลุ่มอาคาร</span>
                                </div>
                                <div class="staff-signature-box">
                                    <span class="label-text d-block">ลงชื่อ</span>
                                    <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"></span>
                                    <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span>)</span>
                                    <span class="signature-label-text d-block mt-1">หัวหน้างานอาคารสถานที่และยานพาหนะ</span>
                                </div>
                            </div>
                        <?php elseif ($request_type === 'equipment'): ?>
                            <div class="d-flex flex-column mb-3">
                                <p class="mb-0 lh-sm">1.เรียน หัวหน้างานอาคารสถานที่และยานพาหนะ</p>
                                <p class="mb-0 lh-sm">2.พิจารณา</p>
                                <div class="approval-options d-flex align-items-baseline flex-wrap ms-4">
                                    <div class="option-item form-check form-check-inline">
                                        <input type="radio" name="staff_approval_status" id="staffApproveOption1" class="form-check-input custom-radio-checkbox" <?php echo ($approve_status == 'อนุมัติ') ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                        <label class="form-check-label" for="staffApproveOption1">เห็นควรอนุมัติ</label>
                                    </div>
                                    <div class="option-item form-check form-check-inline">
                                        <input type="radio" name="staff_approval_status" id="staffApproveOption2" class="form-check-input custom-radio-checkbox" <?php echo ($approve_status == 'ไม่อนุมัติ') ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                        <label class="form-check-label" for="staffApproveOption2">เห็นควรไม่อนุมัติ</label>
                                        <span class="label-text ms-2">เนื่องจาก</span>
                                        <span class="data-text d-inline-block border-dotted-custom" style="min-width: 200px;"><?php echo $approve_detail; ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="staff-signature-area d-flex justify-content-around flex-wrap mt-4 fs-6">
                                <div class="staff-signature-box">
                                    <span class="label-text d-block">ลงชื่อ</span>
                                    <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"><?php echo $staff_name_approved; ?></span>
                                    <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span>)</span>
                                    <span class="signature-label-text d-block mt-1">หัวหน้างานอาคารสถานที่และยานพาหนะ</span>
                                    <?php if ($approve_date_dt): ?>
                                    <span class="signature-label-text d-block mt-1">
                                        วันที่ <?php echo formatThaiDatePartForPrint($approve_date_dt->format('Y-m-d'), 'day'); ?>
                                        เดือน <?php echo formatThaiDatePartForPrint($approve_date_dt->format('Y-m-d'), 'month'); ?>
                                        พ.ศ. <?php echo formatThaiDatePartForPrint($approve_date_dt->format('Y-m-d'), 'year'); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="staff-signature-box">
                                    <span class="label-text d-block">ลงชื่อ</span>
                                    <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"></span>
                                    <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span>)</span>
                                    <span class="signature-label-text d-block mt-1"></span>
                                    <span class="signature-label-text d-block mt-1"></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- หมายเหตุ (General) -->
                <div class="notes mt-4 pt-3 border-top border-secondary">
                    <p class="fw-bold mb-2">หมายเหตุ:</p>
                    <ol class="ps-3">
                        <li class="mb-1">โปรดยื่นล่วงหน้าไม่น้อยกว่า 2 วันทำการ และต้องเก็บเศษขยะ เศษอุปกรณ์ต่าง ๆ รอบบริเวณอาคารทันที หลังเสร็จกิจกรรม</li>
                        <li class="mb-1">หากต้องการใช้โสตทัศนูปกรณ์ โปรดติดต่อ งานเทคโนโลยีสารสนเทศ อาคาร 9</li>
                        <li class="mb-1">หากต้องการใช้ห้องเรียน/ห้องพระพิ สาคริก โปรดติดต่อ งานทะเบียนและประมวลผล อาคาร 9</li>
                    </ol>
                </div>
            </div>
        <?php endif; ?>

        <!-- ปุ่มสำหรับการดำเนินการ (อยู่นอก form-container เพื่อไม่ให้ถูกพิมพ์) -->
        <div class="d-flex justify-content-between mt-4">
            <a href="admin-main-page.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left-circle me-2"></i>ย้อนกลับไปหน้าหลัก
            </a>
            <button id="downloadPdfButton" class="btn btn-primary">
                <i class="bi bi-file-earmark-pdf me-2"></i>ดาวน์โหลด PDF
            </button>
        </div>
    </div>

    <script>
        document.getElementById('downloadPdfButton').addEventListener('click', function() {
            const element = document.getElementById('mainContent');
            const overlay = document.getElementById('pdf-loading-overlay');
            overlay.style.display = 'flex'; // Show loading overlay

            const filename = 'Request_' + '<?php echo $request_type; ?>' + '_ID_<?php echo $request_id; ?>.pdf';

            // Options for html2pdf
            const options = {
                margin: 8, // Millimeters on all sides, further reduced
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 3, // Keep high scale for clarity
                    logging: true,
                    dpi: 192,
                    letterRendering: true,
                    useCORS: true // Important for external resources like Google Fonts and images
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                // Use pagebreak to respect CSS page-break properties and avoid breaking content randomly
                pagebreak: {
                    mode: ['css', 'avoid-all'] // Apply CSS page-break rules, and avoid breaking other content if possible
                }
            };

            // Generate PDF
            html2pdf().set(options).from(element).save().then(() => {
                overlay.style.display = 'none'; // Hide loading overlay after PDF is saved
            }).catch(error => {
                console.error("Error generating PDF:", error);
                alert("เกิดข้อผิดพลาดในการสร้างไฟล์ PDF: " + error);
                overlay.style.display = 'none'; // Hide overlay even on error
            });
        });
    </script>
</body>
</html>