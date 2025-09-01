<?php
include 'database/database.php';

$items_per_page = 9;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search_query . '%';

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'buildings';

$data = [];
$total_items = 0;
$detail_item = null; 

try {
    if ($mode == 'buildings') {

        $sql_count = "SELECT COUNT(DISTINCT b.building_id)
                    FROM buildings b
                    LEFT JOIN facilities f ON b.building_id = f.building_id
                    WHERE b.building_name LIKE ? OR f.facility_name LIKE ?";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bind_param("ss", $search_param, $search_param);
        $stmt_count->execute();
        $stmt_count->bind_result($total_items);
        $stmt_count->fetch();
        $stmt_count->close();

        $sql_data = "SELECT
                        b.building_id,
                        b.building_name,
                        b.building_pic,
                        GROUP_CONCAT(
                            CASE
                                WHEN f.facility_name LIKE ? THEN f.facility_name
                                ELSE NULL
                            END
                            SEPARATOR ', '
                        ) AS matched_facilities
                    FROM buildings b
                    LEFT JOIN facilities f ON b.building_id = f.building_id
                    WHERE b.building_name LIKE ? OR f.facility_name LIKE ?
                    GROUP BY b.building_id, b.building_name, b.building_pic
                    ORDER BY b.building_id ASC
                    LIMIT ? OFFSET ?";
        
        $stmt_data = $conn->prepare($sql_data);

        $stmt_data->bind_param("sssii", $search_param, $search_param, $search_param, $items_per_page, $offset);
        $stmt_data->execute();
        $result_data = $stmt_data->get_result();
        while ($row = $result_data->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt_data->close();

    } elseif ($mode == 'equipment') {
        $sql_count = "SELECT COUNT(*) FROM equipments WHERE equip_name LIKE ?";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->bind_param("s", $search_param);
        $stmt_count->execute();
        $stmt_count->bind_result($total_items);
        $stmt_count->fetch();
        $stmt_count->close();

        $sql_data = "SELECT equip_id, equip_name, quantity, measure, size, equip_pic FROM equipments WHERE equip_name LIKE ? ORDER BY equip_id ASC LIMIT ? OFFSET ?";
        $stmt_data = $conn->prepare($sql_data);
        $stmt_data->bind_param("sii", $search_param, $items_per_page, $offset);
        $stmt_data->execute();
        $result_data = $stmt_data->get_result();
        while ($row = $result_data->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt_data->close();

    } elseif ($mode == 'building_detail' && isset($_GET['building_id'])) {

        $building_id = (int)$_GET['building_id'];
        $stmt_building = $conn->prepare("SELECT building_name, building_pic FROM buildings WHERE building_id = ?");
        $stmt_building->bind_param("i", $building_id);
        $stmt_building->execute();
        $detail_item = $stmt_building->get_result()->fetch_assoc();
        $stmt_building->close();

        if ($detail_item) {

            $sql_count = "SELECT COUNT(*) FROM facilities WHERE building_id = ? AND (facility_name LIKE ? OR facility_des LIKE ?)";
            $stmt_count = $conn->prepare($sql_count);
            $stmt_count->bind_param("iss", $building_id, $search_param, $search_param);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            $sql_data = "SELECT facility_id, facility_name, facility_des, facility_pic FROM facilities WHERE building_id = ? AND (facility_name LIKE ? OR facility_des LIKE ?) ORDER BY facility_id ASC LIMIT ? OFFSET ?";
            $stmt_data = $conn->prepare($sql_data);
            $stmt_data->bind_param("issii", $building_id, $search_param, $search_param, $items_per_page, $offset);
            $stmt_data->execute();
            $result_data = $stmt_data->get_result();
            while ($row = $result_data->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt_data->close();
        } else {
            header("Location: ?mode=buildings&status=building_not_found");
            exit();
        }

    } elseif ($mode == 'facility_detail' && isset($_GET['facility_id'])) {

        $facility_id = (int)$_GET['facility_id'];

        $stmt = $conn->prepare("SELECT f.facility_name, f.facility_des, f.facility_pic, f.building_id, b.building_name
                                FROM facilities f
                                JOIN buildings b ON f.building_id = b.building_id
                                WHERE f.facility_id = ?");
        $stmt->bind_param("i", $facility_id);
        $stmt->execute();
        $detail_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$detail_item) {
            header("Location: ?mode=buildings&status=facility_not_found");
            exit();
        }

    } elseif ($mode == 'equip_detail' && isset($_GET['equip_id'])) {

        $equip_id = (int)$_GET['equip_id'];

        $stmt = $conn->prepare("SELECT equip_name, quantity, measure, size, equip_pic FROM equipments WHERE equip_id = ?");
        $stmt->bind_param("i", $equip_id);
        $stmt->execute();
        $detail_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$detail_item) {
            header("Location: ?mode=equipment&status=equip_not_found");
            exit();
        }
    }
} catch (Exception $e) {
    error_log("Database Error: " . $e->getMessage());
}

$total_pages = ceil($total_items / $items_per_page);
?>