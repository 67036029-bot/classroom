<?php
// file: report_overview.php
// =========================================================
// PART 1: LOGIC การคำนวณ (คงเดิม 100% ห้ามแก้ไข)
// =========================================================
session_start();
// ตรวจสอบสิทธิ์ (ถ้า header.php ตรวจซ้ำก็ไม่เป็นไร แต่ใส่ไว้กันเหนียวใน Logic ส่วนนี้ครับ)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include_once 'db.php'; // ใช้ include_once ป้องกัน error ซ้ำ

// 1. ดึง Config ของวิชาและโหมดการคำนวณ
$sql_info = "SELECT * FROM tb_course_info LIMIT 1";
$res_info = $conn->query($sql_info);
$course_info = $res_info->fetch_assoc();

$calc_mode = isset($course_info['calc_mode']) ? intval($course_info['calc_mode']) : 1;

$weights = [
    'w1' => ($course_info['weight_k1'] > 0) ? $course_info['weight_k1'] : 30,
    'w2' => ($course_info['weight_mid'] > 0) ? $course_info['weight_mid'] : 20,
    'w3' => ($course_info['weight_k2'] > 0) ? $course_info['weight_k2'] : 30,
    'w4' => ($course_info['weight_final'] > 0) ? $course_info['weight_final'] : 20
];

function calculateGrade($score) {
    if ($score >= 80) return 4;
    if ($score >= 75) return 3.5;
    if ($score >= 70) return 3;
    if ($score >= 65) return 2.5;
    if ($score >= 60) return 2;
    if ($score >= 55) return 1.5;
    if ($score >= 50) return 1;
    return 0;
}

$rooms_data = [];
$total_students = 0;
$grade_counts_total = ['4'=>0, '3.5'=>0, '3'=>0, '2.5'=>0, '2'=>0, '1.5'=>0, '1'=>0, '0'=>0];
$sum_grade_total = 0;

$sql_rooms = "SELECT DISTINCT room FROM tb_students ORDER BY room ASC";
$res_rooms = $conn->query($sql_rooms);

while ($r = $res_rooms->fetch_assoc()) {
    $room_name = $r['room'];
    $parts = explode('/', $room_name);
    $grade_text = $parts[0]; 
    $subj_code = $course_info['subject_code'];

    $max_scores = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
    $sql_max = "SELECT work_type, SUM(full_score) as total_max 
                FROM tb_work 
                WHERE target_room = 'all' OR target_room = '$room_name' OR target_room = 'grade:$grade_text'
                GROUP BY work_type";
    $res_max = $conn->query($sql_max);
    while ($m = $res_max->fetch_assoc()) { $max_scores[$m['work_type']] = $m['total_max']; }
    foreach($max_scores as $k => $v) { if($v == 0) $max_scores[$k] = 1; }

    $sql_std = "SELECT id FROM tb_students WHERE room = '$room_name'";
    $res_std = $conn->query($sql_std);
    
    $room_n = 0;
    $room_g_counts = ['4'=>0, '3.5'=>0, '3'=>0, '2.5'=>0, '2'=>0, '1.5'=>0, '1'=>0, '0'=>0];
    $room_grades_list = [];

    while ($std = $res_std->fetch_assoc()) {
        $sid = $std['id'];
        $sql_score = "SELECT w.work_type, SUM(s.score_point) as raw_point 
                      FROM tb_score s 
                      JOIN tb_work w ON s.work_id = w.work_id 
                      WHERE s.std_id = '$sid'
                      AND (w.target_room = 'all' OR w.target_room = '$room_name' OR w.target_room = 'grade:$grade_text')
                      GROUP BY w.work_type";
        $res_score = $conn->query($sql_score);
        $raw = [1=>0, 2=>0, 3=>0, 4=>0];
        while($sc = $res_score->fetch_assoc()){ $raw[$sc['work_type']] = $sc['raw_point']; }
        
        if ($calc_mode == 1) {
            $f1 = ($raw[1] / $max_scores[1]) * $weights['w1'];
            $f2 = ($raw[2] / $max_scores[2]) * $weights['w3']; 
            $f3 = ($raw[3] / $max_scores[3]) * $weights['w2'];
            $f4 = ($raw[4] / $max_scores[4]) * $weights['w4'];
            $total = $f1 + $f2 + $f3 + $f4;
        } else {
            $total = array_sum($raw);
        }
        
        $grade = calculateGrade(round($total));
        $g_str = strval($grade);
        $room_n++;
        if(isset($room_g_counts[$g_str])) $room_g_counts[$g_str]++;
        $room_grades_list[] = floatval($grade);
        $total_students++;
        if(isset($grade_counts_total[$g_str])) $grade_counts_total[$g_str]++;
        $sum_grade_total += floatval($grade);
    }

    $x_bar = ($room_n > 0) ? (array_sum($room_grades_list) / $room_n) : 0;
    $sd = 0;
    if ($room_n > 1) {
        $variance = 0;
        foreach ($room_grades_list as $v) { $variance += pow($v - $x_bar, 2); }
        $sd = sqrt($variance / $room_n);
    }

    $rooms_data[] = [
        'name' => $room_name,
        'subject_code' => $subj_code, 
        'n' => $room_n,
        'counts' => $room_g_counts,
        'mean' => $x_bar,
        'sd' => $sd,
        'calc_mode' => $calc_mode
    ];
}

$good_grades = $grade_counts_total['4'] + $grade_counts_total['3.5'] + $grade_counts_total['3'];
$percent_good = ($total_students > 0) ? ($good_grades / $total_students) * 100 : 0;
$avg_gpax = ($total_students > 0) ? ($sum_grade_total / $total_students) : 0;

$eval_result = "ปรับปรุง"; $eval_color = "#dc3545"; $eval_bg = "rgba(220, 53, 69, 0.1)"; $eval_icon = "fa-triangle-exclamation";
if ($percent_good >= 90) { $eval_result = "ดีเยี่ยม"; $eval_color = "#198754"; $eval_bg = "rgba(25, 135, 84, 0.1)"; $eval_icon = "fa-trophy"; }
elseif ($percent_good >= 65) { $eval_result = "ดี"; $eval_color = "#0d6efd"; $eval_bg = "rgba(13, 110, 253, 0.1)"; $eval_icon = "fa-thumbs-up"; }
elseif ($percent_good >= 50) { $eval_result = "พอใช้"; $eval_color = "#ffc107"; $eval_bg = "rgba(255, 193, 7, 0.1)"; $eval_icon = "fa-circle-check"; }

// =========================================================
// PART 2: เรียก HEADER (เริ่มแสดงผล)
// =========================================================
include 'header.php';
?>

<style>
    :root { --pink-brand: #ff007f; --dark-bg: #1a1a1a; --card-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    
    /* KPI Banner Section */
    .kpi-banner {
        background: white; border-radius: 15px; padding: 20px;
        box-shadow: var(--card-shadow); margin-bottom: 25px;
        display: flex; justify-content: space-around; align-items: center;
        border-bottom: 4px solid var(--pink-brand);
    }
    .kpi-item { text-align: center; }
    .kpi-val { display: block; font-size: 1.8rem; font-weight: 700; color: #333; font-family: 'Poppins', sans-serif; }
    .kpi-label { font-size: 0.85rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Quality Box */
    .quality-summary-box {
        background: white; border-radius: 15px; padding: 20px;
        box-shadow: var(--card-shadow); margin-bottom: 25px;
        display: flex; align-items: center; justify-content: space-between;
        background: linear-gradient(135deg, #ffffff 0%, #fdfdfd 100%);
    }
    .eval-status { display: flex; align-items: center; gap: 20px; }
    .eval-icon-circle {
        width: 60px; height: 60px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.8rem; color: white;
    }
    .eval-text h3 { margin: 0; font-weight: 700; color: #333; }
    .eval-text p { margin: 0; color: #6c757d; font-size: 0.9rem; }

    /* Table Alignment */
    .table-card { background: white; border-radius: 15px; overflow: hidden; box-shadow: var(--card-shadow); }
    .table-custom { width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 0; }
    .table-custom thead th {
        background-color: var(--dark-bg); color: white;
        padding: 12px; text-align: center !important; vertical-align: middle;
        font-size: 0.85rem; font-weight: 500; border-bottom: 3px solid var(--pink-brand);
    }
    .table-custom td { 
        padding: 12px; border-bottom: 1px solid #eee; 
        vertical-align: middle; text-align: center !important; 
        font-size: 0.95rem;
    }
    .table-custom tbody tr:hover td { background-color: #fffafc; }
    
    .badge-subj { background: #f0f0f0; color: #333; padding: 2px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; margin-top: 4px; display: inline-block; }
    .val-focus { font-weight: 700; color: #333; }
    .row-total { background-color: #f8f9fa; font-weight: bold; }
    .row-percent { background-color: #f1f3f5; font-weight: bold; color: #495057; font-size: 0.9rem; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-line text-pink-brand me-2"></i> สรุปผลสัมฤทธิ์ทางการเรียน (KPI)</h4>
    <div class="text-muted small">Academic Achievement Report</div>
</div>

<div class="kpi-banner">
    <div class="kpi-item">
        <span class="kpi-val"><?php echo number_format($total_students); ?></span>
        <span class="kpi-label">นักเรียนทั้งหมด</span>
    </div>
    <div class="vr opacity-25"></div>
    <div class="kpi-item">
        <span class="kpi-val text-primary"><?php echo number_format($percent_good, 1); ?>%</span>
        <span class="kpi-label">ผ่านเกณฑ์คุณภาพ (3-4)</span>
    </div>
    <div class="vr opacity-25"></div>
    <div class="kpi-item">
        <span class="kpi-val text-success"><?php echo number_format($avg_gpax, 2); ?></span>
        <span class="kpi-label">เกรดเฉลี่ยรวม (GPAX)</span>
    </div>
    <div class="vr opacity-25"></div>
    <div class="kpi-item">
        <span class="kpi-val text-danger"><?php echo $grade_counts_total['0']; ?></span>
        <span class="kpi-label">จำนวนที่ติด 0</span>
    </div>
</div>

<div class="quality-summary-box">
    <div class="eval-status">
        <div class="eval-icon-circle" style="background-color: <?php echo $eval_color; ?>;">
            <i class="fa-solid <?php echo $eval_icon; ?>"></i>
        </div>
        <div class="eval-text">
            <p>ผลการประเมินคุณภาพการจัดการเรียนการสอน</p>
            <h3>ระดับคุณภาพ: <span style="color: <?php echo $eval_color; ?>;"><?php echo $eval_result; ?></span></h3>
        </div>
    </div>
    <div class="eval-meta text-end">
        <div class="fw-bold h5 mb-0" style="color: <?php echo $eval_color; ?>;"><?php echo number_format($percent_good, 2); ?>%</div>
        <small class="text-muted">ร้อยละของนักเรียนที่ได้ระดับดีขึ้นไป</small>
    </div>
</div>

<div class="table-card">
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th rowspan="2">ห้องเรียน / วิชา</th>
                    <th rowspan="2">นร.</th>
                    <th colspan="8">จำนวนนักเรียนแยกตามระดับเกรด</th>
                    <th rowspan="2">X̄ (Mean)</th>
                    <th rowspan="2">S.D.</th>
                </tr>
                <tr>
                    <th style="background:#198754">4</th>
                    <th style="background:#198754">3.5</th>
                    <th style="background:#198754">3</th>
                    <th style="background:#ffc107; color:black;">2.5</th>
                    <th style="background:#ffc107; color:black;">2</th>
                    <th style="background:#ffc107; color:black;">1.5</th>
                    <th style="background:#dc3545">1</th>
                    <th style="background:#dc3545">0</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms_data as $r): ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?php echo $r['name']; ?></div>
                        <div class="badge-subj"><?php echo $r['subject_code']; ?></div>
                    </td>
                    <td><?php echo $r['n']; ?></td>
                    <td class="val-focus"><?php echo $r['counts']['4']; ?></td>
                    <td><?php echo $r['counts']['3.5']; ?></td>
                    <td><?php echo $r['counts']['3']; ?></td>
                    <td><?php echo $r['counts']['2.5']; ?></td>
                    <td><?php echo $r['counts']['2']; ?></td>
                    <td><?php echo $r['counts']['1.5']; ?></td>
                    <td class="text-danger"><?php echo $r['counts']['1']; ?></td>
                    <td class="bg-danger bg-opacity-10 text-danger fw-bold"><?php echo $r['counts']['0']; ?></td>
                    <td class="val-focus bg-light"><?php echo number_format($r['mean'], 2); ?></td>
                    <td class="text-muted bg-light"><?php echo number_format($r['sd'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="row-total">
                    <td class="text-center">รวมทั้งหมด</td>
                    <td><?php echo $total_students; ?></td>
                    <td><?php echo $grade_counts_total['4']; ?></td>
                    <td><?php echo $grade_counts_total['3.5']; ?></td>
                    <td><?php echo $grade_counts_total['3']; ?></td>
                    <td><?php echo $grade_counts_total['2.5']; ?></td>
                    <td><?php echo $grade_counts_total['2']; ?></td>
                    <td><?php echo $grade_counts_total['1.5']; ?></td>
                    <td><?php echo $grade_counts_total['1']; ?></td>
                    <td><?php echo $grade_counts_total['0']; ?></td>
                    <td>-</td>
                    <td>-</td>
                </tr>
                <tr class="row-percent">
                    <td class="text-center">คิดเป็นร้อยละ</td>
                    <td>100%</td>
                    <td><?php echo ($total_students>0) ? number_format(($grade_counts_total['4']/$total_students)*100, 2) : 0; ?></td>
                    <td><?php echo ($total_students>0) ? number_format(($grade_counts_total['3.5']/$total_students)*100, 2) : 0; ?></td>
                    <td><?php echo ($total_students>0) ? number_format(($grade_counts_total['3']/$total_students)*100, 2) : 0; ?></td>
                    <td><?php echo ($total_students>0) ? number_format(($grade_counts_total['2.5']/$total_students)*100, 2) : 0; ?></td>
                    <td><?php echo ($total_students>0) ? number_format(($grade_counts_total['2']/$total_students)*100, 2) : 0; ?></td>
                    <td><?php echo ($total_students>0) ? number_format(($grade_counts_total['1.5']/$total_students)*100, 2) : 0; ?></td>
                    <td><?php echo ($total_students>0) ? number_format(($grade_counts_total['1']/$total_students)*100, 2) : 0; ?></td>
                    <td><?php echo ($total_students>0) ? number_format(($grade_counts_total['0']/$total_students)*100, 2) : 0; ?></td>
                    <td>-</td>
                    <td>-</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php include 'footer.php'; ?>