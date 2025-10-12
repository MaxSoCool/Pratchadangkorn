<?php

if (!isset($data)) $data = [];
if (!isset($detail_item)) $detail_item = null;
if (!isset($total_items)) $total_items = 0;
if (!isset($project_facility_requests)) $project_facility_requests = [];
if (!isset($project_equipment_requests)) $project_equipment_requests = [];
if (!isset($errors)) $errors = [];

try {
    // --- รายการข้อมูลต่าง ๆ ---
    // ตัวแปรการกรองที่ส่งมาจาก admin-main-page.php
    $date_filtering = getDateFilteringClauses($main_tab, $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select);

    if ($main_tab == 'projects_admin') {
        if ($mode == 'list') {
            $sort_filter = $_GET['sort_filter'] ?? 'date_desc'; // ตรวจสอบให้แน่ใจว่า $sort_filter มีค่า
            $sorting = getSortingClauses('projects_admin', $sort_filter);

            $base_where = " WHERE p.project_name LIKE ? AND p.writed_status != 'ร่างโครงการ'";
            $full_where_sql = $base_where . ($sorting['where_sql'] ?? '') . ($date_filtering['where_sql'] ?? '');

            $join_user_faculty = " JOIN user u ON p.nontri_id = u.nontri_id";
            if (!empty($fa_de_id_filter_global)) {
                $full_where_sql .= " AND u.fa_de_id = ?";
            }

            // นับจำนวนโครงการทั้งหมด
            $count_sql = "SELECT COUNT(*) FROM project p" . $join_user_faculty . $full_where_sql;
            $stmt_count = $conn->prepare($count_sql);

            $count_params = [$search_param];
            $count_param_types = "s";
            if (!empty($sorting['where_param_value'])) {
                $count_params[] = $sorting['where_param_value'];
                $count_param_types .= $sorting['where_param_type'];
            }
            if (!empty($date_filtering['param_values'])) {
                $count_params = array_merge($count_params, $date_filtering['param_values']);
            }
            if (!empty($date_filtering['param_types'])) {
                $count_param_types .= $date_filtering['param_types'];
            }
            if (!empty($fa_de_id_filter_global)) {
                $count_params[] = (int)$fa_de_id_filter_global;
                $count_param_types .= 'i';
            }

            $stmt_count->bind_param($count_param_types, ...$count_params);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            // QUERY ข้อมูลของโครงการ
            $sql_data = "SELECT
                            p.project_id, p.project_name, p.start_date, p.end_date, p.project_des, p.files, p.attendee, p.phone_num, p.advisor_name, p.writed_status, p.created_date,
                            at.activity_type_name, CONCAT(u.user_name, ' ', u.user_sur) AS user_name
                        FROM project p
                        JOIN user u ON p.nontri_id = u.nontri_id
                        JOIN activity_type at ON p.activity_type_id = at.activity_type_id"
                        . $full_where_sql
                        . ($sorting['order_by_sql'] ?? '') . " LIMIT ? OFFSET ?";

            $stmt_data = $conn->prepare($sql_data);

            $data_params = $count_params;
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
            } else {
                // คำร้องขอใช้สถานที่ภายในโครงการ
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

                // คำร้องขอใช้อุปกรณ์ภายในโครงการ
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
            }
        }
    } elseif ($main_tab == 'buildings_admin') {
        if ($mode == 'list') {
            $sort_filter = $_GET['sort_filter'] ?? 'date_desc';
            $sorting = getSortingClauses('buildings_admin', $sort_filter);

            $base_where = " WHERE fr.writed_status != 'ร่างคำร้องขอ' AND (p.project_name LIKE ? OR f.facility_name LIKE ?)";
            $full_where_sql = $base_where . ($sorting['where_sql'] ?? '') . ($date_filtering['where_sql'] ?? '');

            $join_user_faculty = " LEFT JOIN user u ON p.nontri_id = u.nontri_id";
            if (!empty($fa_de_id_filter_global)) {
                $full_where_sql .= " AND u.fa_de_id = ?";
            }

            // นับจำนวนคำร้องขอใช้สถานที่ทั้งหมด
            $count_sql = "SELECT COUNT(*) FROM facilities_requests fr JOIN project p ON fr.project_id = p.project_id JOIN facilities f ON fr.facility_id = f.facility_id" . $join_user_faculty . $full_where_sql;
            $stmt_count = $conn->prepare($count_sql);
            if ($stmt_count === false) {
                error_log("Error preparing count SQL for buildings_admin (list): " . $conn->error);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งนับจำนวนข้อมูลสถานที่: " . $conn->error;
                $total_items = 0;
            } else {
                $count_params = [$search_param, $search_param];
                $count_param_types = "ss";
                if (!empty($sorting['where_param_value'])) {
                    $count_params[] = $sorting['where_param_value'];
                    $count_param_types .= $sorting['where_param_type'];
                }
                if (!empty($date_filtering['param_values'])) {
                    $count_params = array_merge($count_params, $date_filtering['param_values']);
                }
                if (!empty($date_filtering['param_types'])) {
                    $count_param_types .= $date_filtering['param_types'];
                }
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

            // QUERY ข้อมูลของคำร้องขอสถานที่
            $sql_data = "SELECT
                            fr.facility_re_id, fr.request_date, fr.writed_status, fr.start_date, fr.end_date, fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time, fr.approve,
                            f.facility_name, p.project_name, CONCAT(u.user_name, ' ', u.user_sur) AS user_name
                        FROM facilities_requests fr
                        JOIN facilities f ON fr.facility_id = f.facility_id
                        JOIN project p ON fr.project_id = p.project_id"
                        . $join_user_faculty
                        . $full_where_sql
                        . ($sorting['order_by_sql'] ?? '') . " LIMIT ? OFFSET ?";

            $stmt_data = $conn->prepare($sql_data);
            if ($stmt_data === false) {
                error_log("Error preparing data SQL for buildings_admin (list): " . $conn->error);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลสถานที่: " . $conn->error;
                $data = [];
            } else {
                $data_params = $count_params;
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
                            JOIN buildings b ON f.building_id = b.building_id
                            JOIN project p ON fr.project_id = p.project_id
                            JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                            JOIN user u ON p.nontri_id = u.nontri_id
                            JOIN faculties_department fd ON u.fa_de_id = fd.fa_de_id
                            LEFT JOIN staff s ON fr.staff_id = s.staff_id
                            WHERE fr.facility_re_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
            if ($stmt_detail === false) {
                error_log("Error preparing detail SQL for buildings_admin: " . $conn->error);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงรายละเอียดสถานที่: " . $conn->error;
            } else {
                $stmt_detail->bind_param("i", $facility_re_id);
                $stmt_detail->execute();
                $detail_item = $stmt_detail->get_result()->fetch_assoc();
                $stmt_detail->close();

                if (!$detail_item) {
                    $errors[] = "ไม่พบคำร้องขอสถานที่ที่คุณร้องขอ.";
                }
            }
        }
    } elseif ($main_tab == 'equipments_admin') {
        if ($mode == 'list') {
            $sort_filter = $_GET['sort_filter'] ?? 'date_desc';
            $sorting = getSortingClauses('equipments_admin', $sort_filter);

            $base_where = " WHERE er.writed_status != 'ร่างคำร้องขอ' AND (p.project_name LIKE ? OR e.equip_name LIKE ?)";
            $full_where_sql = $base_where . ($sorting['where_sql'] ?? '') . ($date_filtering['where_sql'] ?? '');

            $join_user_faculty = " LEFT JOIN user u ON p.nontri_id = u.nontri_id";
            if (!empty($fa_de_id_filter_global)) {
                $full_where_sql .= " AND u.fa_de_id = ?";
            }

            // นับจำนวนคำร้องขอใช้อุปกรณ์ทั้งหมด
            $count_sql = "SELECT COUNT(*) FROM equipments_requests er JOIN project p ON er.project_id = p.project_id JOIN equipments e ON er.equip_id = e.equip_id" . $join_user_faculty . $full_where_sql;
            $stmt_count = $conn->prepare($count_sql);
            if ($stmt_count === false) {
                error_log("Error preparing count SQL for equipments_admin (list): " . $conn->error);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งนับจำนวนข้อมูลอุปกรณ์: " . $conn->error;
                $total_items = 0;
            } else {
                $count_params = [$search_param, $search_param];
                $count_param_types = "ss";
                if (!empty($sorting['where_param_value'])) {
                    $count_params[] = $sorting['where_param_value'];
                    $count_param_types .= $sorting['where_param_type'];
                }
                if (!empty($date_filtering['param_values'])) {
                    $count_params = array_merge($count_params, $date_filtering['param_values']);
                }
                if (!empty($date_filtering['param_types'])) {
                    $count_param_types .= $date_filtering['param_types'];
                }
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

            // QUERY ข้อมูลของคำร้องขอใช้อุปกรณ์
            $sql_data = "SELECT
                            er.equip_re_id, er.request_date, er.writed_status, er.start_date, er.end_date, er.quantity, er.transport, er.approve,
                            e.equip_name, e.measure, f.facility_name, p.project_name, CONCAT(u.user_name, ' ', u.user_sur) AS user_name
                        FROM equipments_requests er
                        JOIN equipments e ON er.equip_id = e.equip_id
                        JOIN project p ON er.project_id = p.project_id
                        LEFT JOIN facilities f ON er.facility_id = f.facility_id"
                        . $join_user_faculty
                        . $full_where_sql
                        . ($sorting['order_by_sql'] ?? '') . " LIMIT ? OFFSET ?";

            $stmt_data = $conn->prepare($sql_data);
            if ($stmt_data === false) {
                error_log("Error preparing data SQL for equipments_admin (list): " . $conn->error);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงข้อมูลอุปกรณ์: " . $conn->error;
                $data = [];
            } else {
                $data_params = $count_params;
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
                            JOIN user u ON p.nontri_id = u.nontri_id
                            LEFT JOIN facilities f ON er.facility_id = f.facility_id
                            LEFT JOIN staff s ON er.staff_id = s.staff_id
                            WHERE er.equip_re_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
            if ($stmt_detail === false) {
                error_log("Error preparing detail SQL for equipments_admin: " . $conn->error);
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่งดึงรายละเอียดอุปกรณ์: " . $conn->error;
            } else {
                $stmt_detail->bind_param("i", $equip_re_id);
                $stmt_detail->execute();
                $detail_item = $stmt_detail->get_result()->fetch_assoc();
                $stmt_detail->close();

                if (!$detail_item) {
                    $errors[] = "ไม่พบคำร้องขออุปกรณ์ที่คุณร้องขอ.";
                }
            }
        }
    }

} catch (Exception $e) {
    error_log("Admin Data List Fetching Error: " . $e->getMessage());
    $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

// Error เมื่อรายละเอียดคำร้องขอใช้เกิด error
if (($main_tab == 'buildings_admin' || $main_tab == 'equipments_admin') && $mode == 'detail' && !isset($detail_item)) {
    $errors[] = "ไม่สามารถโหลดรายละเอียดคำร้องขอได้ โปรดลองอีกครั้ง.";
    $detail_item = []; 
}
?>