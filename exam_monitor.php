<?php
session_start();
// ตรวจสอบสิทธิ์ (Security)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

$exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : 0;
$search_query = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : ''; 

// --- LOGIC: BULK RESET (คงเดิม) ---
if (isset($_POST['btn_bulk_reset']) && $exam_id > 0) {
    if (isset($_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
        $count = 0;
        $work_res = $conn->query("SELECT work_id FROM tb_exam_sets WHERE exam_id = '$exam_id'");
        $work_id = ($work_res->num_rows > 0) ? $work_res->fetch_assoc()['work_id'] : 0;

        foreach ($_POST['selected_ids'] as $std_id) {
            $std_id = intval($std_id);
            $conn->query("DELETE FROM tb_exam_results WHERE exam_id = '$exam_id' AND std_id = '$std_id'");
            $conn->query("DELETE FROM tb_exam_answer_log WHERE exam_id = '$exam_id' AND std_id = '$std_id'");
            if ($work_id > 0) {
                $conn->query("DELETE FROM tb_score WHERE work_id = '$work_id' AND std_id = '$std_id'");
            }
            $count++;
        }
        echo "<script>alert('✅ รีเซ็ตการสอบเรียบร้อย $count คน'); window.location='exam_monitor.php?exam_id=$exam_id';</script>";
    }
}

// --- Logic SQL (คงเดิม) ---
$exam_info = null;
$target_room_sql = "1=0";

if ($exam_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM tb_exam_sets WHERE exam_id = ?");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $exam_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($exam_info) {
        $work_id = $exam_info['work_id'];
        $work_data = $conn->query("SELECT target_room FROM tb_work WHERE work_id = '$work_id'")->fetch_assoc();
        if ($work_data) {
            $target = $work_data['target_room'];
            if ($target == 'all') { $target_room_sql = "1=1"; } 
            elseif (strpos($target, 'grade:') !== false) { $grade_prefix = str_replace('grade:', '', $target); $target_room_sql = "s.room LIKE '$grade_prefix%'"; } 
            else { $target_room_sql = "s.room = '$target'"; }
        }
    }
}

$sql_exams = "SELECT e.*, w.work_name, c.subject_code FROM tb_exam_sets e LEFT JOIN tb_work w ON e.work_id = w.work_id LEFT JOIN tb_course_info c ON w.course_id = c.id ORDER BY e.exam_id DESC";
$res_exams = $conn->query($sql_exams);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ห้องควบคุมการสอบ (Live)</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; overflow-x: hidden; }
        .sidebar-container { display: flex; flex-wrap: nowrap; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 20px; height: 100vh; overflow-y: auto; }
        
        /* Stats Strip */
        .stats-strip {
            background: white; border-radius: 50px; padding: 10px 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; border: 1px solid #eef2f5;
        }
        .stat-item { display: flex; align-items: center; gap: 15px; position: relative; }
        .stat-item:not(:last-child)::after {
            content: ''; position: absolute; right: -30px; height: 30px; width: 1px; background: #e0e0e0;
        }
        .stat-icon-box {
            width: 45px; height: 45px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
        }
        .s-blue { background: #e7f1ff; color: #0d6efd; }
        .s-yellow { background: #fff8d6; color: #ffc107; }
        .s-green { background: #d1e7dd; color: #198754; }
        .s-red { background: #ffe6e6; color: #dc3545; }
        
        .stat-info h5 { font-weight: 800; margin: 0; line-height: 1; font-size: 1.4rem; }
        .stat-info span { font-size: 0.8rem; color: #6c757d; font-weight: 600; }

        /* Table */
        .card-table { border-radius: 16px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.05); overflow: hidden; }
        .table-custom thead th {
            background-color: #212529; color: white;
            font-weight: 600; border: none; padding: 12px 15px;
            white-space: nowrap; position: sticky; top: 0; z-index: 10;
        }
        .table-custom tbody td { 
            vertical-align: middle; padding: 10px 15px; border-bottom: 1px solid #f0f0f0; 
            white-space: nowrap;
        }
        .table-custom tbody tr:hover { background-color: #f8f9fa; }
        .room-header td { background: #e9ecef; font-weight: 800; padding: 8px 20px; font-size: 0.95rem; color: #495057; }

        /* Badges */
        .status-badge {
            padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: bold;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .st-wait { background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; }
        .st-done { background-color: #d1e7dd; color: #0f5132; }
        .st-doing { 
            background-color: #fff3cd; color: #856404; border: 1px solid #ffecb5;
            animation: pulse-yellow 2s infinite;
        }
        .dot-blink { width: 6px; height: 6px; background-color: #ffc107; border-radius: 50%; display: inline-block; }
        @keyframes pulse-yellow { 0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); } 100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); } }

        .cheat-badge {
            background-color: #fff0f0; color: #dc3545; border: 1px solid #ffc9c9;
            padding: 4px 10px; border-radius: 6px; font-size: 0.75rem; font-weight: bold;
            display: inline-flex; align-items: center; gap: 5px;
        }
        .row-cheat { background-color: #fff5f5 !important; }
        .row-cheat td:first-child { border-left: 4px solid #dc3545; }

        /* Checkbox */
        .form-check-input.chk-reset { cursor: pointer; border: 2px solid #adb5bd; }
        .form-check-input.chk-reset:checked { background-color: #dc3545; border-color: #dc3545; }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-tower-broadcast text-danger"></i> ห้องควบคุม</h4>
                    
                    <form method="GET" action="exam_monitor.php" class="d-flex align-items-center">
                        <select name="exam_id" class="form-select form-select-sm border-secondary fw-bold" style="width: 250px;" onchange="this.form.submit()">
                            <option value="0">-- เลือกชุดข้อสอบ --</option>
                            <?php 
                            $res_exams->data_seek(0);
                            while($ex = $res_exams->fetch_assoc()): 
                                $sel = ($exam_id == $ex['exam_id']) ? "selected" : "";
                                echo "<option value='{$ex['exam_id']}' $sel>[{$ex['subject_code']}] {$ex['exam_name']}</option>";
                            endwhile; ?>
                        </select>
                    </form>

                    <?php if($exam_id > 0): ?>
                    <form method="GET" action="exam_monitor.php" class="d-flex align-items-center">
                        <div class="input-group input-group-sm ms-2" style="width: 200px;">
                            <input type="text" name="search" class="form-control" placeholder="ค้นหาชื่อ..." value="<?php echo htmlspecialchars($search_query); ?>">
                            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                            <button class="btn btn-secondary"><i class="fa-solid fa-search"></i></button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <?php if($exam_id > 0): ?>
                        
                        <a href="regrade_exam.php?exam_id=<?php echo $exam_id; ?>" 
                           class="btn btn-warning rounded-pill px-3 shadow-sm btn-sm fw-bold text-dark border-warning" 
                           onclick="return confirm('⚠️ ยืนยันการคำนวณคะแนนใหม่?\nระบบจะตรวจคำตอบของนักเรียนทุกคนใหม่อีกครั้งตามเฉลยปัจจุบัน');">
                            <i class="fa-solid fa-rotate me-1"></i> ตรวจใหม่
                        </a>

                        <a href="exam_analysis.php?exam_id=<?php echo $exam_id; ?>" 
                           class="btn btn-dark rounded-pill px-3 shadow-sm btn-sm fw-bold">
                            <i class="fa-solid fa-chart-pie me-1"></i> วิเคราะห์
                        </a>

                    <?php endif; ?>
                </div>
            </div>

            <?php if ($exam_id > 0 && $exam_info): ?>
                
                <?php
                // คำนวณสถิติ
                $sql_stats = "SELECT 
                                COUNT(std_id) as stat_all,
                                SUM(CASE WHEN status=1 THEN 1 ELSE 0 END) as stat_done,
                                SUM(CASE WHEN status=0 THEN 1 ELSE 0 END) as stat_doing
                              FROM tb_exam_results WHERE exam_id='$exam_id'";
                $stats = $conn->query($sql_stats)->fetch_assoc();
                $cheat_total = $conn->query("SELECT SUM(cheat_count) as c FROM tb_exam_results WHERE exam_id = '$exam_id'")->fetch_assoc()['c'] ?? 0;
                ?>
                
                <div class="stats-strip">
                    <div class="stat-item">
                        <div class="stat-icon-box s-blue"><i class="fa-solid fa-users"></i></div>
                        <div class="stat-info"><h5><?php echo $stats['stat_all']; ?></h5><span>เข้าสอบ</span></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon-box s-yellow"><i class="fa-solid fa-pen-nib"></i></div>
                        <div class="stat-info"><h5><?php echo $stats['stat_doing']; ?></h5><span>กำลังทำ</span></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon-box s-green"><i class="fa-solid fa-check-circle"></i></div>
                        <div class="stat-info"><h5><?php echo $stats['stat_done']; ?></h5><span>ส่งแล้ว</span></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-icon-box s-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="stat-info"><h5><?php echo $cheat_total; ?></h5><span>สลับจอ</span></div>
                    </div>
                </div>

                <form method="POST" id="resetForm" onsubmit="return confirm('⚠️ ยืนยันการรีเซ็ตการสอบของนักเรียนที่เลือก?');">
                    <div class="card card-table">
                        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-muted"><i class="fa-solid fa-list-ul me-1"></i> รายชื่อผู้เข้าสอบ</span>
                            
                            <button type="submit" name="btn_bulk_reset" id="btnBulk" class="btn btn-danger btn-sm fw-bold rounded-pill px-3" disabled>
                                <i class="fa-solid fa-rotate-right me-1"></i> รีเซ็ตที่เลือก
                            </button>
                        </div>
                        
                        <div class="table-responsive" style="max-height: 580px;">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th width="40" class="text-center"><input type="checkbox" id="checkAll" class="form-check-input chk-reset"></th>
                                        <th>ห้อง</th>
                                        <th>รหัส</th>
                                        <th>ชื่อ - นามสกุล</th>
                                        <th class="text-center">วันที่สอบ</th>
                                        <th class="text-center">เวลาที่ใช้</th>
                                        <th class="text-center">สถานะ</th>
                                        <th class="text-center" width="150">แจ้งเตือน</th>
                                        <th class="text-center">คะแนน</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $where_search = $target_room_sql;
                                    if ($search_query) $where_search .= " AND (s.std_code LIKE '%$search_query%' OR s.firstname LIKE '%$search_query%' OR s.lastname LIKE '%$search_query%')";

                                    $sql_list = "SELECT s.*, r.start_time, r.submit_time, r.status, r.score_obtained, r.cheat_count, r.exam_id as result_exam_id
                                                 FROM tb_students s
                                                 LEFT JOIN tb_exam_results r ON r.std_id = s.id AND r.exam_id = '$exam_id'
                                                 WHERE $where_search
                                                 ORDER BY s.room ASC, s.std_no ASC";
                                    $res_list = $conn->query($sql_list);
                                    $current_room = "";

                                    $thai_months = [1=>'ม.ค.', 2=>'ก.พ.', 3=>'มี.ค.', 4=>'เม.ย.', 5=>'พ.ค.', 6=>'มิ.ย.', 7=>'ก.ค.', 8=>'ส.ค.', 9=>'ก.ย.', 10=>'ต.ค.', 11=>'พ.ย.', 12=>'ธ.ค.'];

                                    if ($res_list && $res_list->num_rows > 0) {
                                        while ($row = $res_list->fetch_assoc()) {
                                            if ($current_room != $row['room']) {
                                                $current_room = $row['room'];
                                                echo "<tr class='room-header'><td colspan='10'>ห้อง {$current_room}</td></tr>";
                                            }

                                            $status_html = '<span class="status-badge st-wait">รอสอบ</span>';
                                            $time_str = "-";
                                            $date_str = "-";
                                            $score_show = "-";
                                            $btn_check = "";
                                            $row_class = "";
                                            $cheat_alert = "";
                                            $chk_input = ""; 

                                            if ($row['result_exam_id'] == $exam_id) {
                                                $chk_input = "<input type='checkbox' name='selected_ids[]' value='{$row['id']}' class='form-check-input chk-reset item-chk'>";
                                                
                                                if ($row['start_time']) {
                                                    $ts = strtotime($row['start_time']);
                                                    $y = date('Y', $ts) + 543;
                                                    $y_short = substr($y, 2, 2);
                                                    $m = $thai_months[date('n', $ts)];
                                                    $d = date('j', $ts);
                                                    $t = date('H:i', $ts);
                                                    $date_str = "{$d} {$m} {$y_short} <br><small class='text-muted'>{$t} น.</small>";
                                                }

                                                if ($row['cheat_count'] > 0) {
                                                    $row_class = "row-cheat";
                                                    $cheat_alert = "<span class='cheat-badge'><i class='fa-solid fa-eye-slash'></i> สลับจอ {$row['cheat_count']} ครั้ง</span>";
                                                } else {
                                                    $cheat_alert = "<span class='text-success small'><i class='fa-solid fa-check'></i> ปกติ</span>";
                                                }

                                                if ($row['status'] == 1) { 
                                                    $status_html = '<span class="status-badge st-done">ส่งแล้ว</span>';
                                                    $score_show = "<span class='fw-bold text-dark'>{$row['score_obtained']}</span>";
                                                    $btn_check = "<a href='admin_grading.php?exam_id=$exam_id&std_id={$row['id']}' target='_blank' class='btn btn-outline-secondary btn-sm rounded-pill px-3 py-0' style='font-size:0.75rem;'>ตรวจ</a>";
                                                    $diff = strtotime($row['submit_time']) - strtotime($row['start_time']);
                                                    $time_str = round($diff/60) . " นาที";
                                                } else { 
                                                    $status_html = '<span class="status-badge st-doing"><span class="dot-blink"></span> กำลังทำ</span>';
                                                    $diff = time() - strtotime($row['start_time']);
                                                    $time_str = round($diff/60) . " น.";
                                                }
                                            }

                                            echo "<tr class='$row_class'>";
                                            echo "<td class='text-center'>$chk_input</td>";
                                            echo "<td><span class='badge bg-light text-dark border'>{$row['room']}</span></td>";
                                            echo "<td class='text-muted small'>{$row['std_code']}</td>";
                                            echo "<td class='fw-bold'>{$row['firstname']} {$row['lastname']}</td>";
                                            echo "<td class='text-center small'>$date_str</td>";
                                            echo "<td class='text-center small'>$time_str</td>";
                                            echo "<td class='text-center'>$status_html</td>";
                                            echo "<td class='text-center'>$cheat_alert</td>";
                                            echo "<td class='text-center'>$score_show</td>";
                                            echo "<td class='text-center'>$btn_check</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='10' class='text-center py-5 text-muted'>ไม่พบข้อมูลนักเรียน</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>

            <?php else: ?>
                <div class="text-center py-5 mt-5 text-muted">กรุณาเลือกชุดข้อสอบเพื่อเริ่มติดตามสถานะ</div>
            <?php endif; ?>

        </div>
    </div>

    <script>
        const checkAll = document.getElementById('checkAll');
        const itemChks = document.querySelectorAll('.item-chk');
        const btnBulk = document.getElementById('btnBulk');

        function toggleBtn() {
            let anyChecked = false;
            itemChks.forEach(c => { if(c.checked) anyChecked = true; });
            if(btnBulk) btnBulk.disabled = !anyChecked;
        }

        if(checkAll) {
            checkAll.addEventListener('change', function() {
                itemChks.forEach(chk => chk.checked = this.checked);
                toggleBtn();
            });
        }
        itemChks.forEach(chk => chk.addEventListener('change', toggleBtn));
    </script>
</body>
</html>