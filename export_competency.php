<?php
session_start();
include 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { exit("Access Denied"); }

// รับค่าระดับชั้น (เช่น ม.5)
$grade_level = isset($_GET['grade']) ? $_GET['grade'] : '';
if ($grade_level == "") exit("กรุณาระบุระดับชั้น");

// ตั้งชื่อไฟล์
$filename = "Competency_" . str_replace('.', '', $grade_level) . "_" . date("Y-m-d") . ".xls";

// Header Excel
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header("Content-Description: File Transfer");

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head>';
echo '<body>';

function calculateCompetency($total_score) {
    if ($total_score >= 70) return "3"; elseif ($total_score >= 60) return "2"; else return "1";
}

// ดึงนักเรียนทั้งระดับชั้น (ใช้ LIKE 'ม.5%') และเรียงตามห้อง -> เลขที่
$sql_std = "SELECT * FROM tb_students WHERE room LIKE '$grade_level%' ORDER BY room ASC, std_no ASC";
$result_std = $conn->query($sql_std);
?>

<table border="1">
    <thead>
        <tr>
            <th colspan="5" style="background-color: #f8f9fa; height: 40px; font-size: 16px; text-align: center; vertical-align: middle;">
                สรุปผลการประเมินสมรรถนะ ระดับชั้น <?php echo $grade_level; ?> - วิชา <?php echo $SubjectName; ?>
            </th>
        </tr>
        <tr style="text-align: center;">
            <th width="100" style="background-color: #212529; color: white;">ชั้นเรียน</th>
            <th width="60" style="background-color: #212529; color: white;">เลขที่</th>
            <th width="200" style="background-color: #212529; color: white;">ชื่อ - สกุล</th>
            <th width="150" style="background-color: #d63384; color: white;">คุณลักษณะ<br>อันพึงประสงค์</th>
            <th width="150" style="background-color: #d63384; color: white;">อ่าน คิดวิเคราะห์<br>และเขียน</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($result_std->num_rows > 0) {
            while ($std = $result_std->fetch_assoc()) {
                $sid = $std['id'];
                
                // คำนวณคะแนน
                $sql_sum = "SELECT SUM(score_point) as total FROM tb_score WHERE std_id = '$sid'";
                $row_sum = $conn->query($sql_sum)->fetch_assoc();
                $total_score = ($row_sum['total'] == "") ? 0 : $row_sum['total'];

                $comp_read = calculateCompetency($total_score);
                $comp_attr = "3"; 

                echo "<tr>";
                echo "<td style='text-align: center;'>{$std['room']}</td>";
                echo "<td style='text-align: center;'>{$std['std_no']}</td>";
                echo "<td>{$std['title']}{$std['firstname']} {$std['lastname']}</td>";
                echo "<td style='text-align: center; font-weight: bold;'>$comp_attr</td>";
                echo "<td style='text-align: center; font-weight: bold;'>$comp_read</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5' style='text-align:center;'>ไม่พบข้อมูลนักเรียน</td></tr>";
        }
        ?>
    </tbody>
</table>
</body>
</html>