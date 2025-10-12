<?php
if (!isset($conn)) {
    include dirname(__DIR__) . '/database/database.php';
}

// ตรวจสอบตัวแปรที่ถูกส่งมาจากการ include file หลัก (admin-main-page.php)
if (!isset($staff_id_for_db)) {
    // หาก staff_id_for_db ไม่ถูกส่งมา ให้ลองดึงจาก session โดยตรง
    $staff_id_for_db = $_SESSION['staff_id'] ?? null;
    if (empty($staff_id_for_db) || $staff_id_for_db === 'N/A') {
        error_log("admin-req-injection.php: Staff ID not found in session for POST request.");
        if (!isset($GLOBALS['errors'])) $GLOBALS['errors'] = [];
        $GLOBALS['errors'][] = "ไม่สามารถดำเนินการได้: Staff ID ไม่ถูกต้องหรือไม่พบสำหรับผู้ดูแลระบบที่ล็อกอินอยู่. โปรดตรวจสอบการตั้งค่าผู้ใช้หรือติดต่อผู้ดูแลระบบ.";
        return; 
    }
}
if (!isset($errors)) $errors = [];
if (!isset($success_message)) $success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if (($_POST['action'] == 'approve_request' || $_POST['action'] == 'reject_request')) {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $requestType = $_POST['request_type'] ?? '';
        $approveStatus = ($_POST['action'] == 'approve_request') ? 'อนุมัติ' : 'ไม่อนุมัติ';
        $approveDetail = trim($_POST['approve_detail'] ?? '');
        $staffIdToUse = $staff_id_for_db; // ตัวแปรจาก admin-main-page.php
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
            // ตรวจสอบสถานะปัจจุบันของคำร้องขอก่อนดำเนินการ
            $current_status_sql = "SELECT writed_status, approve FROM {$tableName} WHERE {$idColumn} = ?";
            $stmt_current_status = $conn->prepare($current_status_sql);
            if ($stmt_current_status) {
                $stmt_current_status->bind_param("i", $requestId);
                $stmt_current_status->execute();
                $result_current_status = $stmt_current_status->get_result();
                $current_request = $result_current_status->fetch_assoc();
                $stmt_current_status->close();

                // อนุญาตให้ดำเนินการได้ถ้าสถานะเป็น 'ส่งคำร้องขอ' และยังไม่เคยถูกอนุมัติ/ไม่อนุมัติ/ยกเลิก
                if ($current_request && $current_request['writed_status'] === 'ส่งคำร้องขอ' && ($current_request['approve'] === null || $current_request['approve'] === '')) {
                    $stmt = $conn->prepare("UPDATE {$tableName} SET approve = ?, approve_date = ?, approve_detail = ?, staff_id = ? WHERE {$idColumn} = ?");
                    $stmt->bind_param("sssi", $approveStatus, $approveDate, $approveDetail, $staffIdToUse, $requestId);
                    if ($stmt->execute()) {
                        $success_message = "ดำเนินการ {$approveStatus} คำร้องขอสำเร็จแล้ว!";
                        // Redirect เพื่อป้องกันการ submit ซ้ำ
                        header("Location: ?main_tab={$main_tab}&mode=detail&id={$requestId}&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการบันทึกการดำเนินการ: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $errors[] = "ไม่สามารถดำเนินการกับคำร้องนี้ได้. สถานะคำร้องไม่ถูกต้องหรือถูกดำเนินการไปแล้ว.";
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบสถานะคำร้องขอ: " . $conn->error;
            }

        } else {
            $errors[] = "ข้อมูลคำร้องไม่ถูกต้องสำหรับการดำเนินการ.";
        }
    }

    if (!empty($errors)) {
        // สร้าง URL กลับไปยังหน้าเดิมพร้อม parameters และ error message
        $redirect_url_params = $_GET; // ดึง parameters ปัจจุบัน
        $redirect_url_params['status'] = 'error';
        // ใช้ implode เพื่อรวม error messages เข้าด้วยกัน
        $redirect_url_params['message'] = urlencode(implode(", ", $errors));
        header("Location: ?" . http_build_query($redirect_url_params));
        exit();
    }
}
?>