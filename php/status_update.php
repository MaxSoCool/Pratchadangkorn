<?php
if (!isset($conn)) {
    include dirname(__DIR__) . '/database/database.php';
}

try {
    $current_date = date('Y-m-d');

    // Update project statuses
    $stmt_project_end = $conn->prepare("UPDATE project SET writed_status = 'สิ้นสุดโครงการ' WHERE end_date < ? AND writed_status NOT IN ('สิ้นสุดโครงการ', 'ยกเลิกโครงการ')");
    if ($stmt_project_end) {
        $stmt_project_end->bind_param("s", $current_date);
        $stmt_project_end->execute();
        $stmt_project_end->close();
    } else {
        error_log("Failed to prepare statement for ending projects: " . $conn->error);
    }

    $stmt_project_start = $conn->prepare("UPDATE project SET writed_status = 'เริ่มดำเนินการ' WHERE start_date <= ? AND writed_status = 'ส่งโครงการ'");
    if ($stmt_project_start) {
        $stmt_project_start->bind_param("s", $current_date);
        $stmt_project_start->execute();
        $stmt_project_start->close();
    } else {
        error_log("Failed to prepare statement for starting projects: " . $conn->error);
    }

    $stmt_building_end = $conn->prepare("UPDATE facilities_requests SET writed_status = 'สิ้นสุดดำเนินการ' WHERE end_date < ? AND writed_status NOT IN ('สิ้นสุดดำเนินการ', 'ยกเลิกคำร้องขอ')");
    if ($stmt_building_end) {
        $stmt_building_end->bind_param("s", $current_date);
        $stmt_building_end->execute();
        $stmt_building_end->close();
    } else {
        error_log("Failed to prepare statement for ending buildings: " . $conn->error);
    }

    $stmt_building_start = $conn->prepare("UPDATE facilities_requests SET writed_status = 'เริ่มดำเนินการ' WHERE start_date <= ? AND writed_status = 'ส่งคำร้องขอ' AND approve = 'อนุมัติ'");
    if ($stmt_building_start) {
        $stmt_building_start->bind_param("s", $current_date);
        $stmt_building_start->execute();
        $stmt_building_start->close();
    } else {
        error_log("Failed to prepare statement for starting buildings: " . $conn->error);
    }

    // Update equipments_requests statuses
    $stmt_equipment_end = $conn->prepare("UPDATE equipments_requests SET writed_status = 'สิ้นสุดดำเนินการ' WHERE end_date < ? AND writed_status NOT IN ('สิ้นสุดดำเนินการ', 'ยกเลิกคำร้องขอ')");
    if ($stmt_equipment_end) {
        $stmt_equipment_end->bind_param("s", $current_date);
        $stmt_equipment_end->execute();
        $stmt_equipment_end->close();
    } else {
        error_log("Failed to prepare statement for ending equipments: " . $conn->error);
    }

    $stmt_equipment_start = $conn->prepare("UPDATE equipments_requests SET writed_status = 'เริ่มดำเนินการ' WHERE start_date <= ? AND writed_status = 'ส่งคำร้องขอ' AND approve = 'อนุมัติ'");
    if ($stmt_equipment_start) {
        $stmt_equipment_start->bind_param("s", $current_date);
        $stmt_equipment_start->execute();
        $stmt_equipment_start->close();
    } else {
        error_log("Failed to prepare statement for starting equipments: " . $conn->error);
    }

} catch (Exception $e) {
    error_log("Automatic Status Update Error: " . $e->getMessage());
}
?>