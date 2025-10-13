<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../login-page.php");
    exit();
}

if (!isset($conn)) {
    include '../database/database.php';
}

$images_base_dir = 'images/'; // Web-relative path จาก project root
$buildings_upload_dir = $images_base_dir . 'buildings/';
$facilities_upload_dir = $images_base_dir . 'facilities/';
$equipments_upload_dir = $images_base_dir . 'equipments/';

$errors = [];
// พาธเริ่มต้นสำหรับ redirect ในกรณีเกิดข้อผิดพลาด
$redirect_url_on_error = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../admin-data_view-page.php';

function uploadImage($file_input_name, $target_dir, &$errors_ref, $old_pic_path = null) {
    global $images_base_dir;

    // กำหนด Project Root
    $project_root = dirname(dirname(__FILE__));

    // Directory ที่จะเก็บรูปภาพ
    $full_server_target_dir = $project_root . '/' . $target_dir;

    if (!is_dir($full_server_target_dir)) {
        if (!mkdir($full_server_target_dir, 0777, true)) {
            $errors_ref[] = "ไม่สามารถสร้างไดเรกทอรีสำหรับรูปภาพได้: " . $full_server_target_dir;
            return false;
        }
    }

    // ตรวจสอบการอัปโหลดไฟล์
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
        $file_name = basename($_FILES[$file_input_name]['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed_ext)) {
            $errors_ref[] = "ประเภทไฟล์รูปภาพสำหรับ " . $file_input_name . " ไม่ถูกต้อง อนุญาตเฉพาะ JPG, JPEG, PNG, GIF";
            return false;
        }

        $new_file_name = uniqid() . '.' . $file_ext;

        // พาธที่ใช้สำหรับ Browser ที่ถูกเก็บใน Database (Web Relative Path)
        $web_relative_path_to_store_in_db = $target_dir . $new_file_name;

        // พาธเต็มบน Server ที่ใช้สำหรับฟังก์ชัน PHP
        $full_server_upload_path = $full_server_target_dir . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $full_server_upload_path)) {
            // Only delete old pic if it exists, is a file within our base directory, and is different from the new upload path
            // และต้องสร้าง Full Server Path สำหรับ old_pic_path ด้วย
            if ($old_pic_path && file_exists($project_root . '/' . $old_pic_path) && is_file($project_root . '/' . $old_pic_path) && strpos($old_pic_path, $images_base_dir) === 0 && ($project_root . '/' . $old_pic_path) !== $full_server_upload_path) {
                unlink($project_root . '/' . $old_pic_path);
            }
            return $web_relative_path_to_store_in_db; // ส่งคืน Web Relative Path เพื่อเก็บใน DB
        } else {
            $errors_ref[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ " . $file_input_name . ": " . $_FILES[$file_input_name]['error'] . " (Path: " . $full_server_upload_path . ")";
            return false;
        }
    } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE) {
        $errors_ref[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ " . $file_input_name . ": " . $_FILES[$file_input_name]['error'];
        return false;
    }

    // หากไม่มีการอัปโหลดไฟล์ใหม่ ให้คืนค่า old_pic_path เดิม
    return $old_pic_path;
}

// ฟังก์ชันสำหรับลบไฟล์รูปภาพ
function deleteImageFile($file_path) {
    global $images_base_dir;
    $project_root = dirname(dirname(__FILE__)); // Project Root

    $full_server_file_path = $project_root . '/' . $file_path;

    // ตรวจสอบว่าไฟล์อยู่ในไดเรกทอรีที่อนุญาตและมีอยู่จริงก่อนลบ
    if ($file_path && file_exists($full_server_file_path) && is_file($full_server_file_path) && strpos($file_path, $images_base_dir) === 0) {
        return unlink($full_server_file_path);
    }
    return true; // คืนค่า true ถ้าไม่มีไฟล์ให้ลบ หรือ path ไม่ถูกต้อง (ถือว่าลบสำเร็จ)
}

// --- ประมวลผล POST Request จากฟอร์ม ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inject_type'])) {

    // กำหนด URL สำหรับ redirect ในกรณีเกิดข้อผิดพลาด
    // ใช้ referer เป็นค่าเริ่มต้น แต่จะถูก override ภายในแต่ละ block ถ้ามี redirect ที่เฉพาะเจาะจงกว่า
    $redirect_url_on_error = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../admin-data_view-page.php';

    // --- โค๊ดการสร้าง
    if ($_POST['inject_type'] === 'building') {
        $building_id = trim($_POST['building_id'] ?? '');
        $building_name = trim($_POST['building_name'] ?? '');
        $available = 'yes';

        $building_pic_path = uploadImage('building_pic', $buildings_upload_dir, $errors);

        if (empty($building_id)) { $errors[] = "กรุณากรอกหมายเลขอาคาร (Building Number)."; }
        if (empty($building_name)) { $errors[] = "กรุณากรอกชื่ออาคาร (Building Name)."; }

        if (!empty($errors)) {
            goto end_process;
        }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM buildings WHERE building_id = ?");
        if (!$stmt) {
            $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL เพื่อตรวจสอบอาคาร: " . $conn->error;
            goto end_process;
        }
        $stmt->bind_param("s", $building_id);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count > 0) {
            $errors[] = "หมายเลขอาคาร {$building_id} มีอยู่ในระบบแล้ว กรุณาใช้หมายเลขอื่น";
            goto end_process;
        }

        $stmt = $conn->prepare("INSERT INTO buildings (building_id, building_name, building_pic, available) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL เพื่อบันทึกอาคาร: " . $conn->error;
            goto end_process;
        }
        $stmt->bind_param("ssss", $building_id, $building_name, $building_pic_path, $available);
        if ($stmt->execute()) {
            header("Location: ../admin-data_view-page.php?mode=buildings&status=success&message=" . urlencode("เพิ่มอาคาร '{$building_name}' สำเร็จแล้ว!"));
            exit();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลอาคาร: " . $stmt->error;
            deleteImageFile($building_pic_path);
            goto end_process;
        }
        $stmt->close();
    }

    elseif ($_POST['inject_type'] === 'facility') {
        $facility_name = trim($_POST['facility_name'] ?? '');
        $facility_des = trim($_POST['facility_des'] ?? '');
        $building_id_for_facility = htmlspecialchars($_POST['building_id'] ?? '');
        $available_status_from_building = 'yes';
        if (!empty($building_id_for_facility)) {
            $stmt_get_building_status = $conn->prepare("SELECT available FROM buildings WHERE building_id = ?");
            if ($stmt_get_building_status) {
                $stmt_get_building_status->bind_param("s", $building_id_for_facility);
                $stmt_get_building_status->execute();
                $stmt_get_building_status->bind_result($building_available);
                $stmt_get_building_status->fetch();
                $stmt_get_building_status->close();
                $available_status_from_building = $building_available;
            } else {
                error_log("Failed to prepare statement to get building status: " . $conn->error);
            }
        }

        $facility_pic_path = uploadImage('facility_pic', $facilities_upload_dir, $errors);

        if (empty($facility_name)) { $errors[] = "กรุณากรอกชื่อสถานที่ (Facility Name)."; }
        if (empty($building_id_for_facility)) { $errors[] = "ไม่พบรหัสอาคารที่ถูกต้องสำหรับสถานที่นี้."; }

        if (!empty($errors)) {
            goto end_process;
        }

        $stmt = $conn->prepare("INSERT INTO facilities (building_id, facility_name, facility_des, facility_pic, available) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL เพื่อบันทึกสถานที่: " . $conn->error;
            goto end_process;
        }
        $stmt->bind_param("issss", $building_id_for_facility, $facility_name, $facility_des, $facility_pic_path, $available_status_from_building); // ใช้สถานะจากอาคารแม่
        if ($stmt->execute()) {
            header("Location: ../admin-data_view-page.php?mode=building_detail&building_id={$building_id_for_facility}&status=success&message=" . urlencode("เพิ่มสถานที่ '{$facility_name}' สำเร็จแล้ว!"));
            exit();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลสถานที่: " . $stmt->error;
            deleteImageFile($facility_pic_path);
            goto end_process;
        }
        $stmt->close();
    }

    elseif ($_POST['inject_type'] === 'equipment') {
        $equip_name = trim($_POST['equip_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $measure = trim($_POST['measure'] ?? '');
        $size = trim($_POST['size'] ?? '');
        $available = 'yes';

        $equip_pic_path = uploadImage('equip_pic', $equipments_upload_dir, $errors);

        if (empty($equip_name)) { $errors[] = "กรุณากรอกชื่ออุปกรณ์ (Equipment Name)."; }
        if ($quantity <= 0) { $errors[] = "กรุณาระบุจำนวนอุปกรณ์ที่ถูกต้อง (ต้องมากกว่า 0)."; }

        if (!empty($errors)) {
            goto end_process;
        }

        $stmt = $conn->prepare("INSERT INTO equipments (equip_name, quantity, measure, size, equip_pic, available) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL เพื่อบันทึกอุปกรณ์: " . $conn->error;
            goto end_process;
        }
        $stmt->bind_param("sissss", $equip_name, $quantity, $measure, $size, $equip_pic_path, $available);
        if ($stmt->execute()) {
            header("Location: ../admin-data_view-page.php?mode=equipment&status=success&message=" . urlencode("เพิ่มอุปกรณ์ '{$equip_name}' สำเร็จแล้ว!"));
            exit();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลอุปกรณ์: " . $stmt->error;
            deleteImageFile($equip_pic_path);
            goto end_process;
        }
        $stmt->close();
    }

    // โค๊ดการอัปเดต
    elseif ($_POST['inject_type'] === 'update_building') {
        $building_id = trim($_POST['building_id'] ?? '');
        $building_name = trim($_POST['building_name'] ?? '');
        $old_building_pic = trim($_POST['old_building_pic'] ?? '');
        $new_available_status = (isset($_POST['available']) && $_POST['available'] === 'yes') ? 'yes' : 'no';

        $redirect_url_on_error = "../admin-data_view-page.php?mode=edit_building&building_id=" . urlencode($building_id);

        if (empty($building_id)) { $errors[] = "ไม่พบรหัสอาคารที่ต้องการแก้ไข"; }
        if (empty($building_name)) { $errors[] = "กรุณากรอกชื่ออาคาร (Building Name)."; }

        if (!empty($errors)) {
            goto end_process;
        }

        $final_pic_path = uploadImage('building_pic', $buildings_upload_dir, $errors, $old_building_pic);

        if (!empty($errors)) { // ตรวจสอบ error จาก uploadImage
            goto end_process;
        }

        // 1. อัปเดตข้อมูลอาคาร
        $stmt_building = $conn->prepare("UPDATE buildings SET building_name = ?, building_pic = ?, available = ? WHERE building_id = ?");
        if (!$stmt_building) {
            $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับอาคาร: " . $conn->error;
            goto end_process;
        }
        $stmt_building->bind_param("ssss", $building_name, $final_pic_path, $new_available_status, $building_id);
        if ($stmt_building->execute()) {
            $stmt_building->close();

            // อัปเดตสถานะของสถานที่ทั้งหมดในอาคารนี้ให้ตรงกับสถานะของอาคาร
            $stmt_facilities = $conn->prepare("UPDATE facilities SET available = ? WHERE building_id = ?");
            if ($stmt_facilities) {
                $stmt_facilities->bind_param("ss", $new_available_status, $building_id);
                $stmt_facilities->execute();
                $stmt_facilities->close();
            } else {
                error_log("Failed to prepare statement for updating facilities status: " . $conn->error);
            }

            header("Location: ../admin-data_view-page.php?mode=building_detail&building_id={$building_id}&status=success&message=" . urlencode("แก้ไขอาคาร '{$building_name}' และสถานะสถานที่ในอาคารสำเร็จแล้ว!"));
            exit();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลอาคาร: " . $stmt_building->error;
            // ถ้า DB update ล้มเหลว และเป็นรูปภาพใหม่ ให้ลบรูปภาพนั้นทิ้ง
            if ($final_pic_path && $final_pic_path !== $old_building_pic) {
                deleteImageFile($final_pic_path);
            }
            goto end_process;
        }
        $stmt_building->close(); // ปิด statement ถ้าไม่ได้ใช้ goto
    }

    elseif ($_POST['inject_type'] === 'update_facility') {
        $facility_id = (int)($_POST['facility_id'] ?? 0);
        $facility_name = trim($_POST['facility_name'] ?? '');
        $facility_des = trim($_POST['facility_des'] ?? '');
        $new_building_id = htmlspecialchars($_POST['building_id'] ?? '');
        $old_facility_pic = trim($_POST['old_facility_pic'] ?? '');
        $new_available_status = (isset($_POST['available']) && $_POST['available'] === 'yes') ? 'yes' : 'no';

        $redirect_url_on_error = "../admin-data_view-page.php?mode=edit_facility&facility_id=" . urlencode($facility_id);

        if (empty($facility_id)) { $errors[] = "ไม่พบรหัสสถานที่ที่ต้องการแก้ไข"; }
        if (empty($facility_name)) { $errors[] = "กรุณากรอกชื่อสถานที่ (Facility Name)."; }
        if (empty($new_building_id)) { $errors[] = "ไม่พบรหัสอาคารที่ถูกต้องสำหรับสถานที่นี้."; }

        if (!empty($errors)) {
            goto end_process;
        }

        $building_available_status = 'yes';
        $stmt_get_building_status = $conn->prepare("SELECT available FROM buildings WHERE building_id = ?");
        if ($stmt_get_building_status) {
            $stmt_get_building_status->bind_param("s", $new_building_id);
            $stmt_get_building_status->execute();
            $stmt_get_building_status->bind_result($building_available_status);
            $stmt_get_building_status->fetch();
            $stmt_get_building_status->close();
        } else {
            error_log("Failed to prepare statement for checking building status: " . $conn->error);
        }

        // ถ้าอาคารแม่ไม่พร้อมใช้งาน (no) และพยายามตั้งสถานะสถานที่นี้เป็น 'yes'
        if ($building_available_status === 'no' && $new_available_status === 'yes') {
             $errors[] = "ไม่สามารถตั้งสถานะสถานที่แห่งนี้เป็น 'พร้อมใช้งาน' ได้ เนื่องจากอาคารหลัก 'ไม่พร้อมใช้งาน'";
             goto end_process;
        }

        $final_pic_path = uploadImage('facility_pic', $facilities_upload_dir, $errors, $old_facility_pic); // uploadImage จะจัดการลบรูปเก่าให้

        if (!empty($errors)) { 
            goto end_process;
        }

        $stmt = $conn->prepare("UPDATE facilities SET facility_name = ?, facility_des = ?, facility_pic = ?, building_id = ?, available = ? WHERE facility_id = ?");
        if (!$stmt) {
            $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            goto end_process;
        }
        $stmt->bind_param("sssiis", $facility_name, $facility_des, $final_pic_path, $new_building_id, $new_available_status, $facility_id);
        if ($stmt->execute()) {
            header("Location: ../admin-data_view-page.php?mode=building_detail&building_id={$new_building_id}&status=success&message=" . urlencode("แก้ไขสถานที่ '{$facility_name}' สำเร็จแล้ว!"));
            exit();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลสถานที่: " . $stmt->error;
            if ($final_pic_path && $final_pic_path !== $old_facility_pic) {
                deleteImageFile($final_pic_path);
            }
            goto end_process;
        }
        $stmt->close();
    }

    elseif ($_POST['inject_type'] === 'update_equipment') {
        $equip_id = (int)($_POST['equip_id'] ?? 0);
        $equip_name = trim($_POST['equip_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $measure = trim($_POST['measure'] ?? '');
        $size = trim($_POST['size'] ?? '');
        $old_equip_pic = trim($_POST['old_equip_pic'] ?? '');
        $new_available_status = (isset($_POST['available']) && $_POST['available'] === 'yes') ? 'yes' : 'no';

        $redirect_url_on_error = "../admin-data_view-page.php?mode=edit_equipment&equip_id=" . urlencode($equip_id);

        if (empty($equip_id)) { $errors[] = "ไม่พบรหัสอุปกรณ์ที่ต้องการแก้ไข"; }
        if (empty($equip_name)) { $errors[] = "กรุณากรอกชื่ออุปกรณ์ (Equipment Name)."; }
        if ($quantity <= 0) { $errors[] = "กรุณาระบุจำนวนอุปกรณ์ที่ถูกต้อง (ต้องมากกว่า 0)."; }

        if (!empty($errors)) {
            goto end_process;
        }

        $final_pic_path = uploadImage('equip_pic', $equipments_upload_dir, $errors, $old_equip_pic);

        if (!empty($errors)) { // ตรวจสอบ error จาก uploadImage
            goto end_process;
        }

        $stmt = $conn->prepare("UPDATE equipments SET equip_name = ?, quantity = ?, measure = ?, size = ?, equip_pic = ?, available = ? WHERE equip_id = ?");
        if (!$stmt) {
            $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            goto end_process;
        }
        $stmt->bind_param("sissssi", $equip_name, $quantity, $measure, $size, $final_pic_path, $new_available_status, $equip_id);
        if ($stmt->execute()) {
            header("Location: ../admin-data_view-page.php?mode=equip_detail&equip_id={$equip_id}&status=success&message=" . urlencode("แก้ไขอุปกรณ์ '{$equip_name}' สำเร็จแล้ว!"));
            exit();
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลอุปกรณ์: " . $stmt->error;
            // ถ้า DB update ล้มเหลว และเป็นรูปภาพใหม่ ให้ลบรูปภาพนั้นทิ้ง
            if ($final_pic_path && $final_pic_path !== $old_equip_pic) {
                deleteImageFile($final_pic_path);
            }
            goto end_process;
        }
        $stmt->close();
    }

    // โค๊ดการลบ (แก้ไขตามคำขอ: ลบเฉพาะตัวหลัก ไม่ลบคำร้องขอหรือข้อมูลเชื่อมโยง, และแสดงชื่อ)
    elseif ($_POST['inject_type'] === 'delete_building') {
        $building_id = trim($_POST['delete_id'] ?? '');
        $redirect_to = "../admin-data_view-page.php?mode=buildings";
        $redirect_url_on_error = $redirect_to; // ตั้งค่า redirect_url_on_error สำหรับการลบอาคาร
        $building_name_display = "ไม่ระบุ"; // Default name for display

        if (empty($building_id)) { $errors[] = "ไม่พบรหัสอาคารที่ต้องการลบ"; goto end_process; }

        try {
            // ดึงชื่ออาคารและรูปภาพก่อนลบ
            $stmt_get_building_info = $conn->prepare("SELECT building_name, building_pic FROM buildings WHERE building_id = ?");
            if (!$stmt_get_building_info) { throw new mysqli_sql_exception("Failed to prepare statement to get building info: " . $conn->error); }
            $stmt_get_building_info->bind_param("s", $building_id);
            $stmt_get_building_info->execute();
            $stmt_get_building_info->bind_result($building_name_display, $building_pic);
            $stmt_get_building_info->fetch();
            $stmt_get_building_info->close();

            // ลบอาคาร
            $stmt_delete_building = $conn->prepare("DELETE FROM buildings WHERE building_id = ?");
            if (!$stmt_delete_building) { throw new mysqli_sql_exception("Failed to prepare statement to delete building: " . $conn->error); }
            $stmt_delete_building->bind_param("s", $building_id);
            $stmt_delete_building->execute();

            if ($stmt_delete_building->affected_rows > 0) {
                deleteImageFile($building_pic); // ลบรูปภาพอาคาร
                header("Location: {$redirect_to}&status=success&message=" . urlencode("ลบอาคาร '{$building_name_display}' สำเร็จแล้ว!"));
                exit();
            } else {
                $errors[] = "ไม่พบอาคาร '{$building_name_display}' หรือไม่สามารถลบได้.";
                goto end_process;
            }
            $stmt_delete_building->close();

        } catch (mysqli_sql_exception $e) {
            $errors[] = "เกิดข้อผิดพลาดในการลบอาคาร '{$building_name_display}': " . $e->getMessage();
            goto end_process;
        }
    }

    elseif ($_POST['inject_type'] === 'delete_facility') {
        $facility_id = (int)($_POST['delete_id'] ?? 0);
        $building_id_for_redirect = htmlspecialchars($_POST['building_id_for_redirect'] ?? '');
        $redirect_to = "../admin-data_view-page.php?mode=building_detail&building_id={$building_id_for_redirect}";
        $redirect_url_on_error = $redirect_to; // ตั้งค่า redirect_url_on_error สำหรับการลบสถานที่
        $facility_name_display = "ไม่ระบุ"; // Default name for display

        if (empty($facility_id)) { $errors[] = "ไม่พบรหัสสถานที่ที่ต้องการลบ"; goto end_process; }

        try {
            // ดึงชื่อสถานที่และรูปภาพก่อนลบ
            $stmt_get_facility_info = $conn->prepare("SELECT facility_name, facility_pic FROM facilities WHERE facility_id = ?");
            if (!$stmt_get_facility_info) { throw new mysqli_sql_exception("Failed to prepare statement to get facility info: " . $conn->error); }
            $stmt_get_facility_info->bind_param("i", $facility_id);
            $stmt_get_facility_info->execute();
            $stmt_get_facility_info->bind_result($facility_name_display, $facility_pic);
            $stmt_get_facility_info->fetch();
            $stmt_get_facility_info->close();

            // ลบสถานที่
            $stmt_delete = $conn->prepare("DELETE FROM facilities WHERE facility_id = ?");
            if (!$stmt_delete) { throw new mysqli_sql_exception("Failed to prepare statement to delete facility: " . $conn->error); }
            $stmt_delete->bind_param("i", $facility_id);
            $stmt_delete->execute();

            if ($stmt_delete->affected_rows > 0) {
                deleteImageFile($facility_pic); // ลบรูปภาพที่เกี่ยวข้อง
                header("Location: {$redirect_to}&status=success&message=" . urlencode("ลบสถานที่ '{$facility_name_display}' สำเร็จแล้ว!"));
                exit();
            } else {
                $errors[] = "ไม่พบสถานที่ '{$facility_name_display}' หรือไม่สามารถลบได้.";
                goto end_process;
            }
            $stmt_delete->close();

        } catch (mysqli_sql_exception $e) {
            $errors[] = "เกิดข้อผิดพลาดในการลบสถานที่ '{$facility_name_display}': " . $e->getMessage();
            goto end_process;
        }
    }

    elseif ($_POST['inject_type'] === 'delete_equipment') {
        $equip_id = (int)($_POST['delete_id'] ?? 0);
        $redirect_to = "../admin-data_view-page.php?mode=equipment";
        $redirect_url_on_error = $redirect_to; // ตั้งค่า redirect_url_on_error สำหรับการลบอุปกรณ์
        $equip_name_display = "ไม่ระบุ"; // Default name for display

        if (empty($equip_id)) { $errors[] = "ไม่พบรหัสอุปกรณ์ที่ต้องการลบ"; goto end_process; }

        try {
            // ดึงชื่ออุปกรณ์และรูปภาพก่อนลบ
            $stmt_get_equip_info = $conn->prepare("SELECT equip_name, equip_pic FROM equipments WHERE equip_id = ?");
            if (!$stmt_get_equip_info) { throw new mysqli_sql_exception("Failed to prepare statement to get equipment info: " . $conn->error); }
            $stmt_get_equip_info->bind_param("i", $equip_id);
            $stmt_get_equip_info->execute();
            $stmt_get_equip_info->bind_result($equip_name_display, $equip_pic);
            $stmt_get_equip_info->fetch();
            $stmt_get_equip_info->close();

            // ลบอุปกรณ์
            $stmt_delete = $conn->prepare("DELETE FROM equipments WHERE equip_id = ?");
            if (!$stmt_delete) { throw new mysqli_sql_exception("Failed to prepare statement to delete equipment: " . $conn->error); }
            $stmt_delete->bind_param("i", $equip_id);
            $stmt_delete->execute();

            if ($stmt_delete->affected_rows > 0) {
                deleteImageFile($equip_pic); // ลบรูปภาพที่เกี่ยวข้อง
                header("Location: {$redirect_to}&status=success&message=" . urlencode("ลบอุปกรณ์ '{$equip_name_display}' สำเร็จแล้ว!"));
                exit();
            } else {
                $errors[] = "ไม่พบอุปกรณ์ '{$equip_name_display}' หรือไม่สามารถลบได้.";
                goto end_process;
            }
            $stmt_delete->close();

        } catch (mysqli_sql_exception $e) {
            $errors[] = "เกิดข้อผิดพลาดในการลบอุปกรณ์ '{$equip_name_display}': " . $e->getMessage();
            goto end_process;
        }
    }
    else {
        // หาก inject_type ไม่ตรงกับกรณีใดๆ เลย
        $errors[] = "inject_type ที่ไม่รู้จัก: " . ($_POST['inject_type'] ?? 'ไม่ระบุ');
    }

}

end_process:

// แจ้งผลลัพธ์หากมี error เกิดขึ้น
if (!empty($errors)) {
    $redirect_param_glue = strpos($redirect_url_on_error, '?') === false ? '?' : '&';
    header("Location: {$redirect_url_on_error}{$redirect_param_glue}status=error&message=" . urlencode(implode(", ", $errors)));
    exit();
}
?>