<?php
session_start();

// ตรวจสอบสถานะการ Login และ Role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'เจ้าหน้าที่') { // กำหนด role ที่สามารถเข้าถึงได้
    header("Location: login.php");
    exit();
}

include 'database/database.php'; // ไฟล์เชื่อมต่อฐานข้อมูล

$staff_id_for_db = $_SESSION['staff_id'] ?? null; // ปล่อยให้เป็น VARCHAR หรือ null ถ้าไม่มี
$staff_THname = htmlspecialchars($_SESSION['staff_THname'] ?? 'N/A');
$staff_THsur = htmlspecialchars($_SESSION['staff_THsur'] ?? 'N/A');
$staff_ENname = htmlspecialchars($_SESSION['staff_ENname'] ?? 'N/A');
$staff_ENsur = htmlspecialchars($_SESSION['staff_ENsur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');

if (empty($staff_id_for_db) || $staff_id_for_db === 'N/A') {
    $errors[] = "ไม่สามารถดำเนินการได้: Staff ID ไม่ถูกต้องหรือไม่พบสำหรับผู้ดูแลระบบที่ล็อกอินอยู่ โปรดตรวจสอบการตั้งค่าผู้ใช้หรือติดต่อผู้ดูแลระบบ.";
}

$items_per_page = 10; 
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search_query . '%';

$main_tab = isset($_GET['main_tab']) ? $_GET['main_tab'] : 'dashboard_admin';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list'; 

$data = []; 
$detail_item = null; 
$total_items = 0; 
$errors = []; 
$success_message = ''; 


$activity_types = [];
$result_activity = $conn->query("SELECT activity_type_id, activity_type_name FROM activity_type ORDER BY activity_type_name");
if ($result_activity) {
    while ($row = $result_activity->fetch_assoc()) {
        $activity_types[] = $row;
    }
}

$facilities_dropdown = []; // สำหรับ dropdown สถานที่ทั้งหมด (หากต้องการในอนาคต)
$result_facilities_dropdown = $conn->query("SELECT facility_id, facility_name FROM facilities ORDER BY facility_name ASC");
if ($result_facilities_dropdown) {
    while ($row = $result_facilities_dropdown->fetch_assoc()) {
        $facilities_dropdown[] = $row;
    }
}

$equipments_dropdown = []; // สำหรับ dropdown อุปกรณ์ทั้งหมด (หากต้องการในอนาคต)
$result_equipments_dropdown = $conn->query("SELECT equip_id, equip_name, measure FROM equipments ORDER BY equip_name ASC");
if ($result_equipments_dropdown) {
    while ($row = $result_equipments_dropdown->fetch_assoc()) {
        $equipments_dropdown[] = $row;
    }
}


// PHP Logic for Data Fetching
// --- Dashboard Counts (ดึงข้อมูลสำหรับหน้าภาพรวม) ---
$total_projects_count = 0;
$total_facilities_requests_count = 0;
$total_equipments_requests_count = 0;

$previous = $_SERVER['HTTP_REFERER'] ?? '';
if (strpos($previous, 'main_tab=equipments_admin') !== false) {
    $previous = '?main_tab=equipments_admin';
} elseif (strpos($previous, 'main_tab=buildings_admin') !== false) {
    $previous = '?main_tab=buildings_admin';
}

try {
    // Projects count (excluding 'ร่างโครงการ')
    $stmt_proj_count = $conn->prepare("SELECT COUNT(*) FROM project WHERE writed_status != 'ร่างโครงการ'");
    if ($stmt_proj_count) {
        $stmt_proj_count->execute();
        $stmt_proj_count->bind_result($total_projects_count);
        $stmt_proj_count->fetch();
        $stmt_proj_count->close();
    } else {
        error_log("Failed to prepare statement for projects count: " . $conn->error);
    }

    // Facilities Requests count (excluding 'ร่างคำร้องขอ')
    $stmt_fac_req_count = $conn->prepare("SELECT COUNT(*) FROM facilities_requests WHERE writed_status != 'ร่างคำร้องขอ'");
    if ($stmt_fac_req_count) {
        $stmt_fac_req_count->execute();
        $stmt_fac_req_count->bind_result($total_facilities_requests_count);
        $stmt_fac_req_count->fetch();
        $stmt_fac_req_count->close();
    } else {
        error_log("Failed to prepare statement for facilities requests count: " . $conn->error);
    }

    // Equipments Requests count (excluding 'ร่างคำร้องขอ')
    $stmt_equip_req_count = $conn->prepare("SELECT COUNT(*) FROM equipments_requests WHERE writed_status != 'ร่างคำร้องขอ'");
    if ($stmt_equip_req_count) {
        $stmt_equip_req_count->execute();
        $stmt_equip_req_count->bind_result($total_equipments_requests_count);
        $stmt_equip_req_count->fetch();
        $stmt_equip_req_count->close();
    } else {
        error_log("Failed to prepare statement for equipments requests count: " . $conn->error);
    }

    // --- Automatic Project Status Update (เหมือนเดิม) ---
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

            $sql_count = "SELECT COUNT(*) FROM project WHERE project_name LIKE ? AND writed_status != 'ร่างโครงการ'";
            $stmt_count = $conn->prepare($sql_count);
            $stmt_count->bind_param("s", $search_param);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            $sql_data = "SELECT
                            p.project_id, p.project_name, p.start_date, p.end_date, p.project_des, p.files, p.attendee, p.phone_num, p.advisor_name, p.writed_status, p.created_date,
                            at.activity_type_name, CONCAT(u.user_THname, ' ', u.user_THsur) AS user_name
                         FROM project p
                         LEFT JOIN user u ON p.nontri_id = u.nontri_id
                         LEFT JOIN activity_type at ON p.activity_type_id = at.activity_type_id
                         WHERE p.project_name LIKE ? AND p.writed_status != 'ร่างโครงการ'
                         ORDER BY p.created_date DESC LIMIT ? OFFSET ?";
            $stmt_data = $conn->prepare($sql_data);
            $stmt_data->bind_param("sii", $search_param, $items_per_page, $offset);
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
                                at.activity_type_name AS activity_type_name, CONCAT(u.user_THname, ' ', u.user_THsur) AS user_name, u.nontri_id
                           FROM project p
                           LEFT JOIN user u ON p.nontri_id = u.nontri_id
                           LEFT JOIN activity_type at ON p.activity_type_id = at.activity_type_id
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

                $project_facility_requests = [];
                $sql_fr = "SELECT fr.facility_re_id, fr.request_date, fr.writed_status, fr.start_date, fr.end_date, fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time, f.facility_name, fr.approve
                           FROM facilities_requests fr
                           JOIN facilities f ON fr.facility_id = f.facility_id
                           WHERE fr.project_id = ? AND fr.writed_status != 'ร่างคำร้องขอ'
                           ORDER BY fr.request_date DESC";
                $stmt_fr = $conn->prepare($sql_fr);
                $stmt_fr->bind_param("i", $project_id);
                $stmt_fr->execute();
                $result_fr = $stmt_fr->get_result();
                while ($row_fr = $result_fr->fetch_assoc()) {
                    $project_facility_requests[] = $row_fr;
                }
                $stmt_fr->close();

                $project_equipment_requests = [];
                $sql_er = "SELECT er.equip_re_id, er.request_date, er.writed_status, er.start_date, er.end_date, er.quantity, e.equip_name, e.measure, er.approve
                           FROM equipments_requests er
                           JOIN equipments e ON er.equip_id = e.equip_id
                           WHERE er.project_id = ? AND er.writed_status != 'ร่างคำร้องขอ'
                           ORDER BY er.request_date DESC";
                $stmt_er = $conn->prepare($sql_er);
                $stmt_er->bind_param("i", $project_id);
                $stmt_er->execute();
                $result_er = $stmt_er->get_result();
                while ($row_er = $result_er->fetch_assoc()) {
                    $project_equipment_requests[] = $row_er;
                }
                $stmt_er->close();
            }
        }
    } elseif ($main_tab == 'buildings_admin') {
        if ($mode == 'list') {

            $sql_count = "SELECT COUNT(*)
                          FROM facilities_requests fr
                          JOIN project p ON fr.project_id = p.project_id
                          JOIN facilities f ON fr.facility_id = f.facility_id
                          WHERE fr.writed_status != 'ร่างคำร้องขอ' AND (p.project_name LIKE ? OR f.facility_name LIKE ?)";
            $stmt_count = $conn->prepare($sql_count);
            $stmt_count->bind_param("ss", $search_param, $search_param);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            $sql_data = "SELECT
                            fr.facility_re_id, fr.request_date, fr.writed_status, fr.start_date, fr.end_date, fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time, fr.approve,
                            f.facility_name, p.project_name, CONCAT(u.user_THname, ' ', u.user_THsur) AS user_name
                         FROM facilities_requests fr
                         JOIN facilities f ON fr.facility_id = f.facility_id
                         JOIN project p ON fr.project_id = p.project_id
                         LEFT JOIN user u ON p.nontri_id = u.nontri_id
                         WHERE fr.writed_status != 'ร่างคำร้องขอ' AND (p.project_name LIKE ? OR f.facility_name LIKE ?)
                         ORDER BY fr.request_date DESC LIMIT ? OFFSET ?";
            $stmt_data = $conn->prepare($sql_data);
            $stmt_data->bind_param("ssii", $search_param, $search_param, $items_per_page, $offset);
            $stmt_data->execute();
            $result_data = $stmt_data->get_result();
            while ($row = $result_data->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt_data->close();
        } elseif ($mode == 'detail' && isset($_GET['id'])) {
            $facility_re_id = (int)$_GET['id'];
            $sql_detail = "SELECT
                                fr.facility_re_id, fr.project_id, fr.prepare_start_time, fr.prepare_end_time,
                                fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time,
                                fr.start_date , fr.end_date , fr.agree,
                                fr.writed_status, fr.request_date, fr.approve, fr.approve_date, fr.approve_detail,
                                f.facility_name, p.project_name, CONCAT(u.user_THname, ' ', u.user_THsur) AS user_name,
                                CONCAT(s.staff_THname, ' ', s.staff_THsur) AS staff_name
                            FROM facilities_requests fr
                            JOIN facilities f ON fr.facility_id = f.facility_id
                            JOIN project p ON fr.project_id = p.project_id
                            LEFT JOIN user u ON p.nontri_id = u.nontri_id
                            LEFT JOIN staff s ON fr.staff_id = s.staff_id
                            WHERE fr.facility_re_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
            $stmt_detail->bind_param("i", $facility_re_id);
            $stmt_detail->execute();
            $detail_item = $stmt_detail->get_result()->fetch_assoc();
            $stmt_detail->close();

            if (!$detail_item) {
                $errors[] = "ไม่พบคำร้องขอสถานที่ที่คุณร้องขอ.";
                $mode = 'list';
            }
        }
    } elseif ($main_tab == 'equipments_admin') {
        if ($mode == 'list') {

            $sql_count = "SELECT COUNT(*)
                          FROM equipments_requests er
                          JOIN project p ON er.project_id = p.project_id
                          JOIN equipments e ON er.equip_id = e.equip_id
                          WHERE er.writed_status != 'ร่างคำร้องขอ' AND (p.project_name LIKE ? OR e.equip_name LIKE ?)";
            $stmt_count = $conn->prepare($sql_count);
            $stmt_count->bind_param("ss", $search_param, $search_param);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            $sql_data = "SELECT
                            er.equip_re_id, er.request_date, er.writed_status, er.start_date, er.end_date, er.quantity, er.transport, er.approve,
                            e.equip_name, e.measure, f.facility_name, p.project_name, CONCAT(u.user_THname, ' ', u.user_THsur) AS user_name
                         FROM equipments_requests er
                         JOIN equipments e ON er.equip_id = e.equip_id
                         JOIN project p ON er.project_id = p.project_id
                         LEFT JOIN facilities f ON er.facility_id = f.facility_id
                         LEFT JOIN user u ON p.nontri_id = u.nontri_id
                         WHERE er.writed_status != 'ร่างคำร้องขอ' AND (p.project_name LIKE ? OR e.equip_name LIKE ?)
                         ORDER BY er.request_date DESC LIMIT ? OFFSET ?";
            $stmt_data = $conn->prepare($sql_data);
            $stmt_data->bind_param("ssii", $search_param, $search_param, $items_per_page, $offset);
            $stmt_data->execute();
            $result_data = $stmt_data->get_result();
            while ($row = $result_data->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt_data->close();
        } elseif ($mode == 'detail' && isset($_GET['id'])) {
            $equip_re_id = (int)$_GET['id'];
            $sql_detail = "SELECT
                                er.equip_re_id, er.project_id, er.start_date, er.end_date, er.quantity, er.transport,
                                er.writed_status, er.request_date, er.approve, er.approve_date, er.approve_detail, er.agree,
                                e.equip_name, e.measure, f.facility_name, p.project_name, CONCAT(u.user_THname, ' ', u.user_THsur) AS user_name,
                                CONCAT(s.staff_THname, ' ', s.staff_THsur) AS staff_name
                            FROM equipments_requests er
                            JOIN equipments e ON er.equip_id = e.equip_id
                            JOIN project p ON er.project_id = p.project_id
                            LEFT JOIN facilities f ON er.facility_id = f.facility_id
                            LEFT JOIN user u ON p.nontri_id = u.nontri_id
                            LEFT JOIN staff s ON er.staff_id = s.staff_id
                            WHERE er.equip_re_id = ?";
            $stmt_detail = $conn->prepare($sql_detail);
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
    <title>Admin Dashboard KU FTD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="style.css" rel="stylesheet">
</head>
<body class="admin-body">
    <nav class="navbar navbar-dark navigator">
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
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo $staff_THname . ' ' . $staff_THsur; ?></span>
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
        <div class="admin-sidebar">
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

        <div class="admin-content-area">
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
            <?php

            $modal_status = $_GET['status'] ?? '';
            $modal_message = $_GET['message'] ?? '';
            if ($modal_status == 'success' && $modal_message != ''): ?>
                <div class="alert alert-success" role="alert">
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
                <h1 class="mb-4">หน้าหลัก</h1>
                <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
                    <div class="col">
                        <div class="card text-white bg-primary mb-3 h-100">
                            <div class="card-header"><i class="bi bi-folder-fill me-2"></i>โครงการ</div>
                            <div class="card-body">
                                <h5 class="card-title">โครงการทั้งหมด</h5>
                                <p class="card-text fs-1"><?php echo $total_projects_count; ?></p>
                            </div>
                            <div class="card-footer">

                                <a href="?main_tab=projects_admin&mode=list" class="stretched-link text-white text-decoration-none">ดูรายละเอียด <i class="bi bi-arrow-right"></i></a>
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

                                <a href="?main_tab=buildings_admin&mode=list" class="stretched-link text-white text-decoration-none">ดูรายละเอียด <i class="bi bi-arrow-right"></i></a>
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

                                <a href="?main_tab=equipments_admin&mode=list" class="stretched-link text-dark text-decoration-none">ดูรายละเอียด <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
            <?php elseif ($mode == 'list'): ?>
                <h1 class="mb-4">
                    <?php
                    if ($main_tab == 'projects_admin') echo 'โครงการทั้งหมด';
                    elseif ($main_tab == 'buildings_admin') echo 'คำร้องขอใช้สถานที่';
                    elseif ($main_tab == 'equipments_admin') echo 'คำร้องขอใช้อุปกรณ์';
                    ?>
                </h1>
                <div class="d-flex justify-content-end mb-3">
                    <form class="d-flex" action="" method="GET">
                        <input type="hidden" name="main_tab" value="<?php echo htmlspecialchars($main_tab); ?>">
                        <input type="hidden" name="mode" value="list">
                        <input class="form-control me-2" type="search" placeholder="ค้นหา..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-outline-success" type="submit">ค้นหา</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="?main_tab=<?php echo htmlspecialchars($main_tab); ?>&mode=list" class="btn btn-outline-secondary ms-2">ล้าง</a>
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
                    <div class="table-responsive admin-details">
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
                                                    } else { 
                                                        echo htmlspecialchars($item['approve']);
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-warning text-dark">รอดำเนินการ</span>';
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
                                                } else {
                                                    echo '<span class="badge bg-warning text-dark">รอดำเนินการ</span>';
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
                    <!-- Pagination -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=<?php echo htmlspecialchars($main_tab); ?>&mode=list&page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">ก่อนหน้า</a>
                                </li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?main_tab=<?php echo htmlspecialchars($main_tab); ?>&mode=list&page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=<?php echo htmlspecialchars($main_tab); ?>&mode=list&page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">ถัดไป</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($mode == 'detail' && $detail_item): ?>
                <h2 class="mb-4">
                    รายละเอียด
                    <?php
                    if ($main_tab == 'projects_admin') echo 'โครงการ: ' . htmlspecialchars($detail_item['project_name']);
                    elseif ($main_tab == 'buildings_admin') echo 'คำร้องขอใช้สถานที่สำหรับโครงการ: ' . htmlspecialchars($detail_item['project_name']);
                    elseif ($main_tab == 'equipments_admin') echo 'คำร้องขอใช้อุปกรณ์สำหรับโครงการ: ' . htmlspecialchars($detail_item['project_name']);
                    ?>
                </h2>
                <div class="card shadow-sm p-4 admin-details">
                    <?php if ($main_tab == 'projects_admin'): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>ชื่อโครงการ:</strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                                <p><strong>สถานะโครงการ:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                                <p><strong>ระยะเวลาโครงการ:</strong> ตั้งแต่วันที่ <?php echo (new DateTime($detail_item['start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($detail_item['end_date']))->format('d/m/Y'); ?></p>
                                <p><strong>จำนวนผู้เข้าร่วม:</strong> <?php echo htmlspecialchars($detail_item['attendee']); ?></p>
                                <p><strong>หมายเลขโทรศัพท์:</strong> <?php echo htmlspecialchars($detail_item['phone_num']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <?php if (isset($detail_item['advisor_name']) && !empty($detail_item['advisor_name'])): // ตรวจสอบ isset ก่อน ?>
                                    <p><strong>ชื่อที่ปรึกษาโครงการ:</strong> <?php echo htmlspecialchars($detail_item['advisor_name']); ?></p>
                                <?php endif; ?>
                                <p><strong>ประเภทกิจกรรม:</strong> <?php echo htmlspecialchars($detail_item['activity_type_name'] ?? 'ไม่ระบุ'); ?></p>
                                <p><strong>ผู้ยื่นโครงการ:</strong> <?php echo htmlspecialchars($detail_item['user_name'] ?? 'ไม่ระบุ'); ?></p>
                                <p><strong>วันที่สร้างโครงการ:</strong> <?php echo (new DateTime($detail_item['created_date']))->format('d/m/Y H:i'); ?></p>
                                <p><strong>รายละเอียดโครงการ:</strong><br> <?php echo nl2br(htmlspecialchars($detail_item['project_des'])); ?></p>
                                <?php if ($detail_item['files'] && file_exists($detail_item['files'])): ?>
                                    <a href="<?php echo htmlspecialchars($detail_item['files']); ?>" target="_blank" class="btn btn-secondary btn-sm mt-2"> ดูไฟล์แนบ</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h4 class="mt-4 mb-3">คำร้องขอใช้สถานที่ที่เกี่ยวข้อง</h4>
                        <?php if (empty($project_facility_requests)): ?>
                            <div class="alert alert-info">ไม่มีคำร้องขอใช้สถานที่สำหรับโครงการนี้</div>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($project_facility_requests as $req): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($req['facility_name']); ?></strong>
                                            (สถานะ: <?php echo htmlspecialchars($req['writed_status']); ?>)  
                                            <?php if (isset($req['approve']) && !empty($req['approve'])): // ตรวจสอบ isset ก่อน ?>
                                                (การอนุมัติคำร้อง: <?php echo ($req['approve'] == 'อนุมัติ') ? '<span class="badge bg-success ms-1">อนุมัติ</span>' : '<span class="badge bg-danger ms-1">ไม่อนุมัติ</span>'; ?>)
                                            <?php endif; ?>
                                            <br><small>ช่วงเวลา: <?php echo (new DateTime($req['start_date']))->format('d/m/Y'); ?> ถึง <?php echo (new DateTime($req['end_date']))->format('d/m/Y'); ?></small>
                                        </div>
                                        <a href="?main_tab=buildings_admin&mode=detail&id=<?php echo $req['facility_re_id']; ?>" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <h4 class="mt-4 mb-3">คำร้องขอใช้อุปกรณ์ที่เกี่ยวข้อง</h4>
                        <?php if (empty($project_equipment_requests)): ?>
                            <div class="alert alert-info">ไม่มีคำร้องขอใช้อุปกรณ์สำหรับโครงการนี้</div>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($project_equipment_requests as $req): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($req['equip_name']); ?></strong> (<?php echo htmlspecialchars($req['quantity'] . ' ' . $req['measure']); ?>)
                                            (สถานะ: <?php echo htmlspecialchars($req['writed_status']); ?>)  
                                            <?php if (isset($req['approve']) && !empty($req['approve'])): // ตรวจสอบ isset ก่อน ?>
                                                (การอนุมัติคำร้อง: <?php echo ($req['approve'] == 'อนุมัติ') ? '<span class="badge bg-success ms-1">อนุมัติ</span>' : '<span class="badge bg-danger ms-1">ไม่อนุมัติ</span>'; ?>)
                                            <?php endif; ?>
                                            <br><small>ช่วงเวลา: <?php echo (new DateTime($req['start_date']))->format('d/m/Y'); ?> ถึง <?php echo (new DateTime($req['end_date']))->format('d/m/Y'); ?></small>
                                        </div>
                                        <a href="?main_tab=equipments_admin&mode=detail&id=<?php echo $req['equip_re_id']; ?>" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>


                    <?php elseif ($main_tab == 'buildings_admin'): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>ชื่อโครงการ:</strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></a></p>
                                <p><strong>ผู้ยื่นคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['user_name'] ?? 'N/A'); ?></p>
                                <p><strong>สถานที่ที่ขอใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['facility_name']); ?></p>
                                <p><strong>สถานะคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                                <p><strong>วันเริ่มต้นการเตรียมการ:</strong> <?php echo (new DateTime($detail_item['prepare_start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($detail_item['prepare_end_date']))->format('d/m/Y'); ?></p>
                                <p><strong>ตั้งแต่เวลา:</strong> <?php echo (new DateTime($detail_item['prepare_start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['prepare_end_time']))->format('H:i'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>วันที่สร้างคำร้อง:</strong> <?php echo (new DateTime($detail_item['request_date']))->format('d/m/Y H:i'); ?></p>
                                <p><strong>วันเริ่มต้นการใช้งาน:</strong> <?php echo (new DateTime($detail_item['start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($detail_item['end_date']))->format('d/m/Y'); ?></p>
                                <p><strong>ตั้งแต่เวลา:</strong> <?php echo (new DateTime($detail_item['start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['end_time']))->format('H:i'); ?></p>
                                <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                                <p class="mt-3">
                                    <strong>สถานะการอนุมัติ:</strong>
                                    <?php
                                    if (isset($detail_item['approve']) && !empty($detail_item['approve'])) { // ใช้ isset() เพื่อความปลอดภัย
                                        echo htmlspecialchars($detail_item['approve']);
                                    } else {
                                        echo 'รอดำเนินการ';
                                    }
                                    ?>
                                </p>
                                <?php if (isset($detail_item['approve']) && !empty($detail_item['approve'])): // แสดงรายละเอียดเพิ่มเติมเฉพาะเมื่อมีการอนุมัติ/ไม่อนุมัติแล้ว ?>
                                    <p><strong>วันที่ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['approve_date']) && $detail_item['approve_date'] ? (new DateTime($detail_item['approve_date']))->format('d/m/Y') : 'N/A'); ?></p>
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
                    <?php elseif ($main_tab == 'equipments_admin'): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>ชื่อโครงการ:</strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></a></p>
                                <p><strong>ผู้ยื่นคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['user_name'] ?? 'N/A'); ?></p>
                                <p><strong>อุปกรณ์ที่ขอใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['equip_name']); ?></p>
                                <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($detail_item['quantity']) . ' ' . htmlspecialchars($detail_item['measure']); ?></p>
                                <p><strong>สถานที่นำอุปกรณ์ไปใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['facility_name'] ?? 'ไม่ระบุ'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>สถานะคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                                <p><strong>วันที่สร้างคำร้อง:</strong> <?php echo (new DateTime($detail_item['request_date']))->format('d/m/Y H:i'); ?></p>
                                <p><strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo (new DateTime($detail_item['start_date']))->format('d/m/Y'); ?> ถึง วันที่ <?php echo (new DateTime($detail_item['end_date']))->format('d/m/Y'); ?></p>
                                <p><strong>ต้องการขนส่งอุปกรณ์:</strong> <?php echo ($detail_item['transport'] == 1) ? 'ต้องการ' : 'ไม่ต้องการ'; ?></p>
                                <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                                <p class="mt-3">
                                    <strong>สถานะการอนุมัติ:</strong>
                                    <?php
                                    // ตรวจสอบ approve column
                                    if (isset($detail_item['approve']) && !empty($detail_item['approve'])) { // ใช้ isset() เพื่อความปลอดภัย
                                        echo htmlspecialchars($detail_item['approve']);
                                    } else {
                                        echo 'รอดำเนินการ'; // ถ้า approve เป็น NULL หรือว่างเปล่า
                                    }
                                    ?>
                                </p>
                                <?php if (isset($detail_item['approve']) && !empty($detail_item['approve'])): // แสดงรายละเอียดเพิ่มเติมเฉพาะเมื่อมีการอนุมัติ/ไม่อนุมัติแล้ว ?>
                                    <p><strong>วันที่ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['approve_date']) && $detail_item['approve_date'] ? (new DateTime($detail_item['approve_date']))->format('d/m/Y') : 'N/A'); ?></p>
                                    <p><strong>ผู้ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['staff_name']) ? ($detail_item['staff_name'] ?? 'N/A') : 'N/A'); ?></p>
                                    <?php if ($detail_item['approve'] == 'ไม่อนุมัติ'): ?>
                                        <p><strong>เหตุผลการไม่อนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                    <?php endif; ?>
                                    <?php if ($detail_item['approve'] == 'อนุมัติ' && !empty($detail_item['approve_detail'])): ?>
                                        <p><strong>รายละเอียดการอนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mt-4">
                        <?php if (strpos($previous, 'main_tab=projects_admin&mode=detail') !== false): ?>
                            <a href='admin-main-page.php?main_tab=projects_admin' class="btn btn-secondary"> ย้อนกลับ</a>
                        <?php else: ?>
                            <a href="<?php echo $previous ?: '#'; ?>" 
                                class="btn btn-secondary"
                                onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                    ย้อนกลับ
                            </a>
                        <?php endif; ?>
                        <div>
                            <?php
                            if (($main_tab == 'buildings_admin' || $main_tab == 'equipments_admin') && ($detail_item['approve'] !== 'อนุมัติ')):
                            ?>
                                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#approveModal">
                                    อนุมัติ
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    ไม่อนุมัติ
                                </button>

                                <!-- Approve Modal -->
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
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight active sidebar link
            const urlParams = new URLSearchParams(window.location.search);
            const currentTab = urlParams.get('main_tab') || 'dashboard_admin'; // Default to dashboard_admin
            const navLinks = document.querySelectorAll('.admin-sidebar .nav-link'); // ใช้ .admin-sidebar เพื่อความเฉพาะเจาะจง

            navLinks.forEach(link => {
                link.classList.remove('active'); // Remove active from all
                if (link.href.includes(`main_tab=${currentTab}`)) {
                    link.classList.add('active'); // Add active to current tab
                }
            });
        });
    </script>
</body>
</html>