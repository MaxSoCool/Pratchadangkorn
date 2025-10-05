<?php
session_start();
header('Content-Type: application/json');

// Check login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'เจ้าหน้าที่') {
    echo json_encode(['error' => 'Unauthorized access.']);
    exit();
}

include '../database/database.php';
include '../php/admin-sorting.php'; // Required for getDateFilteringClauses
include '../php/chart-sorting.php'; // New chart sorting logic

$predefined_range_select = $_GET['predefined_range'] ?? null;
$specific_year_select = $_GET['specific_year'] ?? null;
$specific_month_select = $_GET['specific_month'] ?? null;
$specific_day_select = $_GET['specific_day'] ?? null;
$fa_de_id_filter_global = $_GET['fa_de_id_global'] ?? null; // Global faculty filter for dashboard

// Chart-specific sorting parameters
$chart_sort_mode = $_GET['chart_sort_mode'] ?? 'faculty_overview'; // Default to faculty overview
$drilldown_type = $_GET['drilldown_type'] ?? null;
$drilldown_id = $_GET['drilldown_id'] ?? null;

$faculty_colors = [
    'ทรัพยากรธรรมชาติและอุตสาหกรรมเกษตร' => '#4C8C2B',
    'วิทยาศาสตร์และวิศวกรรมศาสตร์' => '#72242B',
    'ศิลปศาสตร์และวิทยาการจัดการ' => '#5BC2E7',
    'สาธารณสุขศาสตร์' => '#EE575F',
    'Default' => '#6c757d' // A default color if a faculty doesn't have a specific one
];

$chart_data = [
    'labels' => [],
    'datasets' => [],
    'chart_title' => '',
    'chart_mode' => $chart_sort_mode,
    'drilldown_type' => $drilldown_type,
    'drilldown_id' => $drilldown_id,
    'faculty_colors' => $faculty_colors, // Pass colors to JS for consistent rendering
    'extra_info' => [], // For tooltips
    'extra_title_line' => '' // For filtering indicator
];

try {
    // ดึงคณะ/หน่วยงานทั้งหมดที่เคยมี Activity อย่างน้อย 1 รายการในระบบ
    // เพื่อใช้เป็นพื้นฐานสำหรับ datasets ใน Stacked Bar Chart
    $combined_active_faculties_sql_base = "
        SELECT DISTINCT fd.fa_de_id, fd.fa_de_name
        FROM faculties_department fd
        LEFT JOIN user u ON fd.fa_de_id = u.fa_de_id
        LEFT JOIN project p ON u.nontri_id = p.nontri_id
        LEFT JOIN facilities_requests fr ON p.project_id = fr.project_id AND fr.writed_status != 'ร่างคำร้องขอ'
        LEFT JOIN equipments_requests er ON p.project_id = er.project_id AND er.writed_status != 'ร่างคำร้องขอ'
        WHERE (p.project_id IS NOT NULL AND p.writed_status != 'ร่างโครงการ')
           OR fr.facility_re_id IS NOT NULL
           OR er.equip_re_id IS NOT NULL
    ";

    $combined_params = [];
    $combined_param_types = '';

    // Apply global faculty filter if present (this filter is for the dashboard cards and upcoming/recent activity)
    if (!empty($fa_de_id_filter_global)) {
        $combined_active_faculties_sql_base .= " AND fd.fa_de_id = ?";
        $combined_params[] = (int)$fa_de_id_filter_global;
        $combined_param_types .= 'i';
    }
    $combined_active_faculties_sql_base .= " ORDER BY fa_de_name ASC";

    $stmt_all_active_faculties = $conn->prepare($combined_active_faculties_sql_base);
    if ($stmt_all_active_faculties === false) {
        throw new Exception("Failed to prepare all active faculties statement: " . $conn->error);
    }
    if (!empty($combined_param_types)) {
        $stmt_all_active_faculties->bind_param($combined_param_types, ...$combined_params);
    }
    $stmt_all_active_faculties->execute();
    $result_all_active_faculties = $stmt_all_active_faculties->get_result();

    $all_active_faculties_map = []; // fa_de_id => fa_de_name (trimmed)
    while ($row = $result_all_active_faculties->fetch_assoc()) {
        $trimmed_fa_de_name = trim($row['fa_de_name']);
        $all_active_faculties_map[$row['fa_de_id']] = $trimmed_fa_de_name;
    }
    $stmt_all_active_faculties->close();
    
    // Construct extra title line for filtering indicator
    $extra_title_parts = [];
    if (!empty($predefined_range_select)) {
        $thai_predefined_ranges = [
            'today' => 'วันนี้', 'this_week' => 'สัปดาห์นี้',
            'this_month' => 'เดือนนี้', 'this_year' => 'ปีนี้'
        ];
        if (isset($thai_predefined_ranges[$predefined_range_select])) {
            $extra_title_parts[] = "ช่วง: " . $thai_predefined_ranges[$predefined_range_select];
        }
    } elseif (!empty($specific_year_select)) {
        $date_text = "ปี: " . ($specific_year_select + 543); // Thai year
        if (!empty($specific_month_select)) {
            $thai_months_full = [
                1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
                5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
                9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
            ];
            $date_text = $thai_months_full[(int)$specific_month_select] . " " . ($specific_year_select + 543);
            if (!empty($specific_day_select)) {
                $date_text = (int)$specific_day_select . " " . $thai_months_full[(int)$specific_month_select] . " " . ($specific_year_select + 543);
            }
        }
        $extra_title_parts[] = "วันที่: " . $date_text;
    }

    // Only fetch faculty name if fa_de_id_filter_global is set
    if (!empty($fa_de_id_filter_global)) {
        $stmt_fa_name_filter = $conn->prepare("SELECT fa_de_name FROM faculties_department WHERE fa_de_id = ?");
        $stmt_fa_name_filter->bind_param("i", $fa_de_id_filter_global);
        $stmt_fa_name_filter->execute();
        $fa_name_row = $stmt_fa_name_filter->get_result()->fetch_assoc();
        if ($fa_name_row) {
            $extra_title_parts[] = "คณะ: " . htmlspecialchars($fa_name_row['fa_de_name']);
        }
        $stmt_fa_name_filter->close();
    }

    if (!empty($extra_title_parts)) {
        $chart_data['extra_title_line'] = " (กรองโดย: " . implode(" | ", $extra_title_parts) . ")";
    }

    // If no faculties are active *after global filter*, send empty data
    if (empty($all_active_faculties_map) && !in_array($chart_sort_mode, ['faculty_overview', 'top_facilities', 'top_equipments'])) {
        // Only return empty if it's a drilldown or overview, and no faculties are found.
        // For top_facilities/equipments, we want to proceed to see if any items have data regardless of global faculty filter initially.
        echo json_encode($chart_data);
        exit();
    }
    

    switch ($chart_sort_mode) {
        case 'faculty_overview':
            $chart_data['chart_title'] = 'สถิติคำร้องและโครงการจำแนกตามคณะ';
            $faculty_labels = [];
            $projects_data = [];
            $facilities_data = [];
            $equipments_data = [];
            $faculty_ids_for_drilldown = []; 

            foreach ($all_active_faculties_map as $fa_de_id => $fa_de_name) {
                $current_fa_de_id = $fa_de_id;
                $total_count_for_faculty = 0;

                $date_filter_proj = getDateFilteringClauses('dashboard_projects', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);
                $sql_proj_count = "SELECT COUNT(p.project_id) AS count
                                   FROM project p
                                   JOIN user u ON p.nontri_id = u.nontri_id
                                   WHERE p.writed_status != 'ร่างโครงการ'
                                   AND u.fa_de_id = ? " . $date_filter_proj['where_sql'];
                $stmt_proj_count = $conn->prepare($sql_proj_count);
                if ($stmt_proj_count === false) throw new Exception("Failed to prepare project count statement: " . $conn->error);
                $proj_params = [(int)$current_fa_de_id];
                $proj_param_types = 'i';
                $proj_params = array_merge($proj_params, $date_filter_proj['param_values']);
                $proj_param_types .= $date_filter_proj['param_types'];
                $stmt_proj_count->bind_param($proj_param_types, ...$proj_params);
                $stmt_proj_count->execute();
                $row = $stmt_proj_count->get_result()->fetch_assoc();
                $current_projects_count = $row['count'];
                $stmt_proj_count->close();
                $total_count_for_faculty += $current_projects_count;

                $date_filter_fr = getDateFilteringClauses('dashboard_facilities_requests', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);
                $sql_fr_count = "SELECT COUNT(fr.facility_re_id) AS count
                                 FROM facilities_requests fr
                                 JOIN project p ON fr.project_id = p.project_id
                                 JOIN user u ON p.nontri_id = u.nontri_id
                                 WHERE fr.writed_status != 'ร่างคำร้องขอ'
                                 AND u.fa_de_id = ? " . $date_filter_fr['where_sql'];
                $stmt_fr_count = $conn->prepare($sql_fr_count);
                if ($stmt_fr_count === false) throw new Exception("Failed to prepare facility request count statement: " . $conn->error);
                $fr_params = [(int)$current_fa_de_id];
                $fr_param_types = 'i';
                $fr_params = array_merge($fr_params, $date_filter_fr['param_values']);
                $fr_param_types .= $date_filter_fr['param_types'];
                $stmt_fr_count->bind_param($fr_param_types, ...$fr_params);
                $stmt_fr_count->execute();
                $row = $stmt_fr_count->get_result()->fetch_assoc();
                $current_facilities_count = $row['count'];
                $stmt_fr_count->close();
                $total_count_for_faculty += $current_facilities_count;

                $date_filter_er = getDateFilteringClauses('dashboard_equipments_requests', $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);
                $sql_er_count = "SELECT COUNT(er.equip_re_id) AS count
                                 FROM equipments_requests er
                                 JOIN project p ON er.project_id = p.project_id
                                 JOIN user u ON p.nontri_id = u.nontri_id
                                 WHERE er.writed_status != 'ร่างคำร้องขอ'
                                 AND u.fa_de_id = ? " . $date_filter_er['where_sql'];
                $stmt_er_count = $conn->prepare($sql_er_count);
                if ($stmt_er_count === false) throw new Exception("Failed to prepare equipment request count statement: " . $conn->error);
                $er_params = [(int)$current_fa_de_id];
                $er_param_types = 'i';
                $er_params = array_merge($er_params, $date_filter_er['param_values']);
                $er_param_types .= $date_filter_er['param_types'];
                $stmt_er_count->bind_param($er_param_types, ...$er_params);
                $stmt_er_count->execute();
                $row = $stmt_er_count->get_result()->fetch_assoc();
                $current_equipments_count = $row['count'];
                $stmt_er_count->close();
                $total_count_for_faculty += $current_equipments_count;

                if ($total_count_for_faculty > 0) {
                    $faculty_labels[] = htmlspecialchars($fa_de_name);
                    $projects_data[] = $current_projects_count;
                    $facilities_data[] = $current_facilities_count;
                    $equipments_data[] = $current_equipments_count;
                    $faculty_ids_for_drilldown[htmlspecialchars($fa_de_name)] = $current_fa_de_id;
                }
            }

            if (empty($faculty_labels)) {
                 echo json_encode($chart_data);
                 exit();
            }

            $chart_data['labels'] = $faculty_labels;
            $chart_data['datasets'] = [
                [
                    'label' => 'โครงการ',
                    'data' => $projects_data,
                    'backgroundColor' => '#0d6efd',
                    'borderColor' => '#0149b6ff',
                    'borderWidth' => 1
                ],
                [
                    'label' => 'คำร้องขอสถานที่',
                    'data' => $facilities_data,
                    'backgroundColor' => '#198754',
                    'borderColor' => '#084a2bff',
                    'borderWidth' => 1
                ],
                [
                    'label' => 'คำร้องขอใช้อุปกรณ์',
                    'data' => $equipments_data,
                    'backgroundColor' => '#ffc107',
                    'borderColor' => 'rgba(182, 93, 4, 1)',
                    'borderWidth' => 1
                ]
            ];
            $chart_data['faculty_ids_map'] = $faculty_ids_for_drilldown;
            break;

        case 'faculty_drilldown': // This mode is not reachable via UI clicks anymore
            // Fallback for direct URL access, should not occur from UI
            echo json_encode($chart_data);
            exit();

        case 'top_facilities':
            $chart_data['chart_title'] = 'สถานที่ 10 อันดับแรกที่มีการร้องขอมากที่สุด';
            $filtering = getChartFilteringClauses(
                'top_facilities',
                $predefined_range_select,
                $specific_year_select,
                $specific_month_select,
                $specific_day_select
            );

            $where_clauses_arr = ["fr.writed_status != 'ร่างคำร้องขอ'"];
            $params_arr = [];
            $param_types_str = '';

            if (!empty($filtering['where_clauses'])) {
                $where_clauses_arr = array_merge($where_clauses_arr, $filtering['where_clauses']);
                $params_arr = array_merge($params_arr, $filtering['param_values']);
                $param_types_str .= $filtering['param_types'];
            }
            if (!empty($fa_de_id_filter_global)) {
                $where_clauses_arr[] = "u.fa_de_id = ?";
                $params_arr[] = (int)$fa_de_id_filter_global;
                $param_types_str .= 'i';
            }

            $final_where_clause_sql = " WHERE " . implode(" AND ", $where_clauses_arr);

            $sql_top_10_ids = "SELECT f.facility_id, f.facility_name, b.building_name, COUNT(fr.facility_re_id) AS count_requests
                               FROM facilities_requests fr
                               JOIN facilities f ON fr.facility_id = f.facility_id
                               LEFT JOIN buildings b ON f.building_id = b.building_id
                               JOIN project p ON fr.project_id = p.project_id
                               JOIN user u ON p.nontri_id = u.nontri_id
                               " . $final_where_clause_sql . "
                               GROUP BY f.facility_id, f.facility_name, b.building_name
                               ORDER BY count_requests DESC
                               LIMIT 10";

            $stmt_top_10 = $conn->prepare($sql_top_10_ids);
            if ($stmt_top_10 === false) {
                error_log("Failed to prepare top 10 facilities ID statement: " . $conn->error . ". SQL: " . $sql_top_10_ids . ". Params: " . implode(',', $params_arr) . ". Types: " . $param_types_str);
                throw new Exception("Failed to prepare top 10 facilities ID statement: " . $conn->error);
            }
            if (!empty($param_types_str)) {
                $stmt_top_10->bind_param($param_types_str, ...$params_arr);
            }
            $stmt_top_10->execute();
            $result_top_10 = $stmt_top_10->get_result();

            $top_facility_ids = [];
            $top_facility_names = [];
            $top_facility_extra_info = [];
            while ($row = $result_top_10->fetch_assoc()) {
                $top_facility_ids[] = (int)$row['facility_id'];
                $top_facility_names[] = htmlspecialchars($row['facility_name']);
                $top_facility_extra_info[] = [
                    'id' => (int)$row['facility_id'],
                    'name' => htmlspecialchars($row['facility_name']),
                    'building_name' => htmlspecialchars($row['building_name'] ?? 'ไม่ระบุอาคาร')
                ];
            }
            $stmt_top_10->close();

            if (empty($top_facility_ids)) {
                echo json_encode($chart_data);
                exit();
            }

            $chart_data['labels'] = $top_facility_names;
            $chart_data['extra_info'] = $top_facility_extra_info;

            $placeholders = implode(',', array_fill(0, count($top_facility_ids), '?'));
            $sql_grouped_data = "SELECT
                                    fr.facility_id,
                                    fd.fa_de_id,
                                    fd.fa_de_name,
                                    COUNT(fr.facility_re_id) AS count_requests
                                FROM facilities_requests fr
                                JOIN project p ON fr.project_id = p.project_id
                                JOIN user u ON p.nontri_id = u.nontri_id
                                JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
                                WHERE fr.facility_id IN ({$placeholders})
                                AND fr.writed_status != 'ร่างคำร้องขอ' ";

            $grouped_params = $top_facility_ids;
            $grouped_param_types = str_repeat('i', count($top_facility_ids));

            $grouped_conditions = [];
            if (!empty($filtering['where_clauses'])) {
                 $grouped_conditions = array_merge($grouped_conditions, $filtering['where_clauses']);
                 $grouped_params = array_merge($grouped_params, $filtering['param_values']);
                 $grouped_param_types .= $filtering['param_types'];
            }
            if (!empty($fa_de_id_filter_global)) {
                $grouped_conditions[] = "u.fa_de_id = ?";
                $grouped_params[] = (int)$fa_de_id_filter_global;
                $grouped_param_types .= 'i';
            }
            if(!empty($grouped_conditions)) {
                $sql_grouped_data .= " AND " . implode(" AND ", $grouped_conditions);
            }

            $sql_grouped_data .= " GROUP BY fr.facility_id, fd.fa_de_id";

            $stmt_grouped_data = $conn->prepare($sql_grouped_data);
            if ($stmt_grouped_data === false) {
                error_log("Failed to prepare grouped data statement for top facilities: " . $conn->error . ". SQL: " . $sql_grouped_data . ". Params: " . implode(',', $grouped_params) . ". Types: " . $grouped_param_types);
                throw new Exception("Failed to prepare grouped data statement: " . $conn->error);
            }

            $stmt_grouped_data->bind_param($grouped_param_types, ...$grouped_params);
            $stmt_grouped_data->execute();
            $result_grouped_data = $stmt_grouped_data->get_result();

            $facility_data_by_faculty = []; // fa_de_id => [facility_id => count]
            while ($row = $result_grouped_data->fetch_assoc()) {
                $fa_de_id = $row['fa_de_id'];
                $facility_id = $row['facility_id'];
                $count = $row['count_requests'];
                $facility_data_by_faculty[$fa_de_id][$facility_id] = $count;
            }
            $stmt_grouped_data->close();

            // For Grouped Bar Chart, each dataset is still a faculty, but the 'stacked' option is false.
            foreach ($all_active_faculties_map as $fa_de_id => $fa_de_name) {
                $dataset_data = [];
                $total_dataset_count = 0; // Check if this faculty has any data for *any* of the top 10
                foreach ($top_facility_ids as $f_id) {
                    $count_val = $facility_data_by_faculty[$fa_de_id][$f_id] ?? 0;
                    $dataset_data[] = $count_val;
                    $total_dataset_count += $count_val;
                }
                if ($total_dataset_count > 0) { // Only add dataset if faculty has data for at least one item
                    $chart_data['datasets'][] = [
                        'label' => htmlspecialchars($fa_de_name),
                        'data' => $dataset_data,
                        'backgroundColor' => $faculty_colors[$fa_de_name] ?? $faculty_colors['Default'],
                        'borderWidth' => 1
                    ];
                }
            }
            break;

        case 'top_equipments':
            $chart_data['chart_title'] = 'อุปกรณ์ 10 อันดับแรกที่มีการร้องขอมากที่สุด';
            $filtering = getChartFilteringClauses(
                'top_equipments',
                $predefined_range_select,
                $specific_year_select,
                $specific_month_select,
                $specific_day_select
            );

            $where_clauses_arr = ["er.writed_status != 'ร่างคำร้องขอ'"];
            $params_arr = [];
            $param_types_str = '';

            if (!empty($filtering['where_clauses'])) {
                $where_clauses_arr = array_merge($where_clauses_arr, $filtering['where_clauses']);
                $params_arr = array_merge($params_arr, $filtering['param_values']);
                $param_types_str .= $filtering['param_types'];
            }
            if (!empty($fa_de_id_filter_global)) {
                $where_clauses_arr[] = "u.fa_de_id = ?";
                $params_arr[] = (int)$fa_de_id_filter_global;
                $param_types_str .= 'i';
            }
            $final_where_clause_sql = " WHERE " . implode(" AND ", $where_clauses_arr);

            $sql_top_10_ids = "SELECT e.equip_id, e.equip_name, e.measure, COUNT(er.equip_re_id) AS count_requests
                               FROM equipments_requests er
                               JOIN equipments e ON er.equip_id = e.equip_id
                               JOIN project p ON er.project_id = p.project_id
                               JOIN user u ON p.nontri_id = u.nontri_id
                               " . $final_where_clause_sql . "
                               GROUP BY e.equip_id, e.equip_name, e.measure
                               ORDER BY count_requests DESC
                               LIMIT 10";

            $stmt_top_10 = $conn->prepare($sql_top_10_ids);
            if ($stmt_top_10 === false) {
                error_log("Failed to prepare top 10 equipment ID statement: " . $conn->error . ". SQL: " . $sql_top_10_ids . ". Params: " . implode(',', $params_arr) . ". Types: " . $param_types_str);
                throw new Exception("Failed to prepare top 10 equipment ID statement: " . $conn->error);
            }
            if (!empty($param_types_str)) {
                $stmt_top_10->bind_param($param_types_str, ...$params_arr);
            }
            $stmt_top_10->execute();
            $result_top_10 = $stmt_top_10->get_result();

            $top_equip_ids = [];
            $top_equip_names = [];
            $top_equip_extra_info = [];
            while ($row = $result_top_10->fetch_assoc()) {
                $top_equip_ids[] = (int)$row['equip_id'];
                $equip_label = htmlspecialchars($row['equip_name'] . ' (' . $row['measure'] . ')');
                $top_equip_names[] = $equip_label;
                $top_equip_extra_info[] = [
                    'id' => (int)$row['equip_id'],
                    'name' => htmlspecialchars($row['equip_name']),
                    'measure' => htmlspecialchars($row['measure'])
                ];
            }
            $stmt_top_10->close();

            if (empty($top_equip_ids)) {
                echo json_encode($chart_data);
                exit();
            }

            $chart_data['labels'] = $top_equip_names;
            $chart_data['extra_info'] = $top_equip_extra_info;

            $placeholders = implode(',', array_fill(0, count($top_equip_ids), '?'));
            $sql_grouped_data = "SELECT
                                    er.equip_id,
                                    fd.fa_de_id,
                                    fd.fa_de_name,
                                    COUNT(er.equip_re_id) AS count_requests
                                FROM equipments_requests er
                                JOIN project p ON er.project_id = p.project_id
                                JOIN user u ON p.nontri_id = u.nontri_id
                                JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
                                WHERE er.equip_id IN ({$placeholders})
                                AND er.writed_status != 'ร่างคำร้องขอ' ";

            $grouped_params = $top_equip_ids;
            $grouped_param_types = str_repeat('i', count($top_equip_ids));

            $grouped_conditions = [];
            if (!empty($filtering['where_clauses'])) {
                 $grouped_conditions = array_merge($grouped_conditions, $filtering['where_clauses']);
                 $grouped_params = array_merge($grouped_params, $filtering['param_values']);
                 $grouped_param_types .= $filtering['param_types'];
            }
            if (!empty($fa_de_id_filter_global)) {
                $grouped_conditions[] = "u.fa_de_id = ?";
                $grouped_params[] = (int)$fa_de_id_filter_global;
                $grouped_param_types .= 'i';
            }
            if(!empty($grouped_conditions)) {
                $sql_grouped_data .= " AND " . implode(" AND ", $grouped_conditions);
            }

            $sql_grouped_data .= " GROUP BY er.equip_id, fd.fa_de_id";

            $stmt_grouped_data = $conn->prepare($sql_grouped_data);
            if ($stmt_grouped_data === false) {
                error_log("Failed to prepare grouped data statement for top equipments: " . $conn->error . ". SQL: " . $sql_grouped_data . ". Params: " . implode(',', $grouped_params) . ". Types: " . $grouped_param_types);
                throw new Exception("Failed to prepare grouped data statement: " . $conn->error);
            }

            $stmt_grouped_data->bind_param($grouped_param_types, ...$grouped_params);
            $stmt_grouped_data->execute();
            $result_grouped_data = $stmt_grouped_data->get_result();

            $equipment_data_by_faculty = []; // fa_de_id => [equip_id => count]
            while ($row = $result_grouped_data->fetch_assoc()) {
                $fa_de_id = $row['fa_de_id'];
                $equip_id = $row['equip_id'];
                $count = $row['count_requests'];
                $equipment_data_by_faculty[$fa_de_id][$equip_id] = $count;
            }
            $stmt_grouped_data->close();

            foreach ($all_active_faculties_map as $fa_de_id => $fa_de_name) {
                $dataset_data = [];
                $total_dataset_count = 0;
                foreach ($top_equip_ids as $e_id) {
                    $count_val = $equipment_data_by_faculty[$fa_de_id][$e_id] ?? 0;
                    $dataset_data[] = $count_val;
                    $total_dataset_count += $count_val;
                }
                if ($total_dataset_count > 0) {
                    $chart_data['datasets'][] = [
                        'label' => htmlspecialchars($fa_de_name),
                        'data' => $dataset_data,
                        'backgroundColor' => $faculty_colors[$fa_de_name] ?? $faculty_colors['Default'],
                        'borderWidth' => 1
                    ];
                }
            }
            break;

        case 'drilldown_facility_by_faculty': // This mode is not reachable via UI clicks anymore
            // Fallback for direct URL access, should not occur from UI
            echo json_encode($chart_data);
            exit();

        case 'drilldown_equipment_by_faculty': // This mode is not reachable via UI clicks anymore
            // Fallback for direct URL access, should not occur from UI
            echo json_encode($chart_data);
            exit();

        default:
            echo json_encode(['error' => 'Unknown chart sorting mode.']);
            exit();
    }

    echo json_encode($chart_data);

} catch (Exception $e) {
    error_log("Chart data error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to retrieve chart data: ' . $e->getMessage()]);
} finally {
    if (isset($conn) && $conn instanceof mysqli && !$conn->connect_error) {
        $conn->close();
    }
}