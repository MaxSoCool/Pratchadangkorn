<?php

if (!isset($conn)) {
    include dirname(__DIR__) . '/database/database.php';
}
// ตรวจสอบ helper functions
if (!function_exists('formatThaiDate')) {
    include 'helpers.php';
}
if (!function_exists('getDateFilteringClauses')) {
    include 'admin-sorting.php'; 
}

$DASHBOARD_UPCOMING_DAYS_LIMIT = 14;
$DASHBOARD_RECENT_ACTIVITY_LIMIT = 5;

if (!isset($total_projects_count)) $total_projects_count = 0;
if (!isset($total_facilities_requests_count)) $total_facilities_requests_count = 0;
if (!isset($total_equipments_requests_count)) $total_equipments_requests_count = 0;
if (!isset($dashboard_data['upcoming_requests'])) $dashboard_data['upcoming_requests'] = [];
if (!isset($dashboard_data['recent_activity'])) $dashboard_data['recent_activity'] = [];
if (!isset($errors)) $errors = [];

try {
    // รับค่าตัวแปร ($predefined_range_select, $specific_year_select, ฯลฯ) ถูกส่งมาจาก admin-main-page.php
    $date_filter_params_proj = getDateFilteringClauses('dashboard_projects', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);
    $date_filter_params_fr = getDateFilteringClauses('dashboard_facilities_requests', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);
    $date_filter_params_er = getDateFilteringClauses('dashboard_equipments_requests', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);

    // นับจำนวนโครงการทั้งหมด (ยกเว้นร่างโครงการ)
    $sql_proj_count = "SELECT COUNT(*) FROM project p LEFT JOIN user u ON p.nontri_id = u.nontri_id WHERE p.writed_status != 'ร่างโครงการ' " . ($date_filter_params_proj['where_sql'] ?? '');
    $count_params_proj = $date_filter_params_proj['param_values'] ?? [];
    $count_param_types_proj = $date_filter_params_proj['param_types'] ?? '';

    if (!empty($fa_de_id_filter_global)) {
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

    // นับจำนวนคำร้องขอสถานที่ทั้งหมด (ยกเว้นร่างคำร้องขอ)
    $sql_fac_req_count = "SELECT COUNT(*) FROM facilities_requests fr JOIN project p ON fr.project_id = p.project_id LEFT JOIN user u ON p.nontri_id = u.nontri_id WHERE fr.writed_status != 'ร่างคำร้องขอ' " . ($date_filter_params_fr['where_sql'] ?? '');
    $count_params_fr = $date_filter_params_fr['param_values'] ?? [];
    $count_param_types_fr = $date_filter_params_fr['param_types'] ?? '';

    if (!empty($fa_de_id_filter_global)) {
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

    // นับจำนวนคำร้องขออุปกรณ์ทั้งหมด (ยกเว้นร่างคำร้องขอ)
    $sql_equip_req_count = "SELECT COUNT(*) FROM equipments_requests er JOIN project p ON er.project_id = p.project_id LEFT JOIN user u ON p.nontri_id = u.nontri_id WHERE er.writed_status != 'ร่างคำร้องขอ' " . ($date_filter_params_er['where_sql'] ?? '');
    $count_params_er = $date_filter_params_er['param_values'] ?? [];
    $count_param_types_er = $date_filter_params_er['param_types'] ?? '';

    if (!empty($fa_de_id_filter_global)) {
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

    // แสดงโครงการที่กำลังจะมาถึงภายใน 14 วัน
    $upcoming_date_limit = date('Y-m-d', strtotime('+' . $DASHBOARD_UPCOMING_DAYS_LIMIT . ' days'));
    $current_date_php = date('Y-m-d');

    $sql_upcoming_projects = "SELECT p.project_id AS id, 'โครงการ' AS type, p.project_name AS name,
                                p.start_date, p.end_date, NULL AS start_time, NULL AS end_time,
                                p.project_name AS project_name_for_display, p.writed_status AS writed_status_for_display, NULL AS approve_for_display
                        FROM project p
                        JOIN user u ON p.nontri_id = u.nontri_id
                        WHERE p.start_date BETWEEN ? AND ?
                        AND (p.writed_status = 'ส่งโครงการ' OR p.writed_status = 'เริ่มดำเนินการ')";

    $upcoming_proj_params = [$current_date_php, $upcoming_date_limit];
    $upcoming_proj_param_types = "ss";

    if (!empty($fa_de_id_filter_global)) {
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

    // จัดเรียงกิจกรรมที่กำลังจะมาถึง
    if (isset($dashboard_data['upcoming_requests']) && is_array($dashboard_data['upcoming_requests'])) {
        usort($dashboard_data['upcoming_requests'], function($a, $b) {
            return strtotime($a['start_date']) - strtotime($b['start_date']);
        });
        $dashboard_data['upcoming_requests'] = array_slice($dashboard_data['upcoming_requests'], 0, $DASHBOARD_RECENT_ACTIVITY_LIMIT);
    } else {
        $dashboard_data['upcoming_requests'] = [];
    }


    // คำร้องล่าสุดจากผู้ใช้
    $all_recent_activity_raw = [];

    // คำร้องขอสถานที่ล่าสุดที่รอการอนุมัติ
    $sql_recent_fr = "SELECT fr.facility_re_id AS id, 'คำร้องขอสถานที่' AS item_type, f.facility_name AS item_name,
                            fr.request_date AS activity_date, fr.writed_status AS status_text, fr.approve AS approve_status
                    FROM facilities_requests fr
                    JOIN project p ON fr.project_id = p.project_id
                    JOIN facilities f ON fr.facility_id = f.facility_id
                    JOIN user u ON p.nontri_id = u.nontri_id
                    WHERE fr.writed_status = 'ส่งคำร้องขอ' AND fr.approve IS NULL";

    $recent_fr_params = [];
    $recent_fr_param_types = "";
    if (!empty($fa_de_id_filter_global)) {
        $sql_recent_fr .= " AND u.fa_de_id = ?";
        $recent_fr_params[] = (int)$fa_de_id_filter_global;
        $recent_fr_param_types .= "i";
    }
    $sql_recent_fr .= " ORDER BY fr.request_date DESC LIMIT " . $DASHBOARD_RECENT_ACTIVITY_LIMIT;

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

    // คำร้องขออุปกรณ์ล่าสุดที่รอการอนุมัติ
    $sql_recent_er = "SELECT er.equip_re_id AS id, 'คำร้องขออุปกรณ์' AS item_type, e.equip_name AS item_name,
                            er.request_date AS activity_date, er.writed_status AS status_text, er.approve AS approve_status
                    FROM equipments_requests er
                    JOIN project p ON er.project_id = p.project_id
                    JOIN equipments e ON er.equip_id = e.equip_id
                    JOIN user u ON p.nontri_id = u.nontri_id
                    WHERE er.writed_status = 'ส่งคำร้องขอ' AND er.approve IS NULL";

    $recent_er_params = [];
    $recent_er_param_types = "";
    if (!empty($fa_de_id_filter_global)) {
        $sql_recent_er .= " AND u.fa_de_id = ?";
        $recent_er_params[] = (int)$fa_de_id_filter_global;
        $recent_er_param_types .= "i";
    }
    $sql_recent_er .= " ORDER BY er.request_date DESC LIMIT " . $DASHBOARD_RECENT_ACTIVITY_LIMIT;

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

    if (isset($all_recent_activity_raw) && is_array($all_recent_activity_raw)) {
        usort($all_recent_activity_raw, function($a, $b) {
            return strtotime($b['activity_date']) - strtotime($a['activity_date']);
        });
        $dashboard_data['recent_activity'] = array_slice($all_recent_activity_raw, 0, $DASHBOARD_RECENT_ACTIVITY_LIMIT);
    } else {
        $dashboard_data['recent_activity'] = [];
    }

} catch (Exception $e) {
    error_log("Admin Dashboard Data Fetching Error: " . $e->getMessage());
    $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล Dashboard: " . $e->getMessage();
}
?>