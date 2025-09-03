<?php

function getSortingClauses($context, $sort_filter)
{
    $result = [
        'where_sql'         => '',
        'where_param_type'  => '',
        'where_param_value' => null,
        'order_by_sql'      => ''
    ];

    $date_column = 'created_date'; 
    $table_alias = 'p';

    if (in_array($context, ['buildings_list', 'buildings_admin'])) {
        $date_column = 'request_date';
        $table_alias = 'fr';
    } elseif (in_array($context, ['equipments_list', 'equipments_admin'])) {
        $date_column = 'request_date';
        $table_alias = 'er';
    }

    switch ($sort_filter) {
        case 'date_asc': 
            $result['order_by_sql'] = " ORDER BY {$table_alias}.{$date_column} ASC";
            break;
        case 'date_desc': 
        default:
            $result['order_by_sql'] = " ORDER BY {$table_alias}.{$date_column} DESC";
            break;
    }

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