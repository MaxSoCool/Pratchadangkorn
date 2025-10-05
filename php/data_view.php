<?php
// php/data_view_logic.php
// This file assumes $conn, $mode, $errors, $is_admin, $is_logged_in are already defined
// It will define: $data, $total_items, $detail_item, $show_add_card, $total_pages, $available_filter

// Ensure sorting.php is included
if (!function_exists('getSortingClauses')) {
    include_once __DIR__ . '/sorting.php'; // Use __DIR__ for reliable path
}

// Ensure admin-injection.php is included if this is an admin context
if ($is_admin && !function_exists('uploadImage')) { // Check a function from admin-injection.php
    include_once __DIR__ . '/admin-injection.php';
}

// --- Data Fetching Logic (Common to Admin, User, Guest) ---
$items_per_page = 9;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search_query . '%';

// Determine default available_filter based on user type
// $is_admin, $is_logged_in should be defined in the calling script
$default_available_filter = ($is_admin || !$is_logged_in) ? 'all' : 'yes'; // Admin/Guest default 'all', User default 'yes'
$available_filter = $_GET['available_filter'] ?? $default_available_filter;

$available_context = '';
$table_alias_for_available = ''; // ใช้สำหรับระบุ alias ตารางใน WHERE clause

if ($mode == 'buildings') {
    $available_context = 'data_buildings';
    $table_alias_for_available = 'b';
} elseif ($mode == 'equipment') {
    $available_context = 'data_equipment';
    $table_alias_for_available = ''; // ตาราง equipments ไม่ต้องมี alias ใน where clause
} elseif ($mode == 'building_detail') { // แสดง facilities ภายในอาคาร
    $available_context = 'data_facilities';
    $table_alias_for_available = ''; // ตาราง facilities ไม่ต้องมี alias ใน where clause
}

$available_sorting_clauses = getSortingClauses($available_context, $available_filter);
$available_where_sql = str_replace('T.', $table_alias_for_available . '.', $available_sorting_clauses['where_sql']);
$available_param_type = $available_sorting_clauses['where_param_type'];
$available_param_value = $available_sorting_clauses['where_param_value'];

$data = [];
$total_items = 0;
$detail_item = null;

// --- Calculate OFFSET and "Add" card (only for Admin, and under specific conditions) ---
$show_add_card = false;
$items_to_fetch = $items_per_page;
$offset = ($current_page - 1) * $items_per_page;

if ($is_admin && empty($search_query) && ($mode == 'buildings' || $mode == 'equipment')) {
    if ($current_page == 1 && $available_filter == 'all') {
        $show_add_card = true;
        $items_to_fetch = $items_per_page - 1;
        $offset = 0;
    } else {
        // Adjust offset if an 'add card' would have been on page 1 but isn't now
        $offset = (($current_page - 1) * $items_per_page) - ($current_page == 1 ? 0 : 1);
    }
}


// Check if current mode is an edit/add form (only relevant for admin)
$is_admin_form_mode = in_array($mode, ['add_building', 'add_facility', 'add_equipment', 'edit_building', 'edit_facility', 'edit_equipment']);

// Only fetch data if not in an admin form mode OR if it's an admin accessing forms
if (!$is_admin_form_mode || ($is_admin_form_mode && $is_admin)) {
    try {
        if ($mode == 'buildings') {
            // COUNT SQL
            $sql_count_base = "SELECT COUNT(DISTINCT b.building_id) FROM buildings b LEFT JOIN facilities f ON b.building_id = f.building_id WHERE (b.building_name LIKE ? OR f.facility_name LIKE ?)";
            $sql_count = $sql_count_base . $available_where_sql; // เพิ่มเงื่อนไข available
            $stmt_count = $conn->prepare($sql_count);

            $bind_params_count = [$search_param, $search_param];
            $bind_types_count = "ss";
            if ($available_param_value !== null) {
                $bind_params_count[] = $available_param_value;
                $bind_types_count .= $available_param_type;
            }
            $stmt_count->bind_param($bind_types_count, ...$bind_params_count);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            // DATA SQL
            $sql_data_base = "SELECT b.building_id, b.building_name, b.building_pic, b.available, GROUP_CONCAT(CASE WHEN f.facility_name LIKE ? THEN f.facility_name ELSE NULL END SEPARATOR ', ') AS matched_facilities FROM buildings b LEFT JOIN facilities f ON b.building_id = f.building_id WHERE (b.building_name LIKE ? OR f.facility_name LIKE ?)";
            $sql_data = $sql_data_base . $available_where_sql . " GROUP BY b.building_id ORDER BY CAST(b.building_id AS UNSIGNED) ASC LIMIT ? OFFSET ?";
            $stmt_data = $conn->prepare($sql_data);

            $bind_params_data = [$search_param, $search_param, $search_param]; // search for matched_facilities, search for building name/facility name
            $bind_types_data = "sss";
            if ($available_param_value !== null) {
                $bind_params_data[] = $available_param_value;
                $bind_types_data .= $available_param_type;
            }
            $bind_params_data[] = $items_to_fetch;
            $bind_params_data[] = $offset;
            $bind_types_data .= "ii";
            
            $stmt_data->bind_param($bind_types_data, ...$bind_params_data);
            $stmt_data->execute();
            $result_data = $stmt_data->get_result();
            while ($row = $result_data->fetch_assoc()) { $data[] = $row; }
            $stmt_data->close();
        } elseif ($mode == 'equipment') {
            // COUNT SQL
            $sql_count_base = "SELECT COUNT(*) FROM equipments WHERE equip_name LIKE ?";
            $sql_count = $sql_count_base;
            if ($available_param_value !== null) {
                $sql_count .= " AND available = ?"; // ไม่มี alias เพราะ $table_alias_for_available ถูกตั้งเป็นว่าง
            }
            $stmt_count = $conn->prepare($sql_count);

            $bind_params_count = [$search_param];
            $bind_types_count = "s";
            if ($available_param_value !== null) {
                $bind_params_count[] = $available_param_value;
                $bind_types_count .= $available_param_type;
            }
            $stmt_count->bind_param($bind_types_count, ...$bind_params_count);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();

            // DATA SQL
            $sql_data_base = "SELECT equip_id, equip_name, quantity, measure, size, equip_pic, available FROM equipments WHERE equip_name LIKE ?";
            $sql_data = $sql_data_base;
            if ($available_param_value !== null) {
                $sql_data .= " AND available = ?"; // ไม่มี alias เพราะ $table_alias_for_available ถูกตั้งเป็นว่าง
            }
            $sql_data .= " ORDER BY equip_id ASC LIMIT ? OFFSET ?";
            $stmt_data = $conn->prepare($sql_data);

            $bind_params_data = [$search_param];
            $bind_types_data = "s";
            if ($available_param_value !== null) {
                $bind_params_data[] = $available_param_value;
                $bind_types_data .= $available_param_type;
            }
            $bind_params_data[] = $items_to_fetch;
            $bind_params_data[] = $offset;
            $bind_types_data .= "ii";

            $stmt_data->bind_param($bind_types_data, ...$bind_params_data);
            $stmt_data->execute();
            $result_data = $stmt_data->get_result();
            while ($row = $result_data->fetch_assoc()) { $data[] = $row; }
            $stmt_data->close();
        } elseif ($mode == 'building_detail' && isset($_GET['building_id'])) {
            $building_id = htmlspecialchars($_GET['building_id']);
            $stmt_building = $conn->prepare("SELECT building_id, building_name, building_pic, available FROM buildings WHERE building_id = ?");
            $stmt_building->bind_param("s", $building_id);
            $stmt_building->execute();
            $detail_item = $stmt_building->get_result()->fetch_assoc();
            $stmt_building->close();

            if ($detail_item) {
                // COUNT SQL (Facilities in building_detail)
                $sql_count_base = "SELECT COUNT(*) FROM facilities WHERE building_id = ? AND (facility_name LIKE ? OR facility_des LIKE ?)";
                $sql_count = $sql_count_base;
                if ($available_param_value !== null) {
                    $sql_count .= " AND available = ?"; // ไม่มี alias เพราะ $table_alias_for_available ถูกตั้งเป็นว่าง
                }
                $stmt_count = $conn->prepare($sql_count);

                $bind_params_count = [$building_id, $search_param, $search_param];
                $bind_types_count = "sss";
                if ($available_param_value !== null) {
                    $bind_params_count[] = $available_param_value;
                    $bind_types_count .= $available_param_type;
                }
                $stmt_count->bind_param($bind_types_count, ...$bind_params_count);
                $stmt_count->execute();
                $stmt_count->bind_result($total_items);
                $stmt_count->fetch();
                $stmt_count->close();

                // DATA SQL (Facilities in building_detail)
                $sql_data_base = "SELECT facility_id, facility_name, facility_des, facility_pic, available FROM facilities WHERE building_id = ? AND (facility_name LIKE ? OR facility_des LIKE ?)";
                $sql_data = $sql_data_base;
                if ($available_param_value !== null) {
                    $sql_data .= " AND available = ?"; // ไม่มี alias เพราะ $table_alias_for_available ถูกตั้งเป็นว่าง
                }
                $sql_data .= " ORDER BY facility_id ASC LIMIT ? OFFSET ?";
                $stmt_data = $conn->prepare($sql_data);

                $bind_params_data = [$building_id, $search_param, $search_param];
                $bind_types_data = "sss";
                if ($available_param_value !== null) {
                    $bind_params_data[] = $available_param_value;
                    $bind_types_data .= $available_param_type;
                }
                $bind_params_data[] = $items_per_page;
                $bind_params_data[] = $offset;
                $bind_types_data .= "ii";

                $stmt_data->bind_param($bind_types_data, ...$bind_params_data);
                $stmt_data->execute();
                $result_data = $stmt_data->get_result();
                while ($row = $result_data->fetch_assoc()) { $data[] = $row; }
                $stmt_data->close();
            } else {
                if ($is_admin) {
                    header("Location: admin-data_view-page.php?mode=buildings&status=building_not_found");
                    exit();
                } else {
                    $errors[] = "ไม่พบข้อมูลอาคารที่คุณร้องขอ";
                }
            }
        } elseif ($mode == 'facility_detail' && isset($_GET['facility_id'])) {
            $facility_id = (int)$_GET['facility_id'];
            $stmt = $conn->prepare("SELECT f.facility_id, f.facility_name, f.facility_des, f.facility_pic, f.building_id, b.building_name, f.available FROM facilities f JOIN buildings b ON f.building_id = b.building_id WHERE f.facility_id = ?");
            $stmt->bind_param("i", $facility_id);
            $stmt->execute();
            $detail_item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$detail_item) {
                if ($is_admin) {
                    header("Location: admin-data_view-page.php?mode=buildings&status=facility_not_found");
                    exit();
                } else {
                    $errors[] = "ไม่พบข้อมูลสิ่งอำนวยความสะดวกที่คุณร้องขอ";
                }
            }
        } elseif ($mode == 'equip_detail' && isset($_GET['equip_id'])) {
            $equip_id = (int)$_GET['equip_id'];
            $stmt = $conn->prepare("SELECT equip_id, equip_name, quantity, measure, size, equip_pic, available FROM equipments WHERE equip_id = ?");
            $stmt->bind_param("i", $equip_id);
            $stmt->execute();
            $detail_item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$detail_item) {
                if ($is_admin) {
                    header("Location: admin-data_view-page.php?mode=equipment&status=equip_not_found");
                    exit();
                } else {
                    $errors[] = "ไม่พบข้อมูลอุปกรณ์ที่คุณร้องขอ";
                }
            }
        }
    } catch (Exception $e) {
        error_log("Database Error: " . $e->getMessage());
        $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    }
    
    $adjusted_total_items = $total_items;
    if ($show_add_card && $current_page == 1) {
        $adjusted_total_items++;
    }
    $total_pages = ceil($adjusted_total_items / $items_per_page);

}
// Logic for pre-filling forms in Edit mode (only for Admin)
if ($is_admin_form_mode && $is_admin) { // Ensure only admin can access these modes
    if ($mode == 'edit_building' && isset($_GET['building_id'])) {
        $building_id = htmlspecialchars($_GET['building_id']);
        $stmt = $conn->prepare("SELECT building_id, building_name, building_pic, available FROM buildings WHERE building_id = ?");
        $stmt->bind_param("s", $building_id);
        $stmt->execute();
        $detail_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$detail_item) {
            header("Location: admin-data_view-page.php?mode=buildings&status=building_not_found");
            exit();
        }
    } elseif ($mode == 'edit_facility' && isset($_GET['facility_id'])) {
        $facility_id = (int)$_GET['facility_id'];
        $stmt = $conn->prepare("SELECT facility_id, facility_name, facility_des, facility_pic, building_id, available FROM facilities WHERE facility_id = ?");
        $stmt->bind_param("i", $facility_id);
        $stmt->execute();
        $detail_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$detail_item) {
            header("Location: admin-data_view-page.php?mode=buildings&status=facility_not_found");
            exit();
        }
    } elseif ($mode == 'edit_equipment' && isset($_GET['equip_id'])) {
        $equip_id = (int)$_GET['equip_id'];
        $stmt = $conn->prepare("SELECT equip_id, equip_name, quantity, measure, size, equip_pic, available FROM equipments WHERE equip_id = ?");
        $stmt->bind_param("i", $equip_id);
        $stmt->execute();
        $detail_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$detail_item) {
            header("Location: admin-data_view-page.php?mode=equipment&status=equip_not_found");
            exit();
        }
    }
} else if ($is_admin_form_mode && !$is_admin) { // If a non-admin tries to access an admin form mode
    // Redirect to appropriate data view page based on login status
    $redirect_page = $is_logged_in ? "user-data_view-page.php" : "index.php";
    header("Location: " . $redirect_page . "?status=unauthorized_access");
    exit();
}


// Buildings for facility dropdown (only for admin when adding/editing facility)
$buildings = [];
if ($is_admin && ($mode == 'add_facility' || $mode == 'edit_facility')) {
    $result_buildings = $conn->query("SELECT building_id, building_name FROM buildings ORDER BY building_name");
    if ($result_buildings) {
        while ($row = $result_buildings->fetch_assoc()) {
            $buildings[] = $row;
        }
    }
}