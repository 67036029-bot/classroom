<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

// =========================================================
// PART 1: CONFIGURATION & SETUP
// =========================================================

// 1. ดึงข้อมูล Config ของวิชา
$sql_info = "SELECT * FROM tb_course_info LIMIT 1";
$res_info = $conn->query($sql_info);
$course_info = $res_info->fetch_assoc();

// โหมดการคำนวณ (1 = น้ำหนัก, 0 = ตามจริง)
$CALC_MODE = isset($course_info['calc_mode']) ? intval($course_info['calc_mode']) : 1; 

$weights = [
    'w1' => ($course_info['weight_k1'] > 0) ? $course_info['weight_k1'] : 30,
    'w2' => ($course_info['weight_mid'] > 0) ? $course_info['weight_mid'] : 20,
    'w3' => ($course_info['weight_k2'] > 0) ? $course_info['weight_k2'] : 30,
    'w4' => ($course_info['weight_final'] > 0) ? $course_info['weight_final'] : 20
];

// 2. ฟังก์ชันตัดเกรด
function calculateGrade($score) {
    if ($score >= 80) return '4';
    if ($score >= 75) return '3.5';
    if ($score >= 70) return '3';
    if ($score >= 65) return '2.5';
    if ($score >= 60) return '2';
    if ($score >= 55) return '1.5';
    if ($score >= 50) return '1';
    return '0';
}

// 3. จัดการตัวกรอง (Room & Grade)
$all_grades = [];
$sql_rooms_all = "SELECT DISTINCT room FROM tb_students ORDER BY room ASC";
$res_rooms_all = $conn->query($sql_rooms_all);
while($r = $res_rooms_all->fetch_assoc()) {
    $parts = explode('/', $r['room']);
    $g = $parts[0];
    if (!in_array($g, $all_grades)) $all_grades[] = $g;
}
sort($all_grades);
$res_rooms_all->data_seek(0);

$filter_room = isset($_GET['room']) ? $_GET['room'] : "";
$filter_grade = isset($_GET['grade']) ? $_GET['grade'] : "";

if ($filter_room == "" && $res_rooms_all->num_rows > 0) {
    $row = $res_rooms_all->fetch_assoc();
    $filter_room = $row['room'];
    $res_rooms_all->data_seek(0);
}

// =========================================================
// PART 2: DYNAMIC SUBJECT LOGIC
// =========================================================
$parts = explode('/', $filter_room);
$grade_text = $parts[0]; 
$grade_num = preg_replace('/[^0-9]/', '', $grade_text);
$sql_conf = "SELECT default_subject_name, default_subject_code FROM tb_grade_config WHERE grade_level = '$grade_num' LIMIT 1";
$res_conf = $conn->query($sql_conf);
$display_subj_code = $course_info['subject_code'];
if ($res_conf->num_rows > 0) {
    $conf = $res_conf->fetch_assoc();
    $display_subj_code = $conf['default_subject_code'];
}

// =========================================================
// PART 3: CALCULATION CORE
// =========================================================

// หาคะแนนเต็มของงานที่มีอยู่จริง (Max Raw Score)
// (กรองเฉพาะงานของห้องนี้)
$max_scores = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
$sql_max = "SELECT work_type, SUM(full_score) as total_max 
            FROM tb_work 
            WHERE target_room = 'all' OR target_room = '$filter_room' OR target_room = 'grade:$grade_text'
            GROUP BY work_type";
$res_max = $conn->query($sql_max);
while ($m = $res_max->fetch_assoc()) {
    $max_scores[$m['work_type']] = $m['total_max'];
}
// กันหารด้วย 0
foreach($max_scores as $k => $v) { if($v == 0) $max_scores[$k] = 1; }

$report_data = [];
if ($filter_room != "") {
    $sql_std = "SELECT * FROM tb_students WHERE room = '$filter_room' ORDER BY std_no ASC";
    $result_std = $conn->query($sql_std);
    
    while ($std = $result_std->fetch_assoc()) {
        $sid = $std['id'];
        
        // 🔴 [FIXED] กรองคะแนนเฉพาะงานที่มอบหมายให้ห้องนี้เท่านั้น (ตัดคะแนนขยะทิ้ง)
        // เพิ่มเงื่อนไข AND (...) ด้านล่าง
        $sql_score = "SELECT w.work_type, SUM(s.score_point) as raw_point 
                      FROM tb_score s 
                      JOIN tb_work w ON s.work_id = w.work_id 
                      WHERE s.std_id = '$sid'
                      AND (w.target_room = 'all' OR w.target_room = '$filter_room' OR w.target_room = 'grade:$grade_text')
                      GROUP BY w.work_type";
                      
        $res_score = $conn->query($sql_score);
        $raw_scores = [1=>0, 2=>0, 3=>0, 4=>0];
        while($sc = $res_score->fetch_assoc()){
            $raw_scores[$sc['work_type']] = $sc['raw_point'];
        }
        
        $final_scores = [];
        
        // คำนวณตามโหมด
        if ($CALC_MODE == 1) { 
            // Weighted
            $final_scores[1] = ($raw_scores[1] / $max_scores[1]) * $weights['w1'];
            $final_scores[2] = ($raw_scores[2] / $max_scores[2]) * $weights['w3']; 
            $final_scores[3] = ($raw_scores[3] / $max_scores[3]) * $weights['w2'];
            $final_scores[4] = ($raw_scores[4] / $max_scores[4]) * $weights['w4'];
        } else {
            // Direct Score
            $final_scores[1] = $raw_scores[1];
            $final_scores[2] = $raw_scores[2];
            $final_scores[3] = $raw_scores[3];
            $final_scores[4] = $raw_scores[4];
        }
        
        $total_final = array_sum($final_scores);
		$grade = calculateGrade(round($total_final));
        
        if ($filter_grade != "") {
            if (strval($grade) != strval($filter_grade)) continue;
        }

        $std['raw'] = $raw_scores;
        $std['final'] = $final_scores;
        $std['total'] = $total_final;
        $std['grade'] = $grade;
        $report_data[] = $std;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สรุปผลการเรียน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --pink-brand: #d63384; --dark-head: #212529; --hover-bg: #fff0f6; }
        body { font-family: 'Sarabun', sans-serif; background-color: #f2f4f6; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 20px; height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        
        .page-header { background: white; border-radius: 12px; padding: 10px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.03); margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .info-tag { font-size: 0.85rem; font-weight: 600; padding: 4px 10px; border-radius: 6px; border: 1px solid #eee; display: inline-flex; align-items: center; gap: 6px; }
        .tag-subj { background: #fff0f6; color: #d63384; border-color: #fcc2d7; }
        .tag-room { background: #f8f9fa; color: #495057; }
        .tool-btn { height: 34px; padding: 0 15px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; border: 1px solid #dee2e6; background: white; color: #555; transition: 0.2s; white-space: nowrap; }
        .tool-btn:hover { background: #f8f9fa; border-color: #ccc; color: #333; }
        .tool-btn.btn-export { background: #198754; color: white; border: none; }
        .tool-btn.btn-export:hover { background: #146c43; }
        .custom-select-sm { height: 34px; border-radius: 50px; font-size: 0.85rem; font-weight: bold; padding-left: 12px; padding-right: 30px; border: 1px solid #dee2e6; cursor: pointer; }

        .grade-card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: none; overflow: hidden; display: flex; flex-direction: column; flex-grow: 1; }
        .table-scroll { flex-grow: 1; overflow-y: auto; position: relative; }
        .table-modern { width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-modern thead th { position: sticky; top: 0; z-index: 10; background-color: var(--dark-head); color: white; font-weight: 500; font-size: 0.85rem; padding: 10px 5px; text-align: center; border-bottom: 3px solid var(--pink-brand); vertical-align: middle; }
        .th-total { background-color: #d63384 !important; color: white !important; border-bottom: 3px solid #a61e61 !important; }
        .table-modern tbody td { padding: 6px 8px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; font-size: 0.9rem; color: #495057; }
        .table-modern tbody tr:hover { background-color: var(--hover-bg); transform: scale(1.001); z-index: 5; position: relative; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .table-modern tbody tr:hover td { color: #000; font-weight: 500; }

        .col-id { width: 45px; text-align: center; color: #adb5bd; }
        .col-std-id { width: 80px; text-align: center; font-family: monospace; }
        .col-name { text-align: left; padding-left: 15px !important; }
        .col-total-val { font-size: 1.1rem; font-weight: 800; color: var(--pink-brand); background: #fffafa; border-left: 1px solid #f0f0f0; border-right: 1px solid #f0f0f0; text-align: center; width: 80px; }
        .score-val { font-weight: 700; display: block; line-height: 1; }
        .raw-val { font-size: 0.7rem; color: #adb5bd; display: block; margin-top: 2px; }
        
        .grade-pill { width: 30px; height: 30px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; color: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .g-4 { background: #198754; } .g-3-5, .g-3 { background: #20c997; } .g-2-5, .g-2 { background: #ffc107; color: black; } .g-1-5, .g-1 { background: #fd7e14; } .g-0 { background: #dc3545; }
        
        /* Toggle Switch Style */
        .mode-selector { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 10px; padding: 10px; margin-bottom: 15px; }
        .form-check-input:checked { background-color: var(--pink-brand); border-color: var(--pink-brand); }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            
            <div class="page-header">
                <div class="d-flex align-items-center gap-3">
                    <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-pie me-2 text-secondary"></i>สรุปผลการเรียน</h4>
                    <div class="d-flex gap-2">
                        <?php if($CALC_MODE == 1): ?>
                            <span class="badge bg-dark rounded-pill shadow-sm"><i class="fa-solid fa-scale-balanced me-1"></i> คิดตามสัดส่วน (%)</span>
                        <?php else: ?>
                            <span class="badge bg-primary rounded-pill shadow-sm"><i class="fa-solid fa-calculator me-1"></i> คิดคะแนนตามจริง (Raw)</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="tool-btn" data-bs-toggle="modal" data-bs-target="#configModal">
                        <i class="fa-solid fa-sliders me-1"></i> ตั้งค่าการคำนวณ
                    </button>
                    
                    <div class="dropdown">
                        <button class="tool-btn btn-export dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="fa-solid fa-file-excel me-1"></i> Excel</button>
                        <ul class="dropdown-menu shadow border-0 mt-1">
                            <?php foreach($all_grades as $g): ?>
                                <li><a class="dropdown-item small" href="export_grade.php?grade=<?php echo $g; ?>"><i class="fa-solid fa-download me-2 text-success"></i>ระดับชั้น <?php echo $g; ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="vr mx-1 bg-secondary opacity-25" style="height: 25px;"></div>
                    <form method="GET" class="d-flex align-items-center gap-2 m-0">
                        <select name="room" class="form-select form-select-sm custom-select-sm bg-light" onchange="this.form.submit()">
                            <?php $res_rooms_all->data_seek(0); while($r = $res_rooms_all->fetch_assoc()) { $sel = ($filter_room == $r['room']) ? "selected" : ""; echo "<option value='" . $r['room'] . "' $sel>" . $r['room'] . "</option>"; } ?>
                        </select>
                        <select name="grade" class="form-select form-select-sm custom-select-sm text-pink border-pink" style="width: 110px;" onchange="this.form.submit()">
                            <option value="">ทุกเกรด</option>
                            <option value="4" <?php if($filter_grade=='4') echo 'selected'; ?>>เกรด 4</option>
                            <option value="3.5" <?php if($filter_grade=='3.5') echo 'selected'; ?>>เกรด 3.5</option>
                            <option value="3" <?php if($filter_grade=='3') echo 'selected'; ?>>เกรด 3</option>
                            <option value="2.5" <?php if($filter_grade=='2.5') echo 'selected'; ?>>เกรด 2.5</option>
                            <option value="2" <?php if($filter_grade=='2') echo 'selected'; ?>>เกรด 2</option>
                            <option value="1.5" <?php if($filter_grade=='1.5') echo 'selected'; ?>>เกรด 1.5</option>
                            <option value="1" <?php if($filter_grade=='1') echo 'selected'; ?>>เกรด 1</option>
                            <option value="0" <?php if($filter_grade=='0') echo 'selected'; ?>>ติด 0</option>
                        </select>
                    </form>
                </div>
            </div>

            <div class="grade-card">
                <div class="table-scroll">
                    <table class="table-modern">
                        <thead>
                            <tr>
                                <th class="col-id">#</th>
                                <th class="col-std-id">รหัส</th>
                                <th class="col-name">ชื่อ - นามสกุล</th>
                                <?php if($CALC_MODE == 1): ?>
                                    <th width="12%">เก็บ 1 <span class="badge bg-secondary rounded-pill fw-light"><?php echo $weights['w1']; ?>%</span></th>
                                    <th width="12%">เก็บ 2 <span class="badge bg-secondary rounded-pill fw-light"><?php echo $weights['w3']; ?>%</span></th>
                                    <th width="12%">กลาง <span class="badge bg-secondary rounded-pill fw-light"><?php echo $weights['w2']; ?>%</span></th>
                                    <th width="12%">ปลาย <span class="badge bg-secondary rounded-pill fw-light"><?php echo $weights['w4']; ?>%</span></th>
                                <?php else: ?>
                                    <th width="12%">เก็บ 1 <span class="badge bg-primary rounded-pill fw-light">Raw</span></th>
                                    <th width="12%">เก็บ 2 <span class="badge bg-primary rounded-pill fw-light">Raw</span></th>
                                    <th width="12%">กลาง <span class="badge bg-primary rounded-pill fw-light">Raw</span></th>
                                    <th width="12%">ปลาย <span class="badge bg-primary rounded-pill fw-light">Raw</span></th>
                                <?php endif; ?>
                                <th class="th-total">รวม</th>
                                <th width="70px">เกรด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($report_data) > 0): foreach ($report_data as $row): 
                                $g = str_replace('.', '-', $row['grade']); $g_class = "g-" . $g;
                            ?>
                            <tr>
                                <td class="col-id"><?php echo $row['std_no']; ?></td>
                                <td class="col-std-id"><?php echo $row['std_code']; ?></td>
                                <td class="col-name text-truncate" style="max-width: 250px;">
                                    <?php echo $row['title'].$row['firstname']." ".$row['lastname']; ?>
                                </td>
                                
                                <td class="text-center">
                                    <span class="score-val"><?php echo number_format($row['final'][1], 0); ?></span>
                                    <?php if($CALC_MODE == 1): ?><span class="raw-val">(<?php echo $row['raw'][1].'/'.$max_scores[1]; ?>)</span><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="score-val"><?php echo number_format($row['final'][2], 0); ?></span>
                                    <?php if($CALC_MODE == 1): ?><span class="raw-val">(<?php echo $row['raw'][2].'/'.$max_scores[2]; ?>)</span><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="score-val"><?php echo number_format($row['final'][3], 0); ?></span>
                                    <?php if($CALC_MODE == 1): ?><span class="raw-val">(<?php echo $row['raw'][3].'/'.$max_scores[3]; ?>)</span><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="score-val"><?php echo number_format($row['final'][4], 0); ?></span>
                                    <?php if($CALC_MODE == 1): ?><span class="raw-val">(<?php echo $row['raw'][4].'/'.$max_scores[4]; ?>)</span><?php endif; ?>
                                </td>
                                
                                <td class="col-total-val"><?php echo number_format($row['total'], 0); ?></td>
                                <td class="text-center"><span class="grade-pill <?php echo $g_class; ?>"><?php echo $row['grade']; ?></span></td>
                            </tr>
                            <?php endforeach; else: ?>
                                <tr><td colspan="9" class="text-center py-5 text-muted opacity-50">ไม่พบข้อมูลในห้องนี้</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="configModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow-lg">
                <div class="modal-header bg-dark text-white border-0 py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-sliders text-warning me-2"></i> ตั้งค่าการคำนวณ</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="save_weights.php" method="POST">
                    <div class="modal-body p-4 bg-light">
                        
                        <div class="mode-selector">
                            <label class="d-block small fw-bold text-muted mb-2">เลือกรูปแบบการคำนวณคะแนน:</label>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="calc_mode" id="mode1" value="1" <?php if($CALC_MODE==1) echo 'checked'; ?> onchange="toggleWeights(true)">
                                <label class="form-check-label fw-bold" for="mode1">
                                    1. คิดตามสัดส่วน (Weighted) <span class="badge bg-secondary ms-1">แนะนำ</span>
                                    <div class="small text-muted fw-normal">ระบบจะแปลงคะแนนดิบให้เป็น % ตามน้ำหนักที่ตั้งไว้ (เช่น งาน 10 คะแนน แต่คิดเป็น 30%)</div>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="calc_mode" id="mode0" value="0" <?php if($CALC_MODE==0) echo 'checked'; ?> onchange="toggleWeights(false)">
                                <label class="form-check-label fw-bold" for="mode0">
                                    2. คิดคะแนนตามจริง (Direct Score)
                                    <div class="small text-muted fw-normal">ได้เท่าไหร่เอาเท่านั้น ไม่มีการคูณ/หาร (รวมคะแนนดิบทั้งหมด)</div>
                                </label>
                            </div>
                        </div>

                        <div id="weightBox" style="display: <?php echo ($CALC_MODE==1)?'block':'none'; ?>;">
                            <div class="bg-white p-3 rounded-3 shadow-sm border mb-2">
                                <div class="row g-3 align-items-center mb-2">
                                    <label class="col-8 small fw-bold text-dark">1. เก็บก่อนกลางภาค</label>
                                    <div class="col-4"><div class="input-group input-group-sm"><input type="number" name="w_k1" class="form-control text-center fw-bold input-weight" value="<?php echo $weights['w1']; ?>"><span class="input-group-text">%</span></div></div>
                                </div>
                                <div class="row g-3 align-items-center">
                                    <label class="col-8 small fw-bold text-primary">2. สอบกลางภาค</label>
                                    <div class="col-4"><div class="input-group input-group-sm"><input type="number" name="w_mid" class="form-control text-center fw-bold input-weight text-primary border-primary" value="<?php echo $weights['w2']; ?>"><span class="input-group-text bg-primary text-white">%</span></div></div>
                                </div>
                            </div>
                            <div class="bg-white p-3 rounded-3 shadow-sm border">
                                <div class="row g-3 align-items-center mb-2">
                                    <label class="col-8 small fw-bold text-dark">3. เก็บหลังกลางภาค</label>
                                    <div class="col-4"><div class="input-group input-group-sm"><input type="number" name="w_k2" class="form-control text-center fw-bold input-weight" value="<?php echo $weights['w3']; ?>"><span class="input-group-text">%</span></div></div>
                                </div>
                                <div class="row g-3 align-items-center">
                                    <label class="col-8 small fw-bold text-danger">4. สอบปลายภาค</label>
                                    <div class="col-4"><div class="input-group input-group-sm"><input type="number" name="w_final" class="form-control text-center fw-bold input-weight text-danger border-danger" value="<?php echo $weights['w4']; ?>"><span class="input-group-text bg-danger text-white">%</span></div></div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3 px-2">
                                <span class="small fw-bold text-muted">รวมต้องได้ 100%</span>
                                <span id="total_weight_disp" class="h4 fw-bold text-success m-0">100</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-white">
                        <button type="button" class="btn btn-light text-muted fw-bold rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" id="btnSave" class="btn btn-dark fw-bold rounded-pill px-5 shadow-sm">บันทึกการตั้งค่า</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const inputs = document.querySelectorAll('.input-weight');
        const totalDisp = document.getElementById('total_weight_disp');
        const btnSave = document.getElementById('btnSave');
        const weightBox = document.getElementById('weightBox');

        function toggleWeights(show) {
            weightBox.style.display = show ? 'block' : 'none';
            // ถ้าซ่อน (เลือกโหมดตามจริง) ให้เปิดปุ่มบันทึกเสมอ
            if(!show) {
                btnSave.disabled = false;
                btnSave.classList.replace('btn-secondary','btn-dark');
            } else {
                calcTotal(); // ถ้าแสดง ให้เช็คผลรวมก่อน
            }
        }

        function calcTotal() {
            // คำนวณเฉพาะเมื่อกล่องน้ำหนักเปิดอยู่
            if(weightBox.style.display === 'none') return;

            let sum = 0; inputs.forEach(inp => sum += parseInt(inp.value || 0));
            totalDisp.innerText = sum;
            if(sum === 100) { totalDisp.className="h4 fw-bold text-success m-0"; btnSave.disabled=false; btnSave.classList.replace('btn-secondary','btn-dark'); }
            else { totalDisp.className="h4 fw-bold text-danger m-0"; btnSave.disabled=true; btnSave.classList.replace('btn-dark','btn-secondary'); }
        }
        
        inputs.forEach(inp => inp.addEventListener('input', calcTotal));
        // Run once on load
        calcTotal();
    </script>
</body>
</html>