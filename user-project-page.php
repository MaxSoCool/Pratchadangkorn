<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

include 'database/database.php';

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'N/A');
$user_name = htmlspecialchars($_SESSION['user_display_name'] ?? 'N/A');
$user_sur = htmlspecialchars($_SESSION['user_display_sur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$fa_de_name = htmlspecialchars($_SESSION['fa_de_name'] ?? 'ไม่ระบุ');
$nontri_id = htmlspecialchars($_SESSION['nontri_id'] ?? 'N/A');
$user_id = $_SESSION['nontri_id'] ?? '';

$main_tab = $_GET['main_tab'] ?? 'user_dashboard';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'projects_list';

$errors = [];
$success_message = '';

include 'php/helpers.php';
include 'php/sorting.php';
include 'php/ajax_handler.php';
include 'php/status_update.php';
include 'php/user-injection.php';

$previous = $_SERVER['HTTP_REFERER'] ?? '';
$current_mode = $_GET['mode'] ?? '';
$this_tab = $_GET['this_tab'] ?? '';

if ($current_mode === 'projects_detail') {
    if (strpos($previous, 'mode=buildings_detail') === false &&
        strpos($previous, 'mode=equipments_detail') === false) {
        $_SESSION['projects_detail_initial_referrer'] = $previous;
    }
} else {
    if ($current_mode === 'projects_list' || $current_mode === 'user_dashboard' || $this_tab !== 'user_requests') {
         unset($_SESSION['projects_detail_initial_referrer']);
    }
}

if ($current_mode === 'projects_detail' &&
   (strpos($previous, 'mode=buildings_detail') !== false || strpos($previous, 'mode=equipments_detail') !== false)) {
    if (isset($_SESSION['projects_detail_initial_referrer']) && !empty($_SESSION['projects_detail_initial_referrer'])) {
        $previous = $_SESSION['projects_detail_initial_referrer'];
    } else {
        $previous = '?this_tab=user_dashboard';
    }
} elseif (strpos($previous, 'this_tab=user_requests') !== false) {
    if (strpos($previous, 'mode=projects_edit') !== false || strpos($previous, 'mode=projects_create') !== false) {
        $previous = '?this_tab=user_requests&mode=projects_list';
    } elseif (strpos($previous, 'mode=buildings_edit') !== false || strpos($previous, 'mode=buildings_create') !== false) {
        $previous = '?this_tab=user_requests&mode=buildings_list';
    } elseif (strpos($previous, 'mode=equipments_edit') !== false || strpos($previous, 'mode=equipments_create') !== false) {
        $previous = '?this_tab=user_requests&mode=equipments_list';
    }
} elseif (strpos($previous, 'this_tab=user_dashboard') !== false) {
    $previous = '?this_tab=user_dashboard';
} elseif (empty($previous)) {
    $previous = '?this_tab=user_dashboard';
}

include 'php/user-dropdown.php';

$dashboard_data = [
    'project_counts' => [
        'total' => 0, 'draft' => 0, 'submitted' => 0, 'in_progress' => 0,
        'completed' => 0, 'cancelled' => 0,
    ],
    'facilities_request_counts' => [
        'total' => 0, 'draft' => 0, 'submitted' => 0, 'pending_approval' => 0,
        'approved' => 0, 'rejected' => 0, 'in_progress' => 0,
        'completed' => 0, 'cancelled' => 0,
    ],
    'equipments_request_counts' => [
        'total' => 0, 'draft' => 0, 'submitted' => 0, 'pending_approval' => 0,
        'approved' => 0, 'rejected' => 0, 'in_progress' => 0,
        'completed' => 0, 'cancelled' => 0,
    ],
    'upcoming_requests' => [],
    'recent_activity' => [],
];

include 'php/user-dashboard.php';

// --- Data Fetching for Lists and Details (ถ้าอยู่ใน main_tab user_requests) ---
// (เรียกข้อมูลหลักสำหรับแสดงรายการหรือรายละเอียด)
$data = []; // Initialize before fetching
$detail_item = null; // Initialize before fetching
if ($main_tab == 'user_requests') {
    include 'php/user-data-list.php';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'?>
</head>
<body>
    <nav class="navbar navbar-dark navigator">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3">
                <a href="?main_tab=user_dashboard">
                    <img src="./images/logo.png" class="img-fluid logo" alt="Logo">
                </a>
                <div class="d-flex flex-column">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4">ระบบขอใช้อุปกรณ์และสถานที่</span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5">KU FTD</span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mx-auto">
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="user-project-page.php">ข้อมูลคำร้อง</a>
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="user-data_view-page.php">ตรวจสอบอุปกรณ์และสถานที่</a>
            </div>
            <div class="d-flex align-items-center ms-auto gap-2">
                <div class="d-flex flex-column text-end">
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo $user_name . ' ' . $user_sur; ?></span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5"><?php echo $user_role; ?></span>
                </div>
                <a href="user-profile-page.php">
                    <img src="./images/user_button.png" class="img-fluid logo" style="width:40px; height:40px; object-fit:cover;" alt="User Button">
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-0">
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

        <?php if ($main_tab == 'user_requests') : ?>
            <?php if ($mode == 'projects_list') : ?>
                <h1 class="mb-3 text-center">ข้อมูลโครงการของผู้ใช้</h1>
            <?php elseif ($mode == 'buildings_list') : ?>
                <h1 class="mb-3 text-center">ข้อมูลคำร้องขอใช้อาคารและสถานที่</h1>
            <?php elseif ($mode == 'equipments_list') : ?>
                <h1 class="mb-3 text-center">ข้อมูลคำร้องขอใช้อุปกรณ์</h1>
            <?php endif; ?>
        <?php else: ?>
            <h1 class="mb-3 text-center">ภาพรวมคำร้องของผู้ใช้</h1>
        <?php endif; ?>

        <div class="row mb-3 align-items-center">
            <div class="col-md-6 inbox-text">
                <?php if ($main_tab == 'user_dashboard' || ($main_tab == 'user_requests' && in_array($mode, ['projects_list', 'buildings_list', 'equipments_list']))): ?>
                    <ul class="nav nav-tabs">
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($main_tab == 'user_dashboard') ? 'active' : ''; ?>" aria-current="page" href="?main_tab=user_dashboard">
                                <i class="bi bi-folder"></i> ภาพรวม
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($main_tab == 'user_requests' && ($mode == 'projects_list' || $mode == 'projects_create' || $mode == 'projects_detail' || $mode == 'projects_edit')) ? 'active' : ''; ?>" href="?main_tab=user_requests&mode=projects_list">
                                <i class="bi bi-folder"></i> โครงการ
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($main_tab == 'user_requests' && ($mode == 'buildings_list' || $mode == 'buildings_create' || $mode == 'buildings_detail' || $mode == 'buildings_edit')) ? 'active' : ''; ?>" href="?main_tab=user_requests&mode=buildings_list">
                                <i class="bi bi-building"></i> คำร้องขออาคารและสถานที่ทั้งหมด
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($main_tab == 'user_requests' && ($mode == 'equipments_list' || $mode == 'equipments_create' || $mode == 'equipments_detail' || $mode == 'equipments_edit')) ? 'active' : ''; ?>" href="?main_tab=user_requests&mode=equipments_list">
                                <i class="bi bi-tools"></i> คำร้องขออุปกรณ์ทั้งหมด
                            </a>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>
            <?php if ($main_tab == 'user_requests' && in_array($mode, ['projects_list', 'buildings_list', 'equipments_list'])): ?>
                <div class="col-md-6">
                    <form class="d-flex align-items-center" action="" method="GET">
                        <input type="hidden" name="main_tab" value="user_requests">
                        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">

                        <select name="sort_filter" class="form-select me-2" onchange="this.form.submit()" style="width: auto;">
                            <optgroup label="เรียงตามวันที่">
                                <option value="date_desc" <?php echo (($_GET['sort_filter'] ?? 'date_desc') == 'date_desc') ? 'selected' : ''; ?>>ใหม่สุดไปเก่าสุด</option>
                                <option value="date_asc" <?php echo (($_GET['sort_filter'] ?? '') == 'date_asc') ? 'selected' : ''; ?>>เก่าสุดไปใหม่สุด</option>
                            </optgroup>
                            <optgroup label="กรองตามสถานะ">
                                <option value="all" <?php echo (($_GET['sort_filter'] ?? '') == 'all') ? 'selected' : ''; ?>>แสดงทุกสถานะ</option>
                                <?php if ($mode == 'projects_list'): ?>
                                    <option value="ร่างโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'ร่างโครงการ') ? 'selected' : ''; ?>>ร่างโครงการ</option>
                                    <option value="ส่งโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'ส่งโครงการ') ? 'selected' : ''; ?>>ส่งโครงการ</option>
                                    <option value="เริ่มดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'เริ่มดำเนินการ') ? 'selected' : ''; ?>>เริ่มดำเนินการ</option>
                                    <option value="สิ้นสุดโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'สิ้นสุดโครงการ') ? 'selected' : ''; ?>>สิ้นสุดโครงการ</option>
                                    <option value="ยกเลิกโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'ยกเลิกโครงการ') ? 'selected' : ''; ?>>ยกเลิกโครงการ</option>
                                <?php elseif ($mode == 'buildings_list' || $mode == 'equipments_list'): ?>
                                    <option value="ร่างคำร้องขอ" <?php echo (($_GET['sort_filter'] ?? '') == 'ร่างคำร้องขอ') ? 'selected' : ''; ?>>ร่างคำร้องขอ</option>
                                    <option value="ส่งคำร้องขอ" <?php echo (($_GET['sort_filter'] ?? '') == 'ส่งคำร้องขอ') ? 'selected' : ''; ?>>ส่งคำร้องขอ</option>
                                    <option value="อนุมัติ" <?php echo (($_GET['sort_filter'] ?? '') == 'อนุมัติ') ? 'selected' : ''; ?>>อนุมัติ</option>
                                    <option value="ไม่อนุมัติ" <?php echo (($_GET['sort_filter'] ?? '') == 'ไม่อนุมัติ') ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                                    <option value="เริ่มดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'เริ่มดำเนินการ') ? 'selected' : ''; ?>>เริ่มดำเนินการ</option>
                                    <option value="สิ้นสุดดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'สิ้นสุดดำเนินการ') ? 'selected' : ''; ?>>สิ้นสุดดำเนินการ</option>
                                    <option value="ยกเลิกคำร้องขอ" <?php echo (($_GET['sort_filter'] ?? '') == 'ยกเลิกคำร้องขอ') ? 'selected' : ''; ?>>ยกเลิกคำร้องขอ</option>
                                <?php endif; ?>
                            </optgroup>
                        </select>

                        <input class="form-control me-2" type="search" placeholder="ค้นหา..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-outline-success" type="submit">ค้นหา</button>
                        <?php if (!empty($search_query) || !empty($_GET['sort_filter'])): ?>
                            <a href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>" class="btn btn-outline-secondary ms-2">ล้าง</a>
                        <?php endif; ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($main_tab == 'user_dashboard'): ?>
		    <div class="row row-cols-1 row-cols-md-3 g-4 mb-4">
                <div class="col">
                    <div class="card text-white bg-primary mb-3 h-100">
                        <div class="card-header"><i class="bi bi-folder-fill me-2"></i>โครงการ</div>
                        <div class="card-body">
                            <h5 class="card-title">โครงการของคุณ</h5>
                            <h2 class="card-text fs-1"><?php echo $dashboard_data['project_counts']['total']; ?></h2>
                        </div>
                        <div class="card-footer">
                            <a href="?main_tab=user_requests&mode=projects_list" class="stretched-link text-white text-decoration-none"> ดูโครงการทั้งหมด <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-white bg-success mb-3 h-100">
                        <div class="card-header"><i class="bi bi-building-fill me-2"></i>คำร้องขอใช้อาคารสถานที่</div>
                        <div class="card-body">
                            <h5 class="card-title">คำร้องขอใช้อาคารสถานที่ของคุณ</h5>
                            <h2 class="card-text fs-1"><?php echo $dashboard_data['facilities_request_counts']['total']; ?></h2>
                        </div>
                        <div class="card-footer">
                            <a href="?main_tab=user_requests&mode=buildings_list" class="stretched-link text-white text-decoration-none"> ดูคำร้องขอใช้อาคารสถานที่ทั้งหมด <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card text-dark bg-warning mb-3 h-100">
                        <div class="card-header"><i class="bi bi-tools me-2"></i>คำร้องขอใช้อุปกรณ์</div>
                        <div class="card-body">
                            <h5 class="card-title">คำร้องขอใช้อุปกรณ์ของคุณ</h5>
                            <h2 class="card-text fs-1"><?php echo $dashboard_data['equipments_request_counts']['total']; ?></h2>
                        </div>
                        <div class="card-footer">
                            <a href="?main_tab=user_requests&mode=equipments_list" class="stretched-link text-dark text-decoration-none"> ดูคำร้องขอใช้อุปกรณ์ทั้งหมด <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm p-4">
                            <h4 class="mb-3">โครงการที่กำลังจะมาถึง <span class="badge bg-secondary"><?php echo count($dashboard_data['upcoming_requests']); ?></span></h4>
                            <?php if (empty($dashboard_data['upcoming_requests'])): ?>
                                <div class="alert alert-info text-center py-3 mb-0">
                                    ยังไม่มีโครงการที่กำลังจะมาถึงใน 14 วันนี้
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($dashboard_data['upcoming_requests'] as $req):
                                        $detail_link = '';
                                        if ($req['type'] == 'โครงการ') {
                                            $detail_link = '?main_tab=user_requests&mode=projects_detail&project_id=' . $req['id'];
                                        } elseif ($req['type'] == 'คำร้องขอสถานที่') {
                                            $detail_link = '?main_tab=user_requests&mode=buildings_detail&facility_re_id=' . $req['id'];
                                        } elseif ($req['type'] == 'คำร้องขออุปกรณ์') {
                                            $detail_link = '?main_tab=user_requests&mode=equipments_detail&equip_re_id=' . $req['id'];
                                        }
                                    ?>
                                    <li class="list-group-item activity-item">
                                        <?php if ($detail_link): ?>
                                            <a href="<?php echo $detail_link; ?>" class="d-flex w-100 justify-content-between align-items-center stretched-link text-decoration-none text-dark">
                                        <?php else: ?>
                                            <div class="d-flex w-100 justify-content-between align-items-center text-dark">
                                        <?php endif; ?>
                                                <div class="main-info">
                                                    <span class="badge bg-primary me-2"><?php echo htmlspecialchars($req['type']); ?></span>
                                                    <strong><?php echo htmlspecialchars($req['name']); ?></strong>
                                                </div>
                                                <div class="date-info">
                                                    <span class="badge <?php echo getStatusBadgeClass($req['writed_status'], $req['approve']); ?>"><?php echo htmlspecialchars($req['writed_status']); ?></span><br>
                                                    <small class="text-muted">
                                                        <?php echo formatThaiDate($req['start_date'], false); ?>
                                                        <?php if ($req['start_time'] && $req['end_time']): ?>
                                                            (<?php echo (new DateTime($req['start_time']))->format('H:i'); ?>-<?php echo (new DateTime($req['end_time']))->format('H:i'); ?>)
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                        <?php if ($detail_link): ?>
                                            </a>
                                        <?php else: ?>
                                            </div>
                                        <?php endif; ?>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm p-4">
                            <h4 class="mb-3">การดำเนินการล่าสุด <span class="badge bg-secondary"><?php echo count($dashboard_data['recent_activity']); ?></span></h4>
                            <?php if (empty($dashboard_data['recent_activity'])): ?>
                                <div class="alert alert-info text-center py-3 mb-0">
                                    ไม่พบการดำเนินการล่าสุดของคุณ
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($dashboard_data['recent_activity'] as $activity):
                                        $detail_link = '';
                                        if ($activity['item_type'] == 'โครงการ') {
                                            $detail_link = '?main_tab=user_requests&mode=projects_detail&project_id=' . $activity['id'];
                                        } elseif ($activity['item_type'] == 'คำร้องขอสถานที่') {
                                            $detail_link = '?main_tab=user_requests&mode=buildings_detail&facility_re_id=' . $activity['id'];
                                        } elseif ($activity['item_type'] == 'คำร้องขออุปกรณ์') {
                                            $detail_link = '?main_tab=user_requests&mode=equipments_detail&equip_re_id=' . $activity['id'];
                                        }
                                    ?>
                                        <li class="list-group-item activity-item">
                                            <?php if ($detail_link): ?>
                                                <a href="<?php echo $detail_link; ?>" class="d-flex w-100 justify-content-between align-items-center stretched-link text-decoration-none text-dark ">
                                                    <div class="main-info">
                                                        <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($activity['item_type']); ?></span>
                                                        <strong><?php echo htmlspecialchars($activity['item_name']); ?></strong>
                                                    </div>
                                                    <div class="date-info">
                                                        <span class="badge <?php echo getStatusBadgeClass($activity['status_text'], $activity['approve_status'] ?? null); ?>"><?php echo htmlspecialchars($activity['approve_status'] ?? $activity['status_text']); ?></span><br>
                                                        <small class="text-muted"><?php echo formatThaiDate($activity['activity_date']); ?></small>
                                                    </div>
                                                </a>
                                            <?php else: ?>
                                                <div class="main-info">
                                                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($activity['item_type']); ?></span>
                                                    <strong><?php echo htmlspecialchars($activity['item_name']); ?></strong>
                                                </div>
                                                <div class="date-info">
                                                    <span class="badge <?php echo getStatusBadgeClass($activity['status_text'], $activity['approve_status'] ?? null); ?>"><?php echo htmlspecialchars($activity['approve_status'] ?? $activity['status_text']); ?></span><br>
                                                    <small class="text-muted"><?php echo formatThaiDate($activity['activity_date']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
            </div>
        <?php endif; ?>

        <?php if ($main_tab == 'user_requests'): ?>
            <?php if ($mode == 'projects_list'): ?>
                <div class="row mb-3">
                    <div class="col">
                        <div class="card shadow-sm p-3">
                            <h5 class="card-title mb-3">โครงการทั้งหมดของคุณ: <?php echo $dashboard_data['project_counts']['total']; ?> โครงการ</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <h6>
                                    <span class="badge bg-warning text-dark">ร่างโครงการ: <?php echo $dashboard_data['project_counts']['draft']; ?> </span>
                                    <span class="badge bg-primary">ส่งโครงการ: <?php echo $dashboard_data['project_counts']['submitted']; ?> </span>
                                    <span class="badge bg-info text-dark">เริ่มดำเนินการ: <?php echo $dashboard_data['project_counts']['in_progress']; ?> </span>
                                    <span class="badge bg-secondary">สิ้นสุดโครงการ: <?php echo $dashboard_data['project_counts']['completed']; ?> </span>
                                    <span class="badge bg-dark">ยกเลิกโครงการ: <?php echo $dashboard_data['project_counts']['cancelled']; ?> </span>
                                </h6>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex justify-content-end mb-3">
                            <a href="?main_tab=user_requests&mode=projects_create" class="btn btn-primary">
                                <i class="bi bi-plus-circle"></i> สร้างโครงการใหม่
                            </a>
                        </div>
                    </div>
                </div>
                <?php if (empty($data)): ?>
                    <div class="alert alert-info text-center mt-4">
                        <?php if (!empty($search_query)): ?>
                            ไม่พบโครงการที่คุณค้นหา "<?php echo htmlspecialchars($search_query); ?>"
                        <?php else: ?>
                            ยังไม่มีโครงการที่คุณสร้างไว้
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 g-3">
                        <?php foreach ($data as $project): ?>
                            <div class="col">
                                <a href="?main_tab=user_requests&mode=projects_detail&project_id=<?php echo $project['project_id']; ?>" class="text-decoration-none text-dark">
                                    <div class="card shadow-sm p-3">
                                        <div class="row g-0">
                                            <div class="col">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <h5 class="card-title mb-1"> ชื่อโครงการ: <?php echo htmlspecialchars($project['project_name']); ?></h5>
                                                    <div class="text-end">
                                                        <h5 class="card-title mb-1"> สถานะ:
                                                            <span class="badge <?php echo getStatusBadgeClass($project['writed_status']); ?>"><?php echo htmlspecialchars($project['writed_status']); ?></span>
                                                        </h5>
                                                        <p class="card-text small mb-1 text-muted">
                                                            ยื่นเมื่อ: <?php echo formatThaiDate($project['created_date']); ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <p class="card-text small mb-1">
                                                    <?php if ($project['start_date'] != $project['end_date']) : ?>
                                                        <strong>ระยะเวลาโครงการ: </strong>ตั้งแต่วันที่ <?php echo formatThaiDate($project['start_date'], false); ?> ถึงวันที่ <?php echo formatThaiDate($project['end_date'], false); ?>
                                                    <?php else: ?>
                                                        <strong>ระยะเวลาโครงการ: </strong>วันที่ <?php echo formatThaiDate($project['start_date'], false); ?>
                                                    <?php endif; ?>
                                                <p class="card-text small mb-1">
                                                    <strong>ประเภทกิจกรรม:</strong> <?php echo htmlspecialchars($project['activity_type_name'] ?? 'ไม่ระบุ'); ?>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <nav aria-label="Page navigation" class="pagination-container mt-3 mb-0">
                        <ul class="pagination pagination-lg">
                            <?php

                            $search_param_url = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                            $sort_param_url = !empty($_GET['sort_filter']) ? '&sort_filter=' . urlencode($_GET['sort_filter']) : '';
                            ?>

                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page - 1; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>">ก่อนหน้า</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $i; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page + 1; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>">ถัดไป</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($mode == 'projects_create'): ?>
                <div class="form-section my-4">
                    <h2 class="mb-4 text-center">สร้างโครงการใหม่</h2>
                    <form id="createProjectForm" action="?main_tab=user_requests&mode=projects_create" method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="project_name" class="form-label">ชื่อโครงการ:</label>
                            <input type="text" class="form-control" id="project_name" name="project_name" value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">วันเริ่มต้น:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">วันสิ้นสุด:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="project_des" class="form-label">รายละเอียดของโครงการ:</label>
                            <textarea class="form-control" id="project_des" name="project_des" rows="5"><?php echo htmlspecialchars($_POST['project_des'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="files" class="form-label">ไฟล์แนบ (รูปภาพ, PDF, Doc, XLS) (สามารถอัปโหลดได้หลายไฟล์):</label>
                            <input type="file" class="form-control" id="files" name="files[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="attendee" class="form-label">จำนวนผู้เข้าร่วม:</label>
                                <input type="number" class="form-control" id="attendee" name="attendee" min="1" value="<?php echo htmlspecialchars($_POST['attendee'] ?? '1'); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone_num" class="form-label">หมายเลขโทรศัพท์:</label>
                                <input type="text" class="form-control" id="phone_num" name="phone_num" value="<?php echo htmlspecialchars($_POST['phone_num'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <?php if ($user_role === 'นิสิต'): ?>
                        <div class="mb-3">
                            <label for="advisor_name" class="form-label">ชื่อที่ปรึกษาโครงการ:</label>
                            <input type="text" class="form-control" id="advisor_name" name="advisor_name" value="<?php echo htmlspecialchars($_POST['advisor_name'] ?? ''); ?>" required>
                        </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="activity_type_id" class="form-label">ประเภทกิจกรรม:</label>
                            <select class="form-select" id="activity_type_id" name="activity_type_id" required>
                                <option value="">-- เลือกประเภทกิจกรรม --</option>
                                <?php foreach ($activity_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type['activity_type_id']); ?>"
                                        <?php echo (isset($_POST['activity_type_id']) && $_POST['activity_type_id'] == $type['activity_type_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['activity_type_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <a href="?main_tab=user_requests&mode=projects_list" class="btn btn-secondary">ย้อนกลับ</a>
                            <div>
                                <button type="submit" name="action" value="save_draft" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                                <button type="button" id="submitCreateProject" name="action" value="submit_project" class="btn btn-success">บันทึกและส่งโครงการ</button>
                            </div>
                        </div>
                    </form>
                </div>

            <?php elseif ($mode == 'projects_detail' && $detail_item): ?>
                <div class="project-detail-card my-4">
                    <h3 class="mb-4">รายละเอียดโครงการ: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <div class="row">
                        <div class="col-md-6 pro-details">
                            <p><strong>ชื่อโครงการ:</strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                            <p><strong>สถานะโครงการ:</strong> <span class="badge <?php echo getStatusBadgeClass($detail_item['writed_status']); ?>"><?php echo htmlspecialchars($detail_item['writed_status']); ?></span></p>
                            <p><strong>ระยะเวลาโครงการ:</strong>
                                <?php if (($detail_item['start_date']) !== ($detail_item['end_date'])):?>
                                    ตั้งแต่วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['end_date'], false); ?></p>
                                <?php else: ?>
                                    วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?>
                                <?php endif; ?>
                            <p><strong>จำนวนผู้เข้าร่วม:</strong> <?php echo htmlspecialchars($detail_item['attendee']); ?></p>
                            <p><strong>หมายเลขโทรศัพท์:</strong> <?php echo htmlspecialchars($detail_item['phone_num']); ?></p>
                        </div>
                        <div class="col-md-6 pro-details">
                            <?php if (!empty($detail_item['advisor_name'])): ?>
                                <p><strong>ชื่อที่ปรึกษาโครงการ:</strong> <?php echo htmlspecialchars($detail_item['advisor_name']); ?></p>
                            <?php endif; ?>
                            <p><strong>ประเภทกิจกรรม:</strong> <?php echo htmlspecialchars($detail_item['activity_type_name'] ?? 'ไม่ระบุ'); ?></p>
                            <p><strong>ผู้เขียนโครงการ:</strong> <?php echo $user_name, ' ', $user_sur ?></p>
                            <p><strong>วันที่สร้างโครงการ: </strong><?php echo formatThaiDate($detail_item['created_date'])?>
                            <p><strong>รายละเอียดโครงการ:</strong><br> <?php echo nl2br(htmlspecialchars($detail_item['project_des'])); ?></p>
                            <?php
                                $project_files = json_decode($detail_item['files'], true) ?: [];
                            ?>
                            <?php if (!empty($project_files)): ?>
                                <p><strong>ไฟล์แนบ:</strong></p>
                                <ul>
                                    <?php foreach ($project_files as $file_path): ?>
                                        <li><a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="btn btn-secondary btn-sm mb-1">
                                                <i class="bi bi-file-earmark"></i> <?php echo htmlspecialchars(basename($file_path)); ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p><strong>ไฟล์แนบ:</strong> ไม่มี</p>
                            <?php endif; ?>
                        </div>

                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo $previous ?: '?main_tab=user_requests&mode=projects_list'; ?>"
                            class="btn btn-secondary"
                            onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                ย้อนกลับ
                        </a>
                        <div>
                            <?php
                                $can_edit = (($detail_item['writed_status'] == 'ร่างโครงการ' || $detail_item['writed_status'] == 'ส่งโครงการ') && strtotime($detail_item['start_date']) >= strtotime(date('Y-m-d', strtotime('+7 days'))) );
                                $can_delete = ($detail_item['writed_status'] == 'ร่างโครงการ');
                                $can_cancel = ($detail_item['writed_status'] == 'ส่งโครงการ');
                            ?>
                            <?php if ($can_edit): ?>
                                <a href="?main_tab=user_requests&mode=projects_edit&project_id=<?php echo $detail_item['project_id']; ?>" class="btn btn-warning me-2">
                                    <i class="bi bi-pencil-square"></i> แก้ไขข้อมูล
                                </a>
                            <?php endif; ?>

                            <?php if ($can_delete): ?>
                                <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteProjectModal"
                                        data-id="<?php echo $detail_item['project_id']; ?>"
                                        data-project-name="<?php echo htmlspecialchars($detail_item['project_name']); ?>"
                                        data-type="โครงการ">
                                    <i class="bi bi-trash"></i> ลบโครงการ
                                </button>
                            <?php endif; ?>

                            <?php if ($can_cancel):?>
                                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#cancelProjectModal"
                                        data-id="<?php echo $detail_item['project_id']; ?>"
                                        data-project-name="<?php echo htmlspecialchars($detail_item['project_name']); ?>"
                                        data-type="โครงการ">
                                    <i class="bi bi-x-circle"></i> ยกเลิกโครงการ
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form id="deleteProjectForm" action="?main_tab=user_requests" method="POST">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title" id="deleteProjectModalLabel">ยืนยันการลบโครงการ</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="details-text">คุณแน่ใจหรือไม่ว่าต้องการลบโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                        การดำเนินการนี้ไม่สามารถย้อนกลับได้ และจะลบคำร้องขอที่เกี่ยวข้องทั้งหมดด้วย.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                        <input type="hidden" name="action" id="delete_action_project" value="delete_project">
                                        <input type="hidden" name="project_id" id="delete_project_id" value="">
                                        <button type="submit" class="btn btn-danger">ลบโครงการ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="cancelProjectModal" tabindex="-1" aria-labelledby="cancelProjectModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form id="cancelProjectForm" action="?main_tab=user_requests" method="POST">
                                    <div class="modal-header bg-dark text-white">
                                        <h5 class="modal-title" id="cancelProjectModalLabel">ยืนยันการยกเลิกโครงการ</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="details-text">คุณแน่ใจหรือไม่ว่าต้องการยกเลิกโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                        การยกเลิกโครงการจะยกเลิกคำร้องขอที่เกี่ยวข้องทั้งหมดด้วย.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ไม่</button>
                                        <input type="hidden" name="action" id="cancel_action_project" value="cancel_project">
                                        <input type="hidden" name="project_id" id="cancel_project_id" value="">
                                        <button type="submit" class="btn btn-dark">ยืนยัน, ยกเลิกโครงการ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <h4 class="mb-3">คำร้องขอใช้อาคารสถานที่ทั้งหมดของโครงการนี้</h4>
                    <?php if (empty($project_facility_requests)): ?>
                        <div class="alert alert-info text-center mt-3">
                            ยังไม่มีคำร้องขอใช้อาคารสถานที่ใด ๆ ของโครงการนี้.
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-1 g-3 mt-2">
                            <?php foreach ($project_facility_requests as $request): ?>
                                <div class="col">
                                    <a href="?main_tab=user_requests&mode=buildings_detail&facility_re_id=<?php echo $request['facility_re_id']; ?>" class="text-decoration-none text-dark">
                                        <div class="card shadow-sm p-3">
                                            <div class="d-flex justify-content-between align-items-center ">
                                                <h5 class="card-title mb-1">สถานที่: <?php echo htmlspecialchars($request['facility_name']); ?></h5>
                                                <div class="text-end">
                                                    <?php if ($request['approve'] === 'อนุมัติ' || $request['approve'] === 'ไม่อนุมัติ' || $request['approve'] === 'ยกเลิก'): ?>
                                                        <h5 class="card-title mb-1">การอนุมัติคำร้อง: <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['approve']); ?></span>
                                                        </h5>
                                                    <?php endif; ?>
                                                    <p class="card-text small mb-1 text-muted">
                                                        ยื่นเมื่อ: <?php echo formatThaiDate($request['request_date']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <p class="card-text small mb-1"><strong> สถานะ: </strong>
                                                <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['writed_status']); ?></span>
                                            </p>
                                            <p class="card-text small mb-1">
                                                <?php if ($request['start_date'] !== $request['end_date']) : ?>
                                                    <strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> (<?php echo (new DateTime($request['start_time']))->format('H:i'); ?>) ถึง วันที่ <?php echo formatThaiDate($request['end_date'], false); ?> (<?php echo (new DateTime($request['end_time']))->format('H:i'); ?>)
                                                <?php else: ?>
                                                    <strong>ช่วงเวลาใช้งาน:</strong> วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> (<?php echo (new DateTime($request['start_time']))->format('H:i'); ?> - <?php echo (new DateTime($request['end_time']))->format('H:i'); ?>)
                                                <?php endif; ?>
                                            </p>
                                            <?php if (!empty($request['prepare_start_date'])): ?>
                                                <p class="card-text small mb-1 pro-details">
                                                <?php if ($request['prepare_start_date'] != $request['prepare_end_date']) : ?>
                                                    <strong>ช่วงเวลาเตรียมการ:</strong> ตั้งแต่วันที่ <?php echo formatThaiDate($request['prepare_start_date'], false); ?> (<?php echo (new DateTime($request['prepare_start_time']))->format('H:i'); ?>) ถึง วันที่ <?php echo formatThaiDate($request['prepare_end_date'], false); ?> (<?php echo (new DateTime($request['prepare_end_time']))->format('H:i'); ?>)
                                                <?php else: ?>
                                                    <strong>ช่วงเวลาเตรียมการ:</strong> วันที่ <?php echo formatThaiDate($request['prepare_start_date'], false); ?> (<?php echo (new DateTime($request['prepare_start_time']))->format('H:i'); ?> - <?php echo (new DateTime($request['prepare_end_time']))->format('H:i'); ?>)
                                                <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <hr class="my-4">

                    <h4 class="mb-3">คำร้องขอใช้อุปกรณ์ทั้งหมดของโครงการนี้</h4>
                    <?php if (empty($project_equipment_requests)): ?>
                        <div class="alert alert-info text-center mt-3">
                            ยังไม่มีคำร้องขอใช้อุปกรณ์ใด ๆ ของโครงการนี้.
                        </div>
                    <?php else: ?>
                        <div class="row row-cols-1 g-3 mt-2">
                            <?php foreach ($project_equipment_requests as $request): ?>
                                <div class="col">
                                    <a href="?main_tab=user_requests&mode=equipments_detail&equip_re_id=<?php echo $request['equip_re_id']; ?>" class="text-decoration-none text-dark">
                                        <div class="card shadow-sm p-3">
                                            <div class="d-flex w-100 justify-content-between align-items-center">
                                                <h5 class="card-title mb-1">อุปกรณ์: <?php echo htmlspecialchars($request['equip_name']); ?></h5>
                                                <div class="text-end">
                                                    <?php if ($request['approve'] === 'อนุมัติ' || $request['approve'] === 'ไม่อนุมัติ' || $request['approve'] === 'ยกเลิก'): ?>
                                                        <h5 class="card-title mb-1">การอนุมัติคำร้อง: <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['approve']); ?></span>
                                                        </h5>
                                                    <?php endif; ?>
                                                    <p class="card-text small mb-1 text-muted">
                                                        ยื่นเมื่อ: <?php echo formatThaiDate($request['request_date']) ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <p class="card-text small mb-1">
                                                <strong>จำนวน:</strong> <?php echo htmlspecialchars($request['quantity']); ?> <?php echo htmlspecialchars($request['measure']); ?>
                                            </p>
                                            <p class="card-text small mb-1">
                                                <strong>สถานะ:</strong>
                                                <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['writed_status']); ?></span>
                                            </p>
                                            <p class="card-text small mb-1">
                                                <strong>ช่วงเวลาใช้งาน:</strong>
                                                <?php if ($request['start_date'] !== $request['end_date']) : ?>
                                                    ตั้งแต่วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($request['end_date'], false); ?>
                                                <?php else: ?>
                                                    วันที่ <?php echo formatThaiDate($request['start_date'], false); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            <?php elseif ($mode == 'projects_edit' && $detail_item): ?>
                <div class="form-section my-4">
                    <h2 class="mb-4 text-center">แก้ไขโครงการ: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <form id="editProjectForm" action="?main_tab=user_requests&mode=projects_edit" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="project_id" value="<?php echo htmlspecialchars($detail_item['project_id']); ?>">
                        <input type="hidden" name="existing_file_paths_json_from_db" value="<?php echo htmlspecialchars($detail_item['files'] ?? '[]'); ?>">

                            <div class="mb-3">
                                <label for="project_name" class="form-label">ชื่อโครงการ:</label>
                                <input type="text" class="form-control" id="project_name" name="project_name" value="<?php echo htmlspecialchars($_POST['project_name'] ?? $detail_item['project_name']); ?>" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">วันเริ่มต้น:</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? $detail_item['start_date']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">วันสิ้นสุด:</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? $detail_item['end_date']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="project_des" class="form-label">รายละเอียดของโครงการ:</label>
                                <textarea class="form-control" id="project_des" name="project_des" rows="5"><?php echo htmlspecialchars($_POST['project_des'] ?? $detail_item['project_des']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ไฟล์แนบปัจจุบัน:</label>
                                <div id="existing-files-list">
                                    <?php
                                    $existing_files_for_form = json_decode($detail_item['files'], true) ?: [];
                                    if (!empty($existing_files_for_form)): ?>
                                        <?php foreach ($existing_files_for_form as $idx => $filePath): ?>
                                            <div class="input-group mb-2 existing-file-item">
                                                <a href="<?php echo htmlspecialchars($filePath); ?>" target="_blank" class="form-control text-truncate">
                                                    <?php echo htmlspecialchars(basename($filePath)); ?>
                                                </a>
                                                <input type="hidden" name="existing_file_paths_retained[]" value="<?php echo htmlspecialchars($filePath); ?>">
                                                <button type="button" class="btn btn-outline-danger remove-existing-file" data-file-path="<?php echo htmlspecialchars($filePath); ?>">ลบ</button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted small">ไม่มีไฟล์เดิม</p>
                                    <?php endif; ?>
                                </div>
                                <label for="files" class="form-label mt-3">อัปโหลดไฟล์ใหม่ (เพิ่มเติม):</label>
                                <input type="file" class="form-control" id="files" name="files[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="attendee" class="form-label">จำนวนผู้เข้าร่วม:</label>
                                    <input type="number" class="form-control" id="attendee" name="attendee" min="1" value="<?php echo htmlspecialchars($_POST['attendee'] ?? $detail_item['attendee']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="phone_num" class="form-label">หมายเลขโทรศัพท์:</label>
                                    <input type="text" class="form-control" id="phone_num" name="phone_num" value="<?php echo htmlspecialchars($_POST['phone_num'] ?? $detail_item['phone_num']); ?>" required>
                                </div>
                            </div>
                            <?php if ($user_role === 'นิสิต'): ?>
                            <div class="mb-3">
                                <label for="advisor_name" class="form-label">ชื่อที่ปรึกษาโครงการ:</label>
                                <input type="text" class="form-control" id="advisor_name" name="advisor_name" value="<?php echo htmlspecialchars($_POST['advisor_name'] ?? $detail_item['advisor_name']); ?>" required>
                            </div>
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="activity_type_id" class="form-label">ประเภทกิจกรรม:</label>
                                <select class="form-select" id="activity_type_id" name="activity_type_id" required>
                                    <option value="">-- เลือกประเภทกิจกรรม --</option>
                                    <?php foreach ($activity_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['activity_type_id']); ?>"
                                            <?php echo (isset($_POST['activity_type_id']) && $_POST['activity_type_id'] == $type['activity_type_id']) ? 'selected' : ''; ?>
                                            <?php echo (!isset($_POST['activity_type_id']) && $detail_item['activity_type_id'] == $type['activity_type_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['activity_type_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="d-flex justify-content-between mt-4">
                                <a href="?main_tab=user_requests&mode=projects_detail&project_id=<?php echo $detail_item['project_id']; ?>" class="btn btn-secondary">ย้อนกลับ</a>
                                <div>
                                    <?php if ($detail_item['writed_status'] == 'ร่างโครงการ') : ?>
                                        <button type="submit" name="action" value="save_draft_edit" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                                    <?php endif; ?>
                                    <button type="button" id="submitEditProject" name="action" value="submit_project_edit" class="btn btn-success">บันทึกและส่งโครงการ</button>
                                </div>
                            </div>
                        </form>
                    </div>

            <?php elseif ($mode == 'buildings_list' || $mode == 'equipments_list'): ?>
                <div class="row mb-3">
                    <div class="col">
                        <div class="card shadow-sm p-3">
                            <h5 class="card-title mb-3">คำร้องขอใช้อาคารสถานที่ทั้งหมดของคุณ:
                                <?php if ($mode == 'buildings_list') : ?> <?php echo $dashboard_data['facilities_request_counts']['total']; ?> คำร้อง</h5>
                                <?php elseif ($mode == 'equipments_list') : ?> <?php echo $dashboard_data['equipments_request_counts']['total']; ?> คำร้อง</h5>
                                <?php endif; ?>
                            <div class="d-flex flex-wrap gap-2">
                                <h6>
                                    <?php if ($mode == 'buildings_list') : ?>
                                        <span class="badge bg-success">อนุมัติ: <?php echo $dashboard_data['facilities_request_counts']['approved']; ?> </span>
                                        <span class="badge bg-danger">ไม่อนุมัติ: <?php echo $dashboard_data['facilities_request_counts']['rejected']; ?> </span>
                                        <div class="mt-2">
                                            <span class="badge bg-warning text-dark">ร่างคำร้องขอ: <?php echo $dashboard_data['facilities_request_counts']['draft']; ?> </span>
                                            <span class="badge bg-primary">ส่งคำร้องขอ: <?php echo $dashboard_data['facilities_request_counts']['submitted']; ?> </span>
                                            <span class="badge bg-info text-dark">เริ่มดำเนินการ: <?php echo $dashboard_data['facilities_request_counts']['in_progress']; ?> </span>
                                            <span class="badge bg-secondary">สิ้นสุดดำเนินการ: <?php echo $dashboard_data['facilities_request_counts']['completed']; ?> </span>
                                            <span class="badge bg-dark">ยกเลิกคำร้องขอ: <?php echo $dashboard_data['facilities_request_counts']['cancelled']; ?> </span>
                                        </div>
                                    <?php elseif ($mode == 'equipments_list') : ?>
                                        <span class="badge bg-success">อนุมัติ: <?php echo $dashboard_data['equipments_request_counts']['approved']; ?> </span>
                                        <span class="badge bg-danger">ไม่อนุมัติ: <?php echo $dashboard_data['equipments_request_counts']['rejected']; ?> </span>
                                        <div class="mt-2">
                                            <span class="badge bg-warning text-dark">ร่างคำร้องขอ: <?php echo $dashboard_data['equipments_request_counts']['draft']; ?> </span>
                                            <span class="badge bg-primary">ส่งคำร้องขอ: <?php echo $dashboard_data['equipments_request_counts']['submitted']; ?> </span>
                                            <span class="badge bg-info text-dark">เริ่มดำเนินการ: <?php echo $dashboard_data['equipments_request_counts']['in_progress']; ?> </span>
                                            <span class="badge bg-secondary">สิ้นสุดดำเนินการ: <?php echo $dashboard_data['equipments_request_counts']['completed']; ?> </span>
                                            <span class="badge bg-dark">ยกเลิกคำร้องขอ: <?php echo $dashboard_data['equipments_request_counts']['cancelled']; ?> </span>
                                        </div>
                                    <?php endif; ?>
                                </h6>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="d-flex justify-content-end mb-3">
                            <?php if ($mode == 'buildings_list'): ?>
                                <a href="?main_tab=user_requests&mode=buildings_create" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> สร้างคำร้องขอใช้อาคารสถานที่
                                </a>
                            <?php elseif ($mode == 'equipments_list'): ?>
                                <a href="?main_tab=user_requests&mode=equipments_create" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> สร้างคำร้องขอใช้อุปกรณ์
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php if (empty($data)): ?>
                    <div class="alert alert-info text-center mt-4">
                        <?php if (!empty($search_query)): ?>
                            ไม่พบโครงการที่มีคำร้องขอใช้<?php echo ($mode == 'buildings_list' ? 'สถานที่' : 'อุปกรณ์'); ?>ที่คุณค้นหา "<?php echo htmlspecialchars($search_query); ?>"
                        <?php else: ?>
                            ยังไม่มีโครงการของคุณที่มีคำร้องขอใช้<?php echo ($mode == 'buildings_list' ? 'สถานที่' : 'อุปกรณ์'); ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 g-3">
                        <?php foreach ($data as $project): ?>
                            <div class="col">
                                <div class="card shadow-sm p-3 mb-4">
                                    <h4 class="card-title mb-2">โครงการ: <?php echo htmlspecialchars($project['project_name']); ?>
                                        <small class="text-muted">(สถานะ: <span class="badge <?php echo getStatusBadgeClass($project['writed_status']); ?>"><?php echo htmlspecialchars($project['writed_status']); ?></span>)</small>
                                    </h4>
                                    <p class="card-text small mb-2 text-muted">
                                        ยื่นเมื่อ: <?php echo formatThaiDate($project['created_date']); ?>
                                    </p>
                                    <hr>
                                    <h5 class="mb-3">คำร้องขอใช้<?php echo ($mode == 'buildings_list' ? 'อาคารสถานที่' : 'อุปกรณ์'); ?>ทั้งหมดของโครงการนี้:</h5>
                                    <?php if (empty($project['requests'])): ?>
                                        <div class="alert alert-warning text-center small py-2">
                                            ยังไม่มีคำร้องขอใช้<?php echo ($mode == 'buildings_list' ? 'อาคารสถานที่' : 'อุปกรณ์'); ?>ใด ๆ ของโครงการนี้
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($project['requests'] as $request): ?>
                                                <a href="?main_tab=user_requests&mode=<?php echo ($mode == 'buildings_list' ? 'buildings_detail' : 'equipments_detail'); ?>&<?php echo ($mode == 'buildings_list' ? 'facility_re_id' : 'equip_re_id'); ?>=<?php echo ($mode == 'buildings_list' ? $request['facility_re_id'] : $request['equip_re_id']); ?>" class="list-group-item list-group-item-action mb-2 rounded-3">
                                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                                        <h6 class="mb-1">
                                                            <?php if ($mode == 'buildings_list'): ?>
                                                                สถานที่: <?php echo htmlspecialchars($request['facility_name']); ?>
                                                            <?php elseif ($mode == 'equipments_list'): ?>
                                                                อุปกรณ์: <?php echo htmlspecialchars($request['equip_name']); ?> จำนวน <?php echo htmlspecialchars($request['quantity']); ?> <?php echo htmlspecialchars($request['measure']); ?>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <div class="text-end">
                                                            <?php if ($request['approve'] === 'อนุมัติ' || $request['approve'] === 'ไม่อนุมัติ' || $request['approve'] === 'ยกเลิก'): ?>
                                                                <h6 class="card-title">การอนุมัติคำร้อง: <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['approve']); ?></span>
                                                                </h6>
                                                            <?php endif; ?>
                                                            <p class="text-muted">ยื่นเมื่อ: <?php echo formatThaiDate($request['request_date']); ?></p>
                                                        </div>
                                                    </div>
                                                    <p class="card-text small mb-1"><strong>สถานะ: </strong>
                                                        <span class="badge <?php echo getStatusBadgeClass($request['writed_status'], $request['approve']); ?>"><?php echo htmlspecialchars($request['writed_status']); ?></span>
                                                    </p>
                                                    <?php if ($mode == 'buildings_list'): ?>
                                                        <p class="card-text small mb-1">
                                                            <?php if ($request['start_date'] !== $request['end_date']) : ?>
                                                                <strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> (<?php echo (new DateTime($request['start_time']))->format('H:i'); ?>) ถึง วันที่ <?php echo formatThaiDate($request['end_date'], false); ?> (<?php echo (new DateTime($request['end_time']))->format('H:i'); ?>)
                                                            <?php else: ?>
                                                                <strong>ช่วงเวลาใช้งาน:</strong> วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> (<?php echo (new DateTime($request['start_time']))->format('H:i'); ?> - <?php echo (new DateTime($request['end_time']))->format('H:i'); ?>)
                                                            <?php endif; ?>
                                                        </p>
                                                        <?php if (!empty($request['prepare_start_date'])): ?>
                                                            <p class="card-text small mb-1 pro-details">
                                                            <?php if ($request['prepare_start_date'] != $request['prepare_end_date']) : ?>
                                                                <strong>ช่วงเวลาเตรียมการ:</strong> ตั้งแต่วันที่ <?php echo formatThaiDate($request['prepare_start_date'], false); ?> (<?php echo (new DateTime($request['prepare_start_time']))->format('H:i'); ?>) ถึง วันที่ <?php echo formatThaiDate($request['prepare_end_date'], false); ?> (<?php echo (new DateTime($request['prepare_end_time']))->format('H:i'); ?>)
                                                            <?php else: ?>
                                                                <strong>ช่วงเวลาเตรียมการ:</strong> วันที่ <?php echo formatThaiDate($request['prepare_start_date'], false); ?> (<?php echo (new DateTime($request['prepare_start_time']))->format('H:i'); ?> - <?php echo (new DateTime($request['prepare_end_time']))->format('H:i'); ?>)
                                                            <?php endif; ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    <?php elseif ($mode == 'equipments_list'): ?>
                                                        <p class="card-text small mb-1">
                                                            <strong>ช่วงเวลาใช้งาน:</strong>
                                                            <?php if ($request['start_date'] !== $request['end_date']) : ?>
                                                                ตั้งแต่วันที่ <?php echo formatThaiDate($request['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($request['end_date'], false); ?>
                                                            <?php else: ?>
                                                                วันที่ <?php echo formatThaiDate($request['start_date'], false); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <nav aria-label="Page navigation" class="pagination-container mt-3 mb-0">
                        <ul class="pagination pagination-lg">
                            <?php

                            $search_param_url = !empty($search_query) ? '&search=' . urlencode($search_query) : '';
                            $sort_param_url = !empty($_GET['sort_filter']) ? '&sort_filter=' . urlencode($_GET['sort_filter']) : '';
                            ?>

                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page - 1; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>">ก่อนหน้า</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $i; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page + 1; ?><?php echo $search_param_url; ?><?php echo $sort_param_url; ?>">ถัดไป</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($mode == 'buildings_create'):?>
                <div class="form-section my-4">
                    <h2 class="mb-4 text-center">สร้างคำร้องขอใช้อาคารสถานที่</h2>
                    <form id="createBuildingForm" action="?main_tab=user_requests&mode=buildings_create" method="POST">
                        <div class="mb-3">
                            <label for="project_id" class="form-label">โครงการ:</label>
                            <select class="form-select" id="project_id" name="project_id" required>
                                <option value="">-- เลือกโครงการ --</option>
                                <?php foreach ($user_projects as $project): ?>
                                    <option value="<?php echo htmlspecialchars($project['project_id']); ?>"
                                        <?php echo (isset($_POST['project_id']) && $_POST['project_id'] == $project['project_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($user_projects)): ?>
                                <p class="text-danger mt-2">คุณยังไม่มีโครงการที่สร้างไว้ กรุณาสร้างโครงการก่อน.</p>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="building_id" class="form-label">อาคารที่ต้องการขอใช้:</label>
                            <select class="form-select" id="building_id" name="building_id" required>
                                <option value="">-- เลือกอาคาร --</option>
                                <?php foreach ($buildings as $building):
                                    $is_disabled = ($building['available'] == 'no') ? 'disabled' : '';
                                    $status_text = ($building['available'] == 'no') ? ' (ไม่พร้อมใช้งาน)' : '';
                                    $is_selected = '';
                                    if (isset($_POST['building_id']) && $_POST['building_id'] == $building['building_id']) {
                                        $is_selected = 'selected';
                                    }
                                    ?>
                                    <option value="<?php echo htmlspecialchars($building['building_id']); ?>"
                                        <?php echo $is_selected; ?> <?php echo $is_disabled; ?>>
                                        <?php echo htmlspecialchars($building['building_id'] . ' ' . $building['building_name']) . $status_text; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($buildings)): ?>
                                <p class="text-danger mt-2">ยังไม่มีข้อมูลอาคารในระบบ</p>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="facility_id" class="form-label">สถานที่ที่ต้องการขอใช้:</label>
                            <select class="form-select" id="facility_id" name="facility_id" required disabled
                                data-initial-facility-id-from-post="<?php echo htmlspecialchars($_POST['facility_id'] ?? ''); ?>">
                                <option value="">-- เลือกอาคารก่อน --</option>
                            </select>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="prepare_start_date" class="form-label">วันเริ่มต้นเตรียมการ:</label>
                                <input type="date" class="form-control" id="prepare_start_date" name="prepare_start_date" value="<?php echo htmlspecialchars($_POST['prepare_start_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="prepare_end_date" class="form-label">วันสิ้นสุดเตรียมการ:</label>
                                <input type="date" class="form-control" id="prepare_end_date" name="prepare_end_date" value="<?php echo htmlspecialchars($_POST['prepare_end_date'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="prepare_start_time" class="form-label">เวลาเริ่มต้นเตรียมการ:</label>
                                <input type="time" class="form-control" id="prepare_start_time" name="prepare_start_time" value="<?php echo htmlspecialchars($_POST['prepare_start_time'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="prepare_end_time" class="form-label">เวลาสิ้นสุดเตรียมการ:</label>
                                <input type="time" class="form-control" id="prepare_end_time" name="prepare_end_time" value="<?php echo htmlspecialchars($_POST['prepare_end_time'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">วันเริ่มต้นใช้การ:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">วันสิ้นสุดใช้การ:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_time" class="form-label">เวลาเริ่มต้นใช้การ:</label>
                                <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_time" class="form-label">เวลาสิ้นสุดใช้การ:</label>
                                <input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="agree" name="agree" value="1" <?php echo (isset($_POST['agree']) && $_POST['agree'] == 1) ? 'checked' : ''; ?> >
                            <label class="form-check-label" for="agree">ข้าพเจ้ายินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ </label>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="?main_tab=user_requests&mode=buildings_list" class="btn btn-secondary">ย้อนกลับ</a>
                            <div>
                                <button type="submit" name="action" value="save_draft_building" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                                <button type="button" id="submitCreateBuilding" name="action" value="submit_building" class="btn btn-success">บันทึกและส่งคำร้อง</button>
                            </div>
                        </div>
                    </form>
                </div>

            <?php elseif ($mode == 'buildings_detail' && $detail_item): ?>
                <div class="project-detail-card my-4">
                    <h3 class="mb-4">รายละเอียดคำร้องขอสถานที่: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <div class="row">
                        <div class="col-md-6 pro-details">
                            <p><strong>ชื่อโครงการ: </strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                            <p><strong>สถานะคำร้อง: </strong> <span class="badge <?php echo getStatusBadgeClass($detail_item['writed_status'], $detail_item['approve']); ?>"><?php echo htmlspecialchars($detail_item['writed_status']); ?></span></p>
                            <p><strong>สถานที่ที่ขอใช้งาน: </strong> <?php echo htmlspecialchars($detail_item['facility_name']); ?></p>

                            <?php if (($detail_item['prepare_start_date']) !== ($detail_item['prepare_end_date'])) : ?>
                                <p><strong>วันเริ่มต้นการเตรียมการ: </strong> <?php echo formatThaiDate($detail_item['prepare_start_date'], false)?> ถึง วันที่ <?php echo formatThaiDate($detail_item['prepare_end_date'], false); ?></p>
                            <?php else: ?>
                                <p><strong>วันที่เตรียมการ: </strong> <?php echo formatThaiDate($detail_item['prepare_start_date'], false); ?></p>
                            <?php endif; ?>

                            <p><strong>ตั้งแต่เวลา: </strong>
                            <?php if($detail_item['prepare_start_time'] !== $detail_item['prepare_end_time']): ?>
                                <?php echo (new DateTime($detail_item['prepare_start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['prepare_end_time']))->format('H:i'); ?></p>
                            <?php else: ?>
                                <?php echo (new DateTime($detail_item['prepare_start_time']))->format('H:i'); ?> น.
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6 pro-details">
                            <p><strong>วันที่สร้างคำร้อง: </strong>
                                <?php echo formatThaiDate($detail_item['request_date']);?>
                            </p>

                            <p><strong>ผู้เขียนคำร้อง: </strong> <?php echo htmlspecialchars($detail_item['user_name']); ?></p>
                            <?php if (($detail_item['start_date']) !== ($detail_item['end_date'])):?>
                                <p><strong>วันเริ่มต้นการใช้งาน: </strong> <?php echo formatThaiDate($detail_item['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['end_date'], false); ?></p>
                            <?php else: ?>
                                <p><strong>วันที่ใช้งาน: </strong> <?php echo formatThaiDate($detail_item['start_date'], false); ?></p>
                            <?php endif; ?>

                            <p><strong>ตั้งแต่เวลา: </strong>
                            <?php if ($detail_item['start_time'] !== $detail_item['end_time']) : ?>
                                <?php echo (new DateTime($detail_item['start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['end_time']))->format('H:i'); ?></p>
                            <?php else: ?>
                                <?php echo (new DateTime($detail_item['start_time']))->format('H:i'); ?> น.
                            <?php endif; ?>

                            <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                            <?php if ($detail_item['approve'] != '' && $detail_item['approve'] != 'ยกเลิก'): ?>
                                <p><strong>การอนุมัติคำร้อง: </strong> <span class="badge <?php echo getStatusBadgeClass($detail_item['writed_status'], $detail_item['approve']); ?>"><?php echo htmlspecialchars($detail_item['approve']); ?></span></p>
                                <?php if ($detail_item['approve'] == 'อนุมัติ'): ?>
                                    <p><strong>วันที่อนุมัติ: </strong> <?php echo formatThaiDate($detail_item['approve_date'], false); ?></p>
                                <?php elseif ($detail_item['approve'] == 'ไม่อนุมัติ' || $detail_item['approve'] == 'ยกเลิก'): ?>
                                    <p><strong>วันที่ดำเนินการ: </strong> <?php echo formatThaiDate($detail_item['approve_date'], false); ?></p>
                                <?php endif; ?>
                                <?php if ($detail_item['approve'] == 'ไม่อนุมัติ' || $detail_item['approve'] == 'ยกเลิก'): ?>
                                    <p><strong>รายละเอียดการ<?php echo htmlspecialchars($detail_item['approve']); ?>:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                <?php elseif ($detail_item['approve'] == 'อนุมัติ' && !empty($detail_item['approve_detail'])): ?>
                                    <p><strong>รายละเอียดการอนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="<?php echo $previous ?: '?main_tab=user_requests&mode=buildings_list'; ?>"
                            class="btn btn-secondary"
                            onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                ย้อนกลับ
                        </a>
                        <div>
                            <?php
                                $can_edit = (($detail_item['writed_status'] == 'ร่างคำร้องขอ' || $detail_item['writed_status'] == 'ส่งคำร้องขอ') && (strtotime($detail_item['start_date']) >= strtotime(date('Y-m-d', strtotime('+3 days'))) && ($detail_item['approve'] === null || $detail_item['approve'] === '')));
                                $can_delete = ($detail_item['writed_status'] == 'ร่างคำร้องขอ');
                                $can_cancel = ($detail_item['writed_status'] == 'ส่งคำร้องขอ');
                            ?>
                            <?php if ($can_edit): ?>
                                <a href="?main_tab=user_requests&mode=buildings_edit&facility_re_id=<?php echo $detail_item['facility_re_id']; ?>" class="btn btn-warning me-2">
                                    <i class="bi bi-pencil-square"></i> แก้ไขคำร้องขอ
                                </a>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                                <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteBuildingRequestModal"
                                        data-id="<?php echo $detail_item['facility_re_id']; ?>"
                                        data-facility-name="<?php echo htmlspecialchars($detail_item['facility_name']); ?>"
                                        data-type="คำร้องขอสถานที่">
                                    <i class="bi bi-trash"></i> ลบคำร้องขอ
                                </button>
                            <?php endif; ?>
                            <?php if ($can_cancel): ?>
                                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#cancelBuildingRequestModal"
                                        data-id="<?php echo $detail_item['facility_re_id']; ?>"
                                        data-facility-name="<?php echo htmlspecialchars($detail_item['facility_name']); ?>"
                                        data-type="คำร้องขอสถานที่">
                                    <i class="bi bi-x-circle"></i> ยกเลิกคำร้องขอ
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="modal fade" id="deleteBuildingRequestModal" tabindex="-1" aria-labelledby="deleteBuildingRequestModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form id="deleteBuildingRequestForm" action="?main_tab=user_requests" method="POST">
                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title" id="deleteBuildingRequestModalLabel">ยืนยันการลบคำร้องขอสถานที่</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="details-text">คุณแน่ใจหรือไม่ว่าต้องการลบคำร้องขอใช้สถานที่ "<strong><?php echo htmlspecialchars($detail_item['facility_name']); ?></strong>" ของโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                        การดำเนินการนี้ไม่สามารถย้อนกลับได้.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                        <input type="hidden" name="action" id="delete_action_fr" value="delete_building_request">
                                        <input type="hidden" name="facility_re_id" id="delete_fr_id" value="">
                                        <button type="submit" class="btn btn-danger">ยืนยันการลบ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal fade" id="cancelBuildingRequestModal" tabindex="-1" aria-labelledby="cancelBuildingRequestModalLabel" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <form id="cancelBuildingRequestForm" action="?main_tab=user_requests" method="POST">
                                    <div class="modal-header bg-dark text-white">
                                        <h5 class="modal-title" id="cancelBuildingRequestModalLabel">ยืนยันการยกเลิกคำร้องขอสถานที่</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="details-text">คุณแน่ใจหรือไม่ว่าต้องการยกเลิกคำร้องขอใช้สถานที่ "<strong><?php echo htmlspecialchars($detail_item['facility_name']); ?></strong>" ของโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                        การดำเนินการนี้จะเปลี่ยนสถานะคำร้องเป็น "ยกเลิกคำร้องขอ".</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ไม่</button>
                                        <input type="hidden" name="action" id="cancel_action_fr" value="cancel_building_request">
                                        <input type="hidden" name="facility_re_id" id="cancel_fr_id" value="">
                                        <button type="submit" class="btn btn-dark">ใช่, ยกเลิกคำร้องขอ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($mode == 'buildings_edit' && $detail_item): ?>
                    <div class="form-section my-4">
                        <h2 class="mb-4 text-center">แก้ไขคำร้องขอใช้อาคารสถานที่: <?php echo htmlspecialchars($detail_item['facility_name']); ?></h2>
                        <form id="editBuildingForm" action="?main_tab=user_requests&mode=buildings_edit" method="POST"
                            data-project-start-date="<?php echo htmlspecialchars($detail_item['project_start_date'] ?? ''); ?>"
                            data-project-end-date="<?php echo htmlspecialchars($detail_item['project_end_date'] ?? ''); ?>">
                            <input type="hidden" name="facility_re_id" value="<?php echo htmlspecialchars($detail_item['facility_re_id']); ?>">
                            <div class="mb-3">
                                <label for="project_id" class="form-label">โครงการ:</label>
                                <select class="form-select" id="project_id" name="project_id" required>
                                    <option value="">-- เลือกโครงการ --</option>
                                    <?php foreach ($user_projects as $project): ?>
                                        <option value="<?php echo htmlspecialchars($project['project_id']); ?>"
                                            <?php
                                            $selected_project = $_POST['project_id'] ?? ($detail_item['project_id'] ?? null);
                                            if ($project['project_id'] == $selected_project) { echo 'selected'; }
                                            ?>>
                                            <?php echo htmlspecialchars($project['project_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($user_projects)): ?>
                                    <p class="text-danger mt-2">คุณยังไม่มีโครงการที่สร้างไว้ กรุณาสร้างโครงการก่อน.</p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="building_id" class="form-label">อาคารที่ต้องการขอใช้:</label>
                                <select class="form-select" id="building_id" name="building_id" required
                                    data-initial-building-id="<?php echo htmlspecialchars($detail_item['building_id'] ?? ''); ?>">
                                    <option value="">-- เลือกอาคาร --</option>
                                    <?php foreach ($buildings as $building):
                                        $is_disabled = ($building['available'] == 'no') ? 'disabled' : '';
                                        $status_text = ($building['available'] == 'no') ? ' (ไม่พร้อมใช้งาน)' : '';
                                        $is_selected = '';
                                        if ((isset($_POST['building_id']) && $_POST['building_id'] == $building['building_id']) ||
                                            (!isset($_POST['building_id']) && isset($detail_item['building_id']) && $detail_item['building_id'] == $building['building_id'])) {
                                            $is_selected = 'selected';
                                        }
                                        ?>
                                        <option value="<?php echo htmlspecialchars($building['building_id']); ?>"
                                            <?php echo $is_selected; ?> <?php echo $is_disabled; ?>>
                                            <?php echo htmlspecialchars($building['building_id'] . ' ' . $building['building_name']) . $status_text; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($buildings)): ?>
                                    <p class="text-danger mt-2">ยังไม่มีข้อมูลอาคารในระบบ</p>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="facility_id" class="form-label">สถานที่ที่ต้องการขอใช้:</label>
                                <select class="form-select" id="facility_id" name="facility_id" required disabled
                                    data-initial-facility-id="<?php echo htmlspecialchars($detail_item['facility_id'] ?? ''); ?>">
                                    <option value="">-- เลือกอาคารก่อน --</option>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="prepare_start_date" class="form-label">วันเริ่มต้นเตรียมการ:</label>
                                    <input type="date" class="form-control" id="prepare_start_date" name="prepare_start_date" value="<?php echo htmlspecialchars($_POST['prepare_start_date'] ?? $detail_item['prepare_start_date']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="prepare_end_date" class="form-label">วันสิ้นสุดเตรียมการ:</label>
                                    <input type="date" class="form-control" id="prepare_end_date" name="prepare_end_date" value="<?php echo htmlspecialchars($_POST['prepare_end_date'] ?? $detail_item['prepare_end_date']); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="prepare_start_time" class="form-label">เวลาเริ่มต้นเตรียมการ:</label>
                                    <input type="time" class="form-control" id="prepare_start_time" name="prepare_start_time" value="<?php echo htmlspecialchars($_POST['prepare_start_time'] ?? $detail_item['prepare_start_time']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="prepare_end_time" class="form-label">เวลาสิ้นสุดเตรียมการ:</label>
                                    <input type="time" class="form-control" id="prepare_end_time" name="prepare_end_time" value="<?php echo htmlspecialchars($_POST['prepare_end_time'] ?? $detail_item['prepare_end_time']); ?>">
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_date" class="form-label">วันเริ่มต้นใช้การ:</label>
                                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? $detail_item['start_date']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_date" class="form-label">วันสิ้นสุดใช้การ:</label>
                                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? $detail_item['end_date']); ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="start_time" class="form-label">เวลาเริ่มต้นใช้การ:</label>
                                    <input type="time" class="form-control" id="start_time" name="start_time" value="<?php echo htmlspecialchars($_POST['start_time'] ?? $detail_item['start_time']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="end_time" class="form-label">เวลาสิ้นสุดใช้การ:</label>
                                    <input type="time" class="form-control" id="end_time" name="end_time" value="<?php echo htmlspecialchars($_POST['end_time'] ?? $detail_item['end_time']); ?>">
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="agree" name="agree" value="1" <?php echo (isset($_POST['agree']) && $_POST['agree'] == 1) ? 'checked' : ''; ?> <?php echo (!isset($_POST['agree']) && $detail_item['agree'] == 1) ? 'checked' : ''; ?> >
                                <label class="form-check-label" for="agree">ข้าพเจ้ายินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ </label>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="?main_tab=user_requests&mode=buildings_detail&facility_re_id=<?php echo $detail_item['facility_re_id']; ?>" class="btn btn-secondary">ย้อนกลับ</a>
                                <div>
                                    <?php if ($detail_item['writed_status'] == 'ร่างคำร้องขอ') : ?>
                                        <button type="submit" name="action" value="save_draft_building_edit" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                                    <?php endif; ?>
                                    <button type="button" id="submitEditBuilding" name="action" value="submit_building_edit" class="btn btn-success">บันทึกและส่งคำร้อง</button>
                                </div>
                            </div>
                        </form>
                    </div>

            <?php elseif ($mode == 'equipments_create'): ?>
                <div class="form-section my-4">
                    <h2 class="mb-4 text-center">สร้างคำร้องขอใช้อุปกรณ์</h2>
                    <form id="createEquipmentForm" action="?main_tab=user_requests&mode=equipments_create" method="POST">
                        <div class="mb-3">
                            <label for="project_id" class="form-label">โครงการ:</label>
                            <select class="form-select" id="project_id" name="project_id" required>
                                <option value="">-- เลือกโครงการ --</option>
                                <?php foreach ($user_projects as $project): ?>
                                    <option value="<?php echo htmlspecialchars($project['project_id']); ?>"
                                        <?php echo (isset($_POST['project_id']) && $_POST['project_id'] == $project['project_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['project_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($user_projects)): ?>
                                <p class="text-danger mt-2">คุณยังไม่มีโครงการที่สร้างไว้ กรุณาสร้างโครงการก่อน.</p>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="equip_id" class="form-label">อุปกรณ์ที่ต้องการขอใช้:</label>
                            <select class="form-select" id="equip_id" name="equip_id" required>
                                <option value="">-- เลือกอุปกรณ์ --</option>
                                <?php foreach ($equipments as $equip):
                                    $is_disabled = ($equip['available'] == 'no') ? 'disabled' : '';
                                    $status_text = ($equip['available'] == 'no') ? ' (ไม่พร้อมใช้งาน)' : '';
                                    $is_selected = '';
                                    if (isset($_POST['equip_id']) && $_POST['equip_id'] == $equip['equip_id']) {
                                        $is_selected = 'selected';
                                    }
                                    ?>
                                    <option value="<?php echo htmlspecialchars($equip['equip_id']); ?>"
                                        <?php echo $is_selected; ?> <?php echo $is_disabled; ?>>
                                        <?php echo htmlspecialchars($equip['equip_name']) . " (" . htmlspecialchars($equip['measure']) . ")" . $status_text; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($equipments)): ?>
                                <p class="text-danger mt-2">ยังไม่มีข้อมูลอุปกรณ์ในระบบ</p>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label for="facility_id" class="form-label">สถานที่/อาคารที่อุปกรณ์นำไปใช้งาน:</label>
                            <select class="form-select" id="facility_id" name="facility_id">
                                <option value="">-- เลือกโครงการเพื่อดูสถานที่ --</option>
                            </select>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="start_date" class="form-label">วันเริ่มต้นใช้การ:</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">วันสิ้นสุดใช้การ:</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="quantity" class="form-label">จำนวนที่ต้องการขอใช้:</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>" required>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="transport" name="transport" value="1" <?php echo (isset($_POST['transport']) && $_POST['transport'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="transport">ต้องการขนส่งอุปกรณ์</label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="agree" name="agree" value="1" <?php echo (isset($_POST['agree']) && $_POST['agree'] == 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="agree">ข้าพเจ้ายินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ </label>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="?main_tab=user_requests&mode=equipments_list" class="btn btn-secondary">ย้อนกลับ</a>
                            <div>
                                <button type="submit" name="action" value="save_draft_equipment" class="btn btn-warning me-2">บันทึกแบบร่าง</button>
                                <button type="button" id="submitCreateEquipment" name="action" value="submit_equipment" class="btn btn-success">บันทึกและส่งคำร้อง</button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php elseif ($mode == 'equipments_detail' && $detail_item): ?>
                    <div class="project-detail-card my-4">
                        <h3 class="mb-4">รายละเอียดคำร้องขออุปกรณ์: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                        <div class="row">
                            <div class="col-md-6 pro-details">
                                <p><strong>ชื่อโครงการ: </strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                                <p><strong>สถานะคำร้อง: </strong> <span class="badge <?php echo getStatusBadgeClass($detail_item['writed_status'], $detail_item['approve']); ?>"><?php echo htmlspecialchars($detail_item['writed_status']); ?></span></p>
                                <p><strong>อุปกรณ์ที่ขอใช้งาน: </strong> <?php echo htmlspecialchars($detail_item['equip_name']); ?> จำนวน <?php echo htmlspecialchars($detail_item['quantity']);?> <?php echo htmlspecialchars($detail_item['measure']); ?></p>
                                <p><strong>สถานที่ที่นำอุปกรณ์ไปใช้งาน: </strong> <?php echo htmlspecialchars($detail_item['facility_name'] ?? 'ไม่ระบุ'); ?></p>
                                <p><strong>ระยะเวลาใช้การ: </strong>
                                <?php if (($detail_item['start_date']) !== ($detail_item['end_date'])):?> ตั้งแต่วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['end_date'], false); ?></p>
                                <?php else: ?> วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 pro-details">
                                <p><strong>วันที่สร้างคำร้อง:</strong>
                                        <?php echo formatThaiDate($detail_item['request_date']);?>
                                </p>
                                <p><strong>ผู้เขียนคำร้อง: </strong> <?php echo htmlspecialchars($detail_item['user_name']); ?></p>
                                <p><strong>ต้องการขนส่งอุปกรณ์: </strong> <?php echo ($detail_item['transport'] == 1) ? 'ต้องการ' : 'ไม่ต้องการ'; ?></p>
                                <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ: </strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                                <?php if ($detail_item['approve'] != ''): ?>
                                    <p><strong>การอนุมัติคำร้อง: </strong> <span class="badge <?php echo getStatusBadgeClass($detail_item['writed_status'], $detail_item['approve']); ?>"><?php echo htmlspecialchars($detail_item['approve']); ?></span></p>
                                <?php if ($detail_item['approve'] == 'อนุมัติ'): ?>
                                    <p><strong>วันที่อนุมัติ: </strong> <?php echo formatThaiDate($detail_item['approve_date'], false); ?></p>
                                <?php elseif ($detail_item['approve'] == 'ไม่อนุมัติ' || $detail_item['approve'] == 'ยกเลิก'): ?>
                                    <p><strong>วันที่ดำเนินการ: </strong> <?php echo formatThaiDate($detail_item['approve_date'], false); ?></p>
                                <?php endif; ?>
                                <?php if ($detail_item['approve'] == 'ไม่อนุมัติ' || $detail_item['approve'] == 'ยกเลิก'): ?>
                                    <p><strong>รายละเอียดการ<?php echo htmlspecialchars($detail_item['approve']); ?>:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                <?php elseif ($detail_item['approve'] == 'อนุมัติ' && !empty($detail_item['approve_detail'])): ?>
                                    <p><strong>รายละเอียดการอนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-4">
                            <a href="<?php echo $previous ?: '?main_tab=user_requests&mode=equipments_list'; ?>"
                                class="btn btn-secondary"
                                onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                    ย้อนกลับ
                            </a>
                            <div>
                            <?php
                                $can_edit = (($detail_item['writed_status'] == 'ร่างคำร้องขอ' || $detail_item['writed_status'] == 'ส่งคำร้องขอ') && (strtotime($detail_item['start_date']) >= strtotime(date('Y-m-d', strtotime('+3 days'))) && ($detail_item['approve'] === null || $detail_item['approve'] === '')));
                                $can_delete = ($detail_item['writed_status'] == 'ร่างคำร้องขอ');
                                $can_cancel_request = ($detail_item['writed_status'] !== 'ร่างคำร้องขอ' && $detail_item['writed_status'] !== 'เริ่มดำเนินการ' && $detail_item['writed_status'] !== 'สิ้นสุดดำเนินการ' && $detail_item['writed_status'] !== 'ยกเลิกคำร้องขอ');
                            ?>
                            <?php if ($can_edit): ?>
                                <a href="?main_tab=user_requests&mode=equipments_edit&equip_re_id=<?php echo $detail_item['equip_re_id']; ?>" class="btn btn-warning me-2">
                                    <i class="bi bi-pencil-square"></i> แก้ไขคำร้องขอ
                                </a>
                            <?php endif; ?>
                            <?php if ($can_delete): ?>
                                <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteEquipmentRequestModal"
                                        data-id="<?php echo $detail_item['equip_re_id']; ?>"
                                        data-equip-name="<?php echo htmlspecialchars($detail_item['equip_name']); ?>"
                                        data-type="คำร้องขออุปกรณ์">
                                    <i class="bi bi-trash"></i> ลบคำร้องขอ
                                </button>
                            <?php endif; ?>
                            <?php if ($can_cancel_request): ?>
                                <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#cancelEquipmentRequestModal"
                                        data-id="<?php echo $detail_item['equip_re_id']; ?>"
                                        data-equip-name="<?php echo htmlspecialchars($detail_item['equip_name']); ?>"
                                        data-type="คำร้องขออุปกรณ์">
                                    <i class="bi bi-x-circle"></i> ยกเลิกคำร้องขอ
                                </button>
                            <?php endif; ?>
                        </div>
                        </div>

                        <div class="modal fade" id="deleteEquipmentRequestModal" tabindex="-1" aria-labelledby="deleteEquipmentRequestModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form id="deleteEquipmentRequestForm" action="?main_tab=user_requests" method="POST">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title" id="deleteEquipmentRequestModalLabel">ยืนยันการลบคำร้องขออุปกรณ์</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                    <div class="modal-body">
                                        <p class="details-text">คุณแน่ใจหรือไม่ว่าต้องการลบคำร้องขอใช้อุปกรณ์ "<strong><?php echo htmlspecialchars($detail_item['equip_name']); ?></strong>" ของโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                        การดำเนินการนี้ไม่สามารถย้อนกลับได้.</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                        <input type="hidden" name="action" id="delete_action_er" value="delete_equipment_request">
                                        <input type="hidden" name="equip_re_id" id="delete_er_id" value="">
                                        <button type="submit" class="btn btn-danger">ยืนยันการลบ</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="cancelEquipmentRequestModal" tabindex="-1" aria-labelledby="cancelEquipmentRequestModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <form id="cancelEquipmentRequestForm" action="?main_tab=user_requests" method="POST">
                                        <div class="modal-header bg-dark text-white">
                                            <h5 class="modal-title" id="cancelEquipmentRequestModalLabel">ยืนยันการยกเลิกคำร้องขออุปกรณ์</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                    <div class="modal-body">
                                        <p class="details-text">คุณแน่ใจหรือไม่ว่าต้องการยกเลิกคำร้องขอใช้อุปกรณ์ "<strong><?php echo htmlspecialchars($detail_item['equip_name']); ?></strong>" ของโครงการ "<strong><?php echo htmlspecialchars($detail_item['project_name']); ?></strong>" นี้?
                                        การดำเนินการนี้จะเปลี่ยนสถานะคำร้องเป็น "ยกเลิกคำร้องขอ".</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ไม่</button>
                                        <input type="hidden" name="action" id="cancel_action_er" value="cancel_equipment_request">
                                        <input type="hidden" name="equip_re_id" id="cancel_er_id" value="">
                                        <button type="submit" class="btn btn-dark">ใช่, ยกเลิกคำร้องขอ</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
            <?php endif; ?>
        <?php endif; ?>

    </div>

<!-- Generic Modal (used by admin-modal.js for success/error/confirm) -->
<div class="modal fade" id="genericModal" tabindex="-1" aria-labelledby="genericModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content details-text">
            <div class="modal-header">
                <h5 class="modal-title" id="genericModalLabel"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center" id="genericModalBody">
            </div>
            <div class="modal-footer d-flex justify-content-center" id="genericModalFooter">
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script>
    const phpCurrentMode = "<?php echo $mode; ?>";
    const phpCurrentMainTab = "<?php echo $main_tab; ?>";
</script>
<script src="./js/admin-modal.js"></script>
<script src="./js/building_dropdown.js"></script>
<script src="./js/file_upload.js"></script>
</body>
</html>