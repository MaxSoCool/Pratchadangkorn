<?php

$images_base_dir = 'images/';

$buildings_upload_dir = $images_base_dir . 'buildings/';
$facilities_upload_dir = $images_base_dir . 'facilities/';
$equipments_upload_dir = $images_base_dir . 'equipments/';

function uploadImage($file_input_name, $target_dir, &$errors) {
    if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES[$file_input_name]['tmp_name'];
        $file_name = basename($_FILES[$file_input_name]['name']);
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];

        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = "ประเภทไฟล์รูปภาพสำหรับ " . $file_input_name . " ไม่ถูกต้อง อนุญาตเฉพาะ JPG, JPEG, PNG, GIF";
            return false;
        }

        $new_file_name = uniqid() . '.' . $file_ext;
        $upload_path = $target_dir . $new_file_name;

        if (move_uploaded_file($file_tmp_name, $upload_path)) {
            return $upload_path; 
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ " . $file_input_name . ": Error Code " . $_FILES[$file_input_name]['error'];
            return false;
        }
    } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE) {
        $errors[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ " . $file_input_name . ": Error Code " . $_FILES[$file_input_name]['error'];
        return false;
    }
    return null; 
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inject_type']) && $_POST['inject_type'] === 'building') {
    $building_id = trim($_POST['building_id'] ?? '');
    $building_name = trim($_POST['building_name'] ?? '');
    $building_pic_path = uploadImage('building_pic', $buildings_upload_dir, $errors);

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
                header("Location: ?step=1&status=success");
                exit();
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลอาคาร: " . $stmt->error;
                if ($building_pic_path && file_exists($building_pic_path)) {
                    unlink($building_pic_path);
                }
            }
            $stmt->close();
        }
    }
}

// --- Equipments Injection ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inject_type']) && $_POST['inject_type'] === 'equipment') {
    $equip_id = trim($_POST['equip_id'] ?? '');
    $equip_name = trim($_POST['equip_name'] ?? '');
    $measure = trim($_POST['measure'] ?? '');
    $equip_pic_path = uploadImage('equip_pic', $equipments_upload_dir, $errors);

    if (empty($equip_name)) {
        $errors[] = "กรุณากรอกชื่ออุปกรณ์ (Equipment Name).";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO equipments (equip_id, equip_name, measure, equip_pic) VALUES (?, ?, ?, ?)");
        if (!$stmt) {
            $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
        } else {
            $stmt->bind_param("ssss", $equip_id, $equip_name, $measure, $equip_pic_path);
            if ($stmt->execute()) {
                header("Location: ?step=2&status=success");
                exit();
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลอุปกรณ์: " . $stmt->error;
                if ($equip_pic_path && file_exists($equip_pic_path)) {
                    unlink($equip_pic_path);
                }
            }
            $stmt->close();
        }
    }
}

// --- Facilities Injection ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inject_type']) && $_POST['inject_type'] === 'facility') {
    $facility_id = trim($_POST['facility_id'] ?? '');
    $facility_name = trim($_POST['facility_name'] ?? '');
    $facility_pic_path = uploadImage('facility_pic', $facilities_upload_dir, $errors);

    if (empty($facility_name)) {
        $errors[] = "กรุณากรอกชื่อสถานที่ (Facility Name).";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO facilities (facility_id, facility_name, facility_pic) VALUES (?, ?, ?)");
        if (!$stmt) {
            $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
        } else {
            $stmt->bind_param("sss", $facility_id, $facility_name, $facility_pic_path);
            if ($stmt->execute()) {
                header("Location: ?step=3&status=success");
                exit();
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลสถานที่: " . $stmt->error;
                if ($facility_pic_path && file_exists($facility_pic_path)) {
                    unlink($facility_pic_path);
                }
            }
            $stmt->close();
        }
    }
}
?>