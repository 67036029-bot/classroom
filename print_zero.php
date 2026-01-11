<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { exit("Access Denied"); }
include 'db.php';
include 'functions.php';

$room = isset($_GET['room']) ? $_GET['room'] : "";
if ($room == "") exit("กรุณาระบุห้อง");


$sql_std = "SELECT * FROM tb_students WHERE room = '$room' ORDER BY std_no ASC";
$result_std = $conn->query($sql_std);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายชื่อนักเรียนติด 0 - ห้อง <?php echo $room; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: white; color: black; }
        .header { text-align: center; margin-bottom: 30px; }
        .table thead th { border-bottom: 2px solid black; text-align: center; }
        .table td, .table th { border: 1px solid black; vertical-align: middle; }
        .signature-box { margin-top: 50px; display: flex; justify-content: space-around; }
        .sig-line { border-bottom: 1px dotted black; width: 200px; display: inline-block; margin-bottom: 5px; }
        
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 20px; }
        }
    </style>
</head>
<body onload="window.print()"> <div class="container mt-4">
        
        <div class="no-print mb-4 text-center">
            <div class="alert alert-info d-inline-block">
                <i class="fa-solid fa-print"></i> ระบบจะสั่งพิมพ์อัตโนมัติ หากไม่ขึ้นให้กด Ctrl+P<br>
                ต้องการ Save เป็น PDF ให้เลือกปลายทางเครื่องพิมพ์เป็น <strong>"Save as PDF"</strong>
            </div>
        </div>

        <div class="header">
            <h4 class="fw-bold">บัญชีรายชื่อนักเรียนที่มีผลการเรียน 0 (ติดศูนย์)</h4>
            <h5 class="fw-bold">รายวิชา <?php echo $SubjectName; ?> (<?php echo $SubjectCode; ?>)</h5>
            <p>ภาคเรียนที่ <?php echo $Semester; ?> ปีการศึกษา <?php echo $AcadYear; ?> | ห้องเรียน <?php echo $room; ?></p>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr class="table-light">
                    <th width="60">เลขที่</th>
                    <th width="120">รหัสนักเรียน</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th width="100">คะแนนรวม</th>
                    <th width="100">ผลการเรียน</th>
                    <th width="200">หมายเหตุ / ลงชื่อรับทราบ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $found = false;
                if ($result_std->num_rows > 0) {
                    while ($std = $result_std->fetch_assoc()) {
                        $sid = $std['id'];
                        $sql_sum = "SELECT SUM(score_point) as total FROM tb_score WHERE std_id = '$sid'";
                        $total = $conn->query($sql_sum)->fetch_assoc()['total'];
                        if($total == "") $total = 0;
                        
                        $grade = calculateGrade($total);

                        // แสดงเฉพาะเกรด 0
                        if ($grade == "0") {
                            $found = true;
                            echo "<tr>";
                            echo "<td class='text-center'>{$std['std_no']}</td>";
                            echo "<td class='text-center'>{$std['std_code']}</td>";
                            echo "<td>{$std['title']}{$std['firstname']} {$std['lastname']}</td>";
                            echo "<td class='text-center fw-bold'>$total</td>";
                            echo "<td class='text-center fw-bold text-danger'>0</td>";
                            echo "<td></td>"; // เว้นว่างให้เซ็นชื่อ
                            echo "</tr>";
                        }
                    }
                }
                
                if (!$found) {
                    echo "<tr><td colspan='6' class='text-center py-4'>- ไม่มีนักเรียนติด 0 ในห้องนี้ -</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <?php if($found): ?>
        <div class="signature-box">
            <div class="text-center">
                ลงชื่อ ........................................................... ครูผู้สอน<br>
                ( <?php echo $TeacherName; ?> )<br>
                วันที่ ........./........./.............
            </div>
            <div class="text-center">
                ลงชื่อ ........................................................... ครูที่ปรึกษา<br>
                (...........................................................)<br>
                รับทราบข้อมูลนักเรียน
            </div>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>