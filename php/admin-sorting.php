<?php

/**
 * ฟังก์ชันสำหรับสร้างเงื่อนไข WHERE clause เพื่อกรองข้อมูลตามวันที่
 * โดยจะรองรับการกรอง 2 แบบ:
 * 1. Predefined ranges (วันนี้, สัปดาห์นี้, เดือนนี้, ปีนี้)
 * 2. Specific date (ปี, เดือน, วันที่เจาะจง)
 *
 * @param string $context บ่งบอกว่ากำลังกรองข้อมูลของแท็บใด (e.g., 'projects_admin', 'buildings_admin', 'dashboard_projects')
 * @param string|null $predefined_range_select ช่วงเวลาที่กำหนดไว้ล่วงหน้า (e.g., 'today', 'this_week')
 * @param string|null $specific_year_select ปีที่ระบุ
 * @param string|null $specific_month_select เดือนที่ระบุ
 * @param string|null $specific_day_select วันที่ระบุ
 * @return array คืนค่า array ที่มี 'where_sql', 'param_types', 'param_values'
 */
function getDateFilteringClauses($context, $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select)
{
    $where_sql = '';
    $param_types = '';
    $param_values = [];

    $date_column = ''; // คอลัมน์วันที่ที่จะใช้กรอง
    $alias = '';       // Alias ของตารางหลัก

    // กำหนดคอลัมน์วันที่และ alias ตาม context
    // สำหรับ dashboard counts จะใช้ created_date สำหรับ project และ request_date สำหรับ requests
    if ($context == 'projects_admin' || $context == 'dashboard_projects') {
        $date_column = 'created_date';
        $alias = 'p'; // project table alias
    } elseif ($context == 'buildings_admin' || $context == 'dashboard_facilities_requests') {
        $date_column = 'request_date';
        $alias = 'fr'; // facilities_requests table alias
    } elseif ($context == 'equipments_admin' || $context == 'dashboard_equipments_requests') {
        $date_column = 'request_date';
        $alias = 'er'; // equipments_requests table alias
    } elseif ($context == 'dashboard_upcoming_projects') {
        // สำหรับ upcoming projects จะกรองจาก start_date
        $date_column = 'start_date';
        $alias = 'p';
    } elseif ($context == 'dashboard_recent_requests_fr') {
        // สำหรับ recent facilities requests
        $date_column = 'request_date';
        $alias = 'fr';
    } elseif ($context == 'dashboard_recent_requests_er') {
        // สำหรับ recent equipments requests
        $date_column = 'request_date';
        $alias = 'er';
    } else {
        // หาก context ไม่ถูกต้อง ให้ไม่กรอง
        return [
            'where_sql'    => '',
            'param_types'  => '',
            'param_values' => []
        ];
    }

    $base_column = "{$alias}.{$date_column}";

    // ตรวจสอบการกรองแบบเจาะจงก่อน (มีลำดับความสำคัญสูงกว่า)
    if (!empty($specific_year_select) || !empty($specific_month_select) || !empty($specific_day_select)) {
        $conditions = [];
        if (!empty($specific_year_select)) {
            $conditions[] = "YEAR({$base_column}) = ?";
            $param_types .= 'i';
            $param_values[] = (int)$specific_year_select;
        }
        if (!empty($specific_month_select)) {
            $conditions[] = "MONTH({$base_column}) = ?";
            $param_types .= 'i';
            $param_values[] = (int)$specific_month_select;
        }
        if (!empty($specific_day_select)) {
            $conditions[] = "DAY({$base_column}) = ?";
            $param_types .= 'i';
            $param_values[] = (int)$specific_day_select;
        }
        if (!empty($conditions)) {
            $where_sql .= " AND (" . implode(" AND ", $conditions) . ")";
        }
    }
    // หากไม่มีการกรองแบบเจาะจง ให้ใช้การกรองแบบช่วงเวลาที่กำหนดไว้ล่วงหน้า
    elseif (!empty($predefined_range_select)) {
        $current_date = date('Y-m-d');
        switch ($predefined_range_select) {
            case 'today':
                $where_sql .= " AND DATE({$base_column}) = ?";
                $param_types .= 's';
                $param_values[] = $current_date;
                break;
            case 'this_week':
                // ใช้ YEARWEEK(date, 1) เพื่อให้สัปดาห์เริ่มต้นที่วันจันทร์ (ตามมาตรฐาน ISO)
                $where_sql .= " AND YEARWEEK({$base_column}, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'this_month':
                $where_sql .= " AND YEAR({$base_column}) = YEAR(?) AND MONTH({$base_column}) = MONTH(?)";
                $param_types .= 'ss';
                $param_values[] = $current_date;
                $param_values[] = $current_date;
                break;
            case 'this_year':
                $where_sql .= " AND YEAR({$base_column}) = YEAR(?)";
                $param_types .= 's';
                $param_values[] = $current_date;
                break;
        }
    }

    return [
        'where_sql'    => $where_sql,
        'param_types'  => $param_types,
        'param_values' => $param_values
    ];
}
?>