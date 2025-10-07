function formatThaiDateJS(date_str, include_time = true) {
    if (!date_str || date_str === '0000-00-00' || date_str === '0000-00-00 00:00:00') return "-";

    try {
        const dt = new Date(date_str);
        // ตรวจสอบว่าเป็น Invalid Date object หรือไม่
        if (isNaN(dt.getTime())) { 
            console.warn("Invalid date string passed to formatThaiDateJS:", date_str);
            return "-";
        }
        const thai_months = [
            "", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.",
            "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."
        ];
        const d = dt.getDate();
        const m = dt.getMonth() + 1; // getMonth() is 0-indexed
        const y = dt.getFullYear() + 543;
        let output = `${d} ${thai_months[m]} ${y}`;
        if (include_time) {
            const hours = dt.getHours().toString().padStart(2, '0');
            const minutes = dt.getMinutes().toString().padStart(2, '0');
            output += ` ${hours}:${minutes}`;
        }
        return output;
    } catch (e) {
        console.error("Error formatting date in JS:", date_str, e);
        return "-";
    }
}