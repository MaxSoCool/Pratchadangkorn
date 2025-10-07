<?
function formatThaiDate($date_str, $include_time = true, $include_seconds = false) {
    if (empty($date_str) || $date_str === '0000-00-00' || $date_str === '0000-00-00 00:00:00') return "-";
    
    try {
        $dt = new DateTime($date_str);
    } catch (Exception $e) {
        error_log("Invalid date string for formatThaiDate: " . $date_str . " - " . $e->getMessage());
        return "-";
    }

    $thai_months = [
        "", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
        "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
    ];
    $d = (int)$dt->format('j');
    $m = (int)$dt->format('n');
    $y = (int)$dt->format('Y') + 543; 
    $output = "{$d} {$thai_months[$m]} {$y}";
    if ($include_time) {
        $time_format = 'H:i';
        if ($include_seconds) {
            $time_format .= ':s';
        }
        $time = $dt->format($time_format);
        $output .= " {$time}";
    }
    return $output;
}
?>