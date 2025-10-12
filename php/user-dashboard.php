<?php
if (!isset($conn)) {
    include dirname(__DIR__) . '/database/database.php';
}
if (!function_exists('formatThaiDate')) {
    include 'helpers.php';
}

if (!empty($user_id)) {
    // 1. นับจำนวนโครงการ
    $sql_projects_count = "SELECT writed_status, COUNT(*) AS count FROM project WHERE nontri_id = ? GROUP BY writed_status";
    $stmt_projects_count = $conn->prepare($sql_projects_count);
    if ($stmt_projects_count) {
        $stmt_projects_count->bind_param("s", $user_id);
        $stmt_projects_count->execute();
        $result_projects_count = $stmt_projects_count->get_result();
        while ($row = $result_projects_count->fetch_assoc()) {
            $status_key = '';
            if ($row['writed_status'] == 'ร่างโครงการ') $status_key = 'draft';
            else if ($row['writed_status'] == 'ส่งโครงการ') $status_key = 'submitted';
            else if ($row['writed_status'] == 'เริ่มดำเนินการ') $status_key = 'in_progress';
            else if ($row['writed_status'] == 'สิ้นสุดโครงการ') $status_key = 'completed';
            else if ($row['writed_status'] == 'ยกเลิกโครงการ') $status_key = 'cancelled';

            if ($status_key) {
                $dashboard_data['project_counts'][$status_key] = $row['count'];
                $dashboard_data['project_counts']['total'] += $row['count'];
            }
        }
        $stmt_projects_count->close();
    }

    // 2. นับจำนวนคำร้องขอสถานที่
    $sql_fr_count = "SELECT fr.writed_status, fr.approve, COUNT(*) AS count
                        FROM facilities_requests fr
                        JOIN project p ON fr.project_id = p.project_id
                        WHERE p.nontri_id = ? GROUP BY fr.writed_status, fr.approve";
    $stmt_fr_count = $conn->prepare($sql_fr_count);
    if ($stmt_fr_count) {
        $stmt_fr_count->bind_param("s", $user_id);
        $stmt_fr_count->execute();
        $result_fr_count = $stmt_fr_count->get_result();
        while ($row = $result_fr_count->fetch_assoc()) {
            $dashboard_data['facilities_request_counts']['total'] += $row['count'];

            if ($row['writed_status'] == 'ร่างคำร้องขอ') {
                $dashboard_data['facilities_request_counts']['draft'] += $row['count'];
            } elseif ($row['writed_status'] == 'ส่งคำร้องขอ') {
                $dashboard_data['facilities_request_counts']['submitted'] += $row['count'];
                if ($row['approve'] === null || $row['approve'] === '') {
                    $dashboard_data['facilities_request_counts']['pending_approval'] += $row['count'];
                }
            } elseif ($row['writed_status'] == 'เริ่มดำเนินการ') {
                    $dashboard_data['facilities_request_counts']['in_progress'] += $row['count'];
            } elseif ($row['writed_status'] == 'สิ้นสุดดำเนินการ') {
                $dashboard_data['facilities_request_counts']['completed'] += $row['count'];
            } elseif ($row['writed_status'] == 'ยกเลิกคำร้องขอ') {
                $dashboard_data['facilities_request_counts']['cancelled'] += $row['count'];
            }

            if ($row['approve'] == 'อนุมัติ') {
                $dashboard_data['facilities_request_counts']['approved'] += $row['count'];
            } elseif ($row['approve'] == 'ไม่อนุมัติ') {
                $dashboard_data['facilities_request_counts']['rejected'] += $row['count'];
            }
        }
        $stmt_fr_count->close();
    }

    // 3. นับจำนวนคำร้องขออุปกรณ์
    $sql_er_count = "SELECT er.writed_status, er.approve, COUNT(*) AS count
                        FROM equipments_requests er
                        JOIN project p ON er.project_id = p.project_id
                        WHERE p.nontri_id = ? GROUP BY er.writed_status, er.approve";
    $stmt_er_count = $conn->prepare($sql_er_count);
    if ($stmt_er_count) {
        $stmt_er_count->bind_param("s", $user_id);
        $stmt_er_count->execute();
        $result_er_count = $stmt_er_count->get_result();
        while ($row = $result_er_count->fetch_assoc()) {
            $dashboard_data['equipments_request_counts']['total'] += $row['count'];

            if ($row['writed_status'] == 'ร่างคำร้องขอ') {
                $dashboard_data['equipments_request_counts']['draft'] += $row['count'];
            } elseif ($row['writed_status'] == 'ส่งคำร้องขอ') {
                $dashboard_data['equipments_request_counts']['submitted'] += $row['count'];
                if ($row['approve'] === null || $row['approve'] === '') {
                    $dashboard_data['equipments_request_counts']['pending_approval'] += $row['count'];
                }
            } elseif ($row['writed_status'] == 'เริ่มดำเนินการ') {
                    $dashboard_data['equipments_request_counts']['in_progress'] += $row['count'];
            } elseif ($row['writed_status'] == 'สิ้นสุดดำเนินการ') {
                $dashboard_data['equipments_request_counts']['completed'] += $row['count'];
            } elseif ($row['writed_status'] == 'ยกเลิกคำร้องขอ') {
                $dashboard_data['equipments_request_counts']['cancelled'] += $row['count'];
            }

            if ($row['approve'] == 'อนุมัติ') {
                $dashboard_data['equipments_request_counts']['approved'] += $row['count'];
            } elseif ($row['approve'] == 'ไม่อนุมัติ') {
                $dashboard_data['equipments_request_counts']['rejected'] += $row['count'];
            }
        }
        $stmt_er_count->close();
    }

    // 4. กิจกรรมที่กำลังจะมาถึง
    $upcoming_date_limit = date('Y-m-d', strtotime('+14 days'));
    $current_date_php = date('Y-m-d');

    $sql_upcoming_projects = "SELECT project_id AS id, 'โครงการ' AS type, project_name AS name,
                                start_date, end_date, NULL AS start_time, NULL AS end_time,
                                project_name AS project_name_for_display, writed_status AS writed_status_for_display, NULL AS approve_for_display
                        FROM project
                        WHERE nontri_id = ? AND start_date BETWEEN ? AND ?
                        AND (writed_status = 'ส่งโครงการ' OR writed_status = 'เริ่มดำเนินการ')
                        ORDER BY start_date ASC";
    $stmt_upcoming_projects = $conn->prepare($sql_upcoming_projects);
    if ($stmt_upcoming_projects) {
        $stmt_upcoming_projects->bind_param("sss", $user_id, $current_date_php, $upcoming_date_limit);
        $stmt_upcoming_projects->execute();
        $result_upcoming_projects = $stmt_upcoming_projects->get_result();
        while ($row = $result_upcoming_projects->fetch_assoc()) {
            $dashboard_data['upcoming_requests'][] = [
                'id' => $row['id'],
                'type' => $row['type'],
                'name' => $row['name'],
                'start_date' => $row['start_date'],
                'end_date' => $row['end_date'],
                'start_time' => $row['start_time'],
                'end_time' => $row['end_time'],
                'project_name' => $row['name'],
                'writed_status' => $row['writed_status_for_display'],
                'approve' => $row['approve_for_display'],
            ];
        }
        $stmt_upcoming_projects->close();
    }

    // 5. กิจกรรมล่าสุด (Logic คงเดิม)
    $all_recent_activity_raw = [];

    // โครงการล่าสุด
    $sql_recent_p = "SELECT project_id AS id, 'โครงการ' AS item_type, project_name AS item_name,
                            created_date AS activity_date, writed_status AS status_text, NULL AS approve_status
                    FROM project WHERE nontri_id = ?
                    ORDER BY created_date DESC LIMIT 5";
    $stmt_recent_p = $conn->prepare($sql_recent_p);
    if ($stmt_recent_p) {
        $stmt_recent_p->bind_param("s", $user_id);
        $stmt_recent_p->execute();
        $result_recent_p = $stmt_recent_p->get_result();
        while ($row = $result_recent_p->fetch_assoc()) {
            $all_recent_activity_raw[] = $row;
        }
        $stmt_recent_p->close();
    }

    // คำร้องขอสถานที่ล่าสุด
    $sql_recent_fr = "SELECT fr.facility_re_id AS id, 'คำร้องขอสถานที่' AS item_type, f.facility_name AS item_name,
                            fr.request_date AS activity_date, fr.writed_status AS status_text, fr.approve AS approve_status
                    FROM facilities_requests fr
                    JOIN project p ON fr.project_id = p.project_id
                    JOIN facilities f ON fr.facility_id = f.facility_id
                    WHERE p.nontri_id = ?
                    ORDER BY fr.request_date DESC LIMIT 5";
    $stmt_recent_fr = $conn->prepare($sql_recent_fr);
    if ($stmt_recent_fr) {
        $stmt_recent_fr->bind_param("s", $user_id);
        $stmt_recent_fr->execute();
        $result_recent_fr = $stmt_recent_fr->get_result();
        while ($row = $result_recent_fr->fetch_assoc()) {
            $all_recent_activity_raw[] = $row;
        }
        $stmt_recent_fr->close();
    }

    // คำร้องขออุปกรณ์ล่าสุด
    $sql_recent_er = "SELECT er.equip_re_id AS id, 'คำร้องขออุปกรณ์' AS item_type, e.equip_name AS item_name,
                            er.request_date AS activity_date, er.writed_status AS status_text, er.approve AS approve_status
                    FROM equipments_requests er
                    JOIN project p ON er.project_id = p.project_id
                    JOIN equipments e ON er.equip_id = e.equip_id
                    WHERE p.nontri_id = ?
                    ORDER BY er.request_date DESC LIMIT 5";
    $stmt_recent_er = $conn->prepare($sql_recent_er);
    if ($stmt_recent_er) {
        $stmt_recent_er->bind_param("s", $user_id);
        $stmt_recent_er->execute();
        $result_recent_er = $stmt_recent_er->get_result();
        while ($row = $result_recent_er->fetch_assoc()) {
            $all_recent_activity_raw[] = $row;
        }
        $stmt_recent_er->close();
    }

    // จัดเรียงกิจกรรมที่กำลังจะมาถึง
    if (!empty($dashboard_data['upcoming_requests'])) {
        usort($dashboard_data['upcoming_requests'], function($a, $b) {
            return strtotime($a['start_date']) - strtotime($b['start_date']);
        });
        $dashboard_data['upcoming_requests'] = array_slice($dashboard_data['upcoming_requests'], 0, 5);
    }

    // จัดเรียงกิจกรรมล่าสุดทั้งหมดและจำกัดจำนวน
    if (!empty($all_recent_activity_raw)) {
        usort($all_recent_activity_raw, function($a, $b) {
            return strtotime($b['activity_date']) - strtotime($a['activity_date']);
        });
        $dashboard_data['recent_activity'] = array_slice($all_recent_activity_raw, 0, 5);
    }
}
?>