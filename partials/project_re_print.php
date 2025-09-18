<?php
// partials/project_re_print.php
// ไฟล์นี้จะใช้ในการแสดงผลรายละเอียดโครงการสำหรับการพิมพ์เท่านั้น
// คาดหวังตัวแปร $project_detail_for_print ซึ่งมีข้อมูลโครงการอยู่

if (!isset($project_detail_for_print) || empty($project_detail_for_print)) {
    echo '<p>ไม่พบข้อมูลโครงการสำหรับการพิมพ์</p>';
    return;
}

?>
<div class="print-section project-details-print mb-4 page-break-inside-avoid">
    <h4 class="text-center mb-3">ข้อมูลโครงการ</h4>
    <table class="table table-bordered table-sm print-table">
        <tbody>
            <tr>
                <th style="width: 30%;">ชื่อโครงการ:</th>
                <td><?php echo htmlspecialchars($project_detail_for_print['project_name']); ?></td>
            </tr>
            <tr>
                <th>สถานะโครงการ:</th>
                <td><?php echo htmlspecialchars($project_detail_for_print['writed_status']); ?></td>
            </tr>
            <tr>
                <th>ประเภทกิจกรรม:</th>
                <td><?php echo htmlspecialchars($project_detail_for_print['activity_type_name'] ?? 'ไม่ระบุ'); ?></td>
            </tr>
            <tr>
                <th>ผู้ยื่นโครงการ:</th>
                <td><?php echo htmlspecialchars($project_detail_for_print['user_name'] ?? 'ไม่ระบุ'); ?></td>
            </tr>
            <tr>
                <th>วันที่สร้างโครงการ:</th>
                <td><?php echo formatThaiDate($project_detail_for_print['created_date']); ?></td>
            </tr>
            <tr>
                <th>ระยะเวลาโครงการ:</th>
                <td>
                    <?php if ($project_detail_for_print['start_date'] != $project_detail_for_print['end_date']) : ?>
                        ตั้งแต่วันที่ <?php echo formatThaiDate($project_detail_for_print['start_date'], false); ?> ถึง วันที่ <?php echo formatThaiDate($project_detail_for_print['end_date'], false); ?>
                    <?php else: ?>
                        วันที่ <?php echo formatThaiDate($project_detail_for_print['start_date'], false); ?>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>จำนวนผู้เข้าร่วม:</th>
                <td><?php echo htmlspecialchars($project_detail_for_print['attendee']); ?> คน</td>
            </tr>
            <tr>
                <th>หมายเลขโทรศัพท์:</th>
                <td><?php echo htmlspecialchars($project_detail_for_print['phone_num']); ?></td>
            </tr>
            <?php if (isset($project_detail_for_print['advisor_name']) && !empty($project_detail_for_print['advisor_name'])): ?>
            <tr>
                <th>ชื่อที่ปรึกษาโครงการ:</th>
                <td><?php echo htmlspecialchars($project_detail_for_print['advisor_name']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>รายละเอียดโครงการ:</th>
                <td><?php echo nl2br(htmlspecialchars($project_detail_for_print['project_des'])); ?></td>
            </tr>
            <?php if ($project_detail_for_print['files'] && file_exists($project_detail_for_print['files'])): ?>
            <tr>
                <th>ไฟล์แนบ:</th>
                <td><a href="<?php echo htmlspecialchars($project_detail_for_print['files']); ?>" target="_blank">ดูไฟล์แนบ</a></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>