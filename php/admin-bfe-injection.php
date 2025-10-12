<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบการเข้าสู่ระบบของเจ้าหน้าที่
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
$redirect_url_on_error = '../admin-data_view-page.php';

function uploadImage($file_input_name, $target_dir, &$errors_ref, $old_pic_path = null) {
    global $images_base_dir; 

    // กำหนด Project Root 
    $project_root = dirname(dirname(__FILE__));

    // Directory ที่จะเก็บรูปภาพ
    $full_server_target_dir = $project_root . '/' . $target_dir;

    if (!is_dir($full_server_target_dir)) {
        mkdir($full_server_target_dir, 0777, true);
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
    return true;
}

// --- ประมวลผล POST Request จากฟอร์ม ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inject_type'])) {

    // กำหนด URL สำหรับ redirect ในกรณีเกิดข้อผิดพลาด
    $redirect_url_on_error = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../admin-data_view-page.php';

    // --- โค๊ดการสร้าง
    if ($_POST['inject_type'] === 'building') {
        $building_id = trim($_POST['building_id'] ?? '');
        $building_name = trim($_POST['building_name'] ?? '');
        $available = 'yes';

        $building_pic_path = uploadImage('building_pic', $buildings_upload_dir, $errors);

        if (empty($building_id)) { $errors[] = "กรุณากรอกหมายเลขอาคาร (Building Number)."; }
        if (empty($building_name)) { $errors[] = "กรุณากรอกชื่ออาคาร (Building Name)."; }

        $stmt = $conn->prepare("SELECT COUNT(*) FROM buildings WHERE building_id = ?");
        if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; }
        else {
            $stmt->bind_param("s", $building_id);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count > 0) {
                header("Location: ../admin-data_view-page.php?mode=add_building&status=error&message=" . urlencode("หมายเลขอาคาร {$building_id} มีอยู่ในระบบแล้ว กรุณาใช้หมายเลขอื่น"));
                exit();
            }
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO buildings (building_id, building_name, building_pic, available) VALUES (?, ?, ?, ?)");
            if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; }
            else {
                $stmt->bind_param("ssss", $building_id, $building_name, $building_pic_path, $available);
                if ($stmt->execute()) {
                    header("Location: ../admin-data_view-page.php?mode=buildings&status=success&message=" . urlencode("เพิ่มอาคาร '{$building_name}' สำเร็จแล้ว!"));
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลอาคาร: " . $stmt->error;
                    deleteImageFile($building_pic_path); 
                }
                $stmt->close();
            }
        }
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

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO facilities (building_id, facility_name, facility_des, facility_pic, available) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; }
            else {
                $stmt->bind_param("issss", $building_id_for_facility, $facility_name, $facility_des, $facility_pic_path, $available_status_from_building); // ใช้สถานะจากอาคารแม่
                if ($stmt->execute()) {
                    header("Location: ../admin-data_view-page.php?mode=building_detail&building_id={$building_id_for_facility}&status=success&message=" . urlencode("เพิ่มสถานที่ '{$facility_name}' สำเร็จแล้ว!"));
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลสถานที่: " . $stmt->error;
                    deleteImageFile($facility_pic_path);
                }
                $stmt->close();
            }
        }
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

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO equipments (equip_name, quantity, measure, size, equip_pic, available) VALUES (?, ?, ?, ?, ?, ?)");
            if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; }
            else {
                $stmt->bind_param("sissss", $equip_name, $quantity, $measure, $size, $equip_pic_path, $available);
                if ($stmt->execute()) {
                    header("Location: ../admin-data_view-page.php?mode=equipment&status=success&message=" . urlencode("เพิ่มอุปกรณ์ '{$equip_name}' สำเร็จแล้ว!"));
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลอุปกรณ์: " . $stmt->error;
                    deleteImageFile($equip_pic_path);
                }
                $stmt->close();
            }
        }
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

        if (empty($errors)) {
            $final_pic_path = uploadImage('building_pic', $buildings_upload_dir, $errors, $old_building_pic);

            if ($final_pic_path === false && isset($_FILES['building_pic']) && $_FILES['building_pic']['error'] != UPLOAD_ERR_NO_FILE) {
            } else {
                // 1. อัปเดตข้อมูลอาคาร
                $stmt_building = $conn->prepare("UPDATE buildings SET building_name = ?, building_pic = ?, available = ? WHERE building_id = ?");
                if (!$stmt_building) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับอาคาร: " . $conn->error; }
                else {
                    $stmt_building->bind_param("ssss", $building_name, $final_pic_path, $new_available_status, $building_id);
                    if ($stmt_building->execute()) {
                        $stmt_building->close(); 

                        // อัปเดตสถานะของสถานที่ทั้งหมดในอาคารนี้ให้ตรงกับสถานะของอาคาร**
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
                    }
                }
            }
        }
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

        if (empty($errors)) {
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
                 $redirect_param_glue = strpos($redirect_url_on_error, '?') === false ? '?' : '&';
                 header("Location: {$redirect_url_on_error}{$redirect_param_glue}status=error&message=" . urlencode(implode(", ", $errors)));
                 exit();
            }

            $final_pic_path = uploadImage('facility_pic', $facilities_upload_dir, $errors, $old_facility_pic); // uploadImage จะจัดการลบรูปเก่าให้

            if ($final_pic_path === false && isset($_FILES['facility_pic']) && $_FILES['facility_pic']['error'] != UPLOAD_ERR_NO_FILE) {
            } else {
                $stmt = $conn->prepare("UPDATE facilities SET facility_name = ?, facility_des = ?, facility_pic = ?, building_id = ?, available = ? WHERE facility_id = ?");
                if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; }
                else {
                    $stmt->bind_param("sssiis", $facility_name, $facility_des, $final_pic_path, $new_building_id, $new_available_status, $facility_id);
                    if ($stmt->execute()) {
                        header("Location: ../admin-data_view-page.php?mode=building_detail&building_id={$new_building_id}&status=success&message=" . urlencode("แก้ไขสถานที่ '{$facility_name}' สำเร็จแล้ว!"));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลสถานที่: " . $stmt->error;
                        // ถ้า DB update ล้มเหลว และเป็นรูปภาพใหม่ ให้ลบรูปภาพนั้นทิ้ง
                        if ($final_pic_path && $final_pic_path !== $old_facility_pic) {
                            deleteImageFile($final_pic_path);
                        }
                    }
                    $stmt->close();
                }
            }
        }
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

        if (empty($errors)) {
            $final_pic_path = uploadImage('equip_pic', $equipments_upload_dir, $errors, $old_equip_pic);

            if ($final_pic_path === false && isset($_FILES['equip_pic']) && $_FILES['equip_pic']['error'] != UPLOAD_ERR_NO_FILE) {
            } else {
                $stmt = $conn->prepare("UPDATE equipments SET equip_name = ?, quantity = ?, measure = ?, size = ?, equip_pic = ?, available = ? WHERE equip_id = ?");
                if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; }
                else {
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
                    }
                    $stmt->close();
                }
            }
        }
    }

    // โค๊ดการลบ
    elseif ($_POST['inject_type'] === 'delete_building') {
        $building_id = trim($_POST['delete_id'] ?? '');
        $redirect_to = "../admin-data_view-page.php?mode=buildings";

        if (empty($building_id)) { $errors[] = "ไม่พบรหัสอาคารที่ต้องการลบ"; }

        if (empty($errors)) {
            try {
                // ดึงรูปภาพทั้งหมดของการจองที่เกี่ยวข้องกับสถานที่ในอาคารนี้ก่อน
                $stmt_get_request_pics = $conn->prepare("SELECT R.request_pic FROM requests R JOIN facilities F ON R.facility_id = F.facility_id WHERE F.building_id = ?");
                if ($stmt_get_request_pics) {
                    $stmt_get_request_pics->bind_param("s", $building_id);
                    $stmt_get_request_pics->execute();
                    $result_request_pics = $stmt_get_request_pics->get_result();
                    while ($row = $result_request_pics->fetch_assoc()) {
                        deleteImageFile($row['request_pic']);
                    }
                    $stmt_get_request_pics->close();
                } else {
                    error_log("Warning: Could not prepare statement to get request pics for facilities in building: " . $conn->error);
                }

                // ลบการจองทั้งหมดที่เกี่ยวข้องกับสถานที่ในอาคารนี้
                $stmt_delete_requests_for_building = $conn->prepare("DELETE R FROM requests R JOIN facilities F ON R.facility_id = F.facility_id WHERE F.building_id = ?");
                if ($stmt_delete_requests_for_building) {
                    $stmt_delete_requests_for_building->bind_param("s", $building_id);
                    $stmt_delete_requests_for_building->execute();
                    $stmt_delete_requests_for_building->close();
                } else {
                    error_log("Warning: Could not prepare statement to delete requests for facilities in building: " . $conn->error);
                }

                // ดึงรูปภาพทั้งหมดของสถานที่ในอาคารนี้ก่อน
                $stmt_get_facility_pics = $conn->prepare("SELECT facility_pic FROM facilities WHERE building_id = ?");
                if ($stmt_get_facility_pics) {
                    $stmt_get_facility_pics->bind_param("s", $building_id);
                    $stmt_get_facility_pics->execute();
                    $result_facility_pics = $stmt_get_facility_pics->get_result();
                    while ($row = $result_facility_pics->fetch_assoc()) {
                        deleteImageFile($row['facility_pic']);
                    }
                    $stmt_get_facility_pics->close();
                } else {
                    error_log("Warning: Could not prepare statement to get facility pics in building: " . $conn->error);
                }

                // ลบสถานที่ทั้งหมดในอาคารนี้
                $stmt_delete_facilities = $conn->prepare("DELETE FROM facilities WHERE building_id = ?");
                if ($stmt_delete_facilities) {
                    $stmt_delete_facilities->bind_param("s", $building_id);
                    $stmt_delete_facilities->execute();
                    $stmt_delete_facilities->close();
                } else {
                    error_log("Warning: Could not prepare statement to delete facilities in building: " . $conn->error);
                }

                // ดึงรูปภาพอาคารก่อนลบ
                $stmt_get_building_pic = $conn->prepare("SELECT building_pic FROM buildings WHERE building_id = ?");
                if ($stmt_get_building_pic) {
                    $stmt_get_building_pic->bind_param("s", $building_id);
                    $stmt_get_building_pic->execute();
                    $stmt_get_building_pic->bind_result($building_pic);
                    $stmt_get_building_pic->fetch();
                    $stmt_get_building_pic->close();
                } else {
                    $building_pic = null;
                    error_log("Warning: Could not prepare statement to get building pic: " . $conn->error);
                }

                // ลบอาคาร
                $stmt_delete_building = $conn->prepare("DELETE FROM buildings WHERE building_id = ?");
                if ($stmt_delete_building) {
                    $stmt_delete_building->bind_param("s", $building_id);
                    $stmt_delete_building->execute();
                    $stmt_delete_building->close();
                } else {
                    error_log("Warning: Could not prepare statement to delete building: " . $conn->error);
                    throw new mysqli_sql_exception("Failed to delete building due to SQL error.");
                }

                deleteImageFile($building_pic); // ลบรูปภาพอาคาร

                header("Location: {$redirect_to}&status=success&message=" . urlencode("ลบอาคารและข้อมูลที่เกี่ยวข้องทั้งหมดสำเร็จแล้ว!"));
                exit();

            } catch (mysqli_sql_exception $e) {
                $errors[] = "เกิดข้อผิดพลาดในการลบข้อมูลอาคารและสถานที่: " . $e->getMessage();
            }
        }
        $redirect_url_on_error = $redirect_to; // ตั้งค่า redirect_url_on_error สำหรับการลบอาคาร
    }

    elseif ($_POST['inject_type'] === 'delete_facility') {
        $facility_id = (int)($_POST['delete_id'] ?? 0);
        $building_id_for_redirect = htmlspecialchars($_POST['building_id_for_redirect'] ?? '');
        $redirect_to = "../admin-data_view-page.php?mode=building_detail&building_id={$building_id_for_redirect}";

        if (empty($facility_id)) { $errors[] = "ไม่พบรหัสสถานที่ที่ต้องการลบ"; }

        if (empty($errors)) {
            try {
                // ดึงรูปภาพสถานที่ก่อนลบ
                $stmt_get_pic = $conn->prepare("SELECT facility_pic FROM facilities WHERE facility_id = ?");
                if ($stmt_get_pic) {
                    $stmt_get_pic->bind_param("i", $facility_id);
                    $stmt_get_pic->execute();
                    $stmt_get_pic->bind_result($facility_pic);
                    $stmt_get_pic->fetch();
                    $stmt_get_pic->close();
                } else {
                    $facility_pic = null;
                    error_log("Warning: Could not prepare statement to get facility pic: " . $conn->error);
                }


                // ดึงรูปภาพทั้งหมดของการจองที่เกี่ยวข้องกับสถานที่นี้ก่อน (ถ้ามี)
                $stmt_get_request_pics = $conn->prepare("SELECT request_pic FROM requests WHERE facility_id = ?");
                if ($stmt_get_request_pics) {
                    $stmt_get_request_pics->bind_param("i", $facility_id);
                    $stmt_get_request_pics->execute();
                    $result_request_pics = $stmt_get_request_pics->get_result();
                    while ($row = $result_request_pics->fetch_assoc()) {
                        deleteImageFile($row['request_pic']);
                    }
                    $stmt_get_request_pics->close();
                } else {
                    error_log("Warning: Could not prepare statement to get request pics for facility: " . $conn->error);
                }

                // ลบการจองที่เกี่ยวข้องกับสถานที่นี้ (ถ้ามีตาราง booking/requests ที่อ้างอิง facility_id)
                // ตรวจสอบว่าตาราง `requests` มีคอลัมน์ `facility_id` หรือไม่ หรือมีตาราง `facility_bookings` แยกต่างหาก
                $stmt_delete_requests = $conn->prepare("DELETE FROM requests WHERE facility_id = ?"); // ปรับเปลี่ยนตามชื่อตารางจองของคุณ
                if ($stmt_delete_requests) {
                    $stmt_delete_requests->bind_param("i", $facility_id);
                    $stmt_delete_requests->execute();
                    $stmt_delete_requests->close();
                } else {
                     error_log("Warning: Could not prepare statement to delete related requests for facility: " . $conn->error);
                }


                // ลบสถานที่
                $stmt_delete = $conn->prepare("DELETE FROM facilities WHERE facility_id = ?");
                if ($stmt_delete) {
                    $stmt_delete->bind_param("i", $facility_id);
                    $stmt_delete->execute();
                    $stmt_delete->close();
                } else {
                    error_log("Warning: Could not prepare statement to delete facility: " . $conn->error);
                    throw new mysqli_sql_exception("Failed to delete facility due to SQL error.");
                }


                deleteImageFile($facility_pic); // ลบรูปภาพที่เกี่ยวข้อง

                header("Location: {$redirect_to}&status=success&message=" . urlencode("ลบสถานที่และข้อมูลที่เกี่ยวข้องสำเร็จแล้ว!"));
                exit();

            } catch (mysqli_sql_exception $e) {
                $errors[] = "เกิดข้อผิดพลาดในการลบข้อมูลสถานที่: " . $e->getMessage();
            }
        }
        $redirect_url_on_error = $redirect_to; // ตั้งค่า redirect_url_on_error สำหรับการลบสถานที่
    }

    elseif ($_POST['inject_type'] === 'delete_equipment') {
        $equip_id = (int)($_POST['delete_id'] ?? 0);
        $redirect_to = "../admin-data_view-page.php?mode=equipment";

        if (empty($equip_id)) { $errors[] = "ไม่พบรหัสอุปกรณ์ที่ต้องการลบ"; }

        if (empty($errors)) {
            try {
                // ดึงรูปภาพอุปกรณ์ก่อนลบ
                $stmt_get_pic = $conn->prepare("SELECT equip_pic FROM equipments WHERE equip_id = ?");
                if ($stmt_get_pic) {
                    $stmt_get_pic->bind_param("i", $equip_id);
                    $stmt_get_pic->execute();
                    $stmt_get_pic->bind_result($equip_pic);
                    $stmt_get_pic->fetch();
                    $stmt_get_pic->close();
                } else {
                    $equip_pic = null;
                    error_log("Warning: Could not prepare statement to get equipment pic: " . $conn->error);
                }


                // ดึงรูปภาพทั้งหมดของการจองอุปกรณ์ที่เกี่ยวข้องกับอุปกรณ์นี้ก่อน (ถ้ามี)
                $stmt_get_booking_equip_pics = $conn->prepare("SELECT booking_equip_pic FROM booking_equipments WHERE equip_id = ?"); // สมมติว่ามีคอลัมน์ booking_equip_pic
                if ($stmt_get_booking_equip_pics) {
                    $stmt_get_booking_equip_pics->bind_param("i", $equip_id);
                    $stmt_get_booking_equip_pics->execute();
                    $result_booking_equip_pics = $stmt_get_booking_equip_pics->get_result();
                    while ($row = $result_booking_equip_pics->fetch_assoc()) {
                        deleteImageFile($row['booking_equip_pic']);
                    }
                    $stmt_get_booking_equip_pics->close();
                } else {
                    error_log("Warning: Could not prepare statement to get booking equipment pics: " . $conn->error);
                }

                // ลบการจองอุปกรณ์ที่เกี่ยวข้อง
                $stmt_delete_bookings = $conn->prepare("DELETE FROM booking_equipments WHERE equip_id = ?"); // ปรับเปลี่ยนตามชื่อตารางจองอุปกรณ์ของคุณ
                if ($stmt_delete_bookings) {
                    $stmt_delete_bookings->bind_param("i", $equip_id);
                    $stmt_delete_bookings->execute();
                    $stmt_delete_bookings->close();
                } else {
                    error_log("Warning: Could not prepare statement to delete related equipment bookings: " . $conn->error);
                }


                // ลบอุปกรณ์
                $stmt_delete = $conn->prepare("DELETE FROM equipments WHERE equip_id = ?");
                if (!$stmt_delete) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; }
                else {
                    $stmt_delete->bind_param("i", $equip_id);
                    if ($stmt_delete->execute()) {
                        deleteImageFile($equip_pic); // ลบรูปภาพที่เกี่ยวข้อง
                        header("Location: {$redirect_to}&status=success&message=" . urlencode("ลบอุปกรณ์และข้อมูลที่เกี่ยวข้องสำเร็จแล้ว!"));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการลบข้อมูลอุปกรณ์: " . $stmt_delete->error;
                    }
                    $stmt_delete->close();
                }

            } catch (mysqli_sql_exception $e) {
                $errors[] = "เกิดข้อผิดพลาดในการลบข้อมูลอุปกรณ์: " . $e->getMessage();
            }
        }
        $redirect_url_on_error = $redirect_to; // ตั้งค่า redirect_url_on_error สำหรับการลบอุปกรณ์
    }

    // แจ้งผลลัพธ์หากมี error เกิดขึ้น
    if (!empty($errors)) {
        $redirect_param_glue = strpos($redirect_url_on_error, '?') === false ? '?' : '&';
        header("Location: {$redirect_url_on_error}{$redirect_param_glue}status=error&message=" . urlencode(implode(", ", $errors)));
        exit();
    }
}
?>