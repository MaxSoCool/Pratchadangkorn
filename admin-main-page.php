<?php
session_start();

// ตรวจสอบสถานะการ Login และ Role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'เจ้าหน้าที่') {
    header("Location: login.php");
    exit();
}

include 'database/database.php';
include 'php/sorting.php';
include 'php/admin-sorting.php';
include 'php/chart-sorting.php'; // เพิ่มการเรียกใช้ chart_sorting.php

// Helper functions for Thai date formatting (These are now for screen display in admin-main-page.php)
if (!function_exists('formatThaiDate')) {
    function formatThaiDate($date_str, $with_time = true) {
        if (!$date_str || $date_str === '0000-00-00 00:00:00' || $date_str === '0000-00-00') {
            return 'N/A';
        }
        $thai_months = [
            '01' => 'ม.ค.', '02' => 'ก.พ.', '03' => 'มี.ค.', '04' => 'เม.ย.',
            '05' => 'พ.ค.', '06' => 'มิ.ย.', '07' => 'ก.ค.', '08' => 'ส.ค.',
            '09' => 'ก.ย.', '10' => 'ต.ค.', '11' => 'พ.ย.', '12' => 'ธ.ค.'
        ];
        $timestamp = strtotime($date_str);
        $day = date('d', $timestamp);
        $month = $thai_months[date('m', $timestamp)];
        $year = date('Y', $timestamp) + 543; // Buddhist year
        $time = date('H:i', $timestamp);

        if ($with_time) {
            return "{$day} {$month} {$year} เวลา {$time} น.";
        } else {
            return "{$day} {$month} {$year}";
        }
    }
}

if (!function_exists('getStatusBadgeClass')) {
    function getStatusBadgeClass($writed_status, $approve_status = null) {
        if ($approve_status === 'อนุมัติ') {
            return 'bg-success';
        } elseif ($approve_status === 'ไม่อนุมัติ') {
            return 'bg-danger';
        }

        switch ($writed_status) {
            case 'ร่างโครงการ':
            case 'ร่างคำร้องขอ':
                return 'bg-warning text-dark';
            case 'ส่งโครงการ':
            case 'ส่งคำร้องขอ':
                return 'bg-primary';
            case 'เริ่มดำเนินการ':
                return 'bg-info text-dark';
            case 'สิ้นสุดโครงการ':
                return 'bg-secondary';
            case 'ยกเลิกคำร้องขอ':
                return 'bg-dark';
            default:
                return 'bg-light text-dark';
        }
    }
}


$staff_id_for_db = $_SESSION['staff_id'] ?? null;
$staff_name = htmlspecialchars($_SESSION['user_display_name'] ?? 'N/A');
$staff_sur = htmlspecialchars($_SESSION['user_display_sur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');

if (empty($staff_id_for_db) || $staff_id_for_db === 'N/A') {
    $errors[] = "ไม่สามารถดำเนินการได้: Staff ID ไม่ถูกต้องหรือไม่พบสำหรับผู้ดูแลระบบที่ล็อกอินอยู่ โปรดตรวจสอบการตั้งค่าผู้ใช้หรือติดต่อผู้ดูแลระบบ.";
}

$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search_query . '%';

// เพิ่มตัวแปรสำหรับ Date Filtering
$predefined_range_select = $_GET['predefined_range'] ?? null;
$specific_year_select = $_GET['specific_year'] ?? null;
$specific_month_select = $_GET['specific_month'] ?? null;
$specific_day_select = $_GET['specific_day'] ?? null;

$fa_de_id_filter_global = $_GET['fa_de_id_global'] ?? null; // Changed name to avoid conflict with chart drilldown

$main_tab = isset($_GET['main_tab']) ? $_GET['main_tab'] : 'dashboard_admin';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list';

$data = [];
$detail_item = null;
$total_items = 0;
$errors = [];
$success_message = '';

// New chart-specific sorting parameters
$chart_sort_mode = $_GET['chart_sort_mode'] ?? 'faculty_overview';
$drilldown_type = $_GET['drilldown_type'] ?? null; // Still in URL for potential direct access, but not used by UI clicks
$drilldown_id = $_GET['drilldown_id'] ?? null;     // Still in URL for potential direct access, but not used by UI clicks

$activity_types = [];
$result_activity = $conn->query("SELECT activity_type_id, activity_type_name FROM activity_type ORDER BY activity_type_name");
if ($result_activity) {
    while ($row = $result_activity->fetch_assoc()) {
        $activity_types[] = $row;
    }
}

$facilities_dropdown = [];
$result_facilities_dropdown = $conn->query("SELECT facility_id, facility_name FROM facilities ORDER BY facility_name ASC");
if ($result_facilities_dropdown) {
    while ($row = $result_facilities_dropdown->fetch_assoc()) {
        $facilities_dropdown[] = $row;
    }
}

$equipments_dropdown = [];
$result_equipments_dropdown = $conn->query("SELECT equip_id, equip_name, measure FROM equipments ORDER BY equip_name ASC");
if ($result_equipments_dropdown) {
    while ($row = $result_equipments_dropdown->fetch_assoc()) {
        $equipments_dropdown[] = $row;
    }
}

$faculties_for_chart_filter = [];
$result_fa_de = $conn->query("SELECT fa_de_id, fa_de_name FROM faculties_department ORDER BY fa_de_name ASC");
if ($result_fa_de) {
    while ($row = $result_fa_de->fetch_assoc()) {
        $faculties_for_chart_filter[] = $row;
    }
}


$total_projects_count = 0;
$total_facilities_requests_count = 0;
$total_equipments_requests_count = 0;


$dashboard_data = [
    'upcoming_requests' => [],
    'recent_activity' => [],
];


// --- เริ่มตรรกะสำหรับการกำหนดค่า $previous (สำหรับปุ่มย้อนกลับ) ---
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$previous = 'admin-main-page.php?main_tab=dashboard_admin'; // ค่าเริ่มต้นปลอดภัย: กลับไป Dashboard เสมอ

// ตรวจสอบว่า Referrer เป็นหน้า admin-main-page.php หรือไม่
$is_admin_referrer = (strpos($referrer, 'admin-main-page.php') !== false);

// Parse referrer query parameters
$referrer_url_parts = parse_url($referrer);
$referrer_query = [];
if (isset($referrer_url_parts['query'])) {
    parse_str($referrer_url_parts['query'], $referrer_query);
}

// ตรรกะหลัก: กำหนดค่า $previous
if ($mode === 'detail') {
    // 1. กำลังอยู่ในหน้า "รายละเอียดโครงการ" (projects_admin&mode=detail)
    if ($main_tab === 'projects_admin') {
        // หากมาจาก Dashboard ให้บันทึก Dashboard เป็นจุดเริ่มต้นใน session
        if ($is_admin_referrer && ($referrer_query['main_tab'] ?? '') === 'dashboard_admin' && !isset($referrer_query['mode'])) {
            $_SESSION['projects_detail_entry_referrer'] = 'admin-main-page.php?main_tab=dashboard_admin';
            $previous = 'admin-main-page.php?main_tab=dashboard_admin'; // กลับไป Dashboard
        }
        // หากมาจาก Project List ให้บันทึก Project List เป็นจุดเริ่มต้น
        elseif ($is_admin_referrer && ($referrer_query['main_tab'] ?? '') === 'projects_admin' && (!isset($referrer_query['mode']) || $referrer_query['mode'] === 'list')) {
            $_SESSION['projects_detail_entry_referrer'] = 'admin-main-page.php?main_tab=projects_admin';
            $previous = 'admin-main-page.php?main_tab=projects_admin'; // กลับไป Project List
        }
        // หากกลับมาจากหน้ารายละเอียดคำร้องขอ (buildings/equipments detail)
        // หรือกรณีอื่นๆ ที่ session มีค่าอยู่แล้ว (หมายถึงเคยมาจาก Dashboard หรือ Project List)
        elseif (isset($_SESSION['projects_detail_entry_referrer'])) {
            $previous = $_SESSION['projects_detail_entry_referrer']; // ใช้ referrer ที่บันทึกไว้
        }
        // Fallback หากไม่มี session หรือ referrer ที่ชัดเจน ให้กลับไป Project List
        else {
            $previous = 'admin-main-page.php?main_tab=projects_admin';
        }
    }
    // 2. กำลังอยู่ในหน้า "รายละเอียดคำร้องขอสถานที่/อุปกรณ์" (buildings_admin&mode=detail หรือ equipments_admin&mode=detail)
    elseif ($main_tab === 'buildings_admin' || $main_tab === 'equipments_admin') {
        // หากมาจากหน้า "รายละเอียดโครงการ"
        if ($is_admin_referrer && ($referrer_query['main_tab'] ?? '') === 'projects_admin' && ($referrer_query['mode'] ?? '') === 'detail') {
            $previous = $referrer; // กลับไปที่หน้า 'รายละเอียดโครงการ' นั้นๆ
        }
        // หากมาจาก Dashboard
        elseif ($is_admin_referrer && ($referrer_query['main_tab'] ?? '') === 'dashboard_admin' && !isset($referrer_query['mode'])) {
            $previous = 'admin-main-page.php?main_tab=dashboard_admin'; // กลับไปที่ Dashboard
        }
        // หากมาจากหน้า List ของแท็บนั้นๆ
        elseif ($is_admin_referrer && ($referrer_query['main_tab'] ?? '') === $main_tab && (!isset($referrer_query['mode']) || $referrer_query['mode'] === 'list')) {
            $previous = 'admin-main-page.php?main_tab=' . htmlspecialchars($main_tab); // กลับไปหน้า List ของแท็บนั้นๆ
        }
        // Fallback: กลับไปหน้า List ของแท็บนั้น (ถ้าไม่เข้าเงื่อนไขใดๆ ข้างต้น)
        else {
            $previous = 'admin-main-page.php?main_tab=' . htmlspecialchars($main_tab);
        }
    }
}
// 3. กำลังอยู่ในหน้า "List" หรือ "Dashboard" (mode !== 'detail')
else {
    // ล้าง session entry referrer เมื่อไม่ได้อยู่ใน project detail หรือ sub-detail
    unset($_SESSION['projects_detail_entry_referrer']);

    if ($is_admin_referrer) {
        // หากมาจากหน้า login หรือไม่มี referrer (เริ่มต้น)
        if (empty($referrer) || strpos($referrer, 'login.php') !== false) {
             $previous = 'admin-main-page.php?main_tab=dashboard_admin';
        }
        // หากมาจากหน้า Admin อื่นๆ ที่ไม่ใช่ detail page ที่กำลังจะกลับไป (เช่น list ไป list)
        // หรือมาจากหน้า detail ที่ต้องการให้กลับไป list
        elseif (isset($referrer_query['mode']) && $referrer_query['mode'] === 'detail') {
            // หาก referrer เป็นหน้ารายละเอียด (เช่นกดกลับจาก detail มา list) ให้กลับไปที่ list ของแท็บนั้น
            $previous = 'admin-main-page.php?main_tab=' . htmlspecialchars($referrer_query['main_tab'] ?? 'dashboard_admin');
        } else {
            // หากเป็น Admin referrer ทั่วไป ให้ใช้ referrer นั้น
            $previous = $referrer;
        }
    }
    // หาก referrer ไม่ใช่ Admin page หรือว่างเปล่า ให้กลับไป Dashboard
    else {
        $previous = 'admin-main-page.php?main_tab=dashboard_admin';
    }
}
// --- สิ้นสุดตรรกะสำหรับการกำหนดค่า $previous ---


// 4. กิจกรรมที่กำลังจะมาถึง (เฉพาะโครงการ ภายใน 30 วันข้างหน้า)
$upcoming_date_limit = date('Y-m-d', strtotime('+14 days'));
$current_date_php = date('Y-m-d');

// SQL สำหรับโครงการที่กำลังจะมาถึง (ยังคงกรองตามสถานะและวันที่)
// เพิ่ม LEFT JOIN user U ON P.nontri_id = U.nontri_id เพื่อกรองตามคณะ
$sql_upcoming_projects = "SELECT p.project_id AS id, 'โครงการ' AS type, p.project_name AS name,
                            p.start_date, p.end_date, NULL AS start_time, NULL AS end_time,
                            p.project_name AS project_name_for_display, p.writed_status AS writed_status_for_display, NULL AS approve_for_display
                    FROM project p
                    LEFT JOIN user u ON p.nontri_id = u.nontri_id
                    WHERE p.start_date BETWEEN ? AND ?
                    AND (p.writed_status = 'ส่งโครงการ' OR p.writed_status = 'เริ่มดำเนินการ')";

$upcoming_proj_params = [$current_date_php, $upcoming_date_limit];
$upcoming_proj_param_types = "ss";

if (!empty($fa_de_id_filter_global)) { // ใช้ fa_de_id_filter_global
    $sql_upcoming_projects .= " AND u.fa_de_id = ?";
    $upcoming_proj_params[] = (int)$fa_de_id_filter_global;
    $upcoming_proj_param_types .= "i";
}
$sql_upcoming_projects .= " ORDER BY p.start_date ASC";

$stmt_upcoming_projects = $conn->prepare($sql_upcoming_projects);
if ($stmt_upcoming_projects) {
    $stmt_upcoming_projects->bind_param($upcoming_proj_param_types, ...$upcoming_proj_params);
    $stmt_upcoming_projects->execute();
    $result_upcoming_projects = $stmt_upcoming_projects->get_result();
    while ($row = $result_upcoming_projects->fetch_assoc()) {
        $dashboard_data['upcoming_requests'][] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'name' => $row['name'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'project_name' => $row['name'],
            'writed_status' => $row['writed_status_for_display'],
            'approve' => $row['approve_for_display'],
        ];
    }
    $stmt_upcoming_projects->close();
} else {
    error_log("Failed to prepare statement for upcoming projects: " . $conn->error);
}

// จัดเรียงกิจกรรมที่กำลังจะมาถึงและจำกัดจำนวน
// ตรวจสอบว่า $dashboard_data['upcoming_requests'] ถูกตั้งค่าและเป็น array ก่อนใช้งาน
if (isset($dashboard_data['upcoming_requests']) && is_array($dashboard_data['upcoming_requests'])) {
    usort($dashboard_data['upcoming_requests'], function($a, $b) {
        return strtotime($a['start_date']) - strtotime($b['start_date']);
    });
    $dashboard_data['upcoming_requests'] = array_slice($dashboard_data['upcoming_requests'], 0, 5);
} else {
    $dashboard_data['upcoming_requests'] = []; // กำหนดให้เป็น array ว่างถ้าไม่มีข้อมูล
}


$all_recent_activity_raw = []; // Initialize $all_recent_activity_raw as an empty array

// คำร้องขอสถานที่ล่าสุด
$sql_recent_fr = "SELECT fr.facility_re_id AS id, 'คำร้องขอสถานที่' AS item_type, f.facility_name AS item_name,
                        fr.request_date AS activity_date, fr.writed_status AS status_text, fr.approve AS approve_status
                FROM facilities_requests fr
                JOIN project p ON fr.project_id = p.project_id
                JOIN facilities f ON fr.facility_id = f.facility_id
                LEFT JOIN user u ON p.nontri_id = u.nontri_id
                WHERE fr.writed_status = 'ส่งคำร้องขอ' AND fr.approve IS NULL";

$recent_fr_params = [];
$recent_fr_param_types = "";
if (!empty($fa_de_id_filter_global)) { // ใช้ fa_de_id_filter_global
    $sql_recent_fr .= " AND u.fa_de_id = ?";
    $recent_fr_params[] = (int)$fa_de_id_filter_global;
    $recent_fr_param_types .= "i";
}
$sql_recent_fr .= " ORDER BY fr.request_date DESC LIMIT 5";

$stmt_recent_fr = $conn->prepare($sql_recent_fr);
if ($stmt_recent_fr) {
    if (!empty($recent_fr_param_types)) {
        $stmt_recent_fr->bind_param($recent_fr_param_types, ...$recent_fr_params);
    }
    $stmt_recent_fr->execute();
    $result_recent_fr = $stmt_recent_fr->get_result();
    while ($row = $result_recent_fr->fetch_assoc()) {
        $all_recent_activity_raw[] = $row;
    }
    $stmt_recent_fr->close();
} else {
    error_log("Failed to prepare statement for recent facility requests: " . $conn->error);
}

// คำร้องขออุปกรณ์ล่าสุด
$sql_recent_er = "SELECT er.equip_re_id AS id, 'คำร้องขออุปกรณ์' AS item_type, e.equip_name AS item_name,
                        er.request_date AS activity_date, er.writed_status AS status_text, er.approve AS approve_status
                FROM equipments_requests er
                JOIN project p ON er.project_id = p.project_id
                JOIN equipments e ON er.equip_id = e.equip_id
                LEFT JOIN user u ON p.nontri_id = u.nontri_id
                WHERE er.writed_status = 'ส่งคำร้องขอ' AND er.approve IS NULL";

$recent_er_params = [];
$recent_er_param_types = "";
if (!empty($fa_de_id_filter_global)) { // ใช้ fa_de_id_filter_global
    $sql_recent_er .= " AND u.fa_de_id = ?";
    $recent_er_params[] = (int)$fa_de_id_filter_global;
    $recent_er_param_types .= "i";
}
$sql_recent_er .= " ORDER BY er.request_date DESC LIMIT 5";

$stmt_recent_er = $conn->prepare($sql_recent_er);
if ($stmt_recent_er) {
    if (!empty($recent_er_param_types)) {
        $stmt_recent_er->bind_param($recent_er_param_types, ...$recent_er_params);
    }
    $stmt_recent_er->execute();
    $result_recent_er = $stmt_recent_er->get_result();
    while ($row = $result_recent_er->fetch_assoc()) {
        $all_recent_activity_raw[] = $row;
    }
    $stmt_recent_er->close();
} else {
    error_log("Failed to prepare statement for recent equipment requests: " . $conn->error);
}

// ตรวจสอบว่า $all_recent_activity_raw ถูกตั้งค่าและเป็น array ก่อนใช้งาน
if (isset($all_recent_activity_raw) && is_array($all_recent_activity_raw)) {
    usort($all_recent_activity_raw, function($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });
    $dashboard_data['recent_activity'] = array_slice($all_recent_activity_raw, 0, 5);
} else {
    $dashboard_data['recent_activity'] = []; // กำหนดให้เป็น array ว่างถ้าไม่มีข้อมูล
}


try {
    // --- ส่วนการกรองวันที่สำหรับ Dashboard Counts ---
    $date_filter_params_proj = getDateFilteringClauses('dashboard_projects', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);
    $date_filter_params_fr = getDateFilteringClauses('dashboard_facilities_requests', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);
    $date_filter_params_er = getDateFilteringClauses('dashboard_equipments_requests', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);

    // โครงการทั้งหมด (ยกเว้นร่างโครงการ)
    $sql_proj_count = "SELECT COUNT(*) FROM project p LEFT JOIN user u ON p.nontri_id = u.nontri_id WHERE p.writed_status != 'ร่างโครงการ' " . ($date_filter_params_proj['where_sql'] ?? '');
    $count_params_proj = [];
    $count_param_types_proj = '';

    if (!empty($date_filter_params_proj['param_values'])) {
        $count_params_proj = array_merge($count_params_proj, $date_filter_params_proj['param_values']);
    }
    if (!empty($date_filter_params_proj['param_types'])) {
        $count_param_types_proj .= $date_filter_params_proj['param_types'];
    }
    
    if (!empty($fa_de_id_filter_global)) { // ใช้ fa_de_id_filter_global
        $sql_proj_count .= " AND u.fa_de_id = ?";
        $count_params_proj[] = (int)$fa_de_id_filter_global;
        $count_param_types_proj .= 'i';
    }

    $stmt_proj_count = $conn->prepare($sql_proj_count);
    if ($stmt_proj_count) {
        if (!empty($count_param_types_proj)) {
            $stmt_proj_count->bind_param($count_param_types_proj, ...$count_params_proj);
        }
        $stmt_proj_count->execute();
        $stmt_proj_count->bind_result($total_projects_count);
        $stmt_proj_count->fetch();
        $stmt_proj_count->close();
    } else {
        error_log("Failed to prepare statement for projects count: " . $conn->error);
    }

    // คำร้องขอใช้สถานที่ทั้งหมด (ยกเว้นร่างคำร้องขอ)
    $sql_fac_req_count = "SELECT COUNT(*) FROM facilities_requests fr JOIN project p ON fr.project_id = p.project_id LEFT JOIN user u ON p.nontri_id = u.nontri_id WHERE fr.writed_status != 'ร่างคำร้องขอ' " . ($date_filter_params_fr['where_sql'] ?? '');
    $count_params_fr = [];
    $count_param_types_fr = '';

    if (!empty($date_filter_params_fr['param_values'])) {
        $count_params_fr = array_merge($count_params_fr, $date_filter_params_fr['param_values']);
    }
    if (!empty($date_filter_params_fr['param_types'])) {
        $count_param_types_fr .= $date_filter_params_fr['param_types'];
    }

    if (!empty($fa_de_id_filter_global)) { // ใช้ fa_de_id_filter_global
        $sql_fac_req_count .= " AND u.fa_de_id = ?";
        $count_params_fr[] = (int)$fa_de_id_filter_global;
        $count_param_types_fr .= 'i';
    }

    $stmt_fac_req_count = $conn->prepare($sql_fac_req_count);
    if ($stmt_fac_req_count) {
        if (!empty($count_param_types_fr)) {
            $stmt_fac_req_count->bind_param($count_param_types_fr, ...$count_params_fr);
        }
        $stmt_fac_req_count->execute();
        $stmt_fac_req_count->bind_result($total_facilities_requests_count);
        $stmt_fac_req_count->fetch();
        $stmt_fac_req_count->close();
    } else {
        error_log("Failed to prepare statement for facilities requests count: " . $conn->error);
    }

    // คำร้องขอใช้อุปกรณ์ทั้งหมด (ยกเว้นร่างคำร้องขอ)
    $sql_equip_req_count = "SELECT COUNT(*) FROM equipments_requests er JOIN project p ON er.project_id = p.project_id LEFT JOIN user u ON p.nontri_id = u.nontri_id WHERE er.writed_status != 'ร่างคำร้องขอ' " . ($date_filter_params_er['where_sql'] ?? '');
    $count_params_er = [];
    $count_param_types_er = '';
    
    if (!empty($date_filter_params_er['param_values'])) {
        $count_params_er = array_merge($count_params_er, $date_filter_params_er['param_values']);
    }
    if (!empty($date_filter_params_er['param_types'])) {
        $count_param_types_er .= $date_filter_params_er['param_types'];
    }

    if (!empty($fa_de_id_filter_global)) { // ใช้ fa_de_id_filter_global
        $sql_equip_req_count .= " AND u.fa_de_id = ?";
        $count_params_er[] = (int)$fa_de_id_filter_global;
        $count_param_types_er .= 'i';
    }

    $stmt_equip_req_count = $conn->prepare($sql_equip_req_count);
    if ($stmt_equip_req_count) {
        if (!empty($count_param_types_er)) {
            $stmt_equip_req_count->bind_param($count_param_types_er, ...$count_params_er);
        }
        $stmt_equip_req_count->execute();
        $stmt_equip_req_count->bind_result($total_equipments_requests_count);
        $stmt_equip_req_count->fetch();
        $stmt_equip_req_count->close();
    } else {
        error_log("Failed to prepare statement for equipments requests count: " . $conn->error);
    }
    // --- จบส่วนการกรองวันที่สำหรับ Dashboard Counts ---

    $current_date = date('Y-m-d');
    $stmt_end = $conn->prepare("UPDATE project SET writed_status = 'สิ้นสุดโครงการ' WHERE end_date < ? AND writed_status != 'สิ้นสุดโครงการ'");
    if ($stmt_end) {
        $stmt_end->bind_param("s", $current_date);
        $stmt_end->execute();
        $stmt_end->close();
    } else {
        error_log("Failed to prepare statement for ending projects: " . $conn->error);
    }

    $stmt_start = $conn->prepare("UPDATE project SET writed_status = 'เริ่มดำเนินการ' WHERE start_date <= ? AND (writed_status = 'ร่างโครงการ' OR writed_status = 'ส่งโครงการ')");
    if ($stmt_start) {
        $stmt_start->bind_param("s", $current_date);
        $stmt_start->execute();
        $stmt_start->close();
    } else {
        error_log("Failed to prepare statement for starting projects: " . $conn->error);
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action']) && ($_POST['action'] == 'approve_request' || $_POST['action'] == 'reject_request')) {
            $requestId = (int)($_POST['request_id'] ?? 0);
            $requestType = $_POST['request_type'] ?? '';
            $approveStatus = ($_POST['action'] == 'approve_request') ? 'อนุมัติ' : 'ไม่อนุมัติ';
            $approveDetail = trim($_POST['approve_detail'] ?? '');
            $staffIdToUse = $staff_id_for_db;
            $approveDate = date('Y-m-d H:i:s');

            if ($approveStatus == 'อนุมัติ' && empty($approveDetail)) {
                $approveDetail = null;
            }

            $tableName = '';
            $idColumn = '';
            if ($requestType == 'facility') {
                $tableName = 'facilities_requests';
                $idColumn = 'facility_re_id';
            } elseif ($requestType == 'equipment') {
                $tableName = 'equipments_requests';
                $idColumn = 'equip_re_id';
            }


            if (empty($staffIdToUse) || $staffIdToUse === 'N/A') {
                $errors[] = "ไม่สามารถดำเนินการได้: Staff ID ไม่ถูกต้องหรือไม่พบสำหรับผู้ดูแลระบบที่ล็อกอินอยู่. โปรดตรวจสอบการตั้งค่าผู้ใช้หรือติดต่อผู้ดูแลระบบ.";
            } elseif ($tableName && $requestId > 0) {

                $stmt = $conn->prepare("UPDATE {$tableName} SET approve = ?, approve_date = ?, approve_detail = ?, staff_id = ? WHERE {$idColumn} = ?");
                if (!$stmt) {
                    $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับการดำเนินการ: " . $conn->error;
                } else {
                    $stmt->bind_param("ssssi", $approveStatus, $approveDate, $approveDetail, $staffIdToUse, $requestId);
                    if ($stmt->execute()) {
                        $success_message = "ดำเนินการ {$approveStatus} คำร้องขอสำเร็จแล้ว!";
                        header("Location: ?main_tab={$main_tab}&mode=detail&id={$requestId}&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการบันทึกการดำเนินการ: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
                $errors[] = "ข้อมูลคำร้องไม่ถูกต้องสำหรับการดำเนินการ.";
            }
        }
    }

    if ($main_tab == 'projects_admin') {
        if ($mode == 'list') {
            $sort_filter = $_GET['sort_filter'] ?? 'date_desc';
            $sorting = getSortingClauses('projects_admin', $sort_filter);

            $date_filtering = getDateFilteringClauses('projects_admin', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);

            $base_where = " WHERE p.project_name LIKE ? AND p.writed_status != 'ร่างโครงการ'";
            $full_where_sql = $base_where . ($sorting['where_sql'] ?? '') . ($date_filtering['where_sql'] ?? ''); // Null coalescing

            $join_user_faculty = " LEFT JOIN user u ON p.nontri_id = u.nontri_id";
            if (!empty($fa_de_id_filter_global)) { // ใช้ fa_de_id_filter_global
                $full_where_sql .= " AND u.fa_de_id = ?";
            }

            // --- COUNT QUERY for PROJECTS ---
            $count_sql = "SELECT COUNT(*) FROM project p" . $join_user_faculty . $full_where_sql;
            $stmt_count = $conn->prepare($count_sql);

            $count_params = [$search_param];
            $count_param_types = "s";
            if (!empty($sorting['where_param_value'])) { // Check if not empty
                $count_params[] = $sorting['where_param_value'];
                $count_param_types .= $sorting['where_param_type'];
            }
            // เพิ่ม params จาก date filtering
            if (!empty($date_filtering['param_values'])) {
                $count_params = array_merge($count_params, $date_filtering['param_values']);
            }
            if (!empty($date_filtering['param_types'])) {
                $count_param_types .= $date_filtering['param_types'];
            }
            // เพิ่ม param จาก fa_de_id_filter_global
            if (!empty($fa_de_id_filter_global)) {
                $count_params[] = (int)$fa_de_id_filter_global;
                $count_param_types .= 'i';
            }

            $stmt_count->bind_param($count_param_types, ...$count_params);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            // --- DATA QUERY for PROJECTS ---
            $sql_data = "SELECT
                            p.project_id, p.project_name, p.start_date, p.end_date, p.project_des, p.files, p.attendee, p.phone_num, p.advisor_name, p.writed_status, p.created_date,
                            at.activity_type_name, CONCAT(u.user_name, ' ', u.user_sur) AS user_name
                        FROM project p
                        LEFT JOIN user u ON p.nontri_id = u.nontri_id
                        LEFT JOIN activity_type at ON p.activity_type_id = at.activity_type_id"
                        . $full_where_sql
                        . ($sorting['order_by_sql'] ?? '') . " LIMIT ? OFFSET ?"; // Null coalescing

            $stmt_data = $conn->prepare($sql_data);

            $data_params = $count_params; // ใช้ params ที่รวมกันแล้ว
            $data_param_types = $count_param_types;
            $data_params[] = $items_per_page;
            $data_params[] = $offset;
            $data_param_types .= "ii";

            $stmt_data->bind_param($data_param_types, ...$data_params);
            $stmt_data->execute();
            $result_data = $stmt_data->get_result();
            while ($row = $result_data->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt_data->close();
        } elseif ($mode == 'detail' && isset($_GET['id'])) {
            $project_id = (int)$_GET['id'];
            $sql_detail = "SELECT
                                p.project_id, p.project_name, p.start_date, p.end_date, p.project_des, p.files, p.attendee, p.phone_num, p.advisor_name, p.writed_status, p.created_date,
                                at.activity_type_name AS activity_type_name, CONCAT(u.user_name, ' ', u.user_sur) AS user_name, u.nontri_id
                           FROM project p
                           JOIN user u ON p.nontri_id = u.nontri_id
                           JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                           WHERE p.project_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
            $stmt_detail->bind_param("i", $project_id);
            $stmt_detail->execute();
            $detail_item = $stmt_detail->get_result()->fetch_assoc();
            $stmt_detail->close();

            if (!$detail_item) {
                $errors[] = "ไม่พบโครงการที่คุณร้องขอ.";
                $mode = 'list';
            } else {
                // Fetch summary of facility requests for display (on-screen)
                $project_facility_requests = [];
                $sql_fr_summary = "SELECT fr.facility_re_id, fr.request_date, fr.writed_status, fr.start_date, fr.end_date, fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time, f.facility_name, fr.approve
                           FROM facilities_requests fr
                           JOIN facilities f ON fr.facility_id = f.facility_id
                           WHERE fr.project_id = ? AND fr.writed_status != 'ร่างคำร้องขอ'
                           ORDER BY fr.request_date DESC";
                $stmt_fr_summary = $conn->prepare($sql_fr_summary);
                $stmt_fr_summary->bind_param("i", $project_id);
                $stmt_fr_summary->execute();
                $result_fr_summary = $stmt_fr_summary->get_result();
                while ($row_fr_summary = $result_fr_summary->fetch_assoc()) {
                    $project_facility_requests[] = $row_fr_summary;
                }
                $stmt_fr_summary->close();

                // Fetch FULL details of facilities requests for PRINTING
                $detailed_project_facility_requests = [];
                foreach ($project_facility_requests as $summary_fr) {
                    $fr_id = $summary_fr['facility_re_id'];
                    $sql_fr_print_detail = "SELECT
                                fr.facility_re_id, fr.project_id, fr.prepare_start_time, fr.prepare_end_time,
                                fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time,
                                fr.start_date , fr.end_date , fr.agree,
                                fr.writed_status, fr.request_date, fr.approve, fr.approve_date, fr.approve_detail,
                                f.facility_name, p.project_name, CONCAT(u.user_name, ' ', u.user_sur) AS user_name,
                                CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name
                            FROM facilities_requests fr
                            JOIN facilities f ON fr.facility_id = f.facility_id
                            JOIN project p ON fr.project_id = p.project_id
                            LEFT JOIN user u ON p.nontri_id = u.nontri_id
                            LEFT JOIN staff s ON fr.staff_id = s.staff_id
                            WHERE fr.facility_re_id = ?";
                    $stmt_fr_print = $conn->prepare($sql_fr_print_detail);
                    if ($stmt_fr_print) {
                        $stmt_fr_print->bind_param("i", $fr_id);
                        $stmt_fr_print->execute();
                        $detailed_fr_item = $stmt_fr_print->get_result()->fetch_assoc();
                        if ($detailed_fr_item) {
                            $detailed_project_facility_requests[] = $detailed_fr_item;
                        }
                        $stmt_fr_print->close();
                    }
                }

                // Fetch summary of equipment requests for display (on-screen)
                $project_equipment_requests = [];
                $sql_er_summary = "SELECT er.equip_re_id, er.request_date, er.writed_status, er.start_date, er.end_date, er.quantity, e.equip_name, e.measure, er.approve
                           FROM equipments_requests er
                           JOIN equipments e ON er.equip_id = e.equip_id
                           WHERE er.project_id = ? AND er.writed_status != 'ร่างคำร้องขอ'
                           ORDER BY er.request_date DESC";
                $stmt_er_summary = $conn->prepare($sql_er_summary);
                $stmt_er_summary->bind_param("i", $project_id);
                $stmt_er_summary->execute();
                $result_er_summary = $stmt_er_summary->get_result();
                while ($row_er_summary = $result_er_summary->fetch_assoc()) {
                    $project_equipment_requests[] = $row_er_summary;
                }
                $stmt_er_summary->close();

                // Fetch FULL details of equipment requests for PRINTING
                $detailed_project_equipment_requests = [];
                foreach ($project_equipment_requests as $summary_er) {
                    $er_id = $summary_er['equip_re_id'];
                    $sql_er_print_detail = "SELECT
                                er.equip_re_id, er.project_id, er.start_date, er.end_date, er.quantity, er.transport,
                                er.writed_status, er.request_date, er.approve, er.approve_date, er.approve_detail, er.agree,
                                e.equip_name, e.measure, f.facility_name, p.project_name, CONCAT(u.user_name, ' ', u.user_sur) AS user_name,
                                CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name
                            FROM equipments_requests er
                            JOIN equipments e ON er.equip_id = e.equip_id
                            JOIN project p ON er.project_id = p.project_id
                            LEFT JOIN facilities f ON er.facility_id = f.facility_id
                            LEFT JOIN user u ON p.nontri_id = u.nontri_id
                            LEFT JOIN staff s ON er.staff_id = s.staff_id
                            WHERE er.equip_re_id = ?";
                    $stmt_er_print = $conn->prepare($sql_er_print_detail);
                    if ($stmt_er_print) {
                        $stmt_er_print->bind_param("i", $er_id);
                        $stmt_er_print->execute();
                        $detailed_er_item = $stmt_er_print->get_result()->fetch_assoc();
                        if ($detailed_er_item) {
                            $detailed_project_equipment_requests[] = $detailed_er_item;
                        }
                        $stmt_er_print->close();
                    }
                }
            }
        }
    } elseif ($main_tab == 'buildings_admin') {
        if ($mode == 'list') {
            $sort_filter = $_GET['sort_filter'] ?? 'date_desc';
            $sorting = getSortingClauses('buildings_admin', $sort_filter);

            $date_filtering = getDateFilteringClauses('buildings_admin', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);

            $base_where = " WHERE fr.writed_status != 'ร่างคำร้องขอ' AND (p.project_name LIKE ? OR f.facility_name LIKE ?)";
            $full_where_sql = $base_where . ($sorting['where_sql'] ?? '') . ($date_filtering['where_sql'] ?? ''); // Null coalescing

            $join_user_faculty = " LEFT JOIN user u ON p.nontri_id = u.nontri_id";
            if (!empty($fa_de_id_filter_global)) { // ใช้ fa_de_id_filter_global
                $full_where_sql .= " AND u.fa_de_id = ?";
            }

            $count_sql = "SELECT COUNT(*) FROM facilities_requests fr JOIN project p ON fr.project_id = p.project_id JOIN facilities f ON fr.facility_id = f.facility_id" . $join_user_faculty . $full_where_sql;
            $stmt_count = $conn->prepare($count_sql);

            $count_params = [$search_param, $search_param];
            $count_param_types = "ss";
            if (!empty($sorting['where_param_value'])) { // Check if not empty
                $count_params[] = $sorting['where_param_value'];
                $count_param_types .= $sorting['where_param_type'];
            }
            // เพิ่ม params จาก date filtering
            if (!empty($date_filtering['param_values'])) {
                $count_params = array_merge($count_params, $date_filtering['param_values']);
            }
            if (!empty($date_filtering['param_types'])) {
                $count_param_types .= $date_filtering['param_types'];
            }
            // เพิ่ม param จาก fa_de_id_filter_global
            if (!empty($fa_de_id_filter_global)) {
                $count_params[] = (int)$fa_de_id_filter_global;
                $count_param_types .= 'i';
            }

            $stmt_count->bind_param($count_param_types, ...$count_params);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            $sql_data = "SELECT
                            fr.facility_re_id, fr.request_date, fr.writed_status, fr.start_date, fr.end_date, fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time, fr.approve,
                            f.facility_name, p.project_name, CONCAT(u.user_name, ' ', u.user_sur) AS user_name
                        FROM facilities_requests fr
                        JOIN facilities f ON fr.facility_id = f.facility_id
                        JOIN project p ON fr.project_id = p.project_id"
                        . $join_user_faculty // <-- ใช้ $join_user_faculty
                        . $full_where_sql // ใช้ $full_where_sql ที่รวมเงื่อนไขแล้ว
                        . ($sorting['order_by_sql'] ?? '') . " LIMIT ? OFFSET ?"; // Null coalescing

            $stmt_data = $conn->prepare($sql_data);
            if ($stmt_data === false) { // <-- เพิ่มเช็คข้อผิดพลาดตรงนี้
                error_log("Error preparing data SQL for buildings_admin (list): " . $conn->error);
                error_log("Failing DATA SQL: " . $sql_data);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลสถานที่: " . $conn->error;
                $data = [];
            } else {
                $data_params = $count_params; // ใช้ params ที่รวมกันแล้ว
                $data_param_types = $count_param_types;
                $data_params[] = $items_per_page;
                $data_params[] = $offset;
                $data_param_types .= "ii";

                $stmt_data->bind_param($data_param_types, ...$data_params);
                $stmt_data->execute();
                $result_data = $stmt_data->get_result();
                while ($row = $result_data->fetch_assoc()) {
                    $data[] = $row;
                }
                $stmt_data->close();
            }
        } elseif ($mode == 'detail' && isset($_GET['id'])) {
            $facility_re_id = (int)$_GET['id'];
            // Modified SQL to include project description, activity type, user phone, faculty name, advisor name, building name
            $sql_detail = "SELECT
                                fr.facility_re_id, fr.project_id, fr.prepare_start_time, fr.prepare_end_time,
                                fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time,
                                fr.start_date AS fr_start_date, fr.end_date AS fr_end_date, fr.agree,
                                fr.writed_status, fr.request_date, fr.approve, fr.approve_date, fr.approve_detail,
                                f.facility_name, f.building_id, b.building_name,
                                p.project_name, p.project_des, p.activity_type_id, at.activity_type_name, p.advisor_name,
                                CONCAT(u.user_name, ' ', u.user_sur) AS user_name, u.nontri_id,
                                p.phone_num AS user_phone_num, fd.fa_de_name,
                                CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name, s.staff_id
                            FROM facilities_requests fr
                            JOIN facilities f ON fr.facility_id = f.facility_id
                            LEFT JOIN buildings b ON f.building_id = b.building_id
                            JOIN project p ON fr.project_id = p.project_id
                            LEFT JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                            LEFT JOIN user u ON p.nontri_id = u.nontri_id
                            LEFT JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
                            LEFT JOIN staff s ON fr.staff_id = s.staff_id
                            WHERE fr.facility_re_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
            if ($stmt_detail === false) { // <-- เพิ่มเช็คข้อผิดพลาดตรงนี้
                error_log("Error preparing detail SQL for buildings_admin: " . $conn->error);
                error_log("Failing DETAIL SQL: " . $sql_detail);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงรายละเอียดสถานที่: " . $conn->error;
                $mode = 'list';
            } else {
                $stmt_detail->bind_param("i", $facility_re_id);
                $stmt_detail->execute();
                $detail_item = $stmt_detail->get_result()->fetch_assoc();
                $stmt_detail->close();

                if (!$detail_item) {
                    $errors[] = "ไม่พบคำร้องขอสถานที่ที่คุณร้องขอ.";
                    $mode = 'list';
                }
            }
        }
    } elseif ($main_tab == 'equipments_admin') {
        if ($mode == 'list') {
            $sort_filter = $_GET['sort_filter'] ?? 'date_desc';
            $sorting = getSortingClauses('equipments_admin', $sort_filter);

            $date_filtering = getDateFilteringClauses('equipments_admin', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);

            $base_where = " WHERE er.writed_status != 'ร่างคำร้องขอ' AND (p.project_name LIKE ? OR e.equip_name LIKE ?)";
            $full_where_sql = $base_where . ($sorting['where_sql'] ?? '') . ($date_filtering['where_sql'] ?? ''); // Null coalescing

            $join_user_faculty = " LEFT JOIN user u ON p.nontri_id = u.nontri_id";
            if (!empty($fa_de_id_filter_global)) { // ใช้ fa_de_id_filter_global
                $full_where_sql .= " AND u.fa_de_id = ?";
            }

            // --- COUNT QUERY ---
            $count_sql = "SELECT COUNT(*) FROM equipments_requests er JOIN project p ON er.project_id = p.project_id JOIN equipments e ON er.equip_id = e.equip_id" . $join_user_faculty . $full_where_sql;
            $stmt_count = $conn->prepare($count_sql);
            if ($stmt_count === false) { // <-- เพิ่มเช็คข้อผิดพลาดตรงนี้
                error_log("Error preparing count SQL for equipments_admin (list): " . $conn->error);
                error_log("Failing COUNT SQL: " . $count_sql);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งนับจำนวนข้อมูลอุปกรณ์: " . $conn->error;
                $total_items = 0;
            } else {
                $count_params = [$search_param, $search_param];
                $count_param_types = "ss";
                if (!empty($sorting['where_param_value'])) { // Check if not empty
                    $count_params[] = $sorting['where_param_value'];
                    $count_param_types .= $sorting['where_param_type'];
                }
                // เพิ่ม params จาก date filtering
                if (!empty($date_filtering['param_values'])) {
                    $count_params = array_merge($count_params, $date_filtering['param_values']);
                }
                if (!empty($date_filtering['param_types'])) {
                    $count_param_types .= $date_filtering['param_types'];
                }
                // เพิ่ม param จาก fa_de_id_filter_global
                if (!empty($fa_de_id_filter_global)) {
                    $count_params[] = (int)$fa_de_id_filter_global;
                    $count_param_types .= 'i';
                }

                $stmt_count->bind_param($count_param_types, ...$count_params);
                $stmt_count->execute();
                $stmt_count->bind_result($total_items);
                $stmt_count->fetch();
                $stmt_count->close();
            }

            // --- DATA QUERY ---
            $sql_data = "SELECT
                            er.equip_re_id, er.request_date, er.writed_status, er.start_date, er.end_date, er.quantity, er.transport, er.approve,
                            e.equip_name, e.measure, f.facility_name, p.project_name, CONCAT(u.user_name, ' ', u.user_sur) AS user_name
                        FROM equipments_requests er
                        JOIN equipments e ON er.equip_id = e.equip_id
                        JOIN project p ON er.project_id = p.project_id
                        LEFT JOIN facilities f ON er.facility_id = f.facility_id"
                        . $join_user_faculty // <-- ใช้ $join_user_faculty
                        . $full_where_sql // ใช้ $full_where_sql ที่รวมเงื่อนไขแล้ว
                        . ($sorting['order_by_sql'] ?? '') . " LIMIT ? OFFSET ?"; // Null coalescing

            $stmt_data = $conn->prepare($sql_data);
            if ($stmt_data === false) { // <-- เพิ่มเช็คข้อผิดพลาดตรงนี้
                error_log("Error preparing data SQL for equipments_admin (list): " . $conn->error);
                error_log("Failing DATA SQL: " . $sql_data);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลอุปกรณ์: " . $conn->error;
                $data = [];
            } else {
                $data_params = $count_params; // ใช้ params ที่รวมกันแล้ว
                $data_param_types = $count_param_types;
                $data_params[] = $items_per_page;
                $data_params[] = $offset;
                $data_param_types .= "ii";

                $stmt_data->bind_param($data_param_types, ...$data_params);
                $stmt_data->execute();
                $result_data = $stmt_data->get_result();
                while ($row = $result_data->fetch_assoc()) {
                    $data[] = $row;
                }
                $stmt_data->close();
            }
        } elseif ($mode == 'detail' && isset($_GET['id'])) {
            $equip_re_id = (int)$_GET['id'];
            $sql_detail = "SELECT
                                er.equip_re_id, er.project_id, er.start_date, er.end_date, er.quantity, er.transport,
                                er.writed_status, er.request_date, er.approve, er.approve_date, er.approve_detail, er.agree,
                                e.equip_name, e.measure, f.facility_name, p.project_name, CONCAT(u.user_name, ' ', u.user_sur) AS user_name,
                                CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name
                            FROM equipments_requests er
                            JOIN equipments e ON er.equip_id = e.equip_id
                            JOIN project p ON er.project_id = p.project_id
                            LEFT JOIN facilities f ON er.facility_id = f.facility_id
                            LEFT JOIN user u ON p.nontri_id = u.nontri_id
                            LEFT JOIN staff s ON er.staff_id = s.staff_id
                            WHERE er.equip_re_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
            if ($stmt_detail === false) { // <-- เพิ่มเช็คข้อผิดพลาดตรงนี้
                error_log("Error preparing detail SQL for equipments_admin: " . $conn->error);
                error_log("Failing DETAIL SQL: " . $sql_detail);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงรายละเอียดอุปกรณ์: " . $conn->error;
                $mode = 'list';
            } else {
                $stmt_detail->bind_param("i", $equip_re_id);
                $stmt_detail->execute();
                $detail_item = $stmt_detail->get_result()->fetch_assoc();
                $stmt_detail->close();

                if (!$detail_item) {
                    $errors[] = "ไม่พบคำร้องขออุปกรณ์ที่คุณร้องขอ.";
                    $mode = 'list';
                }
            }
        }
    }

} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

// Ensure $detail_item is available and initialized for all scenarios where it might be used
if (($main_tab == 'buildings_admin' || $main_tab == 'equipments_admin') && $mode == 'detail' && !isset($detail_item)) {
    // This block helps if the initial fetch for $detail_item failed but $mode is still 'detail'
    $errors[] = "ไม่สามารถโหลดรายละเอียดคำร้องขอได้ โปรดลองอีกครั้ง.";
    $detail_item = []; // Initialize to an empty array to prevent further errors
}


$conn->close();

$total_pages = ceil($total_items / $items_per_page);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'?>
    <!-- เพิ่ม Chart.js CDN (ถ้ายังไม่มีใน header.php) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Google Fonts: Sarabun -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet">

    <!-- ลบ CSS ที่เกี่ยวข้องกับการพิมพ์ออกจากส่วนนี้ -->
</head>
<body class="admin-body">
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
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo $staff_name . ' ' . $staff_sur; ?></span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5"><?php echo $user_role; ?></span>
                </div>
                <a href="admin-profile-page.php">
                    <img src="./images/user_button.png" class="img-fluid logo" style="width:40px; height:40px; object-fit:cover;" alt="User Button">
                </a>
            </div>
        </div>
    </nav>

    <div class="admin-main-wrapper">
        <!-- Sidebar -->
        <div class="admin-sidebar screen-only">
            <h5 class="mb-3">เมนูผู้ดูแลระบบ</h5>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="admin-details nav-link <?php echo ($main_tab == 'dashboard_admin') ? 'active' : ''; ?>" href="?main_tab=dashboard_admin">
                        <i class="bi bi-speedometer2"></i> ภาพรวม
                    </a>
                </li>
                <li class="nav-item">
                    <a class="admin-details nav-link <?php echo ($main_tab == 'projects_admin') ? 'active' : ''; ?>" href="?main_tab=projects_admin">
                        <i class="bi bi-folder"></i> รายการโครงการ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="admin-details nav-link <?php echo ($main_tab == 'buildings_admin') ? 'active' : ''; ?>" href="?main_tab=buildings_admin">
                        <i class="bi bi-building"></i> คำร้องขอใช้สถานที่
                    </a>
                </li>
                <li class="nav-item">
                    <a class="admin-details nav-link <?php echo ($main_tab == 'equipments_admin') ? 'active' : ''; ?>" href="?main_tab=equipments_admin">
                        <i class="bi bi-tools"></i> คำร้องขอใช้อุปกรณ์
                    </a>
                </li>
            </ul>
        </div>

        <div class="admin-content-area" id="mainContent">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert" class="screen-only">
                    <h4 class="alert-heading">เกิดข้อผิดพลาด!</h4>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php

            $modal_status = $_GET['status'] ?? '';
            $modal_message = $_GET['message'] ?? '';
            if ($modal_status == 'success' && $modal_message != ''): ?>
                <div class="alert alert-success" role="alert" class="screen-only">
                    <h4 class="alert-heading">สำเร็จ!</h4>
                    <p><?php echo htmlspecialchars($modal_message); ?></p>
                </div>
            <?php endif; ?>
            <?php

            if (isset($_GET['status']) || isset($_GET['message'])) {
                $current_params = $_GET;
                unset($current_params['status']);
                unset($current_params['message']);
                $new_url = '?' . http_build_query($current_params);
                echo '<script>window.history.replaceState({}, document.title, "' . $new_url . '");</script>';
            }
            ?>

            <?php if ($main_tab == 'dashboard_admin'): ?>
                <h1 class="mb-4">ภาพรวมคำร้องขอทั้งหมด</h1>
                <div class="row mb-4 justify-content-end screen-only">
                    <div class="col-md-auto">
                        <form id="dateFilterFormDashboard" class="d-inline-flex gap-2 align-items-center" action="" method="GET">
                            <input type="hidden" name="main_tab" value="<?php echo htmlspecialchars($main_tab); ?>">
                            <!-- Keep chart sorting parameters if they are set, to be reapplied after date filter -->
                            <?php if ($chart_sort_mode): ?><input type="hidden" name="chart_sort_mode" value="<?php echo htmlspecialchars($chart_sort_mode); ?>"><?php endif; ?>
                            <?php if ($drilldown_type): ?><input type="hidden" name="drilldown_type" value="<?php echo htmlspecialchars($drilldown_type); ?>"><?php endif; ?>
                            <?php if ($drilldown_id): ?><input type="hidden" name="drilldown_id" value="<?php echo htmlspecialchars($drilldown_id); ?>"><?php endif; ?>


                            <!-- ดรอปดาวน์สำหรับช่วงเวลาที่กำหนดไว้ล่วงหน้า -->
                            <select name="predefined_range" id="predefined_range_select" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">กรองตามวันที่...</option>
                                <option value="today" <?php echo ($predefined_range_select == 'today') ? 'selected' : ''; ?>>วันนี้</option>
                                <option value="this_week" <?php echo ($predefined_range_select == 'this_week') ? 'selected' : ''; ?>>สัปดาห์นี้</option>
                                <option value="this_month" <?php echo ($predefined_range_select == 'this_month') ? 'selected' : ''; ?>>เดือนนี้</option>
                                <option value="this_year" <?php echo ($predefined_range_select == 'this_year') ? 'selected' : ''; ?>>ปีนี้</option>
                            </select>

                            <!-- ดรอปดาวน์สำหรับเลือกวันที่เฉพาะเจาะจง -->
                            <select name="specific_year" id="specific_year_select" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">ปี</option>
                                <?php for ($y = date('Y') + 1; $y >= 2021; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($specific_year_select == $y) ? 'selected' : ''; ?>><?php echo $y + 543; ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="specific_month" id="specific_month_select" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">เดือน</option>
                                <?php
                                $thai_months_full = [
                                    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
                                    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
                                    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                                ];
                                foreach ($thai_months_full as $num => $name): ?>
                                    <option value="<?php echo $num; ?>" <?php echo ($specific_month_select == $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="specific_day" id="specific_day_select" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">วัน</option>
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?php echo $d; ?>" <?php echo ($specific_day_select == $d) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                <?php endfor; ?>
                            </select>

                            <!-- เพิ่ม Dropdown สำหรับกรองตามคณะ (ใช้สำหรับ dashboard cards และ Upcoming/Recent activity) -->
                            <select name="fa_de_id_global" id="fa_de_id_select_dashboard" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">กรองตามคณะ...</option>
                                <?php foreach ($faculties_for_chart_filter as $faculty): ?>
                                    <option value="<?php echo $faculty['fa_de_id']; ?>" <?php echo (($fa_de_id_filter_global ?? null) == $faculty['fa_de_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($faculty['fa_de_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php
                                // Link to clear all date/global faculty filters, but keep chart-specific filters if active
                                $clear_url_params = ['main_tab' => 'dashboard_admin'];
                                // If chart_sort_mode is top_facilities or top_equipments, keep it (no drilldown anymore)
                                if (in_array($chart_sort_mode, ['top_facilities', 'top_equipments'])) {
                                    $clear_url_params['chart_sort_mode'] = $chart_sort_mode;
                                }
                                // If in a drilldown mode (accessed manually or old link), clear to its parent Top N view
                                elseif ($chart_sort_mode === 'drilldown_facility_by_faculty') {
                                    $clear_url_params['chart_sort_mode'] = 'top_facilities';
                                } elseif ($chart_sort_mode === 'drilldown_equipment_by_faculty') {
                                    $clear_url_params['chart_sort_mode'] = 'top_equipments';
                                }
                                // For 'faculty_overview' or any other state, just clear everything.
                                
                                $clear_url = '?' . http_build_query(array_filter($clear_url_params, fn($value) => $value !== null && $value !== ''));
                            ?>
                            <?php if (!empty($predefined_range_select) || !empty($specific_year_select) || !empty($specific_month_select) || !empty($specific_day_select) || !empty($fa_de_id_filter_global) || (in_array($chart_sort_mode, ['top_facilities', 'top_equipments'])) || ($drilldown_type && $drilldown_id)): ?>
                                <a href="<?php echo $clear_url; ?>" class="btn btn-outline-secondary btn-sm">ล้าง</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="row row-cols-1 row-cols-md-3 g-4 mb-4 screen-only">
                    <div class="col">
                        <div class="card text-white bg-primary mb-3 h-100">
                            <div class="card-header"><i class="bi bi-folder-fill me-2"></i>โครงการ</div>
                            <div class="card-body">
                                <h5 class="card-title">โครงการทั้งหมด</h5>
                                <p class="card-text fs-1"><?php echo $total_projects_count; ?></p>
                            </div>
                            <div class="card-footer">
                                <?php
                                    $link_params = ['main_tab' => 'projects_admin', 'mode' => 'list'];
                                    if (!empty($predefined_range_select)) $link_params['predefined_range'] = $predefined_range_select;
                                    if (!empty($specific_year_select)) $link_params['specific_year'] = $specific_year_select;
                                    if (!empty($specific_month_select)) $link_params['specific_month'] = $specific_month_select;
                                    if (!empty($specific_day_select)) $link_params['specific_day'] = $specific_day_select;
                                    if (!empty($fa_de_id_filter_global)) $link_params['fa_de_id_global'] = $fa_de_id_filter_global; // ใช้ fa_de_id_global
                                ?>
                                <a href="?<?php echo http_build_query($link_params); ?>" class="stretched-link text-white text-decoration-none footer-text">ดูรายละเอียด <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card text-white bg-success mb-3 h-100">
                            <div class="card-header"><i class="bi bi-building-fill me-2"></i>คำร้องขอใช้สถานที่</div>
                            <div class="card-body">
                                <h5 class="card-title">คำร้องขอใช้สถานที่ทั้งหมด</h5>
                                <p class="card-text fs-1"><?php echo $total_facilities_requests_count; ?></p>
                            </div>
                            <div class="card-footer">
                                <?php
                                    $link_params = ['main_tab' => 'buildings_admin', 'mode' => 'list'];
                                    if (!empty($predefined_range_select)) $link_params['predefined_range'] = $predefined_range_select;
                                    if (!empty($specific_year_select)) $link_params['specific_year'] = $specific_year_select;
                                    if (!empty($specific_month_select)) $link_params['specific_month'] = $specific_month_select;
                                    if (!empty($specific_day_select)) $link_params['specific_day'] = $specific_day_select;
                                    if (!empty($fa_de_id_filter_global)) $link_params['fa_de_id_global'] = $fa_de_id_filter_global; // ใช้ fa_de_id_global
                                ?>
                                <a href="?<?php echo http_build_query($link_params); ?>" class="stretched-link text-white text-decoration-none footer-text">ดูรายละเอียด <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card text-dark bg-warning mb-3 h-100">
                            <div class="card-header"><i class="bi bi-tools me-2"></i>คำร้องขอใช้อุปกรณ์</div>
                            <div class="card-body">
                                <h5 class="card-title">คำร้องขอใช้อุปกรณ์ทั้งหมด</h5>
                                <p class="card-text fs-1"><?php echo $total_equipments_requests_count; ?></p>
                            </div>
                            <div class="card-footer">
                                <?php
                                    $link_params = ['main_tab' => 'equipments_admin', 'mode' => 'list'];
                                    if (!empty($predefined_range_select)) $link_params['predefined_range'] = $predefined_range_select;
                                    if (!empty($specific_year_select)) $link_params['specific_year'] = $specific_year_select;
                                    if (!empty($specific_month_select)) $link_params['specific_month'] = $specific_month_select;
                                    if (!empty($specific_day_select)) $link_params['specific_day'] = $specific_day_select;
                                    if (!empty($fa_de_id_filter_global)) $link_params['fa_de_id_global'] = $fa_de_id_filter_global; // ใช้ fa_de_id_global
                                ?>
                                <a href="?<?php echo http_build_query($link_params); ?>" class="stretched-link text-dark text-decoration-none footer-text">ดูรายละเอียด <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="screen-only">

                <div class="row g-4 mb-4 screen-only">
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm p-4">
                            <h4 class="mb-3">โครงการที่กำลังจะมาถึง <span class="badge bg-secondary"><?php echo count($dashboard_data['upcoming_requests']); ?></span></h4>
                            <?php if (empty($dashboard_data['upcoming_requests'])): ?>
                                <div class="alert alert-info text-center py-3 mb-0">
                                    ยังไม่มีโครงการที่กำลังจะมาถึงใน 14 วันนี้
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($dashboard_data['upcoming_requests'] as $req):
                                        $detail_link = '';
                                        if ($req['type'] == 'โครงการ') {
                                            $detail_link = '?main_tab=projects_admin&mode=detail&id=' . $req['id'];
                                        } elseif ($req['type'] == 'คำร้องขอสถานที่') {
                                            $detail_link = '?main_tab=buildings_admin&mode=detail&id=' . $req['id'];
                                        } elseif ($req['type'] == 'คำร้องขออุปกรณ์') {
                                            $detail_link = '?main_tab=equipments_admin&mode=detail&id=' . $req['id'];
                                        }
                                    ?>
                                    <li class="list-group-item activity-item">
                                        <?php if ($detail_link): ?>
                                            <a href="<?php echo $detail_link; ?>" class="d-flex w-100 justify-content-between align-items-center stretched-link text-decoration-none text-dark inbox-text">
                                        <?php else: ?>
                                            <div class="d-flex w-100 justify-content-between align-items-center text-dark">
                                        <?php endif; ?>
                                                <div class="main-info">
                                                    <span class="badge bg-primary me-2"><?php echo htmlspecialchars($req['type']); ?></span>
                                                    <strong><?php echo htmlspecialchars($req['name']); ?></strong>
                                                </div>
                                                <div class="date-info">
                                                    <span class="badge <?php echo getStatusBadgeClass($req['writed_status'], $req['approve']); ?>"><?php echo htmlspecialchars($req['writed_status']); ?></span><br>
                                                    <small class="text-muted">
                                                        <?php echo formatThaiDate($req['start_date'], false); ?>
                                                        <?php if ($req['start_time'] && $req['end_time']): ?>
                                                            (<?php echo (new DateTime($req['start_time']))->format('H:i'); ?>-<?php echo (new DateTime($req['end_time']))->format('H:i'); ?>)
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                        <?php if ($detail_link): ?>
                                            </a>
                                        <?php else: ?>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm p-4">
                            <h4 class="mb-3">คำร้องขอใช้ล่าสุด <span class="badge bg-secondary"><?php echo count($dashboard_data['recent_activity']); ?></span></h4>
                            <?php if (empty($dashboard_data['recent_activity'])): ?>
                                <div class="alert alert-info text-center py-3 mb-0">
                                    ยังไม่มีคำร้องขอใช้ใด ๆ จากผู้ใช้
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($dashboard_data['recent_activity'] as $activity):
                                        $detail_link = '';

                                        if ($activity['item_type'] == 'คำร้องขอสถานที่') {
                                            $detail_link = '?main_tab=buildings_admin&mode=detail&id=' . $activity['id'];
                                        } elseif ($activity['item_type'] == 'คำร้องขออุปกรณ์') {
                                            $detail_link = '?main_tab=equipments_admin&mode=detail&id=' . $activity['id'];
                                        }
                                    ?>
                                        <li class="list-group-item activity-item">
                                            <a href="<?php echo $detail_link; ?>" class="d-flex w-100 justify-content-between align-items-center stretched-link text-decoration-none text-dark inbox-text">
                                                <div class="main-info">
                                                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($activity['item_type']); ?></span>
                                                    <strong><?php echo htmlspecialchars($activity['item_name']); ?></strong>
                                                </div>
                                                <div class="date-info">
                                                    <span class="badge <?php echo getStatusBadgeClass($activity['status_text'], $activity['approve_status'] ?? null); ?>"><?php echo htmlspecialchars($activity['approve_status'] ?? $activity['status_text']); ?></span><br>
                                                    <small class="text-muted"><?php echo formatThaiDate($activity['activity_date']); ?></small>
                                                </div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div><!-- End of row g-4 mb-4 screen-only -->

                <!-- เพิ่มส่วนของ Bar Chart ที่นี่ -->
                <div class="row g-4 mb-4 screen-only">
                    <div class="col-12">
                        <div class="card h-100 shadow-sm p-4">
                            <h4 class="mb-3" id="chartTitle"></h4> <!-- Dynamic title for chart -->
                            <div class="d-flex justify-content-end mb-3 chart-sorting-controls">
                                <form id="chartSortForm" class="d-inline-flex gap-2 align-items-center" action="" method="GET">
                                    <input type="hidden" name="main_tab" value="<?php echo htmlspecialchars($main_tab); ?>">
                                    <!-- Keep existing date/global faculty filters for continuity -->
                                    <?php if (!empty($predefined_range_select)): ?><input type="hidden" name="predefined_range" value="<?php echo htmlspecialchars($predefined_range_select); ?>"><?php endif; ?>
                                    <?php if (!empty($specific_year_select)): ?><input type="hidden" name="specific_year" value="<?php echo htmlspecialchars($specific_year_select); ?>"><?php endif; ?>
                                    <?php if (!empty($specific_month_select)): ?><input type="hidden" name="specific_month" value="<?php echo htmlspecialchars($specific_month_select); ?>"><?php endif; ?>
                                    <?php if (!empty($specific_day_select)): ?><input type="hidden" name="specific_day" value="<?php echo htmlspecialchars($specific_day_select); ?>"><?php endif; ?>
                                    <?php if (!empty($fa_de_id_filter_global)): ?><input type="hidden" name="fa_de_id_global" value="<?php echo htmlspecialchars($fa_de_id_filter_global); ?>"><?php endif; ?>
                                    <!-- drilldown parameters are not needed for this form's submission, as changing chart_sort_mode implies clearing them -->

                                    <label for="chart_sort_mode_select" class="form-label mb-0">จำแนกตาม:</label>
                                    <select name="chart_sort_mode" id="chart_sort_mode_select" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                        <option value="faculty_overview" <?php echo ($chart_sort_mode == 'faculty_overview') ? 'selected' : ''; ?>>คณะ</option>
                                        <option value="top_facilities" <?php echo ($chart_sort_mode == 'top_facilities') ? 'selected' : ''; ?>>อาคารสถานที่ 10 อันดับแรก</option>
                                        <option value="top_equipments" <?php echo ($chart_sort_mode == 'top_equipments') ? 'selected' : ''; ?>>อุปกรณ์ 10 อันดับแรก</option>
                                    </select>
                                    <?php
                                        // Generate back button URL based on current drilldown state
                                        $back_button_url_parts = [
                                            'main_tab' => 'dashboard_admin',
                                            'predefined_range' => $predefined_range_select,
                                            'specific_year' => $specific_year_select,
                                            'specific_month' => $specific_month_select,
                                            'specific_day' => $specific_day_select,
                                            'fa_de_id_global' => $fa_de_id_filter_global,
                                        ];

                                        // The back button should only appear if the chart_sort_mode is a 'drilldown'
                                        // which is not triggered by UI clicks anymore, but could be accessed via URL
                                        if (in_array($chart_sort_mode, ['drilldown_facility_by_faculty', 'drilldown_equipment_by_faculty'])) {
                                            // Go back to the corresponding top-level view
                                            $back_button_url_parts['chart_sort_mode'] = ($chart_sort_mode === 'drilldown_facility_by_faculty') ? 'top_facilities' : 'top_equipments';
                                        } else {
                                            // If not in a drilldown mode, ensure drilldown params are cleared and back button doesn't show
                                            unset($back_button_url_parts['drilldown_type']);
                                            unset($back_button_url_parts['drilldown_id']);
                                        }

                                        // Filter out null/empty values for cleaner URL
                                        $back_button_url_parts = array_filter($back_button_url_parts, function($value) {
                                            return $value !== null && $value !== '';
                                        });

                                        $back_button_url = '?' . http_build_query($back_button_url_parts);
                                    ?>
                                    <?php if (in_array($chart_sort_mode, ['drilldown_facility_by_faculty', 'drilldown_equipment_by_faculty'])): // Show back button only if in a drilldown state ?>
                                        <a href="<?php echo htmlspecialchars($back_button_url); ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-arrow-left"></i> ย้อนกลับ
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div style="height: 400px;"><!-- กำหนดความสูงเพื่อให้กราฟแสดงผลได้ดี -->
                                <canvas id="dashboardChartCanvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($mode == 'list'): ?>
                <h1 class="mb-4">
                    <?php
                    if ($main_tab == 'projects_admin') echo 'โครงการทั้งหมด';
                    elseif ($main_tab == 'buildings_admin') echo 'คำร้องขอใช้สถานที่';
                    elseif ($main_tab == 'equipments_admin') echo 'คำร้องขอใช้อุปกรณ์';
                    ?>
                </h1>
                <div class="d-flex justify-content-end mb-3 screen-only flex-wrap gap-2">
                    <form id="combinedFilterFormList" class="d-inline-flex gap-2 align-items-center flex-wrap" action="" method="GET">
                        <input type="hidden" name="main_tab" value="<?php echo htmlspecialchars($main_tab); ?>">
                        <input type="hidden" name="mode" value="list">

                        <!-- Dropdown for Sorting -->
                        <select name="sort_filter" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                            <optgroup label="เรียงตามวันที่">
                                <option value="date_desc" <?php echo (($_GET['sort_filter'] ?? 'date_desc') == 'date_desc') ? 'selected' : ''; ?>>ใหม่สุดไปเก่าสุด</option>
                                <option value="date_asc" <?php echo (($_GET['sort_filter'] ?? '') == 'date_asc') ? 'selected' : ''; ?>>เก่าสุดไปใหม่สุด</option>
                            </optgroup>
                            <optgroup label="กรองตามสถานะ">
                                <?php if ($main_tab == 'projects_admin'): ?>
                                    <option value="ส่งโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'ส่งโครงการ') ? 'selected' : ''; ?>>ส่งโครงการ</option>
                                    <option value="เริ่มดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'เริ่มดำเนินการ') ? 'selected' : ''; ?>>เริ่มดำเนินการ</option>
                                    <option value="สิ้นสุดโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'สิ้นสุดโครงการ') ? 'selected' : ''; ?>>สิ้นสุดโครงการ</option>
                                    <option value="ยกเลิกโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'ยกเลิกโครงการ') ? 'selected' : ''; ?>>ยกเลิกโครงการ</option>
                                <?php elseif (in_array($main_tab, ['buildings_admin', 'equipments_admin'])): ?>
                                    <option value="ส่งคำร้องขอ" <?php echo (($_GET['sort_filter'] ?? '') == 'ส่งคำร้องขอ') ? 'selected' : ''; ?>>ส่งคำร้องขอ</option>
                                    <option value="อนุมัติ" <?php echo (($_GET['sort_filter'] ?? '') == 'อนุมัติ') ? 'selected' : ''; ?>>อนุมัติ</option>
                                    <option value="ไม่อนุมัติ" <?php echo (($_GET['sort_filter'] ?? '') == 'ไม่อนุมัติ') ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                                    <option value="เริ่มดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'เริ่มดำเนินการ') ? 'selected' : ''; ?>>เริ่มดำเนินการ</option>
                                    <option value="สิ้นสุดดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'สิ้นสุดดำเนินการ') ? 'selected' : ''; ?>>สิ้นสุดดำเนินการ</option>
                                    <option value="ยกเลิกคำร้องขอ" <?php echo (($_GET['sort_filter'] ?? '') == 'ยกเลิกคำร้องขอ') ? 'selected' : ''; ?>>ยกเลิกคำร้องขอ</option>
                                <?php endif; ?>
                            </optgroup>
                        </select>

                        <!-- Search Input -->
                        <input class="form-control form-control-sm" type="search" placeholder="ค้นหา..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" style="width: 150px;">
                        <button class="btn btn-outline-success btn-sm" type="submit">ค้นหา</button>

                        <!-- Date Filtering Dropdowns -->
                        <select name="predefined_range" id="predefined_range_select_list" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">กรองตามวันที่...</option>
                            <option value="today" <?php echo ($predefined_range_select == 'today') ? 'selected' : ''; ?>>วันนี้</option>
                            <option value="this_week" <?php echo ($predefined_range_select == 'this_week') ? 'selected' : ''; ?>>สัปดาห์นี้</option>
                            <option value="this_month" <?php echo ($predefined_range_select == 'this_month') ? 'selected' : ''; ?>>เดือนนี้</option>
                            <option value="this_year" <?php echo ($predefined_range_select == 'this_year') ? 'selected' : ''; ?>>ปีนี้</option>
                        </select>
                        <select name="specific_year" id="specific_year_select_list" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">ปี</option>
                            <?php for ($y = date('Y') + 1; $y >= 2021; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($specific_year_select == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="specific_month" id="specific_month_select_list" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">เดือน</option>
                            <?php
                            $thai_months_full = [
                                1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
                                5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
                                9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                            ];
                            foreach ($thai_months_full as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($specific_month_select == $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="specific_day" id="specific_day_select_list" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">วัน</option>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <option value="<?php echo $d; ?>" <?php echo ($specific_day_select == $d) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                            <?php endfor; ?>
                        </select>
                        
                        <!-- เพิ่ม Dropdown สำหรับกรองตามคณะ (ใช้สำหรับ list views) -->
                        <select name="fa_de_id_global" id="fa_de_id_select_list" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">กรองตามคณะ...</option>
                            <?php foreach ($faculties_for_chart_filter as $faculty): ?>
                                <option value="<?php echo $faculty['fa_de_id']; ?>" <?php echo (($fa_de_id_filter_global ?? null) == $faculty['fa_de_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($faculty['fa_de_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php
                        // สร้าง URL สำหรับปุ่ม 'ล้าง' ทั้งหมด
                        $clear_all_params = ['main_tab' => $main_tab, 'mode' => 'list'];
                        if (!empty($search_query) || !empty($_GET['sort_filter']) || !empty($predefined_range_select) || !empty($specific_year_select) || !empty($specific_month_select) || !empty($specific_day_select) || !empty($fa_de_id_filter_global)): ?>
                            <a href="?<?php echo http_build_query($clear_all_params); ?>" class="btn btn-outline-secondary btn-sm">ล้างทั้งหมด</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (empty($data)): ?>
                    <div class="alert alert-info text-center mt-4">
                        <?php if (!empty($search_query)): ?>
                            ไม่พบรายการที่คุณค้นหา "<?php echo htmlspecialchars($search_query); ?>" ในสถานะที่แสดงได้
                        <?php else: ?>
                            ยังไม่มีรายการในสถานะที่แสดงได้
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive admin-details list-text">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ลำดับที่</th>
                                    <?php if ($main_tab == 'projects_admin'): ?>
                                        <th>วันที่สร้าง</th>
                                        <th>ชื่อโครงการ</th>
                                        <th>ผู้ยื่น</th>
                                        <th>ประเภทกิจกรรม</th>
                                        <th>สถานะโครงการ</th>
                                    <?php elseif ($main_tab == 'buildings_admin'): ?>
                                        <th>วันที่ยื่นคำร้อง</th>
                                        <th>โครงการ</th>
                                        <th>สถานที่</th>
                                        <th>ผู้ยื่น</th>
                                        <th>ช่วงเวลาใช้งาน</th>
                                        <th>สถานะคำร้อง</th>
                                        <th>การอนุมัติ</th>
                                    <?php elseif ($main_tab == 'equipments_admin'): ?>
                                        <th>วันที่ยื่นคำร้อง</th>
                                        <th>โครงการ</th>
                                        <th>อุปกรณ์</th>
                                        <th>จำนวน</th>
                                        <th>ผู้ยื่น</th>
                                        <th>ช่วงเวลาใช้งาน</th>
                                        <th>สถานะคำร้อง</th>
                                        <th>การอนุมัติ</th>
                                    <?php endif; ?>
                                    <th>ตรวจสอบรายละเอียด</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $item_number = $offset + 1; ?>
                                <?php foreach ($data as $item): ?>
                                    <tr>
                                        <td><?php echo $item_number++; ?></td>
                                        <?php if ($main_tab == 'projects_admin'): ?>
                                            <td><?php echo (new DateTime($item['created_date']))->format('d/m/Y H:i'); ?></td>
                                            <td><?php echo htmlspecialchars($item['project_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['user_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['activity_type_name'] ?? 'ไม่ระบุ'); ?></td>
                                            <td><?php echo htmlspecialchars($item['writed_status']); ?></td>
                                            <td>
                                                <a href="?main_tab=projects_admin&mode=detail&id=<?php echo $item['project_id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="bi bi-eye"></i> ดูรายละเอียด
                                                </a>
                                            </td>
                                        <?php elseif ($main_tab == 'buildings_admin'): ?>
                                            <td><?php echo (new DateTime($item['request_date']))->format('d/m/Y H:i'); ?></td>
                                            <td><?php echo htmlspecialchars($item['project_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['facility_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['user_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo (new DateTime($item['start_date']))->format('d/m/Y'); ?> (<?php echo (new DateTime($item['start_time']))->format('H:i'); ?>)</td>
                                            <td><?php echo htmlspecialchars($item['writed_status']); ?></td>
                                            <td>
                                                <?php
                                                if (isset($item['approve']) && !empty($item['approve'])) {
                                                    if ($item['approve'] == 'อนุมัติ') {
                                                        echo '<span class="badge bg-success">อนุมัติแล้ว</span>';
                                                    } elseif ($item['approve'] == 'ไม่อนุมัติ') {
                                                        echo '<span class="badge bg-danger">ไม่อนุมัติ</span>';
                                                    } elseif ($item['approve'] == 'ยกเลิก') {
                                                        echo '<span class="badge bg-dark">ยกเลิก</span>';
                                                    } else {
                                                        echo htmlspecialchars($item['approve']);
                                                    }
                                                } elseif($item['writed_status'] != 'ยกเลิกคำร้องขอ') {
                                                    echo '<span class="badge bg-warning text-dark">รอดำเนินการ</span>';
                                                } else {
                                                    echo '<span class="badge bg-dark">ยกเลิกคำร้องขอ</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="?main_tab=buildings_admin&mode=detail&id=<?php echo $item['facility_re_id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="bi bi-eye"></i> ดูรายละเอียด
                                                </a>
                                            </td>
                                        <?php elseif ($main_tab == 'equipments_admin'): ?>
                                            <td><?php echo (new DateTime($item['request_date']))->format('d/m/Y H:i'); ?></td>
                                            <td><?php echo htmlspecialchars($item['project_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['equip_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['quantity'] . ' ' . $item['measure']); ?></td>
                                            <td><?php echo htmlspecialchars($item['user_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo (new DateTime($item['start_date']))->format('d/m/Y'); ?> - <?php echo (new DateTime($item['end_date']))->format('d/m/Y'); ?></td>
                                            <td><?php echo htmlspecialchars($item['writed_status']); ?></td>
                                            <td>
                                                <?php
                                                if (isset($item['approve']) && !empty($item['approve'])) {
                                                    if ($item['approve'] == 'อนุมัติ') {
                                                        echo '<span class="badge bg-success">อนุมัติแล้ว</span>';
                                                    } elseif ($item['approve'] == 'ไม่อนุมัติ') {
                                                        echo '<span class="badge bg-danger">ไม่อนุมัติ</span>';
                                                    } else {
                                                        echo htmlspecialchars($item['approve']);
                                                    }
                                                } elseif($item['writed_status'] != 'ยกเลิกคำร้องขอ') {
                                                    echo '<span class="badge bg-warning text-dark">รอดำเนินการ</span>';
                                                } else {
                                                    echo '<span class="badge bg-dark">ยกเลิกคำร้องขอ</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="?main_tab=equipments_admin&mode=detail&id=<?php echo $item['equip_re_id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="bi bi-eye"></i> ดูรายละเอียด
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <nav aria-label="Page navigation" class="screen-only">
                        <ul class="pagination justify-content-center">
                            <?php

                            $search_param = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                            $sort_param = !empty($_GET['sort_filter']) ? '&sort_filter=' . urlencode($_GET['sort_filter']) : '';

                            // เพิ่มพารามิเตอร์สำหรับ Date Filtering
                            $date_filter_params_for_pagination = '';
                            if (!empty($predefined_range_select)) {
                                $date_filter_params_for_pagination .= '&predefined_range=' . urlencode($predefined_range_select);
                            }
                            if (!empty($specific_year_select)) {
                                $date_filter_params_for_pagination .= '&specific_year=' . urlencode($specific_year_select);
                            }
                            if (!empty($specific_month_select)) {
                                $date_filter_params_for_pagination .= '&specific_month=' . urlencode($specific_month_select);
                            }
                            if (!empty($specific_day_select)) {
                                $date_filter_params_for_pagination .= '&specific_day=' . urlencode($specific_day_select);
                            }
                            // เพิ่มพารามิเตอร์สำหรับ Faculty Filtering
                            if (!empty($fa_de_id_filter_global)) { // ใช้ fa_de_id_filter_global
                                $date_filter_params_for_pagination .= '&fa_de_id_global=' . urlencode($fa_de_id_filter_global);
                            }
                            // --- จบการเพิ่มพารามิเตอร์สำหรับ Date Filtering ---
                            ?>

                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=<?php echo htmlspecialchars($main_tab); ?>&mode=list&page=<?php echo $current_page - 1; ?><?php echo $search_param; ?><?php echo $sort_param; ?><?php echo $date_filter_params_for_pagination; ?>">ก่อนหน้า</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?main_tab=<?php echo htmlspecialchars($main_tab); ?>&mode=list&page=<?php echo $i; ?><?php echo $search_param; ?><?php echo $sort_param; ?><?php echo $date_filter_params_for_pagination; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?> <!-- Corrected from endif; to endfor; -->
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($mode == 'detail' && $detail_item): ?>
                <?php // --- Main conditional block for detail views (Corrected structure) --- ?>

                <?php if ($main_tab == 'projects_admin'): ?>
                    <h2 class="mb-4 screen-only">รายละเอียดโครงการ: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <div class="card shadow-sm p-4 admin-details screen-only">
                        <div class="row list-text">
                            <div class="col-md-6">
                                <p><strong>ชื่อโครงการ:</strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                                <p><strong>สถานะโครงการ:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                                <p><strong>ระยะเวลาโครงการ:</strong>
                                <?php if ($detail_item['start_date'] != $detail_item['end_date']) : ?>
                                    ตั้งแต่วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['end_date'], false); ?>
                                <?php else: ?>
                                    วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?>
                                <?php endif; ?>
                                </p>
                                <p><strong>จำนวนผู้เข้าร่วม:</strong> <?php echo htmlspecialchars($detail_item['attendee']); ?></p>
                                <p><strong>หมายเลขโทรศัพท์:</strong> <?php echo htmlspecialchars($detail_item['phone_num']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <?php if (isset($detail_item['advisor_name']) && !empty($detail_item['advisor_name'])): ?>
                                    <p><strong>ชื่อที่ปรึกษาโครงการ:</strong> <?php echo htmlspecialchars($detail_item['advisor_name']); ?></p>
                                <?php endif; ?>
                                <p><strong>ประเภทกิจกรรม:</strong> <?php echo htmlspecialchars($detail_item['activity_type_name'] ?? 'ไม่ระบุ'); ?></p>
                                <p><strong>ผู้ยื่นโครงการ:</strong> <?php echo htmlspecialchars($detail_item['user_name'] ?? 'ไม่ระบุ'); ?></p>
                                <p><strong>วันที่สร้างโครงการ:</strong> <?php echo formatThaiDate($detail_item['created_date']); ?></p>
                                <p><strong>รายละเอียดโครงการ:</strong><br> <?php echo nl2br(htmlspecialchars($detail_item['project_des'])); ?></p>
                                <?php if ($detail_item['files'] && file_exists($detail_item['files'])): ?>
                                    <a href="<?php echo htmlspecialchars($detail_item['files']); ?>" target="_blank" class="btn btn-secondary btn-sm mt-2 screen-only"> ดูไฟล์แนบ</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex screen-only">
                            <a href="<?php echo htmlspecialchars($previous) ?: '#'; ?>"
                            class="btn btn-secondary me-2"
                            onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                ย้อนกลับ
                            </a>
                            <button type="button" class="btn btn-info me-2" onclick="window.print()">
                                <i class="bi bi-printer"></i> พิมพ์รายงาน
                            </button>
                        </div>

                        <h4 class="mt-4 mb-3 screen-only">คำร้องขอใช้สถานที่ที่เกี่ยวข้อง (สรุป)</h4>
                        <?php if (empty($project_facility_requests)): ?>
                            <div class="alert alert-info screen-only">ไม่มีคำร้องขอใช้สถานที่สำหรับโครงการนี้</div>
                        <?php else: ?>
                            <ul class="list-group screen-only">
                                <?php foreach ($project_facility_requests as $req): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center page-break-inside-avoid">
                                        <div>
                                            <strong><?php echo htmlspecialchars($req['facility_name']); ?></strong>
                                            (สถานะ: <?php echo htmlspecialchars($req['writed_status']); ?>)
                                            <?php if (isset($req['approve']) && !empty($req['approve'])): ?>
                                                (การอนุมัติคำร้อง: <?php echo ($req['approve'] == 'อนุมัติ') ? '<span class="badge bg-success ms-1">อนุมัติ</span>' : '<span class="badge bg-danger ms-1">ไม่อนุมัติ</span>'; ?>)
                                            <?php endif; ?>
                                            <br><small>ช่วงเวลา: <?php echo (new DateTime($req['start_date']))->format('d/m/Y'); ?> ถึง <?php echo (new DateTime($req['end_date']))->format('d/m/Y'); ?></small>
                                        </div>
                                        <a href="?main_tab=buildings_admin&mode=detail&id=<?php echo $req['facility_re_id']; ?>" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <h4 class="mt-4 mb-3 screen-only">คำร้องขอใช้อุปกรณ์ที่เกี่ยวข้อง (สรุป)</h4>
                        <?php if (empty($project_equipment_requests)): ?>
                            <div class="alert alert-info screen-only">ไม่มีคำร้องขอใช้อุปกรณ์สำหรับโครงการนี้</div>
                        <?php else: ?>
                            <ul class="list-group screen-only">
                                <?php foreach ($project_equipment_requests as $req): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center page-break-inside-avoid">
                                        <div>
                                            <strong><?php echo htmlspecialchars($req['equip_name']); ?></strong> (<?php echo htmlspecialchars($req['quantity'] . ' ' . $req['measure']); ?>)
                                            (สถานะ: <?php echo htmlspecialchars($req['writed_status']); ?>)
                                            <?php if (isset($req['approve']) && !empty($req['approve'])): ?>
                                                (การอนุมัติคำร้อง: <?php echo ($req['approve'] == 'อนุมัติ') ? '<span class="badge bg-success ms-1">อนุมัติ</span>' : '<span class="badge bg-danger ms-1">ไม่อนุมัติ</span>'; ?>)
                                            <?php endif; ?>
                                            <br><small>ช่วงเวลา: <?php echo (new DateTime($req['start_date']))->format('d/m/Y'); ?> ถึง <?php echo (new DateTime($req['end_date']))->format('d/m/Y'); ?></small>
                                        </div>
                                        <a href="?main_tab=equipments_admin&mode=detail&id=<?php echo $req['equip_re_id']; ?>" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div><!-- End of screen-only card -->

                    <!-- Print-only section for Project related requests is entirely removed -->

                <?php elseif ($main_tab == 'buildings_admin'): ?>
                    <h2 class="mb-4 screen-only">รายละเอียดคำร้องขอใช้สถานที่สำหรับโครงการ: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <div class="card shadow-sm p-4 admin-details screen-only">
                        <div class="row list-text">
                            <div class="col-md-6">
                                <p><strong>ชื่อโครงการ:</strong> <a href="?main_tab=projects_admin&mode=detail&id=<?php echo htmlspecialchars($detail_item['project_id']); ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($detail_item['project_name']); ?></a></p>
                                <p><strong>ผู้ยื่นคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['user_name'] ?? 'N/A'); ?></p>
                                <p><strong>สถานที่ที่ขอใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['facility_name']); ?></p>
                                <p><strong>สถานะคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                                <p><strong>วันเริ่มต้นการเตรียมการ:</strong> <?php echo formatThaiDate($detail_item['prepare_start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['prepare_end_date'], false); ?></p>
                                <p><strong>ตั้งแต่เวลา:</strong> <?php echo (new DateTime($detail_item['prepare_start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['prepare_end_time']))->format('H:i'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>วันที่สร้างคำร้อง:</strong> <?php echo formatThaiDate($detail_item['request_date']); ?></p>
                                <p><strong>วันเริ่มต้นการใช้งาน:</strong> <?php echo formatThaiDate($detail_item['fr_start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['fr_end_date'], false); ?></p>
                                <p><strong>ตั้งแต่เวลา:</strong> <?php echo (new DateTime($detail_item['start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['end_time']))->format('H:i'); ?></p>
                                <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                                <p class="mt-3">
                                    <strong>สถานะการอนุมัติ:</strong>
                                    <?php
                                    if (isset($detail_item['approve']) && !empty($detail_item['approve'])) {
                                        echo htmlspecialchars($detail_item['approve']);
                                    } else {
                                        echo 'รอดำเนินการ';
                                    }
                                    ?>
                                </p>
                                <?php if (isset($detail_item['approve']) && !empty($detail_item['approve'] && $detail_item['approve'] !== 'ยกเลิก')): ?>
                                    <p><strong>วันที่ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['approve_date']) && $detail_item['approve_date'] ? (new DateTime($detail_item['approve_date']))->format('d/m/Y H:i') : 'N/A'); ?></p>
                                    <p><strong>ผู้ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['staff_name']) ? ($detail_item['staff_name'] ?? 'N/A') : 'N/A'); ?></p>
                                    <?php if ($detail_item['approve'] == 'ไม่อนุมัติ'): ?>
                                        <p><strong>รายละเอียดการไม่อนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                    <?php endif; ?>
                                    <?php if ($detail_item['approve'] == 'อนุมัติ' && !empty($detail_item['approve_detail'])): ?>
                                        <p><strong>รายละเอียดการอนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div><!-- End of screen-only card -->

                <?php elseif ($main_tab == 'equipments_admin'): ?>
                    <h2 class="mb-4 screen-only">รายละเอียดคำร้องขอใช้อุปกรณ์สำหรับโครงการ: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <div class="card shadow-sm p-4 admin-details screen-only">
                        <div class="row list-text">
                            <div class="col-md-6">
                                <p><strong>ชื่อโครงการ:</strong> <a href="?main_tab=projects_admin&mode=detail&id=<?php echo htmlspecialchars($detail_item['project_id']); ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($detail_item['project_name']); ?></a></p>
                                <p><strong>ผู้ยื่นคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['user_name'] ?? 'N/A'); ?></p>
                                <p><strong>อุปกรณ์ที่ขอใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['equip_name']); ?></p>
                                <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($detail_item['quantity']) . ' ' . htmlspecialchars($detail_item['measure']); ?></p>
                                <p><strong>สถานที่นำอุปกรณ์ไปใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['facility_name'] ?? 'ไม่ระบุ'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>สถานะคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                                <p><strong>วันที่สร้างคำร้อง:</strong> <?php echo formatThaiDate($detail_item['request_date']); ?></p>
                                <p><strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['end_date'], false); ?></p>
                                <p><strong>ต้องการขนส่งอุปกรณ์:</strong> <?php echo ($detail_item['transport'] == 1) ? 'ต้องการ' : 'ไม่ต้องการ'; ?></p>
                                <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                                <p class="mt-3">
                                    <strong>สถานะการอนุมัติ:</strong>
                                    <?php
                                    if (isset($detail_item['approve']) && !empty($detail_item['approve'])) {
                                        echo htmlspecialchars($detail_item['approve']);
                                    } else {
                                        echo 'รอดำเนินการ';
                                    }
                                    ?>
                                </p>
                                <?php if (isset($detail_item['approve']) && !empty($detail_item['approve'] && $detail_item['approve'] !== 'ยกเลิก')): ?>
                                    <p><strong>วันที่ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['approve_date']) && $detail_item['approve_date'] ? (new DateTime($detail_item['approve_date']))->format('d/m/Y H:i') : 'N/A'); ?></p>
                                    <p><strong>ผู้ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['staff_name']) ? ($detail_item['staff_name'] ?? 'N/A') : 'N/A'); ?></p>
                                    <?php if ($detail_item['approve'] == 'ไม่อนุมัติ'): ?>
                                        <p><strong>รายละเอียดการไม่อนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                    <?php endif; ?>
                                    <?php if ($detail_item['approve'] == 'อนุมัติ' && !empty($detail_item['approve_detail'])): ?>
                                        <p><strong>รายละเอียดการอนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div><!-- End of screen-only card -->

                <?php endif; // Closes if ($main_tab == 'projects_admin') / elseif ($main_tab == 'buildings_admin') / elseif ($main_tab == 'equipments_admin') ?>

                <?php if ($main_tab == 'buildings_admin' || $main_tab == 'equipments_admin'): ?>
                    <div class="d-flex justify-content-between mt-4 screen-only">
                        <div>
                            <a href="<?php echo htmlspecialchars($previous) ?: '#'; ?>"
                            class="btn btn-secondary me-2"
                            onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                ย้อนกลับ
                            </a>
                            <!-- เปลี่ยนปุ่มพิมพ์สำหรับ Buildings Admin -->
                            <?php if ($main_tab == 'buildings_admin' && $detail_item): ?>
                                <a href="admin-print-page.php?id=<?php echo htmlspecialchars($detail_item['facility_re_id']); ?>&type=facility" target="_blank" class="btn btn-info me-2">
                                    <i class="bi bi-printer"></i> พิมพ์รายงาน
                                </a>
                            <?php elseif ($main_tab == 'equipments_admin' && $detail_item): ?>
                                <!-- แก้ไขลิงก์สำหรับ Equipments Admin ให้ชี้ไปที่ admin-print-page.php พร้อมพารามิเตอร์ type=equipment -->
                                <a href="admin-print-page.php?id=<?php echo htmlspecialchars($detail_item['equip_re_id']); ?>&type=equipment" target="_blank" class="btn btn-info me-2">
                                    <i class="bi bi-printer"></i> พิมพ์รายงาน
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-info me-2" onclick="alert('ไม่มีข้อมูลให้พิมพ์ หรือยังไม่รองรับการพิมพ์สำหรับหน้านี้');">
                                    <i class="bi bi-printer"></i> พิมพ์รายงาน
                                </button>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php
                            // Show approve/reject buttons only if the request has not been approved/rejected/cancelled yet
                            if (($main_tab == 'buildings_admin' || $main_tab == 'equipments_admin') &&
                                ($detail_item['approve'] !== 'อนุมัติ' && $detail_item['approve'] !== 'ไม่อนุมัติ' && $detail_item['approve'] !== 'ยกเลิก')):
                            ?>
                                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#approveModal">
                                    อนุมัติ
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    ไม่อนุมัติ
                                </button>

                                <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="" method="POST">
                                                <div class="modal-header bg-success text-white">
                                                    <h5 class="modal-title" id="approveModalLabel">เพิ่มรายละเอียดการอนุมัติ (ไม่บังคับ)</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="approve_detail_optional" class="form-label">รายละเอียดเพิ่มเติม:</label>
                                                        <textarea class="form-control" id="approve_detail_optional" name="approve_detail" rows="3"></textarea>
                                                        <small class="text-muted">คุณสามารถเพิ่มบันทึกเกี่ยวกับการอนุมัติคำร้องนี้ได้ (ไม่บังคับ)</small>
                                                    </div>
                                                    <input type="hidden" name="action" value="approve_request">
                                                    <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($detail_item['facility_re_id'] ?? $detail_item['equip_re_id']); ?>">
                                                    <input type="hidden" name="request_type" value="<?php echo ($main_tab == 'buildings_admin') ? 'facility' : 'equipment'; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                    <button type="submit" class="btn btn-success">ยืนยันอนุมัติ</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="" method="POST">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title" id="rejectModalLabel">ระบุเหตุผลการไม่อนุมัติ</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="reject_reason" class="form-label">เหตุผล:</label>
                                                        <textarea class="form-control" id="reject_reason" name="approve_detail" rows="3" required></textarea>
                                                    </div>
                                                    <input type="hidden" name="action" value="reject_request">
                                                    <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($detail_item['facility_re_id'] ?? $detail_item['equip_re_id']); ?>">
                                                    <input type="hidden" name="request_type" value="<?php echo ($main_tab == 'buildings_admin') ? 'facility' : 'equipment'; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                    <button type="submit" class="btn btn-danger">ยืนยันไม่อนุมัติ</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; // Closes elseif ($mode == 'detail' && $detail_item) ?>
        </div> <!-- End of admin-content-area -->
    </div> <!-- End of admin-main-wrapper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="./js/admin_menu.js"></script>
<!-- อ้างอิงไฟล์ JavaScript Chart.js ที่แยกออกมา -->
<script src="./js/chart.js"></script>
</body>
</html>