<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'เจ้าหน้าที่') {
    header("Location: login.php");
    exit();
}

include 'database/database.php';
include 'php/helpers.php'; 

$print_all_project_requests = isset($_GET['print_all']) && $_GET['print_all'] === 'true';
$project_id = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;

$all_requests_to_print = []; // จะเก็บคำร้องขอทั้งหมดสำหรับพิมพ์
$errors = [];

if ($print_all_project_requests && $project_id > 0) {
    // 1. ดึงข้อมูลโครงการหลัก (สำหรับข้อมูลผู้ยื่น, ที่ปรึกษา)
    $project_info = null;
    $sql_project_info = "SELECT p.project_name, p.project_des, p.activity_type_id, at.activity_type_name, p.advisor_name,
                         CONCAT(u.user_name, ' ', u.user_sur) AS user_name, u.nontri_id,
                         ut.user_type_name AS requester_role, u.position AS requester_position,
                         p.phone_num AS user_phone_num, fd.fa_de_name
                         FROM project p
                         JOIN user u ON p.nontri_id = u.nontri_id
                         JOIN user_type ut ON u.user_type_id = ut.user_type_id
                         JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                         JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
                         WHERE p.project_id = ?";
    $stmt_project_info = $conn->prepare($sql_project_info);
    if ($stmt_project_info) {
        $stmt_project_info->bind_param("i", $project_id);
        $stmt_project_info->execute();
        $project_info = $stmt_project_info->get_result()->fetch_assoc();
        $stmt_project_info->close();
    } else {
        $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูลโครงการหลัก: " . $conn->error;
    }

    if ($project_info) {
        // 2. ดึงคำร้องขอใช้สถานที่ทั้งหมดที่เกี่ยวข้องกับโครงการนี้
        $sql_facilities = "SELECT
                            fr.facility_re_id AS request_id, fr.project_id, fr.prepare_start_time, fr.prepare_end_time,
                            fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time,
                            fr.start_date AS main_start_date, fr.end_date AS main_end_date, fr.agree,
                            fr.writed_status, fr.request_date, fr.approve, fr.approve_date, fr.approve_detail,
                            f.facility_name, f.building_id, b.building_name,
                            CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name, s.staff_id,
                            'facility' AS request_type_label
                        FROM facilities_requests fr
                        JOIN facilities f ON fr.facility_id = f.facility_id
                        JOIN buildings b ON f.building_id = b.building_id
                        LEFT JOIN staff s ON fr.staff_id = s.staff_id
                        WHERE fr.project_id = ? AND fr.writed_status != 'ร่างคำร้องขอ'
                        ORDER BY fr.request_date ASC";
        $stmt_facilities = $conn->prepare($sql_facilities);
        if ($stmt_facilities) {
            $stmt_facilities->bind_param("i", $project_id);
            $stmt_facilities->execute();
            $result_facilities = $stmt_facilities->get_result();
            while ($row = $result_facilities->fetch_assoc()) {
                $all_requests_to_print[] = array_merge($project_info, $row); // ผสมข้อมูลโครงการกับข้อมูลคำร้อง
            }
            $stmt_facilities->close();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการดึงคำร้องขอใช้สถานที่: " . $conn->error;
        }

        // 3. ดึงคำร้องขอใช้อุปกรณ์ทั้งหมดที่เกี่ยวข้องกับโครงการนี้
        $sql_equipments = "SELECT
                            er.equip_re_id AS request_id, er.project_id, er.start_date AS main_start_date, er.end_date AS main_end_date, er.quantity, er.transport,
                            er.writed_status, er.request_date, er.approve, er.approve_date, er.approve_detail, er.agree,
                            e.equip_name, e.measure,
                            COALESCE(f.facility_name, 'ไม่ระบุ') AS facility_name_used,
                            CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name, s.staff_id,
                            'equipment' AS request_type_label
                        FROM equipments_requests er
                        JOIN equipments e ON er.equip_id = e.equip_id
                        JOIN facilities f ON er.facility_id = f.facility_id
                        LEFT JOIN staff s ON er.staff_id = s.staff_id
                        WHERE er.project_id = ? AND er.writed_status != 'ร่างคำร้องขอ'
                        ORDER BY er.request_date ASC";
        $stmt_equipments = $conn->prepare($sql_equipments);
        if ($stmt_equipments) {
            $stmt_equipments->bind_param("i", $project_id);
            $stmt_equipments->execute();
            $result_equipments = $stmt_equipments->get_result();
            while ($row = $result_equipments->fetch_assoc()) {
                $all_requests_to_print[] = array_merge($project_info, $row); // ผสมข้อมูลโครงการกับข้อมูลคำร้อง
            }
            $stmt_equipments->close();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการดึงคำร้องขอใช้อุปกรณ์: " . $conn->error;
        }

        // Sort all requests by their request_date (newest first for consistency)
        usort($all_requests_to_print, function($a, $b) {
            return strtotime($a['request_date']) - strtotime($b['request_date']);
        });

    } else {
        $errors[] = "ไม่พบข้อมูลโครงการหลักสำหรับ Project ID: " . $project_id;
    }

} else {
    // โหมดพิมพ์คำร้องขอเดี่ยว (โค้ดเดิม)
    $request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $request_type = isset($_GET['type']) ? $_GET['type'] : ''; // 'facility' or 'equipment'

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
                        ut.user_type_name AS requester_role, u.position AS requester_position,
                        p.phone_num AS user_phone_num, fd.fa_de_name,
                        CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name, s.staff_id,
                        'facility' AS request_type_label
                    FROM facilities_requests fr
                    JOIN facilities f ON fr.facility_id = f.facility_id
                    JOIN buildings b ON f.building_id = b.building_id
                    JOIN project p ON fr.project_id = p.project_id
                    JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                    JOIN user u ON p.nontri_id = u.nontri_id
                    JOIN user_type ut ON u.user_type_id = ut.user_type_id
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
                        ut.user_type_name AS requester_role, u.position AS requester_position,
                        p.phone_num AS user_phone_num, fd.fa_de_name,
                        COALESCE(f.facility_name, 'ไม่ระบุ') AS facility_name_used,
                        CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name, s.staff_id,
                        'equipment' AS request_type_label
                    FROM equipments_requests er
                    JOIN equipments e ON er.equip_id = e.equip_id
                    JOIN project p ON er.project_id = p.project_id
                    JOIN facilities f ON er.facility_id = f.facility_id
                    JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                    JOIN user u ON p.nontri_id = u.nontri_id
                    JOIN user_type ut ON u.user_type_id = ut.user_type_id
                    JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
                    LEFT JOIN staff s ON er.staff_id = s.staff_id
                    WHERE er.equip_re_id = ?";
        } else {
            $errors[] = "ประเภทคำร้องไม่ถูกต้อง (ต้องเป็น 'facility' หรือ 'equipment').";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $request_data = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($request_data) {
                    $all_requests_to_print[] = $request_data; // เพิ่มคำร้องขอเดี่ยวเข้าใน array
                } else {
                    $errors[] = "ไม่พบข้อมูลสำหรับคำร้องนี้";
                }
            } else {
                error_log("Failed to prepare statement for admin-print-page.php: " . $conn->error);
                $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $conn->error;
            }
        }
    } else {
        $errors[] = "ไม่พบรหัสคำร้อง";
    }
}

$conn->close();

if (empty($all_requests_to_print) && empty($errors)) {
    $errors[] = "ไม่พบข้อมูลคำร้องขอที่เกี่ยวข้องสำหรับโครงการนี้";
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Sarabun', sans-serif !important;
            font-size: 8.5pt; 
            line-height: 1.2; 
            color: #000;
            background-color: #fff;
        }

        .form-container {
            max-width: 210mm;
            margin: 0 auto; 
            border: 1px solid #000;
            padding: 1.5mm;
            box-sizing: border-box;
            page-break-after: always;
        }
        .form-container:last-child {
            page-break-after: avoid; 
        }


        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 3px;
            padding-bottom: 0.5px;
            border-bottom: 1px solid #dee2e6;
            margin: 0 1.5mm;
        }

        .header .logo-container {
            flex-shrink: 0;
            width: 55px;
            text-align: left;
            margin-right: 3px;
        }

        .header .logo {
            width: 40px;
            height: auto;
            display: block;
        }

        .header .title-info {
            flex-grow: 1;
            text-align: center;
            line-height: 1.0;
        }

        .header .title-info h1 {
            font-size: 10.5pt;
            margin: 0;
            font-weight: bold;
        }

        .header .title-info p {
            font-size: 7.5pt;
            margin: 0;
        }

        .header .doc-id {
            flex-shrink: 0;
            width: 55px; 
            text-align: right;
            font-weight: bold;
            font-size: 7.5pt;
            white-space: nowrap;
        }

        .section-header-text {
            font-weight: bold;
            font-size: 9pt;
            margin-top: 4px;
            margin-bottom: 1px;
            padding-bottom: 0.5px;
            border-bottom: 1px solid #000;
        }

        .section-content {
            padding: 0 1.5mm;
        }

        .section-content p, .section-content div {
            margin-top: 1.5px;
            margin-bottom: 1.5px;
            line-height: 1.2;
            font-size: 8.5pt;
        }

        .section-content ol li {
            font-size: 8.5pt;
            line-height: 1.15;
            margin-bottom: 1px;
        }

        .form-group .label-text {
            flex-shrink: 0;
            margin-right: 2px;
            white-space: nowrap;
            font-size: 8.5pt; 
            line-height: 1.2;
        }

        .equipment-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            margin-bottom: 6px; 
            font-size: 8pt; 
        }
        .equipment-table th, .equipment-table td {
            border: 1px solid #000;
            padding: 0.8px 2mm; 
            text-align: center;
            line-height: 1.05;
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
            font-size: 8.5pt;
            line-height: 1.2;
        }
        .option-group .form-check-label {
            line-height: 1.2;
        }

        .option-item {
            display: flex;
            align-items: baseline;
            margin-right: 6px;
            white-space: nowrap;
        }

        .description-line {
            flex-grow: 1;
            min-height: 1.1em;
            padding: 0 2px;
            font-size: 8.5pt;
            line-height: 1.2;
            white-space: normal;
            word-break: break-word;
            text-align: left;
        }

        .signature-area {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 4mm;
            padding-top: 1.5mm; 
            border-top: 1px dotted #000;
            font-size: 8.5pt;
        }

        .signature-box {
            flex: 1;
            min-width: 30%;
            max-width: 33%;
            text-align: center;
            margin: 1mm 0.5mm;
        }

        .signature-area.two-columns .signature-box {
            min-width: 45%;
            max-width: 50%;
        }

        .signature-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 55px; 
            padding: 0 1mm;
            text-align: center;
            font-size: 8.5pt; 
            line-height: 1.2;
            margin-bottom: 0.8mm; 
        }

        .signature-label-text {
            display: block;
            font-size: 8pt; 
            margin-top: 0.2mm;
        }

        .section2-approval {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 3px;
            font-size: 8.5pt;
        }
        .section2-approval > div {
            flex: 1;
        }
        .approval-options {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            font-size: 8.5pt;
            line-height: 1.2;
        }
        .approval-options .option-item {
            margin-right: 6px;
        }
        .approval-title {
            font-weight: bold;
            margin-bottom: 1.5px;
            font-size: 9pt;
        }

        .staff-signature-area {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin-top: 4mm;
            font-size: 8.5pt;
        }
        .staff-signature-box {
            flex: 1;
            min-width: 45%;
            max-width: 50%;
            text-align: center;
            margin: 1mm 0.5mm;
        }
        .staff-signature-box .signature-line {
            margin-bottom: 0.8mm;
        }
        .staff-signature-box .signature-date-group {
            margin-top: 1mm;
            justify-content: flex-start;
            display: flex;
            align-items: center;
        }

        .notes {
            margin-top: 3mm;
            padding-top: 1mm;
            border-top: 1px solid #ccc;
            font-size: 8pt; 
            line-height: 1.1; 
            page-break-inside: avoid;
        }
        .notes ol {
            list-style-type: decimal;
            padding-left: 7mm; 
            margin: 0;
        }
        .notes ol li {
            margin-bottom: 0.2mm; 
            line-height: 1.1;
        }
        .notes p {
            font-size: 8.5pt;
            font-weight: bold;
            margin-bottom: 0.5px; 
        }
        .notes p:first-child {
            margin-bottom: 0.5px;
        }

        input[type="radio"].custom-radio-checkbox,
        input[type="checkbox"].custom-radio-checkbox {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            display: inline-block;
            vertical-align: middle;
            width: 8.5px; 
            height: 8.5px;
            margin-right: 1.5px; 
            border: 1px solid #000;
            box-sizing: border-box;
            position: relative;
            top: -1px;
            flex-shrink: 0;
        }

        input[type="radio"].custom-radio-checkbox:checked::before {
            content: '';
            display: block;
            position: absolute;
            top: 2px;
            left: 2px;
            width: 2.5px; 
            height: 2.5px;
            background-color: #000;
            border-radius: 50%;
        }

        input[type="checkbox"].custom-radio-checkbox:checked::before {
            content: '\2713';
            display: block;
            position: absolute;
            top: -3px;
            left: 0px;
            font-size: 8.5px; 
            color: #000;
            font-weight: bold;
            line-height: 1;
        }

        .border-dotted-custom {
            border-bottom: 1px dotted #000;
        }

        .section {
            page-break-inside: avoid;
            margin-bottom: 5px;
        }

        .tab {
            margin-left: 1.5em;
            text-indent: -1.5em;
        }

        @media screen {
            .print-only {
                display: none !important;
            }
        }
        @media print {
            .screen-only {
                display: none !important;
            }
            body {
                margin: 0; 
            }
            .form-container {
                 border: none !important;
                 box-shadow: none !important;
                 margin: 0 !important;
                 padding: 0 !important;
            }
            #printContentWrapper {
                width: 210mm; 
                margin: 0 auto;
                padding: 5mm;
            }
        }

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
    <nav class="navbar navbar-dark navigator screen-only">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3">
                <a href="admin-data_view-page.php">
                    <img src="./images/logo.png" class="img-fluid logo" alt="Logo">
                </a>
                <div class="d-flex flex-column">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4">ระบบขอใช้อุปกรณ์และสถานที่</span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5">KU FTD</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mx-auto">
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="admin-main-page.php">การจัดการระบบ</a>
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="admin-data_view-page.php">ตรวจสอบอุปกรณ์และสถานที่</a>
            </div>
            <div class="d-flex align-items-center ms-auto gap-2">
                <div class="d-flex flex-column text-end">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo htmlspecialchars($_SESSION['user_display_name'] ?? ''); ?> <?php echo htmlspecialchars($_SESSION['user_display_sur'] ?? ''); ?></span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></span>
                </div>
                <img src="./images/user_button.png" class="img-fluid logo" style="width:40px; height:40px; object-fit:cover;" alt="User Button">
            </div>
        </div>
    </nav>
    <div class="container mt-4 mb-4 screen-only">
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
            <div id="pdf-loading-overlay" style="display: none;">
                <div class="spinner-border text-light" role="status">
                    <span class="visually-hidden">กำลังสร้าง PDF...</span>
                </div>
                <span>กำลังสร้างเอกสาร PDF... โปรดรอสักครู่</span>
            </div>

            <div id="printContentWrapper">
                <?php
                if (empty($all_requests_to_print)) {
                    echo '<div class="alert alert-info text-center mt-4">ไม่พบคำร้องขอที่เกี่ยวข้องสำหรับโครงการนี้</div>';
                } else {
                    foreach ($all_requests_to_print as $index => $current_request_data):
                        // Extract and prepare data for the current request
                        $request_dt = new DateTime($current_request_data['request_date'] ?? 'now');
                        $requester_name = htmlspecialchars($current_request_data['user_name'] ?? '');
                        $requester_role = htmlspecialchars($current_request_data['requester_role'] ?? '');
                        $requester_position = htmlspecialchars($current_request_data['requester_position'] ?? '');
                        $faculty_name = htmlspecialchars($current_request_data['fa_de_name'] ?? '');
                        $requester_phone = htmlspecialchars($current_request_data['user_phone_num'] ?? '');
                        $project_name = htmlspecialchars($current_request_data['project_name'] ?? '');
                        $project_description = htmlspecialchars($current_request_data['project_des'] ?? '');
                        $activity_type_name = htmlspecialchars($current_request_data['activity_type_name'] ?? '');
                        $agree_reuse = $current_request_data['agree'] ?? null;

                        $current_request_type = $current_request_data['request_type_label']; // 'facility' or 'equipment'

                        // Dates/Times for Facilities Request (if applicable)
                        $prepare_start_date = $current_request_data['prepare_start_date'] ?? null;
                        $prepare_end_date = $current_request_data['prepare_end_date'] ?? null;
                        $prepare_start_time = $current_request_data['prepare_start_time'] ?? null;
                        $prepare_end_time = $current_request_data['prepare_end_time'] ?? null;
                        $event_start_time = $current_request_data['start_time'] ?? null;
                        $event_end_time = $current_request_data['end_time'] ?? null;
                        $facility_name_display = htmlspecialchars($current_request_data['facility_name'] ?? '');
                        $building_name_display = htmlspecialchars($current_request_data['building_name'] ?? '');

                        // Dates/Times & specific fields for Equipment Request (if applicable)
                        $equip_name_display = htmlspecialchars($current_request_data['equip_name'] ?? '');
                        $measure_display = htmlspecialchars($current_request_data['measure'] ?? '');
                        $quantity_display = htmlspecialchars($current_request_data['quantity'] ?? '');
                        $facility_name_used_display = htmlspecialchars($current_request_data['facility_name_used'] ?? '');
                        $transport_option = $current_request_data['transport'] ?? null;

                        // Common dates (main event dates)
                        $main_start_date = $current_request_data['main_start_date'] ?? null;
                        $main_end_date = $current_request_data['main_end_date'] ?? null;

                        $advisor_name = htmlspecialchars($current_request_data['advisor_name'] ?? '');

                        $approve_status = $current_request_data['approve'] ?? null;
                        $approve_date_dt = ($current_request_data['approve_date'] ?? null) ? new DateTime($current_request_data['approve_date']) : null;
                        $staff_name_approved = htmlspecialchars($current_request_data['staff_name'] ?? '');
                        $approve_detail = htmlspecialchars($current_request_data['approve_detail'] ?? '');
                ?>
                    <div class="form-container shadow-sm">
                        <div class="header">
                            <div class="logo-container">
                                <img src="images/ku_print_logo.png" alt="โลโก้ มหาวิทยาลัยเกษตรศาสตร์ วิทยาเขตเฉลิมพระเกียรติ จังหวัดสกลนคร" class="logo">
                            </div>
                            <div class="title-info">
                                <?php if ($current_request_type === 'facility'): ?>
                                    <h1 class="fs-5 fw-bold mb-0">แบบขอใช้อาคาร/สถานที่ (สำหรับ<?php echo ($requester_role === 'บุคลากร') ? 'บุคลากร' : 'นิสิต'; ?>)</h1>
                                    <p class="fs-6 mb-0">สำนักงานวิทยาเขต กองบริการกลาง งานอาคารสถานที่และยานพาหนะ</p>
                                    <p class="fs-6 mb-0">โทร. 0-4272-5089 โทรสาร 0-4272-5088</p>
                                <?php elseif ($current_request_type === 'equipment'): ?>
                                    <h1 class="fs-5 fw-bold mb-0">แบบขอใช้พัสดุ-อุปกรณ์ (สำหรับ<?php echo ($requester_role === 'บุคลากร') ? 'บุคลากร' : 'นิสิต'; ?>)</h1>
                                    <p class="fs-6 mb-0">สำนักงานวิทยาเขต กองบริการกลาง งานอาคารสถานที่และยานพาหนะ</p>
                                    <p class="fs-6 mb-0">โทร. 0-4272-5089 โทรสาร 0-4272-5088</p>
                                <?php endif; ?>
                            </div>
                            <div class="doc-id">
                                <?php echo ($current_request_type === 'facility') ? 'OCC04-08.1' : 'OCC 03-03.03'; ?>
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

                                <?php if ($current_request_type === 'facility'): ?>
                                    <div class="tab">
                                        มีความประสงค์ขอใช้สถานที่ในโครงการ/งาน/หน่วย <?php echo $project_name; ?>
                                    </div>

                                    <div class="tab">
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

                                    <div class="tab">
                                        วันที่เตรียมงาน วันที่ <?php echo formatThaiDatePartForPrint($prepare_start_date, 'day'); ?>
                                        เดือน <?php echo formatThaiDatePartForPrint($prepare_start_date, 'month'); ?>
                                        พ.ศ. <?php echo formatThaiDatePartForPrint($prepare_start_date, 'year'); ?>
                                        ถึง
                                        <?php echo formatThaiDatePartForPrint($prepare_end_date, 'day'); ?>
                                        เดือน <?php echo formatThaiDatePartForPrint($prepare_end_date, 'month'); ?> พ.ศ. <?php echo formatThaiDatePartForPrint($prepare_end_date, 'year'); ?>
                                        <br>
                                        ระหว่างเวลา <?php echo formatThaiDatePartForPrint($prepare_start_time, 'time'); ?> น. ถึง <?php echo formatThaiDatePartForPrint($prepare_end_time, 'time'); ?> น.
                                    </div>

                                    <div class="tab">
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
                                            <li class="d-flex align-items-baseline mb-1">
                                                <span class="me-1">1.</span>
                                                <div class="option-group">
                                                    <span class="label-text me-2">อาคาร/สถานที่</span>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input custom-radio-checkbox" type="radio" name="building_option" id="buildingOption1_<?php echo $current_request_data['request_id']; ?>" <?php echo ($facility_name_display || $building_name_display) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                        <label class="form-check-label" for="buildingOption1_<?php echo $current_request_data['request_id']; ?>">
                                                            <span class="data-text d-inline-block border-dotted-custom" style="min-width: 150px;"><?php echo $building_name_display . ($building_name_display && $facility_name_display ? ' ' : '') . $facility_name_display; ?></span>
                                                        </label>
                                                    </div>
                                                    <span class="label-text ms-3 me-2">อื่นๆ</span>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input custom-radio-checkbox" type="radio" name="building_option" id="buildingOption2_<?php echo $current_request_data['request_id']; ?>" <?php echo (!($facility_name_display || $building_name_display)) ? 'checked' : ''; ?>onclick="return false;" tabindex="-1">
                                                        <label class="form-check-label" for="buildingOption2_<?php echo $current_request_data['request_id']; ?>">
                                                            <span class="data-text d-inline-block border-dotted-custom" style="min-width: 100px;"></span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex align-items-baseline mb-1">
                                                <span class="me-1">2.</span>
                                                <div class="option-group">
                                                    <span class="label-text me-2">ประเภทของกิจกรรม</span>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input custom-radio-checkbox" type="radio" name="activity_type_<?php echo $current_request_data['request_id']; ?>" id="activityType1_<?php echo $current_request_data['request_id']; ?>" <?php echo ($activity_type_name == 'การเรียนการสอน') ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                        <label class="form-check-label" for="activityType1_<?php echo $current_request_data['request_id']; ?>">การเรียนการสอน (นอกตารางเรียน)</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input custom-radio-checkbox" type="radio" name="activity_type_<?php echo $current_request_data['request_id']; ?>" id="activityType2_<?php echo $current_request_data['request_id']; ?>" <?php echo ($activity_type_name == 'กิจกรรมพิเศษ' || $activity_type_name == 'กิจกรรมนิสิต' || $activity_type_name == 'การประชุม' || ($activity_type_name != 'การเรียนการสอน' && $activity_type_name != '')) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                        <label class="form-check-label" for="activityType2_<?php echo $current_request_data['request_id']; ?>">กิจกรรมพิเศษ</label>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex align-items-baseline mb-1">
                                                <span class="me-1">3.</span>
                                                <p class="mb-0 ps-0 d-flex align-items-baseline flex-grow-1">
                                                    <span class="label-text flex-shrink-0 me-2">ให้แนบรายละเอียดและชื่อของกิจกรรม/โครงการที่อนุมัติแล้วมาด้วย</span>
                                                    <span class="description-line border-dotted-custom flex-grow-1"><?php echo $project_description; ?></span>
                                                </p>
                                            </li>
                                            <li class="d-flex align-items-baseline mb-1">
                                                <span class="me-1">4.</span>
                                                <div class="option-group">
                                                    <span class="label-text me-2">ยินยอมให้นำวัสดุ โครงป้ายไวนิล นำไปใช้ Reuse</span>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input custom-radio-checkbox" type="checkbox" name="reuse_option_<?php echo $current_request_data['request_id']; ?>" id="reuseOption1_<?php echo $current_request_data['request_id']; ?>" <?php echo ($agree_reuse == 1) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                        <label class="form-check-label" for="reuseOption1_<?php echo $current_request_data['request_id']; ?>">ยินยอม</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input custom-radio-checkbox" type="checkbox" name="reuse_option_<?php echo $current_request_data['request_id']; ?>" id="reuseOption2_<?php echo $current_request_data['request_id']; ?>" <?php echo ($agree_reuse == 0) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                        <label class="form-check-label" for="reuseOption2_<?php echo $current_request_data['request_id']; ?>">ไม่ยินยอม (ต้องเก็บคืนภายใน 7 วัน)</label>
                                                    </div>
                                                </div>
                                            </li>
                                        </ol>
                                    </div>
                                <?php elseif ($current_request_type === 'equipment'): ?>
                                    <div class="tab">
                                        มีความประสงค์จะยืมอุปกรณ์/วัสดุ/ครุภัณฑ์ ของงานอาคารสถานที่ เพื่อใช้ในงาน/โครงการ <?php echo $project_name; ?>
                                    </div>

                                    <div class="tab">
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
                                                        <input class="form-check-input custom-radio-checkbox" type="checkbox" name="reuse_option_<?php echo $current_request_data['request_id']; ?>" id="reuseOption1_<?php echo $current_request_data['request_id']; ?>" <?php echo ($agree_reuse == 1) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                        <label class="form-check-label" for="reuseOption1_<?php echo $current_request_data['request_id']; ?>">ยินยอม</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input custom-radio-checkbox" type="checkbox" name="reuse_option_<?php echo $current_request_data['request_id']; ?>" id="reuseOption2_<?php echo $current_request_data['request_id']; ?>" <?php echo ($agree_reuse == 0) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                        <label class="form-check-label" for="reuseOption2_<?php echo $current_request_data['request_id']; ?>">ไม่ยินยอม (ต้องเก็บ,รื้อถอน ภายใน 7 วัน)</label>
                                                    </div>
                                                </div>
                                            </li>
                                            <li class="d-flex align-items-baseline mb-1">
                                                <span class="me-1">2.</span>
                                                <div class="option-group flex-grow-1">
                                                    <span class="label-text me-2">ต้องการการขนย้ายจากงานอาคารสถานที่และยานพาหนะ</span>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input custom-radio-checkbox" type="radio" name="transport_option_<?php echo $current_request_data['request_id']; ?>" id="transportOption1_<?php echo $current_request_data['request_id']; ?>" <?php echo ($transport_option == 1) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                        <label class="form-check-label" for="transportOption1_<?php echo $current_request_data['request_id']; ?>">ต้องการ</label>
                                                    </div>
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input custom-radio-checkbox" type="radio" name="transport_option_<?php echo $current_request_data['request_id']; ?>" id="transportOption2_<?php echo $current_request_data['request_id']; ?>" <?php echo ($transport_option == 0) ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                        <label class="form-check-label" for="transportOption2_<?php echo $current_request_data['request_id']; ?>">ไม่ต้องการ</label>
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
                                        <br>
                                        <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"></span>
                                        <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"><?php echo $requester_name; ?></span>)</span>
                                        <span class="signature-label-text d-block mt-1">ผู้ขอใช้บริการ</span>
                                    </div>
                                    <div class="signature-box">
                                        <span class="label-text d-block">ลงชื่อ</span>
                                        <br>
                                        <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"></span>
                                        <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span>)</span>
                                        <span class="signature-label-text d-block mt-1">หัวหน้าหน่วยพัฒนาและกิจกรรมนิสิต</span>
                                    </div>
                                    <?php if ($requester_role !== 'บุคลากร'): ?>
                                        <div class="signature-box">
                                            <span class="label-text d-block">ลงชื่อ</span>
                                            <br>
                                            <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"></span>
                                            <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"><?php echo $advisor_name; ?></span>)</span>
                                            <span class="signature-label-text d-block mt-1">อาจารย์ที่ปรึกษา</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="section mb-4">
                            <div class="section-header-text">ส่วนที่ 2 สำหรับเจ้าหน้าที่</div>
                            <div class="section-content">
                                <?php if ($current_request_type === 'facility'): ?>
                                    <div class="section2-approval d-flex justify-content-between align-items-start mb-3">
                                        <div class="flex-grow-1 me-3">
                                            <p class="mb-0 lh-sm">1.เรียน หัวหน้างานอาคารสถานที่และยานพาหนะ<br>เพื่อโปรดพิจารณา</p>
                                        </div>
                                        <div class="flex-grow-1">
                                            <p class="approval-title fw-bold mb-2">2.ผู้มีอำนาจอนุมัติ</p>
                                            <div class="approval-options d-flex align-items-baseline flex-wrap">
                                                <div class="option-item form-check form-check-inline">
                                                    <input type="radio" name="approval_status_<?php echo $current_request_data['request_id']; ?>" id="approveOption1_<?php echo $current_request_data['request_id']; ?>" class="form-check-input custom-radio-checkbox" <?php echo ($approve_status == 'อนุมัติ') ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                    <label class="form-check-label" for="approveOption1_<?php echo $current_request_data['request_id']; ?>">อนุมัติ</label>
                                                </div>
                                                <div class="option-item form-check form-check-inline">
                                                    <input type="radio" name="approval_status_<?php echo $current_request_data['request_id']; ?>" id="approveOption2_<?php echo $current_request_data['request_id']; ?>" class="form-check-input custom-radio-checkbox" <?php echo ($approve_status == 'ไม่อนุมัติ') ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                    <label class="form-check-label" for="approveOption2_<?php echo $current_request_data['request_id']; ?>">ไม่อนุมัติ</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="staff-signature-area d-flex justify-content-around flex-wrap mt-4 fs-6">
                                        <div class="staff-signature-box">
                                            <span class="label-text d-block">ลงชื่อ</span>
                                            <br>
                                            <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"></span>
                                            <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span><?php echo $staff_name_approved; ?>)</span>
                                            <span class="signature-label-text d-block mt-1">ผู้จัดการกลุ่มอาคาร</span>
                                        </div>
                                        <div class="staff-signature-box">
                                            <span class="label-text d-block">ลงชื่อ</span>
                                            <br>
                                            <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"></span>
                                            <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span>)</span>
                                            <span class="signature-label-text d-block mt-1">หัวหน้างานอาคารสถานที่และยานพาหนะ</span>
                                        </div>
                                    </div>
                                <?php elseif ($current_request_type === 'equipment'): ?>
                                    <div class="d-flex flex-column mb-3">
                                        <p class="mb-0 lh-sm">1.เรียน หัวหน้างานอาคารสถานที่และยานพาหนะ</p>
                                        <p class="mb-0 lh-sm">2.พิจารณา</p>
                                        <div class="approval-options d-flex align-items-baseline flex-wrap ms-4">
                                            <div class="option-item form-check form-check-inline">
                                                <input type="radio" name="staff_approval_status_<?php echo $current_request_data['request_id']; ?>" id="staffApproveOption1_<?php echo $current_request_data['request_id']; ?>" class="form-check-input custom-radio-checkbox" <?php echo ($approve_status == 'อนุมัติ') ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="staffApproveOption1_<?php echo $current_request_data['request_id']; ?>">เห็นควรอนุมัติ</label>
                                            </div>
                                            <div class="option-item form-check form-check-inline">
                                                <input type="radio" name="staff_approval_status_<?php echo $current_request_data['request_id']; ?>" id="staffApproveOption2_<?php echo $current_request_data['request_id']; ?>" class="form-check-input custom-radio-checkbox" <?php echo ($approve_status == 'ไม่อนุมัติ') ? 'checked' : ''; ?> onclick="return false;" tabindex="-1">
                                                <label class="form-check-label" for="staffApproveOption2_<?php echo $current_request_data['request_id']; ?>">เห็นควรไม่อนุมัติ</label>
                                                <span class="label-text ms-2">เนื่องจาก</span>
                                                <span class="data-text d-inline-block border-dotted-custom" style="min-width: 200px;"><?php echo $approve_detail; ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="staff-signature-area d-flex justify-content-around flex-wrap mt-4 fs-6">
                                        <div class="staff-signature-box">
                                            <span class="label-text d-block">ลงชื่อ</span>
                                            <br>
                                            <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"></span>
                                            <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"><?php echo $staff_name_approved; ?></span>)</span>
                                            <span class="signature-label-text d-block mt-1">เจ้าหน้าที่งานอาคารสถานที่และยานพาหนะ</span>
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
                                            <br>
                                            <span class="signature-line d-inline-block border-bottom border-dark pb-0 mb-2" style="min-width: 150px;"></span>
                                            <span class="signature-label-text d-block">(<span class="signature-line d-inline-block border-bottom border-dark pb-0" style="min-width: 150px;"></span>)</span>
                                            <span class="signature-label-text d-block mt-1"></span>
                                            <span class="signature-label-text d-block mt-1"></span>
                                            <span class="signature-label-text d-block mt-1">หัวหน้างานอาคารสถานที่และยานพาหนะ</span>
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
                    </div><!-- End of form-container -->
                <?php endforeach; } // End foreach and else for $all_requests_to_print ?>
            </div><!-- End of printContentWrapper -->
        <?php endif; ?>

        <!-- ปุ่มสำหรับการดำเนินการ (อยู่นอก form-container เพื่อไม่ให้ถูกพิมพ์) -->
        <div class="d-flex justify-content-between mt-4 screen-only">
            <?php
                // Determine appropriate back link
                $back_link = 'admin-main-page.php?main_tab=dashboard_admin'; // Default fallback
                if ($print_all_project_requests && $project_id > 0) {
                    $back_link = 'admin-main-page.php?main_tab=projects_admin&mode=detail&id=' . htmlspecialchars($project_id);
                } elseif (isset($_SERVER['HTTP_REFERER'])) {
                    $back_link = htmlspecialchars($_SERVER['HTTP_REFERER']);
                }
            ?>
            <a href="<?php echo $back_link; ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left-circle me-2"></i>ย้อนกลับ
            </a>
            <button id="downloadPdfButton" class="btn btn-primary">
                <i class="bi bi-file-earmark-pdf me-2"></i>ดาวน์โหลด PDF
            </button>
        </div>
    </div>

    <script>
        document.getElementById('downloadPdfButton').addEventListener('click', function() {
            const element = document.getElementById('printContentWrapper'); // ใช้ wrapper ใหม่
            const overlay = document.getElementById('pdf-loading-overlay');
            overlay.style.display = 'flex';

            let filename = 'Request_';
            <?php if ($print_all_project_requests && $project_id > 0): ?>
                filename = 'Project_<?php echo htmlspecialchars($project_id); ?>_All_Requests.pdf';
            <?php elseif (!empty($all_requests_to_print) && isset($all_requests_to_print[0])): // Access the first item for single print mode ?>
                filename = 'Request_<?php echo htmlspecialchars($all_requests_to_print[0]['request_type_label']); ?>_ID_<?php echo htmlspecialchars($all_requests_to_print[0]['request_id']); ?>.pdf';
            <?php endif; ?>

            const options = {
                margin: 5, // Global margin for the PDF document itself (from html2pdf)
                filename: filename,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: {
                    scale: 3,
                    logging: true,
                    dpi: 192,
                    letterRendering: true,
                    useCORS: true
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
                pagebreak: {
                    mode: ['css', 'avoid-all'] // Respects 'page-break-after: always;' in CSS
                }
            };

            html2pdf().set(options).from(element).save().then(() => {
                overlay.style.display = 'none';
            }).catch(error => {
                console.error("Error generating PDF:", error);
                alert("เกิดข้อผิดพลาดในการสร้างไฟล์ PDF: " + error);
                overlay.style.display = 'none';
            });
        });
    </script>
</body>
</html>