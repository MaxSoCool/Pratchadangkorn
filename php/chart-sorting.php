<?php

/**
 * Generates SQL WHERE clauses, JOINs, and parameters for chart filtering based on different modes.
 *
 * @param string $chart_mode The chart filtering mode (e.g., 'faculty_overview', 'top_facilities', 'top_equipments').
 * @param string|null $predefined_range Predefined date range (e.g., 'today', 'this_month').
 * @param int|null $specific_year Specific year.
 * @param int|null $specific_month Specific month.
 * @param int|null $specific_day Specific day.
 * @param int|null $fa_de_id_filter_global Global faculty filter (e.g., from dashboard cards filter).
 * @param string|null $drilldown_type For drill-down, specifies 'projects', 'facilities', 'equipments', 'facility_by_faculty', 'equipment_by_faculty'.
 * @param int|string|null $drilldown_id For drill-down, the ID of the faculty, facility, or equipment.
 * @return array An associative array containing 'where_clauses' (array of conditions), 'param_values', 'param_types', 'join_sql', 'group_by_sql', 'order_by_sql', 'limit_sql'.
 */
function getChartFilteringClauses(
    $chart_mode,
    $predefined_range,
    $specific_year,
    $specific_month,
    $specific_day,
    $fa_de_id_filter_global = null, // Global filter, but often applied externally in api/chart.php
    $drilldown_type = null,
    $drilldown_id = null
) {
    $where_clauses = []; // This will hold conditions *without* 'WHERE' or 'AND' prefixes
    $param_values = [];
    $param_types = '';
    $join_sql = '';
    $group_by_sql = '';
    $order_by_sql = '';
    $limit_sql = '';

    switch ($chart_mode) {
        // faculty_overview does not use this function directly for its primary filtering,
        // as its logic is handled more directly in api/chart.php to filter out empty faculties.

        case 'top_facilities':
        case 'drilldown_facility_by_faculty':
            $date_filter_result = getDateFilteringClauses('dashboard_facilities_requests', $predefined_range, $specific_year, $specific_month, $specific_day);
            if (!empty($date_filter_result['where_sql'])) {
                // getDateFilteringClauses returns " AND ...", so we strip " AND "
                $where_clauses[] = ltrim($date_filter_result['where_sql'], ' AND ');
                $param_values = array_merge($param_values, $date_filter_result['param_values']);
                $param_types .= $date_filter_result['param_types'];
            }
            if ($chart_mode === 'drilldown_facility_by_faculty' && !empty($drilldown_id)) {
                $where_clauses[] = "fr.facility_id = ?";
                $param_values[] = (int)$drilldown_id;
                $param_types .= 'i';
            }
            $group_by_sql = ($chart_mode === 'top_facilities') ? " GROUP BY f.facility_id, f.facility_name, b.building_name" : " GROUP BY fd.fa_de_id, fd.fa_de_name";
            $order_by_sql = ($chart_mode === 'top_facilities') ? " ORDER BY count_requests DESC" : " ORDER BY fd.fa_de_name ASC";
            $limit_sql = ($chart_mode === 'top_facilities') ? " LIMIT 10" : "";
            break;

        case 'top_equipments':
        case 'drilldown_equipment_by_faculty':
            $date_filter_result = getDateFilteringClauses('dashboard_equipments_requests', $predefined_range, $specific_year, $specific_month, $specific_day);
            if (!empty($date_filter_result['where_sql'])) {
                $where_clauses[] = ltrim($date_filter_result['where_sql'], ' AND ');
                $param_values = array_merge($param_values, $date_filter_result['param_values']);
                $param_types .= $date_filter_result['param_types'];
            }
            if ($chart_mode === 'drilldown_equipment_by_faculty' && !empty($drilldown_id)) {
                $where_clauses[] = "er.equip_id = ?";
                $param_values[] = (int)$drilldown_id;
                $param_types .= 'i';
            }
            $group_by_sql = ($chart_mode === 'top_equipments') ? " GROUP BY e.equip_id, e.equip_name, e.measure" : " GROUP BY fd.fa_de_id, fd.fa_de_name";
            $order_by_sql = ($chart_mode === 'top_equipments') ? " ORDER BY count_requests DESC" : " ORDER BY fd.fa_de_name ASC";
            $limit_sql = ($chart_mode === 'top_equipments') ? " LIMIT 10" : "";
            break;
        
        // faculty_drilldown is now essentially disabled from direct click, but kept for completeness
        case 'faculty_drilldown':
            // This case might require its own specific logic based on drilldown_type
            // For now, it will use the date filter for the corresponding request type
            if ($drilldown_type == 'projects') {
                $date_filter_result = getDateFilteringClauses('dashboard_projects', $predefined_range, $specific_year, $specific_month, $specific_day);
            } elseif ($drilldown_type == 'facilities') {
                $date_filter_result = getDateFilteringClauses('dashboard_facilities_requests', $predefined_range, $specific_year, $specific_month, $specific_day);
            } elseif ($drilldown_type == 'equipments') {
                $date_filter_result = getDateFilteringClauses('dashboard_equipments_requests', $predefined_range, $specific_year, $specific_month, $specific_day);
            }
            if (!empty($date_filter_result['where_sql'])) {
                $where_clauses[] = ltrim($date_filter_result['where_sql'], ' AND ');
                $param_values = array_merge($param_values, $date_filter_result['param_values']);
                $param_types .= $date_filter_result['param_types'];
            }
            // Add specific faculty filter for the drilldown
            if (!empty($drilldown_id)) {
                $where_clauses[] = "u.fa_de_id = ?";
                $param_values[] = (int)$drilldown_id;
                $param_types .= 'i';
            }
            // No specific group by or order by for a single bar chart
            break;

        default:
            // Fallback for any other unexpected chart_mode
            break;
    }

    return [
        'where_clauses' => $where_clauses, // Return the array of conditions, NOT a string with " WHERE "
        'param_values' => $param_values,
        'param_types' => $param_types,
        'join_sql' => $join_sql,
        'group_by_sql' => $group_by_sql,
        'order_by_sql' => $order_by_sql,
        'limit_sql' => $limit_sql
    ];
}

// **Important**: The getDateFilteringClauses function (likely in admin-sorting.php)
// should remain as is (returning " AND " prefixed string for where_sql),
// because chart_sorting.php's ltrim() handles it.
// If it's redefined here, ensure it matches the original definition precisely.
if (!function_exists('getDateFilteringClauses')) {
    function getDateFilteringClauses($context, $predefined_range_select, $specific_year_select, $specific_month_select, $specific_day_select) {
        $where_clauses = [];
        $param_values = [];
        $param_types = '';
        $date_column = '';

        switch ($context) {
            case 'projects_admin':
            case 'dashboard_projects':
                $date_column = 'p.created_date';
                break;
            case 'buildings_admin':
            case 'dashboard_facilities_requests':
                $date_column = 'fr.request_date';
                break;
            case 'equipments_admin':
            case 'dashboard_equipments_requests':
                $date_column = 'er.request_date';
                break;
            default:
                $date_column = 'p.created_date';
                error_log("Unknown date filtering context: " . $context);
                break;
        }

        if (!empty($predefined_range_select)) {
            switch ($predefined_range_select) {
                case 'today':
                    $where_clauses[] = "DATE({$date_column}) = CURDATE()";
                    break;
                case 'this_week':
                    $where_clauses[] = "YEARWEEK({$date_column}, 1) = YEARWEEK(CURDATE(), 1)";
                    break;
                case 'this_month':
                    $where_clauses[] = "MONTH({$date_column}) = MONTH(CURDATE()) AND YEAR({$date_column}) = YEAR(CURDATE())";
                    break;
                case 'this_year':
                    $where_clauses[] = "YEAR({$date_column}) = YEAR(CURDATE())";
                    break;
            }
        } elseif (!empty($specific_year_select)) {
            $year = (int)$specific_year_select;
            $where_clauses[] = "YEAR({$date_column}) = ?";
            $param_values[] = $year;
            $param_types .= 'i';

            if (!empty($specific_month_select)) {
                $month = (int)$specific_month_select;
                $where_clauses[] = "MONTH({$date_column}) = ?";
                $param_values[] = $month;
                $param_types .= 'i';

                if (!empty($specific_day_select)) {
                    $day = (int)$specific_day_select;
                    $where_clauses[] = "DAY({$date_column}) = ?";
                    $param_values[] = $day;
                    $param_types .= 'i';
                }
            }
        }

        $where_sql = implode(" AND ", $where_clauses);
        if (!empty($where_sql)) {
            $where_sql = " AND " . $where_sql; // Prefix with AND if clauses exist
        }

        return [
            'where_sql' => $where_sql,
            'param_values' => $param_values,
            'param_types' => $param_types
        ];
    }
}
?>