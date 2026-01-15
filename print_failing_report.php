<?php
include 'db.php';
$filter_room = isset($_GET['room']) ? $_GET['room'] : 'all';
$date_now = date("d/m/Y H:i");

// ฟังก์ชันดึงข้อมูล (เหมือนไฟล์ get_failing_students แต่เราจะดึงมา loop แสดงผลแบบ Page Break)
function getFailingData($conn, $room_cond) {
    $data = [];
    $sql = "SELECT * FROM tb_students WHERE 1=1 $room_cond ORDER BY room ASC, std_no ASC";
    $res = $conn->query($sql);
    while($std = $res->fetch_assoc()){
        $std_id = $std['id'];
        
        // คะแนนรวม
        $score = $conn->query("SELECT SUM(score_point) as total FROM tb_score WHERE std_id='$std_id'")->fetch_assoc()['total'] ?? 0;
        
        if($score < 50){
            // งานค้าง
            $room = $std['room'];
            $parts = explode('/', $room); $grade = $parts[0];
            $works = $conn->query("SELECT work_id FROM tb_work WHERE target_room='all' OR target_room='grade:$grade' OR target_room='$room'");
            $miss = 0;
            while($w = $works->fetch_assoc()){
                $wid = $w['work_id'];
                $chk = $conn->query("SELECT score_point FROM tb_score WHERE std_id='$std_id' AND work_id='$wid'");
                if($chk->num_rows == 0) $miss++;
                else { $s=$chk->fetch_assoc(); if($s['score_point']===null) $miss++; }
            }
            
            $std['total_score'] = $score;
            $std['missing_count'] = $miss;
            $data[$std['room']][] = $std; // Group by Room
        }
    }
    return $data;
}

$condition = ($filter_room != 'all') ? "AND room = '".$conn->real_escape_string($filter_room)."'" : "";
$report_data = getFailingData($conn, $condition);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานนักเรียนกลุ่มเสี่ยง</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; font-size: 14px; margin: 0; padding: 20px; background: #eee; }
        .page-container { background: white; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 20mm; box-shadow: 0 0 10px rgba(0,0,0,0.1); box-sizing: border-box; position: relative; }
        
        h3, h4, p { margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; text-align: center; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-danger { color: red; font-weight: bold; }
        .header { text-align: center; margin-bottom: 20px; }
        
        .no-print { position: fixed; top: 20px; right: 20px; }
        .btn { background: #333; color: white; border: none; padding: 10px 20px; cursor: pointer; border-radius: 5px; text-decoration: none; }

        @media print {
            body { background: none; margin: 0; padding: 0; }
            .page-container { width: 100%; margin: 0; padding: 20mm; box-shadow: none; page-break-after: always; }
            .page-container:last-child { page-break-after: auto; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn">🖨️ พิมพ์ / บันทึก PDF</button>
    </div>

    <?php if (empty($report_data)): ?>
        <div class="page-container" style="text-align:center; padding-top: 100px;">
            <h3>ไม่พบข้อมูลนักเรียนกลุ่มเสี่ยง</h3>
            <p>นักเรียนทุกคนมีคะแนนผ่านเกณฑ์ 50 คะแนน</p>
        </div>
    <?php else: ?>
        <?php foreach ($report_data as $room_name => $students): ?>
            <div class="page-container">
                <div class="header">
                    <h3>รายงานสรุปผลการเรียนนักเรียนกลุ่มเสี่ยง (คะแนนต่ำกว่า 50)</h3>
                    <h4>ห้องเรียน: <?php echo $room_name; ?></h4>
                    <p style="font-size: 12px; margin-top: 5px;">ข้อมูล ณ วันที่: <?php echo $date_now; ?></p>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th width="10%">เลขที่</th>
                            <th width="15%">รหัส</th>
                            <th width="35%">ชื่อ - นามสกุล</th>
                            <th width="15%">คะแนนรวม</th>
                            <th width="25%">หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $std): ?>
                        <tr>
                            <td class="text-center"><?php echo $std['std_no']; ?></td>
                            <td class="text-center"><?php echo $std['std_code']; ?></td>
                            <td><?php echo $std['title'].$std['firstname']." ".$std['lastname']; ?></td>
                            <td class="text-center text-danger"><?php echo number_format($std['total_score'], 0); ?></td>
                            <td class="text-center">
                                <?php if($std['missing_count'] > 0): ?>
                                    ค้าง <?php echo $std['missing_count']; ?> งาน
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 50px; display: flex; justify-content: space-between; padding: 0 50px;">
                    <div style="text-align: center;">
                        <p>ลงชื่อ ....................................................... ครูผู้สอน</p>
                        <p>( <?php echo isset($_SESSION['teacher_name']) ? $_SESSION['teacher_name'] : '.......................................................'; ?> )</p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>