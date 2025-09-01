<?php

/**
 * สร้าง SQL clauses สำหรับการกรองสถานะและการเรียงลำดับข้อมูล
 * เวอร์ชัน 2.1: แก้ไขข้อผิดพลาด SQL syntax (เพิ่มช่องว่างหน้า ORDER BY)
 *
 * @param string $context บริบทของหน้าปัจจุบัน (เช่น 'projects_list', 'projects_admin', 'buildings_list', 'buildings_admin')
 * @param string $sort_filter ค่าที่เลือกจาก dropdown (เช่น 'date_desc', 'date_asc', 'อนุมัติ', 'ส่งโครงการ')
 * @return array ['where_sql' => string, 'where_param_type' => string, 'where_param_value' => mixed, 'order_by_sql' => string]
 */
function getSortingClauses($context, $sort_filter)
{
    // กำหนดค่าเริ่มต้น
    $result = [
        'where_sql'         => '',
        'where_param_type'  => '',
        'where_param_value' => null,
        'order_by_sql'      => ''
    ];

    // กำหนดคอลัมน์วันที่และ Alias ของตารางตามบริบท
    $date_column = 'created_date'; // ค่าเริ่มต้นสำหรับโครงการ
    $table_alias = 'p';

    if (in_array($context, ['buildings_list', 'buildings_admin'])) {
        $date_column = 'request_date';
        $table_alias = 'fr';
    } elseif (in_array($context, ['equipments_list', 'equipments_admin'])) {
        $date_column = 'request_date';
        $table_alias = 'er';
    }

    // --- 1. จัดการการเรียงลำดับ (ORDER BY) ---
    // สังเกตช่องว่างที่เพิ่มเข้ามาหน้า "ORDER BY"
    switch ($sort_filter) {
        case 'date_asc': // เก่าสุดไปใหม่สุด
            $result['order_by_sql'] = " ORDER BY {$table_alias}.{$date_column} ASC";
            break;
        case 'date_desc': // ใหม่สุดไปเก่าสุด (ค่าเริ่มต้น)
        default:
            $result['order_by_sql'] = " ORDER BY {$table_alias}.{$date_column} DESC";
            break;
    }

    // --- 2. จัดการการกรอง (WHERE) ---
    // เฉพาะเมื่อ $sort_filter ไม่ใช่การเรียงตามวันที่ หรือ 'all'
    if (!in_array($sort_filter, ['all', 'date_asc', 'date_desc'])) {
        $status_value = $sort_filter;

        if (in_array($context, ['projects_list', 'projects_admin'])) {
            $result['where_sql'] = " AND p.writed_status = ?";
            $result['where_param_type'] = 's';
            $result['where_param_value'] = $status_value;
        }
        elseif (in_array($context, ['buildings_list', 'buildings_admin'])) {
            if (in_array($status_value, ['อนุมัติ', 'ไม่อนุมัติ'])) {
                $result['where_sql'] = " AND fr.approve = ?";
            } else {
                $result['where_sql'] = " AND fr.writed_status = ?";
            }
            $result['where_param_type'] = 's';
            $result['where_param_value'] = $status_value;
        }
        elseif (in_array($context, ['equipments_list', 'equipments_admin'])) {
            if (in_array($status_value, ['อนุมัติ', 'ไม่อนุมัติ'])) {
                $result['where_sql'] = " AND er.approve = ?";
            } else {
                $result['where_sql'] = " AND er.writed_status = ?";
            }
            $result['where_param_type'] = 's';
            $result['where_param_value'] = $status_value;
        }
    }

    return $result;
}
?>