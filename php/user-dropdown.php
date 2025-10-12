<?php
// user-dropdown.php

// ต้องมี database connection ก่อน
if (!isset($conn)) {
    include dirname(__DIR__) . '/database/database.php';
}

$activity_types = [];
$result_activity = $conn->query("SELECT activity_type_id, activity_type_name FROM activity_type ORDER BY activity_type_name");
if ($result_activity) {
    while ($row = $result_activity->fetch_assoc()) {
        $activity_types[] = $row;
    }
} else {
    error_log("Failed to fetch activity types: " . $conn->error);
}

$user_projects = [];
$sql_user_projects = "SELECT project_id, project_name, writed_status FROM project WHERE nontri_id = ? AND writed_status NOT IN ('ร่างโครงการ', 'สิ้นสุดโครงการ', 'ยกเลิกโครงการ') ORDER BY project_name ASC";
$stmt_user_projects = $conn->prepare($sql_user_projects);
if ($stmt_user_projects) {
    $stmt_user_projects->bind_param("s", $user_id);
    $stmt_user_projects->execute();
    $result_user_projects = $stmt_user_projects->get_result();
    while ($row = $result_user_projects->fetch_assoc()) {
        $user_projects[] = $row;
    }
    $stmt_user_projects->close();
} else {
    $errors[] = "ไม่สามารถดึงข้อมูลโครงการของผู้ใช้ได้: " . $conn->error;
}


$buildings = [];
if (isset($main_tab) && $main_tab == 'user_requests' && (isset($mode) && ($mode == 'buildings_create' || $mode == 'buildings_edit'))) {
    $result_buildings = $conn->query("SELECT building_id, building_name, available FROM buildings ORDER BY building_id ASC");
    if ($result_buildings) {
        while ($row = $result_buildings->fetch_assoc()) {
            $buildings[] = $row;
        }
    } else {
        $errors[] = "ไม่สามารถดึงข้อมูลอาคารทั้งหมด: " . $conn->error;
    }
}

$equipments = [];
$result_equipments = $conn->query("SELECT equip_id, equip_name, measure, available FROM equipments ORDER BY equip_name ASC");
if ($result_equipments) {
    while ($row = $result_equipments->fetch_assoc()) {
        $equipments[] = $row;
    }
} else {
    error_log("Failed to fetch equipments: " . $conn->error);
}
?>