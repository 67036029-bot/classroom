<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';
include 'functions.php';

// 1. ดึงระดับชั้นทั้งหมด (สำหรับ Export)
$all_grades = [];
$sql_rooms_all = "SELECT DISTINCT room FROM tb_students ORDER BY room ASC";
$res_rooms_all = $conn->query($sql_rooms_all);
while($r = $res_rooms_all->fetch_assoc()) {
    $parts = explode('/', $r['room']);
    $g = $parts[0]; // ม.2, ม.5
    if (!in_array($g, $all_grades)) { $all_grades[] = $g; }
}
sort($all_grades);
$res_rooms_all->data_seek(0);

// 2. จัดการตัวกรอง
$filter_room = isset($_GET['room']) ? $_GET['room'] : "";
if ($filter_room == "" && $res_rooms_all->num_rows > 0) {
    $filter_room = $res_rooms_all->fetch_assoc()['room'];
    $res_rooms_all->data_seek(0);
}

// 3. ดึงข้อมูล
$student_data = [];
$stats = ['level_3'=>0, 'level_2'=>0, 'level_1'=>0, 'total'=>0];

if ($filter_room != "") {
    $sql_std = "SELECT * FROM tb_students WHERE room = '$filter_room' ORDER BY std_no ASC";
    $result_std = $conn->query($sql_std);
    while ($std = $result_std->fetch_assoc()) {
        $sid = $std['id'];
        $sql_sum = "SELECT SUM(score_point) as total FROM tb_score WHERE std_id = '$sid'";
        $total_score = $conn->query($sql_sum)->fetch_assoc()['total'];
        if($total_score == "") $total_score = 0;

        $comp = calculateCompetency($total_score);
        
        $stats['total']++;
        if($comp == '3') $stats['level_3']++;
        elseif($comp == '2') $stats['level_2']++;
        else $stats['level_1']++;

        $std['total_score'] = $total_score;
        $std['comp'] = $comp;
        $student_data[] = $std;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ผลการประเมินสมรรถนะ</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 25px; }
        
        .stat-box {
            background: white; border-radius: 12px; padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.03); border-left: 5px solid transparent;
        }
        .stat-box.l3 { border-color: #198754; }
        .stat-box.l2 { border-color: #ffc107; }
        .stat-box.l1 { border-color: #dc3545; }
        
        .comp-table thead th { background-color: #212529; color: white; border: none; padding: 12px; }
        .comp-table tbody td { padding: 12px; vertical-align: middle; border-bottom: 1px solid #eee; }
        .level-badge { 
            width: 35px; height: 35px; display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%; font-weight: bold; color: white; font-size: 1rem;
        }
        .bg-l3 { background-color: #198754; }
        .bg-l2 { background-color: #ffc107; color: black; }
        .bg-l1 { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-star text-warning"></i> ประเมินสมรรถนะ</h3>
                    <small class="text-muted">คำนวณอัตโนมัติจากคะแนนรวม</small>
                </div>
                
                <div class="d-flex gap-2">
                    <form method="GET" class="d-flex bg-white p-1 rounded border shadow-sm">
                        <select name="room" class="form-select form-select-sm border-0 fw-bold" onchange="this.form.submit()" style="width: 120px;">
                            <?php 
                            $res_rooms_all->data_seek(0);
                            while($r = $res_rooms_all->fetch_assoc()) {
                                $sel = ($filter_room == $r['room']) ? "selected" : "";
                                echo "<option value='{$r['room']}' $sel>{$r['room']}</option>";
                            }
                            ?>
                        </select>
                    </form>
                    
                    <div class="dropdown">
                        <button class="btn btn-success btn-sm shadow-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <ul class="dropdown-menu">
                            <?php foreach($all_grades as $g): ?>
                                <li>
                                    <a class="dropdown-item" href="export_competency.php?grade=<?php echo $g; ?>">
                                        📥 ดาวน์โหลด <?php echo $g; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-box l3 d-flex justify-content-between align-items-center">
                        <div><h6 class="text-success fw-bold mb-0">ดีเยี่ยม (3)</h6><small class="text-muted">คะแนน 70-100</small></div>
                        <h2 class="fw-bold text-success mb-0"><?php echo $stats['level_3']; ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box l2 d-flex justify-content-between align-items-center">
                        <div><h6 class="text-warning fw-bold mb-0">ผ่านเกณฑ์ (2)</h6><small class="text-muted">คะแนน 60-69</small></div>
                        <h2 class="fw-bold text-warning mb-0"><?php echo $stats['level_2']; ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-box l1 d-flex justify-content-between align-items-center">
                        <div><h6 class="text-danger fw-bold mb-0">ปรับปรุง (1)</h6><small class="text-muted">ต่ำกว่า 60</small></div>
                        <h2 class="fw-bold text-danger mb-0"><?php echo $stats['level_1']; ?></h2>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <table class="table comp-table table-hover mb-0 text-center">
                        <thead>
                            <tr>
                                <th width="80">เลขที่</th>
                                <th class="text-start">ชื่อ - นามสกุล</th>
                                <th width="120">คะแนนรวม</th>
                                <th width="200">คุณลักษณะ<br>อันพึงประสงค์</th>
                                <th width="200">อ่าน คิดวิเคราะห์<br>และเขียน</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($student_data) > 0): foreach($student_data as $std): ?>
                                <tr>
                                    <td class="text-muted fw-bold"><?php echo $std['std_no']; ?></td>
                                    <td class="text-start fw-bold text-dark"><?php echo $std['title'].$std['firstname']." ".$std['lastname']; ?></td>
                                    <td class="text-muted small"><?php echo number_format($std['total_score'], 0); ?></td>
                                    <td><span class="level-badge bg-l3">3</span></td>
                                    <td><span class="level-badge bg-l<?php echo $std['comp']; ?>"><?php echo $std['comp']; ?></span></td>
                                </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="5" class="py-5 text-muted">ไม่พบข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>