<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-page.php"); 
    exit();
}

include 'database/database.php';
// Include ไฟล์ที่มี Logic การเพิ่ม, แก้ไข, ลบข้อมูลจริง ๆ
include_once 'php/admin-injection.php'; 

// --- Session Data ---
$staff_THname = htmlspecialchars($_SESSION['user_display_THname'] ?? 'N/A');
$staff_THsur = htmlspecialchars($_SESSION['user_display_THsur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');

// --- Page Logic Configuration ---
$mode = $_GET['mode'] ?? 'buildings';
$errors = []; // เก็บข้อผิดพลาดจากการประมวลผลฟอร์ม (จาก admin-injection.php)
$success_message = $_GET['message'] ?? ''; // ข้อความสำเร็จจากการ redirect

// --- DATA FETCHING LOGIC (GET REQUESTS) ---
$items_per_page = 9;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search_query . '%';

$data = [];
$total_items = 0;
$detail_item = null; // ใช้สำหรับแสดงรายละเอียด หรือ Pre-fill form ในโหมด edit

// --- คำนวณ OFFSET และการ์ด "เพิ่ม" ที่ถูกต้อง ---
$show_add_card = false;
$items_to_fetch = $items_per_page;
$offset = ($current_page - 1) * $items_per_page;

if (empty($search_query) && ($mode == 'buildings' || $mode == 'equipment')) {
    if ($current_page == 1) {
        $show_add_card = true;
        $items_to_fetch = $items_per_page - 1;
        $offset = 0;
    } else {
        $offset = (($current_page - 1) * $items_per_page) - 1;
    }
}

// เพิ่มโหมด edit เข้าไปในการตรวจสอบ
if (!in_array($mode, ['add_building', 'add_facility', 'add_equipment', 'edit_building', 'edit_facility', 'edit_equipment'])) {
    try {
        if ($mode == 'buildings') {
            $sql_count = "SELECT COUNT(DISTINCT b.building_id) FROM buildings b LEFT JOIN facilities f ON b.building_id = f.building_id WHERE b.building_name LIKE ? OR f.facility_name LIKE ?";
            $stmt_count = $conn->prepare($sql_count);
            $stmt_count->bind_param("ss", $search_param, $search_param);
            $stmt_count->execute();
            $stmt_count->bind_result($total_items);
            $stmt_count->fetch();
            $stmt_count->close();
            $sql_data = "SELECT b.building_id, b.building_name, b.building_pic, GROUP_CONCAT(CASE WHEN f.facility_name LIKE ? THEN f.facility_name ELSE NULL END SEPARATOR ', ') AS matched_facilities FROM buildings b LEFT JOIN facilities f ON b.building_id = f.building_id WHERE b.building_name LIKE ? OR f.facility_name LIKE ? GROUP BY b.building_id ORDER BY CAST(b.building_id AS UNSIGNED) ASC LIMIT ? OFFSET ?";
            $stmt_data = $conn->prepare($sql_data);
            $stmt_data->bind_param("sssii", $search_param, $search_param, $search_param, $items_to_fetch, $offset);
            $stmt_data->execute();
            $result_data = $stmt_data->get_result();
            while ($row = $result_data->fetch_assoc()) { $data[] = $row; }
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
            $stmt_data->bind_param("sii", $search_param, $items_to_fetch, $offset);
            $stmt_data->execute();
            $result_data = $stmt_data->get_result();
            while ($row = $result_data->fetch_assoc()) { $data[] = $row; }
            $stmt_data->close();
        } elseif ($mode == 'building_detail' && isset($_GET['building_id'])) {
            $building_id = htmlspecialchars($_GET['building_id']);
            $stmt_building = $conn->prepare("SELECT building_id, building_name, building_pic FROM buildings WHERE building_id = ?");
            $stmt_building->bind_param("s", $building_id);
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
                while ($row = $result_data->fetch_assoc()) { $data[] = $row; }
                $stmt_data->close();
            } else {
                header("Location: admin-data_view-page.php?mode=buildings&status=building_not_found");
                exit();
            }
        } elseif ($mode == 'facility_detail' && isset($_GET['facility_id'])) {
            $facility_id = (int)$_GET['facility_id'];
            $stmt = $conn->prepare("SELECT f.facility_id, f.facility_name, f.facility_des, f.facility_pic, f.building_id, b.building_name FROM facilities f JOIN buildings b ON f.building_id = b.building_id WHERE f.facility_id = ?");
            $stmt->bind_param("i", $facility_id);
            $stmt->execute();
            $detail_item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$detail_item) {
                header("Location: admin-data_view-page.php?mode=buildings&status=facility_not_found");
                exit();
            }
        } elseif ($mode == 'equip_detail' && isset($_GET['equip_id'])) {
            $equip_id = (int)$_GET['equip_id'];
            $stmt = $conn->prepare("SELECT equip_id, equip_name, quantity, measure, size, equip_pic FROM equipments WHERE equip_id = ?");
            $stmt->bind_param("i", $equip_id);
            $stmt->execute();
            $detail_item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$detail_item) {
                header("Location: admin-data_view-page.php?mode=equipment&status=equip_not_found");
                exit();
            }
        }
    } catch (Exception $e) {
        error_log("Database Error: " . $e->getMessage());
        $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    }
    $total_pages_items = $show_add_card ? $total_items + 1 : $total_items;
    $total_pages = ceil($total_pages_items / $items_per_page);
} 
// Logic สำหรับดึงข้อมูลมา Pre-fill Form ในโหมด Edit
elseif ($mode == 'edit_building' && isset($_GET['building_id'])) {
    $building_id = htmlspecialchars($_GET['building_id']);
    $stmt = $conn->prepare("SELECT building_id, building_name, building_pic FROM buildings WHERE building_id = ?");
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
    $stmt = $conn->prepare("SELECT facility_id, facility_name, facility_des, facility_pic, building_id FROM facilities WHERE facility_id = ?");
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
    $stmt = $conn->prepare("SELECT equip_id, equip_name, quantity, measure, size, equip_pic FROM equipments WHERE equip_id = ?");
    $stmt->bind_param("i", $equip_id);
    $stmt->execute();
    $detail_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$detail_item) {
        header("Location: admin-data_view-page.php?mode=equipment&status=equip_not_found");
        exit();
    }
}

$buildings = []; 
if ($mode == 'add_facility' || $mode == 'edit_facility') { // เพิ่ม edit_facility ด้วย
    $result_buildings = $conn->query("SELECT building_id, building_name FROM buildings ORDER BY building_name");
    if ($result_buildings) {
        while ($row = $result_buildings->fetch_assoc()) {
            $buildings[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'; ?>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/th.js'></script>
</head>
<body>
    <nav class="navbar navbar-dark navigator">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3">
                <a href="admin-data_view-page.php">
                    <img src="./images/logo.png" class="img-fluid logo" alt="Logo">
                </a>
                <div class="d-flex flex-column">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4">ระบบขอใช้อุปกรณ์และสถานที่</span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5">KU FTD</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mx-auto">
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="admin-main-page.php">การจัดการระบบ</a>
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="admin-data_view-page.php">ตรวจสอบอุปกรณ์และสถานที่</a>
            </div>
            <div class="d-flex align-items-center ms-auto gap-2">
                <div class="d-flex flex-column text-end">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo $staff_THname . ' ' . $staff_THsur; ?></span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5"><?php echo $user_role; ?></span>
                </div>
                <a href="admin-profile-page.php">
                    <img src="./images/user_button.png" class="img-fluid logo" style="width:40px; height:40px; object-fit:cover;" alt="User Button">
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-0">
        <?php if (in_array($mode, ['add_building', 'add_facility', 'add_equipment', 'edit_building', 'edit_facility', 'edit_equipment'])) : ?>
            <div class="card shadow-sm p-4 mt-3">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger" role="alert">
                        <h4 class="alert-heading">เกิดข้อผิดพลาด!</h4>
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($mode == 'add_building'): ?>
                    <h2 class="mb-4 text-center fw-bold fs-5">สร้างอาคารใหม่</h2>
                    <form action="admin-data_view-page.php?mode=add_building" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="inject_type" value="building">
                        <div class="mb-3"><label for="building_id" class="form-label fw-bold">หมายเลขอาคาร:</label><input type="text" id="building_id" name="building_id" class="form-control" value="<?php echo htmlspecialchars($_POST['building_id'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="building_name" class="form-label fw-bold">ชื่ออาคาร:</label><input type="text" id="building_name" name="building_name" class="form-control" value="<?php echo htmlspecialchars($_POST['building_name'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="building_pic" class="form-label fw-bold">รูปภาพ:</label><input type="file" id="building_pic" name="building_pic" class="form-control" accept="image/*"></div>
                        <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-primary btn-lg flex-fill">บันทึก</button><a href="admin-data_view-page.php?mode=buildings" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a></div>
                    </form>
                <?php elseif ($mode == 'edit_building' && $detail_item): ?>
                    <h2 class="mb-4 text-center fw-bold fs-5">แก้ไขอาคาร: <?php echo htmlspecialchars($detail_item['building_name']); ?></h2>
                    <form action="admin-data_view-page.php?mode=edit_building" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="inject_type" value="update_building">
                        <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($detail_item['building_id']); ?>">
                        <input type="hidden" name="old_building_pic" value="<?php echo htmlspecialchars($detail_item['building_pic'] ?? ''); ?>">
                        
                        <div class="mb-3"><label for="building_id_display" class="form-label fw-bold">หมายเลขอาคาร:</label><input type="text" id="building_id_display" class="form-control" value="<?php echo htmlspecialchars($detail_item['building_id']); ?>" readonly></div>
                        <div class="mb-3"><label for="building_name" class="form-label fw-bold">ชื่ออาคาร:</label><input type="text" id="building_name" name="building_name" class="form-control" value="<?php echo htmlspecialchars($detail_item['building_name'] ?? ''); ?>" required></div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">รูปภาพปัจจุบัน:</label><br>
                            <?php if ($detail_item['building_pic'] && file_exists($detail_item['building_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($detail_item['building_pic']); ?>" class="img-thumbnail mb-2" style="max-width: 200px;" alt="Current Building Picture">
                            <?php else: ?>
                                <p>ไม่มีรูปภาพ</p>
                            <?php endif; ?>
                            <label for="building_pic" class="form-label fw-bold">เปลี่ยนรูปภาพ (เลือกไฟล์ใหม่หากต้องการเปลี่ยน):</label>
                            <input type="file" id="building_pic" name="building_pic" class="form-control" accept="image/*">
                        </div>
                        <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-primary btn-lg flex-fill">บันทึกการแก้ไข</button><a href="admin-data_view-page.php?mode=building_detail&building_id=<?php echo htmlspecialchars($detail_item['building_id']); ?>" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a></div>
                    </form>
                <?php elseif ($mode == 'add_facility'): ?>
                    <h2 class="mb-4 text-center fw-bold fs-5">สร้างสถานที่ใหม่</h2>
                    <form action="admin-data_view-page.php?mode=add_facility&building_id=<?php echo htmlspecialchars($_GET['building_id'] ?? ''); ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="inject_type" value="facility">
                        <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($_GET['building_id'] ?? ''); ?>">
                        <div class="mb-3">
                            <label for="facility_name" class="form-label fw-bold">ชื่อสถานที่ (Facility Name):</label>
                            <input type="text" id="facility_name" name="facility_name" class="form-control" value="<?php echo htmlspecialchars($_POST['facility_name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="facility_des" class="form-label fw-bold">รายละเอียดสถานที่ (Description):</label>
                            <textarea id="facility_des" name="facility_des" rows="4" class="form-control"><?php echo htmlspecialchars($_POST['facility_des'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="facility_pic" class="form-label fw-bold">รูปภาพ (Image):</label>
                            <input type="file" id="facility_pic" name="facility_pic" class="form-control" accept="image/*">
                        </div>
                        <?php
                        $selected_building_name = '';
                        if (isset($_GET['building_id'])) {
                            foreach ($buildings as $building) {
                                if ($building['building_id'] == $_GET['building_id']) {
                                    $selected_building_name = $building['building_name'];
                                    break;
                                }
                            }
                        }
                        ?>
                        <div class="mb-3">
                            <label class="form-label fw-bold">อาคาร:</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($selected_building_name); ?>" readonly>
                        </div>
                        <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-primary btn-lg flex-fill">บันทึก</button><a href="admin-data_view-page.php?mode=building_detail&building_id=<?php echo htmlspecialchars($_GET['building_id'] ?? ''); ?>" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a></div>
                    </form>
                <?php elseif ($mode == 'edit_facility' && $detail_item): ?>
                    <h2 class="mb-4 text-center fw-bold fs-5">แก้ไขสถานที่: <?php echo htmlspecialchars($detail_item['facility_name']); ?></h2>
                    <form action="admin-data_view-page.php?mode=edit_facility" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="inject_type" value="update_facility">
                        <input type="hidden" name="facility_id" value="<?php echo htmlspecialchars($detail_item['facility_id']); ?>">
                        <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($detail_item['building_id']); ?>">
                        <input type="hidden" name="old_facility_pic" value="<?php echo htmlspecialchars($detail_item['facility_pic'] ?? ''); ?>">
                        
                        <div class="mb-3">
                            <label for="facility_name" class="form-label fw-bold">ชื่อสถานที่ (Facility Name):</label>
                            <input type="text" id="facility_name" name="facility_name" class="form-control" value="<?php echo htmlspecialchars($detail_item['facility_name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="facility_des" class="form-label fw-bold">รายละเอียดสถานที่ (Description):</label>
                            <textarea id="facility_des" name="facility_des" rows="4" class="form-control"><?php echo htmlspecialchars($detail_item['facility_des'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">รูปภาพปัจจุบัน:</label><br>
                            <?php if ($detail_item['facility_pic'] && file_exists($detail_item['facility_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($detail_item['facility_pic']); ?>" class="img-thumbnail mb-2" style="max-width: 200px;" alt="Current Facility Picture">
                            <?php else: ?>
                                <p>ไม่มีรูปภาพ</p>
                            <?php endif; ?>
                            <label for="facility_pic" class="form-label fw-bold">เปลี่ยนรูปภาพ (เลือกไฟล์ใหม่หากต้องการเปลี่ยน):</label>
                            <input type="file" id="facility_pic" name="facility_pic" class="form-control" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label for="building_select" class="form-label fw-bold">อาคาร:</label>
                            <select id="building_select" name="building_id" class="form-select" required>
                                <?php foreach ($buildings as $building_option): ?>
                                    <option value="<?php echo htmlspecialchars($building_option['building_id']); ?>" <?php echo ($building_option['building_id'] == $detail_item['building_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($building_option['building_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary btn-lg flex-fill">บันทึกการแก้ไข</button>
                            <a href="admin-data_view-page.php?mode=building_detail&building_id=<?php echo htmlspecialchars($detail_item['building_id'] ?? ''); ?>" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a>
                        </div>
                    </form>
                <?php elseif ($mode == 'add_equipment'): ?>
                    <h2 class="mb-4 text-center fw-bold fs-5">เพิ่มอุปกรณ์ใหม่</h2>
                    <form action="admin-data_view-page.php?mode=add_equipment" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="inject_type" value="equipment">
                        <div class="mb-3"><label for="equip_name" class="form-label fw-bold">ชื่ออุปกรณ์:</label><input type="text" id="equip_name" name="equip_name" class="form-control" value="<?php echo htmlspecialchars($_POST['equip_name'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="quantity" class="form-label fw-bold">จำนวน:</label><input type="number" id="quantity" name="quantity" class="form-control" value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>" min="1" required></div>
                        <div class="mb-3"><label for="measure" class="form-label fw-bold">หน่วยวัด:</label><input type="text" id="measure" name="measure" class="form-control" value="<?php echo htmlspecialchars($_POST['measure'] ?? ''); ?>"></div>
                        <div class="mb-3"><label for="size" class="form-label fw-bold">ขนาด:</label><input type="text" id="size" name="size" class="form-control" value="<?php echo htmlspecialchars($_POST['size'] ?? ''); ?>"></div>
                        <div class="mb-3"><label for="equip_pic" class="form-label fw-bold">รูปภาพอุปกรณ์:</label><input type="file" id="equip_pic" name="equip_pic" class="form-control" accept="image/*"></div>
                        <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-primary btn-lg flex-fill">บันทึก</button><a href="admin-data_view-page.php?mode=equipment" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a></div>
                    </form>
                <?php elseif ($mode == 'edit_equipment' && $detail_item): ?>
                    <h2 class="mb-4 text-center fw-bold fs-5">แก้ไขอุปกรณ์: <?php echo htmlspecialchars($detail_item['equip_name']); ?></h2>
                    <form action="admin-data_view-page.php?mode=edit_equipment" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="inject_type" value="update_equipment">
                        <input type="hidden" name="equip_id" value="<?php echo htmlspecialchars($detail_item['equip_id']); ?>">
                        <input type="hidden" name="old_equip_pic" value="<?php echo htmlspecialchars($detail_item['equip_pic'] ?? ''); ?>">

                        <div class="mb-3"><label for="equip_name" class="form-label fw-bold">ชื่ออุปกรณ์:</label><input type="text" id="equip_name" name="equip_name" class="form-control" value="<?php echo htmlspecialchars($detail_item['equip_name'] ?? ''); ?>" required></div>
                        <div class="mb-3"><label for="quantity" class="form-label fw-bold">จำนวน:</label><input type="number" id="quantity" name="quantity" class="form-control" value="<?php echo htmlspecialchars($detail_item['quantity'] ?? '1'); ?>" min="1" required></div>
                        <div class="mb-3"><label for="measure" class="form-label fw-bold">หน่วยวัด:</label><input type="text" id="measure" name="measure" class="form-control" value="<?php echo htmlspecialchars($detail_item['measure'] ?? ''); ?>"></div>
                        <div class="mb-3"><label for="size" class="form-label fw-bold">ขนาด:</label><input type="text" id="size" name="size" class="form-control" value="<?php echo htmlspecialchars($detail_item['size'] ?? ''); ?>"></div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">รูปภาพปัจจุบัน:</label><br>
                            <?php if ($detail_item['equip_pic'] && file_exists($detail_item['equip_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($detail_item['equip_pic']); ?>" class="img-thumbnail mb-2" style="max-width: 200px;" alt="Current Equipment Picture">
                            <?php else: ?>
                                <p>ไม่มีรูปภาพ</p>
                            <?php endif; ?>
                            <label for="equip_pic" class="form-label fw-bold">เปลี่ยนรูปภาพ (เลือกไฟล์ใหม่หากต้องการเปลี่ยน):</label>
                            <input type="file" id="equip_pic" name="equip_pic" class="form-control" accept="image/*">
                        </div>
                        <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-primary btn-lg flex-fill">บันทึกการแก้ไข</button><a href="admin-data_view-page.php?mode=equipment" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a></div>
                    </form>
                <?php endif; ?>
            </div>

        <?php else: // === ส่วนแสดงรายการข้อมูลปกติ (เมื่อ $mode ไม่ใช่ add_... หรือ edit_...) === ?>
            <h1 class="mb-3 text-center">การจัดการอาคาร สถานที่และอุปกรณ์</h1>
            <?php if (!empty($errors)): // แสดง error ที่เกิดจากการดึงข้อมูล ?>
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">เกิดข้อผิดพลาด!</h4>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['status']) && $_GET['status'] == 'success'): // แสดงข้อความสำเร็จจากการเพิ่มข้อมูล ?>
                <div class="alert alert-success" role="alert">
                    <h4 class="alert-heading">สำเร็จ!</h4>
                    <p><?php echo htmlspecialchars($_GET['message'] ?? 'บันทึกข้อมูลสำเร็จแล้ว!'); ?></p>
                </div>
            <?php elseif (isset($_GET['status'])): // แสดงสถานะอื่นๆ ?>
                <div class="alert alert-warning" role="alert">
                    <?php if ($_GET['status'] == 'building_not_found'): ?>
                        ไม่พบข้อมูลอาคารที่คุณร้องขอ
                    <?php elseif ($_GET['status'] == 'facility_not_found'): ?>
                        ไม่พบข้อมูลสิ่งอำนวยความสะดวกที่คุณร้องขอ
                    <?php elseif ($_GET['status'] == 'equip_not_found'): ?>
                        ไม่พบข้อมูลอุปกรณ์ที่คุณร้องขอ
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="row mb-3 align-items-center">
                <div class="col-md-6">
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($mode == 'buildings' || $mode == 'building_detail' || $mode == 'facility_detail') ? 'active' : ''; ?>" aria-current="page" href="admin-data_view-page.php?mode=buildings">
                                <i class="bi bi-building"></i> อาคารทั้งหมด
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($mode == 'equipment' || $mode == 'equip_detail') ? 'active' : ''; ?>" href="admin-data_view-page.php?mode=equipment">
                                <i class="bi bi-tools"></i> อุปกรณ์ทั้งหมด
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <div class="d-flex justify-content-end">
                        <?php if ($mode == 'buildings' || $mode == 'building_detail'): ?>
                            <button type="button" class="btn btn-warning me-2" data-bs-toggle="modal" data-bs-target="#calendarModal">
                                <i class="bi bi-calendar-event"></i> ดูปฏิทินการขอใช้อาคารสถานที่
                            </button>
                        <?php endif; ?>

                        <form class="d-flex" action="admin-data_view-page.php" method="GET">
                            <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
                            <?php if ($mode == 'building_detail' && isset($_GET['building_id'])): ?>
                                <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($_GET['building_id']); ?>">
                            <?php endif; ?>
                            <input class="form-control me-2" type="search" placeholder="ค้นหา..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-outline-success" type="submit">ค้นหา</button>
                            <?php if (!empty($search_query)): ?>
                                <a href="admin-data_view-page.php?mode=<?php echo htmlspecialchars($mode); ?><?php echo ($mode == 'building_detail' && isset($_GET['building_id'])) ? '&building_id=' . htmlspecialchars($_GET['building_id']) : ''; ?>" class="btn btn-secondary ms-2">ล้าง</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <?php?>
            <?php
            if ($mode == 'facility_detail' && $detail_item):
                $back_link = 'admin-data_view-page.php?mode=building_detail&building_id=' . htmlspecialchars($detail_item['building_id']);
            ?>
                <div class="card p-3 my-3">
                    <h2 class="card-title mb-3">รายละเอียดสถานที่: <?php echo htmlspecialchars($detail_item['facility_name']); ?></h2>
                    <div class="row">
                        <div class="col-12 col-md-4 text-center">
                            <?php if ($detail_item['facility_pic'] && file_exists($detail_item['facility_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($detail_item['facility_pic']); ?>" class="detail-img img-fluid" alt="Facility Picture">
                            <?php else: ?>
                                <img src="./images/placeholder.png" class="detail-img img-fluid" alt="No Image">
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-8 details-text">
                            <p class="mb-1"><strong>ชื่อสถานที่:</strong> <?php echo htmlspecialchars($detail_item['facility_name']); ?></p>
                            <p class="mb-1"><strong>รายละเอียด:</strong> <?php echo nl2br(htmlspecialchars($detail_item['facility_des'])); ?></p>
                            <p class="mb-1"><strong>อาคาร:</strong> <?php echo htmlspecialchars($detail_item['building_id']); ?></p>
                            <p class="mb-1"><strong>ชื่ออาคาร:</strong> <?php echo htmlspecialchars($detail_item['building_name']); ?></p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex gap-2 mt-3">
                            <a href="<?php echo htmlspecialchars($back_link); ?>" class="btn btn-secondary">ย้อนกลับ</a>
                            <div class="ms-auto d-flex gap-2">
                                <a href="admin-data_view-page.php?mode=edit_facility&facility_id=<?php echo htmlspecialchars($detail_item['facility_id']); ?>" class="btn btn-warning text-dark">แก้ไขข้อมูลสถานที่</a>
                                <button type="button" class="btn btn-danger" 
                                        data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal"
                                        data-id="<?php echo htmlspecialchars($detail_item['facility_id']); ?>"
                                        data-name="<?php echo htmlspecialchars($detail_item['facility_name']); ?>"
                                        data-type="facility"
                                        data-redirect-building-id="<?php echo htmlspecialchars($detail_item['building_id']); ?>">
                                    ลบสถานที่
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            <?php
            elseif ($mode == 'equip_detail' && $detail_item):
                $back_link = 'admin-data_view-page.php?mode=equipment';
            ?>
                <div class="card p-3 my-3">
                    <h2 class="card-title mb-3">รายละเอียดอุปกรณ์: <?php echo htmlspecialchars($detail_item['equip_name']); ?></h2>
                    <div class="row">
                        <div class="col-12 col-md-4 text-center">
                            <?php if ($detail_item['equip_pic'] && file_exists($detail_item['equip_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($detail_item['equip_pic']); ?>" class="detail-img img-fluid" alt="Equipment Picture">
                            <?php else: ?>
                                <img src="./images/placeholder.png" class="detail-img img-fluid" alt="No Image">
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-8 details-text">
                            <p class="mb-1"><strong>ชื่ออุปกรณ์:</strong> <?php echo htmlspecialchars($detail_item['equip_name']); ?></p>
                            <p class="mb-1"><strong>จำนวน:</strong> <?php echo htmlspecialchars($detail_item['quantity']); ?></p>
                            <p class="mb-1"><strong>หน่วยวัด:</strong> <?php echo htmlspecialchars($detail_item['measure']); ?></p>
                            <p class="mb-1"><strong>ขนาด:</strong> <?php echo htmlspecialchars($detail_item['size']); ?></p>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex gap-2 mt-3">
                            <a href="<?php echo htmlspecialchars($back_link); ?>" class="btn btn-secondary">ย้อนกลับ</a>
                            <div class="ms-auto d-flex gap-2">
                                <a href="admin-data_view-page.php?mode=edit_equipment&equip_id=<?php echo htmlspecialchars($detail_item['equip_id']); ?>" class="btn btn-warning text-dark">แก้ไขข้อมูลอุปกรณ์</a>
                                <button type="button" class="btn btn-danger" 
                                        data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal"
                                        data-id="<?php echo htmlspecialchars($detail_item['equip_id']); ?>"
                                        data-name="<?php echo htmlspecialchars($detail_item['equip_name']); ?>"
                                        data-type="equipment">
                                    ลบอุปกรณ์
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            <?php
                else: 
                    if ($mode == 'building_detail' && $detail_item):
            ?>
                        <div class="card p-3 my-3 bg-light">
                            <div class="d-flex align-items-center">
                                <?php if ($detail_item['building_pic'] && file_exists($detail_item['building_pic'])): ?>
                                    <img src="<?php echo htmlspecialchars($detail_item['building_pic']); ?>" alt="Building Pic" class="img-thumbnail me-3" style="width: 70px; height: 70px; object-fit: cover;">
                                <?php endif; ?>
                                <div>
                                    <h3 class="mb-0 fs-5">สถานที่ภายในอาคาร: <?php echo htmlspecialchars($detail_item['building_name']); ?></h3>
                                    <small class="text-muted">คลิกที่รูปภาพสถานที่เพื่อดูรายละเอียด</small>
                                </div>
                            </div>
                            <div class="col">
                                <div class="d-flex gap-2 mt-3">
                                    <a href="admin-data_view-page.php?mode=buildings" class="btn btn-secondary">ย้อนกลับไปดูอาคารทั้งหมด</a>
                                    <div class="ms-auto d-flex gap-2">
                                        <a href="admin-data_view-page.php?mode=edit_building&building_id=<?php echo htmlspecialchars($detail_item['building_id']); ?>" class="btn btn-warning text-dark">แก้ไขข้อมูลอาคาร</a>
                                        <button type="button" class="btn btn-danger"
                                                data-bs-toggle="modal" data-bs-target="#deleteConfirmationModal"
                                                data-id="<?php echo htmlspecialchars($detail_item['building_id']); ?>"
                                                data-name="<?php echo htmlspecialchars($detail_item['building_name']); ?>"
                                                data-type="building">
                                            ลบอาคาร
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
            <?php
                endif; 

                if (empty($data) && !$show_add_card && ($mode != 'building_detail' || ($mode == 'building_detail' && $total_items == 0))):
            ?>
                    <div class="alert alert-info text-center mt-4">
                        <?php if (!empty($search_query)): ?>
                            ไม่พบข้อมูลที่คุณค้นหา "<?php echo htmlspecialchars($search_query); ?>"
                        <?php else: ?>
                            <?php if ($mode == 'buildings'): ?>
                                ยังไม่มีข้อมูลอาคารในระบบ
                            <?php elseif ($mode == 'equipment'): ?>
                                ยังไม่มีข้อมูลอุปกรณ์ในระบบ
                            <?php elseif ($mode == 'building_detail' && $detail_item): ?>
                                ยังไม่มีข้อมูลสถานที่ในอาคาร "<?php echo htmlspecialchars($detail_item['building_name']); ?>"
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
            <?php
                endif; 
            ?>
                    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-3 g-2 mt-0">
                        <?php
                        if ($mode == 'building_detail' && empty($search_query) && $detail_item): 
                        ?>
                            <div class="col">
                                <a href="admin-data_view-page.php?mode=add_facility&building_id=<?php echo htmlspecialchars($_GET['building_id']); ?>" class="text-decoration-none">
                                    <div class="card h-100 shadow-sm border-success border-2 d-flex">
                                        <div class="card-body d-flex flex-column justify-content-center align-items-center my-auto">
                                            <i class="bi bi-plus-circle display-4 text-success mb-2"></i>
                                            <h5 class="card-title text-success">เพิ่มสถานที่</h5>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php
                        endif; 
                        ?>

                        <?php if ($show_add_card): ?>
                            <div class="col">
                                <a href="admin-data_view-page.php?mode=<?php echo ($mode == 'buildings' ? 'add_building' : 'add_equipment'); ?>" class="text-decoration-none">
                                    <div class="card h-100 shadow-sm border-primary border-2 d-flex">
                                        <div class="card-body d-flex flex-column justify-content-center align-items-center my-auto">
                                            <i class="bi bi-plus-circle display-4 text-primary mb-2"></i>
                                            <h5 class="card-title text-primary"><?php echo $mode == 'buildings' ? 'เพิ่มอาคาร' : 'เพิ่มอุปกรณ์'; ?></h5>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php foreach ($data as $item): ?>
                                <div class="col">
                                    <div class="card h-100 shadow-sm">
                                        <?php
                                        $img_src = './images/placeholder.png';
                                        $item_name = '';
                                        $link_href = '#';
                                        $item_id = '';
                                        $item_type = '';
                                        $redirect_building_id = ''; // สำหรับ Facility

                                        if ($mode == 'buildings') {
                                            $img_src = ($item['building_pic'] && file_exists($item['building_pic'])) ? htmlspecialchars($item['building_pic']) : $img_src;
                                            $item_name = 'อาคาร ' . htmlspecialchars($item['building_id']) . ': ' . htmlspecialchars($item['building_name']);
                                            $link_href = 'admin-data_view-page.php?mode=building_detail&building_id=' . htmlspecialchars($item['building_id']);
                                            $item_id = $item['building_id'];
                                            $item_type = 'building';
                                        } elseif ($mode == 'equipment') {
                                            $img_src = ($item['equip_pic'] && file_exists($item['equip_pic'])) ? htmlspecialchars($item['equip_pic']) : $img_src;
                                            $item_name = htmlspecialchars($item['equip_name']);
                                            $link_href = 'admin-data_view-page.php?mode=equip_detail&equip_id=' . htmlspecialchars($item['equip_id']);
                                            $item_id = $item['equip_id'];
                                            $item_type = 'equipment';
                                        } elseif ($mode == 'building_detail') {
                                            $img_src = ($item['facility_pic'] && file_exists($item['facility_pic'])) ? htmlspecialchars($item['facility_pic']) : $img_src;
                                            $item_name = htmlspecialchars($item['facility_name']);
                                            $link_href = 'admin-data_view-page.php?mode=facility_detail&facility_id=' . htmlspecialchars($item['facility_id']);
                                            $item_id = $item['facility_id'];
                                            $item_type = 'facility';
                                            $redirect_building_id = htmlspecialchars($_GET['building_id'] ?? '');
                                        }
                                        ?>
                                        <a href="<?php echo $link_href; ?>" class="card-img-link">
                                            <img src="<?php echo $img_src; ?>" class="card-img-top" alt="<?php echo $item_name; ?>">
                                        </a>
                                        <div class="card-body d-flex flex-column">
                                            <?php if ($mode == 'buildings'): ?>
                                                <h5 class="card-title text-center">
                                                    <?php echo $item_name; ?>
                                                </h5>
                                                <?php if (!empty($search_query) && !empty($item['matched_facilities'])): ?>
                                                    <p class="card-text text-success mb-0 small text-center">
                                                        <i class="bi bi-search"></i> พบ: <?php echo htmlspecialchars($item['matched_facilities']); ?>
                                                    </p>
                                                <?php endif; ?>
                                            <?php elseif ($mode == 'equipment'): ?>
                                                <h5 class="card-title text-center"><?php echo $item_name; ?></h5>
                                                <p class="card-text text-muted mb-0 small text-center">จำนวน: <?php echo htmlspecialchars($item['quantity'] . ' ' . $item['measure']); ?></p>
                                                <p class="card-text text-muted mb-0 small text-center">ขนาด: <?php echo htmlspecialchars($item['size']); ?></p>
                                            <?php elseif ($mode == 'building_detail'): ?>
                                                <h5 class="card-title text-center"><?php echo $item_name; ?></h5>
                                                <p class="card-text text-muted mb-0 small text-center">รายละเอียด: <?php echo htmlspecialchars(mb_strimwidth($item['facility_des'], 0, 40, "...")); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                        <?php endforeach; ?>
                    </div>

                    <nav aria-label="Page navigation" class="pagination-container mt-2 mb-0">
                        <ul class="pagination pagination-lg">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item"><a class="page-link" href="admin-data_view-page.php?mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page - 1; ?>&search=<?php echo urlencode($search_query); ?><?php echo isset($_GET['building_id']) ? '&building_id='.$_GET['building_id'] : ''; ?>">ก่อนหน้า</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>"><a class="page-link" href="admin-data_view-page.php?mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $i; ?>&search=<?php echo urlencode($search_query); ?><?php echo isset($_GET['building_id']) ? '&building_id='.$_GET['building_id'] : ''; ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item"><a class="page-link" href="admin-data_view-page.php?mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page + 1; ?>&search=<?php echo urlencode($search_query); ?><?php echo isset($_GET['building_id']) ? '&building_id='.$_GET['building_id'] : ''; ?>">ถัดไป</a></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
            <?php
                endif; 
            endif; 
        ?>
    </div>

    <?php 
        if ($mode == 'buildings' || $mode == 'building_detail') {
            include 'php/calendar.php';
        }
    ?>

    <div class="modal fade delete-modal" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmationModalLabel"><i class="bi bi-exclamation-triangle-fill"></i> ยืนยันการลบข้อมูล</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="deleteForm" action="admin-data_view-page.php" method="POST">
                    <input type="hidden" name="inject_type" id="delete_inject_type">
                    <input type="hidden" name="delete_id" id="delete_item_id">
                    <input type="hidden" name="building_id_for_redirect" id="delete_redirect_building_id">
                    <div class="modal-body">
                        คุณต้องการลบ <strong id="delete_item_name"></strong> นี้หรือไม่? การดำเนินการนี้ไม่สามารถย้อนกลับได้.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-danger">ลบ</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php 
        if (isset($conn)) {
            $conn->close();
        }
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
    <script src="./js/calendar.js"></script>
    <script>
        // JavaScript สำหรับจัดการ Modal ยืนยันการลบ
        var deleteConfirmationModal = document.getElementById('deleteConfirmationModal');
        deleteConfirmationModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget; // Button that triggered the modal
            var id = button.getAttribute('data-id');
            var name = button.getAttribute('data-name');
            var type = button.getAttribute('data-type');
            var redirectBuildingId = button.getAttribute('data-redirect-building-id'); // สำหรับ facility

            var modalTitle = deleteConfirmationModal.querySelector('.modal-title');
            var modalBodyInput = deleteConfirmationModal.querySelector('#delete_item_name');
            var deleteForm = deleteConfirmationModal.querySelector('#deleteForm');
            var deleteItemId = deleteConfirmationModal.querySelector('#delete_item_id');
            var deleteInjectType = deleteConfirmationModal.querySelector('#delete_inject_type');
            var deleteRedirectBuildingId = deleteConfirmationModal.querySelector('#delete_redirect_building_id');

            modalTitle.textContent = 'ยืนยันการลบข้อมูล ' + (type === 'building' ? 'อาคาร' : (type === 'facility' ? 'สถานที่' : 'อุปกรณ์'));
            modalBodyInput.textContent = name;
            deleteItemId.value = id;
            deleteInjectType.value = 'delete_' + type;
            
            if (type === 'facility' && redirectBuildingId) {
                deleteRedirectBuildingId.value = redirectBuildingId;
            } else {
                deleteRedirectBuildingId.value = ''; // Clear for other types
            }
        });
    </script>
</body>
</html>