<?php
// partials/fac_re_print.php
// ไฟล์นี้จะใช้ในการแสดงผลรายละเอียดคำร้องขอใช้สถานที่สำหรับการพิมพ์เท่านั้น
// คาดหวังตัวแปร $detail_item ซึ่งมีข้อมูลคำร้องขอสถานที่อยู่

if (!isset($detail_item) || empty($detail_item)) {
    echo '<p>ไม่พบข้อมูลคำร้องขอสถานที่สำหรับการพิมพ์</p>';
    return;
}
?>
<div class="print-section facility-details-print mb-4 page-break-inside-avoid">
    <h4 class="text-center mb-3">รายละเอียดคำร้องขอใช้สถานที่</h4>
    <table class="table table-bordered table-sm print-table">
        <tbody>
            <tr>
                <th style="width: 30%;">โครงการ:</th>
                <td><?php echo htmlspecialchars($detail_item['project_name']); ?></td>
            </tr>
            <tr>
                <th>ผู้ยื่นคำร้อง:</th>
                <td><?php echo htmlspecialchars($detail_item['user_name'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th>วันที่สร้างคำร้อง:</th>
                <td><?php echo (new DateTime($detail_item['request_date']))->format('d/m/Y H:i'); ?> น.</td>
            </tr>
            <tr>
                <th>สถานที่ที่ขอใช้งาน:</th>
                <td><?php echo htmlspecialchars($detail_item['facility_name']); ?></td>
            </tr>
            <tr>
                <th>สถานะคำร้อง:</th>
                <td><?php echo htmlspecialchars($detail_item['writed_status']); ?></td>
            </tr>
            <tr>
                <th>ช่วงเวลาเตรียมการ:</th>
                <td>
                    วันที่ <?php echo (new DateTime($detail_item['prepare_start_date']))->format('d/m/Y'); ?> ถึง
                    วันที่ <?php echo (new DateTime($detail_item['prepare_end_date']))->format('d/m/Y'); ?><br>
                    เวลา <?php echo (new DateTime($detail_item['prepare_start_time']))->format('H:i'); ?> น. ถึง
                    <?php echo (new DateTime($detail_item['prepare_end_time']))->format('H:i'); ?> น.
                </td>
            </tr>
            <tr>
                <th>ช่วงเวลาใช้งานจริง:</th>
                <td>
                    วันที่ <?php echo (new DateTime($detail_item['start_date']))->format('d/m/Y'); ?> ถึง
                    วันที่ <?php echo (new DateTime($detail_item['end_date']))->format('d/m/Y'); ?><br>
                    เวลา <?php echo (new DateTime($detail_item['start_time']))->format('H:i'); ?> น. ถึง
                    <?php echo (new DateTime($detail_item['end_time']))->format('H:i'); ?> น.
                </td>
            </tr>
            <tr>
                <th>ยินยอมให้ Reuse ป้ายไวนิลและวัสดุอื่น ๆ:</th>
                <td><?php echo ($detail_item['agree'] == 1) ? 'ยินยอม' : 'ไม่ยินยอม'; ?></td>
            </tr>
            <tr>
                <th>สถานะการอนุมัติ:</th>
                <td>
                    <?php
                    if (isset($detail_item['approve']) && !empty($detail_item['approve'])) {
                        echo htmlspecialchars($detail_item['approve']);
                    } else {
                        echo 'รอดำเนินการ';
                    }
                    ?>
                </td>
            </tr>
            <?php if (isset($detail_item['approve']) && !empty($detail_item['approve'] && $detail_item['approve'] !== 'ยกเลิก')): ?>
            <tr>
                <th>วันที่ดำเนินการ:</th>
                <td><?php echo htmlspecialchars(isset($detail_item['approve_date']) && $detail_item['approve_date'] ? (new DateTime($detail_item['approve_date']))->format('d/m/Y H:i') : 'N/A'); ?> น.</td>
            </tr>
            <tr>
                <th>ผู้ดำเนินการ:</th>
                <td><?php echo htmlspecialchars(isset($detail_item['staff_name']) ? ($detail_item['staff_name'] ?? 'N/A') : 'N/A'); ?></td>
            </tr>
                <?php if ($detail_item['approve'] == 'ไม่อนุมัติ' || ($detail_item['approve'] == 'อนุมัติ' && !empty($detail_item['approve_detail']))): ?>
                <tr>
                    <th>รายละเอียดการ
                        <?php echo ($detail_item['approve'] == 'อนุมัติ' ? 'อนุมัติ' : 'ไม่อนุมัติ'); ?>:
                    </th>
                    <td><?php echo nl2br(htmlspecialchars(isset($detail_item['approve_detail']) ? ($detail_item['approve_detail'] ?? 'ไม่ระบุเหตุผล') : 'ไม่ระบุเหตุผล')); ?></td>
                </tr>
                <?php endif; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>