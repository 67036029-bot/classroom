<?php
// ปิด Error Report ในไฟล์นี้ เพื่อกันไม่ให้ข้อความ Error แทรกเข้าไปทำลายไฟล์ Excel
error_reporting(0);
ini_set('display_errors', 0);

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { exit("Access Denied"); }
include 'db.php';

// 1. รับค่า Filter
$filter_grade = isset($_GET['grade']) ? $_GET['grade'] : "";

// 2. ดึง Config ของวิชา (เพื่อเช็คโหมดการคำนวณ)
$sql_info = "SELECT * FROM tb_course_info LIMIT 1";
$res_info = $conn->query($sql_info);
$course_info = $res_info->fetch_assoc();

// เช็คโหมด (1=Weight, 0=Direct)
$CALC_MODE = isset($course_info['calc_mode']) ? intval($course_info['calc_mode']) : 1;

// เตรียมตัวแปรน้ำหนัก
$weight_map = [
    1 => $course_info['weight_k1'],
    2 => $course_info['weight_k2'],
    3 => $course_info['weight_mid'],
    4 => $course_info['weight_final']
];

// 3. ตั้งค่า Header สำหรับดาวน์โหลด Excel
$filename = "grade_report_" . date('Ymd_His') . ".xls";

// เคลียร์ Buffer ก่อนส่ง Header
if (ob_get_length()) ob_clean();

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 4. คำนวณคะแนนเต็มดิบ (Max Raw Score)
$max_scores = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$sql_max = "SELECT work_type, SUM(full_score) as total_max 
            FROM tb_work 
            GROUP BY work_type";
$res_max = $conn->query($sql_max);
while ($m = $res_max->fetch_assoc()) {
    $max_scores[$m['work_type']] = $m['total_max'];
}
foreach($max_scores as $k => $v) { if($v == 0) $max_scores[$k] = 1; }

// 5. ดึงข้อมูลนักเรียนและคะแนน
$where_grade = "";
if($filter_grade != "") {
    $where_grade = "AND s.room LIKE '$filter_grade%'";
}

// 🔴 [FIXED] เพิ่ม s.title เข้าไปใน SQL เพื่อแก้ปัญหา Undefined array key
$sql_data = "SELECT s.id, s.std_code, s.title, s.firstname, s.lastname, s.room, s.std_no,
             sc.work_id, sc.score_point, w.work_type
             FROM tb_students s
             LEFT JOIN tb_score sc ON s.id = sc.std_id
             LEFT JOIN tb_work w ON sc.work_id = w.work_id
             WHERE 1=1 $where_grade
             ORDER BY s.room ASC, s.std_no ASC";
$res_data = $conn->query($sql_data);

$students = [];
while($row = $res_data->fetch_assoc()) {
    $sid = $row['id'];
    if(!isset($students[$sid])) {
        // ใช้ Null Coalescing Operator (??) กันเหนียว
        $title = $row['title'] ?? ''; 
        $students[$sid] = [
            'no' => $row['std_no'],
            'code' => $row['std_code'],
            'name' => $title . $row['firstname'] . ' ' . $row['lastname'],
            'room' => $row['room'],
            'raw' => [1=>0, 2=>0, 3=>0, 4=>0]
        ];
    }
    if($row['work_type']) {
        $students[$sid]['raw'][$row['work_type']] += floatval($row['score_point']);
    }
}

// ฟังก์ชันตัดเกรด
function calGrade($score) {
    if ($score >= 80) return '4';
    if ($score >= 75) return '3.5';
    if ($score >= 70) return '3';
    if ($score >= 65) return '2.5';
    if ($score >= 60) return '2';
    if ($score >= 55) return '1.5';
    if ($score >= 50) return '1';
    return '0';
}

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
echo '<style>
    table { border-collapse: collapse; width: 100%; font-family: Tahoma, sans-serif; font-size: 14px; }
    th { background-color: #f0f0f0; border: 1px solid #000; padding: 10px; text-align: center; font-weight: bold; }
    td { border: 1px solid #000; padding: 5px; vertical-align: middle; }
    .text-center { text-align: center; }
    .bg-head { background-color: #d63384; color: white; }
</style></head><body>';

echo "<h3>รายงานผลการเรียน {$course_info['subject_code']} {$course_info['subject_name']}</h3>";
echo "<p>โหมดการคำนวณ: <strong>" . (($CALC_MODE == 1) ? 'คิดตามสัดส่วน (%)' : 'คิดคะแนนตามจริง (Raw Score)') . "</strong></p>";

echo '<table><thead><tr class="bg-head">';
echo '<th width="50">ห้อง</th>';
echo '<th width="50">เลขที่</th>';
echo '<th width="100">รหัส</th>';
echo '<th width="200">ชื่อ - นามสกุล</th>';

if($CALC_MODE == 1):
    echo '<th width="80">เก็บ 1 ('.$weight_map[1].'%)</th>';
    echo '<th width="80">เก็บ 2 ('.$weight_map[2].'%)</th>';
    echo '<th width="80">กลางภาค ('.$weight_map[3].'%)</th>';
    echo '<th width="80">ปลายภาค ('.$weight_map[4].'%)</th>';
else:
    echo '<th width="80">เก็บ 1 (เต็ม '.$max_scores[1].')</th>';
    echo '<th width="80">เก็บ 2 (เต็ม '.$max_scores[2].')</th>';
    echo '<th width="80">กลางภาค (เต็ม '.$max_scores[3].')</th>';
    echo '<th width="80">ปลายภาค (เต็ม '.$max_scores[4].')</th>';
endif;

echo '<th width="80">รวม (100)</th>';
echo '<th width="60">เกรด</th>';
echo '</tr></thead><tbody>';

foreach($students as $std): 
    $final = [];
    $raw = $std['raw'];

    if ($CALC_MODE == 1) { 
        $final[1] = ($raw[1] / $max_scores[1]) * $weight_map[1];
        $final[2] = ($raw[2] / $max_scores[2]) * $weight_map[2];
        $final[3] = ($raw[3] / $max_scores[3]) * $weight_map[3];
        $final[4] = ($raw[4] / $max_scores[4]) * $weight_map[4];
    } else {
        $final[1] = $raw[1];
        $final[2] = $raw[2];
        $final[3] = $raw[3];
        $final[4] = $raw[4];
    }
    
    $total = array_sum($final);
    $grade = calGrade(round($total));

    echo '<tr>';
    echo '<td class="text-center">'.$std['room'].'</td>';
    echo '<td class="text-center">'.$std['no'].'</td>';
    echo '<td class="text-center" style="mso-number-format:\'@\'">'.$std['code'].'</td>';
    echo '<td>'.$std['name'].'</td>';
    
    echo '<td class="text-center">'.round($final[1]).'</td>';
    echo '<td class="text-center">'.round($final[2]).'</td>';
    echo '<td class="text-center">'.round($final[3]).'</td>';
    echo '<td class="text-center">'.round($final[4]).'</td>';
    
    echo '<td class="text-center" style="background-color:#ffe6e6;"><b>'.round($total).'</b></td>';
    echo '<td class="text-center" style="background-color:#e6ffe6;"><b>'.$grade.'</b></td>';
    echo '</tr>';
endforeach;

echo '</tbody></table></body></html>';
?>