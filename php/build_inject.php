<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
            $stmt->bind_param("sss",$building_id, $building_name, $building_pic_path);
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
?>