<?php
if (!isset($conn)) {
    include dirname(__DIR__) . '/database/database.php';
}
if (!function_exists('uploadFile')) { 
    include 'helpers.php';
}

$project_files_upload_dir = 'uploads/files/';
if (!is_dir($project_files_upload_dir)) {
    mkdir($project_files_upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($mode == 'projects_create') {
        $project_name = trim($_POST['project_name'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $project_des = trim($_POST['project_des'] ?? '');
        $attendee = (int)($_POST['attendee'] ?? 0);
        $phone_num = trim($_POST['phone_num'] ?? '');

        $advisor_name = null;
        if ($user_role === 'นิสิต') { 
            $advisor_name = trim($_POST['advisor_name'] ?? '');
            if (empty($advisor_name)) {
                $errors[] = "กรุณากรอกชื่อที่ปรึกษาโครงการ";
            }
        }

        $activity_type_id = (int)($_POST['activity_type_id'] ?? 0);
        $nontri_id = $_SESSION['nontri_id'] ?? ''; 

        $writed_status = 'ร่างโครงการ';
        if (isset($_POST['action']) && $_POST['action'] == 'submit_project') {
            $writed_status = 'ส่งโครงการ';
        }

        if (empty($project_name)) $errors[] = "กรุณากรอกชื่อโครงการ";
        if (empty($start_date)) $errors[] = "กรุณาระบุวันเริ่มต้นโครงการ";
        if (empty($end_date)) $errors[] = "กรุณาระบุวันสิ้นสุดโครงการ";
        if ($attendee <= 0) $errors[] = "กรุณาจำนวนผู้เข้าร่วมให้ถูกต้อง";
        if (empty($phone_num)) $errors[] = "กรุณากรอกหมายเลขโทรศัพท์";
        if ($activity_type_id === 0) $errors[] = "กรุณาเลือกประเภทกิจกรรม";

        if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
            $errors[] = "วันสุดท้ายของโครงการห้ามสิ้นสุดก่อนวันเริ่มต้นของโครงการ.";
        }

        $uploaded_paths = handleMultipleFileUploads('files', $project_files_upload_dir, $errors);
        $file_paths_json = json_encode($uploaded_paths);

         if (empty($errors)) {
            $stmt = $conn->prepare("INSERT INTO project (project_name, start_date, end_date, project_des, files, attendee, phone_num, advisor_name, nontri_id, activity_type_id, writed_status, created_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                header("Location: ?main_tab=user_requests&mode=projects_create&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error));
                exit();
            } else {
                $stmt->bind_param("sssssisssis",
                    $project_name, $start_date, $end_date, $project_des, $file_paths_json,
                    $attendee, $phone_num, $advisor_name, $nontri_id, $activity_type_id, $writed_status
                );
                if ($stmt->execute()) {
                    $new_project_id = $conn->insert_id;
                    $success_message = "สร้างโครงการสำเร็จแล้ว!";
                    header("Location: ?main_tab=user_requests&mode=projects_detail&project_id=" . $new_project_id . "&status=success&message=" . urlencode($success_message));
                    exit();
                } else {
                    foreach ($uploaded_paths as $path) { if (file_exists($path)) { unlink($path); } } // Clean up uploaded files
                    header("Location: ?main_tab=user_requests&mode=projects_create&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการบันทึกโครงการ: " . $stmt->error));
                    exit();
                }
                $stmt->close();
            }
        }
    } elseif ($mode == 'buildings_create') {
        $prepare_start_time = trim($_POST['prepare_start_time'] ?? '');
        $prepare_end_time = trim( $_POST['prepare_end_time'] ?? '');
        $prepare_start_date = trim($_POST['prepare_start_date'] ?? '');
        $prepare_end_date = trim($_POST['prepare_end_date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $agree = isset($_POST['agree']) ? 1 : 0;
        $facility_id = (int)($_POST['facility_id']) ?? 0;
        $project_id = (int)($_POST['project_id']) ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        $writed_status = 'ร่างคำร้องขอ';
        if (isset($_POST['action']) && $_POST['action'] == 'submit_building') {
            $writed_status = 'ส่งคำร้องขอ';
        }

        if (empty($prepare_start_time)) $errors[] = "กรุณากรอกเวลาเริ่มต้นของวันเตรียมการ";
        if (empty($prepare_end_time)) $errors[] = "กรุณากรอกเวลาสิ้นสุดของวันเตรียมการ";
        if (empty($prepare_start_date)) $errors[] = "กรุณากรอกวันที่เริ่มต้นของวันเตรียมการ";
        if (empty($prepare_end_date)) $errors[] = "กรุณากรอกวันที่สิ้นสุดเของวันเตรียมการ";
        if (empty($start_time)) $errors[] = "กรุณากรอกเวลาเริ่มต้นของวันใช้การ";
        if (empty($end_time)) $errors[] = "กรุณากรอกเวลาสิ้นสุดของวันใช้การ";
        if (empty($start_date)) $errors[] = "กรุณากรอกวันที่เริ่มต้นใช้การ";
        if (empty($end_date)) $errors[] = "กรุณากรอกวันที่สิ้นสุดใช้การ";
        if (empty($facility_id)) $errors[] = "กรุณาเลือกสถานที่ที่ต้องการขอใช้";
        if (empty($project_id)) $errors[] = "กรุณาเลือกโครงการของท่านที่ต้องการขอใช้สถานที่";

        $today = date('Y-m-d');
        if (!empty($prepare_start_date) && $prepare_start_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของวันเตรียมการก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($prepare_end_date) && $prepare_end_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของวันเตรียมการก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($start_date) && $start_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($end_date) && $end_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($prepare_start_date) && !empty($prepare_end_date) && $prepare_start_date > $prepare_end_date) {
            $errors[] = "วันสุดท้ายของวันเตรียมการห้ามสิ้นสุดก่อนวันเริ่มต้นของวันเตรียมการ";
        } elseif (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
            $errors[] = "วันสุดท้ายของวันใช้การห้ามสิ้นสุดก่อนวันเริ่มต้นของวันใช้การ";
        } elseif (!empty($prepare_end_date) && $prepare_end_date > $start_date && (strtotime($prepare_start_date) < strtotime($start_date))) {
            $errors[] = "วันสิ้นสุดของวันเตรียมการต้องไม่เกินวันเริ่มต้นของวันใช้การ.";
        } elseif (!empty($prepare_start_time) && !empty($prepare_end_time) && $prepare_start_time > $prepare_end_time) {
            $errors[] = "เวลาสิ้นสุดของวันเตรียมการห้ามสิ้นสุดก่อนเวลาเริ่มต้นของวันเตรียมการ";
        } elseif (!empty($start_time) && !empty($end_time) && $start_time > $end_time) {
            $errors[] = "เวลาสิ้นสุดของวันใช้การห้ามสิ้นสุดก่อนเวลาเริ่มต้นของวันใช้การ";
        }

        $approval_day = strtotime('+3 days');
        $pre_start_ts = strtotime($prepare_start_date);
        $start_ts    = strtotime($start_date);

        if (empty($errors) && ( ($writed_status == 'ร่างคำร้องขอ') || ($pre_start_ts >= $approval_day && $start_ts >= $approval_day) ) ) {
            if (!validateRequestDatesAgainstProject($start_date, $end_date, $project_id, $conn, $nontri_id, $errors, 'สถานที่ (ใช้งาน)')) {
            }
            if (!empty($prepare_start_date) && empty($errors)) {
                if (!validateRequestDatesAgainstProject($prepare_start_date, $prepare_end_date, $project_id, $conn, $nontri_id, $errors, 'สถานที่ (เตรียมการ)')) {
                }
            }

            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO facilities_requests
                    (prepare_start_time, prepare_end_time, prepare_start_date, prepare_end_date,
                    start_time, end_time, start_date, end_date, agree, facility_id, project_id, writed_status, request_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");

                if (!$stmt) {
                    header("Location: ?main_tab=user_requests&mode=buildings_create&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error));
                    exit();
                } else {
                    $stmt->bind_param("ssssssssiiis",
                        $prepare_start_time, $prepare_end_time, $prepare_start_date, $prepare_end_date,
                        $start_time, $end_time, $start_date, $end_date,
                        $agree, $facility_id, $project_id, $writed_status
                    );
                    if ($stmt->execute()) {
                        $new_building_id = $conn->insert_id;
                        $success_message = "ส่งคำร้องขอสถานที่สำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=buildings_detail&facility_re_id="
                            . $new_building_id . "&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        header("Location: ?main_tab=user_requests&mode=buildings_create&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการบันทึกคำร้อง: " . $stmt->error));
                        exit();
                    }
                    $stmt->close();
                }
            }
        } else if (empty($errors)){
            header("Location: ?main_tab=user_requests&mode=buildings_create&status=error&message=" . urlencode("ขออภัย คำร้องขอใช้อาคารสถานที่ต้องดำเนินการล่วงหน้าอย่างน้อย 3 วันก่อนวันเริ่มต้นการใช้งาน"));
            exit();
        }

    } elseif ($mode == 'equipments_create') {
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $agree = isset($_POST['agree']) ? 1 : 0;
        $transport = isset($_POST['transport']) ? 1 : 0;
        $quantity = (int)($_POST['quantity'] ?? 0);
        $equip_id = (int)($_POST['equip_id']) ?? 0;
        $facility_id = (int)($_POST['facility_id']) ?? 0;
        $project_id = (int)($_POST['project_id']) ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        $writed_status = 'ร่างคำร้องขอ';
        if (isset($_POST['action']) && $_POST['action'] == 'submit_equipment') {
            $writed_status = 'ส่งคำร้องขอ';
        }

        if (empty($start_date)) $errors[] = "กรุณากรอกวันที่เริ่มต้นใช้การ";
        if (empty($end_date)) $errors[] = "กรุณากรอกวันที่สิ้นสุดใช้การ";
        if ($quantity <= 0) $errors[] = "กรุณาระบุจำนวนอุปกรณ์ที่ต้องการขอใช้";
        if (empty($equip_id)) $errors[] = "กรุณาเลือกอุปกรณ์ที่ต้องการขอใช้";
        if (empty($project_id)) $errors[] = "กรุณาเลือกโครงการของท่านที่ต้องการขอใช้อุปกรณ์";

        $today = date('Y-m-d');
        if (!empty($start_date) && $start_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($end_date) && $end_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
            $errors[] = "วันสุดท้ายของการขอใช้อุปกรณ์ห้ามสิ้นสุดก่อนวันเริ่มต้นขอใช้อุปกรณ์.";
        }

        $approval_day = strtotime('+3 days');
        $start_ts    = strtotime($start_date);

        if (empty($errors) && ( ($writed_status == 'ร่างคำร้องขอ') || ($start_ts >= $approval_day) ) ) {
            if (!validateRequestDatesAgainstProject($start_date, $end_date, $project_id, $conn, $nontri_id, $errors, 'อุปกรณ์')) {
            }

            if (empty($errors)) {
                $stmt = $conn->prepare("INSERT INTO equipments_requests (start_date, end_date, agree, transport, quantity, equip_id, facility_id, project_id, writed_status, request_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                if (!$stmt) {
                    header("Location: ?main_tab=user_requests&mode=equipments_create&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error));
                    exit();
                } else {
                    $stmt->bind_param("ssiiiiiis",
                    $start_date, $end_date, $agree, $transport, $quantity,
                    $equip_id, $facility_id, $project_id, $writed_status
                    );
                    if ($stmt->execute()) {
                        $new_equip_id = $conn->insert_id;
                        $success_message = "ส่งคำร้องขอใช้อุปกรณ์สำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=equipments_detail&equip_re_id=" . $new_equip_id . "&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        header("Location: ?main_tab=user_requests&mode=equipments_create&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการบันทึกคำร้อง: " . $stmt->error));
                        exit();
                    }
                    $stmt->close();
                }
            }
        } else if (empty($errors)){
            header("Location: ?main_tab=user_requests&mode=equipments_create&status=error&message=" . urlencode("ขออภัย คำร้องขอใช้อุปกรณ์ต้องดำเนินการล่วงหน้าอย่างน้อย 3 วันก่อนวันเริ่มต้นการใช้งาน"));
            exit();
        }
    } elseif ($mode == 'projects_edit') {
            $project_id = (int)$_POST['project_id'] ?? 0;
            $project_name = trim($_POST['project_name'] ?? '');
            $start_date = trim($_POST['start_date'] ?? '');
            $end_date = trim($_POST['end_date'] ?? '');
            $project_des = trim($_POST['project_des'] ?? '');
            $attendee = (int)($_POST['attendee'] ?? 0);
            $phone_num = trim($_POST['phone_num'] ?? '');

            $advisor_name = null;
            if ($user_role === 'นิสิต') {
                $advisor_name = trim($_POST['advisor_name'] ?? '');
                if (empty($advisor_name)) {
                    $errors[] = "กรุณากรอกชื่อที่ปรึกษาโครงการ";
                }
            } else {
                $advisor_name = null;
            }

            $activity_type_id = (int)($_POST['activity_type_id'] ?? 0);
            $nontri_id = $_SESSION['nontri_id'] ?? '';

            $writed_status = 'ร่างโครงการ';
            if (isset($_POST['action']) && $_POST['action'] == 'submit_project_edit') {
                $current_status_sql = "SELECT writed_status FROM project WHERE project_id = ? AND nontri_id = ?";
                $stmt_status = $conn->prepare($current_status_sql);
                if ($stmt_status) {
                    $stmt_status->bind_param("is", $project_id, $nontri_id);
                    $stmt_status->execute();
                    $stmt_status->bind_result($current_project_status);
                    $stmt_status->fetch();
                    $stmt_status->close();

                    if ($current_project_status == 'ร่างโครงการ' || $current_project_status == 'ส่งโครงการ') {
                        $writed_status = 'ส่งโครงการ';
                    } else {
                        $writed_status = $current_project_status;
                    }
                } else {
                    $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบสถานะโครงการปัจจุบัน: " . $conn->error;
                }
            } elseif (isset($_POST['action']) && $_POST['action'] == 'save_draft_edit') {
                $writed_status = 'ร่างโครงการ';
            }

            if (empty($project_name)) $errors[] = "กรุณากรอกชื่อโครงการ";
            if (empty($start_date)) $errors[] = "กรุณาระบุวันเริ่มต้นโครงการ";
            if (empty($end_date)) $errors[] = "กรุณาระบุวันสิ้นสุดโครงการ";
            if ($attendee <= 0) $errors[] = "กรุณาจำนวนผู้เข้าร่วมให้ถูกต้อง";
            if (empty($phone_num)) $errors[] = "กรุณากรอกหมายเลขโทรศัพท์";
            if ($activity_type_id === 0) $errors[] = "กรุณาเลือกประเภทกิจกรรม";

            $today = date('Y-m-d');
            if (!empty($start_date) && $start_date < $today) {
                $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของโครงการก่อนวันที่ในปัจจุบันได้";
            } elseif (!empty($end_date) && $end_date < $today) {
                $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของโครงการก่อนวันที่ในปัจจุบันได้";
            } elseif (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
                $errors[] = "วันสุดท้ายของโครงการห้ามสิ้นสุดก่อนวันเริ่มต้นของโครงการ.";
            }

            $original_files_json_from_db = '';
            $stmt_get_original_files = $conn->prepare("SELECT files FROM project WHERE project_id = ? AND nontri_id = ?");
            if($stmt_get_original_files) {
                $stmt_get_original_files->bind_param("is", $project_id, $_SESSION['nontri_id']);
                $stmt_get_original_files->execute();
                $stmt_get_original_files->bind_result($original_files_json_from_db);
                $stmt_get_original_files->fetch();
                $stmt_get_original_files->close();
            } else {
                 $errors[] = "เกิดข้อผิดพลาดในการดึงข้อมูลไฟล์เก่า: " . $conn->error;
            }
            $original_files_array_from_db = json_decode($original_files_json_from_db, true) ?: [];

            $retained_file_paths_from_post = $_POST['existing_file_paths_retained'] ?? [];

            $files_to_actually_delete = array_diff($original_files_array_from_db, $retained_file_paths_from_post);
            foreach ($files_to_actually_delete as $file_path) {
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            $new_uploaded_paths = [];
            if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                $new_uploaded_paths = handleMultipleFileUploads('files', $project_files_upload_dir, $errors);
            }

            $final_file_paths_array = array_merge($retained_file_paths_from_post, $new_uploaded_paths);
            $file_paths_json = json_encode(array_values($final_file_paths_array));

            if (empty($errors)) {
                $stmt = $conn->prepare("UPDATE project SET project_name = ?, start_date = ?, end_date = ?, project_des = ?, files = ?, attendee = ?, phone_num = ?, advisor_name = ?, activity_type_id = ?, writed_status = ? WHERE project_id = ? AND nontri_id = ?");
                if (!$stmt) {
                    header("Location: ?main_tab=user_requests&mode=projects_edit&project_id=" . $project_id . "&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับการอัปเดต: " . $conn->error));
                    exit();
                } else {
                    $stmt->bind_param("sssssissssis",
                        $project_name, $start_date, $end_date, $project_des, $file_paths_json,
                        $attendee, $phone_num, $advisor_name, $activity_type_id, $writed_status,
                        $project_id, $nontri_id
                    );
                    if ($stmt->execute()) {
                        $success_message = "แก้ไขโครงการสำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=projects_detail&project_id=" . $project_id . "&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        foreach ($new_uploaded_paths as $path) { if (file_exists($path)) { unlink($path); } }
                        header("Location: ?main_tab=user_requests&mode=projects_edit&project_id=" . $project_id . "&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการบันทึกการแก้ไขโครงการ: " . $stmt->error));
                        exit();
                    }
                    $stmt->close();
                }
            }
    } elseif ($mode == 'buildings_edit') {
        $facility_re_id = (int)$_POST['facility_re_id'] ?? 0;
        $project_id = (int)$_POST['project_id'] ?? 0;
        $prepare_start_time = trim($_POST['prepare_start_time'] ?? '');
        $prepare_end_time = trim($_POST['prepare_end_time'] ?? '');
        $prepare_start_date = trim($_POST['prepare_start_date'] ?? '');
        $prepare_end_date = trim($_POST['prepare_end_date'] ?? '');
        $start_time = trim($_POST['start_time'] ?? '');
        $end_time = trim($_POST['end_time'] ?? '');
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $agree = isset($_POST['agree']) ? 1 : 0;
        $facility_id = (int)($_POST['facility_id']) ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        $writed_status = 'ร่างคำร้องขอ';
        if (isset($_POST['action']) && $_POST['action'] == 'submit_building_edit') {
            $current_status_sql = "SELECT fr.writed_status FROM facilities_requests fr JOIN project p ON fr.project_id = p.project_id WHERE fr.facility_re_id = ? AND p.nontri_id = ?";
            $stmt_status = $conn->prepare($current_status_sql);
            if ($stmt_status) {
                $stmt_status->bind_param("is", $facility_re_id, $nontri_id);
                $stmt_status->execute();
                $stmt_status->bind_result($current_request_status);
                $stmt_status->fetch();
                $stmt_status->close();

                if ($current_request_status == 'ร่างคำร้องขอ' || $current_request_status == 'ส่งคำร้องขอ') {
                    $writed_status = 'ส่งคำร้องขอ';
                } else {
                    $writed_status = $current_request_status;
                }
            } else {
                 $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบสถานะคำร้องขอสถานที่ปัจจุบัน: " . $conn->error;
            }
        } elseif (isset($_POST['action']) && $_POST['action'] == 'save_draft_building_edit') {
            $writed_status = 'ร่างคำร้องขอ';
        }

        if (empty($prepare_start_time)) $errors[] = "กรุณากรอกเวลาเริ่มต้นของวันเตรียมการ";
        if (empty($prepare_end_time)) $errors[] = "กรุณากรอกเวลาสิ้นสุดของวันเตรียมการ";
        if (empty($prepare_start_date)) $errors[] = "กรุณากรอกวันที่เริ่มต้นของวันเตรียมการ";
        if (empty($prepare_end_date)) $errors[] = "กรุณากรอกวันที่สิ้นสุดเของวันเตรียมการ";
        if (empty($start_time)) $errors[] = "กรุณากรอกเวลาเริ่มต้นของวันใช้การ";
        if (empty($end_time)) $errors[] = "กรุณากรอกเวลาสิ้นสุดของวันใช้การ";
        if (empty($start_date)) $errors[] = "กรุณากรอกวันที่เริ่มต้นใช้การ";
        if (empty($end_date)) $errors[] = "กรุณากรอกวันที่สิ้นสุดใช้การ";
        if (empty($facility_id)) $errors[] = "กรุณาเลือกสถานที่ที่ต้องการขอใช้";
        if (empty($project_id)) $errors[] = "กรุณาเลือกโครงการของท่านที่ต้องการขอใช้สถานที่";

        $today = date('Y-m-d');
        if (!empty($prepare_start_date) && $prepare_start_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของวันเตรียมการก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($prepare_end_date) && $prepare_end_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของวันเตรียมการก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($start_date) && $start_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($end_date) && $end_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($prepare_start_date) && !empty($prepare_end_date) && $prepare_start_date > $prepare_end_date) {
            $errors[] = "วันสุดท้ายของวันเตรียมการห้ามสิ้นสุดก่อนวันเริ่มต้นของวันเตรียมการ";
        } elseif (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
            $errors[] = "วันสุดท้ายของวันใช้การห้ามสิ้นสุดก่อนวันเริ่มต้นของวันใช้การ";
        } elseif (!empty($prepare_end_date) && $prepare_end_date > $start_date && (strtotime($prepare_start_date) < strtotime($start_date))) {
             $errors[] = "วันสิ้นสุดของวันเตรียมการต้องไม่เกินวันเริ่มต้นของวันใช้การ.";
        } elseif (!empty($prepare_start_time) && !empty($prepare_end_time) && $prepare_start_time > $prepare_end_time) {
            $errors[] = "เวลาสิ้นสุดของวันเตรียมการห้ามสิ้นสุดก่อนเวลาเริ่มต้นของวันเตรียมการ";
        } elseif (!empty($start_time) && !empty($end_time) && $start_time > $end_time) {
            $errors[] = "เวลาสิ้นสุดของวันใช้การห้ามสิ้นสุดก่อนเวลาเริ่มต้นของวันใช้การ";
        }

        $approval_day_ts = strtotime('+3 days');
        $pre_start_ts = strtotime($prepare_start_date);
        $start_ts    = strtotime($start_date);

        if (empty($errors) && ( ($writed_status == 'ร่างคำร้องขอ') || ($pre_start_ts >= $approval_day_ts && $start_ts >= $approval_day_ts) ) ) {
            $check_project_owner_sql = "SELECT nontri_id FROM project WHERE project_id = ?";
            $stmt_proj_owner = $conn->prepare($check_project_owner_sql);
            if (!$stmt_proj_owner) {
                header("Location: ?main_tab=user_requests&mode=buildings_edit&facility_re_id=" . $facility_re_id . "&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการตรวจสอบเจ้าของโครงการ: " . $conn->error));
                exit();
            }
            $stmt_proj_owner->bind_param("i", $project_id);
            $stmt_proj_owner->execute();
            $stmt_proj_owner->bind_result($project_owner_nontri_id);
            $stmt_proj_owner->fetch();
            $stmt_proj_owner->close();

            if ($project_owner_nontri_id === $nontri_id) {
                if (!validateRequestDatesAgainstProject($start_date, $end_date, $project_id, $conn, $nontri_id, $errors, 'สถานที่ (ใช้งาน)')) {
                }
                if (!empty($prepare_start_date) && empty($errors)) {
                    if (!validateRequestDatesAgainstProject($prepare_start_date, $prepare_end_date, $project_id, $conn, $nontri_id, $errors, 'สถานที่ (เตรียมการ)')) {
                    }
                }

                if (empty($errors)) {
                    $stmt = $conn->prepare("UPDATE facilities_requests SET
                        prepare_start_time = ?, prepare_end_time = ?, prepare_start_date = ?, prepare_end_date = ?,
                        start_time = ?, end_time = ?, start_date = ?, end_date = ?, agree = ?, facility_id = ?,
                        project_id = ?, writed_status = ? WHERE facility_re_id = ?");

                    if (!$stmt) {
                        header("Location: ?main_tab=user_requests&mode=buildings_edit&facility_re_id=" . $facility_re_id . "&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับการอัปเดต: " . $conn->error));
                        exit();
                    } else {
                        $stmt->bind_param("ssssssssiiisi",
                            $prepare_start_time, $prepare_end_time, $prepare_start_date, $prepare_end_date,
                            $start_time, $end_time, $start_date, $end_date,
                            $agree, $facility_id, $project_id, $writed_status, $facility_re_id
                        );

                        if ($stmt->execute()) {
                            $success_message = "แก้ไขคำร้องขอสถานที่สำเร็จแล้ว!";
                            header("Location: ?main_tab=user_requests&mode=buildings_detail&facility_re_id="
                                . $facility_re_id . "&status=success&message=" . urlencode($success_message));
                            exit();
                        } else {
                            header("Location: ?main_tab=user_requests&mode=buildings_edit&facility_re_id=" . $facility_re_id . "&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการบันทึกการแก้ไขคำร้อง: " . $stmt->error));
                            exit();
                        }
                        $stmt->close();
                    }
                }
            } else {
                $errors[] = "คุณไม่มีสิทธิ์แก้ไขคำร้องขอสถานที่นี้ (ไม่เป็นเจ้าของโครงการที่เกี่ยวข้อง).";
            }
        } else if (empty($errors)) {
            header("Location: ?main_tab=user_requests&mode=buildings_edit&facility_re_id=" . $facility_re_id . "&status=error&message=" . urlencode("ขออภัย คำร้องขอใช้อาคารสถานที่ต้องดำเนินการล่วงหน้าอย่างน้อย 3 วันก่อนวันเริ่มต้นการใช้งาน"));
            exit();
        }
    } elseif ($mode == 'equipments_edit') {
        $equip_re_id = (int)$_POST['equip_re_id'] ?? 0;
        $project_id = (int)$_POST['project_id'] ?? 0;
        $start_date = trim($_POST['start_date'] ?? '');
        $end_date = trim($_POST['end_date'] ?? '');
        $agree = isset($_POST['agree']) ? 1 : 0;
        $transport = isset($_POST['transport']) ? 1 : 0;
        $quantity = (int)($_POST['quantity'] ?? 0);
        $equip_id = (int)($_POST['equip_id']) ?? 0;
        $facility_id = (int)($_POST['facility_id']) ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        $writed_status = 'ร่างคำร้องขอ';
        if (isset($_POST['action']) && $_POST['action'] == 'submit_equipment_edit') {
            $current_status_sql = "SELECT er.writed_status FROM equipments_requests er JOIN project p ON er.project_id = p.project_id WHERE er.equip_re_id = ? AND p.nontri_id = ?";
            $stmt_status = $conn->prepare($current_status_sql);
            if ($stmt_status) {
                $stmt_status->bind_param("is", $equip_re_id, $nontri_id);
                $stmt_status->execute();
                $stmt_status->bind_result($current_request_status);
                $stmt_status->fetch();
                $stmt_status->close();

                if ($current_request_status == 'ร่างคำร้องขอ' || $current_request_status == 'ส่งคำร้องขอ') {
                    $writed_status = 'ส่งคำร้องขอ';
                } else {
                    $writed_status = $current_request_status;
                }
            } else {
                $errors[] = "เกิดข้อผิดพลาดในการตรวจสอบสถานะคำร้องขออุปกรณ์ปัจจุบัน: " . $conn->error;
            }
        } elseif (isset($_POST['action']) && $_POST['action'] == 'save_draft_equipment_edit') {
            $writed_status = 'ร่างคำร้องขอ';
        }

        if (empty($start_date)) $errors[] = "กรุณากรอกวันที่เริ่มต้นใช้การ";
        if (empty($end_date)) $errors[] = "กรุณากรอกวันที่สิ้นสุดใช้การ";
        if ($quantity <= 0) $errors[] = "กรุณาระบุจำนวนอุปกรณ์ที่ต้องการขอใช้";
        if (empty($equip_id)) $errors[] = "กรุณาเลือกอุปกรณ์ที่ต้องการขอใช้";
        if (empty($project_id)) $errors[] = "กรุณาเลือกโครงการของท่านที่ต้องการขอใช้อุปกรณ์";

        $today = date('Y-m-d');
        if (!empty($start_date) && $start_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันเริ่มต้นของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($end_date) && $end_date < $today) {
            $errors[] = "ไม่สามารถเลือกวันสิ้นสุดของวันใช้การก่อนวันที่ในปัจจุบันได้";
        } elseif (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
            $errors[] = "วันสุดท้ายของการขอใช้อุปกรณ์ห้ามสิ้นสุดก่อนวันเริ่มต้นขอใช้อุปกรณ์.";
        }

        $approval_day_ts = strtotime('+3 days');
        $start_ts    = strtotime($start_date);

        if (empty($errors) && ($writed_status == 'อนุมัติ' || $writed_status == 'ไม่อนุมัติ' || ($writed_status == 'ร่างคำร้องขอ') || ($start_ts >= $approval_day_ts) ) ) {
            $check_project_owner_sql = "SELECT nontri_id FROM project WHERE project_id = ?";
            $stmt_proj_owner = $conn->prepare($check_project_owner_sql);
            if (!$stmt_proj_owner) {
                header("Location: ?main_tab=user_requests&mode=equipments_edit&equip_re_id=" . $equip_re_id . "&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการตรวจสอบเจ้าของโครงการ: " . $conn->error));
                exit();
            }
            $stmt_proj_owner->bind_param("i", $project_id);
            $stmt_proj_owner->execute();
            $stmt_proj_owner->bind_result($project_owner_nontri_id);
            $stmt_proj_owner->fetch();
            $stmt_proj_owner->close();

            if ($project_owner_nontri_id === $nontri_id) {
                if (!validateRequestDatesAgainstProject($start_date, $end_date, $project_id, $conn, $nontri_id, $errors, 'อุปกรณ์')) {
                }

                if (empty($errors)) {
                    $stmt = $conn->prepare("UPDATE equipments_requests SET
                        start_date = ?, end_date = ?, agree = ?, transport = ?, quantity = ?,
                        equip_id = ?, facility_id = ?, project_id = ?, writed_status = ? WHERE equip_re_id = ?");

                    if (!$stmt) {
                        header("Location: ?main_tab=user_requests&mode=equipments_edit&equip_re_id=" . $equip_re_id . "&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับการอัปเดต: " . $conn->error));
                        exit();
                    } else {
                        $stmt->bind_param("ssiiiiiisi",
                            $start_date, $end_date, $agree, $transport, $quantity,
                            $equip_id, $facility_id, $project_id, $writed_status, $equip_re_id
                        );

                        if ($stmt->execute()) {
                            $success_message = "แก้ไขคำร้องขอใช้อุปกรณ์สำเร็จแล้ว!";
                            header("Location: ?main_tab=user_requests&mode=equipments_detail&equip_re_id="
                                . $equip_re_id . "&status=success&message=" . urlencode($success_message));
                            exit();
                        } else {
                            header("Location: ?main_tab=user_requests&mode=equipments_edit&equip_re_id=" . $equip_re_id . "&status=error&message=" . urlencode("เกิดข้อผิดผิดพลาดในการบันทึกการแก้ไขคำร้อง: " . $stmt->error));
                            exit();
                        }
                        $stmt->close();
                    }
                }
            } else {
                $errors[] = "คุณไม่มีสิทธิ์แก้ไขคำร้องขออุปกรณ์นี้ (ไม่เป็นเจ้าของโครงการที่เกี่ยวข้อง).";
            }
        } else if (empty($errors)){
            header("Location: ?main_tab=user_requests&mode=equipments_edit&equip_re_id=" . $equip_re_id . "&status=error&message=" . urlencode("ขออภัย คำร้องขอใช้อุปกรณ์ต้องดำเนินการล่วงหน้าอย่างน้อย 3 วันก่อนวันเริ่มต้นการใช้งาน"));
            exit();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_project') {
        $project_id = (int)$_POST['project_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        if ($project_id > 0) {
            $current_status_sql = "SELECT writed_status, files FROM project WHERE project_id = ? AND nontri_id = ?";
            $stmt_status = $conn->prepare($current_status_sql);
            if (!$stmt_status) {
                header("Location: ?main_tab=user_requests&mode=projects_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับตรวจสอบสถานะ: " . $conn->error));
                exit();
            }
            $stmt_status->bind_param("is", $project_id, $nontri_id);
            $stmt_status->execute();
            $stmt_status->bind_result($current_project_status, $files_json_from_db);
            $stmt_status->fetch();
            $stmt_status->close();

            if ($current_project_status === 'ร่างโครงการ') {
                $conn->begin_transaction();
                try {
                    $files_to_unlink = json_decode($files_json_from_db, true) ?: [];

                    $stmt_del_fr = $conn->prepare("DELETE FROM facilities_requests WHERE project_id = ?");
                    if (!$stmt_del_fr) { throw new mysqli_sql_exception("Failed to prepare facilities_requests delete statement: " . $conn->error); }
                    $stmt_del_fr->bind_param("i", $project_id);
                    $stmt_del_fr->execute();
                    $stmt_del_fr->close();

                    $stmt_del_er = $conn->prepare("DELETE FROM equipments_requests WHERE project_id = ?");
                    if (!$stmt_del_er) { throw new mysqli_sql_exception("Failed to prepare equipments_requests delete statement: " . $conn->error); }
                    $stmt_del_er->bind_param("i", $project_id);
                    $stmt_del_er->execute();
                    $stmt_del_er->close();

                    $stmt_del_p = $conn->prepare("DELETE FROM project WHERE project_id = ? AND nontri_id = ?");
                    if (!$stmt_del_p) { throw new mysqli_sql_exception("Failed to prepare project delete statement: " . $conn->error); }
                    $stmt_del_p->bind_param("is", $project_id, $nontri_id);
                    if ($stmt_del_p->execute()) {
                        if ($stmt_del_p->affected_rows > 0) {
                            foreach ($files_to_unlink as $file_path) {
                                if (file_exists($file_path)) {
                                    unlink($file_path);
                                }
                            }
                            $conn->commit();
                            $success_message = "ลบโครงการสำเร็จแล้ว!";
                            header("Location: ?main_tab=user_requests&mode=projects_list&status=success&message=" . urlencode($success_message));
                            exit();
                        } else {
                            $conn->rollback();
                            header("Location: ?main_tab=user_requests&mode=projects_list&status=error&message=" . urlencode("ไม่พบโครงการที่คุณต้องการลบ หรือคุณไม่มีสิทธิ์ลบโครงการนี้."));
                            exit();
                        }
                    } else {
                        $conn->rollback();
                        header("Location: ?main_tab=user_requests&mode=projects_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการลบโครงการ: " . $stmt_del_p->error));
                        exit();
                    }
                    $stmt_del_p->close();

                } catch (mysqli_sql_exception $e) {
                    $conn->rollback();
                    header("Location: ?main_tab=user_requests&mode=projects_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการลบโครงการและข้อมูลที่เกี่ยวข้อง: " . $e->getMessage()));
                    exit();
                }
            } else {
                header("Location: ?main_tab=user_requests&mode=projects_detail&project_id=" . $project_id . "&status=error&message=" . urlencode("ไม่สามารถลบโครงการที่ไม่ใช่ร่างโครงการได้ หากต้องการยกเลิกโปรดใช้ปุ่ม 'ยกเลิกโครงการ'."));
                exit();
            }
        } else {
            header("Location: ?main_tab=user_requests&mode=projects_list&status=error&message=" . urlencode("ไม่พบรหัสโครงการที่ถูกต้อง."));
            exit();
        }

    } elseif (isset($_POST['action']) && $_POST['action'] == 'cancel_project') {
        $project_id = (int)$_POST['project_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        if ($project_id > 0) {
            $conn->begin_transaction();
            try {
                $current_status_sql = "SELECT writed_status FROM project WHERE project_id = ? AND nontri_id = ?";
                $stmt_status = $conn->prepare($current_status_sql);
                if (!$stmt_status) {
                    header("Location: ?main_tab=user_requests&mode=projects_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับตรวจสอบสถานะ: " . $conn->error));
                    exit();
                }
                $stmt_status->bind_param("is", $project_id, $nontri_id);
                $stmt_status->execute();
                $stmt_status->bind_result($current_project_status);
                $stmt_status->fetch();
                $stmt_status->close();

                if ($current_project_status !== 'เริ่มดำเนินการ' && $current_project_status !== 'สิ้นสุดโครงการ' && $current_project_status !== 'ยกเลิกโครงการ') {
                    $stmt_can_fr = $conn->prepare("UPDATE facilities_requests SET writed_status = 'ยกเลิกคำร้องขอ', approve = 'ยกเลิก' WHERE project_id = ? AND writed_status NOT IN ('เริ่มดำเนินการ', 'สิ้นสุดดำเนินการ', 'ยกเลิกคำร้องขอ') AND approve IS NULL");
                    if (!$stmt_can_fr) { throw new mysqli_sql_exception("Failed to prepare facilities_requests cancel statement: " . $conn->error); }
                    $stmt_can_fr->bind_param("i", $project_id);
                    $stmt_can_fr->execute();
                    $stmt_can_fr->close();

                    $stmt_can_er = $conn->prepare("UPDATE equipments_requests SET writed_status = 'ยกเลิกคำร้องขอ', approve = 'ยกเลิก' WHERE project_id = ? AND writed_status NOT IN ('เริ่มดำเนินการ', 'สิ้นสุดดำเนินการ', 'ยกเลิกคำร้องขอ') AND approve IS NULL");
                    if (!$stmt_can_er) { throw new mysqli_sql_exception("Failed to prepare equipments_requests cancel statement: " . $conn->error); }
                    $stmt_can_er->bind_param("i", $project_id);
                    $stmt_can_er->execute();
                    $stmt_can_er->close();

                    $stmt_can_p = $conn->prepare("UPDATE project SET writed_status = 'ยกเลิกโครงการ' WHERE project_id = ? AND nontri_id = ?");
                    if (!$stmt_can_p) { throw new mysqli_sql_exception("Failed to prepare project cancel statement: " . $conn->error); }
                    $stmt_can_p->bind_param("is", $project_id, $nontri_id);
                    if ($stmt_can_p->execute()) {
                        if ($stmt_can_p->affected_rows > 0) {
                            $conn->commit();
                            $success_message = "ยกเลิกโครงการสำเร็จแล้ว!";
                            header("Location: ?main_tab=user_requests&mode=projects_list&status=success&message=" . urlencode($success_message));
                            exit();
                        } else {
                            $conn->rollback();
                            header("Location: ?main_tab=user_requests&mode=projects_list&status=error&message=" . urlencode("ไม่พบโครงการที่คุณต้องการยกเลิก หรือคุณไม่มีสิทธิ์ยกเลิกโครงการนี้."));
                            exit();
                        }
                    } else {
                        $conn->rollback();
                        header("Location: ?main_tab=user_requests&mode=projects_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการยกเลิกโครงการ: " . $stmt_can_p->error));
                        exit();
                    }
                    $stmt_can_p->close();
                } else {
                    header("Location: ?main_tab=user_requests&mode=projects_detail&project_id=" . $project_id . "&status=error&message=" . urlencode("ไม่สามารถยกเลิกโครงการที่ 'เริ่มดำเนินการ', 'สิ้นสุดโครงการ' หรือ 'ยกเลิกโครงการ' แล้วได้."));
                    exit();
                }

            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                header("Location: ?main_tab=user_requests&mode=projects_detail&project_id=" . $project_id . "&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการยกเลิกโครงการและข้อมูลที่เกี่ยวข้อง: " . $e->getMessage()));
                exit();
            }
        } else {
            header("Location: ?main_tab=user_requests&mode=projects_list&status=error&message=" . urlencode("ไม่พบรหัสโครงการที่ถูกต้อง."));
            exit();
        }

    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_building_request') {
        $facility_re_id = (int)$_POST['facility_re_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        if ($facility_re_id > 0) {
            $check_sql = "SELECT fr.writed_status, p.nontri_id FROM facilities_requests fr JOIN project p ON fr.project_id = p.project_id WHERE fr.facility_re_id = ?";
            $stmt_check = $conn->prepare($check_sql);
            if (!$stmt_check) {
                header("Location: ?main_tab=user_requests&mode=buildings_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับตรวจสอบสิทธิ์: " . $conn->error));
                exit();
            }
            $stmt_check->bind_param("i", $facility_re_id);
            $stmt_check->execute();
            $stmt_check->bind_result($current_status, $owner_nontri_id);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($owner_nontri_id === $nontri_id && $current_status === 'ร่างคำร้องขอ') {
                $stmt = $conn->prepare("DELETE FROM facilities_requests WHERE facility_re_id = ?");
                if (!$stmt) {
                    header("Location: ?main_tab=user_requests&mode=buildings_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับลบคำร้องขอ: " . $conn->error));
                    exit();
                }
                $stmt->bind_param("i", $facility_re_id);
                if ($stmt->execute()) {
                    $success_message = "ลบคำร้องขอสถานที่สำเร็จแล้ว!";
                    header("Location: ?main_tab=user_requests&mode=buildings_list&status=success&message=" . urlencode($success_message));
                    exit();
                } else {
                    header("Location: ?main_tab=user_requests&mode=buildings_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการลบคำร้องขอสถานที่: " . $stmt->error));
                    exit();
                }
                $stmt->close();
            } else {
                header("Location: ?main_tab=user_requests&mode=buildings_list&status=error&message=" . urlencode("คุณไม่มีสิทธิ์ลบคำร้องขอสถานที่นี้ หรือคำร้องขอไม่ใช่ร่างคำร้อง."));
                exit();
            }
        } else {
            header("Location: ?main_tab=user_requests&mode=buildings_list&status=error&message=" . urlencode("ไม่พบรหัสคำร้องขอสถานที่ที่ถูกต้อง."));
            exit();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'cancel_building_request') {
        $facility_re_id = (int)$_POST['facility_re_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        if ($facility_re_id > 0) {
            $check_sql = "SELECT fr.writed_status, fr.approve, p.nontri_id FROM facilities_requests fr JOIN project p ON fr.project_id = p.project_id WHERE fr.facility_re_id = ?";
            $stmt_check = $conn->prepare($check_sql);
            if (!$stmt_check) {
                header("Location: ?main_tab=user_requests&mode=buildings_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับตรวจสอบสถานะ: " . $conn->error));
                exit();
            }
            $stmt_check->bind_param("i", $facility_re_id);
            $stmt_check->execute();
            $stmt_check->bind_result($current_status, $current_approve, $owner_nontri_id);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($owner_nontri_id === $nontri_id) {
                if ($current_status !== 'เริ่มดำเนินการ' && $current_status !== 'สิ้นสุดดำเนินการ' && $current_approve !== 'อนุมัติ' && $current_approve !== 'ไม่อนุมัติ' && $current_status !== 'ยกเลิกคำร้องขอ') {
                    $stmt = $conn->prepare("UPDATE facilities_requests SET writed_status = 'ยกเลิกคำร้องขอ', approve = 'ยกเลิก' WHERE facility_re_id = ?");
                    if (!$stmt) {
                        header("Location: ?main_tab=user_requests&mode=buildings_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับยกเลิกคำร้องขอ: " . $conn->error));
                        exit();
                    }
                    $stmt->bind_param("i", $facility_re_id);
                    if ($stmt->execute()) {
                        $success_message = "ยกเลิกคำร้องขอสถานที่สำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=buildings_list&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        header("Location: ?main_tab=user_requests&mode=buildings_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการยกเลิกคำร้องขอสถานที่: " . $stmt->error));
                        exit();
                    }
                    $stmt->close();
                } else {
                    header("Location: ?main_tab=user_requests&mode=buildings_detail&facility_re_id=" . $facility_re_id . "&status=error&message=" . urlencode("ไม่สามารถยกเลิกคำร้องที่ 'เริ่มดำเนินการ', 'สิ้นสุดดำเนินการ', 'อนุมัติ', 'ไม่อนุมัติ' หรือ 'ยกเลิก' แล้วได้"));
                    exit();
                }
            } else {
                header("Location: ?main_tab=user_requests&mode=buildings_list&status=error&message=" . urlencode("คุณไม่มีสิทธิ์ยกเลิกคำร้องขอสถานที่นี้."));
                exit();
            }
        } else {
            header("Location: ?main_tab=user_requests&mode=buildings_list&status=error&message=" . urlencode("ไม่พบรหัสคำร้องขอสถานที่ที่ถูกต้อง."));
            exit();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_equipment_request') {
        $equip_re_id = (int)$_POST['equip_re_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        if ($equip_re_id > 0) {
            $check_sql = "SELECT er.writed_status, p.nontri_id FROM equipments_requests er JOIN project p ON er.project_id = p.project_id WHERE er.equip_re_id = ?";
            $stmt_check = $conn->prepare($check_sql);
            if (!$stmt_check) {
                header("Location: ?main_tab=user_requests&mode=equipments_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับตรวจสอบสิทธิ์: " . $conn->error));
                exit();
            }
            $stmt_check->bind_param("i", $equip_re_id);
            $stmt_check->execute();
            $stmt_check->bind_result($current_status, $owner_nontri_id);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($owner_nontri_id === $nontri_id && $current_status === 'ร่างคำร้องขอ') {
                $stmt = $conn->prepare("DELETE FROM equipments_requests WHERE equip_re_id = ?");
                if (!$stmt) {
                    header("Location: ?main_tab=user_requests&mode=equipments_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับลบคำร้องขอ: " . $conn->error));
                    exit();
                }
                $stmt->bind_param("i", $equip_re_id);
                if ($stmt->execute()) {
                    $success_message = "ลบคำร้องขออุปกรณ์สำเร็จแล้ว!";
                    header("Location: ?main_tab=user_requests&mode=equipments_list&status=success&message=" . urlencode($success_message));
                    exit();
                } else {
                    header("Location: ?main_tab=user_requests&mode=equipments_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการลบคำร้องขออุปกรณ์: " . $stmt->error));
                    exit();
                }
                $stmt->close();
            } else {
                header("Location: ?main_tab=user_requests&mode=equipments_list&status=error&message=" . urlencode("คุณไม่มีสิทธิ์ลบคำร้องขออุปกรณ์นี้ หรือคำร้องขอไม่ใช่ร่างคำร้อง."));
                exit();
            }
        } else {
            header("Location: ?main_tab=user_requests&mode=equipments_list&status=error&message=" . urlencode("ไม่พบรหัสคำร้องขออุปกรณ์ที่ถูกต้อง."));
            exit();
        }
    } elseif (isset($_POST['action']) && $_POST['action'] == 'cancel_equipment_request') {
        $equip_re_id = (int)$_POST['equip_re_id'] ?? 0;
        $nontri_id = $_SESSION['nontri_id'] ?? '';

        if ($equip_re_id > 0) {
            $check_sql = "SELECT er.writed_status, er.approve, p.nontri_id FROM equipments_requests er JOIN project p ON er.project_id = p.project_id WHERE er.equip_re_id = ?";
            $stmt_check = $conn->prepare($check_sql);
            if (!$stmt_check) {
                header("Location: ?main_tab=user_requests&mode=equipments_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับตรวจสอบสถานะ: " . $conn->error));
                exit();
            }
            $stmt_check->bind_param("i", $equip_re_id);
            $stmt_check->execute();
            $stmt_check->bind_result($current_status, $current_approve, $owner_nontri_id);
            $stmt_check->fetch();
            $stmt_check->close();

            if ($owner_nontri_id === $nontri_id) {
                if ($current_status !== 'เริ่มดำเนินการ' && $current_status !== 'สิ้นสุดดำเนินการ' && $current_approve !== 'อนุมัติ' && $current_approve !== 'ไม่อนุมัติ' && $current_status !== 'ยกเลิกคำร้องขอ') {
                    $stmt = $conn->prepare("UPDATE equipments_requests SET writed_status = 'ยกเลิกคำร้องขอ', approve = 'ยกเลิก' WHERE equip_re_id = ?");
                    if (!$stmt) {
                        header("Location: ?main_tab=user_requests&mode=equipments_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL สำหรับยกเลิกคำร้องขอ: " . $conn->error));
                        exit();
                    }
                    $stmt->bind_param("i", $equip_re_id);
                    if ($stmt->execute()) {
                        $success_message = "ยกเลิกคำร้องขออุปกรณ์สำเร็จแล้ว!";
                        header("Location: ?main_tab=user_requests&mode=equipments_list&status=success&message=" . urlencode($success_message));
                        exit();
                    } else {
                        header("Location: ?main_tab=user_requests&mode=equipments_list&status=error&message=" . urlencode("เกิดข้อผิดพลาดในการยกเลิกคำร้องขออุปกรณ์: " . $stmt->error));
                        exit();
                    }
                    $stmt->close();
                } else {
                    header("Location: ?main_tab=user_requests&mode=equipments_detail&equip_re_id=" . $equip_re_id . "&status=error&message=" . urlencode("ไม่สามารถยกเลิกคำร้องที่ 'เริ่มดำเนินการ', 'สิ้นสุดดำเนินการ', 'อนุมัติ', 'ไม่อนุมัติ' หรือ 'ยกเลิก' แล้วได้"));
                    exit();
                }
            } else {
                header("Location: ?main_tab=user_requests&mode=equipments_list&status=error&message=" . urlencode("คุณไม่มีสิทธิ์ยกเลิกคำร้องขออุปกรณ์นี้."));
                exit();
            }
        } else {
            header("Location: ?main_tab=user_requests&mode=equipments_list&status=error&message=" . urlencode("ไม่พบรหัสคำร้องขออุปกรณ์ที่ถูกต้อง."));
            exit();
        }
    }
}
?>