<?php
include 'db.php';

$filter_room = isset($_POST['room']) ? $_POST['room'] : 'all';

// เตรียม Query
$sql_std = "SELECT * FROM tb_students";
if ($filter_room != 'all') {
    $sql_std .= " WHERE room = '" . $conn->real_escape_string($filter_room) . "'";
}
$sql_std .= " ORDER BY room ASC, std_no ASC";

$result = $conn->query($sql_std);
$found_count = 0;

// เริ่มสร้างตาราง
echo '<div class="table-responsive">';
echo '<table class="table table-hover align-middle border">';
echo '<thead class="table-danger"><tr>
        <th width="5%" class="text-center">#</th>
        <th width="10%" class="text-center">ห้อง</th>
        <th width="25%">ชื่อ - นามสกุล</th>
        <th width="15%" class="text-center">คะแนนรวม</th>
        <th width="15%" class="text-center">สถานะ</th>
        <th width="30%">งานที่ค้าง (ตัวอย่าง)</th>
      </tr></thead>';
echo '<tbody>';

if ($result->num_rows > 0) {
    while ($std = $result->fetch_assoc()) {
        $std_id = $std['id'];
        $room = $std['room'];
        $parts = explode('/', $room);
        $grade_level = $parts[0]; // เช่น ม.6

        // 1. คำนวณคะแนนรวม
        $sql_sum = "SELECT SUM(score_point) as total FROM tb_score WHERE std_id = '$std_id'";
        $total_score = $conn->query($sql_sum)->fetch_assoc()['total'] ?? 0;

        // *** เงื่อนไขสำคัญ: แสดงเฉพาะคนที่คะแนน < 50 ***
        if ($total_score < 50) {
            $found_count++;
            
            // 2. หางานที่ค้าง (เพื่อเอามาโชว์)
            $sql_works = "SELECT work_id, work_name FROM tb_work 
                          WHERE target_room = 'all' OR target_room = 'grade:$grade_level' OR target_room = '$room'";
            $res_works = $conn->query($sql_works);
            
            $missing_list = [];
            while($w = $res_works->fetch_assoc()) {
                $wid = $w['work_id'];
                $chk = $conn->query("SELECT score_point FROM tb_score WHERE std_id='$std_id' AND work_id='$wid'");
                // ถ้าไม่มี record หรือ record เป็น null คือค้าง
                if($chk->num_rows == 0) {
                    $missing_list[] = $w['work_name'];
                } else {
                    $s = $chk->fetch_assoc();
                    if($s['score_point'] === null) $missing_list[] = $w['work_name'];
                }
            }
            
            $missing_count = count($missing_list);
            // ตัดคำให้แสดงแค่ 2-3 งานแรก ถ้าเยอะเกิน
            $missing_text = "";
            if($missing_count > 0) {
                $shown_works = array_slice($missing_list, 0, 2);
                $missing_text = "<small class='text-muted'>" . implode(", ", $shown_works);
                if($missing_count > 2) $missing_text .= " และอีก ".($missing_count-2)." งาน";
                $missing_text .= "</small>";
            } else {
                $missing_text = "<span class='badge bg-success'>ส่งครบ</span>";
            }

            echo "<tr>";
            echo "<td class='text-center'>{$std['std_no']}</td>";
            echo "<td class='text-center'><span class='badge bg-secondary'>{$std['room']}</span></td>";
            echo "<td class='fw-bold'>{$std['title']}{$std['firstname']} {$std['lastname']}</td>";
            echo "<td class='text-center fs-5 fw-bold text-danger'>" . number_format($total_score, 0) . "</td>";
            echo "<td class='text-center'>";
                if($missing_count > 0) echo "<span class='badge bg-danger'>ค้าง $missing_count งาน</span>";
                else echo "<span class='badge bg-warning text-dark'>คะแนนสอบต่ำ</span>";
            echo "</td>";
            echo "<td>$missing_text</td>";
            echo "</tr>";
        }
    }
}

if ($found_count == 0) {
    echo "<tr><td colspan='6' class='text-center py-5 text-success fs-5'><i class='fa-solid fa-circle-check display-4 mb-3'></i><br>ยอดเยี่ยม! ไม่มีนักเรียนที่คะแนนต่ำกว่า 50 ในกลุ่มที่เลือก</td></tr>";
}

echo '</tbody></table></div>';
?>