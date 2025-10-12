<?php

if (!isset($conn)) {
    include '../database/database.php';
}
if (!function_exists('formatThaiDate')) { // ตรวจสอบว่าฟังก์ชันถูกโหลดแล้วหรือไม่
    include 'helpers.php';
}

if (isset($_GET['action']) && $_GET['action'] == 'get_facilities_by_building' && isset($_GET['building_id'])) {
    header('Content-Type: application/json');
    $buildingId = (int)$_GET['building_id'];

    $facilities_in_building = [];
    if ($buildingId > 0) {
        $sql = "SELECT facility_id, facility_name, available
                FROM facilities
                WHERE building_id = ?
                ORDER BY facility_id ASC"; 

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("i", $buildingId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $facilities_in_building[] = $row;
            }
            $stmt->close();
        } else {
            error_log("AJAX SQL Error (get_facilities_by_building): " . $conn->error);
            echo json_encode(['error' => 'Database error: ' . $conn->error]);
        }
    }
    echo json_encode($facilities_in_building);
    exit(); // EXIT after handling AJAX
}


if (isset($_GET['action']) && $_GET['action'] == 'get_facilities_by_project' && isset($_GET['project_id'])) {
    header('Content-Type: application/json');
    $projectId = (int)$_GET['project_id'];
    $userId = $_SESSION['nontri_id'] ?? '';

    $facilities_for_project = [];
    if ($projectId > 0 && !empty($userId)) {
        $sql = "SELECT DISTINCT f.facility_id, f.facility_name, f.available
                FROM facilities f
                JOIN facilities_requests fr ON f.facility_id = fr.facility_id
                JOIN project p ON fr.project_id = p.project_id
                WHERE fr.project_id = ? AND p.nontri_id = ?
                ORDER BY f.facility_id ASC"; // Order by f.facility_id (INT) for correct numeric sorting

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $projectId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $facilities_for_project[] = $row;
            }
            $stmt->close();
        } else {
            error_log("AJAX SQL Error (get_facilities_by_project): " . $conn->error);
            echo json_encode(['error' => 'Database error: ' . $conn->error]);
        }
    }
    echo json_encode($facilities_for_project);
    exit(); // EXIT after handling AJAX
}

if (isset($_GET['action']) && $_GET['action'] == 'get_project_dates' && isset($_GET['project_id'])) {
    header('Content-Type: application/json');
    $projectId = (int)$_GET['project_id'];
    $userId = $_SESSION['nontri_id'] ?? '';

    $project_dates = ['start_date' => null, 'end_date' => null];
    if ($projectId > 0 && !empty($userId)) {
        $sql = "SELECT start_date, end_date FROM project WHERE project_id = ? AND nontri_id = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("is", $projectId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $project_dates['start_date'] = $row['start_date'];
                $project_dates['end_date'] = $row['end_date'];
            }
            $stmt->close();
        } else {
            error_log("AJAX SQL Error (get_project_dates): " . $conn->error);
            echo json_encode(['error' => 'Database error: ' . $conn->error]);
        }
    }
    echo json_encode($project_dates);
    exit(); // EXIT after handling AJAX
}

?>