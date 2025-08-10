<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include 'database/database.php';

$user_THname = htmlspecialchars($_SESSION['user_THname'] ?? 'N/A');
$user_THsur = htmlspecialchars($_SESSION['user_THsur'] ?? 'N/A');
$user_ENname = htmlspecialchars($_SESSION['user_ENname'] ?? 'N/A');
$user_ENsur = htmlspecialchars($_SESSION['user_ENsur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$fa_de_name = htmlspecialchars($_SESSION['fa_de_name'] ?? 'N/A');
$nontri_id = htmlspecialchars($_SESSION['nontri_id'] ?? 'N/A');
$user_id = $_SESSION['nontri_id'] ?? '';

$items_per_page = 9;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search_query . '%';

$main_tab = 'user_requests';

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'projects_list';

$data = []; // This will now hold projects for project_list, and projects-with-requests for buildings_list/equipments_list
$detail_item = null;
$total_items = 0;
$errors = [];
$success_message = '';

$project_files_upload_dir = 'uploads/files/';
if (!is_dir($project_files_upload_dir)) {
    mkdir($project_files_upload_dir, 0755, true);
}

function uploadFile($file_input_name, $target_dir, &$errors, $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx']) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
        $file_name = basename($_FILES[$file_input_name]['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = "ประเภทไฟล์สำหรับ " . $file_input_name . " ประเภทไฟล์ที่คุณอัพโหลดไม่ถูกต้อง (ประเภทไฟล์ที่อนุญาต: " . implode(', ', $allowed_ext) . ")";
            return false;
        }

        if ($_FILES[$file_input_name]['size'] > 10 * 1024 * 1024) { // 10 MB
            $errors[] = "ขนาดไฟล์สำหรับ " . $file_input_name . " ต้องมีขนาดไม่เกิน 10 MB เท่านั้น!.";
            return false;
        }

        $new_file_name = uniqid() . '.' . $file_ext;
        $upload_path = $target_dir . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $upload_path)) {
            return $upload_path;
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์ " . $file_input_name . ": Error Code " . $_FILES[$file_input_name]['error'];
            return false;
        }
    } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE) {
        $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์ " . $file_input_name . ": Error Code " . $_FILES[$file_input_name]['error'];
        return false;
    }
    return null;
}

$activity_types = [];
$result_activity = $conn->query("SELECT activity_type_id, activity_type_name FROM activity_type ORDER BY activity_type_name");
if ($result_activity) {
    while ($row = $result_activity->fetch_assoc()) {
        $activity_types[] = $row;
    }
}

$user_projects = []; // Used for dropdowns in create forms
$sql_user_projects = "SELECT project_id, project_name, writed_status FROM project WHERE nontri_id = ? AND writed_status != 'ร่างโครงการ'  ORDER BY project_name ASC";
$stmt_user_projects = $conn->prepare($sql_user_projects);
if ($stmt_user_projects) {
    $stmt_user_projects->bind_param("s", $user_id);
    $stmt_user_projects->execute();
    $result_user_projects = $stmt_user_projects->get_result();
    while ($row = $result_user_projects->fetch_assoc()) {
        $user_projects[] = $row;
    }
    $stmt_user_projects->close();
} else {
    $errors[] = "ไม่สามารถดึงข้อมูลโครงการของผู้ใช้ได้: " . $conn->error;
}

$facilities = [];
$result_facilities = $conn->query("SELECT facility_id, facility_name FROM facilities ORDER BY facility_name ASC");
if ($result_facilities) {
    while ($row = $result_facilities->fetch_assoc()) {
        $facilities[] = $row;
    }
}

$equipments = [];
$result_equipments = $conn->query("SELECT equip_id, equip_name, measure FROM equipments ORDER BY equip_name ASC");
if ($result_equipments) {
    while ($row = $result_equipments->fetch_assoc()) {
        $equipments[] = $row;
    }
}

$previous = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($previous, 'mode=equipments_create') !== false) {
    $previous = '?main_tab=user_requests&mode=equipments_list';
} elseif (strpos($previous, 'mode=buildings_create') !== false) {
    $previous = '?main_tab=user_requests&mode=buildings_list';
}

try {
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

} catch (Exception $e) {
    error_log("Automatic Status Update Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($mode == 'projects_create') {
        $project_name = trim($_POST['project_name'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $project_des = trim($_POST['project_des'] ?? '');
        $attendee = (int)($_POST['attendee'] ?? 0);
        $phone_num = trim($_POST['phone_num'] ?? '');
        $advisor_name = trim($_POST['advisor_name'] ?? '');
        $activity_type_id = (int)($_POST['activity_type_id'] ?? 0);
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        $writed_status = 'ร่างโครงการ';
        if (isset($_POST['action']) && $_POST['action'] == 'submit_project') {
            $writed_status = 'ส่งโครงการ';
        }

        if (empty($project_name)) $errors[] = "กรุณากรอกชื่อโครงการ";
        if (empty($start_date)) $errors[] = "กรุณาระบุวันเริ่มต้นโครงการ";
        if (empty($end_date)) $errors[] = "กรุณาระบุวันสิ้นสุดโครงการ";
        if ($attendee <= 0) $errors[] = "กรุณาจำนวนผู้เข้าร่วมให้ถูกต้อง";
        if (empty($phone_num)) $errors[] = "กรุณากรอกหมายเลขโทรศัพท์";
        if (empty($advisor_name)) $errors[] = "กรุณากรอกชื่อที่ปรึกษาโครงการ";
        if ($activity_type_id === 0) $errors[] = "กรุณาเลือกประเภทกิจกรรม";

        if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
            $errors[] = "วันสุดท้ายของโครงการห้ามสิ้นสุดก่อนวันเริ่มต้นของโครงการ.";
        }

        $file_path = uploadFile('files', $project_files_upload_dir, $errors);

        if (empty($errors)) {

            $stmt = $conn->prepare("INSERT INTO project (project_name, start_date, end_date, project_des, files, attendee, phone_num, advisor_name, nontri_id, activity_type_id, writed_status, created_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("sssssisssis",
                    $project_name, $start_date, $end_date, $project_des, $file_path,
                    $attendee, $phone_num, $advisor_name, $nontri_id, $activity_type_id, $writed_status
                );
                if ($stmt->execute()) {
                    $new_project_id = $conn->insert_id;
                    $success_message = "สร้างโครงการสำเร็จแล้ว!";

                    header("Location: ?main_tab=user_requests&mode=projects_detail&project_id=" . $new_project_id . "&status=success&message=" . urlencode($success_message));
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกโครงการ: " . $stmt->error;
                    if ($file_path && file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                $stmt->close();
            }
        }
    } elseif ($mode == 'buildings_create') {
        $prepare_start_time = trim($_POST['prepare_start_time'] ?? '');
        $prepare_end_time = trim($_POST['prepare_end_time'] ?? '');
        $prepare_start_date = trim($_POST['prepare_start_date'] ?? '');
        $prepare_end_date = trim($_POST['prepare_end_date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $agree = isset($_POST['agree']) ? 1 : 0;
        $facility_id = (int)($_POST['facility_id']) ?? 0;
        $project_id = (int)($_POST['project_id']) ?? 0; // Get project_id
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        $writed_status = 'ร่างโครงการ';
        if (isset($_POST['action']) && $_POST['action'] == 'submit_building') {
            $writed_status = 'ส่งคำร้องขอ';
        }

        if (empty($prepare_start_time)) $errors[] = "กรุณากรอกเวลาเริ่มต้นของวันเตรียมการ";
        if (empty($prepare_end_time)) $errors[] = "กรุณากรอกเวลาสิ้นสุดของวันเตรียมการ";
        if (empty($prepare_start_date)) $errors[] = "กรุณากรอกวันที่เริ่มต้นของวันเตรียมการ";
        if (empty($prepare_end_date)) $errors[] = "กรุณากรอกวันที่สิ้นสุดเของวันเตรียมการ";
        if (empty($start_time)) $errors[] = "กรุณากรอกเวลาเริ่มต้นของวันใช้การ";
        if (empty($end_time)) $errors[] = "กรุณากรอกเวลาสิ้นสุดของวันใช้การ";
        if (empty($start_date)) $errors[] = "กรุณากรอกวันที่เริ่มต้นใช้การ";
        if (empty($end_date)) $errors[] = "กรุณากรอกวันที่สิ้นสุดใช้การ";
        if (empty($facility_id)) $errors[] = "กรุณาเลือกสถานที่หรืออาคารที่ต้องการขอใช้";
        if (empty($project_id)) $errors[] = "กรุณาเลือกโครงกาของท่านที่ต้องการขอใช้สถานที่";

        $today = date('Y-m-d');
        if (!empty($prepare_start_date) && $prepare_start_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของวันเตรียมการก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($prepare_end_date) && $prepare_end_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของวันเตรียมการก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($start_date) && $start_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($end_date) && $end_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($prepare_start_date) && !empty($prepare_end_date) && $prepare_start_date > $prepare_end_date) {
            $errors[] = "วันสุดท้ายของวันเตรียมการห้ามสิ้นสุดก่อนวันเริ่มต้นของวันเตรียมการ";
        } elseif (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
            $errors[] = "วันสุดท้ายของวันใช้การห้ามสิ้นสุดก่อนวันเริ่มต้นของวันใช้การ";
        } elseif (!empty($prepare_end_date) && !empty($start_time) && $prepare_end_date > $start_date) {
            $errors[] = "วันุสดท้ายของวันเตรียมการห้ามสิ้นสุดก่อนวันเริ่มต้นของวันใช้การ";
        } elseif (!empty($prepare_start_time) && !empty($prepare_end_time) && $prepare_start_time > $prepare_end_time) {
            $errors[] = "เวลาสิ้นสุดของวันเตรียมการห้ามสิ้นสุดก่อนเวลาเริ่มต้นของวันเตรียมการ";
        } elseif (!empty($start_time) && !empty($end_time) && $start_time > $end_time) {
            $errors[] = "เวลาสิ้นสุดของวันใช้การห้ามสิ้นสุดก่อนเวลาเริ่มต้นของวันใช้การ";
        }

        $approval_day = strtotime('+3 days');
        $pre_start_ts = strtotime($prepare_start_date);
        $start_ts    = strtotime($start_date);

        if ($pre_start_ts >= $approval_day && $start_ts >= $approval_day) {

            // เงื่อนไขผ่าน → ดำเนินการ INSERT
            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO facilities_requests 
                    (prepare_start_time, prepare_end_time, prepare_start_date, prepare_end_date, 
                    start_time, end_time, start_date, end_date, agree, facility_id, project_id, writed_status, request_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                if (!$stmt) {
                    $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
                } else {
                    $stmt->bind_param("ssssssssiiis",
                        $prepare_start_time, $prepare_end_time, $prepare_start_date, $prepare_end_date,
                        $start_time, $end_time, $start_date, $end_date,
                        $agree, $facility_id, $project_id, $writed_status
                    );

                    if ($stmt->execute()) {
                        $success_message = "ส่งคำร้องขอสถานที่สำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=buildings_detail&facility_re_id=" 
                            . $project_id . "&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการบันทึกคำร้อง: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }

        } else {
            $errors[] = "ขออภัย คำร้องขอใช้อาคารสถานที่ต้องดำเนินการล่วงหน้าอย่างน้อย 3 วันก่อนวันเริ่มต้นการใช้งาน";
        }

    } elseif ($mode == 'equipments_create') {
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $agree = isset($_POST['agree']) ? 1 : 0;
        $transport = isset($_POST['transport']) ? 1 : 0;
        $quantity = (int)($_POST['quantity'] ?? 0);
        $equip_id = (int)($_POST['equip_id']) ?? 0;
        $facility_id = (int)($_POST['facility_id']) ?? 0;
        $project_id = (int)($_POST['project_id']) ?? 0; // Get project_id
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        $writed_status = 'ร่างโครงการ';
        if (isset($_POST['action']) && $_POST['action'] == 'submit_equipment') {
            $writed_status = 'ส่งคำร้องขอ';
        }
        
        if (empty($start_date)) $errors[] = "กรุณากรอกวันที่เริ่มต้นใช้การ";
        if (empty($end_date)) $errors[] = "กรุณากรอกวันที่สิ้นสุดใช้การ";
        if ($quantity <= 0) $errors[] = "กรุณาระบุจำนวนอุปกรณ์ที่ต้องการขอใช้";
        if (empty($equip_id)) $errors[] = "กรุณาเลือกอุปกรณ์ที่ต้องการขอใช้";
        // facility_id is optional for equipment requests
        // if (empty($facility_id)) $errors[] = "กรุณาเลือกสถานที่หรืออาคารที่อุปกรณ์นำไปใช้งาน";
        if (empty($project_id)) $errors[] = "กรุณาเลือกโครงกาของท่านที่ต้องการขอใช้อุปกรณ์";

        $today = date('Y-m-d');
        if (!empty($start_date) && $start_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($end_date) && $end_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
            $errors[] = "วันสุดท้ายของการขอใช้อุปกรณ์ห้ามสิ้นสุดก่อนวันเริ่มต้นขอใช้อุปกรณ์.";
        }

        $approval_day = strtotime('+3 days');
        $start_ts    = strtotime($start_date);

        if ($start_ts >= $approval_day) {
            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO equipments_requests (start_date, end_date, agree, transport, quantity, equip_id, facility_id, project_id, writed_status, request_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                if (!$stmt) {
                    $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
                } else {
                    $stmt->bind_param("ssiiiiiis",
                    $start_date, $end_date, $agree, $transport, $quantity,
                    $equip_id, $facility_id, $project_id, $writed_status
                    );
                    if ($stmt->execute()) {
                        $success_message = "ส่งคำร้องขอใช้อุปกรณ์สำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=equipments_detail&equip_re_id=" . $project_id . "&status=success&message=" . urlencode($success_message)); // Redirect to project detail
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการบันทึกคำร้อง: " . $stmt->error;
                    }
                    $stmt->close();
                }
            } else {
            $errors[] = "ขออภัย คำร้องขอใช้อุปกรณ์ต้องดำเนินการล่วงหน้าอย่างน้อย 3 วันก่อนวันเริ่มต้นการใช้งาน";
            }
        }
    }
}

try {
    if ($main_tab == 'user_requests') {
        if ($mode == 'projects_list') {

            $sql_count = "SELECT COUNT(*) FROM project WHERE nontri_id = ? AND project_name LIKE ?";
            $stmt_count = $conn->prepare($sql_count);
            $stmt_count->bind_param("ss", $nontri_id, $search_param);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            $sql_data = "SELECT
                            p.project_id, p.project_name, p.start_date, p.end_date, p.project_des, p.files, p.attendee, p.phone_num, p.advisor_name, p.writed_status, p.created_date,
                            at.activity_type_name AS activity_type_name
                         FROM project p
                         LEFT JOIN user u ON p.nontri_id = u.nontri_id
                         LEFT JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                         WHERE p.nontri_id = ? AND p.project_name LIKE ?
                         ORDER BY p.created_date DESC LIMIT ? OFFSET ?";
            $stmt_data = $conn->prepare($sql_data);
            $stmt_data->bind_param("ssii", $nontri_id, $search_param, $items_per_page, $offset);
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
                $stmt_detail->close();

                if (!$detail_item) {
                    $errors[] = "ไม่พบโครงการที่คุณร้องขอ หรือคุณไม่มีสิทธิ์เข้าถึงโครงการนี้.";
                    $mode = 'projects_list';
                } else {
                    // Fetch facilities requests for this project
                    $project_facility_requests = [];
                    $sql_fr = "SELECT fr.facility_re_id, fr.request_date, fr.writed_status, fr.start_date, fr.end_date, fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time, f.facility_name
                               FROM facilities_requests fr
                               JOIN facilities f ON fr.facility_id = f.facility_id
                               WHERE fr.project_id = ?
                               ORDER BY fr.request_date DESC"; // Sorted by latest request date
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

                    // Fetch equipments requests for this project
                    $project_equipment_requests = [];
                    $sql_er = "SELECT er.equip_re_id, er.request_date, er.writed_status, er.start_date, er.end_date, er.quantity, e.equip_name, e.measure
                               FROM equipments_requests er
                               JOIN equipments e ON er.equip_id = e.equip_id
                               WHERE er.project_id = ?
                               ORDER BY er.request_date DESC"; // Sorted by latest request date
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
        } elseif ($mode == 'buildings_list') {
            // New logic: Fetch projects, then their associated facility requests
            $sql_count_projects = "SELECT COUNT(*) FROM project WHERE nontri_id = ? AND project_name LIKE ?";
            $stmt_count_projects = $conn->prepare($sql_count_projects);
            $stmt_count_projects->bind_param("ss", $nontri_id, $search_param);
            $stmt_count_projects->execute();
            $stmt_count_projects->bind_result($total_items);
            $stmt_count_projects->fetch();
            $stmt_count_projects->close();

            $sql_paginated_projects = "SELECT project_id, project_name, writed_status, created_date FROM project WHERE nontri_id = ? AND project_name LIKE ? ORDER BY created_date DESC LIMIT ? OFFSET ?";
            $stmt_paginated_projects = $conn->prepare($sql_paginated_projects);
            $stmt_paginated_projects->bind_param("ssii", $nontri_id, $search_param, $items_per_page, $offset);
            $stmt_paginated_projects->execute();
            $result_paginated_projects = $stmt_paginated_projects->get_result();

            $data = []; // This will hold projects for the current page
            while ($project_row = $result_paginated_projects->fetch_assoc()) {
                $project_id = $project_row['project_id'];
                $project_row['requests'] = []; // Initialize requests array for this project

                // Fetch facilities requests for each project
                $sql_requests = "SELECT fr.facility_re_id, fr.request_date, fr.writed_status, fr.start_date, fr.end_date, fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time, f.facility_name
                                 FROM facilities_requests fr
                                 JOIN facilities f ON fr.facility_id = f.facility_id
                                 WHERE fr.project_id = ?
                                 ORDER BY fr.request_date DESC"; // Sort by latest request
                $stmt_requests = $conn->prepare($sql_requests);
                $stmt_requests->bind_param("i", $project_id);
                $stmt_requests->execute();
                $result_requests = $stmt_requests->get_result();
                while ($req_row = $result_requests->fetch_assoc()) {
                    $project_row['requests'][] = $req_row;
                }
                $stmt_requests->close();

                $data[] = $project_row; // Add project with its requests to the main data array
            }
            $stmt_paginated_projects->close();

        } elseif ($mode == 'buildings_detail' && isset($_GET['facility_re_id'])) {
            $facility_re_id = (int)$_GET['facility_re_id'];

            $sql_detail = "SELECT
                                fr.facility_re_id, fr.project_id, fr.prepare_start_time, fr.prepare_end_time,
                                fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time,
                                fr.start_date , fr.end_date , fr.agree,
                                fr.writed_status, fr.request_date, p.nontri_id, CONCAT(u.user_THname,' ', u.user_THsur) AS user_name,
                                p.project_name, f.facility_name
                            FROM facilities_requests fr
                            JOIN project p ON fr.project_id = p.project_id
                            JOIN user u ON p.nontri_id = u.nontri_id
                            JOIN facilities f ON fr.facility_id = f.facility_id
                            WHERE fr.facility_re_id = ? AND p.nontri_id = ?";

                        $stmt_detail = $conn->prepare($sql_detail);
                        if ($stmt_detail) {
                            $stmt_detail->bind_param("is", $facility_re_id, $user_id);
                            $stmt_detail->execute();
                            $detail_item = $stmt_detail->get_result()->fetch_assoc();
                            $stmt_detail->close();

                            if (!$detail_item) {
                                $errors[] = "ไม่พบคำร้องขอสถานที่ที่คุณร้องขอ หรือคุณไม่มีสิทธิ์เข้าถึงคำร้องนี้.";
                                $mode = 'buildings_list';
                            }
                        } else {
                            $errors[] = "เกิดข้อผิดพลาดในการดึงรายละเอียดคำร้อง: " . $conn->error;
                            $mode = 'buildings_list';
                        }

        } elseif ($mode == 'equipments_list') {
            // New logic: Fetch projects, then their associated equipment requests
            $sql_count_projects = "SELECT COUNT(*) FROM project WHERE nontri_id = ? AND project_name LIKE ?";
            $stmt_count_projects = $conn->prepare($sql_count_projects);
            $stmt_count_projects->bind_param("ss", $nontri_id, $search_param);
            $stmt_count_projects->execute();
            $stmt_count_projects->bind_result($total_items);
            $stmt_count_projects->fetch();
            $stmt_count_projects->close();

            $sql_paginated_projects = "SELECT project_id, project_name, writed_status, created_date FROM project WHERE nontri_id = ? AND project_name LIKE ? ORDER BY created_date DESC LIMIT ? OFFSET ?";
            $stmt_paginated_projects = $conn->prepare($sql_paginated_projects);
            $stmt_paginated_projects->bind_param("ssii", $nontri_id, $search_param, $items_per_page, $offset);
            $stmt_paginated_projects->execute();
            $result_paginated_projects = $stmt_paginated_projects->get_result();

            $data = []; // This will hold projects for the current page
            while ($project_row = $result_paginated_projects->fetch_assoc()) {
                $project_id = $project_row['project_id'];
                $project_row['requests'] = []; // Initialize requests array for this project

                // Fetch equipments requests for each project
                $sql_requests = "SELECT er.equip_re_id, er.request_date, er.writed_status, er.start_date, er.end_date, er.quantity, er.transport, e.equip_name, e.measure, f.facility_name
                                 FROM equipments_requests er
                                 JOIN equipments e ON er.equip_id = e.equip_id
                                 LEFT JOIN facilities f ON er.facility_id = f.facility_id
                                 WHERE er.project_id = ?
                                 ORDER BY er.request_date DESC"; // Sort by latest request
                $stmt_requests = $conn->prepare($sql_requests);
                $stmt_requests->bind_param("i", $project_id);
                $stmt_requests->execute();
                $result_requests = $stmt_requests->get_result();
                while ($req_row = $result_requests->fetch_assoc()) {
                    $project_row['requests'][] = $req_row;
                }
                $stmt_requests->close();

                $data[] = $project_row; // Add project with its requests to the main data array
            }
            $stmt_paginated_projects->close();

        } elseif ($mode == 'equipments_detail' && isset($_GET['equip_re_id'])) {
            $equip_re_id = (int)$_GET['equip_re_id'];

            $sql_detail = "SELECT
                                er.equip_re_id, er.project_id, er.start_date, er.end_date, er.quantity, er.transport,
                                er.writed_status, er.request_date, p.nontri_id, CONCAT(u.user_THname,' ', u.user_THsur) AS user_name,
                                p.project_name, e.equip_name, f.facility_name, e.measure, er.agree
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
                            $stmt_detail->close();

                            if (!$detail_item) {
                                $errors[] = "ไม่พบคำร้องขออุปกรณ์ที่คุณร้องขอ หรือคุณไม่มีสิทธิ์เข้าถึงคำร้องนี้.";
                                $mode = 'equipments_list';
                            }
                        } else {
                            $errors[] = "เกิดข้อผิดพลาดในการดึงรายละเอียดคำร้อง: " . $conn->error;
                            $mode = 'equipments_list';
                        }

        }

    }

    $modal_status = $_GET['status'] ?? '';
    $modal_message = $_GET['message'] ?? '';

} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
    $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}

$conn->close();

$total_pages = ceil($total_items / $items_per_page);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="style.css" rel="stylesheet">
    <title>ข้อมูลคำร้องผู้ใช้ KU FTD</title>

</head>
<body>
    <nav class="navbar navbar-dark navigator">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3">
                <a href="user-main-page.php">
                    <img src="./images/logo.png" class="img-fluid logo" alt="Logo">
                </a>
                <div class="d-flex flex-column">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4">ระบบขอใช้อุปกรณ์และสถานที่</span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5">KU FTD</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mx-auto">
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="user-main-page.php">ตรวจสอบอุปกรณ์และสถานที่</a>
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="user-project-page.php">ข้อมูลคำร้อง</a>
            </div>
            <div class="d-flex align-items-center ms-auto gap-2">
                <div class="d-flex flex-column text-end">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo $user_THname . ' ' . $user_THsur; ?></span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5"><?php echo $user_role; ?></span>
                </div>
                <a href="user-profile-page.php">
                    <img src="./images/user_button.png" class="img-fluid logo" style="width:40px; height:40px; object-fit:cover;" alt="User Button">
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-0">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">เกิดข้อผิดพลาด!</h4>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header <?php echo ($modal_status == 'success') ? 'bg-success' : 'bg-danger'; ?> text-white">
                        <h5 class="modal-title" id="statusModalLabel"><?php echo ($modal_status == 'success') ? 'สำเร็จ!' : 'ข้อผิดพลาด!'; ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo htmlspecialchars($modal_message); ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn <?php echo ($modal_status == 'success') ? 'btn-success' : 'btn-danger'; ?>" data-bs-dismiss="modal">ตกลง</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        if ($mode == 'projects_list' || $mode == 'buildings_list' || $mode == 'equipments_list') :
        ?>
            <h1 class="mb-3 text-center">ข้อมูลคำร้องของผู้ใช้</h1>
            <div class="row mb-3 align-items-center">
                <div class="col-md-6">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($mode == 'projects_list' || $mode == 'projects_create' || $mode == 'projects_detail') ? 'active' : ''; ?>" aria-current="page" href="?main_tab=user_requests&mode=projects_list">
                                <i class="bi bi-folder"></i> โครงการ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($mode == 'buildings_list' || $mode == 'buildings_create' || $mode == 'buildings_detail') ? 'active' : ''; ?>" href="?main_tab=user_requests&mode=buildings_list">
                                <i class="bi bi-building"></i> คำร้องขออาคารและสถานที่ทั้งหมด
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($mode == 'equipments_list' || $mode == 'equipments_create' || $mode == 'equipments_detail') ? 'active' : ''; ?>" href="?main_tab=user_requests&mode=equipments_list">
                                <i class="bi bi-tools"></i> คำร้องขออุปกรณ์ทั้งหมด
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <form class="d-flex" action="" method="GET">
                        <input type="hidden" name="main_tab" value="user_requests">
                        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
                        <input class="form-control me-2" type="search" placeholder="ค้นหา..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-outline-success" type="submit">ค้นหา</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>" class="btn btn-outline-secondary ms-2">ล้าง</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($mode == 'projects_list'): ?>
            <div class="d-flex justify-content-end mb-3">
                <a href="?main_tab=user_requests&mode=projects_create" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> สร้างโครงการใหม่
                </a>
            </div>

            <?php if (empty($data)): ?>
                <div class="alert alert-info text-center mt-4">
                    <?php if (!empty($search_query)): ?>
                        ไม่พบโครงการที่คุณค้นหา "<?php echo htmlspecialchars($search_query); ?>"
                    <?php else: ?>
                        ยังไม่มีโครงการที่คุณสร้างไว้
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 g-3">
                    <?php foreach ($data as $project): ?>
                        <div class="col">
                            <a href="?main_tab=user_requests&mode=projects_detail&project_id=<?php echo $project['project_id']; ?>" class="text-decoration-none text-dark">
                                <div class="card shadow-sm p-3">
                                    <div class="row g-0">
                                        <div class="col-md-9">
                                            <h5 class="card-title mb-1"> ชื่อโครงการ: <?php echo htmlspecialchars($project['project_name']); ?></h5>
                                            <p class="card-text small mb-1">
                                                <strong>สถานะ:</strong> <?php echo htmlspecialchars($project['writed_status']); ?>
                                            </p>
                                            <p class="card-text small mb-1">
                                                <strong>ระยะเวลาโครงการ: </strong>ตั้งแต่วันที่ <?php echo (new DateTime($project['start_date']))->format('d/m/Y'); ?> ถึงวันที่ <?php echo (new DateTime($project['end_date']))->format('d/m/Y'); ?>
                                            <p class="card-text small mb-1">
                                                <strong>ประเภทกิจกรรม:</strong> <?php echo htmlspecialchars($project['activity_type_name'] ?? 'ไม่ระบุ'); ?>
                                            <p class="card-text small mb-1 text-muted">
                                                ยื่นเมื่อ: <?php echo (new DateTime($project['created_date']))->format('d/m/Y H:i'); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <nav aria-label="Page navigation" class="pagination-container mt-3 mb-0">
                    <ul class="pagination pagination-lg">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?main_tab=user_requests&mode=projects_list&page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">ก่อนหน้า</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?main_tab=user_requests&mode=projects_list&page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?main_tab=user_requests&mode=projects_list&page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">ถัดไป</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
        <?php endif; ?>

        <?php elseif ($mode == 'projects_create'): ?>
            <div class="form-section my-4">
                <h2 class="mb-4 text-center">สร้างโครงการใหม่</h2>
                <form action="?main_tab=user_requests&mode=projects_create" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="project_name" class="form-label">ชื่อโครงการ:</label>
                        <input type="text" class="form-control" id="project_name" name="project_name" value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">วันเริ่มต้น:</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">วันสิ้นสุด:</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="project_des" class="form-label">รายละเอียดของโครงการ:</label>
                        <textarea class="form-control" id="project_des" name="project_des" rows="5"><?php echo htmlspecialchars($_POST['project_des'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="files" class="form-label">ไฟล์แนบ (รูปภาพ, PDF, Doc, XLS):</label>
                        <input type="file" class="form-control" id="files" name="files" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="attendee" class="form-label">จำนวนผู้เข้าร่วม:</label>
                            <input type="number" class="form-control" id="attendee" name="attendee" min="1" value="<?php echo htmlspecialchars($_POST['attendee'] ?? '1'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone_num" class="form-label">หมายเลขโทรศัพท์:</label>
                            <input type="text" class="form-control" id="phone_num" name="phone_num" value="<?php echo htmlspecialchars($_POST['phone_num'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="advisor_name" class="form-label">ชื่อที่ปรึกษาโครงการ:</label>
                        <?php if ($user_role === 'อาจารย์และบุคลากร'): ?>
                            <input type="text" class="form-control" id="advisor_name" name="advisor_name" value="-" readonly>
                            <small class="text-muted">*อาจารย์และบุคลากร ไม่ต้องระบุชื่อที่ปรึกษา</small>
                        <?php else: ?>
                            <input type="text" class="form-control" id="advisor_name" name="advisor_name" value="<?php echo htmlspecialchars($_POST['advisor_name'] ?? ''); ?>" required>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="activity_type_id" class="form-label">ประเภทกิจกรรม:</label>
                        <select class="form-select" id="activity_type_id" name="activity_type_id" required>
                            <option value="">-- เลือกประเภทกิจกรรม --</option>
                            <?php foreach ($activity_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type['activity_type_id']); ?>"
                                    <?php echo (isset($_POST['activity_type_id']) && $_POST['activity_type_id'] == $type['activity_type_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['activity_type_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="?main_tab=user_requests&mode=projects_list" class="btn btn-secondary">ย้อนกลับ</a>
                        <div>
                            <button type="submit" name="action" value="save_draft" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                            <button type="submit" name="action" value="submit_project" class="btn btn-success">บันทึกและส่งโครงการ</button>
                        </div>
                    </div>
                </form>
            </div>

        <?php elseif ($mode == 'projects_detail' && $detail_item): ?>
            <div class="project-detail-card my-4">
                <h3 class="mb-4">รายละเอียดโครงการ: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                <div class="row">
                    <div class="col-md-6 pro-details">
                        <p><strong>ชื่อโครงการ:</strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                        <p><strong>สถานะโครงการ:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                        <p><strong>ระยะเวลาโครงการ:</strong> ตั้งแต่วันที่ <?php echo (new DateTime($detail_item['start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($detail_item['end_date']))->format('d/m/Y'); ?></p>
                        <p><strong>จำนวนผู้เข้าร่วม:</strong> <?php echo htmlspecialchars($detail_item['attendee']); ?></p>
                        <p><strong>หมายเลขโทรศัพท์:</strong> <?php echo htmlspecialchars($detail_item['phone_num']); ?></p>
                    </div>
                    <div class="col-md-6 pro-details">
                        <p><strong>ชื่อที่ปรึกษาโครงการ:</strong> <?php echo htmlspecialchars($detail_item['advisor_name']); ?></p>
                        <p><strong>ประเภทกิจกรรม:</strong> <?php echo htmlspecialchars($detail_item['activity_type_name'] ?? 'ไม่ระบุ'); ?></p>
                        <p><strong>ผู้เขียนโครงการ:</strong> <?php echo $user_THname, ' ', $user_THsur ?></p>
                        <p><strong>วันที่สร้างโครงการ:</strong>
                                <?php
                                    if (!empty($detail_item['created_date'])) {

                                        $dt = new DateTime($detail_item['created_date']);
                                        $thai_months = [
                                            "", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
                                            "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
                                        ];
                                        $d = (int)$dt->format('j');
                                        $m = (int)$dt->format('n');
                                        $y = (int)$dt->format('Y') + 543;
                                        $time = $dt->format('H:i');
                                        echo "{$d} {$thai_months[$m]} {$y} {$time}";
                                    } else {
                                        echo "-";
                                    }
                                ?>
                        <p><strong>รายละเอียดโครงการ:</strong><br> <?php echo nl2br(htmlspecialchars($detail_item['project_des'])); ?></p>
                        <?php if ($detail_item['files'] && file_exists($detail_item['files'])): ?>
                            <a href="<?php echo htmlspecialchars($detail_item['files']); ?>" target="_blank" class="detail-file-link">
                                <i class="bi bi-file-earmark-arrow-down"></i> ดูไฟล์แนบ
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <hr class="my-4">

                <h4 class="mb-3">คำร้องขอใช้อาคารสถานที่ทั้งหมดของโครงการนี้</h4>
                <?php if (empty($project_facility_requests)): ?>
                    <div class="alert alert-info text-center mt-3">
                        ยังไม่มีคำร้องขอใช้อาคารสถานที่ใด ๆ ของโครงการนี้.
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 g-3 mt-2">
                        <?php foreach ($project_facility_requests as $request): ?>
                            <div class="col">
                                <a href="?main_tab=user_requests&mode=buildings_detail&facility_re_id=<?php echo $request['facility_re_id']; ?>" class="text-decoration-none text-dark">
                                    <div class="card shadow-sm p-3">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h5 class="card-title mb-1">สถานที่: <?php echo htmlspecialchars($request['facility_name']); ?></h5>
                                            <p class="card-text small mb-1 text-muted">
                                                ยื่นเมื่อ: <?php echo (new DateTime($request['request_date']))->format('d/m/Y H:i'); ?>
                                            </p>
                                        </div>
                                        <p class="card-text small mb-1">
                                            <strong>สถานะ:</strong> <?php echo htmlspecialchars($request['writed_status']); ?>
                                        </p>
                                        <p class="card-text small mb-1">
                                            <strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo (new DateTime($request['start_date']))->format('d/m/Y'); ?> (<?php echo (new DateTime($request['start_time']))->format('H:i'); ?>) ถึง วันที่ <?php echo (new DateTime($request['end_date']))->format('d/m/Y'); ?> (<?php echo (new DateTime($request['end_time']))->format('H:i'); ?>)
                                        </p>
                                        <?php if (!empty($request['prepare_start_date'])): ?>
                                            <p class="card-text small mb-1">
                                                <strong>ช่วงเวลาเตรียมการ:</strong> ตั้งแต่วันที่ <?php echo (new DateTime($request['prepare_start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($request['prepare_end_date']))->format('d/m/Y'); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <hr class="my-4">

                <h4 class="mb-3">คำร้องขอใช้อุปกรณ์ทั้งหมดของโครงการนี้</h4>
                <?php if (empty($project_equipment_requests)): ?>
                    <div class="alert alert-info text-center mt-3">
                        ยังไม่มีคำร้องขอใช้อุปกรณ์ใด ๆ ของโครงการนี้.
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 g-3 mt-2">
                        <?php foreach ($project_equipment_requests as $request): ?>
                            <div class="col">
                                <a href="?main_tab=user_requests&mode=equipments_detail&equip_re_id=<?php echo $request['equip_re_id']; ?>" class="text-decoration-none text-dark">
                                    <div class="card shadow-sm p-3">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h5 class="card-title mb-1">อุปกรณ์: <?php echo htmlspecialchars($request['equip_name']); ?></h5>
                                            <p class="card-text small mb-1 text-muted">
                                                ยื่นเมื่อ: <?php echo (new DateTime($request['request_date']))->format('d/m/Y H:i'); ?>
                                            </p>
                                        </div>
                                        <p class="card-text small mb-1">
                                            <strong>จำนวน:</strong> <?php echo htmlspecialchars($request['quantity']); ?> <?php echo htmlspecialchars($request['measure']); ?>
                                        </p>
                                        <p class="card-text small mb-1">
                                            <strong>สถานะ:</strong> <?php echo htmlspecialchars($request['writed_status']); ?>
                                        </p>
                                        <p class="card-text small mb-1">
                                            <strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo (new DateTime($request['start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($request['end_date']))->format('d/m/Y'); ?>
                                        </p>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-start mt-4">
                    <a href="?main_tab=user_requests&mode=projects_list" class="btn btn-secondary">กลับหน้ารายการโครงการ</a>
                </div>
            </div>

        <?php elseif ($mode == 'buildings_list' || $mode == 'equipments_list'): ?>
            <div class="d-flex justify-content-end mb-3">
                <?php if ($mode == 'buildings_list'): ?>
                    <a href="?main_tab=user_requests&mode=buildings_create" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> สร้างคำร้องขอใช้อาคารสถานที่
                    </a>
                <?php elseif ($mode == 'equipments_list'): ?>
                    <a href="?main_tab=user_requests&mode=equipments_create" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> สร้างคำร้องขอใช้อุปกรณ์
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($data)): // $data now holds projects for the current page ?>
                <div class="alert alert-info text-center mt-4">
                    <?php if (!empty($search_query)): ?>
                        ไม่พบโครงการที่มีคำร้องขอใช้<?php echo ($mode == 'buildings_list' ? 'สถานที่' : 'อุปกรณ์'); ?>ที่คุณค้นหา "<?php echo htmlspecialchars($search_query); ?>"
                    <?php else: ?>
                        ยังไม่มีโครงการของคุณที่มีคำร้องขอใช้<?php echo ($mode == 'buildings_list' ? 'สถานที่' : 'อุปกรณ์'); ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 g-3">
                    <?php foreach ($data as $project): ?>
                        <div class="col">
                            <div class="card shadow-sm p-3 mb-4"> <!-- Project Card -->
                                <h4 class="card-title mb-2">โครงการ: <?php echo htmlspecialchars($project['project_name']); ?>
                                    <small class="text-muted">(สถานะ: <?php echo htmlspecialchars($project['writed_status']); ?>)</small>
                                </h4>
                                <p class="card-text small mb-2 text-muted">
                                    ยื่นเมื่อ: <?php echo (new DateTime($project['created_date']))->format('d/m/Y H:i'); ?>
                                </p>
                                <hr>
                                <h5 class="mb-3">คำร้องขอใช้<?php echo ($mode == 'buildings_list' ? 'อาคารสถานที่' : 'อุปกรณ์'); ?>ทั้งหมดของโครงการนี้:</h5>
                                <?php if (empty($project['requests'])): ?>
                                    <div class="alert alert-warning text-center small py-2">
                                        ยังไม่มีคำร้องขอใช้<?php echo ($mode == 'buildings_list' ? 'อาคารสถานที่' : 'อุปกรณ์'); ?>ใด ๆ ของโครงการนี้
                                    </div>
                                <?php else: ?>
                                    <div class="list-group">
                                        <?php foreach ($project['requests'] as $request): ?>
                                            <a href="?main_tab=user_requests&mode=<?php echo ($mode == 'buildings_list' ? 'buildings_detail' : 'equipments_detail'); ?>&<?php echo ($mode == 'buildings_list' ? 'facility_re_id' : 'equip_re_id'); ?>=<?php echo ($mode == 'buildings_list' ? $request['facility_re_id'] : $request['equip_re_id']); ?>" class="list-group-item list-group-item-action mb-2 rounded-3">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <h6 class="mb-1">
                                                        <?php if ($mode == 'buildings_list'): ?>
                                                            สถานที่: <?php echo htmlspecialchars($request['facility_name']); ?>
                                                        <?php elseif ($mode == 'equipments_list'): ?>
                                                            อุปกรณ์: <?php echo htmlspecialchars($request['equip_name']); ?> จำนวน <?php echo htmlspecialchars($request['quantity']); ?> <?php echo htmlspecialchars($request['measure']); ?>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <small class="text-muted">ยื่นเมื่อ: <?php echo (new DateTime($request['request_date']))->format('d/m/Y H:i'); ?></small>
                                                </div>
                                                <p class="mb-1 small"><strong>สถานะ:</strong> <?php echo htmlspecialchars($request['writed_status']); ?></p>
                                                <?php if ($mode == 'buildings_list'): ?>
                                                    <p class="mb-0 small">
                                                        <strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo (new DateTime($request['start_date']))->format('d/m/Y'); ?> (<?php echo (new DateTime($request['start_time']))->format('H:i'); ?>) ถึง วันที่ <?php echo (new DateTime($request['end_date']))->format('d/m/Y'); ?> (<?php echo (new DateTime($request['end_time']))->format('H:i'); ?>)
                                                    </p>
                                                    <?php if (!empty($request['prepare_start_date'])): ?>
                                                        <p class="mb-0 small">
                                                            <strong>ช่วงเวลาเตรียมการ:</strong> ตั้งแต่วันที่ <?php echo (new DateTime($request['prepare_start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($request['prepare_end_date']))->format('d/m/Y'); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                <?php elseif ($mode == 'equipments_list'): ?>
                                                    <p class="mb-0 small">
                                                        <strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo (new DateTime($request['start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($request['end_date']))->format('d/m/Y'); ?>
                                                    </p>
                                                    <?php if (!empty($request['facility_name'])): ?>
                                                        <p class="mb-0 small">
                                                            <strong>สถานที่ใช้งาน:</strong> <?php echo htmlspecialchars($request['facility_name']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <nav aria-label="Page navigation" class="pagination-container mt-3 mb-0">
                    <ul class="pagination pagination-lg">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">ก่อนหน้า</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">ถัดไป</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php elseif ($mode == 'buildings_create'):?>
            <div class="form-section my-4">
                <h2 class="mb-4 text-center">สร้างคำร้องขอใช้อาคารสถานที่</h2>
                <form action="?main_tab=user_requests&mode=buildings_create" method="POST">
                    <div class="mb-3">
                        <label for="project_id" class="form-label">โครงการ:</label>
                        <select class="form-select" id="project_id" name="project_id" required>
                            <option value="">-- เลือกโครงการ --</option>
                            <?php foreach ($user_projects as $project): ?>
                                <option value="<?php echo htmlspecialchars($project['project_id']); ?>"
                                    <?php echo (isset($_POST['project_id']) && $_POST['project_id'] == $project['project_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['project_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($user_projects)): ?>
                            <p class="text-danger mt-2">คุณยังไม่มีโครงการที่สร้างไว้ กรุณาสร้างโครงการก่อน.</p>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="facility_id" class="form-label">สถานที่/อาคารที่ต้องการขอใช้:</label>
                        <select class="form-select" id="facility_id" name="facility_id" required>
                            <option value="">-- เลือกสถานที่/อาคาร --</option>
                            <?php foreach ($facilities as $facility): ?>
                                <option value="<?php echo htmlspecialchars($facility['facility_id']); ?>"
                                    <?php echo (isset($_POST['facility_id']) && $_POST['facility_id'] == $facility['facility_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($facility['facility_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($facilities)): ?>
                            <p class="text-danger mt-2">ยังไม่มีข้อมูลสถานที่/อาคารในระบบ</p>
                        <?php endif; ?>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="prepare_start_date" class="form-label">วันเริ่มต้นเตรียมการ:</label>
                            <input type="date" class="form-control" id="prepare_start_date" name="prepare_start_date" value="<?php echo htmlspecialchars($_POST['prepare_start_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="prepare_end_date" class="form-label">วันสิ้นสุดเตรียมการ:</label>
                            <input type="date" class="form-control" id="prepare_end_date" name="prepare_end_date" value="<?php echo htmlspecialchars($_POST['prepare_end_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="prepare_start_time" class="form-label">เวลาเริ่มต้นเตรียมการ:</label>
                            <input type="time" class="form-control" id="prepare_start_time" name="prepare_start_time" value="<?php echo htmlspecialchars($_POST['prepare_start_time'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="prepare_end_time" class="form-label">เวลาสิ้นสุดเตรียมการ:</label>
                            <input type="time" class="form-control" id="prepare_end_time" name="prepare_end_time" value="<?php echo htmlspecialchars($_POST['prepare_end_time'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">วันเริ่มต้นใช้การ:</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">วันสิ้นสุดใช้การ:</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_time" class="form-label">เวลาเริ่มต้นใช้การ:</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_time" class="form-label">เวลาสิ้นสุดใช้การ:</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="agree" name="agree" value="1" <?php echo (isset($_POST['agree']) && $_POST['agree'] == 1) ? 'checked' : ''; ?> >
                        <label class="form-check-label" for="agree">ข้าพเจ้ายินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ </label>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="?main_tab=user_requests&mode=buildings_list" class="btn btn-secondary">ย้อนกลับ</a>
                        <div>
                            <button type="submit" name="action" value="save_draft_building" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                            <button type="submit" name="action" value="submit_building" class="btn btn-success">บันทึกและส่งคำร้อง</button>
                        </div>
                    </div>
                </form>
            </div>

            <?php elseif ($mode == 'buildings_detail' && $detail_item): ?>
                <div class="project-detail-card my-4">
                    <h3 class="mb-4">รายละเอียดคำร้องขอสถานที่: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <div class="row">
                        <div class="col-md-6 pro-details">
                            <p><strong>ชื่อโครงการ:</strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                            <p><strong>สถานะคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                            <p><strong>สถานที่ที่ขอใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['facility_name']); ?></p>
                            <p><strong>วันเริ่มต้นการเตรียมการ:</strong> <?php echo (new DateTime($detail_item['prepare_start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($detail_item['prepare_end_date']))->format('d/m/Y'); ?></p>
                            <p><strong>ตั้งแต่เวลา:</strong> <?php echo (new DateTime($detail_item['prepare_start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['prepare_end_time']))->format('H:i'); ?></p>
                        </div>
                        <div class="col-md-6 pro-details">
                            <p><strong>วันที่สร้างคำร้อง:</strong>
                                    <?php
                                        if (!empty($detail_item['request_date'])) {

                                            $dt = new DateTime($detail_item['request_date']);
                                            $thai_months = [
                                                "", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
                                                "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
                                            ];
                                            $d = (int)$dt->format('j');
                                            $m = (int)$dt->format('n');
                                            $y = (int)$dt->format('Y') + 543;
                                            $time = $dt->format('H:i');
                                            echo "{$d} {$thai_months[$m]} {$y} {$time}";
                                        } else {
                                            echo "-";
                                        }
                                    ?>
                            </p>
                            <p><strong>ผู้เขียนคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['user_name']); ?></p>
                            <p><strong>วันเริ่มต้นการใช้งาน:</strong> <?php echo (new DateTime($detail_item['start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($detail_item['end_date']))->format('d/m/Y'); ?></p>
                            <p><strong>ตั้งแต่เวลา:</strong> <?php echo (new DateTime($detail_item['start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['end_time']))->format('H:i'); ?></p>
                            <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                        </div>
                    </div>
                    <div class="d-flex justify-content-start mt-4">
                        <a href="<?php echo $previous ?: '#'; ?>" 
                            class="btn btn-secondary"
                            onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                ย้อนกลับ
                        </a>
                    </div>
                </div>

        <?php elseif ($mode == 'equipments_create'): ?>
            <div class="form-section my-4">
                <h2 class="mb-4 text-center">สร้างคำร้องขอใช้อุปกรณ์</h2>
                <form action="?main_tab=user_requests&mode=equipments_create" method="POST">
                    <div class="mb-3">
                        <label for="project_id" class="form-label">โครงการ:</label>
                        <select class="form-select" id="project_id" name="project_id" required>
                            <option value="">-- เลือกโครงการ --</option>
                            <?php foreach ($user_projects as $project): ?>
                                <option value="<?php echo htmlspecialchars($project['project_id']); ?>"
                                    <?php echo (isset($_POST['project_id']) && $_POST['project_id'] == $project['project_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['project_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($user_projects)): ?>
                            <p class="text-danger mt-2">คุณยังไม่มีโครงการที่สร้างไว้ กรุณาสร้างโครงการก่อน.</p>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="equip_id" class="form-label">อุปกรณ์ที่ต้องการขอใช้:</label>
                        <select class="form-select" id="equip_id" name="equip_id" required>
                            <option value="">-- เลือกอุปกรณ์ --</option>
                            <?php foreach ($equipments as $equip): ?>
                                <option value="<?php echo htmlspecialchars($equip['equip_id']); ?>"
                                    <?php echo (isset($_POST['equip_id']) && $_POST['equip_id'] == $equip['equip_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($equip['equip_name']) . " (" . htmlspecialchars($equip['measure']) . ")"; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($equipments)): ?>
                            <p class="text-danger mt-2">ยังไม่มีข้อมูลอุปกรณ์ในระบบ</p>
                        <?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="facility_id" class="form-label">สถานที่/อาคารที่อุปกรณ์นำไปใช้งาน:</label>
                        <select class="form-select" id="facility_id" name="facility_id">
                            <option value="">-- เลือกสถานที่/อาคาร (ไม่บังคับ) --</option>
                            <?php foreach ($facilities as $facility): ?>
                                <option value="<?php echo htmlspecialchars($facility['facility_id']); ?>"
                                    <?php echo (isset($_POST['facility_id']) && $_POST['facility_id'] == $facility['facility_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($facility['facility_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($facilities)): ?>
                            <p class="text-danger mt-2">ยังไม่มีข้อมูลสถานที่/อาคารในระบบ</p>
                        <?php endif; ?>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">วันเริ่มต้นใช้การ:</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">วันสิ้นสุดใช้การ:</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="quantity" class="form-label">จำนวนที่ต้องการขอใช้:</label>
                        <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>" required>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="transport" name="transport" value="1" <?php echo (isset($_POST['transport']) && $_POST['transport'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="transport">ต้องการขนส่งอุปกรณ์</label>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="agree" name="agree" value="1" <?php echo (isset($_POST['agree']) && $_POST['agree'] == 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="agree">ข้าพเจ้ายินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ </label>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="?main_tab=user_requests&mode=equipments_list" class="btn btn-secondary">ย้อนกลับ</a>
                        <div>
                            <button type="submit" name="action" value="save_draft_equipment" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                            <button type="submit" name="action" value="submit_equipment" class="btn btn-success">บันทึกและส่งคำร้อง</button>
                        </div>
                    </div>
                </form>
            </div>
        <?php elseif ($mode == 'equipments_detail' && $detail_item): ?>
                <div class="project-detail-card my-4">
                    <h3 class="mb-4">รายละเอียดคำร้องขออุปกรณ์: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <div class="row">
                        <div class="col-md-6 pro-details">
                            <p><strong>ชื่อโครงการ:</strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                            <p><strong>สถานะคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                            <p><strong>อุปกรณ์ที่ขอใช้งาน: </strong> <?php echo htmlspecialchars($detail_item['equip_name']); ?> จำนวน <?php echo htmlspecialchars($detail_item['quantity']);?> <?php echo htmlspecialchars($detail_item['measure']); ?></p>
                            <p><strong>สถานที่ที่นำอุปกรณ์ไปใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['facility_name'] ?? 'ไม่ระบุ'); ?></p>
                            <p><strong>ระยะเวลาใช้การ:</strong> ตั้งแต่วันที่ <?php echo (new DateTime($detail_item['start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($detail_item['end_date']))->format('d/m/Y'); ?></p>

                        </div>
                        <div class="col-md-6 pro-details">
                            <p><strong>วันที่สร้างคำร้อง:</strong>
                                    <?php
                                        if (!empty($detail_item['request_date'])) {

                                            $dt = new DateTime($detail_item['request_date']);
                                            $thai_months = [
                                                "", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
                                                "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
                                            ];
                                            $d = (int)$dt->format('j');
                                            $m = (int)$dt->format('n');
                                            $y = (int)$dt->format('Y') + 543;
                                            $time = $dt->format('H:i');
                                            echo "{$d} {$thai_months[$m]} {$y} {$time}";
                                        } else {
                                            echo "-";
                                        }
                                    ?>
                            </p>
                            <p><strong>ผู้เขียนคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['user_name']); ?></p>
                            <p><strong>ต้องการขนส่งอุปกรณ์:</strong> <?php echo ($detail_item['transport'] == 1) ? 'ต้องการ' : 'ไม่ต้องการ'; ?></p>
                            <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                        </div>
                    </div>
                    <div class="d-flex justify-content-start mt-4">
                        <a href="<?php echo $previous ?: '#'; ?>" 
                            class="btn btn-secondary"
                            onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                ย้อนกลับ
                        </a>
                    </div>
                </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var statusModalElement = document.getElementById('statusModal');
            var statusModal = new bootstrap.Modal(statusModalElement);

            // Check for status parameters in URL
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            const message = urlParams.get('message');
            const mode = urlParams.get('mode'); // ดึง mode มาด้วย
            const projectId = urlParams.get('project_id'); // ดึง project_id มาด้วย

            if (status && message) {
                // Set modal content
                statusModalElement.querySelector('.modal-header').className = 'modal-header ' + (status === 'success' ? 'bg-success' : 'bg-danger') + ' text-white';
                statusModalElement.querySelector('.modal-title').innerText = (status === 'success' ? 'สำเร็จ!' : 'ข้อผิดพลาด!');
                statusModalElement.querySelector('.modal-body').innerText = message;
                statusModalElement.querySelector('.modal-footer .btn').className = 'btn ' + (status === 'success' ? 'btn-success' : 'btn-danger');

                statusModal.show();

                // Clear URL parameters after showing modal (optional, but good practice)
                // ไม่ต้อง clear ถ้าต้องการใช้ parameters สำหรับการแสดงผลหน้ารายละเอียด
                // ถ้าเป็น projects_detail จะไม่ clear params เพราะต้องใช้แสดงข้อมูล
                if (mode !== 'projects_detail') {
                    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + window.location.search.replace(/(\?|&)(status|message)=[^&]*/g, '').replace(/^&/, '?');
                    window.history.replaceState({}, document.title, newUrl);
                } else {
                    // ถ้าเป็น projects_detail ให้ clear แค่ status/message แต่คง project_id ไว้
                    const currentSearchParams = new URLSearchParams(window.location.search);
                    currentSearchParams.delete('status');
                    currentSearchParams.delete('message');
                    const newUrl = window.location.protocol + "//" + window.location.host + window.location.pathname + '?' + currentSearchParams.toString();
                    window.history.replaceState({}, document.title, newUrl);
                }
            }
        });
    </script>
</body>
</html>