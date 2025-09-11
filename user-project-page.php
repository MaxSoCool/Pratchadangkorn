<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include 'database/database.php';
include 'php/sorting.php';

if (isset($_GET['action']) && $_GET['action'] == 'get_facilities_by_project' && isset($_GET['project_id'])) {
    header('Content-Type: application/json'); 
    $projectId = (int)$_GET['project_id'];
    $userId = $_SESSION['nontri_id'] ?? ''; 

    $facilities_for_project = [];
    if ($projectId > 0 && !empty($userId)) {
        $sql = "SELECT DISTINCT f.facility_id, f.facility_name
                FROM facilities f
                JOIN facilities_requests fr ON f.facility_id = fr.facility_id
                JOIN project p ON fr.project_id = p.project_id
                WHERE fr.project_id = ? AND p.nontri_id = ?
                ORDER BY f.facility_name ASC";

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $projectId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $facilities_for_project[] = $row;
            }
            $stmt->close();
        } else {
            error_log("AJAX SQL Error (get_facilities_by_project): " . $conn->error);
        }
    }
    echo json_encode($facilities_for_project);
    exit(); 
}

$user_THname = htmlspecialchars($_SESSION['user_THname'] ?? 'N/A');
$user_THsur = htmlspecialchars($_SESSION['user_THsur'] ?? 'N/A');
$user_ENname = htmlspecialchars($_SESSION['user_ENname'] ?? 'N/A');
$user_ENsur = htmlspecialchars($_SESSION['user_ENsur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$fa_de_name = htmlspecialchars($_SESSION['fa_de_name'] ?? 'N/A');
$nontri_id = htmlspecialchars($_SESSION['nontri_id'] ?? 'N/A');
$user_id = $_SESSION['nontri_id'] ?? '';

$main_tab = $_GET['main_tab'] ?? 'user_dashboard';

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'projects_list'; 

$data = []; 
$detail_item = null;
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

// <<<<< NEW SECTION: Helper functions for Dashboard & Status Display >>>>>
// ฟังก์ชันสำหรับกำหนด class ของ Badge ตามสถานะ
function getStatusBadgeClass($status_text, $approve_status = null) {
    if ($approve_status === 'อนุมัติ') {
        return 'bg-success';
    } elseif ($approve_status === 'ไม่อนุมัติ') {
        return 'bg-danger';
    } elseif ($approve_status === 'ยกเลิก') { // NEW
        return 'bg-dark'; // NEW
    } else {
        switch ($status_text) {
            case 'ร่างโครงการ':
            case 'ร่างคำร้องขอ':
                return 'bg-warning text-dark'; //
            case 'ส่งโครงการ':
            case 'ส่งคำร้องขอ':
                return 'bg-primary'; // สีเหลืองสำหรับส่งแล้ว/รอดำเนินการ
            case 'เริ่มดำเนินการ':
                return 'bg-info text-dark'; // สีน้ำเงินสำหรับกำลังดำเนินการ
            case 'สิ้นสุดโครงการ':
            case 'สิ้นสุดดำเนินการ':
                return 'bg-secondary'; // สีฟ้าสำหรับสิ้นสุด
            case 'ยกเลิกโครงการ': // NEW
            case 'ยกเลิกคำร้องขอ': // NEW
                return 'bg-dark'; // สีดำสำหรับสถานะยกเลิก
            default:
                return 'bg-secondary'; // สีดำสำหรับสถานะอื่น ๆ (changed default to secondary)
        }
    }
}

// ฟังก์ชันสำหรับจัดรูปแบบวันที่เป็นภาษาไทย
function formatThaiDate($date_str, $include_time = true) {
    if (empty($date_str)) return "-";
    $dt = new DateTime($date_str);
    $thai_months = [
        "", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
        "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
    ];
    $d = (int)$dt->format('j');
    $m = (int)$dt->format('n');
    $y = (int)$dt->format('Y') + 543; // ปีพ.ศ.
    $output = "{$d} {$thai_months[$m]} {$y}";
    if ($include_time) {
        $time = $dt->format('H:i');
        $output .= " {$time}";
    }
    return $output;
}
// <<<<< END NEW SECTION >>>>>


// Initialize dashboard data structure
$dashboard_data = [
    'project_counts' => [
        'total' => 0,
        'draft' => 0,
        'submitted' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0, // NEW
    ],
    'facilities_request_counts' => [
        'total' => 0,
        'draft' => 0,
        'submitted' => 0,
        'pending_approval' => 0,
        'approved' => 0,
        'rejected' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0, // NEW
    ],
    'equipments_request_counts' => [
        'total' => 0,
        'draft' => 0,
        'submitted' => 0,
        'pending_approval' => 0,
        'approved' => 0,
        'rejected' => 0,
        'in_progress' => 0,
        'completed' => 0,
        'cancelled' => 0, // NEW
    ],
    'upcoming_requests' => [],
    'recent_activity' => [],
];


// <<<<< MODIFIED SECTION: PHP Logic for Dashboard (main_tab = user_dashboard) >>>>>
if (!empty($user_id)) {
    // 1. นับจำนวนโครงการ
    $sql_projects_count = "SELECT writed_status, COUNT(*) AS count FROM project WHERE nontri_id = ? GROUP BY writed_status";
    $stmt_projects_count = $conn->prepare($sql_projects_count);
    $stmt_projects_count->bind_param("s", $user_id);
    $stmt_projects_count->execute();
    $result_projects_count = $stmt_projects_count->get_result();
    while ($row = $result_projects_count->fetch_assoc()) {
        $status_key = '';
        if ($row['writed_status'] == 'ร่างโครงการ') $status_key = 'draft';
        else if ($row['writed_status'] == 'ส่งโครงการ') $status_key = 'submitted';
        else if ($row['writed_status'] == 'เริ่มดำเนินการ') $status_key = 'in_progress';
        else if ($row['writed_status'] == 'สิ้นสุดโครงการ') $status_key = 'completed';
        else if ($row['writed_status'] == 'ยกเลิกโครงการ') $status_key = 'cancelled'; // NEW

        if ($status_key) {
            $dashboard_data['project_counts'][$status_key] = $row['count'];
            $dashboard_data['project_counts']['total'] += $row['count'];
        }
    }
    $stmt_projects_count->close();

    // 2. นับจำนวนคำร้องขอสถานที่
    $sql_fr_count = "SELECT fr.writed_status, fr.approve, COUNT(*) AS count
                        FROM facilities_requests fr
                        JOIN project p ON fr.project_id = p.project_id
                        WHERE p.nontri_id = ? GROUP BY fr.writed_status, fr.approve";
    $stmt_fr_count = $conn->prepare($sql_fr_count);
    $stmt_fr_count->bind_param("s", $user_id);
    $stmt_fr_count->execute();
    $result_fr_count = $stmt_fr_count->get_result();
    while ($row = $result_fr_count->fetch_assoc()) {
        $dashboard_data['facilities_request_counts']['total'] += $row['count'];

        if ($row['writed_status'] == 'ร่างคำร้องขอ') {
            $dashboard_data['facilities_request_counts']['draft'] += $row['count'];
        } elseif ($row['writed_status'] == 'ส่งคำร้องขอ') {
            $dashboard_data['facilities_request_counts']['submitted'] += $row['count'];
            if ($row['approve'] === null) { // สมมติว่า null คือรอดำเนินการ
                $dashboard_data['facilities_request_counts']['pending_approval'] += $row['count'];
            }
        } elseif ($row['writed_status'] == 'เริ่มดำเนินการ') {
                $dashboard_data['facilities_request_counts']['in_progress'] += $row['count'];
        } elseif ($row['writed_status'] == 'สิ้นสุดดำเนินการ') {
            $dashboard_data['facilities_request_counts']['completed'] += $row['count'];
        } elseif ($row['writed_status'] == 'ยกเลิกคำร้องขอ') { // NEW
            $dashboard_data['facilities_request_counts']['cancelled'] += $row['count'];
        }

        if ($row['approve'] == 'อนุมัติ') {
            $dashboard_data['facilities_request_counts']['approved'] += $row['count'];
        } elseif ($row['approve'] == 'ไม่อนุมัติ') {
            $dashboard_data['facilities_request_counts']['rejected'] += $row['count'];
        }
    }
    $stmt_fr_count->close();

    $sql_er_count = "SELECT er.writed_status, er.approve, COUNT(*) AS count
                        FROM equipments_requests er
                        JOIN project p ON er.project_id = p.project_id
                        WHERE p.nontri_id = ? GROUP BY er.writed_status, er.approve";
    $stmt_er_count = $conn->prepare($sql_er_count);
    $stmt_er_count->bind_param("s", $user_id);
    $stmt_er_count->execute();
    $result_er_count = $stmt_er_count->get_result();
    while ($row = $result_er_count->fetch_assoc()) {
        $dashboard_data['equipments_request_counts']['total'] += $row['count'];

        if ($row['writed_status'] == 'ร่างคำร้องขอ') {
            $dashboard_data['equipments_request_counts']['draft'] += $row['count'];
        } elseif ($row['writed_status'] == 'ส่งคำร้องขอ') {
            $dashboard_data['equipments_request_counts']['submitted'] += $row['count'];
            if ($row['approve'] === null) {
                $dashboard_data['equipments_request_counts']['pending_approval'] += $row['count'];
            }
        } elseif ($row['writed_status'] == 'เริ่มดำเนินการ') {
                $dashboard_data['equipments_request_counts']['in_progress'] += $row['count'];
        } elseif ($row['writed_status'] == 'สิ้นสุดดำเนินการ') {
            $dashboard_data['equipments_request_counts']['completed'] += $row['count'];
        } elseif ($row['writed_status'] == 'ยกเลิกคำร้องขอ') { // NEW
            $dashboard_data['equipments_request_counts']['cancelled'] += $row['count'];
        }

        if ($row['approve'] == 'อนุมัติ') {
            $dashboard_data['equipments_request_counts']['approved'] += $row['count'];
        } elseif ($row['approve'] == 'ไม่อนุมัติ') {
            $dashboard_data['equipments_request_counts']['rejected'] += $row['count'];
        }
    }
    $stmt_er_count->close();

    // 4. กิจกรรมที่กำลังจะมาถึง (เฉพาะโครงการ ภายใน 30 วันข้างหน้า)
    $upcoming_date_limit = date('Y-m-d', strtotime('+14 days'));
    $current_date_php = date('Y-m-d');

    $sql_upcoming_projects = "SELECT project_id AS id, 'โครงการ' AS type, project_name AS name,
                                start_date, end_date, NULL AS start_time, NULL AS end_time,
                                project_name AS project_name_for_display, writed_status AS writed_status_for_display, NULL AS approve_for_display
                        FROM project
                        WHERE nontri_id = ? AND start_date BETWEEN ? AND ?
                        AND (writed_status = 'ส่งโครงการ' OR writed_status = 'เริ่มดำเนินการ')
                        ORDER BY start_date ASC";
    $stmt_upcoming_projects = $conn->prepare($sql_upcoming_projects);
    $stmt_upcoming_projects->bind_param("sss", $user_id, $current_date_php, $upcoming_date_limit);
    $stmt_upcoming_projects->execute();
    $result_upcoming_projects = $stmt_upcoming_projects->get_result();
    while ($row = $result_upcoming_projects->fetch_assoc()) {
        // ปรับโครงสร้างข้อมูลให้เข้ากับโครงสร้างของ $dashboard_data['upcoming_requests']
        $dashboard_data['upcoming_requests'][] = [
            'id' => $row['id'],
            'type' => $row['type'],
            'name' => $row['name'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'start_time' => $row['start_time'], // จะเป็น NULL
            'end_time' => $row['end_time'],     // จะเป็น NULL
            'project_name' => $row['name'], // สำหรับโครงการเอง project_name ก็คือ name
            'writed_status' => $row['writed_status_for_display'],
            'approve' => $row['approve_for_display'], // จะเป็น NULL
        ];
    }
    $stmt_upcoming_projects->close();

    // จัดเรียงกิจกรรมที่กำลังจะมาถึง (ยังคงรวมการเรียงจากเก่าไปใหม่) และจำกัดจำนวน
    usort($dashboard_data['upcoming_requests'], function($a, $b) {
        return strtotime($a['start_date']) - strtotime($b['start_date']);
    });
    $dashboard_data['upcoming_requests'] = array_slice($dashboard_data['upcoming_requests'], 0, 5);


    // 5. กิจกรรมล่าสุด (5 รายการล่าสุดจากโครงการและคำร้องทั้งหมด)
    $all_recent_activity_raw = [];

    // โครงการล่าสุด
    $sql_recent_p = "SELECT project_id AS id, 'โครงการ' AS item_type, project_name AS item_name,
                            created_date AS activity_date, writed_status AS status_text, NULL AS approve_status
                    FROM project WHERE nontri_id = ?
                    ORDER BY created_date DESC LIMIT 5";
    $stmt_recent_p = $conn->prepare($sql_recent_p);
    $stmt_recent_p->bind_param("s", $user_id);
    $stmt_recent_p->execute();
    $result_recent_p = $stmt_recent_p->get_result();
    while ($row = $result_recent_p->fetch_assoc()) {
        $all_recent_activity_raw[] = $row;
    }
    $stmt_recent_p->close();

    // คำร้องขอสถานที่ล่าสุด
    $sql_recent_fr = "SELECT fr.facility_re_id AS id, 'คำร้องขอสถานที่' AS item_type, f.facility_name AS item_name,
                            fr.request_date AS activity_date, fr.writed_status AS status_text, fr.approve AS approve_status
                    FROM facilities_requests fr
                    JOIN project p ON fr.project_id = p.project_id
                    JOIN facilities f ON fr.facility_id = f.facility_id
                    WHERE p.nontri_id = ?
                    ORDER BY fr.request_date DESC LIMIT 5";
    $stmt_recent_fr = $conn->prepare($sql_recent_fr);
    $stmt_recent_fr->bind_param("s", $user_id);
    $stmt_recent_fr->execute();
    $result_recent_fr = $stmt_recent_fr->get_result();
    while ($row = $result_recent_fr->fetch_assoc()) {
        $all_recent_activity_raw[] = $row;
    }
    $stmt_recent_fr->close();

    // คำร้องขออุปกรณ์ล่าสุด
    $sql_recent_er = "SELECT er.equip_re_id AS id, 'คำร้องขออุปกรณ์' AS item_type, e.equip_name AS item_name,
                            er.request_date AS activity_date, er.writed_status AS status_text, er.approve AS approve_status
                    FROM equipments_requests er
                    JOIN project p ON er.project_id = p.project_id
                    JOIN equipments e ON er.equip_id = e.equip_id
                    WHERE p.nontri_id = ?
                    ORDER BY er.request_date DESC LIMIT 5";
    $stmt_recent_er = $conn->prepare($sql_recent_er);
    $stmt_recent_er->bind_param("s", $user_id);
    $stmt_recent_er->execute();
    $result_recent_er = $stmt_recent_er->get_result();
    while ($row = $result_recent_er->fetch_assoc()) {
        $all_recent_activity_raw[] = $row;
    }
    $stmt_recent_er->close();

    // จัดเรียงกิจกรรมล่าสุดทั้งหมดและจำกัดจำนวน
    usort($all_recent_activity_raw, function($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']); // เรียงจากใหม่ไปเก่า
    });
    $dashboard_data['recent_activity'] = array_slice($all_recent_activity_raw, 0, 5);
}

$previous = $_SERVER['HTTP_REFERER'] ?? '';
$current_mode = $_GET['mode'] ?? '';
$this_tab = $_GET['this_tab'] ?? ''; 

if ($current_mode === 'projects_detail') {

    if (strpos($previous, 'mode=buildings_detail') === false && 
        strpos($previous, 'mode=equipments_detail') === false) {
        $_SESSION['projects_detail_initial_referrer'] = $previous;
    }
} else {

    if ($current_mode === 'projects_list' || $current_mode === 'user_dashboard' || $this_tab !== 'user_requests') {
         unset($_SESSION['projects_detail_initial_referrer']);
    }
}

if ($current_mode === 'projects_detail' && 
   (strpos($previous, 'mode=buildings_detail') !== false || strpos($previous, 'mode=equipments_detail') !== false)) {
    
    if (isset($_SESSION['projects_detail_initial_referrer']) && !empty($_SESSION['projects_detail_initial_referrer'])) {
        $previous = $_SESSION['projects_detail_initial_referrer'];
    } else {

        $previous = '?this_tab=user_dashboard'; 
    }


} elseif (strpos($previous, 'this_tab=user_requests') !== false) {

    if (strpos($previous, 'mode=projects_edit') !== false || strpos($previous, 'mode=projects_create') !== false) {
        $previous = '?this_tab=user_requests&mode=projects_list';
    } elseif (strpos($previous, 'mode=buildings_edit') !== false || strpos($previous, 'mode=buildings_create') !== false) {
        $previous = '?this_tab=user_requests&mode=buildings_list';
    } elseif (strpos($previous, 'mode=equipments_edit') !== false || strpos($previous, 'mode=equipments_create') !== false) {
        $previous = '?this_tab=user_requests&mode=equipments_list';
    }

} elseif (strpos($previous, 'this_tab=user_dashboard') !== false) {
    $previous = '?this_tab=user_dashboard';

} elseif (empty($previous)) {
    $previous = '?this_tab=user_dashboard';
}

try {
    $current_date = date('Y-m-d'); // This variable is for automatic status updates

    // Only end projects if they are not already completed or cancelled
    $stmt_project_end = $conn->prepare("UPDATE project SET writed_status = 'สิ้นสุดโครงการ' WHERE end_date < ? AND writed_status NOT IN ('สิ้นสุดโครงการ', 'ยกเลิกโครงการ')");
    if ($stmt_project_end) {
        $stmt_project_end->bind_param("s", $current_date);
        $stmt_project_end->execute();
        $stmt_project_end->close();
    } else {
        error_log("Failed to prepare statement for ending projects: " . $conn->error);
    }

    // Only start projects if they are 'ส่งโครงการ' (submitted), which implies not cancelled or already started/ended
    $stmt_project_start = $conn->prepare("UPDATE project SET writed_status = 'เริ่มดำเนินการ' WHERE start_date <= ? AND writed_status = 'ส่งโครงการ'");
    if ($stmt_project_start) {
        $stmt_project_start->bind_param("s", $current_date);
        $stmt_project_start->execute();
        $stmt_project_start->close();
    } else {
        error_log("Failed to prepare statement for starting projects: " . $conn->error);
    }

    // Only end building requests if not already completed or cancelled
    $stmt_building_end = $conn->prepare("UPDATE facilities_requests SET writed_status = 'สิ้นสุดดำเนินการ' WHERE end_date < ? AND writed_status NOT IN ('สิ้นสุดดำเนินการ', 'ยกเลิกคำร้องขอ')");
    if ($stmt_building_end) {
        $stmt_building_end->bind_param("s", $current_date);
        $stmt_building_end->execute();
        $stmt_building_end->close();
    } else {
        error_log("Failed to prepare statement for ending buildings: " . $conn->error);
    }

    // Only start building requests if 'ส่งคำร้องขอ' (submitted) and 'อนุมัติ' (approved)
    $stmt_building_start = $conn->prepare("UPDATE facilities_requests SET writed_status = 'เริ่มดำเนินการ' WHERE start_date <= ? AND writed_status = 'ส่งคำร้องขอ' AND approve = 'อนุมัติ'");
    if ($stmt_building_start) {
        $stmt_building_start->bind_param("s", $current_date);
        $stmt_building_start->execute();
        $stmt_building_start->close();
    } else {
        error_log("Failed to prepare statement for starting buildings: " . $conn->error);
    }

    // Only end equipment requests if not already completed or cancelled
    $stmt_equipment_end = $conn->prepare("UPDATE equipments_requests SET writed_status = 'สิ้นสุดดำเนินการ' WHERE end_date < ? AND writed_status NOT IN ('สิ้นสุดดำเนินการ', 'ยกเลิกคำร้องขอ')");
    if ($stmt_equipment_end) {
        $stmt_equipment_end->bind_param("s", $current_date);
        $stmt_equipment_end->execute();
        $stmt_equipment_end->close();
    } else {
        error_log("Failed to prepare statement for ending equipments: " . $conn->error);
    }

    // Only start equipment requests if 'ส่งคำร้องขอ' (submitted) and 'อนุมัติ' (approved)
    $stmt_equipment_start = $conn->prepare("UPDATE equipments_requests SET writed_status = 'เริ่มดำเนินการ' WHERE start_date <= ? AND writed_status = 'ส่งคำร้องขอ' AND approve = 'อนุมัติ'");
    if ($stmt_equipment_start) {
        $stmt_equipment_start->bind_param("s", $current_date);
        $stmt_equipment_start->execute();
        $stmt_equipment_start->close();
    } else {
        error_log("Failed to prepare statement for starting equipments: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Automatic Status Update Error: " . $e->getMessage());
}

// Data for forms and dropdowns (activity_types, user_projects, facilities, equipments)
$activity_types = [];
$result_activity = $conn->query("SELECT activity_type_id, activity_type_name FROM activity_type ORDER BY activity_type_name");
if ($result_activity) {
    while ($row = $result_activity->fetch_assoc()) {
        $activity_types[] = $row;
    }
}

$user_projects = []; // Used for dropdowns in create forms
// Exclude cancelled projects from dropdown for new requests
$sql_user_projects = "SELECT project_id, project_name, writed_status FROM project WHERE nontri_id = ? AND writed_status NOT IN ('ร่างโครงการ', 'สิ้นสุดโครงการ', 'ยกเลิกโครงการ') ORDER BY project_name ASC";
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
// Initial load of all facilities for 'buildings_create' and general use, or specific for 'equipments_edit'
// The AJAX call will handle 'equipments_create' dynamically
if ($main_tab == 'user_requests' && ($mode == 'buildings_create' || $mode == 'buildings_edit' || ($mode == 'equipments_edit' && isset($detail_item['project_id'])))) {
    if ($mode == 'equipments_edit' && isset($detail_item['project_id'])) {
        $projectIdForEdit = $detail_item['project_id'];
        $sql_facilities_for_edit = "
            SELECT DISTINCT f.facility_id, f.facility_name
            FROM facilities f
            JOIN facilities_requests fr ON f.facility_id = fr.facility_id
            JOIN project p ON fr.project_id = p.project_id
            WHERE fr.project_id = ? AND p.nontri_id = ?
            ORDER BY f.facility_name ASC";
        $stmt_facilities_for_edit = $conn->prepare($sql_facilities_for_edit);
        if ($stmt_facilities_for_edit) {
            $stmt_facilities_for_edit->bind_param("is", $projectIdForEdit, $user_id);
            $stmt_facilities_for_edit->execute();
            $result_facilities_edit = $stmt_facilities_for_edit->get_result();
            while ($row = $result_facilities_edit->fetch_assoc()) {
                $facilities[] = $row;
            }
            $stmt_facilities_for_edit->close();
        } else {
            $errors[] = "ไม่สามารถเตรียมคำสั่ง SQL สำหรับดึงสถานที่สำหรับแก้ไข: " . $conn->error;
        }
    } else {
        $result_all_facilities = $conn->query("SELECT facility_id, facility_name FROM facilities ORDER BY facility_id ASC");
        if ($result_all_facilities) {
            while ($row = $result_all_facilities->fetch_assoc()) {
                $facilities[] = $row;
            }
        } else {
            $errors[] = "ไม่สามารถดึงข้อมูลสถานที่ทั้งหมด: " . $conn->error;
        }
    }
}


$equipments = [];
$result_equipments = $conn->query("SELECT equip_id, equip_name, measure FROM equipments ORDER BY equip_name ASC");
if ($result_equipments) {
    while ($row = $result_equipments->fetch_assoc()) {
        $equipments[] = $row;
    }
}


// <<<<< MODIFIED SECTION: POST Request Handling >>>>>
// All POST requests will now explicitly set main_tab to user_requests
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Redirects after POST actions should now include main_tab=user_requests
    // Example for projects_create:
    // header("Location: ?main_tab=user_requests&mode=projects_detail&project_id=" . $new_project_id . "&status=success&message=" . urlencode($success_message));

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
        $prepare_end_time = trim( $_POST['prepare_end_time'] ?? '');
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

        $writed_status = 'ร่างคำร้องขอ'; // Corrected default status for requests
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
        } elseif (!empty($prepare_end_date) && $prepare_end_date > $start_date && (strtotime($prepare_start_date) < strtotime($start_date))) { // Adjusted logic for preparation end date vs. usage start date
            $errors[] = "วันสิ้นสุดของวันเตรียมการต้องไม่เกินวันเริ่มต้นของวันใช้การ.";
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
                        $new_building_id = $conn->insert_id;
                        $success_message = "ส่งคำร้องขอสถานที่สำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=buildings_detail&facility_re_id="
                            . $new_building_id . "&status=success&message=" . urlencode($success_message));
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

        $writed_status = 'ร่างคำร้องขอ'; // Corrected default status for requests
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
                        $new_equip_id = $conn->insert_id;
                        $success_message = "ส่งคำร้องขอใช้อุปกรณ์สำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=equipments_detail&equip_re_id=" . $new_equip_id . "&status=success&message=" . urlencode($success_message)); // Redirect to project detail
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการบันทึกคำร้อง: " . $stmt->error;
                    }
                    $stmt->close();
                }
            }
        } else {
            $errors[] = "ขออภัย คำร้องขอใช้อุปกรณ์ต้องดำเนินการล่วงหน้าอย่างน้อย 3 วันก่อนวันเริ่มต้นการใช้งาน";
        }
    } elseif ($mode == 'projects_edit') {
            $project_id = (int)$_POST['project_id'] ?? 0;
            $project_name = trim($_POST['project_name'] ?? '');
            $start_date = trim($_POST['start_date'] ?? '');
            $end_date = trim($_POST['end_date'] ?? '');
            $project_des = trim($_POST['project_des'] ?? '');
            $attendee = (int)($_POST['attendee'] ?? 0);
            $phone_num = trim($_POST['phone_num'] ?? '');
            $advisor_name = trim($_POST['advisor_name'] ?? '');
            $activity_type_id = (int)($_POST['activity_type_id'] ?? 0);
            $nontri_id = $_SESSION['nontri_id'] ?? '';
            $existing_file_path = trim($_POST['existing_file_path'] ?? '');

            $writed_status = 'ร่างโครงการ'; // Default for draft
            if (isset($_POST['action']) && $_POST['action'] == 'submit_project_edit') {
                $current_status_sql = "SELECT writed_status FROM project WHERE project_id = ? AND nontri_id = ?";
                $stmt_status = $conn->prepare($current_status_sql);
                if ($stmt_status) {
                    $stmt_status->bind_param("is", $project_id, $nontri_id);
                    $stmt_status->execute();
                    $stmt_status->bind_result($current_project_status);
                    $stmt_status->fetch();
                    $stmt_status->close();

                    if ($current_project_status == 'ร่างโครงการ' || $current_project_status == 'ส่งโครงการ') {
                        $writed_status = 'ส่งโครงการ';
                    } else {
                        $writed_status = $current_project_status; // Retain existing status
                    }
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบสถานะโครงการปัจจุบัน: " . $conn->error;
                }
            } elseif (isset($_POST['action']) && $_POST['action'] == 'save_draft_edit') {

                $writed_status = 'ร่างโครงการ';
            }

            if (empty($project_name)) $errors[] = "กรุณากรอกชื่อโครงการ";
            if (empty($start_date)) $errors[] = "กรุณาระบุวันเริ่มต้นโครงการ";
            if (empty($end_date)) $errors[] = "กรุณาระบุวันสิ้นสุดโครงการ";
            if ($attendee <= 0) $errors[] = "กรุณาจำนวนผู้เข้าร่วมให้ถูกต้อง";
            if (empty($phone_num)) $errors[] = "กรุณากรอกหมายเลขโทรศัพท์";
            if (empty($advisor_name)) $errors[] = "กรุณากรอกชื่อที่ปรึกษาโครงการ";
            if ($activity_type_id === 0) $errors[] = "กรุณาเลือกประเภทกิจกรรม";

            $today = date('Y-m-d');
            if (!empty($start_date) && $start_date < $today) {
                $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของโครงการก่อนวันที่ในปัจจุบันได้";
            } elseif (!empty($end_date) && $end_date < $today) {
                $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของโครงการก่อนวันที่ในปัจจุบันได้";
            } elseif (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
                $errors[] = "วันสุดท้ายของโครงการห้ามสิ้นสุดก่อนวันเริ่มต้นของโครงการ.";
            }

            $file_path = $existing_file_path; 
            if (isset($_FILES['files']) && $_FILES['files']['error'] == UPLOAD_ERR_OK) {
                $new_file_path = uploadFile('files', $project_files_upload_dir, $errors);
                if ($new_file_path) {
                    $file_path = $new_file_path;

                    if ($existing_file_path && file_exists($existing_file_path)) {
                        unlink($existing_file_path);
                    }
                }
            } elseif (isset($_FILES['files']) && $_FILES['files']['error'] == UPLOAD_ERR_NO_FILE) {

            } else if (isset($_FILES['files']) && $_FILES['files']['error'] != UPLOAD_ERR_NO_FILE) {
                $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์ " . $_FILES['files']['name'] . ": Error Code " . $_FILES['files']['error'];
            }

            if (empty($errors)) {
                $stmt = $conn->prepare("UPDATE project SET project_name = ?, start_date = ?, end_date = ?, project_des = ?, files = ?, attendee = ?, phone_num = ?, advisor_name = ?, activity_type_id = ?, writed_status = ? WHERE project_id = ? AND nontri_id = ?");
                if (!$stmt) {
                    $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับการอัปเดต: " . $conn->error;
                } else {
                    $stmt->bind_param("sssssissssis",
                        $project_name, $start_date, $end_date, $project_des, $file_path,
                        $attendee, $phone_num, $advisor_name, $activity_type_id, $writed_status,
                        $project_id, $nontri_id
                    );
                    if ($stmt->execute()) {
                        $success_message = "แก้ไขโครงการสำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=projects_detail&project_id=" . $project_id . "&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการบันทึกการแก้ไขโครงการ: " . $stmt->error;
                        // If update fails and a new file was uploaded, delete it to prevent orphaned files
                        if (isset($new_file_path) && $new_file_path && file_exists($new_file_path)) {
                            unlink($new_file_path);
                        }
                    }
                    $stmt->close();
                }
            }
    } elseif ($mode == 'buildings_edit') {
        $facility_re_id = (int)$_POST['facility_re_id'] ?? 0;
        $project_id = (int)$_POST['project_id'] ?? 0;
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
        $nontri_id = $_SESSION['nontri_id'] ?? ''; // ใช้ nontri_id จาก session เพื่อตรวจสอบสิทธิ์

        $writed_status = 'ร่างคำร้องขอ'; // Corrected default status for requests
        if (isset($_POST['action']) && $_POST['action'] == 'submit_building_edit') {
            // Fetch current status to avoid overriding approved/rejected status
            $current_status_sql = "SELECT fr.writed_status FROM facilities_requests fr JOIN project p ON fr.project_id = p.project_id WHERE fr.facility_re_id = ? AND p.nontri_id = ?";
            $stmt_status = $conn->prepare($current_status_sql);
            if ($stmt_status) {
                $stmt_status->bind_param("is", $facility_re_id, $nontri_id);
                $stmt_status->execute();
                $stmt_status->bind_result($current_request_status);
                $stmt_status->fetch();
                $stmt_status->close();
                // Only change status to 'ส่งคำร้องขอ' if it was a draft or pending submission
                if ($current_request_status == 'ร่างคำร้องขอ' || $current_request_status == 'ส่งคำร้องขอ') {
                    $writed_status = 'ส่งคำร้องขอ';
                } else {
                    $writed_status = $current_request_status; // Retain existing status (e.g., 'อนุมัติ', 'ไม่อนุมัติ')
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบสถานะคำร้องขอสถานที่ปัจจุบัน: " . $conn->error;
            }
        } elseif (isset($_POST['action']) && $_POST['action'] == 'save_draft_building_edit') {
            $writed_status = 'ร่างคำร้องขอ'; // Keep as draft if explicitly saving draft
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
        } elseif (!empty($prepare_end_date) && $prepare_end_date > $start_date && (strtotime($prepare_start_date) < strtotime($start_date))) {
             $errors[] = "วันสิ้นสุดของวันเตรียมการต้องไม่เกินวันเริ่มต้นของวันใช้การ.";
        } elseif (!empty($prepare_start_time) && !empty($prepare_end_time) && $prepare_start_time > $prepare_end_time) {
            $errors[] = "เวลาสิ้นสุดของวันเตรียมการห้ามสิ้นสุดก่อนเวลาเริ่มต้นของวันเตรียมการ";
        } elseif (!empty($start_time) && !empty($end_time) && $start_time > $end_time) {
            $errors[] = "เวลาสิ้นสุดของวันใช้การห้ามสิ้นสุดก่อนเวลาเริ่มต้นของวันใช้การ";
        }

        $approval_day_ts = strtotime('+3 days');
        $pre_start_ts = strtotime($prepare_start_date);
        $start_ts    = strtotime($start_date);

        if ($pre_start_ts >= $approval_day_ts && $start_ts >= $approval_day_ts) {
            if (empty($errors)) {
                // Ensure the project belongs to the current user before updating the request
                $check_project_owner_sql = "SELECT nontri_id FROM project WHERE project_id = ?";
                $stmt_proj_owner = $conn->prepare($check_project_owner_sql);
                $stmt_proj_owner->bind_param("i", $project_id);
                $stmt_proj_owner->execute();
                $stmt_proj_owner->bind_result($project_owner_nontri_id);
                $stmt_proj_owner->fetch();
                $stmt_proj_owner->close();

                if ($project_owner_nontri_id === $nontri_id) {
                    $stmt = $conn->prepare("UPDATE facilities_requests SET
                        prepare_start_time = ?, prepare_end_time = ?, prepare_start_date = ?, prepare_end_date = ?,
                        start_time = ?, end_time = ?, start_date = ?, end_date = ?, agree = ?, facility_id = ?,
                        project_id = ?, writed_status = ? WHERE facility_re_id = ?");

                    if (!$stmt) {
                        $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับการอัปเดต: " . $conn->error;
                    } else {
                        $stmt->bind_param("ssssssssiiisi",
                            $prepare_start_time, $prepare_end_time, $prepare_start_date, $prepare_end_date,
                            $start_time, $end_time, $start_date, $end_date,
                            $agree, $facility_id, $project_id, $writed_status, $facility_re_id
                        );

                        if ($stmt->execute()) {
                            $success_message = "แก้ไขคำร้องขอสถานที่สำเร็จแล้ว!";
                            header("Location: ?main_tab=user_requests&mode=buildings_detail&facility_re_id="
                                . $facility_re_id . "&status=success&message=" . urlencode($success_message));
                            exit();
                        } else {
                            $errors[] = "เกิดข้อผิดพลาดในการบันทึกการแก้ไขคำร้อง: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                } else {
                    $errors[] = "คุณไม่มีสิทธิ์แก้ไขคำร้องขอสถานที่นี้ (ไม่เป็นเจ้าของโครงการที่เกี่ยวข้อง).";
                }
            }
        } else {
            $errors[] = "ขออภัย คำร้องขอใช้อาคารสถานที่ต้องดำเนินการล่วงหน้าอย่างน้อย 3 วันก่อนวันเริ่มต้นการใช้งาน";
        }
    } elseif ($mode == 'equipments_edit') {
        $equip_re_id = (int)$_POST['equip_re_id'] ?? 0;
        $project_id = (int)$_POST['project_id'] ?? 0;
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $agree = isset($_POST['agree']) ? 1 : 0;
        $transport = isset($_POST['transport']) ? 1 : 0;
        $quantity = (int)($_POST['quantity'] ?? 0);
        $equip_id = (int)($_POST['equip_id']) ?? 0;
        $facility_id = (int)($_POST['facility_id']) ?? 0; // Optional
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        $writed_status = 'ร่างคำร้องขอ'; // Corrected default status for requests
        if (isset($_POST['action']) && $_POST['action'] == 'submit_equipment_edit') {
            // Fetch current status to avoid overriding approved/rejected status
            $current_status_sql = "SELECT er.writed_status FROM equipments_requests er JOIN project p ON er.project_id = p.project_id WHERE er.equip_re_id = ? AND p.nontri_id = ?";
            $stmt_status = $conn->prepare($current_status_sql);
            if ($stmt_status) {
                $stmt_status->bind_param("is", $equip_re_id, $nontri_id);
                $stmt_status->execute();
                $stmt_status->bind_result($current_request_status);
                $stmt_status->fetch();
                $stmt_status->close();
                // Only change status to 'ส่งคำร้องขอ' if it was a draft or pending submission
                if ($current_request_status == 'ร่างคำร้องขอ' || $current_request_status == 'ส่งคำร้องขอ') {
                    $writed_status = 'ส่งคำร้องขอ';
                } else {
                    $writed_status = $current_request_status; // Retain existing status
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบสถานะคำร้องขออุปกรณ์ปัจจุบัน: " . $conn->error;
            }
        } elseif (isset($_POST['action']) && $_POST['action'] == 'save_draft_equipment_edit') {
            $writed_status = 'ร่างคำร้องขอ'; // Keep as draft if explicitly saving draft
        }

        if (empty($start_date)) $errors[] = "กรุณากรอกวันที่เริ่มต้นใช้การ";
        if (empty($end_date)) $errors[] = "กรุณากรอกวันที่สิ้นสุดใช้การ";
        if ($quantity <= 0) $errors[] = "กรุณาระบุจำนวนอุปกรณ์ที่ต้องการขอใช้";
        if (empty($equip_id)) $errors[] = "กรุณาเลือกอุปกรณ์ที่ต้องการขอใช้";
        if (empty($project_id)) $errors[] = "กรุณาเลือกโครงกาของท่านที่ต้องการขอใช้อุปกรณ์";

        $today = date('Y-m-d');
        if (!empty($start_date) && $start_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($end_date) && $end_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
            $errors[] = "วันสุดท้ายของการขอใช้อุปกรณ์ห้ามสิ้นสุดก่อนวันเริ่มต้นขอใช้อุปกรณ์.";
        }

        $approval_day_ts = strtotime('+3 days');
        $start_ts    = strtotime($start_date);

        // Allow editing if status is 'อนุมัติ' or 'ไม่อนุมัติ' or meets the 3-day approval criteria for 'ร่างคำร้องขอ' or 'ส่งคำร้องขอ'
        if ($writed_status == 'อนุมัติ' || $writed_status == 'ไม่อนุมัติ' || ($start_ts >= $approval_day_ts)) {
            if (empty($errors)) {
                // Ensure the project belongs to the current user before updating the request
                $check_project_owner_sql = "SELECT nontri_id FROM project WHERE project_id = ?";
                $stmt_proj_owner = $conn->prepare($check_project_owner_sql);
                $stmt_proj_owner->bind_param("i", $project_id);
                $stmt_proj_owner->execute();
                $stmt_proj_owner->bind_result($project_owner_nontri_id);
                $stmt_proj_owner->fetch();
                $stmt_proj_owner->close();

                if ($project_owner_nontri_id === $nontri_id) {
                    $stmt = $conn->prepare("UPDATE equipments_requests SET
                        start_date = ?, end_date = ?, agree = ?, transport = ?, quantity = ?,
                        equip_id = ?, facility_id = ?, project_id = ?, writed_status = ? WHERE equip_re_id = ?");

                    if (!$stmt) {
                        $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับการอัปเดต: " . $conn->error;
                    } else {
                        $stmt->bind_param("ssiiiiiisi",
                            $start_date, $end_date, $agree, $transport, $quantity,
                            $equip_id, $facility_id, $project_id, $writed_status, $equip_re_id
                        );

                        if ($stmt->execute()) {
                            $success_message = "แก้ไขคำร้องขอใช้อุปกรณ์สำเร็จแล้ว!";
                            header("Location: ?main_tab=user_requests&mode=equipments_detail&equip_re_id="
                                . $equip_re_id . "&status=success&message=" . urlencode($success_message));
                            exit();
                        } else {
                            $errors[] = "เกิดข้อผิดพลาดในการบันทึกการแก้ไขคำร้อง: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                } else {
                    $errors[] = "คุณไม่มีสิทธิ์แก้ไขคำร้องขออุปกรณ์นี้ (ไม่เป็นเจ้าของโครงการที่เกี่ยวข้อง).";
                }
            }
        } else {
            $errors[] = "ขออภัย คำร้องขอใช้อุปกรณ์ต้องดำเนินการล่วงหน้าอย่างน้อย 3 วันก่อนวันเริ่มต้นการใช้งาน";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_project') {
        $project_id = (int)$_POST['project_id'] ?? 0;
        $file_to_delete = $_POST['file_to_delete'] ?? '';
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        if ($project_id > 0) {
            // Check current status - only allow deletion if 'ร่างโครงการ'
            $current_status_sql = "SELECT writed_status FROM project WHERE project_id = ? AND nontri_id = ?";
            $stmt_status = $conn->prepare($current_status_sql);
            $stmt_status->bind_param("is", $project_id, $nontri_id);
            $stmt_status->execute();
            $stmt_status->bind_result($current_project_status);
            $stmt_status->fetch();
            $stmt_status->close();

            if ($current_project_status === 'ร่างโครงการ') { // Only allow deletion for drafts
                // Start transaction to ensure data integrity
                $conn->begin_transaction();
                try {
                    // 1. Delete associated facilities_requests for this project
                    $stmt_del_fr = $conn->prepare("DELETE FROM facilities_requests WHERE project_id = ?");
                    $stmt_del_fr->bind_param("i", $project_id);
                    $stmt_del_fr->execute();
                    $stmt_del_fr->close();

                    // 2. Delete associated equipments_requests for this project
                    $stmt_del_er = $conn->prepare("DELETE FROM equipments_requests WHERE project_id = ?");
                    $stmt_del_er->bind_param("i", $project_id);
                    $stmt_del_er->execute();
                    $stmt_del_er->close();

                    // 3. Delete the project itself, ensuring it belongs to the current user
                    $stmt_del_p = $conn->prepare("DELETE FROM project WHERE project_id = ? AND nontri_id = ?");
                    $stmt_del_p->bind_param("is", $project_id, $nontri_id);
                    if ($stmt_del_p->execute()) {
                        if ($stmt_del_p->affected_rows > 0) {
                            // 4. Delete the associated file from the server
                            if ($file_to_delete && file_exists($file_to_delete)) {
                                unlink($file_to_delete);
                            }
                            $conn->commit();
                            $success_message = "ลบโครงการสำเร็จแล้ว!";
                            header("Location: ?main_tab=user_requests&mode=projects_list&status=success&message=" . urlencode($success_message));
                            exit();
                        } else {
                            $errors[] = "ไม่พบโครงการที่คุณต้องการลบ หรือคุณไม่มีสิทธิ์ลบโครงการนี้.";
                            $conn->rollback();
                        }
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการลบโครงการ: " . $stmt_del_p->error;
                        $conn->rollback();
                    }
                    $stmt_del_p->close();

                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                    $errors[] = "เกิดข้อผิดพลาดในการลบโครงการและข้อมูลที่เกี่ยวข้อง: " . $e->getMessage();
                }
            } else {
                $errors[] = "ไม่สามารถลบโครงการที่ไม่ใช่ร่างโครงการได้ หากต้องการยกเลิกโปรดใช้ปุ่ม 'ยกเลิกโครงการ'.";
            }
        } else {
            $errors[] = "ไม่พบรหัสโครงการที่ถูกต้อง.";
        }

    } elseif (isset($_POST['action']) && $_POST['action'] == 'cancel_project') { // MODIFIED: Changed from delete to update status
        $project_id = (int)$_POST['project_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        if ($project_id > 0) {
            // Start transaction to ensure data integrity
            $conn->begin_transaction();
            try {
                // Get current project status to ensure it's not already completed or started
                $current_status_sql = "SELECT writed_status FROM project WHERE project_id = ? AND nontri_id = ?";
                $stmt_status = $conn->prepare($current_status_sql);
                $stmt_status->bind_param("is", $project_id, $nontri_id);
                $stmt_status->execute();
                $stmt_status->bind_result($current_project_status);
                $stmt_status->fetch();
                $stmt_status->close();

                if ($current_project_status !== 'เริ่มดำเนินการ' && $current_project_status !== 'สิ้นสุดโครงการ' && $current_project_status !== 'ยกเลิกโครงการ') {
                    // 1. Update associated facilities_requests for this project
                    // Only update requests that are not yet approved, rejected, started, or completed
                    $stmt_can_fr = $conn->prepare("UPDATE facilities_requests SET writed_status = 'ยกเลิกคำร้องขอ', approve = 'ยกเลิก' WHERE project_id = ? AND writed_status NOT IN ('เริ่มดำเนินการ', 'สิ้นสุดดำเนินการ', 'ยกเลิกคำร้องขอ') AND approve IS NULL");
                    $stmt_can_fr->bind_param("i", $project_id);
                    $stmt_can_fr->execute();
                    $stmt_can_fr->close();

                    // 2. Update associated equipments_requests for this project
                    // Only update requests that are not yet approved, rejected, started, or completed
                    $stmt_can_er = $conn->prepare("UPDATE equipments_requests SET writed_status = 'ยกเลิกคำร้องขอ', approve = 'ยกเลิก' WHERE project_id = ? AND writed_status NOT IN ('เริ่มดำเนินการ', 'สิ้นสุดดำเนินการ', 'ยกเลิกคำร้องขอ') AND approve IS NULL");
                    $stmt_can_er->bind_param("i", $project_id);
                    $stmt_can_er->execute();
                    $stmt_can_er->close();

                    // 3. Update the project itself, ensuring it belongs to the current user
                    $stmt_can_p = $conn->prepare("UPDATE project SET writed_status = 'ยกเลิกโครงการ' WHERE project_id = ? AND nontri_id = ?");
                    $stmt_can_p->bind_param("is", $project_id, $nontri_id);
                    if ($stmt_can_p->execute()) {
                        if ($stmt_can_p->affected_rows > 0) {
                            $conn->commit();
                            $success_message = "ยกเลิกโครงการสำเร็จแล้ว!";
                            header("Location: ?main_tab=user_requests&mode=projects_list&status=success&message=" . urlencode($success_message));
                            exit();
                        } else {
                            $errors[] = "ไม่พบโครงการที่คุณต้องการยกเลิก หรือคุณไม่มีสิทธิ์ยกเลิกโครงการนี้.";
                            $conn->rollback();
                        }
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการยกเลิกโครงการ: " . $stmt_can_p->error;
                        $conn->rollback();
                    }
                    $stmt_can_p->close();
                } else {
                    $errors[] = "ไม่สามารถยกเลิกโครงการที่ 'เริ่มดำเนินการ', 'สิ้นสุดโครงการ' หรือ 'ยกเลิกโครงการ' แล้วได้.";
                }

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $errors[] = "เกิดข้อผิดพลาดในการยกเลิกโครงการและข้อมูลที่เกี่ยวข้อง: " . $e->getMessage();
            }
        } else {
            $errors[] = "ไม่พบรหัสโครงการที่ถูกต้อง.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_building_request') {
        $facility_re_id = (int)$_POST['facility_re_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? ''; // For security, ensure the user owns the project associated with this request

        if ($facility_re_id > 0) {
            // Verify ownership and status (only allow deletion if draft)
            $check_sql = "SELECT fr.writed_status, p.nontri_id FROM facilities_requests fr JOIN project p ON fr.project_id = p.project_id WHERE fr.facility_re_id = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("i", $facility_re_id);
            $stmt_check->execute();
            $stmt_check->bind_result($current_status, $owner_nontri_id);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($owner_nontri_id === $nontri_id && $current_status === 'ร่างคำร้องขอ') {
                $stmt = $conn->prepare("DELETE FROM facilities_requests WHERE facility_re_id = ?");
                $stmt->bind_param("i", $facility_re_id);
                if ($stmt->execute()) {
                    $success_message = "ลบคำร้องขอสถานที่สำเร็จแล้ว!";
                    header("Location: ?main_tab=user_requests&mode=buildings_list&status=success&message=" . urlencode($success_message));
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการลบคำร้องขอสถานที่: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "คุณไม่มีสิทธิ์ลบคำร้องขอสถานที่นี้ หรือคำร้องขอไม่ใช่ร่างคำร้อง.";
            }
        } else {
            $errors[] = "ไม่พบรหัสคำร้องขอสถานที่ที่ถูกต้อง.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'cancel_building_request') { // NEW ACTION
        $facility_re_id = (int)$_POST['facility_re_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        if ($facility_re_id > 0) {
            // Verify ownership and status
            $check_sql = "SELECT fr.writed_status, fr.approve, p.nontri_id FROM facilities_requests fr JOIN project p ON fr.project_id = p.project_id WHERE fr.facility_re_id = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("i", $facility_re_id);
            $stmt_check->execute();
            $stmt_check->bind_result($current_status, $current_approve, $owner_nontri_id);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($owner_nontri_id === $nontri_id) {
                // Only allow cancellation if not already started, completed, approved, rejected, or cancelled
                if ($current_status !== 'เริ่มดำเนินการ' && $current_status !== 'สิ้นสุดดำเนินการ' && $current_approve !== 'อนุมัติ' && $current_approve !== 'ไม่อนุมัติ' && $current_status !== 'ยกเลิกคำร้องขอ') {
                    $stmt = $conn->prepare("UPDATE facilities_requests SET writed_status = 'ยกเลิกคำร้องขอ', approve = 'ยกเลิก' WHERE facility_re_id = ?");
                    $stmt->bind_param("i", $facility_re_id);
                    if ($stmt->execute()) {
                        $success_message = "ยกเลิกคำร้องขอสถานที่สำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=buildings_list&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการยกเลิกคำร้องขอสถานที่: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errors[] = "ไม่สามารถยกเลิกคำร้องที่ 'เริ่มดำเนินการ', 'สิ้นสุดดำเนินการ', 'อนุมัติ', 'ไม่อนุมัติ' หรือ 'ยกเลิก' แล้วได้";
                }
            } else {
                $errors[] = "คุณไม่มีสิทธิ์ยกเลิกคำร้องขอสถานที่นี้.";
            }
        } else {
            $errors[] = "ไม่พบรหัสคำร้องขอสถานที่ที่ถูกต้อง.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_equipment_request') {
        $equip_re_id = (int)$_POST['equip_re_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? ''; // For security

        if ($equip_re_id > 0) {
            // Verify ownership and status (only allow deletion if draft)
            $check_sql = "SELECT er.writed_status, p.nontri_id FROM equipments_requests er JOIN project p ON er.project_id = p.project_id WHERE er.equip_re_id = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("i", $equip_re_id);
            $stmt_check->execute();
            $stmt_check->bind_result($current_status, $owner_nontri_id);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($owner_nontri_id === $nontri_id && $current_status === 'ร่างคำร้องขอ') {
                $stmt = $conn->prepare("DELETE FROM equipments_requests WHERE equip_re_id = ?");
                $stmt->bind_param("i", $equip_re_id);
                if ($stmt->execute()) {
                    $success_message = "ลบคำร้องขออุปกรณ์สำเร็จแล้ว!";
                    header("Location: ?main_tab=user_requests&mode=equipments_list&status=success&message=" . urlencode($success_message));
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการลบคำร้องขออุปกรณ์: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errors[] = "คุณไม่มีสิทธิ์ลบคำร้องขออุปกรณ์นี้ หรือคำร้องขอไม่ใช่ร่างคำร้อง.";
            }
        } else {
            $errors[] = "ไม่พบรหัสคำร้องขออุปกรณ์ที่ถูกต้อง.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'cancel_equipment_request') { // NEW ACTION
        $equip_re_id = (int)$_POST['equip_re_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        if ($equip_re_id > 0) {
            // Verify ownership and status
            $check_sql = "SELECT er.writed_status, er.approve, p.nontri_id FROM equipments_requests er JOIN project p ON er.project_id = p.project_id WHERE er.equip_re_id = ?";
            $stmt_check = $conn->prepare($check_sql);
            $stmt_check->bind_param("i", $equip_re_id);
            $stmt_check->execute();
            $stmt_check->bind_result($current_status, $current_approve, $owner_nontri_id);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($owner_nontri_id === $nontri_id) {
                // Only allow cancellation if not already started, completed, approved, rejected, or cancelled
                if ($current_status !== 'เริ่มดำเนินการ' && $current_status !== 'สิ้นสุดดำเนินการ' && $current_approve !== 'อนุมัติ' && $current_approve !== 'ไม่อนุมัติ' && $current_status !== 'ยกเลิกคำร้องขอ') {
                    $stmt = $conn->prepare("UPDATE equipments_requests SET writed_status = 'ยกเลิกคำร้องขอ', approve = 'ยกเลิก' WHERE equip_re_id = ?");
                    $stmt->bind_param("i", $equip_re_id);
                    if ($stmt->execute()) {
                        $success_message = "ยกเลิกคำร้องขออุปกรณ์สำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=equipments_list&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการยกเลิกคำร้องขออุปกรณ์: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errors[] = "ไม่สามารถยกเลิกคำร้องที่ 'เริ่มดำเนินการ', 'สิ้นสุดดำเนินการ', 'อนุมัติ', 'ไม่อนุมัติ' หรือ 'ยกเลิก' แล้วได้";
                }
            } else {
                $errors[] = "คุณไม่มีสิทธิ์ยกเลิกคำร้องขออุปกรณ์นี้.";
            }
        } else {
            $errors[] = "ไม่พบรหัสคำร้องขออุปกรณ์ที่ถูกต้อง.";
        }
    }
}
// <<<<< END MODIFIED SECTION: POST Request Handling >>>>>


// <<<<< MODIFIED SECTION: Data Fetching for user_requests tab >>>>>
// Only fetch this data if we are in the 'user_requests' main_tab
if ($main_tab == 'user_requests') {
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $search_param = '%' . $search_query . '%';
    $sort_filter = $_GET['sort_filter'] ?? 'date_desc';

    // Initialize these for list views
    $items_per_page = 4; // Default items per page
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $items_per_page;
    $total_items = 0; // Default total items
    $total_pages = 1; // Default total pages

    try {
        if ($mode == 'projects_list') {
            $sorting = getSortingClauses('projects_list', $sort_filter);
            $base_where = " WHERE p.nontri_id = ? AND p.project_name LIKE ?";

            // --- COUNT QUERY ---
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

            // --- DATA QUERY ---
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
                $detail_item['start_date_compare'] = strtotime($detail_item['start_date']); 
                $limited_date = strtotime('+7 days'); 
                $stmt_detail->close();

                if (!$detail_item) {
                    $errors[] = "ไม่พบโครงการที่คุณร้องขอ หรือคุณไม่มีสิทธิ์เข้าถึงโครงการนี้.";
                    $mode = 'projects_list';
                } else {
                    // Fetch facilities requests for this project
                    $project_facility_requests = [];
                    $sql_fr = "SELECT fr.facility_re_id, fr.request_date, fr.writed_status, fr.start_date, fr.end_date, fr.prepare_start_date, fr.prepare_end_date, fr.start_time, fr.end_time, fr.prepare_start_time, fr.prepare_end_time, f.facility_name, fr.approve
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
                    $sql_er = "SELECT er.equip_re_id, er.request_date, er.writed_status, er.start_date, er.end_date, er.quantity, e.equip_name, e.measure, er.approve
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
            
            // สร้าง Subquery filter โดยใช้ EXISTS
            $subquery_filter = '';
            if ($sorting['where_param_value'] !== null) {
                // แก้ไข: ใช้วิธีที่ถูกต้องในการลบ " AND " ที่นำหน้าออก
                $condition = preg_replace('/^ AND /', '', $sorting['where_sql']);
                $subquery_filter = " AND EXISTS (SELECT 1 FROM facilities_requests fr WHERE fr.project_id = p.project_id AND {$condition})";
            }

            $base_where = " WHERE p.nontri_id = ? AND p.project_name LIKE ?";

            // --- COUNT QUERY ---
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

            // --- DATA QUERY ---
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

            // ส่วนที่เหลือในการดึง requests ของแต่ละ project ไม่ต้องแก้ไข
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
                                fr.writed_status, fr.request_date, p.nontri_id, CONCAT(u.user_THname,' ', u.user_THsur) AS user_name,
                                p.project_name, f.facility_name, fr.approve, fr.approve_date, fr.approve_detail, CONCAT(s.staff_THname, ' ', s.staff_THsur) AS staff_name
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
                $detail_item['start_date_compare'] = strtotime($detail_item['start_date']); // แปลงเป็น timestamp เพื่อเปรียบเทียบ
                $limited_date = strtotime('+3 days');
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
                                fr.writed_status, fr.request_date, p.nontri_id, CONCAT(u.user_THname,' ', u.user_THsur) AS user_name,
                                p.project_name, f.facility_id, f.facility_name
                            FROM facilities_requests fr
                            JOIN project p ON fr.project_id = p.project_id
                            JOIN user u ON p.nontri_id = u.nontri_id
                            JOIN facilities f ON fr.facility_id = f.facility_id
                            WHERE fr.facility_re_id = ? AND p.nontri_id = ?"; // Ensure user owns the project associated with request
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
                // แก้ไข: ใช้วิธีที่ถูกต้องในการลบ " AND " ที่นำหน้าออก
                $condition = preg_replace('/^ AND /', '', $sorting['where_sql']);
                $subquery_filter = " AND EXISTS (SELECT 1 FROM equipments_requests er WHERE er.project_id = p.project_id AND {$condition})";
            }

            $base_where = " WHERE p.nontri_id = ? AND p.project_name LIKE ?";

            // --- COUNT QUERY ---
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
            
            // --- DATA QUERY ---
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
                                er.writed_status, er.request_date, p.nontri_id, CONCAT(u.user_THname,' ', u.user_THsur) AS user_name,
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
                            $detail_item['start_date_compare'] = strtotime($detail_item['start_date']);
                            $limited_date = strtotime('+3 days');
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
                                er.writed_status, er.request_date, p.nontri_id, CONCAT(u.user_THname,' ', u.user_THsur) AS user_name,
                                p.project_name, e.equip_id, e.equip_name, f.facility_id, f.facility_name, e.measure, er.agree, er.approve, er.approve_date
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
                // Load facilities for the selected project in edit mode for dropdown
                if (isset($detail_item['project_id'])) {
                    $projectIdForEdit = $detail_item['project_id'];
                    $sql_facilities_for_edit = "
                        SELECT DISTINCT f.facility_id, f.facility_name
                        FROM facilities f
                        JOIN facilities_requests fr ON f.facility_id = fr.facility_id
                        JOIN project p ON fr.project_id = p.project_id
                        WHERE fr.project_id = ? AND p.nontri_id = ?
                        ORDER BY f.facility_name ASC";
                    $stmt_facilities_for_edit = $conn->prepare($sql_facilities_for_edit);
                    if ($stmt_facilities_for_edit) {
                        $stmt_facilities_for_edit->bind_param("is", $projectIdForEdit, $user_id);
                        $stmt_facilities_for_edit->execute();
                        $result_facilities_edit = $stmt_facilities_for_edit->get_result();
                        while ($row = $result_facilities_edit->fetch_assoc()) {
                            $facilities[] = $row; // Populate the facilities array for the dropdown
                        }
                        $stmt_facilities_for_edit->close();
                    } else {
                        $errors[] = "ไม่สามารถเตรียมคำสั่ง SQL สำหรับดึงสถานที่สำหรับแก้ไข: " . $conn->error;
                    }
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

$conn->close();

$modal_status = $_GET['status'] ?? '';
$modal_message = $_GET['message'] ?? '';
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'?>
</head>
<body>
    <nav class="navbar navbar-dark navigator">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3">
                <a href="?main_tab=user_dashboard"> <!-- <<<<< MODIFIED LINK >>>>> -->
                    <img src="./images/logo.png" class="img-fluid logo" alt="Logo">
                </a>
                <div class="d-flex flex-column">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4">ระบบขอใช้อุปกรณ์และสถานที่</span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5">KU FTD</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mx-auto">
                <!-- <<<<< MODIFIED NAV LINKS >>>>> -->
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="user-project-page.php">ข้อมูลคำร้อง</a>
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="user-data_view-page.php">ตรวจสอบอุปกรณ์และสถานที่</a>
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

        <?php if ($main_tab == 'user_requests') : ?>
            <?php if ($mode == 'projects_list') : ?> 
                <h1 class="mb-3 text-center">ข้อมูลโครงการของผู้ใช้</h1>
            <?php elseif ($mode == 'buildings_list') : ?>
                <h1 class="mb-3 text-center">ข้อมูลคำร้องขอใช้อาคารและสถานที่</h1>
            <?php elseif ($mode == 'equipments_list') : ?>
                <h1 class="mb-3 text-center">ข้อมูลคำร้องขอใช้อุปกรณ์</h1>
            <?php endif; ?>
        <?php else: ?>
            <h1 class="mb-3 text-center">ภาพรวมคำร้องของผู้ใช้</h1>
        <?php endif; ?>

        <div class="row mb-3 align-items-center">
            <div class="col-md-6">
                <?php if ($main_tab == 'user_dashboard' || ($main_tab == 'user_requests' && in_array($mode, ['projects_list', 'buildings_list', 'equipments_list']))): ?>
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($main_tab == 'user_dashboard') ? 'active' : ''; ?>" aria-current="page" href="?main_tab=user_dashboard">
                                <i class="bi bi-folder"></i> ภาพรวม
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($main_tab == 'user_requests' && ($mode == 'projects_list' || $mode == 'projects_create' || $mode == 'projects_detail' || $mode == 'projects_edit')) ? 'active' : ''; ?>" href="?main_tab=user_requests&mode=projects_list">
                                <i class="bi bi-folder"></i> โครงการ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($main_tab == 'user_requests' && ($mode == 'buildings_list' || $mode == 'buildings_create' || $mode == 'buildings_detail' || $mode == 'buildings_edit')) ? 'active' : ''; ?>" href="?main_tab=user_requests&mode=buildings_list">
                                <i class="bi bi-building"></i> คำร้องขออาคารและสถานที่ทั้งหมด
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($main_tab == 'user_requests' && ($mode == 'equipments_list' || $mode == 'equipments_create' || $mode == 'equipments_detail' || $mode == 'equipments_edit')) ? 'active' : ''; ?>" href="?main_tab=user_requests&mode=equipments_list">
                                <i class="bi bi-tools"></i> คำร้องขออุปกรณ์ทั้งหมด
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
            <?php if ($main_tab == 'user_requests' && in_array($mode, ['projects_list', 'buildings_list', 'equipments_list'])): ?>
                <div class="col-md-6">
                    <form class="d-flex align-items-center" action="" method="GET">
                        <input type="hidden" name="main_tab" value="user_requests">
                        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">

                        <select name="sort_filter" class="form-select me-2" onchange="this.form.submit()" style="width: auto;">
                            <optgroup label="เรียงตามวันที่">
                                <option value="date_desc" <?php echo (($_GET['sort_filter'] ?? 'date_desc') == 'date_desc') ? 'selected' : ''; ?>>ใหม่สุดไปเก่าสุด</option>
                                <option value="date_asc" <?php echo (($_GET['sort_filter'] ?? '') == 'date_asc') ? 'selected' : ''; ?>>เก่าสุดไปใหม่สุด</option>
                            </optgroup>
                            <optgroup label="กรองตามสถานะ">
                                <option value="all" <?php echo (($_GET['sort_filter'] ?? '') == 'all') ? 'selected' : ''; ?>>แสดงทุกสถานะ</option>
                                <?php if ($mode == 'projects_list'): ?>
                                    <option value="ร่างโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'ร่างโครงการ') ? 'selected' : ''; ?>>ร่างโครงการ</option>
                                    <option value="ส่งโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'ส่งโครงการ') ? 'selected' : ''; ?>>ส่งโครงการ</option>
                                    <option value="เริ่มดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'เริ่มดำเนินการ') ? 'selected' : ''; ?>>เริ่มดำเนินการ</option>
                                    <option value="สิ้นสุดโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'สิ้นสุดโครงการ') ? 'selected' : ''; ?>>สิ้นสุดโครงการ</option>
                                    <option value="ยกเลิกโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'ยกเลิกโครงการ') ? 'selected' : ''; ?>>ยกเลิกโครงการ</option> <!-- NEW -->
                                <?php elseif ($mode == 'buildings_list' || $mode == 'equipments_list'): ?>
                                    <option value="ร่างคำร้องขอ" <?php echo (($_GET['sort_filter'] ?? '') == 'ร่างคำร้องขอ') ? 'selected' : ''; ?>>ร่างคำร้องขอ</option>
                                    <option value="ส่งคำร้องขอ" <?php echo (($_GET['sort_filter'] ?? '') == 'ส่งคำร้องขอ') ? 'selected' : ''; ?>>ส่งคำร้องขอ</option>
                                    <option value="อนุมัติ" <?php echo (($_GET['sort_filter'] ?? '') == 'อนุมัติ') ? 'selected' : ''; ?>>อนุมัติ</option>
                                    <option value="ไม่อนุมัติ" <?php echo (($_GET['sort_filter'] ?? '') == 'ไม่อนุมัติ') ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                                    <option value="เริ่มดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'เริ่มดำเนินการ') ? 'selected' : ''; ?>>เริ่มดำเนินการ</option>
                                    <option value="สิ้นสุดดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'สิ้นสุดดำเนินการ') ? 'selected' : ''; ?>>สิ้นสุดดำเนินการ</option>
                                    <option value="ยกเลิกคำร้องขอ" <?php echo (($_GET['sort_filter'] ?? '') == 'ยกเลิกคำร้องขอ') ? 'selected' : ''; ?>>ยกเลิกคำร้องขอ</option> <!-- NEW -->
                                <?php endif; ?>
                            </optgroup>
                        </select>

                        <!-- Search Input and Buttons -->
                        <input class="form-control me-2" type="search" placeholder="ค้นหา..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-outline-success" type="submit">ค้นหา</button>
                        <?php if (!empty($search_query) || !empty($_GET['sort_filter'])): ?>
                            <a href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>" class="btn btn-outline-secondary ms-2">ล้าง</a>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($main_tab == 'user_dashboard'): ?>
		    <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
                <div class="col">
                    <div class="card text-white bg-primary mb-3 h-100">
                        <div class="card-header"><i class="bi bi-folder-fill me-2"></i>โครงการ</div>
                        <div class="card-body">
                            <h5 class="card-title">โครงการของคุณ</h5>
                            <h2 class="card-text fs-1"><?php echo $dashboard_data['project_counts']['total']; ?></h2>
                        </div>
                        <div class="card-footer">
                            <a href="?main_tab=user_requests&mode=projects_list" class="stretched-link text-white text-decoration-none"> ดูโครงการทั้งหมด <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-white bg-success mb-3 h-100">
                        <div class="card-header"><i class="bi bi-building-fill me-2"></i>คำร้องขอใช้อาคารสถานที่</div>
                        <div class="card-body">
                            <h5 class="card-title">คำร้องขอใช้อาคารสถานที่ของคุณ</h5>
                            <h2 class="card-text fs-1"><?php echo $dashboard_data['facilities_request_counts']['total']; ?></h2>
                        </div>
                        <div class="card-footer">
                            <a href="?main_tab=user_requests&mode=buildings_list" class="stretched-link text-white text-decoration-none"> ดูคำร้องขอใช้อาคารสถานที่ทั้งหมด <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-dark bg-warning mb-3 h-100">
                        <div class="card-header"><i class="bi bi-tools me-2"></i>คำร้องขอใช้อุปกรณ์</div>
                        <div class="card-body">
                            <h5 class="card-title">คำร้องขอใช้อุปกรณ์ของคุณ</h5>
                            <h2 class="card-text fs-1"><?php echo $dashboard_data['equipments_request_counts']['total']; ?></h2>
                        </div>
                        <div class="card-footer">
                            <a href="?main_tab=user_requests&mode=equipments_list" class="stretched-link text-dark text-decoration-none"> ดูคำร้องขอใช้อุปกรณ์ทั้งหมด <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

                <div class="row g-4 mb-4">
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
                                            $detail_link = '?main_tab=user_requests&mode=projects_detail&project_id=' . $req['id'];
                                        } elseif ($req['type'] == 'คำร้องขอสถานที่') {
                                            $detail_link = '?main_tab=user_requests&mode=buildings_detail&facility_re_id=' . $req['id'];
                                        } elseif ($req['type'] == 'คำร้องขออุปกรณ์') {
                                            $detail_link = '?main_tab=user_requests&mode=equipments_detail&equip_re_id=' . $req['id'];
                                        }
                                    ?>
                                    <li class="list-group-item activity-item">
                                        <?php if ($detail_link): ?>
                                            <a href="<?php echo $detail_link; ?>" class="d-flex w-100 justify-content-between align-items-center stretched-link text-decoration-none text-dark">
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
                            <h4 class="mb-3">การดำเนินการล่าสุด <span class="badge bg-secondary"><?php echo count($dashboard_data['recent_activity']); ?></span></h4>
                            <?php if (empty($dashboard_data['recent_activity'])): ?>
                                <div class="alert alert-info text-center py-3 mb-0">
                                    ไม่พบการดำเนินการล่าสุดของคุณ
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($dashboard_data['recent_activity'] as $activity):
                                        $detail_link = '';
                                        if ($activity['item_type'] == 'โครงการ') {
                                            $detail_link = '?main_tab=user_requests&mode=projects_detail&project_id=' . $activity['id'];
                                        } elseif ($activity['item_type'] == 'คำร้องขอสถานที่') {
                                            $detail_link = '?main_tab=user_requests&mode=buildings_detail&facility_re_id=' . $activity['id'];
                                        } elseif ($activity['item_type'] == 'คำร้องขออุปกรณ์') {
                                            $detail_link = '?main_tab=user_requests&mode=equipments_detail&equip_re_id=' . $activity['id'];
                                        }
                                    ?>
                                        <li class="list-group-item activity-item">
                                            <?php if ($detail_link): ?>
                                                <a href="<?php echo $detail_link; ?>" class="d-flex w-100 justify-content-between align-items-center stretched-link text-decoration-none text-dark ">
                                                    <div class="main-info">
                                                        <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($activity['item_type']); ?></span>
                                                        <strong><?php echo htmlspecialchars($activity['item_name']); ?></strong>
                                                    </div>
                                                    <div class="date-info">
                                                        <span class="badge <?php echo getStatusBadgeClass($activity['status_text'], $activity['approve_status'] ?? null); ?>"><?php echo htmlspecialchars($activity['approve_status'] ?? $activity['status_text']); ?></span><br>
                                                        <small class="text-muted"><?php echo formatThaiDate($activity['activity_date']); ?></small>
                                                    </div>
                                                </a>
                                            <?php else: ?>
                                                <div class="main-info">
                                                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($activity['item_type']); ?></span>
                                                    <strong><?php echo htmlspecialchars($activity['item_name']); ?></strong>
                                                </div>
                                                <div class="date-info">
                                                    <span class="badge <?php echo getStatusBadgeClass($activity['status_text'], $activity['approve_status'] ?? null); ?>"><?php echo htmlspecialchars($activity['approve_status'] ?? $activity['status_text']); ?></span><br>
                                                    <small class="text-muted"><?php echo formatThaiDate($activity['activity_date']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
            </div>
        <?php endif; ?>

        <?php if ($main_tab == 'user_requests'): ?>
            <?php if ($mode == 'projects_list'): ?>
                <div class="row mb-3">
                    <div class="col">
                        <div class="card shadow-sm p-3">
                            <h5 class="card-title mb-3">โครงการทั้งหมดของคุณ: <?php echo $dashboard_data['project_counts']['total']; ?> โครงการ</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <h6>
                                    <span class="badge bg-warning text-dark">ร่างโครงการ: <?php echo $dashboard_data['project_counts']['draft']; ?> </span>
                                    <span class="badge bg-primary">ส่งโครงการ: <?php echo $dashboard_data['project_counts']['submitted']; ?> </span>
                                    <span class="badge bg-info text-dark">เริ่มดำเนินการ: <?php echo $dashboard_data['project_counts']['in_progress']; ?> </span>
                                    <span class="badge bg-secondary">สิ้นสุดโครงการ: <?php echo $dashboard_data['project_counts']['completed']; ?> </span>
                                    <span class="badge bg-dark">ยกเลิกโครงการ: <?php echo $dashboard_data['project_counts']['cancelled']; ?> </span>
                                </h6>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex justify-content-end mb-3">
                            <a href="?main_tab=user_requests&mode=projects_create" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> สร้างโครงการใหม่
                            </a>
                        </div>
                    </div>
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
                                            <div class="col">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <h5 class="card-title mb-1"> ชื่อโครงการ: <?php echo htmlspecialchars($project['project_name']); ?></h5>
                                                    <div class="text-end">
                                                        <h5 class="card-title mb-1"> สถานะ:
                                                            <span class="badge <?php echo getStatusBadgeClass($project['writed_status']); ?>"><?php echo htmlspecialchars($project['writed_status']); ?></span>
                                                        </h5>
                                                        <p class="card-text small mb-1 text-muted">
                                                            ยื่นเมื่อ: <?php echo formatThaiDate($project['created_date']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <p class="card-text small mb-1">
                                                    <?php if ($project['start_date'] != $project['end_date']) : ?>
                                                        <strong>ระยะเวลาโครงการ: </strong>ตั้งแต่วันที่ <?php echo formatThaiDate($project['start_date'], false); ?> ถึงวันที่ <?php echo formatThaiDate($project['end_date'], false); ?>
                                                    <?php else: ?>
                                                        <strong>ระยะเวลาโครงการ: </strong>วันที่ <?php echo formatThaiDate($project['start_date'], false); ?>
                                                    <?php endif; ?>
                                                <p class="card-text small mb-1">
                                                    <strong>ประเภทกิจกรรม:</strong> <?php echo htmlspecialchars($project['activity_type_name'] ?? 'ไม่ระบุ'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <nav aria-label="Page navigation" class="pagination-container mt-3 mb-0">
                        <ul class="pagination pagination-lg">
                            <?php

                            $search_param_url = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                            $sort_param_url = !empty($_GET['sort_filter']) ? '&sort_filter=' . urlencode($_GET['sort_filter']) : '';
                            ?>

                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page - 1; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>">ก่อนหน้า</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $i; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page + 1; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>">ถัดไป</a>
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
                            <p><strong>สถานะโครงการ:</strong> <span class="badge <?php echo getStatusBadgeClass($detail_item['writed_status']); ?>"><?php echo htmlspecialchars($detail_item['writed_status']); ?></span></p>
                            <p><strong>ระยะเวลาโครงการ:</strong> 
                                <?php if (($detail_item['start_date']) !== ($detail_item['end_date'])):?> 
                                    ตั้งแต่วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['end_date'], false); ?></p>
                                <?php else: ?> 
                                    วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?>
                                <?php endif; ?>
                            <p><strong>จำนวนผู้เข้าร่วม:</strong> <?php echo htmlspecialchars($detail_item['attendee']); ?></p>
                            <p><strong>หมายเลขโทรศัพท์:</strong> <?php echo htmlspecialchars($detail_item['phone_num']); ?></p>
                        </div>
                        <div class="col-md-6 pro-details">
                            <?php if (!empty($detail_item['advisor_name'])): ?>
                                <p><strong>ชื่อที่ปรึกษาโครงการ:</strong> <?php echo htmlspecialchars($detail_item['advisor_name']); ?></p>
                            <?php endif; ?>
                            <p><strong>ประเภทกิจกรรม:</strong> <?php echo htmlspecialchars($detail_item['activity_type_name'] ?? 'ไม่ระบุ'); ?></p>
                            <p><strong>ผู้เขียนโครงการ:</strong> <?php echo $user_THname, ' ', $user_THsur ?></p>
                            <p><strong>วันที่สร้างโครงการ: </strong><?php echo formatThaiDate($detail_item['created_date'])?>
                            <p><strong>รายละเอียดโครงการ:</strong><br> <?php echo nl2br(htmlspecialchars($detail_item['project_des'])); ?></p>
                            <?php if ($detail_item['files'] && file_exists($detail_item['files'])): ?>
                                <a href="<?php echo htmlspecialchars($detail_item['files']); ?>" target="_blank" class="btn btn-secondary"> ดูไฟล์แนบ
                                </a>
                            <?php endif; ?>
                        </div>
                        
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo $previous ?: '?main_tab=user_requests&mode=projects_list'; ?>"
                            class="btn btn-secondary"
                            onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                ย้อนกลับ
                        </a>
                        <div>
                            <?php
                                $can_edit = (($detail_item['writed_status'] == 'ร่างโครงการ' || $detail_item['start_date_compare'] >= $limited_date) && ($detail_item['writed_status'] !== 'เริ่มดำเนินการ' && $detail_item['writed_status'] !== 'สิ้นสุดโครงการ' && $detail_item['writed_status'] !== 'ยกเลิกโครงการ'));
                                $can_delete = ($detail_item['writed_status'] == 'ร่างโครงการ');
                                $can_cancel = ($detail_item['writed_status'] == 'ส่งโครงการ');
                            ?>
                            <?php if ($can_edit): ?>
                                <a href="?main_tab=user_requests&mode=projects_edit&project_id=<?php echo $detail_item['project_id']; ?>" class="btn btn-warning me-2">
                                    <i class="bi bi-pencil-square"></i> แก้ไขข้อมูล
                                </a>
                            <?php endif; ?>

                            <?php if ($can_delete): ?>
                                <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteProjectModal">
                                    <i class="bi bi-trash"></i> ลบโครงการ
                                </button>
                            <?php endif; ?>

                            <?php if ($can_cancel):?>
                                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#cancelProjectModal">
                                    <i class="bi bi-x-circle"></i> ยกเลิกโครงการ
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deleteProjectModalLabel">ยืนยันการลบโครงการ</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    คุณแน่ใจหรือไม่ว่าต้องการลบโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                    การดำเนินการนี้ไม่สามารถย้อนกลับได้ และจะลบคำร้องขอที่เกี่ยวข้องทั้งหมดด้วย.
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                    <form action="?main_tab=user_requests" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_project">
                                        <input type="hidden" name="project_id" value="<?php echo $detail_item['project_id']; ?>">
                                        <input type="hidden" name="file_to_delete" value="<?php echo htmlspecialchars($detail_item['files'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-danger">ลบโครงการ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="cancelProjectModal" tabindex="-1" aria-labelledby="cancelProjectModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-dark text-white">
                                    <h5 class="modal-title" id="cancelProjectModalLabel">ยืนยันการยกเลิกโครงการ</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    คุณแน่ใจหรือไม่ว่าต้องการยกเลิกโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                    การยกเลิกโครงการจะยกเลกคำร้องขอที่เกี่ยวข้องทั้งหมดด้วย.
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ไม่</button>
                                    <form action="?main_tab=user_requests" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="cancel_project">
                                        <input type="hidden" name="project_id" value="<?php echo $detail_item['project_id']; ?>">
                                        <button type="submit" class="btn btn-dark">ยืนยัน, ยกเลิกโครงการ</button>
                                    </form>
                                </div>
                            </div>
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
                                            <div class="d-flex justify-content-between align-items-center ">
                                                <h5 class="card-title mb-1">สถานที่: <?php echo htmlspecialchars($request['facility_name']); ?></h5>
                                                <div class="text-end">
                                                    <?php if ($request['approve'] === 'อนุมัติ' || $request['approve'] === 'ไม่อนุมัติ' || $request['approve'] === 'ยกเลิก'): ?>
                                                        <h5 class="card-title mb-1">การอนุมัติคำร้อง: <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['approve']); ?></span> 
                                                        </h5>
                                                    <?php endif; ?>
                                                    <p class="card-text small mb-1 text-muted">
                                                        ยื่นเมื่อ: <?php echo formatThaiDate($request['request_date']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <p class="card-text small mb-1"><strong> สถานะ: </strong>
                                                <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['writed_status']); ?></span>
                                            </p>
                                            <p class="card-text small mb-1">
                                                <?php if ($request['start_date'] !== $request['end_date']) : ?>
                                                    <strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> (<?php echo (new DateTime($request['start_time']))->format('H:i'); ?>) ถึง วันที่ <?php echo formatThaiDate($request['end_date'], false); ?> (<?php echo (new DateTime($request['end_time']))->format('H:i'); ?>)
                                                <?php else: ?>
                                                    <strong>ช่วงเวลาใช้งาน:</strong> วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> (<?php echo (new DateTime($request['start_time']))->format('H:i'); ?> - <?php echo (new DateTime($request['end_time']))->format('H:i'); ?>)
                                                <?php endif; ?>
                                            </p>
                                            <?php if (!empty($request['prepare_start_date'])): ?>
                                                <p class="card-text small mb-1 pro-details">
                                                <?php if ($request['prepare_start_date'] != $request['prepare_end_date']) : ?>
                                                    <strong>ช่วงเวลาเตรียมการ:</strong> ตั้งแต่วันที่ <?php echo formatThaiDate($request['prepare_start_date'], false); ?> (<?php echo (new DateTime($request['prepare_start_time']))->format('H:i'); ?>) ถึง วันที่ <?php echo formatThaiDate($request['prepare_end_date'], false); ?> (<?php echo (new DateTime($request['prepare_end_time']))->format('H:i'); ?>)
                                                <?php else: ?>
                                                    <strong>ช่วงเวลาเตรียมการ:</strong> วันที่ <?php echo formatThaiDate($request['prepare_start_date'], false); ?> (<?php echo (new DateTime($request['prepare_start_time']))->format('H:i'); ?> - <?php echo (new DateTime($request['prepare_end_time']))->format('H:i'); ?>)
                                                <?php endif; ?>
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
                                                <div class="text-end">
                                                    <?php if ($request['approve'] === 'อนุมัติ' || $request['approve'] === 'ไม่อนุมัติ' || $request['approve'] === 'ยกเลิก'): ?>
                                                        <h5 class="card-title mb-1">การอนุมัติคำร้อง: <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['approve']); ?></span> 
                                                        </h5>
                                                    <?php endif; ?>
                                                    <p class="card-text small mb-1 text-muted">
                                                        ยื่นเมื่อ: <?php echo formatThaiDate($request['request_date']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <p class="card-text small mb-1">
                                                <strong>จำนวน:</strong> <?php echo htmlspecialchars($request['quantity']); ?> <?php echo htmlspecialchars($request['measure']); ?>
                                            </p>
                                            <p class="card-text small mb-1">
                                                <strong>สถานะ:</strong>
                                                <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['writed_status']); ?></span>
                                            </p>
                                            <p class="card-text small mb-1">
                                                <strong>ช่วงเวลาใช้งาน:</strong>
                                                <?php if ($request['start_date'] !== $request['end_date']) : ?>
                                                    ตั้งแต่วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($request['end_date'], false); ?>
                                                <?php else: ?>
                                                    วันที่ <?php echo formatThaiDate($request['start_date'], false); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($mode == 'projects_edit' && $detail_item): ?>
                    <div class="form-section my-4">
                        <h2 class="mb-4 text-center">แก้ไขโครงการ: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                        <form action="?main_tab=user_requests&mode=projects_edit" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($detail_item['project_id']); ?>">
                            <input type="hidden" name="existing_file_path" value="<?php echo htmlspecialchars($detail_item['files'] ?? ''); ?>">

                            <div class="mb-3">
                                <label for="project_name" class="form-label">ชื่อโครงการ:</label>
                                <input type="text" class="form-control" id="project_name" name="project_name" value="<?php echo htmlspecialchars($_POST['project_name'] ?? $detail_item['project_name']); ?>" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">วันเริ่มต้น:</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? $detail_item['start_date']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">วันสิ้นสุด:</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? $detail_item['end_date']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="project_des" class="form-label">รายละเอียดของโครงการ:</label>
                                <textarea class="form-control" id="project_des" name="project_des" rows="5"><?php echo htmlspecialchars($_POST['project_des'] ?? $detail_item['project_des']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="files" class="form-label">ไฟล์แนบ (รูปภาพ, PDF, Doc, XLS):</label>
                                <?php if ($detail_item['files'] && file_exists($detail_item['files'])): ?>
                                    <p class="text-muted small">ไฟล์เดิม: <a href="<?php echo htmlspecialchars($detail_item['files']); ?>" target="_blank"><?php echo basename($detail_item['files']); ?></a> (เลือกไฟล์ใหม่เพื่ออัปโหลดทับ)</p>
                                <?php else: ?>
                                    <p class="text-muted small">ไม่มีไฟล์เดิม</p>
                                <?php endif; ?>
                                <input type="file" class="form-control" id="files" name="files" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="attendee" class="form-label">จำนวนผู้เข้าร่วม:</label>
                                    <input type="number" class="form-control" id="attendee" name="attendee" min="1" value="<?php echo htmlspecialchars($_POST['attendee'] ?? $detail_item['attendee']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone_num" class="form-label">หมายเลขโทรศัพท์:</label>
                                    <input type="text" class="form-control" id="phone_num" name="phone_num" value="<?php echo htmlspecialchars($_POST['phone_num'] ?? $detail_item['phone_num']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="advisor_name" class="form-label">ชื่อที่ปรึกษาโครงการ:</label>
                                <?php if ($user_role === 'อาจารย์และบุคลากร'): ?>
                                    <input type="text" class="form-control" id="advisor_name" name="advisor_name" value="-" readonly>
                                    <small class="text-muted">*อาจารย์และบุคลากร ไม่ต้องระบุชื่อที่ปรึกษา</small>
                                <?php else: ?>
                                    <input type="text" class="form-control" id="advisor_name" name="advisor_name" value="<?php echo htmlspecialchars($_POST['advisor_name'] ?? $detail_item['advisor_name']); ?>" required>
                                <?php endif; ?>
                            </div>
                            <div class="mb-3">
                                <label for="activity_type_id" class="form-label">ประเภทกิจกรรม:</label>
                                <select class="form-select" id="activity_type_id" name="activity_type_id" required>
                                    <option value="">-- เลือกประเภทกิจกรรม --</option>
                                    <?php foreach ($activity_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['activity_type_id']); ?>"
                                            <?php echo (isset($_POST['activity_type_id']) && $_POST['activity_type_id'] == $type['activity_type_id']) ? 'selected' : ''; ?>
                                            <?php echo (!isset($_POST['activity_type_id']) && $detail_item['activity_type_id'] == $type['activity_type_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['activity_type_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="d-flex justify-content-between mt-4">
                                <a href="?main_tab=user_requests&mode=projects_detail&project_id=<?php echo $detail_item['project_id']; ?>" class="btn btn-secondary">ย้อนกลับ</a>
                                <div>
                                    <?php if ($detail_item['writed_status'] == 'ร่างโครงการ') : ?>
                                        <button type="submit" name="action" value="save_draft_edit" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="submit_project_edit" class="btn btn-success">บันทึกและส่งโครงการ</button>
                                </div>
                            </div>
                        </form>
                    </div>

            <?php elseif ($mode == 'buildings_list' || $mode == 'equipments_list'): ?>
                <div class="row mb-3">
                    <div class="col">
                        <div class="card shadow-sm p-3">
                            <h5 class="card-title mb-3">คำร้องขอใช้อาคารสถานที่ทั้งหมดของคุณ: 
                                <?php if ($mode == 'buildings_list') : ?> <?php echo $dashboard_data['facilities_request_counts']['total']; ?> คำร้อง</h5>
                                <?php elseif ($mode == 'equipments_list') : ?> <?php echo $dashboard_data['equipments_request_counts']['total']; ?> คำร้อง</h5>
                                <?php endif; ?>
                            <div class="d-flex flex-wrap gap-2">
                                <h6>
                                    <?php if ($mode == 'buildings_list') : ?>
                                        <span class="badge bg-success">อนุมัติ: <?php echo $dashboard_data['facilities_request_counts']['approved']; ?> </span>
                                        <span class="badge bg-danger">ไม่อนุมัติ: <?php echo $dashboard_data['facilities_request_counts']['rejected']; ?> </span>
                                        <div class="mt-2">
                                            <span class="badge bg-warning text-dark">ร่างคำร้องขอ: <?php echo $dashboard_data['facilities_request_counts']['draft']; ?> </span>
                                            <span class="badge bg-primary">ส่งคำร้องขอ: <?php echo $dashboard_data['facilities_request_counts']['submitted']; ?> </span>
                                            <span class="badge bg-info text-dark">เริ่มดำเนินการ: <?php echo $dashboard_data['facilities_request_counts']['in_progress']; ?> </span>
                                            <span class="badge bg-secondary">สิ้นสุดดำเนินการ: <?php echo $dashboard_data['facilities_request_counts']['completed']; ?> </span>
                                            <span class="badge bg-dark">ยกเลิกคำร้องขอ: <?php echo $dashboard_data['facilities_request_counts']['cancelled']; ?> </span>
                                        </div>
                                    <?php elseif ($mode == 'equipments_list') : ?>
                                        <span class="badge bg-success">อนุมัติ: <?php echo $dashboard_data['equipments_request_counts']['approved']; ?> </span>
                                        <span class="badge bg-danger">ไม่อนุมัติ: <?php echo $dashboard_data['equipments_request_counts']['rejected']; ?> </span>
                                        <div class="mt-2">
                                            <span class="badge bg-warning text-dark">ร่างคำร้องขอ: <?php echo $dashboard_data['equipments_request_counts']['draft']; ?> </span>
                                            <span class="badge bg-primary">ส่งคำร้องขอ: <?php echo $dashboard_data['equipments_request_counts']['submitted']; ?> </span>
                                            <span class="badge bg-info text-dark">เริ่มดำเนินการ: <?php echo $dashboard_data['equipments_request_counts']['in_progress']; ?> </span>
                                            <span class="badge bg-secondary">สิ้นสุดดำเนินการ: <?php echo $dashboard_data['equipments_request_counts']['completed']; ?> </span>
                                            <span class="badge bg-dark">ยกเลิกคำร้องขอ: <?php echo $dashboard_data['equipments_request_counts']['cancelled']; ?> </span>
                                        </div>
                                    <?php endif; ?>
                                </h6>
                            </div>
                        </div>
                    </div>
                    <div class="col">
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
                                        <small class="text-muted">(สถานะ: <span class="badge <?php echo getStatusBadgeClass($project['writed_status']); ?>"><?php echo htmlspecialchars($project['writed_status']); ?></span>)</small>
                                    </h4>
                                    <p class="card-text small mb-2 text-muted">
                                        ยื่นเมื่อ: <?php echo formatThaiDate($project['created_date']); ?>
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
                                                        <div class="text-end">
                                                            <?php if ($request['approve'] === 'อนุมัติ' || $request['approve'] === 'ไม่อนุมัติ' || $request['approve'] === 'ยกเลิก'): ?>
                                                                <h6 class="card-title">การอนุมัติคำร้อง: <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['approve']); ?></span> 
                                                                </h6>
                                                            <?php endif; ?>
                                                            <p class="text-muted">ยื่นเมื่อ: <?php echo formatThaiDate($request['request_date']); ?></p>
                                                        </div>
                                                    </div>
                                                    <p class="card-text small mb-1"><strong>สถานะ: </strong>
                                                        <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['writed_status']); ?></span>
                                                    </p>
                                                    <?php if ($mode == 'buildings_list'): ?>
                                                        <p class="card-text small mb-1">
                                                            <?php if ($request['start_date'] !== $request['end_date']) : ?>
                                                                <strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> (<?php echo (new DateTime($request['start_time']))->format('H:i'); ?>) ถึง วันที่ <?php echo formatThaiDate($request['end_date'], false); ?> (<?php echo (new DateTime($request['end_time']))->format('H:i'); ?>)
                                                            <?php else: ?>
                                                                <strong>ช่วงเวลาใช้งาน:</strong> วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> (<?php echo (new DateTime($request['start_time']))->format('H:i'); ?> - <?php echo (new DateTime($request['end_time']))->format('H:i'); ?>)
                                                            <?php endif; ?>
                                                        </p>
                                                        <?php if (!empty($request['prepare_start_date'])): ?>
                                                            <p class="card-text small mb-1 pro-details">
                                                            <?php if ($request['prepare_start_date'] != $request['prepare_end_date']) : ?>
                                                                <strong>ช่วงเวลาเตรียมการ:</strong> ตั้งแต่วันที่ <?php echo formatThaiDate($request['prepare_start_date'], false); ?> (<?php echo (new DateTime($request['prepare_start_time']))->format('H:i'); ?>) ถึง วันที่ <?php echo formatThaiDate($request['prepare_end_date'], false); ?> (<?php echo (new DateTime($request['prepare_end_time']))->format('H:i'); ?>)
                                                            <?php else: ?>
                                                                <strong>ช่วงเวลาเตรียมการ:</strong> วันที่ <?php echo formatThaiDate($request['prepare_start_date'], false); ?> (<?php echo (new DateTime($request['prepare_start_time']))->format('H:i'); ?> - <?php echo (new DateTime($request['prepare_end_time']))->format('H:i'); ?>)
                                                            <?php endif; ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    <?php elseif ($mode == 'equipments_list'): ?>
                                                        <p class="card-text small mb-1">
                                                            <strong>ช่วงเวลาใช้งาน:</strong>
                                                            <?php if ($request['start_date'] !== $request['end_date']) : ?>
                                                                ตั้งแต่วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($request['end_date'], false); ?>
                                                            <?php else: ?>
                                                                วันที่ <?php echo formatThaiDate($request['start_date'], false); ?>
                                                            <?php endif; ?>
                                                        </p>
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
                            <?php

                            $search_param_url = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                            $sort_param_url = !empty($_GET['sort_filter']) ? '&sort_filter=' . urlencode($_GET['sort_filter']) : '';
                            ?>

                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page - 1; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>">ก่อนหน้า</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $i; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page + 1; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>">ถัดไป</a>
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
                            <p><strong>ชื่อโครงการ: </strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                            <p><strong>สถานะคำร้อง: </strong> <span class="badge <?php echo getStatusBadgeClass($detail_item['writed_status'], $detail_item['approve']); ?>"><?php echo htmlspecialchars($detail_item['writed_status']); ?></span></p>
                            <p><strong>สถานที่ที่ขอใช้งาน: </strong> <?php echo htmlspecialchars($detail_item['facility_name']); ?></p>

                            <?php if (($detail_item['prepare_start_date']) !== ($detail_item['prepare_end_date'])) : ?>
                                <p><strong>วันเริ่มต้นการเตรียมการ: </strong> <?php echo formatThaiDate($detail_item['prepare_start_date'], false)?> ถึง วันที่ <?php echo formatThaiDate($detail_item['prepare_end_date'], false); ?></p>
                            <?php else: ?>
                                <p><strong>วันที่เตรียมการ: </strong> <?php echo formatThaiDate($detail_item['prepare_start_date'], false); ?></p>
                            <?php endif; ?>

                            <p><strong>ตั้งแต่เวลา: </strong> 
                            <?php if($detail_item['prepare_start_time'] !== $detail_item['prepare_end_time']): ?>
                                <?php echo (new DateTime($detail_item['prepare_start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['prepare_end_time']))->format('H:i'); ?></p>
                            <?php else: ?>
                                <?php echo (new DateTime($detail_item['prepare_start_time']))->format('H:i'); ?> น.
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 pro-details">
                            <p><strong>วันที่สร้างคำร้อง: </strong>
                                <?php echo formatThaiDate($detail_item['request_date']);?>
                            </p>

                            <p><strong>ผู้เขียนคำร้อง: </strong> <?php echo htmlspecialchars($detail_item['user_name']); ?></p>
                            <?php if (($detail_item['start_date']) !== ($detail_item['end_date'])):?>
                                <p><strong>วันเริ่มต้นการใช้งาน: </strong> <?php echo formatThaiDate($detail_item['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['end_date'], false); ?></p>
                            <?php else: ?>
                                <p><strong>วันที่ใช้งาน: </strong> <?php echo formatThaiDate($detail_item['start_date'], false); ?></p>
                            <?php endif; ?>

                            <p><strong>ตั้งแต่เวลา: </strong>
                            <?php if ($detail_item['start_time'] !== $detail_item['end_time']) : ?>
                                <?php echo (new DateTime($detail_item['start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['end_time']))->format('H:i'); ?></p>
                            <?php else: ?>
                                <?php echo (new DateTime($detail_item['start_time']))->format('H:i'); ?> น.
                            <?php endif; ?>

                            <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                            <?php if ($detail_item['approve'] != '' && $detail_item['approve'] != 'ยกเลิก'): ?>
                                <p><strong>การอนุมัติคำร้อง: </strong> <span class="badge <?php echo getStatusBadgeClass($detail_item['writed_status'], $detail_item['approve']); ?>"><?php echo htmlspecialchars($detail_item['approve']); ?></span></p>
                                <?php if ($detail_item['approve'] == 'อนุมัติ'): ?>
                                    <p><strong>วันที่อนุมัติ: </strong> <?php echo formatThaiDate($detail_item['approve_date'], false); ?></p>
                                <?php elseif ($detail_item['approve'] == 'ไม่อนุมัติ' || $detail_item['approve'] == 'ยกเลิก'): ?>
                                    <p><strong>วันที่ดำเนินการ: </strong> <?php echo formatThaiDate($detail_item['approve_date'], false); ?></p>
                                <?php endif; ?>
                                <?php if ($detail_item['approve'] == 'ไม่อนุมัติ' || $detail_item['approve'] == 'ยกเลิก'): ?>
                                    <p><strong>รายละเอียดการ<?php echo htmlspecialchars($detail_item['approve']); ?>:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                <?php elseif ($detail_item['approve'] == 'อนุมัติ' && !empty($detail_item['approve_detail'])): ?>
                                    <p><strong>รายละเอียดการอนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo $previous ?: '?main_tab=user_requests&mode=buildings_list'; ?>"
                            class="btn btn-secondary"
                            onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                ย้อนกลับ
                        </a>
                        <div>
                            <?php
                                $can_edit = (($detail_item['writed_status'] == 'ร่างคำร้องขอ' || $detail_item['start_date_compare'] >= $limited_date) && ($detail_item['writed_status'] !== 'เริ่มดำเนินการ' && $detail_item['writed_status'] !== 'สิ้นสุดดำเนินการ' && $detail_item['writed_status'] !== 'ยกเลิกคำร้องขอ'));
                                $can_delete = ($detail_item['writed_status'] == 'ร่างคำร้องขอ');
                                $can_cancel = ($detail_item['writed_status'] == 'ส่งคำร้องขอ'); 
                            ?>
                            <?php if ($can_edit): ?>
                                <a href="?main_tab=user_requests&mode=buildings_edit&facility_re_id=<?php echo $detail_item['facility_re_id']; ?>" class="btn btn-warning me-2">
                                    <i class="bi bi-pencil-square"></i> แก้ไขคำร้องขอ
                                </a>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                                <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteBuildingRequestModal">
                                    <i class="bi bi-trash"></i> ลบคำร้องขอ
                                </button>
                            <?php endif; ?>
                            <?php if ($can_cancel): ?>
                                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#cancelBuildingRequestModal">
                                    <i class="bi bi-x-circle"></i> ยกเลิกคำร้องขอ
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="modal fade" id="deleteBuildingRequestModal" tabindex="-1" aria-labelledby="deleteBuildingRequestModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title" id="deleteBuildingRequestModalLabel">ยืนยันการลบคำร้องขอสถานที่</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    คุณแน่ใจหรือไม่ว่าต้องการลบคำร้องขอใช้สถานที่ "<strong><?php echo htmlspecialchars($detail_item['facility_name']); ?></strong>" ของโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                    การดำเนินการนี้ไม่สามารถย้อนกลับได้.
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                    <form action="?main_tab=user_requests" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_building_request">
                                        <input type="hidden" name="facility_re_id" value="<?php echo $detail_item['facility_re_id']; ?>">
                                        <button type="submit" class="btn btn-danger">ยืนยันการลบ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="cancelBuildingRequestModal" tabindex="-1" aria-labelledby="cancelBuildingRequestModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header bg-dark text-white">
                                    <h5 class="modal-title" id="cancelBuildingRequestModalLabel">ยืนยันการยกเลิกคำร้องขอสถานที่</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    คุณแน่ใจหรือไม่ว่าต้องการยกเลิกคำร้องขอใช้สถานที่ "<strong><?php echo htmlspecialchars($detail_item['facility_name']); ?></strong>" ของโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                    การดำเนินการนี้จะเปลี่ยนสถานะคำร้องเป็น "ยกเลิกคำร้องขอ".
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ไม่</button>
                                    <form action="?main_tab=user_requests" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="cancel_building_request">
                                        <input type="hidden" name="facility_re_id" value="<?php echo $detail_item['facility_re_id']; ?>">
                                        <button type="submit" class="btn btn-dark">ใช่, ยกเลิกคำร้องขอ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($mode == 'buildings_edit' && $detail_item): ?>
                    <div class="form-section my-4">
                        <h2 class="mb-4 text-center">แก้ไขคำร้องขอใช้อาคารสถานที่: <?php echo htmlspecialchars($detail_item['facility_name']); ?></h2>
                        <form action="?main_tab=user_requests&mode=buildings_edit" method="POST">
                            <input type="hidden" name="facility_re_id" value="<?php echo htmlspecialchars($detail_item['facility_re_id']); ?>">
                            <div class="mb-3">
                                <label for="project_id" class="form-label">โครงการ:</label>
                                <select class="form-select" id="project_id" name="project_id" required>
                                    <option value="">-- เลือกโครงการ --</option>
                                    <?php foreach ($user_projects as $project): ?>
                                        <option value="<?php echo htmlspecialchars($project['project_id']); ?>"
                                            <?php echo (isset($_POST['project_id']) && $_POST['project_id'] == $project['project_id']) ? 'selected' : ''; ?>
                                            <?php echo (!isset($_POST['project_id']) && $detail_item['project_id'] == $project['project_id']) ? 'selected' : ''; ?>>
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
                                            <?php echo (isset($_POST['facility_id']) && $_POST['facility_id'] == $facility['facility_id']) ? 'selected' : ''; ?>
                                            <?php echo (!isset($_POST['facility_id']) && $detail_item['facility_id'] == $facility['facility_id']) ? 'selected' : ''; ?>>
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
                                    <input type="date" class="form-control" id="prepare_start_date" name="prepare_start_date" value="<?php echo htmlspecialchars($_POST['prepare_start_date'] ?? $detail_item['prepare_start_date']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="prepare_end_date" class="form-label">วันสิ้นสุดเตรียมการ:</label>
                                    <input type="date" class="form-control" id="prepare_end_date" name="prepare_end_date" value="<?php echo htmlspecialchars($_POST['prepare_end_date'] ?? $detail_item['prepare_end_date']); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="prepare_start_time" class="form-label">เวลาเริ่มต้นเตรียมการ:</label>
                                    <input type="time" class="form-control" id="prepare_start_time" name="prepare_start_time" value="<?php echo htmlspecialchars($_POST['prepare_start_time'] ?? $detail_item['prepare_start_time']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="prepare_end_time" class="form-label">เวลาสิ้นสุดเตรียมการ:</label>
                                    <input type="time" class="form-control" id="prepare_end_time" name="prepare_end_time" value="<?php echo htmlspecialchars($_POST['prepare_end_time'] ?? $detail_item['prepare_end_time']); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">วันเริ่มต้นใช้การ:</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? $detail_item['start_date']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">วันสิ้นสุดใช้การ:</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? $detail_item['end_date']); ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_time" class="form-label">เวลาเริ่มต้นใช้การ:</label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo htmlspecialchars($_POST['start_time'] ?? $detail_item['start_time']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_time" class="form-label">เวลาสิ้นสุดใช้การ:</label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo htmlspecialchars($_POST['end_time'] ?? $detail_item['end_time']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agree" name="agree" value="1" <?php echo (isset($_POST['agree']) && $_POST['agree'] == 1) ? 'checked' : ''; ?> <?php echo (!isset($_POST['agree']) && $detail_item['agree'] == 1) ? 'checked' : ''; ?> >
                                <label class="form-check-label" for="agree">ข้าพเจ้ายินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ </label>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="?main_tab=user_requests&mode=buildings_detail&facility_re_id=<?php echo $detail_item['facility_re_id']; ?>" class="btn btn-secondary">ย้อนกลับ</a>
                                <div>
                                    <?php if ($detail_item['writed_status'] == 'ร่างคำร้องขอ') : ?>
                                        <button type="submit" name="action" value="save_draft_building_edit" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                                    <?php endif; ?>
                                    <button type="submit" name="action" value="submit_building_edit" class="btn btn-success">บันทึกและส่งคำร้อง</button>
                                </div>
                            </div>
                        </form>
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
                                <option value="">-- เลือกโครงการเพื่อดูสถานที่ --</option>
                            </select>
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
                                <p><strong>ชื่อโครงการ: </strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                                <p><strong>สถานะคำร้อง: </strong> <span class="badge <?php echo getStatusBadgeClass($detail_item['writed_status'], $detail_item['approve']); ?>"><?php echo htmlspecialchars($detail_item['writed_status']); ?></span></p>
                                <p><strong>อุปกรณ์ที่ขอใช้งาน: </strong> <?php echo htmlspecialchars($detail_item['equip_name']); ?> จำนวน <?php echo htmlspecialchars($detail_item['quantity']);?> <?php echo htmlspecialchars($detail_item['measure']); ?></p>
                                <p><strong>สถานที่ที่นำอุปกรณ์ไปใช้งาน: </strong> <?php echo htmlspecialchars($detail_item['facility_name'] ?? 'ไม่ระบุ'); ?></p>
                                <p><strong>ระยะเวลาใช้การ: </strong> 
                                <?php if (($detail_item['start_date']) !== ($detail_item['end_date'])):?> ตั้งแต่วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['end_date'], false); ?></p>
                                <?php else: ?> วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 pro-details">
                                <p><strong>วันที่สร้างคำร้อง:</strong>
                                        <?php echo formatThaiDate($detail_item['request_date']);?>
                                </p>
                                <p><strong>ผู้เขียนคำร้อง: </strong> <?php echo htmlspecialchars($detail_item['user_name']); ?></p>
                                <p><strong>ต้องการขนส่งอุปกรณ์: </strong> <?php echo ($detail_item['transport'] == 1) ? 'ต้องการ' : 'ไม่ต้องการ'; ?></p>
                                <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ: </strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                                <?php if ($detail_item['approve'] != ''): ?>
                                    <p><strong>การอนุมัติคำร้อง: </strong> <span class="badge <?php echo getStatusBadgeClass($detail_item['writed_status'], $detail_item['approve']); ?>"><?php echo htmlspecialchars($detail_item['approve']); ?></span></p>
                                <?php if ($detail_item['approve'] == 'อนุมัติ'): ?>
                                    <p><strong>วันที่อนุมัติ: </strong> <?php echo formatThaiDate($detail_item['approve_date'], false); ?></p>
                                <?php elseif ($detail_item['approve'] == 'ไม่อนุมัติ' || $detail_item['approve'] == 'ยกเลิก'): ?>
                                    <p><strong>วันที่ดำเนินการ: </strong> <?php echo formatThaiDate($detail_item['approve_date'], false); ?></p>
                                <?php endif; ?>
                                <?php if ($detail_item['approve'] == 'ไม่อนุมัติ' || $detail_item['approve'] == 'ยกเลิก'): ?>
                                    <p><strong>รายละเอียดการ<?php echo htmlspecialchars($detail_item['approve']); ?>:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                <?php elseif ($detail_item['approve'] == 'อนุมัติ' && !empty($detail_item['approve_detail'])): ?>
                                    <p><strong>รายละเอียดการอนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?php echo $previous ?: '?main_tab=user_requests&mode=equipments_list'; ?>"
                                class="btn btn-secondary"
                                onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                    ย้อนกลับ
                            </a>
                            <div>
                            <?php
                                $can_edit = ($detail_item['writed_status'] == 'ร่างคำร้องขอ');
                                $can_delete = ($detail_item['writed_status'] == 'ร่างคำร้องขอ');
                                $can_cancel_request = ($detail_item['writed_status'] !== 'เริ่มดำเนินการ' && $detail_item['writed_status'] !== 'สิ้นสุดดำเนินการ' && $detail_item['approve'] !== 'อนุมัติ' && $detail_item['approve'] !== 'ไม่อนุมัติ' && $detail_item['writed_status'] !== 'ยกเลิกคำร้องขอ');
                            ?>
                            <?php if ($can_edit): ?>
                                <a href="?main_tab=user_requests&mode=equipments_edit&equip_re_id=<?php echo $detail_item['equip_re_id']; ?>" class="btn btn-warning me-2">
                                    <i class="bi bi-pencil-square"></i> แก้ไขคำร้องขอ
                                </a>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                                <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteEquipmentRequestModal">
                                    <i class="bi bi-trash"></i> ลบคำร้องขอ
                                </button>
                            <?php endif; ?>
                            <?php if ($can_cancel_request): ?>
                                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#cancelEquipmentRequestModal">
                                    <i class="bi bi-x-circle"></i> ยกเลิกคำร้องขอ
                                </button>
                            <?php endif; ?>
                        </div>
                        </div>

                        <div class="modal fade" id="deleteEquipmentRequestModal" tabindex="-1" aria-labelledby="deleteEquipmentRequestModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title" id="deleteEquipmentRequestModalLabel">ยืนยันการลบคำร้องขออุปกรณ์</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        คุณแน่ใจหรือไม่ว่าต้องการลบคำร้องขอใช้อุปกรณ์ "<strong><?php echo htmlspecialchars($detail_item['equip_name']); ?></strong>" ของโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                        การดำเนินการนี้ไม่สามารถย้อนกลับได้.
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                        <form action="?main_tab=user_requests" method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete_equipment_request">
                                            <input type="hidden" name="equip_re_id" value="<?php echo $detail_item['equip_re_id']; ?>">
                                            <button type="submit" class="btn btn-danger">ยืนยันการลบ</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="cancelEquipmentRequestModal" tabindex="-1" aria-labelledby="cancelEquipmentRequestModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header bg-dark text-white">
                                        <h5 class="modal-title" id="cancelEquipmentRequestModalLabel">ยืนยันการยกเลิกคำร้องขออุปกรณ์</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        คุณแน่ใจหรือไม่ว่าต้องการยกเลิกคำร้องขอใช้อุปกรณ์ "<strong><?php echo htmlspecialchars($detail_item['equip_name']); ?></strong>" ของโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                        การดำเนินการนี้จะเปลี่ยนสถานะคำร้องเป็น "ยกเลิกคำร้องขอ".
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ไม่</button>
                                        <form action="?main_tab=user_requests" method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="cancel_equipment_request">
                                            <input type="hidden" name="equip_re_id" value="<?php echo $detail_item['equip_re_id']; ?>">
                                            <button type="submit" class="btn btn-dark">ใช่, ยกเลิกคำร้องขอ</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
            <?php elseif ($mode == 'equipments_edit' && $detail_item): ?>
                <div class="form-section my-4">
                    <h2 class="mb-4 text-center">แก้ไขคำร้องขอใช้อุปกรณ์: <?php echo htmlspecialchars($detail_item['equip_name']); ?></h2>
                    <form action="?main_tab=user_requests&mode=equipments_edit" method="POST">
                        <input type="hidden" name="equip_re_id" value="<?php echo htmlspecialchars($detail_item['equip_re_id']); ?>">
                        <div class="mb-3">
                            <label for="project_id" class="form-label">โครงการ:</label>
                            <select class="form-select" id="project_id" name="project_id" required>
                                <option value="">-- เลือกโครงการ --</option>
                                <?php foreach ($user_projects as $project): ?>
                                    <option value="<?php echo htmlspecialchars($project['project_id']); ?>"
                                        <?php
                                        // Pre-select project_id for edit mode
                                        $selected_project_from_post = isset($_POST['project_id']) ? $_POST['project_id'] : null;
                                        $selected_project_from_detail = $detail_item['project_id'] ?? null;

                                        if ($project['project_id'] == $selected_project_from_post || $project['project_id'] == $selected_project_from_detail) {
                                            echo 'selected';
                                        }
                                        ?>>
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
                                        <?php echo (isset($_POST['equip_id']) && $_POST['equip_id'] == $equip['equip_id']) ? 'selected' : ''; ?>
                                        <?php echo (!isset($_POST['equip_id']) && $detail_item['equip_id'] == $equip['equip_id']) ? 'selected' : ''; ?>>
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
                            <select class="form-select" id="facility_id" name="facility_id"
                                data-initial-facility-id="<?php echo htmlspecialchars($detail_item['facility_id'] ?? ''); ?>">
                                <option value="">-- เลือกโครงการเพื่อดูสถานที่ --</option>
                                <?php
                                // Populate current facility if in edit mode (from $facilities array loaded in PHP)
                                foreach ($facilities as $facility):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($facility['facility_id']); ?>"
                                        <?php
                                        // Pre-select the original facility if it matches
                                        if (isset($detail_item['facility_id']) && $detail_item['facility_id'] == $facility['facility_id']) {
                                            echo 'selected';
                                        }
                                        ?>>
                                        <?php echo htmlspecialchars($facility['facility_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">วันเริ่มต้นใช้การ:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? $detail_item['start_date']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">วันสิ้นสุดใช้การ:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? $detail_item['end_date']); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">จำนวนที่ต้องการขอใช้:</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="<?php echo htmlspecialchars($_POST['quantity'] ?? $detail_item['quantity']); ?>" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="transport" name="transport" value="1" <?php echo (isset($_POST['transport']) && $_POST['transport'] == 1) ? 'checked' : ''; ?> <?php echo (!isset($_POST['transport']) && $detail_item['transport'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="transport">ต้องการขนส่งอุปกรณ์</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="agree" name="agree" value="1" <?php echo (isset($_POST['agree']) && $_POST['agree'] == 1) ? 'checked' : ''; ?> <?php echo (!isset($_POST['agree']) && $detail_item['agree'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="agree">ข้าพเจ้ายินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ </label>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="?main_tab=user_requests&mode=equipments_detail&equip_re_id=<?php echo $detail_item['equip_re_id']; ?>" class="btn btn-secondary">ย้อนกลับ</a>
                            <div>
                                <?php if ($detail_item['writed_status'] == 'ร่างคำร้องขอ') : ?>
                                    <button type="submit" name="action" value="save_draft_equipment_edit" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                                <?php endif; ?>
                                <button type="submit" name="action" value="submit_equipment_edit" class="btn btn-success">บันทึกและส่งคำร้อง</button>
                            </div>
                        </div>
                    </form>
                </div>

            <?php endif; ?>
        <?php endif; ?>
        <!-- <<<<< END MODIFIED SECTION: user_requests Content >>>>> -->

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="./js/building_dropdown.js"></script>
</body>
</html>