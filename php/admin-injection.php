<?php
// php/admin-injection.php

// ไฟล์นี้คาดหวังว่า $conn, $errors, $success_message
// และตัวแปรอื่นๆ จากไฟล์หลัก (admin-data_view-page.php) จะพร้อมใช้งานอยู่แล้ว

// กำหนด base directories สำหรับการอัปโหลด
$images_base_dir = 'images/';
$buildings_upload_dir = $images_base_dir . 'buildings/';
$facilities_upload_dir = $images_base_dir . 'facilities/';
$equipments_upload_dir = $images_base_dir . 'equipments/';

// สร้าง folder ถ้ายังไม่มี
if (!is_dir($buildings_upload_dir)) mkdir($buildings_upload_dir, 0755, true);
if (!is_dir($facilities_upload_dir)) mkdir($facilities_upload_dir, 0755, true);
if (!is_dir($equipments_upload_dir)) mkdir($equipments_upload_dir, 0755, true);


// ฟังก์ชันช่วยในการอัปโหลดรูปภาพ
// คาดหวังว่า $errors array จะถูกส่งมาแบบ reference (&) จากไฟล์หลัก
function uploadImage($file_input_name, $target_dir, &$errors_ref) {
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
        $upload_path = $target_dir . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $upload_path)) {
            return $upload_path; 
        } else {
            $errors_ref[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ " . $file_input_name . ": Error Code " . $_FILES[$file_input_name]['error'];
            return false;
        }
    } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE) {
        $errors_ref[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ " . $file_input_name . ": Error Code " . $_FILES[$file_input_name]['error'];
        return false;
    }
    return null; 
}


// --- Logic สำหรับการประมวลผล POST Request จากฟอร์ม ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inject_type'])) {
    
    // ใช้ $errors จากไฟล์หลัก
    global $conn, $errors;

    if ($_POST['inject_type'] === 'building') {
        $building_id = trim($_POST['building_id'] ?? '');
        $building_name = trim($_POST['building_name'] ?? '');
        $building_pic_path = uploadImage('building_pic', $buildings_upload_dir, $errors);

        if (empty($building_id)) {
            $errors[] = "กรุณากรอกหมายเลขอาคาร (Building Number).";
        }
        if (empty($building_name)) {
            $errors[] = "กรุณากรอกชื่ออาคาร (Building Name).";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO buildings (building_id, building_name, building_pic) VALUES (?, ?, ?)");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("sss", $building_id, $building_name, $building_pic_path);
                if ($stmt->execute()) {
                    header("Location: admin-data_view-page.php?mode=buildings&status=success&message=" . urlencode("เพิ่มอาคาร '{$building_name}' สำเร็จแล้ว!"));
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลอาคาร: " . $stmt->error;
                    if ($building_pic_path && file_exists($building_pic_path)) { unlink($building_pic_path); }
                }
                $stmt->close();
            }
        }
    }

    elseif ($_POST['inject_type'] === 'facility') {
        $facility_name = trim($_POST['facility_name'] ?? '');
        $facility_des = trim($_POST['facility_des'] ?? '');
        $building_id_for_facility = (int)($_POST['building_id'] ?? 0);
        $facility_pic_path = uploadImage('facility_pic', $facilities_upload_dir, $errors);

        if (empty($facility_name)) {
            $errors[] = "กรุณากรอกชื่อสถานที่ (Facility Name).";
        }
        if (empty($building_id_for_facility) || $building_id_for_facility === 0) {
            $errors[] = "ไม่พบรหัสอาคารที่ถูกต้องสำหรับสถานที่นี้.";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO facilities (building_id, facility_name, facility_des, facility_pic) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("isss", $building_id_for_facility, $facility_name, $facility_des, $facility_pic_path);
                if ($stmt->execute()) {
                    header("Location: admin-data_view-page.php?mode=building_detail&building_id={$building_id_for_facility}&status=success&message=" . urlencode("เพิ่มสถานที่ '{$facility_name}' สำเร็จแล้ว!"));
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลสถานที่: " . $stmt->error;
                    if ($facility_pic_path && file_exists($facility_pic_path)) { unlink($facility_pic_path); }
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
        $equip_pic_path = uploadImage('equip_pic', $equipments_upload_dir, $errors);

        if (empty($equip_name)) {
            $errors[] = "กรุณากรอกชื่ออุปกรณ์ (Equipment Name).";
        }
        if ($quantity <= 0) {
            $errors[] = "กรุณาระบุจำนวนอุปกรณ์ที่ถูกต้อง (ต้องมากกว่า 0).";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO equipments (equip_name, quantity, measure, size, equip_pic) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("sisss", $equip_name, $quantity, $measure, $size, $equip_pic_path);
                if ($stmt->execute()) {
                    header("Location: admin-data_view-page.php?mode=equipment&status=success&message=" . urlencode("เพิ่มอุปกรณ์ '{$equip_name}' สำเร็จแล้ว!"));
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลอุปกรณ์: " . $stmt->error;
                    if ($equip_pic_path && file_exists($equip_pic_path)) { unlink($equip_pic_path); }
                }
                $stmt->close();
            }
        }
    }
}
?>