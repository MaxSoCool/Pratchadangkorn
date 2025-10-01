<?php
session_start();

// ตรวจสอบสถานะการ Login และ Role (สามารถปรับแก้ได้ตามความเหมาะสม)
// สำหรับหน้าพิมพ์ หากไม่ต้องการให้ต้อง Login เพื่อดู ให้คอมเมนต์ส่วนนี้ออก
// if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'เจ้าหน้าที่') {
//     header("Location: login.php");
//     exit();
// }

include 'database/database.php'; // ตรวจสอบให้แน่ใจว่า path ถูกต้อง

// Helper function for Thai month names
if (!function_exists('getThaiMonthName')) {
    function getThaiMonthName($month_num) {
        $thai_months_full = [
            '01' => 'มกราคม', '02' => 'กุมภาพันธ์', '03' => 'มีนาคม', '04' => 'เมษายน',
            '05' => 'พฤษภาคม', '06' => 'มิถุนายน', '07' => 'กรกฎาคม', '08' => 'สิงหาคม',
            '09' => 'กันยายน', '10' => 'ตุลาคม', '11' => 'พฤศจิกายน', '12' => 'ธันวาคม'
        ];
        return $thai_months_full[sprintf('%02d', $month_num)] ?? '';
    }
}

// Helper function for Thai date formatting parts specifically for the form
if (!function_exists('formatThaiDatePartForPrint')) {
    function formatThaiDatePartForPrint($date_str, $part) {
        if (!$date_str || $date_str === '0000-00-00 00:00:00' || $date_str === '0000-00-00') {
            return '';
        }
        $dt = new DateTime($date_str);
        if ($part === 'day') return $dt->format('d');
        if ($part === 'month') return getThaiMonthName($dt->format('m'));
        if ($part === 'year') return $dt->format('Y') + 543; // Buddhist year
        if ($part === 'time') return $dt->format('H:i');
        return '';
    }
}

$facility_re_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$request_data = null;
$errors = []; // Initialize errors array for this page

if ($facility_re_id > 0) {
    // Modified SQL to include project description, activity type, user phone, faculty name, advisor name, building name
    $sql = "SELECT
                fr.facility_re_id, fr.project_id, fr.prepare_start_time, fr.prepare_end_time,
                fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time,
                fr.start_date AS fr_start_date, fr.end_date AS fr_end_date, fr.agree,
                fr.writed_status, fr.request_date, fr.approve, fr.approve_date, fr.approve_detail,
                f.facility_name, f.building_id, b.building_name,
                p.project_name, p.project_des, p.activity_type_id, at.activity_type_name, p.advisor_name,
                CONCAT(u.user_THname, ' ', u.user_THsur) AS user_name, u.nontri_id,
                p.phone_num AS user_phone_num, fd.fa_de_name, -- Changed p.phone_num to u.phone_num
                CONCAT(s.staff_THname, ' ', s.staff_THsur) AS staff_name, s.staff_id
            FROM facilities_requests fr
            JOIN facilities f ON fr.facility_id = f.facility_id
            LEFT JOIN buildings b ON f.building_id = b.building_id
            JOIN project p ON fr.project_id = p.project_id
            LEFT JOIN activity_type at ON p.activity_type_id = at.activity_type_id
            LEFT JOIN user u ON p.nontri_id = u.nontri_id
            LEFT JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
            LEFT JOIN staff s ON fr.staff_id = s.staff_id
            WHERE fr.facility_re_id = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $facility_re_id);
        $stmt->execute();
        $request_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        error_log("Failed to prepare statement for admin-print-page.php: " . $conn->error);
        $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $conn->error;
    }
} else {
    $errors[] = "ไม่พบรหัสคำร้องขอใช้สถานที่";
}

$conn->close();

// Default values if data is not found or null
if (empty($request_data)) {
    $request_data = []; // Ensure it's an empty array to prevent undefined index warnings
    $errors[] = "ไม่พบข้อมูลสำหรับคำร้องขอใช้สถานที่นี้";
}

// Extract and prepare data for the form
$request_dt = new DateTime($request_data['request_date'] ?? 'now');
$requester_name = htmlspecialchars($request_data['user_name'] ?? '');
$faculty_name = htmlspecialchars($request_data['fa_de_name'] ?? '');
$requester_phone = htmlspecialchars($request_data['user_phone_num'] ?? '');
$project_name = htmlspecialchars($request_data['project_name'] ?? '');
$project_description = htmlspecialchars($request_data['project_des'] ?? ''); // Use htmlspecialchars directly
$activity_type_name = htmlspecialchars($request_data['activity_type_name'] ?? '');
$agree_reuse = $request_data['agree'] ?? null;

$prepare_start_date = $request_data['prepare_start_date'] ?? null;
$prepare_end_date = $request_data['prepare_end_date'] ?? null;
$prepare_start_time = $request_data['prepare_start_time'] ?? null;
$prepare_end_time = $request_data['prepare_end_time'] ?? null;

$event_start_date = $request_data['fr_start_date'] ?? null;
$event_end_date = $request_data['fr_end_date'] ?? null;
$event_start_time = $request_data['start_time'] ?? null;
$event_end_time = $request_data['end_time'] ?? null;

$facility_name_display = htmlspecialchars($request_data['facility_name'] ?? '');
$building_name_display = htmlspecialchars($request_data['building_name'] ?? '');

$advisor_name = htmlspecialchars($request_data['advisor_name'] ?? '');

$approve_status = $request_data['approve'] ?? null; // 'อนุมัติ' or 'ไม่อนุมัติ' or null
$approve_date_dt = ($request_data['approve_date'] ?? null) ? new DateTime($request_data['approve_date']) : null;
$staff_name_approved = htmlspecialchars($request_data['staff_name'] ?? '');

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบขอใช้อาคาร/สถานที่ (สำหรับนิสิต)</title>
    
    <!-- Google Fonts: Sarabun -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">

    <!-- CSS สำหรับการพิมพ์โดยเฉพาะ -->
    <style>
        /* Base styles */
        body {
            font-family: 'Sarabun', sans-serif;
            font-size: 11pt; /* Adjusted smaller for better fit */
            line-height: 1.4; /* Tighter line height */
            color: #000; /* Black text for print */
            margin: 0;
            padding: 0;
            background-color: #fff; /* White background for print view */
        }

        .form-container {
            max-width: 210mm; /* A4 width */
            margin: 10mm auto; /* A4 margins */
            border: 1px solid #000; /* Main border for the form */
            padding: 5mm; /* Padding inside the main border */
            box-sizing: border-box; /* Include padding in width */
        }

        /* Print-specific overrides (mostly ensuring black text, no background) */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .screen-only {
                display: none !important;
            }
            /* Hidden actual radio/checkbox for print */
            .custom-radio-checkbox {
                -webkit-appearance: none;
                -moz-appearance: none;
                appearance: none;
                border: 1px solid #000;
                width: 12px; /* Smaller checkbox */
                height: 12px; /* Smaller checkbox */
                vertical-align: middle;
                position: relative;
                margin-right: 5px;
                display: inline-block; /* Ensure it stays inline */
                box-sizing: border-box;
            }
            /* Radio buttons are square in the image */
            .custom-radio-checkbox[type="radio"] {
                border-radius: 0; /* Make radio buttons square */
            }
            .custom-radio-checkbox:checked::before {
                content: '•'; /* Small dot for checked */
                display: block;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 10pt; /* Size of the dot */
                line-height: 1; /* Adjust line-height for centering */
                color: #000;
            }
            /* For checkboxes, also use dot as per image */
            .custom-radio-checkbox[type="checkbox"]:checked::before {
                 content: '•'; /* Dot for checked checkbox */
                 font-size: 10pt;
            }

            @page {
                margin: 10mm; /* A4 margins */
            }
            .section, .form-item-block, .signature-area, .notes, .form-container .section-content > p {
                page-break-inside: avoid;
            }
            .form-container {
                border: 1px solid #000; /* Ensure main border on print */
            }
        }

        /* Header Layout */
        .header {
            display: flex;
            align-items: center; /* Vertically align items */
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 5px;
        }

        .header .logo-container {
            flex-shrink: 0; /* Don't allow logo to shrink */
            width: 80px; /* Fixed width for logo container */
            text-align: left;
            margin-right: 10px;
        }

        .header .logo {
            width: 60px; /* Smaller logo */
            height: auto;
            display: block;
        }

        .header .title-info {
            flex-grow: 1; /* Title takes available space */
            text-align: center;
            line-height: 1.2;
        }

        .header .title-info h1 {
            font-size: 14pt; /* Smaller H1 */
            margin: 0;
            font-weight: bold;
        }

        .header .title-info p {
            font-size: 10pt; /* Smaller P */
            margin: 0;
        }

        .header .doc-id {
            flex-shrink: 0; /* Don't allow doc ID to shrink */
            width: 80px; /* Fixed width for doc ID */
            text-align: right;
            font-weight: bold;
            font-size: 10pt;
            white-space: nowrap; /* Prevent wrapping */
        }

        /* Section Header Text */
        .section-header-text {
            font-weight: bold;
            font-size: 11pt;
            margin-top: 10px;
            margin-bottom: 5px;
            padding-bottom: 2px;
            border-bottom: 1px solid #000;
        }

        /* General Content Styling */
        .section-content {
            padding: 0 5mm; /* Reduced padding */
        }

        .section-content p {
            margin-top: 5px;
            margin-bottom: 5px;
            line-height: 1.4;
            font-size: 11pt;
        }

        /* Flex container for form groups, to flow text naturally */
        .form-group {
            display: flex;
            flex-wrap: wrap; /* Allow items to wrap to next line */
            align-items: baseline;
            margin-bottom: 2px; /* Tighter spacing */
            line-height: 1.4;
            font-size: 11pt;
        }

        .form-group .label-text {
            flex-shrink: 0; /* Labels don't shrink */
            margin-right: 5px;
            white-space: nowrap; /* Keep label on one line */
            font-size: 11pt;
            line-height: 1.4;
        }

        .form-group .data-text {
            flex-grow: 1; /* Data takes up available space */
            border-bottom: 1px dotted #000; /* Dotted line under data */
            min-height: 1.2em; /* Ensure some height even if no data */
            padding: 0 2px;
            font-size: 11pt;
            line-height: 1.4;
            white-space: normal; /* Allow data to wrap */
            word-break: break-word; /* Break long words */
            text-align: left; /* Align text to the left */
        }

        /* Special adjustments for date/time parts */
        .date-row {
            width: 100%;
            justify-content: flex-end !important;
            align-items: center;
            margin-bottom: 0;
            gap: 2px;
            flex-wrap: nowrap;
        }
        .date-row .label-text,
        .date-row .data-text {
            min-width: unset;
            width: auto;
            white-space: nowrap;
            font-size: 11pt;
            line-height: 1.4;
            margin: 0 2px;
        }
        .date-row .data-text.date-part {
            min-width: 0;
            text-align: center;
            border-bottom: 1px dotted #000;
            display: inline-block;
            padding: 0 2px;
        }

        /* List items for items 1-4 */
        .form-item-block ol {
            list-style-type: none;
            padding-left: 0;
            margin: 0;
        }
        .form-item-block ol li {
            margin-bottom: 2mm; /* Adjusted margin for list items */
            display: flex;
            align-items: baseline; /* Align first line of content */
            font-size: 11pt;
        }
        .form-item-block ol li > span:first-child {
            flex-shrink: 0;
            width: 15px; /* Number width */
            text-align: right;
            margin-right: 3px;
            font-size: 11pt;
        }

        .option-group {
            display: flex;
            align-items: baseline; /* Align text with radio/checkbox */
            flex-wrap: wrap;
            flex-grow: 1;
            font-size: 11pt;
        }

        .option-item {
            display: flex;
            align-items: baseline;
            margin-right: 15px; /* Spacing between options */
            white-space: nowrap;
        }

        /* Item 3 description */
        .description-line {
            flex-grow: 1;
            border-bottom: 1px dotted #000;
            min-height: 1.2em;
            padding: 0 2px;
            font-size: 11pt;
            line-height: 1.4;
            white-space: normal;
            word-break: break-word;
            text-align: left;
        }

        /* Signature Area */
        .signature-area {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            margin-top: 15mm;
            padding-top: 5mm;
            border-top: 1px dotted #000;
            font-size: 11pt;
        }

        .signature-box {
            flex: 1;
            min-width: 30%; /* Ensure boxes don't get too small */
            max-width: 33%; /* Max width for 3 columns */
            text-align: center;
            margin: 5mm 2mm; /* Smaller margins */
        }

        .signature-line {
            display: inline-block; /* Allow data to sit on a line */
            border-bottom: 1px solid #000; /* Solid line for signature */
            min-width: 100px; /* Minimum width for the line */
            padding: 0 2mm;
            text-align: center;
            font-size: 11pt;
            line-height: 1.4;
            margin-bottom: 2mm; /* Space below line */
        }

        .signature-label-text {
            display: block;
            font-size: 10pt; /* Smaller label text */
            margin-top: 1mm;
        }

        /* Section 2 - Staff part */
        .section2-approval {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
            font-size: 11pt;
        }
        .section2-approval > div {
            flex: 1;
        }
        .approval-options {
            display: flex;
            align-items: baseline; /* Align text with radio */
            flex-wrap: wrap;
            font-size: 11pt;
        }
        .approval-options .option-item {
            margin-right: 15px;
        }
        .approval-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 11pt;
        }

        /* Signature area for staff */
        .staff-signature-area {
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            margin-top: 10mm;
            font-size: 11pt;
        }
        .staff-signature-box {
            flex: 1;
            min-width: 45%; /* For 2 columns */
            max-width: 50%;
            text-align: center;
            margin: 5mm 2mm;
        }
        .staff-signature-box .signature-line {
            margin-bottom: 1mm;
        }
        .staff-signature-box .signature-date-group {
            margin-top: 5mm;
            justify-content: flex-start; /* Align date left under signature */
        }

        /* Notes Section */
        .notes {
            margin-top: 10mm;
            padding-top: 5mm;
            border-top: 1px solid #ccc; /* Lighter border for notes */
            font-size: 10pt; /* Smaller font for notes */
        }
        .notes ol {
            list-style-type: decimal;
            padding-left: 15mm;
            margin: 0;
        }
        .notes ol li {
            margin-bottom: 2mm;
            line-height: 1.3;
        }
        .notes p {
            font-size: 11pt;
            font-weight: bold;
        }
        .notes p:first-child {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>

    <?php if (!empty($errors)): ?>
        <div class="form-container" style="background-color: #ffe0e0; border-color: #ff0000; color: #ff0000; text-align: center;">
            <h3>เกิดข้อผิดพลาดในการแสดงแบบฟอร์ม:</h3>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <p class="screen-only">กรุณากลับไปที่หน้าหลักและลองอีกครั้ง</p>
        </div>
    <?php else: ?>
        <div class="form-container">
            <div class="header">
                <div class="logo-container">
                    <img src="images/ku_print_logo.png" alt="โลโก้ มหาวิทยาลัยเกษตรศาสตร์ วิทยาเขตเฉลิมพระเกียรติ จังหวัดสกลนคร" class="logo">
                </div>
                <div class="title-info">
                    <h1>แบบขอใช้อาคาร/สถานที่ (สำหรับนิสิต)</h1>
                    <p>สำนักงานวิทยาเขต กองบริการกลาง งานอาคารสถานที่และยานพาหนะ</p>
                    <p>โทร. 0-4272-5089 โทรสาร 0-4272-5088</p>
                </div>
                <div class="doc-id">OCC04-08.1</div>
            </div>

            <!-- ส่วนที่ 1 สำหรับผู้ขอใช้บริการ -->
            <div class="section">
                <div class="section-header-text">ส่วนที่ 1 สำหรับผู้ขอใช้บริการ</div>
                <div class="section-content">
                    <p>เรียน หัวหน้างานอาคารสถานที่และยานพาหนะ</p>

                    <div class="form-group date-row" style="width:100%; justify-content:flex-end; margin-bottom: 0;">
                        <span class="label-text">วันที่</span>
                        <span class="data-text date-part" style="min-width:18px;"><?php echo formatThaiDatePartForPrint($request_dt->format('Y-m-d'), 'day'); ?></span>
                        <span class="label-text">เดือน</span>
                        <span class="data-text date-part" style="min-width:40px;"><?php echo formatThaiDatePartForPrint($request_dt->format('Y-m-d'), 'month'); ?></span>
                        <span class="label-text">พ.ศ.</span>
                        <span class="data-text date-part" style="min-width:28px;"><?php echo formatThaiDatePartForPrint($request_dt->format('Y-m-d'), 'year'); ?></span>
                    </div>

                    <div class="form-group">
                        <span class="label-text">ข้าพเจ้า</span>
                        <span class="data-text" style="flex-grow: 3;"><?php echo $requester_name; ?></span>
                        <span class="label-text">คณะ</span>
                        <span class="data-text" style="flex-grow: 2;"><?php echo $faculty_name; ?></span>
                        <span class="label-text">สาขาวิชา</span>
                        <span class="data-text" style="flex-grow: 2;"></span> <!-- สาขาวิชาไม่ต้องเอามา -->
                        <span class="label-text">โทร</span>
                        <span class="data-text" style="flex-grow: 1;"><?php echo $requester_phone; ?></span>
                    </div>

                    <div class="form-group">
                        <span class="label-text">มีความประสงค์ขอใช้สถานที่ในโครงการ/งาน/หน่วย</span>
                        <span class="data-text"><?php echo $project_name; ?></span>
                    </div>

                    <div class="form-group">
                        <span class="label-text">วันที่เตรียมงาน</span>
                        <span class="data-text date-part" style="width: 25px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($prepare_start_date, 'day'); ?></span>
                        <span class="label-text">เดือน</span>
                        <span class="data-text date-part" style="width: 50px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($prepare_start_date, 'month'); ?></span>
                        <span class="label-text">พ.ศ.</span>
                        <span class="data-text date-part" style="width: 35px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($prepare_start_date, 'year'); ?></span>
                        <span class="label-text">ถึง</span>
                        <span class="data-text date-part" style="width: 25px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($prepare_end_date, 'day'); ?></span>
                        <span class="label-text">เดือน</span>
                        <span class="data-text date-part" style="width: 50px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($prepare_end_date, 'month'); ?></span>
                        <span class="label-text">พ.ศ.</span>
                        <span class="data-text date-part" style="width: 35px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($prepare_end_date, 'year'); ?></span>
                        <span class="label-text">ระหว่างเวลา</span>
                        <span class="data-text time-part" style="width: 30px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($prepare_start_time, 'time'); ?></span>
                        <span class="label-text">น. -</span>
                        <span class="data-text time-part" style="width: 30px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($prepare_end_time, 'time'); ?></span>
                        <span class="label-text">น.</span>
                    </div>

                    <div class="form-group">
                        <span class="label-text">วันที่จัดงาน</span>
                        <span class="data-text date-part" style="width: 25px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($event_start_date, 'day'); ?></span>
                        <span class="label-text">เดือน</span>
                        <span class="data-text date-part" style="width: 50px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($event_start_date, 'month'); ?></span>
                        <span class="label-text">พ.ศ.</span>
                        <span class="data-text date-part" style="width: 35px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($event_start_date, 'year'); ?></span>
                        <span class="label-text">ถึง</span>
                        <span class="data-text date-part" style="width: 25px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($event_end_date, 'day'); ?></span>
                        <span class="label-text">เดือน</span>
                        <span class="data-text date-part" style="width: 50px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($event_end_date, 'month'); ?></span>
                        <span class="label-text">พ.ศ.</span>
                        <span class="data-text date-part" style="width: 35px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($event_end_date, 'year'); ?></span>
                        <span class="label-text">ระหว่างเวลา</span>
                        <span class="data-text time-part" style="width: 30px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($event_start_time, 'time'); ?></span>
                        <span class="label-text">น. -</span>
                        <span class="data-text time-part" style="width: 30px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($event_end_time, 'time'); ?></span>
                        <span class="label-text">น.</span>
                    </div>

                    <p style="margin-top: 10px; margin-bottom: 5px;">และโปรดดำเนินการดังนี้</p>

                    <div class="form-item-block">
                        <ol>
                            <li>
                                <span>1.</span>
                                <div class="option-group">
                                    <span class="label-text">อาคาร/สถานที่</span>
                                    <input type="radio" name="building_option" class="custom-radio-checkbox" <?php echo ($facility_name_display || $building_name_display) ? 'checked' : ''; ?> readonly>
                                    <span class="data-text" style="flex-grow: 1;"><?php echo $building_name_display . ($building_name_display && $facility_name_display ? ' ' : '') . $facility_name_display; ?></span>
                                    <span class="label-text" style="margin-left: 20px;">อื่นๆ</span>
                                    <input type="radio" name="building_option" class="custom-radio-checkbox" <?php echo (!($facility_name_display || $building_name_display)) ? 'checked' : ''; ?> readonly>
                                    <span class="data-text" style="flex-grow: 1;"></span>
                                </div>
                            </li>
                            <li>
                                <span>2.</span>
                                <div class="option-group">
                                    <span class="label-text">ประเภทของกิจกรรม</span>
                                    <input type="radio" name="activity_type" class="custom-radio-checkbox" <?php echo ($activity_type_name == 'การเรียนการสอน') ? 'checked' : ''; ?> readonly>
                                    <span class="label-text">การเรียนการสอน (นอกตารางเรียน)</span>
                                    <input type="radio" name="activity_type" class="custom-radio-checkbox" <?php echo ($activity_type_name == 'กิจกรรมพิเศษ' || $activity_type_name == 'กิจกรรมนิสิต' || $activity_type_name == 'การประชุม' || ($activity_type_name != 'การเรียนการสอน' && $activity_type_name != '')) ? 'checked' : ''; ?> readonly>
                                    <span class="label-text">กิจกรรมพิเศษ</span>
                                </div>
                            </li>
                            <li>
                                <span>3.</span>
                                <p style="margin: 0; padding-left: 0; display: flex; align-items: baseline;">
                                    <span class="label-text" style="flex-shrink: 0;">ให้แนบรายละเอียดและชื่อของกิจกรรม/โครงการที่อนุมัติแล้วมาด้วย</span>
                                    <span class="description-line"><?php echo $project_description; ?></span>
                                </p>
                            </li>
                            <li>
                                <span>4.</span>
                                <div class="option-group">
                                    <span class="label-text">ยินยอมให้นำวัสดุ โครงป้ายไวนิล นำไปใช้ Reuse</span>
                                    <input type="checkbox" name="reuse_option" class="custom-radio-checkbox" <?php echo ($agree_reuse == 1) ? 'checked' : ''; ?> readonly>
                                    <span class="label-text">ยินยอม</span>
                                    <input type="checkbox" name="reuse_option" class="custom-radio-checkbox" <?php echo ($agree_reuse === 0) ? 'checked' : ''; ?> readonly>
                                    <span class="label-text">ไม่ยินยอม (ต้องเก็บคืนภายใน 7 วัน)</span>
                                </div>
                            </li>
                        </ol>
                    </div>

                    <p style="margin-top: 10px;">ทั้งนี้ข้าพเจ้าจะรับผิดชอบต่อความเสียหายของวัสดุอุปกรณ์ภายในอาคาร/สถานที่ ที่ขอใช้บริการ และจะควบคุมการจัด<br>
                    สถานที่ ทั้งก่อนและหลังเสร็จงานให้อยู่ในสภาพเดิม และเก็บขยะ เศษอุปกรณ์ รอบบริเวณให้เรียบร้อยก่อนติดต่อรับบัตรคืน</p>

                    <div class="signature-area">
                        <div class="signature-box">
                            <span class="label-text">ลงชื่อ</span>
                            <span class="signature-line"><?php echo $requester_name; ?></span>
                            <span class="signature-label-text">(<span class="signature-line"></span>)</span>
                            <span class="signature-label-text">ผู้ขอใช้บริการ</span>
                        </div>
                        <div class="signature-box">
                            <span class="label-text">ลงชื่อ</span>
                            <span class="signature-line"></span>
                            <span class="signature-label-text">(<span class="signature-line"></span>)</span>
                            <span class="signature-label-text">หัวหน้าหน่วยพัฒนาและกิจกรรมนิสิต</span>
                        </div>
                        <div class="signature-box">
                            <span class="label-text">ลงชื่อ</span>
                            <span class="signature-line"><?php echo $advisor_name; ?></span>
                            <span class="signature-label-text">(<span class="signature-line"></span>)</span>
                            <span class="signature-label-text">อาจารย์ที่ปรึกษา</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ส่วนที่ 2 สำหรับเจ้าหน้าที่ -->
            <div class="section">
                <div class="section-header-text" style="margin-top: 10px;">ส่วนที่ 2 สำหรับเจ้าหน้าที่</div>
                <div class="section-content">
                    <div class="section2-approval">
                        <div style="flex-grow: 1.5;">
                            <p style="margin-top: 0; line-height: 1.4;">1.เรียน หัวหน้างานอาคารสถานที่และยานพาหนะ<br>เพื่อโปรดพิจารณา</p>
                        </div>
                        <div style="flex-grow: 1;">
                            <p class="approval-title" style="margin-top: 0;">2.ผู้มีอำนาจอนุมัติ</p>
                            <div class="approval-options">
                                <div class="option-item">
                                    <input type="radio" name="approval_status" class="custom-radio-checkbox" <?php echo ($approve_status == 'อนุมัติ') ? 'checked' : ''; ?> readonly>
                                    <span class="label-text">อนุมัติ</span>
                                </div>
                                <div class="option-item">
                                    <input type="radio" name="approval_status" class="custom-radio-checkbox" <?php echo ($approve_status == 'ไม่อนุมัติ') ? 'checked' : ''; ?> readonly>
                                    <span class="label-text">ไม่อนุมัติ</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="staff-signature-area">
                        <div class="staff-signature-box">
                            <span class="label-text">ลงชื่อ</span>
                            <span class="signature-line"></span>
                            <span class="signature-label-text">(<span class="signature-line"></span>)</span>
                            <span class="signature-label-text">ผู้จัดการกลุ่มอาคาร</span>
                            <div class="signature-date-group">
                                <span class="label-text">วันที่</span>
                                <span class="data-text date-part" style="width: 25px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($approve_date_dt ? $approve_date_dt->format('Y-m-d') : null, 'day'); ?></span>
                                <span class="label-text">/</span>
                                <span class="data-text date-part" style="width: 25px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($approve_date_dt ? $approve_date_dt->format('Y-m-d') : null, 'month'); ?></span>
                                <span class="label-text">/</span>
                                <span class="data-text date-part" style="width: 25px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($approve_date_dt ? $approve_date_dt->format('Y-m-d') : null, 'year'); ?></span>
                            </div>
                        </div>
                        <div class="staff-signature-box">
                            <span class="label-text">ลงชื่อ</span>
                            <span class="signature-line"><?php echo $staff_name_approved; ?></span>
                            <span class="signature-label-text">(<span class="signature-line"></span>)</span>
                            <span class="signature-label-text">หัวหน้างานอาคารสถานที่และยานพาหนะ</span>
                            <div class="signature-date-group">
                                <span class="label-text">วันที่</span>
                                <span class="data-text date-part" style="width: 25px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($approve_date_dt ? $approve_date_dt->format('Y-m-d') : null, 'day'); ?></span>
                                <span class="label-text">/</span>
                                <span class="data-text date-part" style="width: 25px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($approve_date_dt ? $approve_date_dt->format('Y-m-d') : null, 'month'); ?></span>
                                <span class="label-text">/</span>
                                <span class="data-text date-part" style="width: 25px; flex-grow: 0;"><?php echo formatThaiDatePartForPrint($approve_date_dt ? $approve_date_dt->format('Y-m-d') : null, 'year'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- หมายเหตุ -->
            <div class="notes">
                <p>หมายเหตุ:</p>
                <ol>
                    <li>โปรดยื่นล่วงหน้าไม่น้อยกว่า 2 วันทำการ และต้องเก็บเศษขยะ เศษอุปกรณ์ต่าง ๆ รอบบริเวณอาคารทันที หลังเสร็จกิจกรรม</li>
                    <li>หากต้องการใช้โสตทัศนูปกรณ์ โปรดติดต่อ งานเทคโนโลยีสารสนเทศ อาคาร 9</li>
                    <li>หากต้องการใช้ห้องเรียน/ห้องพระพิ สาคริก โปรดติดต่อ งานทะเบียนและประมวลผล อาคาร 9</li>
                </ol>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Automatically print when the page loads
        window.onload = function() {
            if (<?php echo json_encode(empty($errors)); ?>) { // Only print if there are no errors
                 window.print();
                 // Optional: Close the tab after printing if desired
                 // window.onafterprint = function() {
                 //     window.close();
                 // }
            }
        };
    </script>
</body>
</html>