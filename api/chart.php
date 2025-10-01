<?php
session_start();
header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'เจ้าหน้าที่') {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

include '../database/database.php'; 
include '../php/admin-sorting.php'; 

$predefined_range_select = $_GET['predefined_range'] ?? null;
$specific_year_select = $_GET['specific_year'] ?? null;
$specific_month_select = $_GET['specific_month'] ?? null;
$specific_day_select = $_GET['specific_day'] ?? null;
$fa_de_id_filter_global = $_GET['fa_de_id'] ?? null; 

$chart_data = [
    'labels' => [],       // ชื่อคณะ
    'projects' => [],     // จำนวนโครงการ
    'facilities' => [],   // จำนวนคำร้องขอสถานที่
    'equipments' => []    // จำนวนคำร้องขออุปกรณ์
];

try {
    // 1. ดึงข้อมูลคณะทั้งหมด หรือเฉพาะคณะที่ถูกเลือก
    $sql_faculties = "SELECT fa_de_id, fa_de_name FROM faculties_department";
    $fa_de_params = [];
    $fa_de_param_types = '';

    if (!empty($fa_de_id_filter_global)) {
        $sql_faculties .= " WHERE fa_de_id = ?";
        $fa_de_params[] = (int)$fa_de_id_filter_global;
        $fa_de_param_types .= 'i';
    }
    $sql_faculties .= " ORDER BY fa_de_name ASC";

    $stmt_faculties = $conn->prepare($sql_faculties);
    if ($stmt_faculties === false) {
        throw new Exception("Failed to prepare faculty statement: " . $conn->error);
    }
    if (!empty($fa_de_param_types)) {
        $stmt_faculties->bind_param($fa_de_param_types, ...$fa_de_params);
    }
    $stmt_faculties->execute();
    $result_faculties = $stmt_faculties->get_result();
    $faculties_to_process = [];
    while ($row = $result_faculties->fetch_assoc()) {
        $faculties_to_process[] = $row;
        $chart_data['labels'][] = htmlspecialchars($row['fa_de_name']);
    }
    $stmt_faculties->close();

    if (empty($faculties_to_process)) {
        echo json_encode($chart_data);
        exit();
    }

    // 2. วนลูปแต่ละคณะเพื่อดึงข้อมูลสถิติ
    foreach ($faculties_to_process as $faculty) {
        $current_fa_de_id = $faculty['fa_de_id'];

        $project_count = 0;
        $facility_count = 0;
        $equipment_count = 0;

        $date_filter_proj = getDateFilteringClauses('dashboard_projects', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);
        $sql_proj_count = "SELECT COUNT(p.project_id) AS count 
                           FROM project p 
                           JOIN user u ON p.nontri_id = u.nontri_id 
                           WHERE p.writed_status != 'ร่างโครงการ' 
                           AND u.fa_de_id = ? " . $date_filter_proj['where_sql'];

        $stmt_proj_count = $conn->prepare($sql_proj_count);
        if ($stmt_proj_count === false) {
            throw new Exception("Failed to prepare project count statement: " . $conn->error);
        }
        $proj_params = [(int)$current_fa_de_id];
        $proj_param_types = 'i';
        $proj_params = array_merge($proj_params, $date_filter_proj['param_values']);
        $proj_param_types .= $date_filter_proj['param_types'];
        
        $stmt_proj_count->bind_param($proj_param_types, ...$proj_params);
        $stmt_proj_count->execute();
        $result_proj_count = $stmt_proj_count->get_result();
        if ($row = $result_proj_count->fetch_assoc()) {
            $project_count = $row['count'];
        }
        $stmt_proj_count->close();
        $chart_data['projects'][] = $project_count;

        $date_filter_fr = getDateFilteringClauses('dashboard_facilities_requests', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);
        $sql_fr_count = "SELECT COUNT(fr.facility_re_id) AS count 
                         FROM facilities_requests fr 
                         JOIN project p ON fr.project_id = p.project_id 
                         JOIN user u ON p.nontri_id = u.nontri_id 
                         WHERE fr.writed_status != 'ร่างคำร้องขอ' 
                         AND u.fa_de_id = ? " . $date_filter_fr['where_sql'];

        $stmt_fr_count = $conn->prepare($sql_fr_count);
        if ($stmt_fr_count === false) {
            throw new Exception("Failed to prepare facility request count statement: " . $conn->error);
        }
        $fr_params = [(int)$current_fa_de_id];
        $fr_param_types = 'i';
        $fr_params = array_merge($fr_params, $date_filter_fr['param_values']);
        $fr_param_types .= $date_filter_fr['param_types'];
        
        $stmt_fr_count->bind_param($fr_param_types, ...$fr_params);
        $stmt_fr_count->execute();
        $result_fr_count = $stmt_fr_count->get_result();
        if ($row = $result_fr_count->fetch_assoc()) {
            $facility_count = $row['count'];
        }
        $stmt_fr_count->close();
        $chart_data['facilities'][] = $facility_count;

        $date_filter_er = getDateFilteringClauses('dashboard_equipments_requests', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);
        $sql_er_count = "SELECT COUNT(er.equip_re_id) AS count 
                         FROM equipments_requests er 
                         JOIN project p ON er.project_id = p.project_id 
                         JOIN user u ON p.nontri_id = u.nontri_id 
                         WHERE er.writed_status != 'ร่างคำร้องขอ' 
                         AND u.fa_de_id = ? " . $date_filter_er['where_sql'];

        $stmt_er_count = $conn->prepare($sql_er_count);
        if ($stmt_er_count === false) {
            throw new Exception("Failed to prepare equipment request count statement: " . $conn->error);
        }
        $er_params = [(int)$current_fa_de_id];
        $er_param_types = 'i';
        $er_params = array_merge($er_params, $date_filter_er['param_values']);
        $er_param_types .= $date_filter_er['param_types'];
        
        $stmt_er_count->bind_param($er_param_types, ...$er_params);
        $stmt_er_count->execute();
        $result_er_count = $stmt_er_count->get_result();
        if ($row = $result_er_count->fetch_assoc()) {
            $equipment_count = $row['count'];
        }
        $stmt_er_count->close();
        $chart_data['equipments'][] = $equipment_count;
    }

    echo json_encode($chart_data);

} catch (Exception $e) {
    error_log("Chart data error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to retrieve chart data: ' . $e->getMessage()]);
} finally {
    // ตรวจสอบให้แน่ใจว่าการเชื่อมต่อถูกปิดเสมอ
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->close();
    }
}
?>