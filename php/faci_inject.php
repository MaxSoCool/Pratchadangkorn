<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
}
?>