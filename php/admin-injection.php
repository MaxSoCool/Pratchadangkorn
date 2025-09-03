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


// ฟังก์ชันช่วยในการอัปโหลดรูปภาพ (ปรับปรุงใหม่สำหรับทั้งเพิ่มและแก้ไข)
// คาดหวังว่า $errors array จะถูกส่งมาแบบ reference (&) จากไฟล์หลัก
// $old_pic_path ใช้สำหรับกรณีแก้ไข: ถ้ามีการอัปโหลดรูปใหม่จะลบรูปเก่าทิ้ง, ถ้าไม่จะคืนค่า path รูปเก่า
function uploadImage($file_input_name, $target_dir, &$errors_ref, $old_pic_path = null) {
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
            // ถ้ามีการอัปโหลดใหม่สำเร็จ และมีรูปภาพเก่าอยู่ ให้ลบรูปภาพเก่าทิ้ง
            if ($old_pic_path && file_exists($old_pic_path) && strpos($old_pic_path, $images_base_dir) === 0) { // ตรวจสอบว่าเป็นไฟล์ในโฟลเดอร์ของเรา
                unlink($old_pic_path); // [1], [2], [3], [5], [8]
            }
            return $upload_path; 
        } else {
            // Error codes for file upload: [4], [37], [38], [39], [40]
            $errors_ref[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ " . $file_input_name . ": Error Code " . $_FILES[$file_input_name]['error'];
            return false;
        }
    } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE) {
        $errors_ref[] = "เกิดข้อผิดพลาดในการอัปโหลดรูปภาพ " . $file_input_name . ": Error Code " . $_FILES[$file_input_name]['error'];
        return false;
    }
    // ถ้าไม่มีการอัปโหลดไฟล์ใหม่ (UPLOAD_ERR_NO_FILE) ให้คืนค่า path รูปภาพเก่า
    return $old_pic_path; 
}


// ฟังก์ชันสำหรับลบไฟล์รูปภาพ
function deleteImageFile($file_path) {
    global $images_base_dir; // เพื่อความปลอดภัยในการลบไฟล์
    if ($file_path && file_exists($file_path) && is_file($file_path) && strpos($file_path, $images_base_dir) === 0) {
        return unlink($file_path); // [1], [2], [3], [5], [8]
    }
    return true; // ถือว่าสำเร็จถ้าไม่มีไฟล์ให้ลบ หรือ path ไม่ถูกต้อง
}


// --- Logic สำหรับการประมวลผล POST Request จากฟอร์ม ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['inject_type'])) {
    
    global $conn, $errors; // ใช้ $errors จากไฟล์หลัก

    // --- CREATE Logic ---
    if ($_POST['inject_type'] === 'building') {
        $building_id = trim($_POST['building_id'] ?? '');
        $building_name = trim($_POST['building_name'] ?? '');
        $building_pic_path = uploadImage('building_pic', $buildings_upload_dir, $errors);

        if (empty($building_id)) { $errors[] = "กรุณากรอกหมายเลขอาคาร (Building Number)."; } // [7], [11], [19], [21], [22]
        if (empty($building_name)) { $errors[] = "กรุณากรอกชื่ออาคาร (Building Name)."; } // [7], [11], [19], [21], [22]

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO buildings (building_id, building_name, building_pic) VALUES (?, ?, ?)");
            if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; } 
            else {
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

        if (empty($facility_name)) { $errors[] = "กรุณากรอกชื่อสถานที่ (Facility Name)."; }
        if (empty($building_id_for_facility) || $building_id_for_facility === 0) { $errors[] = "ไม่พบรหัสอาคารที่ถูกต้องสำหรับสถานที่นี้."; }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO facilities (building_id, facility_name, facility_des, facility_pic) VALUES (?, ?, ?, ?)");
            if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; } 
            else {
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

        if (empty($equip_name)) { $errors[] = "กรุณากรอกชื่ออุปกรณ์ (Equipment Name)."; }
        if ($quantity <= 0) { $errors[] = "กรุณาระบุจำนวนอุปกรณ์ที่ถูกต้อง (ต้องมากกว่า 0)."; }

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO equipments (equip_name, quantity, measure, size, equip_pic) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; } 
            else {
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

    // --- UPDATE Logic ---
    elseif ($_POST['inject_type'] === 'update_building') {
        $building_id = trim($_POST['building_id'] ?? '');
        $building_name = trim($_POST['building_name'] ?? '');
        $old_building_pic = trim($_POST['old_building_pic'] ?? ''); // Get old image path

        if (empty($building_id)) { $errors[] = "ไม่พบรหัสอาคารที่ต้องการแก้ไข"; }
        if (empty($building_name)) { $errors[] = "กรุณากรอกชื่ออาคาร (Building Name)."; }

        if (empty($errors)) {
            $new_building_pic_path = uploadImage('building_pic', $buildings_upload_dir, $errors, $old_building_pic);
            if ($new_building_pic_path === false && isset($_FILES['building_pic']) && $_FILES['building_pic']['error'] != UPLOAD_ERR_NO_FILE) {
                // Image upload failed, errors already added by uploadImage function
            } else {
                $stmt = $conn->prepare("UPDATE buildings SET building_name = ?, building_pic = ? WHERE building_id = ?"); // [10], [16], [18], [24], [28], [41]
                if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; } 
                else {
                    $stmt->bind_param("sss", $building_name, $new_building_pic_path, $building_id);
                    if ($stmt->execute()) {
                        header("Location: admin-data_view-page.php?mode=building_detail&building_id={$building_id}&status=success&message=" . urlencode("แก้ไขอาคาร '{$building_name}' สำเร็จแล้ว!"));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลอาคาร: " . $stmt->error;
                        // ถ้า update ล้มเหลวและมีการอัปโหลดรูปใหม่ ให้ลบรูปใหม่ทิ้งเพื่อกลับไปใช้รูปเดิม
                        if ($new_building_pic_path != $old_building_pic && $new_building_pic_path && file_exists($new_building_pic_path)) {
                            unlink($new_building_pic_path);
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }

    elseif ($_POST['inject_type'] === 'update_facility') {
        $facility_id = (int)($_POST['facility_id'] ?? 0);
        $facility_name = trim($_POST['facility_name'] ?? '');
        $facility_des = trim($_POST['facility_des'] ?? '');
        $building_id_for_facility = (int)($_POST['building_id'] ?? 0); // Need to retain for redirect
        $old_facility_pic = trim($_POST['old_facility_pic'] ?? '');

        if (empty($facility_id)) { $errors[] = "ไม่พบรหัสสถานที่ที่ต้องการแก้ไข"; }
        if (empty($facility_name)) { $errors[] = "กรุณากรอกชื่อสถานที่ (Facility Name)."; }
        if (empty($building_id_for_facility) || $building_id_for_facility === 0) { $errors[] = "ไม่พบรหัสอาคารที่ถูกต้องสำหรับสถานที่นี้."; }

        if (empty($errors)) {
            $new_facility_pic_path = uploadImage('facility_pic', $facilities_upload_dir, $errors, $old_facility_pic);
            if ($new_facility_pic_path === false && isset($_FILES['facility_pic']) && $_FILES['facility_pic']['error'] != UPLOAD_ERR_NO_FILE) {
                // Image upload failed
            } else {
                $stmt = $conn->prepare("UPDATE facilities SET facility_name = ?, facility_des = ?, facility_pic = ?, building_id = ? WHERE facility_id = ?");
                if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; } 
                else {
                    $stmt->bind_param("sssii", $facility_name, $facility_des, $new_facility_pic_path, $building_id_for_facility, $facility_id);
                    if ($stmt->execute()) {
                        header("Location: admin-data_view-page.php?mode=facility_detail&facility_id={$facility_id}&status=success&message=" . urlencode("แก้ไขสถานที่ '{$facility_name}' สำเร็จแล้ว!"));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลสถานที่: " . $stmt->error;
                        if ($new_facility_pic_path != $old_facility_pic && $new_facility_pic_path && file_exists($new_facility_pic_path)) {
                            unlink($new_facility_pic_path);
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

        if (empty($equip_id)) { $errors[] = "ไม่พบรหัสอุปกรณ์ที่ต้องการแก้ไข"; }
        if (empty($equip_name)) { $errors[] = "กรุณากรอกชื่ออุปกรณ์ (Equipment Name)."; }
        if ($quantity <= 0) { $errors[] = "กรุณาระบุจำนวนอุปกรณ์ที่ถูกต้อง (ต้องมากกว่า 0)."; }

        if (empty($errors)) {
            $new_equip_pic_path = uploadImage('equip_pic', $equipments_upload_dir, $errors, $old_equip_pic);
            if ($new_equip_pic_path === false && isset($_FILES['equip_pic']) && $_FILES['equip_pic']['error'] != UPLOAD_ERR_NO_FILE) {
                // Image upload failed
            } else {
                $stmt = $conn->prepare("UPDATE equipments SET equip_name = ?, quantity = ?, measure = ?, size = ?, equip_pic = ? WHERE equip_id = ?");
                if (!$stmt) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; } 
                else {
                    $stmt->bind_param("sisssi", $equip_name, $quantity, $measure, $size, $new_equip_pic_path, $equip_id);
                    if ($stmt->execute()) {
                        header("Location: admin-data_view-page.php?mode=equip_detail&equip_id={$equip_id}&status=success&message=" . urlencode("แก้ไขอุปกรณ์ '{$equip_name}' สำเร็จแล้ว!"));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการแก้ไขข้อมูลอุปกรณ์: " . $stmt->error;
                        if ($new_equip_pic_path != $old_equip_pic && $new_equip_pic_path && file_exists($new_equip_pic_path)) {
                            unlink($new_equip_pic_path);
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }

    // --- DELETE Logic ---
    elseif ($_POST['inject_type'] === 'delete_building') {
        $building_id = trim($_POST['delete_id'] ?? '');

        if (empty($building_id)) { $errors[] = "ไม่พบรหัสอาคารที่ต้องการลบ"; }

        if (empty($errors)) {
            // Check for dependent facilities
            $stmt_check = $conn->prepare("SELECT COUNT(*) FROM facilities WHERE building_id = ?");
            $stmt_check->bind_param("s", $building_id);
            $stmt_check->execute();
            $stmt_check->bind_result($facility_count);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($facility_count > 0) {
                $errors[] = "ไม่สามารถลบอาคาร '{$building_id}' ได้ เนื่องจากยังมีสถานที่เกี่ยวข้องอยู่ ({$facility_count} แห่ง). กรุณาลบสถานที่ภายในอาคารนี้ก่อน";
            } else {
                // Get image path before deleting the record
                $stmt_get_pic = $conn->prepare("SELECT building_pic FROM buildings WHERE building_id = ?");
                $stmt_get_pic->bind_param("s", $building_id);
                $stmt_get_pic->execute();
                $stmt_get_pic->bind_result($building_pic);
                $stmt_get_pic->fetch();
                $stmt_get_pic->close();

                $stmt_delete = $conn->prepare("DELETE FROM buildings WHERE building_id = ?"); // [6], [12], [13], [17], [23]
                if (!$stmt_delete) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; } 
                else {
                    $stmt_delete->bind_param("s", $building_id);
                    if ($stmt_delete->execute()) {
                        deleteImageFile($building_pic); // Delete associated image
                        header("Location: admin-data_view-page.php?mode=buildings&status=success&message=" . urlencode("ลบอาคาร '{$building_id}' สำเร็จแล้ว!"));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการลบข้อมูลอาคาร: " . $stmt_delete->error;
                    }
                    $stmt_delete->close();
                }
            }
        }
    }

    elseif ($_POST['inject_type'] === 'delete_facility') {
        $facility_id = (int)($_POST['delete_id'] ?? 0);
        $building_id_for_redirect = (int)($_POST['building_id_for_redirect'] ?? 0); // For redirecting back

        if (empty($facility_id)) { $errors[] = "ไม่พบรหัสสถานที่ที่ต้องการลบ"; }

        if (empty($errors)) {
            // Check for dependent requests (assuming a 'requests' table links to facilities)
            $stmt_check_requests = $conn->prepare("SELECT COUNT(*) FROM requests WHERE facility_id = ?"); 
            if ($stmt_check_requests) {
                $stmt_check_requests->bind_param("i", $facility_id);
                $stmt_check_requests->execute();
                $stmt_check_requests->bind_result($request_count);
                $stmt_check_requests->fetch();
                $stmt_check_requests->close();
            } else {
                 $request_count = 0; // Assume no requests if table not found or query error
                 error_log("Warning: Could not check for facility requests (table 'requests' or query failed). Assuming no requests.");
            }

            if ($request_count > 0) {
                $errors[] = "ไม่สามารถลบสถานที่นี้ได้ เนื่องจากมีการขอใช้งานสถานที่นี้อยู่ ({$request_count} รายการ).";
            } else {
                // Get image path before deleting the record
                $stmt_get_pic = $conn->prepare("SELECT facility_pic FROM facilities WHERE facility_id = ?");
                $stmt_get_pic->bind_param("i", $facility_id);
                $stmt_get_pic->execute();
                $stmt_get_pic->bind_result($facility_pic);
                $stmt_get_pic->fetch();
                $stmt_get_pic->close();

                $stmt_delete = $conn->prepare("DELETE FROM facilities WHERE facility_id = ?");
                if (!$stmt_delete) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; } 
                else {
                    $stmt_delete->bind_param("i", $facility_id);
                    if ($stmt_delete->execute()) {
                        deleteImageFile($facility_pic); // Delete associated image
                        header("Location: admin-data_view-page.php?mode=building_detail&building_id={$building_id_for_redirect}&status=success&message=" . urlencode("ลบสถานที่สำเร็จแล้ว!"));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการลบข้อมูลสถานที่: " . $stmt_delete->error;
                    }
                    $stmt_delete->close();
                }
            }
        }
    }

    elseif ($_POST['inject_type'] === 'delete_equipment') {
        $equip_id = (int)($_POST['delete_id'] ?? 0);

        if (empty($equip_id)) { $errors[] = "ไม่พบรหัสอุปกรณ์ที่ต้องการลบ"; }

        if (empty($errors)) {
            // Check for dependent requests (assuming a 'requests' table links to equipment, e.g., via a join table or direct column)
            $stmt_check_requests = $conn->prepare("SELECT COUNT(*) FROM requests WHERE equip_id = ?"); 
            if ($stmt_check_requests) {
                $stmt_check_requests->bind_param("i", $equip_id);
                $stmt_check_requests->execute();
                $stmt_check_requests->bind_result($request_count);
                $stmt_check_requests->fetch();
                $stmt_check_requests->close();
            } else {
                $request_count = 0; // Assume no requests if table not found or query error
                error_log("Warning: Could not check for equipment requests (table 'requests' or query failed). Assuming no requests.");
            }

            if ($request_count > 0) {
                $errors[] = "ไม่สามารถลบอุปกรณ์นี้ได้ เนื่องจากมีการขอใช้งานอุปกรณ์นี้อยู่ ({$request_count} รายการ).";
            } else {
                // Get image path before deleting the record
                $stmt_get_pic = $conn->prepare("SELECT equip_pic FROM equipments WHERE equip_id = ?");
                $stmt_get_pic->bind_param("i", $equip_id);
                $stmt_get_pic->execute();
                $stmt_get_pic->bind_result($equip_pic);
                $stmt_get_pic->fetch();
                $stmt_get_pic->close();

                $stmt_delete = $conn->prepare("DELETE FROM equipments WHERE equip_id = ?");
                if (!$stmt_delete) { $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error; } 
                else {
                    $stmt_delete->bind_param("i", $equip_id);
                    if ($stmt_delete->execute()) {
                        deleteImageFile($equip_pic); // Delete associated image
                        header("Location: admin-data_view-page.php?mode=equipment&status=success&message=" . urlencode("ลบอุปกรณ์สำเร็จแล้ว!"));
                        exit();
                    } else {
                        $errors[] = "เกิดข้อผิดพลาดในการลบข้อมูลอุปกรณ์: " . $stmt_delete->error;
                    }
                    $stmt_delete->close();
                }
            }
        }
    }
}
?>