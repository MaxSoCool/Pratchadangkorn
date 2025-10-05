<?php

function getSortingClauses($context, $sort_filter)
{
    $result = [
        'where_sql'         => '',
        'where_param_type'  => '',
        'where_param_value' => null,
        'order_by_sql'      => ''
    ];

    // --- Logic for Request/Project Sorting (เดิม) ---
    // ใช้ table_alias ที่ถูกต้องตามบริบทของ request/project
    $request_table_alias = '';
    $request_date_column = '';

    if (in_array($context, ['projects_list', 'projects_admin'])) {
        $request_table_alias = 'p';
        $request_date_column = 'created_date';
    } elseif (in_array($context, ['buildings_list', 'buildings_admin'])) {
        $request_table_alias = 'fr'; // Facility Request
        $request_date_column = 'request_date';
    } elseif (in_array($context, ['equipments_list', 'equipments_admin'])) {
        $request_table_alias = 'er'; // Equipment Request
        $request_date_column = 'request_date';
    }

    // Apply ORDER BY for requests/projects if relevant context
    if ($request_table_alias !== '') {
        switch ($sort_filter) {
            case 'date_asc':
                $result['order_by_sql'] = " ORDER BY {$request_table_alias}.{$request_date_column} ASC";
                break;
            case 'date_desc':
            default: // Default sort for requests/projects
                $result['order_by_sql'] = " ORDER BY {$request_table_alias}.{$request_date_column} DESC";
                break;
        }

        // Apply WHERE for requests/projects status (excluding 'all', 'yes', 'no' which are for item availability)
        if (!in_array($sort_filter, ['all', 'yes', 'no', 'date_asc', 'date_desc'])) {
            $status_value = $sort_filter;
            $result['where_param_type'] = 's';
            $result['where_param_value'] = $status_value;

            if (in_array($context, ['projects_list', 'projects_admin'])) {
                $result['where_sql'] = " AND {$request_table_alias}.writed_status = ?";
            } elseif (in_array($context, ['buildings_list', 'buildings_admin'])) {
                // Assuming 'approve' is for final approval status, 'writed_status' for other statuses
                if (in_array($status_value, ['อนุมัติ', 'ไม่อนุมัติ'])) {
                    $result['where_sql'] = " AND {$request_table_alias}.approve = ?";
                } else {
                    $result['where_sql'] = " AND {$request_table_alias}.writed_status = ?";
                }
            } elseif (in_array($context, ['equipments_list', 'equipments_admin'])) {
                if (in_array($status_value, ['อนุมัติ', 'ไม่อนุมัติ'])) {
                    $result['where_sql'] = " AND {$request_table_alias}.approve = ?";
                } else {
                    $result['where_sql'] = " AND {$request_table_alias}.writed_status = ?";
                }
            }
        }
    }
    // --- End Logic for Request/Project Sorting ---


    // --- New Logic for Item Availability Sorting (สำหรับหน้า admin-data_view-page.php และ user-data_view-page.php) ---
    // ใช้ context ใหม่เพื่อแยกแยะ
    if (in_array($context, ['data_buildings', 'data_facilities', 'data_equipment'])) {
        // ไม่มี ORDER BY เฉพาะเจาะจงที่เกี่ยวข้องกับ available, จะใช้ ORDER BY เดิมที่มีอยู่แล้วในหน้า view
        // เช่น ORDER BY CAST(b.building_id AS UNSIGNED) ASC

        if ($sort_filter === 'yes' || $sort_filter === 'no') {
            $result['where_sql'] = " AND T.available = ?"; // T คือ alias ชั่วคราวที่จะถูกแทนที่ในหน้าเรียกใช้
            $result['where_param_type'] = 's';
            $result['where_param_value'] = $sort_filter;
        }
        // ถ้า $sort_filter เป็น 'all' จะไม่เพิ่ม WHERE clause สำหรับ available
    }
    // --- End New Logic ---

    return $result;
}