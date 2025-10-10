<?php

// ต้องมี database connection, helpers และ sorting ก่อน
if (!isset($conn)) {
    include dirname(__DIR__) . '/database/database.php';
}
if (!function_exists('getSortingClauses')) { // ตรวจสอบว่าฟังก์ชันถูกโหลดแล้วหรือไม่
    include 'sorting.php';
}
if (!function_exists('formatThaiDate')) {
    include 'helper.php';
}

$data = [];
$detail_item = null;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search_query . '%';
$sort_filter = $_GET['sort_filter'] ?? 'date_desc';

$items_per_page = 4;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
$total_items = 0;
$total_pages = 1;

if ($main_tab == 'user_requests') {
    try {
        if ($mode == 'projects_list') {
            $sorting = getSortingClauses('projects_list', $sort_filter);
            $base_where = " WHERE p.nontri_id = ? AND p.project_name LIKE ?";

            $sql_count = "SELECT COUNT(*) FROM project p" . $base_where . $sorting['where_sql'];
            $stmt_count = $conn->prepare($sql_count);

            $count_params = [$nontri_id, $search_param];
            $count_param_types = "ss";
            if ($sorting['where_param_value'] !== null) {
                $count_params[] = $sorting['where_param_value'];
                $count_param_types .= $sorting['where_param_type'];
            }
            $stmt_count->bind_param($count_param_types, ...$count_params);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            $total_pages = ($items_per_page > 0) ? ceil($total_items / $items_per_page) : 1;

            $sql_data = "SELECT
                            p.project_id, p.project_name, p.start_date, p.end_date, p.project_des, p.files, p.attendee, p.phone_num, p.advisor_name, p.writed_status, p.created_date,
                            at.activity_type_name AS activity_type_name
                         FROM project p
                         LEFT JOIN user u ON p.nontri_id = u.nontri_id
                         LEFT JOIN activity_type at ON p.activity_type_id = at.activity_type_id"
                         . $base_where . $sorting['where_sql']
                         . $sorting['order_by_sql'] . " LIMIT ? OFFSET ?";

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
        } elseif ($mode == 'projects_detail' && isset($_GET['project_id'])) {
            $project_id = (int)$_GET['project_id'];

            $sql_detail = "SELECT
                                p.project_id, p.project_name, p.start_date, p.end_date, p.project_des, p.files, p.attendee, p.phone_num, p.advisor_name, p.writed_status, p.created_date,
                                at.activity_type_name AS activity_type_name, p.nontri_id
                           FROM project p
                           LEFT JOIN user u ON p.nontri_id = u.nontri_id
                           LEFT JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                           WHERE p.project_id = ? AND p.nontri_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
            if ($stmt_detail) {
                $stmt_detail->bind_param("is", $project_id, $nontri_id);
                $stmt_detail->execute();
                $detail_item = $stmt_detail->get_result()->fetch_assoc();
                if ($detail_item) {
                    $detail_item['start_date_compare'] = strtotime($detail_item['start_date']);
                }
                $stmt_detail->close();

                if (!$detail_item) {
                    $errors[] = "ไม่พบโครงการที่คุณร้องขอ หรือคุณไม่มีสิทธิ์เข้าถึงโครงการนี้.";
                    $mode = 'projects_list';
                } else {
                    $project_facility_requests = [];
                    $sql_fr = "SELECT fr.facility_re_id, fr.request_date, fr.writed_status, fr.start_date, fr.end_date, fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time, fr.prepare_start_time, fr.prepare_end_time, f.facility_name, fr.approve
                               FROM facilities_requests fr
                               JOIN facilities f ON fr.facility_id = f.facility_id
                               WHERE fr.project_id = ?
                               ORDER BY fr.request_date DESC";
                    $stmt_fr = $conn->prepare($sql_fr);
                    if ($stmt_fr) {
                        $stmt_fr->bind_param("i", $project_id);
                        $stmt_fr->execute();
                        $result_fr = $stmt_fr->get_result();
                        while ($row_fr = $result_fr->fetch_assoc()) {
                            $project_facility_requests[] = $row_fr;
                        }
                        $stmt_fr->close();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการดึงคำร้องขอสถานที่สำหรับโครงการนี้: " . $conn->error;
                    }

                    $project_equipment_requests = [];
                    $sql_er = "SELECT er.equip_re_id, er.request_date, er.writed_status, er.start_date, er.end_date, er.quantity, e.equip_name, e.measure, er.approve
                               FROM equipments_requests er
                               JOIN equipments e ON er.equip_id = e.equip_id
                               WHERE er.project_id = ?
                               ORDER BY er.request_date DESC";
                    $stmt_er = $conn->prepare($sql_er);
                    if ($stmt_er) {
                        $stmt_er->bind_param("i", $project_id);
                        $stmt_er->execute();
                        $result_er = $stmt_er->get_result();
                        while ($row_er = $result_er->fetch_assoc()) {
                            $project_equipment_requests[] = $row_er;
                        }
                        $stmt_er->close();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการดึงคำร้องขออุปกรณ์สำหรับโครงการนี้: " . $conn->error;
                    }
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการดึงรายละเอียดโครงการ: " . $conn->error;
                $mode = 'projects_list';
            }
        } elseif ($mode == 'projects_edit' && isset($_GET['project_id'])) {
            $project_id = (int)$_GET['project_id'];
            $sql_detail = "SELECT
                                p.project_id, p.project_name, p.start_date, p.end_date, p.project_des, p.files, p.attendee, p.phone_num, p.advisor_name, p.writed_status, p.created_date,
                                at.activity_type_id, at.activity_type_name AS activity_type_name, p.nontri_id
                           FROM project p
                           LEFT JOIN user u ON p.nontri_id = u.nontri_id
                           LEFT JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                           WHERE p.project_id = ? AND p.nontri_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
            if ($stmt_detail) {
                $stmt_detail->bind_param("is", $project_id, $nontri_id);
                $stmt_detail->execute();
                $detail_item = $stmt_detail->get_result()->fetch_assoc();
                $stmt_detail->close();
                if (!$detail_item) {
                    $errors[] = "ไม่พบโครงการที่คุณต้องการแก้ไข หรือคุณไม่มีสิทธิ์เข้าถึงโครงการนี้.";
                    $mode = 'projects_list';
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูลโครงการ: " . $conn->error;
                $mode = 'projects_list';
            }

        } elseif ($mode == 'buildings_list') {
            $sorting = getSortingClauses('buildings_list', $sort_filter);

            $subquery_filter = '';
            if ($sorting['where_param_value'] !== null) {
                $condition = preg_replace('/^ AND /', '', $sorting['where_sql']);
                $subquery_filter = " AND EXISTS (SELECT 1 FROM facilities_requests fr WHERE fr.project_id = p.project_id AND {$condition})";
            }

            $base_where = " WHERE p.nontri_id = ? AND p.project_name LIKE ?";

            $sql_count_projects = "SELECT COUNT(*) FROM project p" . $base_where . $subquery_filter;
            $stmt_count_projects = $conn->prepare($sql_count_projects);

            $count_params = [$nontri_id, $search_param];
            $count_param_types = "ss";
            if ($sorting['where_param_value'] !== null) {
                 $count_params[] = $sorting['where_param_value'];
                 $count_param_types .= $sorting['where_param_type'];
            }
            $stmt_count_projects->bind_param($count_param_types, ...$count_params);
            $stmt_count_projects->execute();
            $stmt_count_projects->bind_result($total_items);
            $stmt_count_projects->fetch();
            $stmt_count_projects->close();

            $total_pages = ($items_per_page > 0) ? ceil($total_items / $items_per_page) : 1;

            $sql_paginated_projects = "SELECT project_id, project_name, writed_status, created_date FROM project p"
                                    . $base_where . $subquery_filter
                                    . " ORDER BY p.created_date DESC LIMIT ? OFFSET ?";

            $stmt_paginated_projects = $conn->prepare($sql_paginated_projects);

            $data_params = $count_params;
            $data_param_types = $count_param_types;
            $data_params[] = $items_per_page;
            $data_params[] = $offset;
            $data_param_types .= "ii";

            $stmt_paginated_projects->bind_param($data_param_types, ...$data_params);
            $stmt_paginated_projects->execute();
            $result_paginated_projects = $stmt_paginated_projects->get_result();

            $data = [];
            while ($project_row = $result_paginated_projects->fetch_assoc()) {
                $project_id = $project_row['project_id'];
                $project_row['requests'] = [];

                $sql_requests = "SELECT fr.facility_re_id, fr.request_date, fr.writed_status, fr.start_date, fr.end_date, fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time, fr.prepare_start_time, fr.prepare_end_time, f.facility_name, fr.approve
                                 FROM facilities_requests fr
                                 JOIN facilities f ON fr.facility_id = f.facility_id
                                 WHERE fr.project_id = ?" . $sorting['where_sql'] . $sorting['order_by_sql'];
                $stmt_requests = $conn->prepare($sql_requests);

                $req_params = [$project_id];
                $req_param_types = "i";
                if ($sorting['where_param_value'] !== null) {
                    $req_params[] = $sorting['where_param_value'];
                    $req_param_types .= $sorting['where_param_type'];
                }

                $stmt_requests->bind_param($req_param_types, ...$req_params);
                $stmt_requests->execute();
                $result_requests = $stmt_requests->get_result();
                while ($req_row = $result_requests->fetch_assoc()) {
                    $project_row['requests'][] = $req_row;
                }
                $stmt_requests->close();

                if (!empty($project_row['requests']) || empty($sorting['where_sql'])) {
                    $data[] = $project_row;
                }
            }
            $stmt_paginated_projects->close();

        } elseif ($mode == 'buildings_detail' && isset($_GET['facility_re_id'])) {
            $facility_re_id = (int)$_GET['facility_re_id'];


            $sql_detail = "SELECT
                                fr.facility_re_id, fr.project_id, fr.prepare_start_time, fr.prepare_end_time,
                                fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time,
                                fr.start_date , fr.end_date , fr.agree,
                                fr.writed_status, fr.request_date, p.nontri_id, CONCAT(u.user_name,' ', u.user_sur) AS user_name,
                                p.project_name, f.facility_name, fr.approve, fr.approve_date, fr.approve_detail, CONCAT(s.staff_name, ' ', s.staff_sur) AS staff_name
                            FROM facilities_requests fr
                            JOIN project p ON fr.project_id = p.project_id
                            JOIN user u ON p.nontri_id = u.nontri_id
                            JOIN facilities f ON fr.facility_id = f.facility_id
                            LEFT JOIN staff s ON fr.staff_id = s.staff_id
                            WHERE fr.facility_re_id = ? AND p.nontri_id = ?";

            $stmt_detail = $conn->prepare($sql_detail);
            if ($stmt_detail) {
                $stmt_detail->bind_param("is", $facility_re_id, $user_id);
                $stmt_detail->execute();
                $detail_item = $stmt_detail->get_result()->fetch_assoc();
                if ($detail_item) {
                    $detail_item['start_date_compare'] = strtotime($detail_item['start_date']);
                }
                $stmt_detail->close();

                if (!$detail_item) {
                    $errors[] = "ไม่พบคำร้องขอสถานที่ที่คุณร้องขอ หรือคุณไม่มีสิทธิ์เข้าถึงคำร้องนี้.";
                    $mode = 'buildings_list';
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการดึงรายละเอียดคำร้อง: " . $conn->error;
                $mode = 'buildings_list';
            }

        } elseif ($mode == 'buildings_edit' && isset($_GET['facility_re_id'])) {
            $facility_re_id = (int)$_GET['facility_re_id'];
            $sql_detail = "SELECT
                                fr.facility_re_id, fr.project_id, fr.prepare_start_time, fr.prepare_end_time,
                                fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time,
                                fr.start_date , fr.end_date , fr.agree,
                                fr.writed_status, fr.request_date, p.nontri_id, CONCAT(u.user_name,' ', u.user_sur) AS user_name,
                                p.project_name, f.facility_id, f.facility_name, f.building_id, p.start_date AS project_start_date, p.end_date AS project_end_date
                            FROM facilities_requests fr
                            JOIN project p ON fr.project_id = p.project_id
                            JOIN user u ON p.nontri_id = u.nontri_id
                            JOIN facilities f ON fr.facility_id = f.facility_id
                            WHERE fr.facility_re_id = ? AND p.nontri_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
            if ($stmt_detail) {
                $stmt_detail->bind_param("is", $facility_re_id, $nontri_id);
                $stmt_detail->execute();
                $detail_item = $stmt_detail->get_result()->fetch_assoc();
                $stmt_detail->close();
                if (!$detail_item) {
                    $errors[] = "ไม่พบคำร้องขอสถานที่ที่คุณต้องการแก้ไข หรือคุณไม่มีสิทธิ์เข้าถึงคำร้องนี้.";
                    $mode = 'buildings_list';
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูลคำร้องขอสถานที่: " . $conn->error;
                $mode = 'buildings_list';
            }

        } elseif ($mode == 'equipments_list') {
            $sorting = getSortingClauses('equipments_list', $sort_filter);

            $subquery_filter = '';
            if ($sorting['where_param_value'] !== null) {
                $condition = preg_replace('/^ AND /', '', $sorting['where_sql']);
                $subquery_filter = " AND EXISTS (SELECT 1 FROM equipments_requests er WHERE er.project_id = p.project_id AND {$condition})";
            }

            $base_where = " WHERE p.nontri_id = ? AND p.project_name LIKE ?";

            $sql_count_projects = "SELECT COUNT(*) FROM project p" . $base_where . $subquery_filter;
            $stmt_count_projects = $conn->prepare($sql_count_projects);

            $count_params = [$nontri_id, $search_param];
            $count_param_types = "ss";
            if ($sorting['where_param_value'] !== null) {
                 $count_params[] = $sorting['where_param_value'];
                 $count_param_types .= $sorting['where_param_type'];
            }
            $stmt_count_projects->bind_param($count_param_types, ...$count_params);
            $stmt_count_projects->execute();
            $stmt_count_projects->bind_result($total_items);
            $stmt_count_projects->fetch();
            $stmt_count_projects->close();

            $total_pages = ($items_per_page > 0) ? ceil($total_items / $items_per_page) : 1;

            $sql_paginated_projects = "SELECT project_id, project_name, writed_status, created_date FROM project p"
                                    . $base_where . $subquery_filter
                                    . " ORDER BY p.created_date DESC LIMIT ? OFFSET ?";
            $stmt_paginated_projects = $conn->prepare($sql_paginated_projects);

            $data_params = $count_params;
            $data_param_types = $count_param_types;
            $data_params[] = $items_per_page;
            $data_params[] = $offset;
            $data_param_types .= "ii";

            $stmt_paginated_projects->bind_param($data_param_types, ...$data_params);
            $stmt_paginated_projects->execute();
            $result_paginated_projects = $stmt_paginated_projects->get_result();

            $data = [];
            while ($project_row = $result_paginated_projects->fetch_assoc()) {
                $project_id = $project_row['project_id'];
                $project_row['requests'] = [];

                $sql_requests = "SELECT er.equip_re_id, er.request_date, er.writed_status, er.start_date, er.end_date, er.quantity, er.transport, e.equip_name, e.measure, f.facility_name, er.approve
                                 FROM equipments_requests er
                                 JOIN equipments e ON er.equip_id = e.equip_id
                                 LEFT JOIN facilities f ON er.facility_id = f.facility_id
                                 WHERE er.project_id = ?" . $sorting['where_sql'] . $sorting['order_by_sql'];

                $stmt_requests = $conn->prepare($sql_requests);

                $req_params = [$project_id];
                $req_param_types = "i";
                if ($sorting['where_param_value'] !== null) {
                    $req_params[] = $sorting['where_param_value'];
                    $req_param_types .= $sorting['where_param_type'];
                }

                $stmt_requests->bind_param($req_param_types, ...$req_params);
                $stmt_requests->execute();
                $result_requests = $stmt_requests->get_result();
                while ($req_row = $result_requests->fetch_assoc()) {
                    $project_row['requests'][] = $req_row;
                }
                $stmt_requests->close();

                if (!empty($project_row['requests']) || empty($sorting['where_sql'])) {
                    $data[] = $project_row;
                }
            }
            $stmt_paginated_projects->close();

        } elseif ($mode == 'equipments_detail' && isset($_GET['equip_re_id'])) {
            $equip_re_id = (int)$_GET['equip_re_id'];

            $sql_detail = "SELECT
                                er.equip_re_id, er.project_id, er.start_date, er.end_date, er.quantity, er.transport,
                                er.writed_status, er.request_date, p.nontri_id, CONCAT(u.user_name,' ', u.user_sur) AS user_name,
                                p.project_name, e.equip_name, f.facility_name, e.measure, er.agree, er.approve, er.approve_date, er.approve_detail
                            FROM equipments_requests er
                            JOIN project p ON er.project_id = p.project_id
                            JOIN user u ON p.nontri_id = u.nontri_id
                            JOIN equipments e ON er.equip_id = e.equip_id
                            LEFT JOIN facilities f ON er.facility_id = f.facility_id
                            WHERE er.equip_re_id = ? AND p.nontri_id = ?";

                        $stmt_detail = $conn->prepare($sql_detail);
                        if ($stmt_detail) {
                            $stmt_detail->bind_param("is", $equip_re_id, $user_id);
                            $stmt_detail->execute();
                            $detail_item = $stmt_detail->get_result()->fetch_assoc();
                            if ($detail_item) {
                                $detail_item['start_date_compare'] = strtotime($detail_item['start_date']);
                            }
                            $stmt_detail->close();

                            if (!$detail_item) {
                                $errors[] = "ไม่พบคำร้องขออุปกรณ์ที่คุณร้องขอ หรือคุณไม่มีสิทธิ์เข้าถึงคำร้องนี้.";
                                $mode = 'equipments_list';
                            }
                        } else {
                            $errors[] = "เกิดข้อผิดพลาดในการดึงรายละเอียดคำร้อง: " . $conn->error;
                            $mode = 'equipments_list';
                        }

        } elseif ($mode == 'equipments_edit' && isset($_GET['equip_re_id'])) {
            $equip_re_id = (int)$_GET['equip_re_id'];
            $sql_detail = "SELECT
                                er.equip_re_id, er.project_id, er.start_date, er.end_date, er.quantity, er.transport,
                                er.writed_status, er.request_date, p.nontri_id, CONCAT(u.user_name,' ', u.user_sur) AS user_name,
                                p.project_name, e.equip_id, e.equip_name, f.facility_id, f.facility_name, e.measure, er.agree, er.approve, er.approve_date, p.start_date AS project_start_date, p.end_date AS project_end_date
                            FROM equipments_requests er
                            JOIN project p ON er.project_id = p.project_id
                            JOIN user u ON p.nontri_id = u.nontri_id
                            JOIN equipments e ON er.equip_id = e.equip_id
                            LEFT JOIN facilities f ON er.facility_id = f.facility_id
                            WHERE er.equip_re_id = ? AND p.nontri_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
            if ($stmt_detail) {
                $stmt_detail->bind_param("is", $equip_re_id, $nontri_id);
                $stmt_detail->execute();
                $detail_item = $stmt_detail->get_result()->fetch_assoc();
                $stmt_detail->close();
                if (!$detail_item) {
                    $errors[] = "ไม่พบคำร้องขออุปกรณ์ที่คุณต้องการแก้ไข หรือคุณไม่มีสิทธิ์เข้าถึงคำร้องนี้.";
                    $mode = 'equipments_list';
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูลคำร้องขออุปกรณ์: " . $conn->error;
                $mode = 'equipments_list';
            }
        }

    } catch (Exception $e) {
        error_log("Database Error (user_requests tab): " . $e->getMessage());
        $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    }
}

?>