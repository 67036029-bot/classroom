<?php
// เปิดแสดง Error (เอาไว้ดูตอนระบบมีปัญหา)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

if (isset($_POST['std_id'])) {
    $std_id = $_POST['std_id'];
    
    // 1. ดึงข้อมูลนักเรียน
    $sql_std = "SELECT * FROM tb_students WHERE id = '$std_id'";
    $res_std = $conn->query($sql_std);
    
    if($res_std->num_rows == 0) {
        echo "<div class='alert alert-danger'>ไม่พบข้อมูลนักเรียน (ID: $std_id)</div>";
        exit();
    }
    
    $std = $res_std->fetch_assoc();

    // 2. ดึงงานทั้งหมดที่เกี่ยวข้อง
    // ใช้ LEFT JOIN เพื่อดึงชื่อวิชาจาก tb_course_info มาด้วย
    $room = $std['room'];
    $parts = explode('/', $room);
    $grade = $parts[0]; // เช่น ม.6

    $sql_works = "SELECT w.*, c.subject_code, c.subject_name
                  FROM tb_work w
                  LEFT JOIN tb_course_info c ON w.course_id = c.id
                  WHERE w.target_room = 'all' 
                  OR w.target_room = 'grade:$grade' 
                  OR w.target_room = '$room'
                  ORDER BY w.course_id ASC, w.work_type ASC, w.work_id ASC";
                  
    $res_works = $conn->query($sql_works);

    if (!$res_works) {
        // ถ้า SQL ผิด ให้แจ้งเตือน
        echo "<div class='alert alert-danger'>Error Query: " . $conn->error . "</div>";
        exit();
    }

    // --- ส่วนแสดงผล HTML (ส่งกลับไปที่ Modal) ---
    ?>
    
    <div class="text-center mb-4">
        <h4 class="text-pink fw-bold mb-1"><?php echo $std['title'].$std['firstname']." ".$std['lastname']; ?></h4>
        <div class="mt-2">
            <span class="badge bg-dark">รหัส: <?php echo $std['std_code']; ?></span>
            <span class="badge bg-secondary">ห้อง: <?php echo $std['room']; ?></span>
            <span class="badge bg-secondary">เลขที่: <?php echo $std['std_no']; ?></span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ชื่องาน / รายวิชา</th>
                    <th width="80" class="text-center">เต็ม</th>
                    <th width="100" class="text-center">ผลลัพธ์</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $total_full = 0;
                $total_get = 0;
                $missing_count = 0;

                if ($res_works->num_rows > 0) {
                    while ($work = $res_works->fetch_assoc()) {
                        $wid = $work['work_id'];
                        
                        // ดึงคะแนน
                        $sql_score = "SELECT score_point FROM tb_score WHERE std_id = '$std_id' AND work_id = '$wid'";
                        $res_score = $conn->query($sql_score);
                        $score_row = $res_score->fetch_assoc();

                        $point = ($score_row) ? $score_row['score_point'] : null;
                        $is_missing = ($point === null);

                        $total_full += $work['full_score'];
                        if (!$is_missing) $total_get += $point;
                        if ($is_missing) $missing_count++;
                        
                        // Badge แสดงรหัสวิชา (ถ้ามี)
                        $subj_txt = $work['subject_code'] ? $work['subject_code'] : 'ทั่วไป';
                        $subj_badge = "<span class='badge bg-light text-dark border me-1' style='font-size:0.75rem'>$subj_txt</span>";

                        echo "<tr>";
                        echo "<td>
                                $subj_badge <span class='fw-bold text-dark small'>{$work['work_name']}</span>
                              </td>";
                        echo "<td class='text-center'>{$work['full_score']}</td>";
                        
                        if ($is_missing) {
                            echo "<td class='text-center'><span class='badge bg-danger'>ขาดส่ง</span></td>";
                        } else {
                            // ไฮไลท์คะแนน: น้อยกว่าครึ่งเป็นสีแดง
                            $txt_color = ($point < $work['full_score']/2) ? 'text-danger' : 'text-success';
                            echo "<td class='text-center fw-bold $txt_color fs-6'>$point</td>";
                        }
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='3' class='text-center py-3 text-muted'>ยังไม่มีงานที่มอบหมายให้นักเรียนคนนี้</td></tr>";
                }
                ?>
            </tbody>
            <tfoot class="table-light fw-bold">
                <tr>
                    <td class="text-end">รวมคะแนนสะสม</td>
                    <td class="text-center"><?php echo $total_full; ?></td>
                    <td class="text-center fs-5 text-pink"><?php echo $total_get; ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <?php if($missing_count > 0): ?>
        <div class="alert alert-danger text-center mb-0 shadow-sm border-0">
            <i class="fa-solid fa-triangle-exclamation"></i> นักเรียนคนนี้มีงานค้าง <strong><?php echo $missing_count; ?></strong> งาน
        </div>
    <?php else: ?>
        <div class="alert alert-success text-center mb-0 shadow-sm border-0">
            <i class="fa-solid fa-check-circle"></i> ส่งงานครบถ้วนยอดเยี่ยม!
        </div>
    <?php endif; ?>

    <?php
}
?>