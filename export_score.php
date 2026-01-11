<?php
// ล้าง Buffer ป้องกันไฟล์เสีย
if (ob_get_length()) ob_clean();

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { exit("Access Denied"); }
include 'db.php';

// 1. ตรวจสอบค่าที่รับมา
$target_room = isset($_GET['room']) ? $_GET['room'] : "";
$target_grade = isset($_GET['grade']) ? $_GET['grade'] : "";

// ตัวแปรสำหรับ Query
$work_filter_sql = "";
$student_filter_sql = "";
$filename_prefix = "";
$display_text = "";

if ($target_room != "") {
    // --- กรณี A: ดาวน์โหลดรายห้อง (เช่น ม.5/1) ---
    // แยก "ม.5" ออกมาจาก "ม.5/1"
    $parts = explode('/', $target_room);
    $grade_text = $parts[0]; // ได้ "ม.5"
    
    // ดึงนักเรียน: เฉพาะห้องนี้
    $student_filter_sql = "AND room = '$target_room'";
    
    // ดึงงาน: 
    // 1. งานส่วนกลาง (all) 
    // 2. งานระดับชั้น (grade:ม.5) *แก้จุดนี้*
    // 3. งานห้องนี้ (ม.5/1)
    $work_filter_sql = "AND (target_room = 'all' OR target_room = 'grade:$grade_text' OR target_room = '$target_room')";
    
    $filename_prefix = "Score_Room_{$target_room}";
    $display_text = "ห้อง " . $target_room;

} elseif ($target_grade != "") {
    // --- กรณี B: ดาวน์โหลดทั้งระดับชั้น (เช่น ม.5) ---
    // ค่าที่ส่งมาคือ "ม.5" อยู่แล้ว ใช้ได้เลย
    $grade_text = $target_grade;
    
    // ดึงนักเรียน: ห้องที่ขึ้นต้นด้วย "ม.5"
    $student_filter_sql = "AND room LIKE '$grade_text%'";
    
    // ดึงงาน: 
    // 1. งานส่วนกลาง (all)
    // 2. งานระดับชั้น (grade:ม.5) *แก้จุดนี้*
    // 3. งานที่เจาะจงห้องในระดับนี้ (เช่น ม.5/...)
    $work_filter_sql = "AND (target_room = 'all' OR target_room = 'grade:$grade_text' OR target_room LIKE '$grade_text%')";
    
    $filename_prefix = "Score_Grade_{$grade_text}";
    $display_text = "ระดับชั้น " . $grade_text;
    
} else {
    exit("Error: ไม่พบข้อมูลห้องหรือระดับชั้น");
}

// ตั้งชื่อไฟล์ (รองรับภาษาไทย)
$filename = "Score_Form_" . date('Ymd_Hi') . ".xls";

// Header Excel
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // BOM for UTF-8 (สำคัญมาก เพื่อให้ Excel อ่านไทยออก)

// 2. ดึงข้อมูลงาน (Assignments) -> เพื่อทำหัวตาราง
$works = [];
// ใช้ DISTINCT ป้องกันงานซ้ำ
$sql_work = "SELECT DISTINCT * FROM tb_work WHERE 1=1 $work_filter_sql ORDER BY work_type ASC, work_id ASC";
$res_work = $conn->query($sql_work);
if($res_work){
    while($w = $res_work->fetch_assoc()) {
        $works[] = $w;
    }
}

// 3. ดึงข้อมูลนักเรียน
$students = [];
$sql_std = "SELECT * FROM tb_students WHERE 1=1 $student_filter_sql ORDER BY room ASC, std_no ASC";
$res_std = $conn->query($sql_std);
if($res_std){
    while($s = $res_std->fetch_assoc()) {
        $students[] = $s;
    }
}

// 4. ดึงคะแนนเดิม (ถ้ามี) มาใส่ให้ด้วย
$scores = [];
if (!empty($students)) {
    $std_ids = array_column($students, 'id');
    if(!empty($std_ids)) {
        $ids_str = implode(',', $std_ids);
        $sql_sc = "SELECT * FROM tb_score WHERE std_id IN ($ids_str)";
        $res_sc = $conn->query($sql_sc);
        if($res_sc){
            while($sc = $res_sc->fetch_assoc()) {
                $scores[$sc['std_id']][$sc['work_id']] = $sc['score_point'];
            }
        }
    }
}

// --- สร้าง HTML Table ---
?>
<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        body { font-family: 'Sarabun', sans-serif, Tahoma; font-size: 14px; }
        table { border-collapse: collapse; width: 100%; }
        th { background-color: #212529; color: white; border: 1px solid #000; padding: 10px; text-align: center; vertical-align: middle; height: 50px; }
        td { border: 1px solid #000; padding: 5px; vertical-align: middle; }
        .text-center { text-align: center; }
        .head-work { background-color: #d63384; color: white; font-weight: bold; }
        .head-info { background-color: #495057; color: white; }
    </style>
</head>
<body>
    <h3>แบบฟอร์มบันทึกคะแนน: <?php echo $display_text; ?></h3>
    <table>
        <thead>
            <tr>
                <th class="head-info" width="80">ห้อง</th>
                <th class="head-info" width="60">เลขที่</th>
                <th class="head-info" width="100">รหัสนักเรียน</th>
                <th class="head-info" width="200">ชื่อ - นามสกุล</th>
                
                <?php foreach($works as $w): ?>
                    <th class="head-work" width="120">
                        <?php echo $w['work_name']; ?><br>
                        [ID:<?php echo $w['work_id']; ?>]<br>
                        <span style="font-size:10px;">(เต็ม <?php echo floatval($w['full_score']); ?>)</span>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach($students as $std): ?>
            <tr>
                <td class="text-center" style="mso-number-format:'@'"><?php echo $std['room']; ?></td>
                <td class="text-center"><?php echo $std['std_no']; ?></td>
                <td class="text-center" style="mso-number-format:'@'"><?php echo $std['std_code']; ?></td>
                <td><?php echo $std['title'].$std['firstname'].' '.$std['lastname']; ?></td>
                
                <?php foreach($works as $w): 
                    $val = isset($scores[$std['id']][$w['work_id']]) ? $scores[$std['id']][$w['work_id']] : "";
                ?>
                    <td class="text-center"><?php echo $val; ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>