<?php
// helpers.php

// ต้องมี database connection ถ้า function ไหนใช้ $conn โดยตรง
if (!isset($conn)) {
    // assume database.php is in ../database/
    include dirname(__DIR__) . '/database/database.php';
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
        $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์ " . $_FILES[$file_input_name]['name'] . ": Error Code " . $_FILES[$file_input_name]['error'];
        return false;
    }
    return null;
}

function handleMultipleFileUploads($file_input_name, $target_dir, &$errors, $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx']) {
    $uploaded_paths = [];
    if (isset($_FILES[$file_input_name]) && is_array($_FILES[$file_input_name]['name'])) {
        $file_names = $_FILES[$file_input_name]['name'];
        $file_tmp_names = $_FILES[$file_input_name]['tmp_name'];
        $file_errors = $_FILES[$file_input_name]['error'];
        $file_sizes = $_FILES[$file_input_name]['size'];

        for ($i = 0; $i < count($file_names); $i++) {
            if ($file_errors[$i] == UPLOAD_ERR_OK) {
                $file_name = basename($file_names[$i]);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (!in_array($file_ext, $allowed_ext)) {
                    $errors[] = "ประเภทไฟล์สำหรับ " . $file_name . " ไม่ถูกต้อง (ประเภทไฟล์ที่อนุญาต: " . implode(', ', $allowed_ext) . ")";
                    continue;
                }

                if ($file_sizes[$i] > 10 * 1024 * 1024) { // 10 MB
                    $errors[] = "ขนาดไฟล์สำหรับ " . $file_name . " ต้องมีขนาดไม่เกิน 10 MB เท่านั้น.";
                    continue;
                }

                $new_file_name = uniqid() . '.' . $file_ext;
                $upload_path = $target_dir . $new_file_name;

                if (move_uploaded_file($file_tmp_names[$i], $upload_path)) {
                    $uploaded_paths[] = $upload_path;
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์ " . $file_name . ": Error Code " . $file_errors[$i];
                }
            } elseif ($file_errors[$i] != UPLOAD_ERR_NO_FILE) {
                $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์ " . $file_names[$i] . ": Error Code " . $file_errors[$i];
            }
        }
    }
    return $uploaded_paths;
}

// Helper function for Server-Side Date Validation against Project Dates
function validateRequestDatesAgainstProject($request_start, $request_end, $project_id, $conn, $user_id, &$errors, $request_type = 'ทั่วไป') {
    $sql = "SELECT start_date, end_date FROM project WHERE project_id = ? AND nontri_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับตรวจสอบโครงการ: " . $conn->error;
        return false;
    }
    $stmt->bind_param("is", $project_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$project = $result->fetch_assoc()) {
        $errors[] = "ไม่พบโครงการที่เลือก หรือคุณไม่มีสิทธิ์เข้าถึงโครงการนี้.";
        $stmt->close();
        return false;
    }
    $stmt->close();

    $project_start_date = strtotime($project['start_date']);
    $project_end_date = strtotime($project['end_date']);
    $req_start_timestamp = strtotime($request_start);
    $req_end_timestamp = strtotime($request_end);

    if ($req_start_timestamp < $project_start_date || $req_end_timestamp > $project_end_date) {
        $errors[] = "วันที่ใช้งาน/เตรียมการของคำร้องขอ{$request_type} ต้องอยู่ภายในช่วงเวลาของโครงการ (ตั้งแต่วันที่ " . formatThaiDate($project['start_date'], false) . " ถึงวันที่ " . formatThaiDate($project['end_date'], false) . ").";
        return false;
    }
    return true;
}

function getStatusBadgeClass($status_text, $approve_status = null) {
    if ($approve_status === 'อนุมัติ') {
        return 'bg-success';
    } elseif ($approve_status === 'ไม่อนุมัติ') {
        return 'bg-danger';
    } elseif ($approve_status === 'ยกเลิก') {
        return 'bg-dark';
    } else {
        switch ($status_text) {
            case 'ร่างโครงการ':
            case 'ร่างคำร้องขอ':
                return 'bg-warning text-dark';
            case 'ส่งโครงการ':
            case 'ส่งคำร้องขอ':
                return 'bg-primary';
            case 'เริ่มดำเนินการ':
                return 'bg-info text-dark';
            case 'สิ้นสุดโครงการ':
            case 'สิ้นสุดดำเนินการ':
                return 'bg-secondary';
            case 'ยกเลิกโครงการ':
            case 'ยกเลิกคำร้องขอ':
                return 'bg-dark';
            default:
                return 'bg-secondary';
        }
    }
}

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
?>