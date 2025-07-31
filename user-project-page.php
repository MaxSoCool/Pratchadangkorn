<?php
session_start();
include 'database/database.php';

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$user_id = htmlspecialchars($_SESSION['user_id'] ?? 'N/A');
$user_THname = htmlspecialchars($_SESSION['user_THname'] ?? 'N/A');
$user_THsur = htmlspecialchars($_SESSION['user_THsur'] ?? 'N/A');
$user_ENname = htmlspecialchars($_SESSION['user_ENname'] ?? 'N/A');
$user_ENsur = htmlspecialchars($_SESSION['user_ENsur'] ?? 'N/A');
$user_role = htmlspecialchars($_SESSION['role'] ?? 'N/A');
$fa_de_name = htmlspecialchars($_SESSION['fa_de_name'] ?? 'N/A');

$items_per_page = 9;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = '%' . $search_query . '%';

$main_tab = 'user_requests'; 

$mode = isset($_GET['mode']) ? $_GET['mode'] : 'projects_list'; 

$activity_types = [];
$result_activity = $conn->query("SELECT activity_type_id, activity_type_name FROM activity_type ORDER BY activity_type_name");
if ($result_activity) {
    while ($row = $result_activity->fetch_assoc()) {
        $activity_types[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($mode == 'projects_create') {
        $project_name = trim($_POST['project_name'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $project_des = trim($_POST['project_des'] ?? '');
        $attendee = (int)($_POST['attendee'] ?? 0);
        $phone_num = trim($_POST['phone_num'] ?? '');
        $advisor_name = trim($_POST['advisor_name'] ?? '');
        $activity_type_id = (int)($_POST['activity_type_id'] ?? 0);
        $nontri_id_for_project = $user_nontri_id; 

        if ($nontri_id_for_project === null || $nontri_id_for_project === 0) {
            $errors[] = "ไม่พบบทบาทในระบบ (ใน/นอก) ของผู้ใช้ กรุณาติดต่อผู้ดูแลระบบ";
        }
        if (empty($project_name)) $errors[] = "กรุณากรอกชื่อโครงการ.";
        if (empty($start_date)) $errors[] = "กรุณาระบุวันเริ่มต้นโครงการ.";
        if (empty($end_date)) $errors[] = "กรุณาระบุวันสิ้นสุดโครงการ.";
        if ($attendee <= 0) $errors[] = "จำนวนผู้เข้าร่วมต้องเป็นตัวเลขบวก.";
        if (empty($phone_num)) $errors[] = "กรุณากรอกหมายเลขโทรศัพท์.";
        if (empty($advisor_name)) $errors[] = "กรุณากรอกชื่อที่ปรึกษา.";
        if ($activity_type_id === 0) $errors[] = "กรุณาเลือกประเภทกิจกรรม.";

        if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
            $errors[] = "วันสิ้นสุดโครงการต้องไม่ก่อนวันเริ่มต้นโครงการ.";
        }

        $file_path = uploadFile('files', $project_files_upload_dir, $errors); // อัปโหลดไฟล์

        if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO projects (project_name, start_date, end_date, project_des, file_path, attendee, phone_num, advisor_name, nontri_id, activity_type_id, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if (!$stmt) {
                $errors[] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            } else {
                $stmt->bind_param("sssssisssis",
                    $project_name, $start_date, $end_date, $project_des, $file_path,
                    $attendee, $phone_num, $advisor_name, $nontri_id_for_project, $activity_type_id, $created_by
                );
                if ($stmt->execute()) {
                    $success_message = "สร้างโครงการสำเร็จแล้ว!";
                    header("Location: ?main_tab=user_requests&mode=projects_list&status=success&message=" . urlencode($success_message));
                    exit();
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการบันทึกโครงการ: " . $stmt->error;
                    if ($file_path && file_exists($file_path)) {
                        unlink($file_path);
                    }
                }
                $stmt->close();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="style.css" rel="stylesheet">
    <title>ข้อมูลคำร้องผู้ใช้ KU FTD</title>
    
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
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="user-main-page.php">ตรวจสอบอุปกรณ์และสถานที่</a>
                <a class="navbar-brand mb-0 fs-5 fs-md-4" href="user-project-page.php">ข้อมูลคำร้อง</a>
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
        <h1 class="mb-3 text-center">ข้อมูลคำร้องของผู้ใช้</h1>

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

        <!-- Modal (for success/failure message) -->
        <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header <?php echo ($modal_status == 'success') ? 'bg-success' : 'bg-danger'; ?> text-white">
                        <h5 class="modal-title" id="statusModalLabel"><?php echo ($modal_status == 'success') ? 'สำเร็จ!' : 'ข้อผิดพลาด!'; ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo htmlspecialchars($modal_message); ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn <?php echo ($modal_status == 'success') ? 'btn-success' : 'btn-danger'; ?>" data-bs-dismiss="modal">ตกลง</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ส่วนของข้อมูลคำร้องของผู้ใช้ -->
        <div class="row mb-3 align-items-center">
            <div class="col-md-6">
                <ul class="nav nav-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($mode == 'projects_list' || $mode == 'projects_create') ? 'active' : ''; ?>" aria-current="page" href="?main_tab=user_requests&mode=projects_list">
                            <i class="bi bi-folder"></i> โครงการ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($mode == 'buildings_requests_list') ? 'active' : ''; ?>" href="?main_tab=user_requests&mode=buildings_requests_list">
                            <i class="bi bi-building"></i> อาคารและสถานที่
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($mode == 'equipment_requests_list') ? 'active' : ''; ?>" href="?main_tab=user_requests&mode=equipment_requests_list">
                            <i class="bi bi-tools"></i> อุปกรณ์
                        </a>
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <form class="d-flex" action="" method="GET">
                    <input type="hidden" name="main_tab" value="user_requests">
                    <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
                    <input class="form-control me-2" type="search" placeholder="ค้นหาโครงการ..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <button class="btn btn-outline-success" type="submit">ค้นหา</button>
                    <?php if (!empty($search_query)): ?>
                        <a href="?main_tab=user_requests&mode=<?php echo htmlspecialchars($mode); ?>" class="btn btn-outline-secondary ms-2">ล้าง</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <?php if ($mode == 'projects_list'): ?>
            <div class="d-grid mb-3">
                <a href="?main_tab=user_requests&mode=projects_create" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> สร้างโครงการใหม่
                </a>
            </div>

            <?php if (empty($data)): ?>
                <div class="alert alert-info text-center mt-4">
                    <?php if (!empty($search_query)): ?>
                        ไม่พบโครงการที่คุณค้นหา "<?php echo htmlspecialchars($search_query); ?>"
                    <?php else: ?>
                        test
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 g-3">
                    <?php foreach ($data as $project): ?>
                        <div class="col">
                            <div class="card shadow-sm p-3">
                                <div class="row g-0">
                                    <div class="col-md-9">
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($project['project_name']); ?></h5>
                                        <p class="card-text small mb-1">
                                            <strong>ช่วงเวลา:</strong> <?php echo htmlspecialchars($project['start_date']) . ' ถึง ' . htmlspecialchars($project['end_date']); ?>
                                        </p>
                                        <p class="card-text small mb-1">
                                            <strong>จำนวนผู้เข้าร่วม:</strong> <?php echo htmlspecialchars($project['attendee']); ?>
                                        </p>
                                        <p class="card-text small mb-1">
                                            <strong>ประเภทระบบ:</strong> <?php echo htmlspecialchars($project['nontri_name'] ?? 'ไม่ระบุ'); ?>
                                        </p>
                                        <p class="card-text small mb-1">
                                            <strong>ประเภทกิจกรรม:</strong> <?php echo htmlspecialchars($project['activity_type_name'] ?? 'ไม่ระบุ'); ?>
                                        </p>
                                        <p class="card-text small mb-1">
                                            <strong>รายละเอียด:</strong> <?php echo htmlspecialchars(mb_strimwidth($project['project_des'], 0, 150, "...")); ?>
                                        </p>
                                        <p class="card-text small mb-1 text-muted">สร้างเมื่อ: <?php echo htmlspecialchars($project['created_at']); ?></p>
                                    </div>
                                    <div class="col-md-3 d-flex flex-column justify-content-center align-items-end">
                                        <?php if ($project['file_path'] && file_exists($project['file_path'])): ?>
                                            <a href="<?php echo htmlspecialchars($project['file_path']); ?>" target="_blank" class="btn btn-outline-info btn-sm mb-2">
                                                <i class="bi bi-file-earmark-arrow-down"></i> ดูไฟล์
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-secondary btn-sm" disabled>แก้ไข/ยกเลิก (ยังไม่เปิดใช้งาน)</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <nav aria-label="Page navigation" class="pagination-container mt-3 mb-0">
                    <ul class="pagination pagination-lg">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?main_tab=user_requests&mode=projects_list&page=<?php echo $current_page - 1; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">ก่อนหน้า</a>
                            </li>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="?main_tab=user_requests&mode=projects_list&page=<?php echo $i; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                            <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?main_tab=user_requests&mode=projects_list&page=<?php echo $current_page + 1; ?><?php echo !empty($search_query) ? '&search=' . htmlspecialchars($search_query) : ''; ?>">ถัดไป</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php elseif ($mode == 'projects_create'): ?>
            <div class="form-section my-4">
                <h2 class="mb-4 text-center">สร้างโครงการใหม่</h2>
                <form action="?main_tab=user_requests&mode=projects_create" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="project_name" class="form-label">ชื่อโครงการ:</label>
                        <input type="text" class="form-control" id="project_name" name="project_name" value="<?php echo htmlspecialchars($_POST['project_name'] ?? ''); ?>" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">วันเริ่มต้น:</label>
                            <input type="text" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">วันสิ้นสุด:</label>
                            <input type="text" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="project_des" class="form-label">รายละเอียดของโครงการ:</label>
                        <textarea class="form-control" id="project_des" name="project_des" rows="5"><?php echo htmlspecialchars($_POST['project_des'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="files" class="form-label">ไฟล์แนบ (รูปภาพ, PDF, Doc, XLS):</label>
                        <input type="file" class="form-control" id="files" name="files" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="attendee" class="form-label">จำนวนผู้เข้าร่วม:</label>
                            <input type="text" class="form-control" id="attendee" name="attendee" min="1" value="<?php echo htmlspecialchars($_POST['attendee'] ?? '1'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone_num" class="form-label">หมายเลขโทรศัพท์:</label>
                            <input type="text" class="form-control" id="phone_num" name="phone_num" value="<?php echo htmlspecialchars($_POST['phone_num'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="advisor_name" class="form-label">ชื่อที่ปรึกษาโครงการ:</label>
                        <input type="text" class="form-control" id="advisor_name" name="advisor_name" value="<?php echo htmlspecialchars($_POST['advisor_name'] ?? ''); ?>" required>
                    </div>
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
                        <?php if (empty($activity_types)): ?>
                            <p class="text-danger mt-2">ยังไม่มีข้อมูลประเภทกิจกรรมในระบบ กรุณาเพิ่มข้อมูลก่อน</p>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="?main_tab=user_requests&mode=projects_list" class="btn btn-secondary">ย้อนกลับ</a>
                        <button type="submit" class="btn btn-success">บันทึกโครงการ</button>
                    </div>
                </form>
            </div>

        <?php elseif ($mode == 'buildings_requests_list'): ?>
            <div class="alert alert-info text-center mt-4">
                <h4 class="alert-heading">test</h4>
            </div>
        <?php elseif ($mode == 'equipment_requests_list'): ?>
            <div class="alert alert-info text-center mt-4">
                <h4 class="alert-heading">test</h4>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

</body>
</html>