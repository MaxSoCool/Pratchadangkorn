<?php

if (!isset($conn)) {
    include dirname(__DIR__) . '/database/database.php';
}

$activity_types = [];
$result_activity = $conn->query("SELECT activity_type_id, activity_type_name FROM activity_type ORDER BY activity_type_name");
if ($result_activity) {
    while ($row = $result_activity->fetch_assoc()) {
        $activity_types[] = $row;
    }
    $result_activity->free();
} else {
    error_log("Failed to fetch activity types for admin dropdown: " . $conn->error);
}

$facilities_dropdown = [];
$result_facilities_dropdown = $conn->query("SELECT facility_id, facility_name FROM facilities ORDER BY facility_name ASC");
if ($result_facilities_dropdown) {
    while ($row = $result_facilities_dropdown->fetch_assoc()) {
        $facilities_dropdown[] = $row;
    }
    $result_facilities_dropdown->free();
} else {
    error_log("Failed to fetch facilities for admin dropdown: " . $conn->error);
}

$equipments_dropdown = [];
$result_equipments_dropdown = $conn->query("SELECT equip_id, equip_name, measure FROM equipments ORDER BY equip_name ASC");
if ($result_equipments_dropdown) {
    while ($row = $result_equipments_dropdown->fetch_assoc()) {
        $equipments_dropdown[] = $row;
    }
    $result_equipments_dropdown->free();
} else {
    error_log("Failed to fetch equipments for admin dropdown: " . $conn->error);
}

$faculties_for_chart_filter = [];
$result_fa_de = $conn->query("SELECT fa_de_id, fa_de_name FROM faculties_department ORDER BY fa_de_name ASC");
if ($result_fa_de) {
    while ($row = $result_fa_de->fetch_assoc()) {
        $faculties_for_chart_filter[] = $row;
    }
    $result_fa_de->free();
} else {
    error_log("Failed to fetch faculties for admin dropdown: " . $conn->error);
}
?>