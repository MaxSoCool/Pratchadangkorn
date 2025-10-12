<?php
$project_root = dirname(dirname(__FILE__));


// โหมดต่าง ๆ ของ admin
$is_admin_form_mode = in_array($mode, ['add_building', 'add_facility', 'add_equipment', 'edit_building', 'edit_facility', 'edit_equipment']);

if ($is_admin_form_mode && $is_admin): ?>
        <!-- หน้าการจัดการอาคาร (admin) -->
        <?php if ($mode == 'add_building'): ?>
            <div class="card p-5 my-3 mt-5">
                <h2 class="mb-4 text-center fw-bold fs-5">สร้างอาคารใหม่</h2>
                <form id="addBuildingForm" action="php/admin-bfe-injection.php" method="POST" enctype="multipart/form-data" class="list-text">
                    <input type="hidden" name="inject_type" value="building">
                    <div class="mb-3"><label for="building_id" class="form-label fw-bold">หมายเลขอาคาร:</label><input type="text" id="building_id" name="building_id" class="form-control" value="<?php echo htmlspecialchars($_POST['building_id'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label for="building_name" class="form-label fw-bold">ชื่ออาคาร:</label><input type="text" id="building_name" name="building_name" class="form-control" value="<?php echo htmlspecialchars($_POST['building_name'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label for="building_pic" class="form-label fw-bold">รูปภาพ:</label><input type="file" id="building_pic" name="building_pic" class="form-control" accept="image/*"></div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" id="submitAddBuilding" class="btn btn-primary btn-lg flex-fill">บันทึก</button>
                        <a href="admin-data_view-page.php?mode=buildings" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a>
                    </div>
                </form>
            </div>
        <?php elseif ($mode == 'edit_building' && $detail_item): ?>
            <div class="card p-5 my-3 mt-5">
                <h2 class="mb-4 text-center fw-bold fs-5">แก้ไขอาคาร: <?php echo htmlspecialchars($detail_item['building_name']); ?></h2>
                <form id="editBuildingForm" action="php/admin-bfe-injection.php" method="POST" enctype="multipart/form-data" class="list-text">
                    <input type="hidden" name="inject_type" value="update_building">
                    <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($detail_item['building_id']); ?>">
                    <input type="hidden" name="old_building_pic" value="<?php echo htmlspecialchars($detail_item['building_pic'] ?? ''); ?>">

                    <div class="mb-3"><label for="building_id_display" class="form-label fw-bold">หมายเลขอาคาร:</label><input type="text" id="building_id_display" class="form-control" value="<?php echo htmlspecialchars($detail_item['building_id']); ?>" readonly></div>
                    <div class="mb-3"><label for="building_name" class="form-label fw-bold">ชื่ออาคาร:</label><input type="text" id="building_name" name="building_name" class="form-control" value="<?php echo htmlspecialchars($detail_item['building_name'] ?? ''); ?>" required></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">รูปภาพปัจจุบัน:</label><br>
                        <?php
                        $current_pic_path = $detail_item['building_pic'] ?? '';
                        if ($current_pic_path && file_exists($project_root . '/' . $current_pic_path)): ?>
                            <img src="<?php echo htmlspecialchars($current_pic_path); ?>" class="img-thumbnail mb-2" style="max-width: 200px;" alt="Current Building Picture">
                        <?php else: ?>
                            <p>ไม่มีรูปภาพ</p>
                        <?php endif; ?>
                        <label for="building_pic" class="form-label fw-bold">เปลี่ยนรูปภาพ (เลือกไฟล์ใหม่หากต้องการเปลี่ยน):</label>
                        <input type="file" id="building_pic" name="building_pic" class="form-control" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label for="status_building" class="form-label fw-bold">สถานะอาคาร:</label>
                        <div class="form-check form-switch">
                            <input type="hidden" name="available" value="no">
                            <input class="form-check-input" type="checkbox" id="status_building" name="available" value="yes" role="switch"
                                data-item-type="อาคาร"
                                data-item-name="<?php echo htmlspecialchars($detail_item['building_name']); ?>"
                                <?php echo (isset($detail_item['available']) && $detail_item['available'] === 'yes') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status_building"><?php echo (isset($detail_item['available']) && $detail_item['available'] === 'yes') ? 'เปิด (พร้อมใช้งาน)' : 'ปิด (ไม่พร้อมใช้งาน)'; ?></label>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-primary btn-lg flex-fill">บันทึกการแก้ไข</button><a href="admin-data_view-page.php?mode=building_detail&building_id=<?php echo htmlspecialchars($detail_item['building_id']); ?>" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a></div>
                </form>
            </div>

        <!-- หน้าการจัดการสถานที่ (admin) -->
        <?php elseif ($mode == 'add_facility'): ?>
            <div class="card p-5 my-3 mt-5">
                <h2 class="mb-4 text-center fw-bold fs-5">สร้างสถานที่ใหม่</h2>
                <form id="addFacilityForm" action="php/admin-bfe-injection.php" method="POST" enctype="multipart/form-data" class="list-text">
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
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" id="submitAddFacility" class="btn btn-primary btn-lg flex-fill">บันทึก</button>
                        <a href="admin-data_view-page.php?mode=building_detail&building_id=<?php echo htmlspecialchars($_GET['building_id'] ?? ''); ?>" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a>
                    </div>
                </form>
            </div>
        <?php elseif ($mode == 'edit_facility' && $detail_item): ?>
            <div class="card p-5 my-3 mt-5">
                <h2 class="mb-4 text-center fw-bold fs-5">แก้ไขสถานที่: <?php echo htmlspecialchars($detail_item['facility_name']); ?></h2>
                <form id="editFacilityForm" action="php/admin-bfe-injection.php" method="POST" enctype="multipart/form-data" class="list-text">
                    <input type="hidden" name="inject_type" value="update_facility">
                    <input type="hidden" name="facility_id" value="<?php echo htmlspecialchars($detail_item['facility_id']); ?>">
                    <input type="hidden" name="building_id_original" value="<?php echo htmlspecialchars($detail_item['building_id']); ?>">
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
                        <?php
                        $current_pic_path = $detail_item['facility_pic'] ?? '';
                        if ($current_pic_path && file_exists($project_root . '/' . $current_pic_path)): ?>
                            <img src="<?php echo htmlspecialchars($current_pic_path); ?>" class="img-thumbnail mb-2" style="max-width: 200px;" alt="Current Facility Picture">
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
                    <div class="mb-3">
                        <label for="available_facility" class="form-label fw-bold">สถานะสถานที่:</label>
                        <div class="form-check form-switch">
                            <input type="hidden" name="available" value="no">
                            <input class="form-check-input" type="checkbox" id="available_facility" name="available" value="yes" role="switch"
                                data-item-type="สถานที่"
                                data-item-name="<?php echo htmlspecialchars($detail_item['facility_name']); ?>"
                                <?php echo (isset($detail_item['available']) && $detail_item['available'] === 'yes') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="available_facility"><?php echo (isset($detail_item['available']) && $detail_item['available'] === 'yes') ? 'เปิด (พร้อมใช้งาน)' : 'ปิด (ไม่พร้อมใช้งาน)'; ?></label>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg flex-fill">บันทึกการแก้ไข</button>
                        <a href="admin-data_view-page.php?mode=building_detail&building_id=<?php echo htmlspecialchars($detail_item['building_id'] ?? ''); ?>" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a>
                    </div>
                </form>
            </div>

        <!-- หน้าการจัดการอุปกรณ์ (admin) -->
        <?php elseif ($mode == 'add_equipment'): ?>
            <div class="card p-5 my-3 mt-5">
                <h2 class="mb-4 text-center fw-bold fs-5">เพิ่มอุปกรณ์ใหม่</h2>
                <form id="addEquipmentForm" action="php/admin-bfe-injection.php" method="POST" enctype="multipart/form-data" class="list-text">
                    <input type="hidden" name="inject_type" value="equipment">
                    <div class="mb-3"><label for="equip_name" class="form-label fw-bold">ชื่ออุปกรณ์:</label><input type="text" id="equip_name" name="equip_name" class="form-control" value="<?php echo htmlspecialchars($_POST['equip_name'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label for="quantity" class="form-label fw-bold">จำนวน:</label><input type="number" id="quantity" name="quantity" class="form-control" value="<?php echo htmlspecialchars($_POST['quantity'] ?? '1'); ?>" min="1" required></div>
                    <div class="mb-3"><label for="measure" class="form-label fw-bold">หน่วยวัด:</label><input type="text" id="measure" name="measure" class="form-control" value="<?php echo htmlspecialchars($_POST['measure'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="size" class="form-label fw-bold">ขนาด:</label><input type="text" id="size" name="size" class="form-control" value="<?php echo htmlspecialchars($_POST['size'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="equip_pic" class="form-label fw-bold">รูปภาพอุปกรณ์:</label><input type="file" id="equip_pic" name="equip_pic" class="form-control" accept="image/*"></div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" id="submitAddEquipment" class="btn btn-primary btn-lg flex-fill">บันทึก</button>
                        <a href="admin-data_view-page.php?mode=equipment" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a>
                    </div>
                </form>
            </div>
        <?php elseif ($mode == 'edit_equipment' && $detail_item): ?>
            <div class="card p-5 my-3 mt-5">
                <h2 class="mb-4 text-center fw-bold fs-5">แก้ไขอุปกรณ์: <?php echo htmlspecialchars($detail_item['equip_name']); ?></h2>
                <form id="editEquipmentForm" action="php/admin-bfe-injection.php" method="POST" enctype="multipart/form-data" class="list-text">
                    <input type="hidden" name="inject_type" value="update_equipment">
                    <input type="hidden" name="equip_id" value="<?php echo htmlspecialchars($detail_item['equip_id']); ?>">
                    <input type="hidden" name="old_equip_pic" value="<?php echo htmlspecialchars($detail_item['equip_pic'] ?? ''); ?>">

                    <div class="mb-3"><label for="equip_name" class="form-label fw-bold">ชื่ออุปกรณ์:</label><input type="text" id="equip_name" name="equip_name" class="form-control" value="<?php echo htmlspecialchars($detail_item['equip_name'] ?? ''); ?>" required></div>
                    <div class="mb-3"><label for="quantity" class="form-label fw-bold">จำนวน:</label><input type="number" id="quantity" name="quantity" class="form-control" value="<?php echo htmlspecialchars($detail_item['quantity'] ?? '1'); ?>" min="1" required></div>
                    <div class="mb-3"><label for="measure" class="form-label fw-bold">หน่วยวัด:</label><input type="text" id="measure" name="measure" class="form-control" value="<?php echo htmlspecialchars($detail_item['measure'] ?? ''); ?>"></div>
                    <div class="mb-3"><label for="size" class="form-label fw-bold">ขนาด:</label><input type="text" id="size" name="size" class="form-control" value="<?php echo htmlspecialchars($detail_item['size'] ?? ''); ?>"></div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">รูปภาพปัจจุบัน:</label><br>
                        <?php
                        $current_pic_path = $detail_item['equip_pic'] ?? '';
                        if ($current_pic_path && file_exists($project_root . '/' . $current_pic_path)): ?>
                            <img src="<?php echo htmlspecialchars($current_pic_path); ?>" class="img-thumbnail mb-2" style="max-width: 200px;" alt="Current Equipment Picture">
                        <?php else: ?>
                            <p>ไม่มีรูปภาพ</p>
                        <?php endif; ?>
                        <label for="equip_pic" class="form-label fw-bold">เปลี่ยนรูปภาพ (เลือกไฟล์ใหม่หากต้องการเปลี่ยน):</label>
                        <input type="file" id="equip_pic" name="equip_pic" class="form-control" accept="image/*">
                    </div>
                    <div class="mb-3">
                        <label for="available_equip" class="form-label fw-bold">สถานะอุปกรณ์:</label>
                        <div class="form-check form-switch">
                            <input type="hidden" name="available" value="no">
                            <input class="form-check-input" type="checkbox" id="available_equip" name="available" value="yes" role="switch"
                                data-item-type="อุปกรณ์"
                                data-item-name="<?php echo htmlspecialchars($detail_item['equip_name']); ?>"
                                <?php echo (isset($detail_item['available']) && $detail_item['available'] === 'yes') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="available_equip"><?php echo (isset($detail_item['available']) && $detail_item['available'] === 'yes') ? 'เปิด (พร้อมใช้งาน)' : 'ปิด (ไม่พร้อมใช้งาน)'; ?></label>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4"><button type="submit" class="btn btn-primary btn-lg flex-fill">บันทึกการแก้ไข</button><a href="admin-data_view-page.php?mode=equipment" class="btn btn-secondary btn-lg flex-fill">ยกเลิก</a></div>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php else: // === ส่วนแสดงรายการข้อมูลปกติ (เมื่อ $mode ไม่ใช่ add_... หรือ edit_...) === ?>
    <h1 class="mb-3 text-center"><?php echo $is_admin ? 'การจัดการ' : 'ข้อมูล'; ?>อาคาร สถานที่และอุปกรณ์</h1>
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

    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <?php
                        $tab_link_prefix = $is_admin ? 'admin-data_view-page.php' : ($is_logged_in ? 'user-data_view-page.php' : 'index.php');
                    ?>
                    <a class="nav-link <?php echo ($mode == 'buildings' || $mode == 'building_detail' || $mode == 'facility_detail') ? 'active' : ''; ?>" aria-current="page" href="<?php echo $tab_link_prefix; ?>?mode=buildings">
                        <i class="bi bi-building"></i> อาคารทั้งหมด
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($mode == 'equipment' || $mode == 'equip_detail') ? 'active' : ''; ?>" href="<?php echo $tab_link_prefix; ?>?mode=equipment">
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

                <form class="d-flex" action="<?php echo $tab_link_prefix; ?>" method="GET">
                    <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
                    <?php if ($mode == 'building_detail' && isset($_GET['building_id'])): ?>
                        <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($_GET['building_id']); ?>">
                    <?php endif; ?>
                    <input class="form-control me-2" type="search" placeholder="ค้นหา..." aria-label="Search" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <button class="btn btn-outline-success" type="submit">ค้นหา</button>
                </form>
            </div>
            <div class="d-flex justify-content-end mt-2">
                <!-- การค้นหาข้อมูล -->
                <form class="d-flex" action="<?php echo $tab_link_prefix; ?>" method="GET">
                    <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
                    <?php if ($mode == 'building_detail' && isset($_GET['building_id'])): ?>
                        <input type="hidden" name="building_id" value="<?php echo htmlspecialchars($_GET['building_id']); ?>">
                    <?php endif; ?>
                    <?php if (!empty($search_query)): ?>
                        <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php endif; ?>

                    <select class="form-select me-2" name="available_filter" onchange="this.form.submit()">
                        <option value="all" <?php echo ($available_filter == 'all') ? 'selected' : ''; ?>>ทั้งหมด</option>
                        <option value="yes" <?php echo ($available_filter == 'yes') ? 'selected' : ''; ?>>พร้อมให้บริการ</option>
                        <option value="no" <?php echo ($available_filter == 'no') ? 'selected' : ''; ?>>ไม่พร้อมให้บริการ</option>
                    </select>
                    <?php if (!empty($search_query) || ($available_filter != $default_available_filter)): ?>
                        <a href="<?php echo $tab_link_prefix; ?>?mode=<?php echo htmlspecialchars($mode); ?><?php echo ($mode == 'building_detail' && isset($_GET['building_id'])) ? '&building_id=' . htmlspecialchars($_GET['building_id']) : ''; ?>" class="btn btn-secondary ms-2">ล้าง</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <?php

    // หน้ารายละเอียดสถานที่
    if ($mode == 'facility_detail' && $detail_item):
        $back_link_prefix = $is_admin ? 'admin-data_view-page.php' : ($is_logged_in ? 'user-data_view-page.php' : 'index.php');
        $back_link = $back_link_prefix . '?mode=building_detail&building_id=' . htmlspecialchars($detail_item['building_id']);
    ?>
        <div class="card p-3 my-3">
            <h2 class="card-title mb-3">รายละเอียดสถานที่: <?php echo htmlspecialchars($detail_item['facility_name']); ?></h2>
              <div class="row">
                <div class="col-12 col-md-4 text-center">
                    <?php
                    $current_pic_path = $detail_item['facility_pic'] ?? '';
                    if ($current_pic_path && file_exists($project_root . '/' . $current_pic_path)): ?>
                        <img src="<?php echo htmlspecialchars($current_pic_path); ?>" class="detail-img img-fluid" alt="Facility Picture">
                    <?php else: ?>
                        <img src="./images/placeholder.png" class="detail-img img-fluid" alt="No Image">
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-8 details-text">
                    <p class="mb-1"><strong>ชื่อสถานที่:</strong> <?php echo htmlspecialchars($detail_item['facility_name']); ?></p>
                    <p class="mb-1"><strong>รายละเอียด:</strong> <?php echo nl2br(htmlspecialchars($detail_item['facility_des'])); ?></p>
                    <p class="mb-1"><strong>อาคาร:</strong> <?php echo htmlspecialchars($detail_item['building_id']); ?></p>
                    <p class="mb-1"><strong>ชื่ออาคาร:</strong> <?php echo htmlspecialchars($detail_item['building_name']); ?></p>
                    <p class="mb-1"><strong>สถานะ:</strong> <?php echo ($detail_item['available'] === 'yes') ? '<span class="text-success">พร้อมใช้งาน</span>' : '<span class="text-danger">ไม่พร้อมใช้งาน</span>'; ?></p>
                </div>
            </div>
            <div class="col">
                <div class="d-flex gap-2 mt-3">
                    <a href="<?php echo htmlspecialchars($back_link); ?>" class="btn btn-secondary">ย้อนกลับ</a>
                    <?php if ($is_admin): ?>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php

    // หน้ารายละเอียดอุปกรณ์
    elseif ($mode == 'equip_detail' && $detail_item):
        $back_link_prefix = $is_admin ? 'admin-data_view-page.php' : ($is_logged_in ? 'user-data_view-page.php' : 'index.php');
        $back_link = $back_link_prefix . '?mode=equipment';
    ?>
        <div class="card p-3 my-3">
            <h2 class="card-title mb-3">รายละเอียดอุปกรณ์: <?php echo htmlspecialchars($detail_item['equip_name']); ?></h2>
            <div class="row">
                <div class="col-12 col-md-4 text-center">
                    <?php
                    $current_pic_path = $detail_item['equip_pic'] ?? '';
                    if ($current_pic_path && file_exists($project_root . '/' . $current_pic_path)): ?>
                        <img src="<?php echo htmlspecialchars($current_pic_path); ?>" class="detail-img img-fluid" alt="Equipment Picture">
                    <?php else: ?>
                        <img src="./images/placeholder.png" class="detail-img img-fluid" alt="No Image">
                    <?php endif; ?>
                </div>
                <div class="col-12 col-md-8 details-text">
                    <p class="mb-1"><strong>ชื่ออุปกรณ์:</strong> <?php echo htmlspecialchars($detail_item['equip_name']); ?></p>
                    <!-- แก้ไข $item เป็น $detail_item ตรงนี้ -->
                    <p class="mb-1"><strong>จำนวน:</strong> <?php echo htmlspecialchars($detail_item['quantity']); ?></p>
                    <p class="mb-1"><strong>หน่วยวัด:</strong> <?php echo htmlspecialchars($detail_item['measure']); ?></p>
                    <p class="mb-1"><strong>ขนาด:</strong> <?php echo htmlspecialchars($detail_item['size']); ?></p>
                    <p class="mb-1"><strong>สถานะ:</strong> <?php echo ($detail_item['available'] === 'yes') ? '<span class="text-success">พร้อมใช้งาน</span>' : '<span class="text-danger">ไม่พร้อมใช้งาน</span>'; ?></p>
                </div>
            </div>
            <div class="col">
                <div class="d-flex gap-2 mt-3">
                    <a href="<?php echo htmlspecialchars($back_link); ?>" class="btn btn-secondary">ย้อนกลับ</a>
                    <?php if ($is_admin): // Admin-specific buttons ?>
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
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php
    else: // หน้ารายละเอียดอาคาร
        if ($mode == 'building_detail' && $detail_item):
            $building_detail_card_class = ($detail_item['available'] == 'no') ? 'unavailable' : '';
            $back_to_buildings_prefix = $is_admin ? 'admin-data_view-page.php' : ($is_logged_in ? 'user-data_view-page.php' : 'index.php');
    ?>
            <div class="card p-3 my-3 bg-light <?php echo $building_detail_card_class; ?>">
                <div class="d-flex align-items-center">
                    <?php
                    $current_pic_path = $detail_item['building_pic'] ?? '';
                    if ($current_pic_path && file_exists($project_root . '/' . $current_pic_path)): ?>
                        <img src="<?php echo htmlspecialchars($current_pic_path); ?>" alt="Building Pic" class="img-thumbnail me-3" style="width: 70px; height: 70px; object-fit: cover;">
                    <?php else: ?>
                        <img src="./images/placeholder.png" class="img-thumbnail me-3" alt="No Image">
                    <?php endif; ?>
                    <div>
                        <h3 class="mb-0 fs-5 details-text">สถานที่ภายในอาคาร: <?php echo htmlspecialchars($detail_item['building_name']); ?></h3>
                        <small class="text-muted details-text">คลิกที่รูปภาพสถานที่เพื่อดูรายละเอียด</small><br>
                        <small class="text-muted details-text">สถานะอาคาร: <?php echo ($detail_item['available'] === 'yes') ? '<span class="text-success">พร้อมใช้งาน</span>' : '<span class="text-danger">ไม่พร้อมใช้งาน</span>'; ?></small>
                    </div>
                </div>
                <div class="col">
                    <div class="d-flex gap-2 mt-3">
                        <a href="<?php echo $back_to_buildings_prefix; ?>?mode=buildings" class="btn btn-secondary">ย้อนกลับไปดูอาคารทั้งหมด</a>
                        <?php if ($is_admin): ?>
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
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php
        endif;

        // ข้อความเมื่อไม่มีข้อมูลให้แสดง
        if (empty($data) && !$show_add_card && ($mode != 'building_detail' || ($mode == 'building_detail' && count($data) == 0))): // แก้ไข total_items เป็น count($data)
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
            // Card สำหรับเพิ่มสถานที่ใหม่ (เมื่ออยู่ใน building_detail)
            if ($mode == 'building_detail' && empty($search_query) && $detail_item && $available_filter == 'all' && $is_admin):
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

            <?php
            // Card สำหรับเพิ่มอาคาร/อุปกรณ์ใหม่ (เมื่ออยู่ในหน้าหลักของ buildings/equipment)
            if ($show_add_card && empty($search_query) && $available_filter == 'all' && $is_admin): ?>
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
                <?php
                // กำหนดค่าเพื่อส่งไปยังการจัดการ
                $item_name = '';
                $link_href = '#';
                $item_id = '';
                $item_type = '';
                $redirect_building_id = ''; // สำหรับส่งกลับไปยัง building_id เมื่ออยู่ในโหมด building_detail

                $img_src_from_db = ''; // ตัวแปรเก็บภาพจากฐานข้อมูล
                if ($mode == 'buildings') {
                    $img_src_from_db = $item['building_pic'] ?? '';
                    $item_name = 'อาคาร ' . htmlspecialchars($item['building_id']) . ': ' . htmlspecialchars($item['building_name']);
                } elseif ($mode == 'equipment') {
                    $img_src_from_db = $item['equip_pic'] ?? '';
                    $item_name = htmlspecialchars($item['equip_name']);
                } elseif ($mode == 'building_detail') { // แสดง Facilities
                    $img_src_from_db = $item['facility_pic'] ?? '';
                    $item_name = htmlspecialchars($item['facility_name']);
                }

                // กำหนด final_img_src ที่จะใช้ใน src ของ <img>
                $final_img_src = './images/placeholder.png'; // Default placeholder
                if ($img_src_from_db && file_exists($project_root . '/' . $img_src_from_db)) {
                    $final_img_src = htmlspecialchars($img_src_from_db); // ใช้ Web Relative Path ใน src
                }

                $card_class = ($item['available'] == 'no') ? 'unavailable' : '';

                $page_prefix = '';
                if ($is_admin) {
                    $page_prefix = 'admin-data_view-page.php';
                } elseif ($is_logged_in) {
                    $page_prefix = 'user-data_view-page.php';
                } else {
                    $page_prefix = 'index.php';
                }


                if ($mode == 'buildings') {
                    $link_href = $page_prefix . '?mode=building_detail&building_id=' . htmlspecialchars($item['building_id']);
                    $item_id = $item['building_id'];
                    $item_type = 'building';
                } elseif ($mode == 'equipment') {
                    $link_href = $page_prefix . '?mode=equip_detail&equip_id=' . htmlspecialchars($item['equip_id']);
                    $item_id = $item['equip_id'];
                    $item_type = 'equipment';
                } elseif ($mode == 'building_detail') { // แสดง Facilities
                    $link_href = $page_prefix . '?mode=facility_detail&facility_id=' . htmlspecialchars($item['facility_id']);
                    $item_id = $item['facility_id'];
                    $item_type = 'facility';
                    $redirect_building_id = htmlspecialchars($_GET['building_id'] ?? '');
                }
                ?>
                <div class="col">
                    <div class="card h-100 shadow-sm <?php echo $card_class; ?>">
                        <a href="<?php echo $link_href; ?>" class="card-img-link">
                            <img src="<?php echo $final_img_src; ?>" class="card-img-top" alt="<?php echo $item_name; ?>">
                        </a>

                        <!-- เมื่อค้นหาแล้วพบข้อมูล -->
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
                            <?php elseif ($mode == 'building_detail'): // แสดง Facilities ?>
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
                <?php
                $pagination_base_url_prefix = $is_admin ? 'admin-data_view-page.php' : ($is_logged_in ? 'user-data_view-page.php' : 'index.php');
                $pagination_base_url = $pagination_base_url_prefix . "?mode=" . htmlspecialchars($mode) . "&search=" . urlencode($search_query) . "&available_filter=" . urlencode($available_filter);
                if ($mode == 'building_detail' && isset($_GET['building_id'])) {
                    $pagination_base_url .= '&building_id=' . htmlspecialchars($_GET['building_id']);
                }
                ?>
                <?php if ($current_page > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?php echo $pagination_base_url . '&page=' . ($current_page - 1); ?>">ก่อนหน้า</a></li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>"><a class="page-link" href="<?php echo $pagination_base_url . '&page=' . $i; ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                <?php if ($current_page < $total_pages): ?>
                    <li class="page-item"><a class="page-link" href="<?php echo $pagination_base_url . '&page=' . ($current_page + 1); ?>">ถัดไป</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif;?>
<?php endif;?>

<?php
if ($mode == 'buildings' || $mode == 'building_detail') {
    include __DIR__ . '/calendar.php';
}
?>

<?php if ($is_admin): // หน้า Modal สำหรับการเพ่ม ลบ แก้ไข อาคารสถานที่และอุปกรณ์ ?>
    <!-- Modal หลัก -->
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

    <!-- Modal ยืนยันการลบอาคารสถานที่และอุปกรณ์ -->
    <div class="modal fade" id="deleteConfirmationModal" tabindex="-1" aria-labelledby="deleteConfirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content list-text">
                <form id="deleteForm" action="php/admin-bfe-injection.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger" id="deleteConfirmationModalLabel"><i class="bi bi-exclamation-triangle-fill"></i> ยืนยันการลบ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center" id="deleteModalMessage">
                        <p>คุณแน่ใจหรือไม่ที่ต้องการลบข้อมูลนี้? การดำเนินการนี้ไม่สามารถย้อนกลับได้</p>
                    </div>
                    <div class="modal-footer d-flex justify-content-center">
                        <input type="hidden" name="inject_type" id="deleteItemType">
                        <input type="hidden" name="delete_id" id="deleteItemId">
                        <input type="hidden" name="building_id_for_redirect" id="redirectBuildingId">
                        <button type="submit" class="btn btn-danger">ยืนยันการลบ</button>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>