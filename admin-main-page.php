<?php
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'เจ้าหน้าที่') {
    header("Location: login.php");
    exit();
}

include 'database/database.php';


$staff_id_for_db = $_SESSION['staff_id'] ?? null;
$staff_name = htmlspecialchars($_SESSION['user_display_name'] ?? 'N/A');
$staff_sur = htmlspecialchars($_SESSION['user_display_sur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');

if (empty($staff_id_for_db) || $staff_id_for_db === 'N/A') {
    $errors[] = "ไม่สามารถดำเนินการได้: Staff ID ไม่ถูกต้องหรือไม่พบสำหรับผู้ดูแลระบบที่ล็อกอินอยู่ โปรดตรวจสอบการตั้งค่าผู้ใช้หรือติดต่อผู้ดูแลระบบ.";
}

// กำหนดขนาดข้อมูลต่อหน้า
$items_per_page = 10;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search_query . '%';

// ตัวแปรสำหรับการกรองวันที่
$predefined_range_select = $_GET['predefined_range'] ?? null;
$specific_year_select = $_GET['specific_year'] ?? null;
$specific_month_select = $_GET['specific_month'] ?? null;
$specific_day_select = $_GET['specific_day'] ?? null;
$fa_de_id_filter_global = $_GET['fa_de_id_global'] ?? null;

$main_tab = isset($_GET['main_tab']) ? $_GET['main_tab'] : 'dashboard_admin';
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'list';

$data = [];
$detail_item = null;
$total_items = 0; 
$errors = []; 
$success_message = ''; 

$chart_sort_mode = $_GET['chart_sort_mode'] ?? 'faculty_overview';
$drilldown_type = $_GET['drilldown_type'] ?? null;
$drilldown_id = $_GET['drilldown_id'] ?? null;

include 'php/helpers.php'; 
include 'php/sorting.php';
include 'php/admin-sorting.php';
include 'php/chart-sorting.php'; 
include 'php/status_update.php'; 
include 'php/admin-dropdown.php'; 
include 'php/admin-req-injection.php'; 
include 'php/admin-dashboard.php'; 
include 'php/admin-data-list.php'; 


// --- เริ่มตรรกะสำหรับการกำหนดค่า $previous (สำหรับปุ่มย้อนกลับ) ---
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$previous = 'admin-main-page.php?main_tab=dashboard_admin'; // ค่าเริ่มต้นปลอดภัย: กลับไป Dashboard เสมอ

$is_admin_referrer = (strpos($referrer, 'admin-main-page.php') !== false);
$referrer_url_parts = parse_url($referrer);
$referrer_query = [];
if (isset($referrer_url_parts['query'])) {
    parse_str($referrer_url_parts['query'], $referrer_query);
}

if ($mode === 'detail') {
    if ($main_tab === 'projects_admin') {
        if ($is_admin_referrer && ($referrer_query['main_tab'] ?? '') === 'dashboard_admin' && !isset($referrer_query['mode'])) {
            $_SESSION['projects_detail_entry_referrer'] = 'admin-main-page.php?main_tab=dashboard_admin';
            $previous = 'admin-main-page.php?main_tab=dashboard_admin';
        } elseif ($is_admin_referrer && ($referrer_query['main_tab'] ?? '') === 'projects_admin' && (!isset($referrer_query['mode']) || $referrer_query['mode'] === 'list')) {
            $_SESSION['projects_detail_entry_referrer'] = 'admin-main-page.php?main_tab=projects_admin';
            $previous = 'admin-main-page.php?main_tab=projects_admin';
        } elseif (isset($_SESSION['projects_detail_entry_referrer'])) {
            $previous = $_SESSION['projects_detail_entry_referrer'];
        } else {
            $previous = 'admin-main-page.php?main_tab=projects_admin';
        }
    } elseif ($main_tab === 'buildings_admin' || $main_tab === 'equipments_admin') {
        if ($is_admin_referrer && ($referrer_query['main_tab'] ?? '') === 'projects_admin' && ($referrer_query['mode'] ?? '') === 'detail') {
            $previous = $referrer;
        } elseif ($is_admin_referrer && ($referrer_query['main_tab'] ?? '') === 'dashboard_admin' && !isset($referrer_query['mode'])) {
            $previous = 'admin-main-page.php?main_tab=dashboard_admin';
        } elseif ($is_admin_referrer && ($referrer_query['main_tab'] ?? '') === $main_tab && (!isset($referrer_query['mode']) || $referrer_query['mode'] === 'list')) {
            $previous = 'admin-main-page.php?main_tab=' . htmlspecialchars($main_tab);
        } else {
            $previous = 'admin-main-page.php?main_tab=' . htmlspecialchars($main_tab);
        }
    }
} else {
    unset($_SESSION['projects_detail_entry_referrer']);

    if ($is_admin_referrer) {
        if (empty($referrer) || strpos($referrer, 'login.php') !== false) {
             $previous = 'admin-main-page.php?main_tab=dashboard_admin';
        } elseif (isset($referrer_query['mode']) && $referrer_query['mode'] === 'detail') {
            $previous = 'admin-main-page.php?main_tab=' . htmlspecialchars($referrer_query['main_tab'] ?? 'dashboard_admin');
        } else {
            $previous = $referrer;
        }
    } else {
        $previous = 'admin-main-page.php?main_tab=dashboard_admin';
    }
}

$conn->close();

$total_pages = ($items_per_page > 0) ? ceil($total_items / $items_per_page) : 1;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</head>
<body class="admin-body">
    <nav class="navbar navbar-dark navigator screen-only">
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
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo $staff_name . ' ' . $staff_sur; ?></span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5"><?php echo $user_role; ?></span>
                </div>
                <a href="admin-profile-page.php">
                    <img src="./images/user_button.png" class="img-fluid logo" style="width:40px; height:40px; object-fit:cover;" alt="User Button">
                </a>
            </div>
        </div>
    </nav>

    <div class="admin-main-wrapper">
        <!-- เมนูแถบด้านบน -->
        <div class="admin-sidebar screen-only">
            <h5 class="mb-3">เมนูผู้ดูแลระบบ</h5>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="admin-details nav-link <?php echo ($main_tab == 'dashboard_admin') ? 'active' : ''; ?>" href="?main_tab=dashboard_admin">
                        <i class="bi bi-speedometer2"></i> ภาพรวม
                    </a>
                </li>
                <li class="nav-item">
                    <a class="admin-details nav-link <?php echo ($main_tab == 'projects_admin') ? 'active' : ''; ?>" href="?main_tab=projects_admin">
                        <i class="bi bi-folder"></i> รายการโครงการ
                    </a>
                </li>
                <li class="nav-item">
                    <a class="admin-details nav-link <?php echo ($main_tab == 'buildings_admin') ? 'active' : ''; ?>" href="?main_tab=buildings_admin">
                        <i class="bi bi-building"></i> คำร้องขอใช้สถานที่
                    </a>
                </li>
                <li class="nav-item">
                    <a class="admin-details nav-link <?php echo ($main_tab == 'equipments_admin') ? 'active' : ''; ?>" href="?main_tab=equipments_admin">
                        <i class="bi bi-tools"></i> คำร้องขอใช้อุปกรณ์
                    </a>
                </li>
            </ul>
        </div>

        <div class="admin-content-area" id="mainContent">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert" class="screen-only">
                    <h4 class="alert-heading">เกิดข้อผิดพลาด!</h4>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php
            $modal_status = $_GET['status'] ?? '';
            $modal_message = $_GET['message'] ?? '';
            if ($modal_status == 'success' && $modal_message != ''): ?>
                <div class="alert alert-success" role="alert" class="screen-only">
                    <h4 class="alert-heading">สำเร็จ!</h4>
                    <p><?php echo htmlspecialchars($modal_message); ?></p>
                </div>
            <?php endif; ?>
            <?php

            if (isset($_GET['status']) || isset($_GET['message'])) {
                $current_params = $_GET;
                unset($current_params['status']);
                unset($current_params['message']);
                $new_url = '?' . http_build_query($current_params);
                echo '<script>window.history.replaceState({}, document.title, "' . $new_url . '");</script>';
            }
            ?>

            <?php if ($main_tab == 'dashboard_admin'): ?>
                <h1 class="mb-4">ภาพรวมคำร้องขอทั้งหมด</h1>
                <div class="row mb-4 justify-content-end screen-only">
                    <div class="col-md-auto">
                        <form id="dateFilterFormDashboard" class="d-inline-flex gap-2 align-items-center" action="" method="GET">
                            <input type="hidden" name="main_tab" value="<?php echo htmlspecialchars($main_tab); ?>">
                            <?php if ($chart_sort_mode): ?><input type="hidden" name="chart_sort_mode" value="<?php echo htmlspecialchars($chart_sort_mode); ?>"><?php endif; ?>
                            <?php if ($drilldown_type): ?><input type="hidden" name="drilldown_type" value="<?php echo htmlspecialchars($drilldown_type); ?>"><?php endif; ?>
                            <?php if ($drilldown_id): ?><input type="hidden" name="drilldown_id" value="<?php echo htmlspecialchars($drilldown_id); ?>"><?php endif; ?>

                            <select name="predefined_range" id="predefined_range_select" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">กรองตามวันที่...</option>
                                <option value="today" <?php echo ($predefined_range_select == 'today') ? 'selected' : ''; ?>>วันนี้</option>
                                <option value="this_week" <?php echo ($predefined_range_select == 'this_week') ? 'selected' : ''; ?>>สัปดาห์นี้</option>
                                <option value="this_month" <?php echo ($predefined_range_select == 'this_month') ? 'selected' : ''; ?>>เดือนนี้</option>
                                <option value="this_year" <?php echo ($predefined_range_select == 'this_year') ? 'selected' : ''; ?>>ปีนี้</option>
                            </select>

                            <select name="specific_year" id="specific_year_select" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">ปี</option>
                                <?php for ($y = date('Y') + 1; $y >= 2021; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo ($specific_year_select == $y) ? 'selected' : ''; ?>><?php echo $y + 543; ?></option>
                                <?php endfor; ?>
                            </select>
                            <select name="specific_month" id="specific_month_select" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">เดือน</option>
                                <?php
                                $thai_months_full = [
                                    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
                                    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
                                    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                                ];
                                foreach ($thai_months_full as $num => $name): ?>
                                    <option value="<?php echo $num; ?>" <?php echo ($specific_month_select == $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <select name="specific_day" id="specific_day_select" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">วัน</option>
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?php echo $d; ?>" <?php echo ($specific_day_select == $d) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                <?php endfor; ?>
                            </select>

                            <select name="fa_de_id_global" id="fa_de_id_select_dashboard" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                <option value="">กรองตามคณะ...</option>
                                <?php foreach ($faculties_for_chart_filter as $faculty): ?>
                                    <option value="<?php echo $faculty['fa_de_id']; ?>" <?php echo (($fa_de_id_filter_global ?? null) == $faculty['fa_de_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($faculty['fa_de_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <?php
                                $clear_url_params = ['main_tab' => 'dashboard_admin'];
                                if (in_array($chart_sort_mode, ['top_facilities', 'top_equipments'])) {
                                    $clear_url_params['chart_sort_mode'] = $chart_sort_mode;
                                } elseif ($chart_sort_mode === 'drilldown_facility_by_faculty') {
                                    $clear_url_params['chart_sort_mode'] = 'top_facilities';
                                } elseif ($chart_sort_mode === 'drilldown_equipment_by_faculty') {
                                    $clear_url_params['chart_sort_mode'] = 'top_equipments';
                                }
                                $clear_url = '?' . http_build_query(array_filter($clear_url_params, fn($value) => $value !== null && $value !== ''));
                            ?>
                            <?php if (!empty($predefined_range_select) || !empty($specific_year_select) || !empty($specific_month_select) || !empty($specific_day_select) || !empty($fa_de_id_filter_global) || (in_array($chart_sort_mode, ['top_facilities', 'top_equipments'])) || ($drilldown_type && $drilldown_id)): ?>
                                <a href="<?php echo $clear_url; ?>" class="btn btn-outline-secondary btn-sm">ล้าง</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="row row-cols-1 row-cols-md-3 g-4 mb-4 screen-only">
                    <div class="col">
                        <div class="card text-white bg-primary mb-3 h-100">
                            <div class="card-header"><i class="bi bi-folder-fill me-2"></i>โครงการ</div>
                            <div class="card-body">
                                <h5 class="card-title">โครงการทั้งหมด</h5>
                                <p class="card-text fs-1"><?php echo $total_projects_count; ?></p>
                            </div>
                            <div class="card-footer">
                                <?php
                                    $link_params = ['main_tab' => 'projects_admin', 'mode' => 'list'];
                                    if (!empty($predefined_range_select)) $link_params['predefined_range'] = $predefined_range_select;
                                    if (!empty($specific_year_select)) $link_params['specific_year'] = $specific_year_select;
                                    if (!empty($specific_month_select)) $link_params['specific_month'] = $specific_month_select;
                                    if (!empty($specific_day_select)) $link_params['specific_day'] = $specific_day_select;
                                    if (!empty($fa_de_id_filter_global)) $link_params['fa_de_id_global'] = $fa_de_id_filter_global;
                                ?>
                                <a href="?<?php echo http_build_query($link_params); ?>" class="stretched-link text-white text-decoration-none footer-text">ดูรายละเอียด <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card text-white bg-success mb-3 h-100">
                            <div class="card-header"><i class="bi bi-building-fill me-2"></i>คำร้องขอใช้สถานที่</div>
                            <div class="card-body">
                                <h5 class="card-title">คำร้องขอใช้สถานที่ทั้งหมด</h5>
                                <p class="card-text fs-1"><?php echo $total_facilities_requests_count; ?></p>
                            </div>
                            <div class="card-footer">
                                <?php
                                    $link_params = ['main_tab' => 'buildings_admin', 'mode' => 'list'];
                                    if (!empty($predefined_range_select)) $link_params['predefined_range'] = $predefined_range_select;
                                    if (!empty($specific_year_select)) $link_params['specific_year'] = $specific_year_select;
                                    if (!empty($specific_month_select)) $link_params['specific_month'] = $specific_month_select;
                                    if (!empty($specific_day_select)) $link_params['specific_day'] = $specific_day_select;
                                    if (!empty($fa_de_id_filter_global)) $link_params['fa_de_id_global'] = $fa_de_id_filter_global;
                                ?>
                                <a href="?<?php echo http_build_query($link_params); ?>" class="stretched-link text-white text-decoration-none footer-text">ดูรายละเอียด <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col">
                        <div class="card text-dark bg-warning mb-3 h-100">
                            <div class="card-header"><i class="bi bi-tools me-2"></i>คำร้องขอใช้อุปกรณ์</div>
                            <div class="card-body">
                                <h5 class="card-title">คำร้องขอใช้อุปกรณ์ทั้งหมด</h5>
                                <p class="card-text fs-1"><?php echo $total_equipments_requests_count; ?></p>
                            </div>
                            <div class="card-footer">
                                <?php
                                    $link_params = ['main_tab' => 'equipments_admin', 'mode' => 'list'];
                                    if (!empty($predefined_range_select)) $link_params['predefined_range'] = $predefined_range_select;
                                    if (!empty($specific_year_select)) $link_params['specific_year'] = $specific_year_select;
                                    if (!empty($specific_month_select)) $link_params['specific_month'] = $specific_month_select;
                                    if (!empty($specific_day_select)) $link_params['specific_day'] = $specific_day_select;
                                    if (!empty($fa_de_id_filter_global)) $link_params['fa_de_id_global'] = $fa_de_id_filter_global;
                                ?>
                                <a href="?<?php echo http_build_query($link_params); ?>" class="stretched-link text-dark text-decoration-none footer-text">ดูรายละเอียด <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="screen-only">

                <div class="row g-4 mb-4 screen-only">
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
                                            $detail_link = '?main_tab=projects_admin&mode=detail&id=' . $req['id'];
                                        } elseif ($req['type'] == 'คำร้องขอสถานที่') {
                                            $detail_link = '?main_tab=buildings_admin&mode=detail&id=' . $req['id'];
                                        } elseif ($req['type'] == 'คำร้องขออุปกรณ์') {
                                            $detail_link = '?main_tab=equipments_admin&mode=detail&id=' . $req['id'];
                                        }
                                    ?>
                                    <li class="list-group-item activity-item">
                                        <?php if ($detail_link): ?>
                                            <a href="<?php echo $detail_link; ?>" class="d-flex w-100 justify-content-between align-items-center stretched-link text-decoration-none text-dark inbox-text">
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
                            <h4 class="mb-3">คำร้องขอใช้ล่าสุด <span class="badge bg-secondary"><?php echo count($dashboard_data['recent_activity']); ?></span></h4>
                            <?php if (empty($dashboard_data['recent_activity'])): ?>
                                <div class="alert alert-info text-center py-3 mb-0">
                                    ยังไม่มีคำร้องขอใช้ใด ๆ จากผู้ใช้
                                </div>
                            <?php else: ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($dashboard_data['recent_activity'] as $activity):
                                        $detail_link = '';

                                        if ($activity['item_type'] == 'คำร้องขอสถานที่') {
                                            $detail_link = '?main_tab=buildings_admin&mode=detail&id=' . $activity['id'];
                                        } elseif ($activity['item_type'] == 'คำร้องขออุปกรณ์') {
                                            $detail_link = '?main_tab=equipments_admin&mode=detail&id=' . $activity['id'];
                                        }
                                    ?>
                                        <li class="list-group-item activity-item">
                                            <a href="<?php echo $detail_link; ?>" class="d-flex w-100 justify-content-between align-items-center stretched-link text-decoration-none text-dark inbox-text">
                                                <div class="main-info">
                                                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($activity['item_type']); ?></span>
                                                    <strong><?php echo htmlspecialchars($activity['item_name']); ?></strong>
                                                </div>
                                                <div class="date-info">
                                                    <span class="badge <?php echo getStatusBadgeClass($activity['status_text'], $activity['approve_status'] ?? null); ?>"><?php echo htmlspecialchars($activity['approve_status'] ?? $activity['status_text']); ?></span><br>
                                                    <small class="text-muted"><?php echo formatThaiDate($activity['activity_date']); ?></small>
                                                </div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4 screen-only">
                    <div class="col-12">
                        <div class="card h-100 shadow-sm p-4">
                            <h4 class="mb-3" id="chartTitle"></h4>
                            <div class="d-flex justify-content-end mb-3 chart-sorting-controls">
                                <form id="chartSortForm" class="d-inline-flex gap-2 align-items-center" action="" method="GET">
                                    <input type="hidden" name="main_tab" value="<?php echo htmlspecialchars($main_tab); ?>">
                                    <?php if (!empty($predefined_range_select)): ?><input type="hidden" name="predefined_range" value="<?php echo htmlspecialchars($predefined_range_select); ?>"><?php endif; ?>
                                    <?php if (!empty($specific_year_select)): ?><input type="hidden" name="specific_year" value="<?php echo htmlspecialchars($specific_year_select); ?>"><?php endif; ?>
                                    <?php if (!empty($specific_month_select)): ?><input type="hidden" name="specific_month" value="<?php echo htmlspecialchars($specific_month_select); ?>"><?php endif; ?>
                                    <?php if (!empty($specific_day_select)): ?><input type="hidden" name="specific_day" value="<?php echo htmlspecialchars($specific_day_select); ?>"><?php endif; ?>
                                    <?php if (!empty($fa_de_id_filter_global)): ?><input type="hidden" name="fa_de_id_global" value="<?php echo htmlspecialchars($fa_de_id_filter_global); ?>"><?php endif; ?>

                                    <label for="chart_sort_mode_select" class="form-label mb-0">จำแนกตาม:</label>
                                    <select name="chart_sort_mode" id="chart_sort_mode_select" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                                        <option value="faculty_overview" <?php echo ($chart_sort_mode == 'faculty_overview') ? 'selected' : ''; ?>>คณะ</option>
                                        <option value="top_facilities" <?php echo ($chart_sort_mode == 'top_facilities') ? 'selected' : ''; ?>>อาคารสถานที่ 10 อันดับแรก</option>
                                        <option value="top_equipments" <?php echo ($chart_sort_mode == 'top_equipments') ? 'selected' : ''; ?>>อุปกรณ์ 10 อันดับแรก</option>
                                    </select>
                                    <?php
                                        $back_button_url_parts = [
                                            'main_tab' => 'dashboard_admin',
                                            'predefined_range' => $predefined_range_select,
                                            'specific_year' => $specific_year_select,
                                            'specific_month' => $specific_month_select,
                                            'specific_day' => $specific_day_select,
                                            'fa_de_id_global' => $fa_de_id_filter_global,
                                        ];

                                        if (in_array($chart_sort_mode, ['drilldown_facility_by_faculty', 'drilldown_equipment_by_faculty'])) {
                                            $back_button_url_parts['chart_sort_mode'] = ($chart_sort_mode === 'drilldown_facility_by_faculty') ? 'top_facilities' : 'top_equipments';
                                        } else {
                                            unset($back_button_url_parts['drilldown_type']);
                                            unset($back_button_url_parts['drilldown_id']);
                                        }

                                        $back_button_url_parts = array_filter($back_button_url_parts, function($value) {
                                            return $value !== null && $value !== '';
                                        });

                                        $back_button_url = '?' . http_build_query($back_button_url_parts);
                                    ?>
                                    <?php if (in_array($chart_sort_mode, ['drilldown_facility_by_faculty', 'drilldown_equipment_by_faculty'])): ?>
                                        <a href="<?php echo htmlspecialchars($back_button_url); ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-arrow-left"></i> ย้อนกลับ
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div style="height: 400px;">
                                <canvas id="dashboardChartCanvas"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($mode == 'list'): ?>
                <h1 class="mb-4">
                    <?php
                    if ($main_tab == 'projects_admin') echo 'โครงการทั้งหมด';
                    elseif ($main_tab == 'buildings_admin') echo 'คำร้องขอใช้สถานที่';
                    elseif ($main_tab == 'equipments_admin') echo 'คำร้องขอใช้อุปกรณ์';
                    ?>
                </h1>
                <div class="d-flex justify-content-end mb-3 screen-only flex-wrap gap-2">
                    <form id="combinedFilterFormList" class="d-inline-flex gap-2 align-items-center flex-wrap" action="" method="GET">
                        <input type="hidden" name="main_tab" value="<?php echo htmlspecialchars($main_tab); ?>">
                        <input type="hidden" name="mode" value="list">

                        <select name="sort_filter" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto;">
                            <optgroup label="เรียงตามวันที่">
                                <option value="date_desc" <?php echo (($_GET['sort_filter'] ?? 'date_desc') == 'date_desc') ? 'selected' : ''; ?>>ใหม่สุดไปเก่าสุด</option>
                                <option value="date_asc" <?php echo (($_GET['sort_filter'] ?? '') == 'date_asc') ? 'selected' : ''; ?>>เก่าสุดไปใหม่สุด</option>
                            </optgroup>
                            <optgroup label="กรองตามสถานะ">
                                <?php if ($main_tab == 'projects_admin'): ?>
                                    <option value="ส่งโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'ส่งโครงการ') ? 'selected' : ''; ?>>ส่งโครงการ</option>
                                    <option value="เริ่มดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'เริ่มดำเนินการ') ? 'selected' : ''; ?>>เริ่มดำเนินการ</option>
                                    <option value="สิ้นสุดโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'สิ้นสุดโครงการ') ? 'selected' : ''; ?>>สิ้นสุดโครงการ</option>
                                    <option value="ยกเลิกโครงการ" <?php echo (($_GET['sort_filter'] ?? '') == 'ยกเลิกโครงการ') ? 'selected' : ''; ?>>ยกเลิกโครงการ</option>
                                <?php elseif (in_array($main_tab, ['buildings_admin', 'equipments_admin'])): ?>
                                    <option value="ส่งคำร้องขอ" <?php echo (($_GET['sort_filter'] ?? '') == 'ส่งคำร้องขอ') ? 'selected' : ''; ?>>ส่งคำร้องขอ</option>
                                    <option value="อนุมัติ" <?php echo (($_GET['sort_filter'] ?? '') == 'อนุมัติ') ? 'selected' : ''; ?>>อนุมัติ</option>
                                    <option value="ไม่อนุมัติ" <?php echo (($_GET['sort_filter'] ?? '') == 'ไม่อนุมัติ') ? 'selected' : ''; ?>>ไม่อนุมัติ</option>
                                    <option value="เริ่มดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'เริ่มดำเนินการ') ? 'selected' : ''; ?>>เริ่มดำเนินการ</option>
                                    <option value="สิ้นสุดดำเนินการ" <?php echo (($_GET['sort_filter'] ?? '') == 'สิ้นสุดดำเนินการ') ? 'selected' : ''; ?>>สิ้นสุดดำเนินการ</option>
                                    <option value="ยกเลิกคำร้องขอ" <?php echo (($_GET['sort_filter'] ?? '') == 'ยกเลิกคำร้องขอ') ? 'selected' : ''; ?>>ยกเลิกคำร้องขอ</option>
                                <?php endif; ?>
                            </optgroup>
                        </select>

                        <input class="form-control form-control-sm" type="search" placeholder="ค้นหา..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" style="width: 150px;">
                        <button class="btn btn-outline-success btn-sm" type="submit">ค้นหา</button>

                        <select name="predefined_range" id="predefined_range_select_list" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">กรองตามวันที่...</option>
                            <option value="today" <?php echo ($predefined_range_select == 'today') ? 'selected' : ''; ?>>วันนี้</option>
                            <option value="this_week" <?php echo ($predefined_range_select == 'this_week') ? 'selected' : ''; ?>>สัปดาห์นี้</option>
                            <option value="this_month" <?php echo ($predefined_range_select == 'this_month') ? 'selected' : ''; ?>>เดือนนี้</option>
                            <option value="this_year" <?php echo ($predefined_range_select == 'this_year') ? 'selected' : ''; ?>>ปีนี้</option>
                        </select>
                        <select name="specific_year" id="specific_year_select_list" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">ปี</option>
                            <?php for ($y = date('Y') + 1; $y >= 2021; $y--): ?>
                                <option value="<?php echo $y; ?>" <?php echo ($specific_year_select == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <select name="specific_month" id="specific_month_select_list" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">เดือน</option>
                            <?php
                            $thai_months_full = [
                                1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
                                5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
                                9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
                            ];
                            foreach ($thai_months_full as $num => $name): ?>
                                <option value="<?php echo $num; ?>" <?php echo ($specific_month_select == $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="specific_day" id="specific_day_select_list" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">วัน</option>
                            <?php for ($d = 1; $d <= 31; $d++): ?>
                                <option value="<?php echo $d; ?>" <?php echo ($specific_day_select == $d) ? 'selected' : ''; ?>><?php echo $d; ?></option>
                            <?php endfor; ?>
                        </select>

                        <select name="fa_de_id_global" id="fa_de_id_select_list" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="">กรองตามคณะ...</option>
                            <?php foreach ($faculties_for_chart_filter as $faculty): ?>
                                <option value="<?php echo $faculty['fa_de_id']; ?>" <?php echo (($fa_de_id_filter_global ?? null) == $faculty['fa_de_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($faculty['fa_de_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php
                        $clear_all_params = ['main_tab' => $main_tab, 'mode' => 'list'];
                        if (!empty($search_query) || !empty($_GET['sort_filter']) || !empty($predefined_range_select) || !empty($specific_year_select) || !empty($specific_month_select) || !empty($specific_day_select) || !empty($fa_de_id_filter_global)): ?>
                            <a href="?<?php echo http_build_query($clear_all_params); ?>" class="btn btn-outline-secondary btn-sm">ล้างทั้งหมด</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (empty($data)): ?>
                    <div class="alert alert-info text-center mt-4">
                        <?php if (!empty($search_query)): ?>
                            ไม่พบรายการที่คุณค้นหา "<?php echo htmlspecialchars($search_query); ?>" ในสถานะที่แสดงได้
                        <?php else: ?>
                            ยังไม่มีรายการในสถานะที่แสดงได้
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="table-responsive admin-details list-text">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ลำดับที่</th>
                                    <?php if ($main_tab == 'projects_admin'): ?>
                                        <th>วันที่สร้าง</th>
                                        <th>ชื่อโครงการ</th>
                                        <th>ผู้ยื่น</th>
                                        <th>ประเภทกิจกรรม</th>
                                        <th>สถานะโครงการ</th>
                                    <?php elseif ($main_tab == 'buildings_admin'): ?>
                                        <th>วันที่ยื่นคำร้อง</th>
                                        <th>โครงการ</th>
                                        <th>สถานที่</th>
                                        <th>ผู้ยื่น</th>
                                        <th>ช่วงเวลาใช้งาน</th>
                                        <th>สถานะคำร้อง</th>
                                        <th>การอนุมัติ</th>
                                    <?php elseif ($main_tab == 'equipments_admin'): ?>
                                        <th>วันที่ยื่นคำร้อง</th>
                                        <th>โครงการ</th>
                                        <th>อุปกรณ์</th>
                                        <th>จำนวน</th>
                                        <th>ผู้ยื่น</th>
                                        <th>ช่วงเวลาใช้งาน</th>
                                        <th>สถานะคำร้อง</th>
                                        <th>การอนุมัติ</th>
                                    <?php endif; ?>
                                    <th>ตรวจสอบรายละเอียด</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $item_number = $offset + 1; ?>
                                <?php foreach ($data as $item): ?>
                                    <tr>
                                        <td><?php echo $item_number++; ?></td>
                                        <?php if ($main_tab == 'projects_admin'): ?>
                                            <td><?php echo (new DateTime($item['created_date']))->format('d/m/Y H:i'); ?></td>
                                            <td><?php echo htmlspecialchars($item['project_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['user_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['activity_type_name'] ?? 'ไม่ระบุ'); ?></td>
                                            <td><?php echo htmlspecialchars($item['writed_status']); ?></td>
                                            <td>
                                                <a href="?main_tab=projects_admin&mode=detail&id=<?php echo $item['project_id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="bi bi-eye"></i> ดูรายละเอียด
                                                </a>
                                            </td>
                                        <?php elseif ($main_tab == 'buildings_admin'): ?>
                                            <td><?php echo (new DateTime($item['request_date']))->format('d/m/Y H:i'); ?></td>
                                            <td><?php echo htmlspecialchars($item['project_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['facility_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['user_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo (new DateTime($item['start_date']))->format('d/m/Y'); ?> (<?php echo (new DateTime($item['start_time']))->format('H:i'); ?>)</td>
                                            <td><?php echo htmlspecialchars($item['writed_status']); ?></td>
                                            <td>
                                                <?php
                                                if (isset($item['approve']) && !empty($item['approve'])) {
                                                    echo '<span class="badge ' . getStatusBadgeClass($item['writed_status'], $item['approve']) . '">' . htmlspecialchars($item['approve']) . '</span>';
                                                } elseif($item['writed_status'] != 'ยกเลิกคำร้องขอ') {
                                                    echo '<span class="badge bg-warning text-dark">รอดำเนินการ</span>';
                                                } else {
                                                    echo '<span class="badge bg-dark">ยกเลิกคำร้องขอ</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="?main_tab=buildings_admin&mode=detail&id=<?php echo $item['facility_re_id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="bi bi-eye"></i> ดูรายละเอียด
                                                </a>
                                            </td>
                                        <?php elseif ($main_tab == 'equipments_admin'): ?>
                                            <td><?php echo (new DateTime($item['request_date']))->format('d/m/Y H:i'); ?></td>
                                            <td><?php echo htmlspecialchars($item['project_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['equip_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['quantity'] . ' ' . $item['measure']); ?></td>
                                            <td><?php echo htmlspecialchars($item['user_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo (new DateTime($item['start_date']))->format('d/m/Y'); ?> - <?php echo (new DateTime($item['end_date']))->format('d/m/Y'); ?></td>
                                            <td><?php echo htmlspecialchars($item['writed_status']); ?></td>
                                            <td>
                                                <?php
                                                if (isset($item['approve']) && !empty($item['approve'])) {
                                                    echo '<span class="badge ' . getStatusBadgeClass($item['writed_status'], $item['approve']) . '">' . htmlspecialchars($item['approve']) . '</span>';
                                                } elseif($item['writed_status'] != 'ยกเลิกคำร้องขอ') {
                                                    echo '<span class="badge bg-warning text-dark">รอดำเนินการ</span>';
                                                } else {
                                                    echo '<span class="badge bg-dark">ยกเลิกคำร้องขอ</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <a href="?main_tab=equipments_admin&mode=detail&id=<?php echo $item['equip_re_id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="bi bi-eye"></i> ดูรายละเอียด
                                                </a>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <nav aria-label="Page navigation" class="screen-only">
                        <ul class="pagination justify-content-center">
                            <?php
                            $pagination_params = [
                                'main_tab' => $main_tab,
                                'mode' => 'list',
                                'search' => $search_query,
                                'sort_filter' => $_GET['sort_filter'] ?? '',
                                'predefined_range' => $predefined_range_select,
                                'specific_year' => $specific_year_select,
                                'specific_month' => $specific_month_select,
                                'specific_day' => $specific_day_select,
                                'fa_de_id_global' => $fa_de_id_filter_global,
                            ];
                            // Clean up empty params
                            $pagination_params = array_filter($pagination_params, fn($value) => $value !== null && $value !== '');

                            $base_pagination_url = '?' . http_build_query($pagination_params);
                            ?>

                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo htmlspecialchars($base_pagination_url . '&page=' . ($current_page - 1)); ?>">ก่อนหน้า</a>
                                </li>
                            <?php endif; ?>

                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo htmlspecialchars($base_pagination_url . '&page=' . $i); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="<?php echo htmlspecialchars($base_pagination_url . '&page=' . ($current_page + 1)); ?>">ถัดไป</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php elseif ($mode == 'detail' && $detail_item): ?>

                <?php if ($main_tab == 'projects_admin'): ?>
                    <h2 class="mb-4 screen-only">รายละเอียดโครงการ: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <div class="card shadow-sm p-4 admin-details screen-only">
                        <div class="row list-text">
                            <div class="col-md-6">
                                <p><strong>ชื่อโครงการ:</strong> <?php echo htmlspecialchars($detail_item['project_name']); ?></p>
                                <p><strong>สถานะโครงการ:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                                <p><strong>ระยะเวลาโครงการ:</strong>
                                <?php if ($detail_item['start_date'] != $detail_item['end_date']) : ?>
                                    ตั้งแต่วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['end_date'], false); ?>
                                <?php else: ?>
                                    วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?>
                                <?php endif; ?>
                                </p>
                                <p><strong>จำนวนผู้เข้าร่วม:</strong> <?php echo htmlspecialchars($detail_item['attendee']); ?></p>
                                <p><strong>หมายเลขโทรศัพท์:</strong> <?php echo htmlspecialchars($detail_item['phone_num']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <?php if (isset($detail_item['advisor_name']) && !empty($detail_item['advisor_name'])): ?>
                                    <p><strong>ชื่อที่ปรึกษาโครงการ:</strong> <?php echo htmlspecialchars($detail_item['advisor_name']); ?></p>
                                <?php endif; ?>
                                <p><strong>ประเภทกิจกรรม:</strong> <?php echo htmlspecialchars($detail_item['activity_type_name'] ?? 'ไม่ระบุ'); ?></p>
                                <p><strong>ผู้ยื่นโครงการ:</strong> <?php echo htmlspecialchars($detail_item['user_name'] ?? 'ไม่ระบุ'); ?></p>
                                <p><strong>วันที่สร้างโครงการ:</strong> <?php echo formatThaiDate($detail_item['created_date']); ?></p>
                                <p><strong>รายละเอียดโครงการ:</strong><br> <?php echo nl2br(htmlspecialchars($detail_item['project_des'])); ?></p>
                                <?php
                                    $project_files = json_decode($detail_item['files'], true) ?: [];
                                ?>
                                <?php if (!empty($project_files)): ?>
                                    <p><strong>ไฟล์แนบ:</strong></p>
                                    <ul class="list-unstyled">
                                        <?php foreach ($project_files as $file_path): ?>
                                            <li>
                                                <a href="<?php echo htmlspecialchars($file_path); ?>" target="_blank" class="btn btn-secondary btn-sm mb-1 screen-only">
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
                        <div class="d-flex screen-only">
                            <a href="<?php echo htmlspecialchars($previous) ?: '#'; ?>"
                            class="btn btn-secondary me-2"
                            onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                ย้อนกลับ
                            </a>
                            <a href="admin-print-page.php?project_id=<?php echo htmlspecialchars($detail_item['project_id']); ?>&print_all=true" target="_blank" class="btn btn-info me-2">
                                <i class="bi bi-printer"></i> พิมพ์คำร้องทั้งหมด
                            </a>
                        </div>

                        <h4 class="mt-4 mb-3 screen-only">คำร้องขอใช้สถานที่ที่เกี่ยวข้อง (สรุป)</h4>
                        <?php if (empty($project_facility_requests)): ?>
                            <div class="alert alert-info screen-only">ไม่มีคำร้องขอใช้สถานที่สำหรับโครงการนี้</div>
                        <?php else: ?>
                            <ul class="list-group screen-only">
                                <?php foreach ($project_facility_requests as $req): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center page-break-inside-avoid">
                                        <div>
                                            <strong><?php echo htmlspecialchars($req['facility_name']); ?></strong>
                                            (สถานะ: <?php echo htmlspecialchars($req['writed_status']); ?>)
                                            <?php if (isset($req['approve']) && !empty($req['approve'])): ?>
                                                (การอนุมัติคำร้อง: <span class="badge <?php echo getStatusBadgeClass($req['writed_status'], $req['approve']); ?>"><?php echo htmlspecialchars($req['approve']); ?></span>)
                                            <?php endif; ?>
                                            <br><small>ช่วงเวลา: <?php echo formatThaiDate($req['start_date'], false); ?> ถึง <?php echo formatThaiDate($req['end_date'], false); ?></small>
                                        </div>
                                        <a href="?main_tab=buildings_admin&mode=detail&id=<?php echo $req['facility_re_id']; ?>" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>

                        <h4 class="mt-4 mb-3 screen-only">คำร้องขอใช้อุปกรณ์ที่เกี่ยวข้อง (สรุป)</h4>
                        <?php if (empty($project_equipment_requests)): ?>
                            <div class="alert alert-info screen-only">ไม่มีคำร้องขอใช้อุปกรณ์สำหรับโครงการนี้</div>
                        <?php else: ?>
                            <ul class="list-group screen-only">
                                <?php foreach ($project_equipment_requests as $req): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center page-break-inside-avoid">
                                        <div>
                                            <strong><?php echo htmlspecialchars($req['equip_name']); ?></strong> (<?php echo htmlspecialchars($req['quantity'] . ' ' . $req['measure']); ?>)
                                            (สถานะ: <?php echo htmlspecialchars($req['writed_status']); ?>)
                                            <?php if (isset($req['approve']) && !empty($req['approve'])): ?>
                                                (การอนุมัติคำร้อง: <span class="badge <?php echo getStatusBadgeClass($req['writed_status'], $req['approve']); ?>"><?php echo htmlspecialchars($req['approve']); ?></span>)
                                            <?php endif; ?>
                                            <br><small>ช่วงเวลา: <?php echo formatThaiDate($req['start_date'], false); ?> ถึง <?php echo formatThaiDate($req['end_date'], false); ?></small>
                                        </div>
                                        <a href="?main_tab=equipments_admin&mode=detail&id=<?php echo $req['equip_re_id']; ?>" class="btn btn-sm btn-outline-primary">ดูรายละเอียด</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                <?php elseif ($main_tab == 'buildings_admin'): ?>
                    <h2 class="mb-4 screen-only">รายละเอียดคำร้องขอใช้สถานที่สำหรับโครงการ: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <div class="card shadow-sm p-4 admin-details screen-only">
                        <div class="row list-text">
                            <div class="col-md-6">
                                <p><strong>ชื่อโครงการ:</strong> <a href="?main_tab=projects_admin&mode=detail&id=<?php echo htmlspecialchars($detail_item['project_id']); ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($detail_item['project_name']); ?></a></p>
                                <p><strong>ผู้ยื่นคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['user_name'] ?? 'N/A'); ?></p>
                                <p><strong>สถานที่ที่ขอใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['facility_name']); ?></p>
                                <p><strong>สถานะคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                                <p><strong>วันเริ่มต้นการเตรียมการ:</strong> <?php echo formatThaiDate($detail_item['prepare_start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['prepare_end_date'], false); ?></p>
                                <p><strong>ตั้งแต่เวลา:</strong> <?php echo (new DateTime($detail_item['prepare_start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['prepare_end_time']))->format('H:i'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>วันที่สร้างคำร้อง:</strong> <?php echo formatThaiDate($detail_item['request_date']); ?></p>
                                <p><strong>วันเริ่มต้นการใช้งาน:</strong> <?php echo formatThaiDate($detail_item['fr_start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['fr_end_date'], false); ?></p>
                                <p><strong>ตั้งแต่เวลา:</strong> <?php echo (new DateTime($detail_item['start_time']))->format('H:i'); ?> น. ถึง <?php echo (new DateTime($detail_item['end_time']))->format('H:i'); ?></p>
                                <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                                <p class="mt-3">
                                    <strong>สถานะการอนุมัติ:</strong>
                                    <?php
                                    if (isset($detail_item['approve']) && !empty($detail_item['approve'])) {
                                        echo '<span class="badge ' . getStatusBadgeClass($detail_item['writed_status'], $detail_item['approve']) . '">' . htmlspecialchars($detail_item['approve']) . '</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark">รอดำเนินการ</span>';
                                    }
                                    ?>
                                </p>
                                <?php if (isset($detail_item['approve']) && !empty($detail_item['approve'] && $detail_item['approve'] !== 'ยกเลิก')): ?>
                                    <p><strong>วันที่ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['approve_date']) && $detail_item['approve_date'] ? formatThaiDate($detail_item['approve_date']) : 'N/A'); ?></p>
                                    <p><strong>ผู้ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['staff_name']) ? ($detail_item['staff_name'] ?? 'N/A') : 'N/A'); ?></p>
                                    <?php if ($detail_item['approve'] == 'ไม่อนุมัติ'): ?>
                                        <p><strong>รายละเอียดการไม่อนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                    <?php endif; ?>
                                    <?php if ($detail_item['approve'] == 'อนุมัติ' && !empty($detail_item['approve_detail'])): ?>
                                        <p><strong>รายละเอียดการอนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($main_tab == 'equipments_admin'): ?>
                    <h2 class="mb-4 screen-only">รายละเอียดคำร้องขอใช้อุปกรณ์สำหรับโครงการ: <?php echo htmlspecialchars($detail_item['project_name']); ?></h2>
                    <div class="card shadow-sm p-4 admin-details screen-only">
                        <div class="row list-text">
                            <div class="col-md-6">
                                <p><strong>ชื่อโครงการ:</strong> <a href="?main_tab=projects_admin&mode=detail&id=<?php echo htmlspecialchars($detail_item['project_id']); ?>" class="text-decoration-none text-dark"><?php echo htmlspecialchars($detail_item['project_name']); ?></a></p>
                                <p><strong>ผู้ยื่นคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['user_name'] ?? 'N/A'); ?></p>
                                <p><strong>อุปกรณ์ที่ขอใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['equip_name']); ?></p>
                                <p><strong>จำนวน:</strong> <?php echo htmlspecialchars($detail_item['quantity']) . ' ' . htmlspecialchars($detail_item['measure']); ?></p>
                                <p><strong>สถานที่นำอุปกรณ์ไปใช้งาน:</strong> <?php echo htmlspecialchars($detail_item['facility_name'] ?? 'ไม่ระบุ'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>สถานะคำร้อง:</strong> <?php echo htmlspecialchars($detail_item['writed_status']); ?></p>
                                <p><strong>วันที่สร้างคำร้อง:</strong> <?php echo formatThaiDate($detail_item['request_date']); ?></p>
                                <p><strong>ช่วงเวลาใช้งาน:</strong> ตั้งแต่วันที่ <?php echo formatThaiDate($detail_item['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($detail_item['end_date'], false); ?></p>
                                <p><strong>ต้องการขนส่งอุปกรณ์:</strong> <?php echo ($detail_item['transport'] == 1) ? 'ต้องการ' : 'ไม่ต้องการ'; ?></p>
                                <p><strong>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</strong> <?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></p>
                                <p class="mt-3">
                                    <strong>สถานะการอนุมัติ:</strong>
                                    <?php
                                    if (isset($detail_item['approve']) && !empty($detail_item['approve'])) {
                                        echo '<span class="badge ' . getStatusBadgeClass($detail_item['writed_status'], $detail_item['approve']) . '">' . htmlspecialchars($detail_item['approve']) . '</span>';
                                    } else {
                                        echo '<span class="badge bg-warning text-dark">รอดำเนินการ</span>';
                                    }
                                    ?>
                                </p>
                                <?php if (isset($detail_item['approve']) && !empty($detail_item['approve'] && $detail_item['approve'] !== 'ยกเลิก')): ?>
                                    <p><strong>วันที่ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['approve_date']) && $detail_item['approve_date'] ? formatThaiDate($detail_item['approve_date']) : 'N/A'); ?></p>
                                    <p><strong>ผู้ดำเนินการ:</strong> <?php echo htmlspecialchars(isset($detail_item['staff_name']) ? ($detail_item['staff_name'] ?? 'N/A') : 'N/A'); ?></p>
                                    <?php if ($detail_item['approve'] == 'ไม่อนุมัติ'): ?>
                                        <p><strong>รายละเอียดการไม่อนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                    <?php endif; ?>
                                    <?php if ($detail_item['approve'] == 'อนุมัติ' && !empty($detail_item['approve_detail'])): ?>
                                        <p><strong>รายละเอียดการอนุมัติ:</strong> <?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

                <?php if ($main_tab == 'buildings_admin' || $main_tab == 'equipments_admin'): ?>
                    <div class="d-flex justify-content-between mt-4 screen-only">
                        <div>
                            <a href="<?php echo htmlspecialchars($previous) ?: '#'; ?>"
                            class="btn btn-secondary me-2"
                            onclick="if(this.getAttribute('href') === '#'){ history.back(); return false; }">
                                ย้อนกลับ
                            </a>
                            <?php if ($main_tab == 'buildings_admin' && $detail_item): ?>
                                <a href="admin-print-page.php?id=<?php echo htmlspecialchars($detail_item['facility_re_id']); ?>&type=facility" target="_blank" class="btn btn-info me-2">
                                    <i class="bi bi-printer"></i> พิมพ์รายงาน
                                </a>
                            <?php elseif ($main_tab == 'equipments_admin' && $detail_item): ?>
                                <a href="admin-print-page.php?id=<?php echo htmlspecialchars($detail_item['equip_re_id']); ?>&type=equipment" target="_blank" class="btn btn-info me-2">
                                    <i class="bi bi-printer"></i> พิมพ์รายงาน
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-info me-2" onclick="alert('ไม่มีข้อมูลให้พิมพ์ หรือยังไม่รองรับการพิมพ์สำหรับหน้านี้');">
                                    <i class="bi bi-printer"></i> พิมพ์รายงาน
                                </button>
                            <?php endif; ?>
                        </div>
                        <div>
                            <?php
                            if (($main_tab == 'buildings_admin' || $main_tab == 'equipments_admin') &&
                                ($detail_item['approve'] !== 'อนุมัติ' && $detail_item['approve'] !== 'ไม่อนุมัติ' && $detail_item['approve'] !== 'ยกเลิก')):
                            ?>
                                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#approveModal">
                                    อนุมัติ
                                </button>
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    ไม่อนุมัติ
                                </button>

                                <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="" method="POST">
                                                <div class="modal-header bg-success text-white">
                                                    <h5 class="modal-title" id="approveModalLabel">เพิ่มรายละเอียดการอนุมัติ (ไม่บังคับ)</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="approve_detail_optional" class="form-label">รายละเอียดเพิ่มเติม:</label>
                                                        <textarea class="form-control" id="approve_detail_optional" name="approve_detail" rows="3"></textarea>
                                                        <small class="text-muted">คุณสามารถเพิ่มบันทึกเกี่ยวกับการอนุมัติคำร้องนี้ได้ (ไม่บังคับ)</small>
                                                    </div>
                                                    <input type="hidden" name="action" value="approve_request">
                                                    <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($detail_item['facility_re_id'] ?? $detail_item['equip_re_id']); ?>">
                                                    <input type="hidden" name="request_type" value="<?php echo ($main_tab == 'buildings_admin') ? 'facility' : 'equipment'; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                    <button type="submit" class="btn btn-success">ยืนยันอนุมัติ</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form action="" method="POST">
                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title" id="rejectModalLabel">ระบุเหตุผลการไม่อนุมัติ</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label for="reject_reason" class="form-label">เหตุผล:</label>
                                                        <textarea class="form-control" id="reject_reason" name="approve_detail" rows="3" required></textarea>
                                                    </div>
                                                    <input type="hidden" name="action" value="reject_request">
                                                    <input type="hidden" name="request_id" value="<?php echo htmlspecialchars($detail_item['facility_re_id'] ?? $detail_item['equip_re_id']); ?>">
                                                    <input type="hidden" name="request_type" value="<?php echo ($main_tab == 'buildings_admin') ? 'facility' : 'equipment'; ?>">
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                                    <button type="submit" class="btn btn-danger">ยืนยันไม่อนุมัติ</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="./js/admin_menu.js"></script>
<script src="./js/chart.js"></script>
</body>
</html>