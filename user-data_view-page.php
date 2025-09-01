<?php
include 'database/database.php';
require_once 'php/user_data-view.php';

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login-page.php"); 
    exit();
}

$nontri_id = htmlspecialchars($_SESSION['nontri_id'] ?? 'N/A');
$user_THname = htmlspecialchars($_SESSION['user_THname'] ?? 'N/A');
$user_THsur = htmlspecialchars($_SESSION['user_THsur'] ?? 'N/A');
$user_ENname = htmlspecialchars($_SESSION['user_ENname'] ?? 'N/A');
$user_ENsur = htmlspecialchars($_SESSION['user_ENsur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$fa_de_name = htmlspecialchars($_SESSION['fa_de_name'] ?? 'N/A');

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <?php include 'header.php'?>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/th.js'></script>
</head>
<body>
    <nav class="navbar navbar-dark navigator">
        <div class="container-fluid">
            <div class="d-flex align-items-center gap-3">
                <a href="user-main-page.php">
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
                    <span class="navbar-brand mb-0 fs-5 fs-md-4"><?php echo $user_THname . ' ' . $user_THsur; ?></span>
                    <span class="navbar-brand mb-0 fs-6 fs-md-5"><?php echo $user_role; ?></span>
                </div>
                <a href="user-profile-page.php">
                    <img src="./images/user_button.png" class="img-fluid logo" style="width:40px; height:40px; object-fit:cover;" alt="User Button">
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-0">
        <h1 class="mb-3 text-center">ข้อมูลอาคารและอุปกรณ์</h1>

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

        <?php if (isset($_GET['status'])): ?>
            <?php if ($_GET['status'] == 'building_not_found'): ?>
                <div class="alert alert-warning" role="alert">
                    ไม่พบข้อมูลอาคารที่คุณร้องขอ
                </div>
            <?php elseif ($_GET['status'] == 'facility_not_found'): ?>
                <div class="alert alert-warning" role="alert">
                    ไม่พบข้อมูลสิ่งอำนวยความสะดวกที่คุณร้องขอ
                </div>
            <?php elseif ($_GET['status'] == 'equip_not_found'): ?>
                <div class="alert alert-warning" role="alert">
                    ไม่พบข้อมูลอุปกรณ์ที่คุณร้องขอ
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="row mb-3 align-items-center">
            <div class="col-md-6">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($mode == 'buildings' || $mode == 'building_detail' || $mode == 'facility_detail') ? 'active' : ''; ?>" aria-current="page" href="?mode=buildings">
                            <i class="bi bi-building"></i> อาคารทั้งหมด
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($mode == 'equipment' || $mode == 'equip_detail') ? 'active' : ''; ?>" href="?mode=equipment">
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
                    <form class="d-flex" action="" method="GET">
                        <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
                        <?php if ($mode == 'building_detail' && isset($_GET['building_id'])): ?>
                            <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($_GET['building_id']); ?>">
                        <?php endif; ?>
                        <input class="form-control me-2" type="search" placeholder="ค้นหา..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                            <button class="btn btn-outline-success" type="submit">ค้นหา</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="?mode=<?php echo htmlspecialchars($mode); ?><?php echo ($mode == 'building_detail' && isset($_GET['building_id'])) ? '&building_id=' . htmlspecialchars($_GET['building_id']) : ''; ?>" class="btn btn-outline-secondary ms-2">ล้าง</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <?php

        if ($mode == 'facility_detail' && $detail_item):
            $back_link = '?mode=building_detail&building_id=' . htmlspecialchars($detail_item['building_id']);
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
                <div class="d-flex justify-content-start mt-3">
                    <a href="<?php echo htmlspecialchars($back_link); ?>" class="btn btn-secondary">ย้อนกลับ</a>
                </div>
            </div>

        <?php
        elseif ($mode == 'equip_detail' && $detail_item):
            $back_link = '?mode=equipment';
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
                <div class="d-flex justify-content-start mt-3">
                    <a href="<?php echo htmlspecialchars($back_link); ?>" class="btn btn-secondary">ย้อนกลับ</a>
                </div>
            </div>

        <?php

        else:
            if (empty($data)):
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
                        <div class="d-flex justify-content-start mt-3">
                            <a href="?mode=buildings" class="btn btn-secondary">ย้อนกลับไปดูอาคารทั้งหมด</a>
                        </div>
                    </div>
        <?php
                endif;
        ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-3 g-2 mt-0">
                    <?php foreach ($data as $item): ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm">
                                <?php
                                $img_src = './images/placeholder.png';
                                $item_name = '';
                                $link_href = '#';

                                if ($mode == 'buildings') {
                                    $img_src = ($item['building_pic'] && file_exists($item['building_pic'])) ? htmlspecialchars($item['building_pic']) : $img_src;
                                    $item_name = htmlspecialchars($item['building_name']);
                                    $link_href = '?mode=building_detail&building_id=' . htmlspecialchars($item['building_id']);
                                } elseif ($mode == 'equipment') {
                                    $img_src = ($item['equip_pic'] && file_exists($item['equip_pic'])) ? htmlspecialchars($item['equip_pic']) : $img_src;
                                    $item_name = htmlspecialchars($item['equip_name']);
                                    $link_href = '?mode=equip_detail&equip_id=' . htmlspecialchars($item['equip_id']);
                                } elseif ($mode == 'building_detail') {
                                    $img_src = ($item['facility_pic'] && file_exists($item['facility_pic'])) ? htmlspecialchars($item['facility_pic']) : $img_src;
                                    $item_name = htmlspecialchars($item['facility_name']);
                                    $link_href = '?mode=facility_detail&facility_id=' . htmlspecialchars($item['facility_id']);
                                }
                                ?>
                                <a href="<?php echo $link_href; ?>" class="card-img-link">
                                    <img src="<?php echo $img_src; ?>" class="card-img-top" alt="<?php echo $item_name; ?>">
                                </a>
                                <div class="card-body d-flex flex-column">
                                    <?php if ($mode == 'buildings'): ?>
                                        <h5 class="card-title text-center">
                                            อาคาร <?php echo htmlspecialchars($item['building_id']); ?>: <?php echo htmlspecialchars($item['building_name']); ?>
                                        </h5>
                                        <?php if (!empty($search_query) && !empty($item['matched_facilities'])): ?>
                                            <p class="card-text text-success mb-0 small text-center">
                                                <i class="bi bi-search"></i> : <?php echo htmlspecialchars($item['matched_facilities']); ?>
                                            </p>
                                        <?php endif; ?>

                                    <?php else: // สำหรับ mode อื่นๆ ใช้ h5 เดิม ?>
                                        <h5 class="card-title text-center"><?php echo $item_name; ?></h5>
                                    <?php endif; ?>
                                    
                                    <?php if ($mode == 'equipment'): ?>
                                        <p class="card-text text-muted mb-0 small text-center">จำนวน: <?php echo htmlspecialchars($item['quantity'] . ' ' . $item['measure']); ?></p>
                                        <p class="card-text text-muted mb-0 small text-center">ขนาด: <?php echo htmlspecialchars($item['size']); ?></p>
                                    <?php elseif ($mode == 'building_detail'): ?>
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
                            <li class="page-item">
                                <a class="page-link" href="?mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?><?php echo ($mode == 'building_detail' && isset($_GET['building_id'])) ? '&building_id=' . htmlspecialchars($_GET['building_id']) : ''; ?>">ก่อนหน้า</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?><?php echo ($mode == 'building_detail' && isset($_GET['building_id'])) ? '&building_id=' . htmlspecialchars($_GET['building_id']) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?mode=<?php echo htmlspecialchars($mode); ?>&page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?><?php echo ($mode == 'building_detail' && isset($_GET['building_id'])) ? '&building_id=' . htmlspecialchars($_GET['building_id']) : ''; ?>">ถัดไป</a>
                            </li>
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
    <?php
        $conn->close();
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
</body>
</html>