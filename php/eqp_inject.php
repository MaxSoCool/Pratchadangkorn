<?php

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
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
?>