<?php
include(__DIR__ . '/../database/database.php'); 
$images_base_dir = 'images/';

$buildings_upload_dir = $images_base_dir . 'buildings/';
$facilities_upload_dir = $images_base_dir . 'facilities/';
$equipments_upload_dir = $images_base_dir . 'equipments/';

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success_message = '';

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($step == 1) {
        $building_name = trim($_POST['building_name'] ?? '');

        $building_pic_path = uploadImage('building_pic', $buildings_upload_dir, $errors);

        if (empty($building_name)) {
            $errors[] = "กรุณากรอกชื่ออาคาร (Building Name).";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO buildings (building_name, building_pic) VALUES (?, ?)");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("ss", $building_name, $building_pic_path);
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
    } elseif ($step == 2) {
        $facility_name = trim($_POST['facility_name'] ?? '');
        $facility_des = trim($_POST['facility_des'] ?? '');
        $building_id = (int)($_POST['building_id'] ?? 0);

        $facility_pic_path = uploadImage('facility_pic', $facilities_upload_dir, $errors);

        if (empty($facility_name)) {
            $errors[] = "กรุณากรอกชื่อสิ่งอำนวยความสะดวก (Facility Name).";
        }
        if ($building_id === 0) {
            $errors[] = "กรุณาเลือกอาคาร (Building ID).";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO facilities (facility_name, facility_des, facility_pic, building_id) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("sssi", $facility_name, $facility_des, $facility_pic_path, $building_id);
                if ($stmt->execute()) {
                    header("Location: ?step=2&status=success");
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลสิ่งอำนวยความสะดวก: " . $stmt->error;
                    if ($facility_pic_path && file_exists($facility_pic_path)) {
                        unlink($facility_pic_path);
                    }
                }
                $stmt->close();
            }
        }
    } elseif ($step == 3) {
        $equip_name = trim($_POST['equip_name'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $measure = trim($_POST['measure'] ?? '');
        $size = trim($_POST['size'] ?? '');

        $equip_pic_path = uploadImage('equip_pic', $equipments_upload_dir, $errors);

        if (empty($equip_name)) {
            $errors[] = "กรุณากรอกชื่ออุปกรณ์ (Equipment Name).";
        }
        if ($quantity <= 0) {
             $errors[] = "จำนวน (Quantity) ต้องเป็นตัวเลขบวก.";
        }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO equipments (equip_name, quantity, measure, size, equip_pic) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("sisss", $equip_name, $quantity, $measure, $size, $equip_pic_path);
                if ($stmt->execute()) {
                    header("Location: ?step=3&status=success");
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
}

$buildings = [];
if ($conn->ping()) {
    $result_buildings = $conn->query("SELECT building_id, building_name FROM buildings ORDER BY building_name");
    if ($result_buildings) {
        while ($row = $result_buildings->fetch_assoc()) {
            $buildings[] = $row;
        }
    } else {
        $errors[] = "ไม่สามารถดึงข้อมูลอาคารได้: " . $conn->error;
    }
}

$conn->close();

?>