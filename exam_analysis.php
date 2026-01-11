<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

$exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : 0;
if ($exam_id == 0) exit("Invalid Exam ID");

// =========================================================
// Config Column Name
// =========================================================
$id_col = 'std_id';           
$score_col = 'score_obtained'; 
// =========================================================

// 1. ดึงข้อมูลชุดข้อสอบ
$exam_info = $conn->query("SELECT exam_name FROM tb_exam_sets WHERE exam_id = '$exam_id'")->fetch_assoc();

// 2. เตรียมข้อมูลสำหรับคำนวณอำนาจจำแนก (r)
$sql_scores = "SELECT $id_col FROM tb_exam_results WHERE exam_id = '$exam_id' AND status = 1 ORDER BY $score_col DESC";
$res_scores = $conn->query($sql_scores);

$all_students = [];
if($res_scores) {
    while($row = $res_scores->fetch_assoc()) { 
        $all_students[] = $row[$id_col]; 
    }
}

$total_students = count($all_students);
$group_size = floor($total_students * 0.33); 
if ($group_size == 0) $group_size = floor($total_students / 2); 

$upper_group = array_slice($all_students, 0, $group_size); // กลุ่มเก่ง
$lower_group = array_slice($all_students, -$group_size, $group_size); // กลุ่มอ่อน

function ids_to_string($ids) {
    if(empty($ids)) return "0";
    global $conn;
    $ids = array_map(function($id) use ($conn) { return "'" . $conn->real_escape_string($id) . "'"; }, $ids);
    return implode(',', $ids);
}

$upper_str = ids_to_string($upper_group);
$lower_str = ids_to_string($lower_group);

// 3. ดึงโจทย์และสถิติ
$sql_stat = "SELECT q.*, 
            (SELECT COUNT(*) FROM tb_exam_answer_log l WHERE l.question_id = q.question_id AND l.is_correct = 1) as correct_count
            FROM tb_exam_questions q
            WHERE q.exam_id = '$exam_id'
            ORDER BY q.question_id ASC";
$result_questions = $conn->query($sql_stat);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Deep Exam Analysis</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --neon-pink: #ff007f;
            --neon-glow: 0 0 10px rgba(255, 0, 127, 0.5);
            --dark-bg: #1a1d20;
            --card-bg: #ffffff;
            --hover-bg: #fff0f6;
        }
        
        body { 
            font-family: 'Sarabun', sans-serif; 
            background-color: #f0f2f5; 
            background-image: linear-gradient(120deg, #fdfbfb 0%, #ebedee 100%);
        }

        .page-header { position: relative; padding-left: 20px; }
        .page-header::before {
            content: ''; position: absolute; left: 0; top: 5px; bottom: 5px;
            width: 5px; background: var(--neon-pink); border-radius: 4px; box-shadow: var(--neon-glow);
        }

        .card-custom {
            border: none; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px);
        }

        .table-custom thead th {
            background-color: var(--dark-bg); color: white; border: none;
            font-weight: 600; text-transform: uppercase; letter-spacing: 1px; padding: 15px;
        }

        .table-custom tbody tr.main-row {
            cursor: pointer; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); border-left: 4px solid transparent;
        }
        .table-custom tbody tr.main-row:hover {
            background-color: var(--hover-bg); transform: translateY(-2px) scale(1.005);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1); border-left: 4px solid var(--neon-pink); z-index: 10; position: relative;
        }

        .badge-glow { box-shadow: 0 2px 5px rgba(0,0,0,0.1); transition: 0.2s; }
        .badge-glow:hover { transform: scale(1.1); }
        
        .badge-p-hard { background: linear-gradient(45deg, #dc3545, #ff6b6b); color:white; }
        .badge-p-medium { background: linear-gradient(45deg, #ffc107, #ffdb4d); color:black; }
        .badge-p-easy { background: linear-gradient(45deg, #198754, #20c997); color:white; }

        .badge-r-good { background: linear-gradient(45deg, #198754, #20c997); box-shadow: 0 0 8px rgba(32, 201, 151, 0.4); }
        .badge-r-poor { background: linear-gradient(45deg, #dc3545, #ff6b6b); }

        .expanded-content {
            animation: slideDown 0.4s ease-out forwards;
            background: linear-gradient(to bottom, #fff0f6, #ffffff); border-bottom: 2px solid var(--neon-pink);
        }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .progress-distractor { height: 12px; border-radius: 6px; background-color: #e9ecef; overflow: visible; }
        .progress-bar { border-radius: 6px; position: relative; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="p-4">
    <div class="container">
        
        <div class="d-flex justify-content-between align-items-center mb-5 fade-in">
            <div class="page-header">
                <h3 class="fw-bold mb-0 text-dark">วิเคราะห์คุณภาพข้อสอบ <span style="color:var(--neon-pink);">Advanced</span></h3>
                <span class="text-secondary">ชุด: <strong><?php echo $exam_info['exam_name']; ?></strong> (ผู้สอบ <?php echo $total_students; ?> คน)</span>
            </div>
            <a href="exam_monitor.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-dark rounded-pill px-4 fw-bold shadow-sm" style="transition:0.3s;">
                <i class="fa-solid fa-arrow-left me-2"></i> ย้อนกลับ
            </a>
        </div>

        <div class="card-custom">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" width="60">ข้อ</th>
                            <th>โจทย์และเฉลย</th>
                            <th class="text-center" width="180">ความยาก (P)</th>
                            <th class="text-center" width="180">อำนาจจำแนก (r)</th>
                            <th class="text-center" width="50"><i class="fa-solid fa-angle-down"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $i = 1;
                    if($result_questions && $result_questions->num_rows > 0):
                        while($q = $result_questions->fetch_assoc()):
                            $qid = $q['question_id'];
                            
                            // คำนวณ P
                            $correct_count = $q['correct_count'];
                            $p_value = ($total_students > 0) ? ($correct_count / $total_students) : 0;
                            $p_percent = $p_value * 100;
                            
                            if($p_percent < 40) { $p_txt = "ยาก"; $p_class = "badge-p-hard"; }
                            elseif($p_percent < 70) { $p_txt = "ปานกลาง"; $p_class = "badge-p-medium"; }
                            else { $p_txt = "ง่าย"; $p_class = "badge-p-easy"; }

                            // คำนวณ r
                            $ru = 0; $rl = 0;
                            if($group_size > 0) {
                                $sql_ru = "SELECT COUNT(*) as c FROM tb_exam_answer_log WHERE question_id='$qid' AND is_correct=1 AND $id_col IN ($upper_str)";
                                $ru = $conn->query($sql_ru)->fetch_assoc()['c'];
                                
                                $sql_rl = "SELECT COUNT(*) as c FROM tb_exam_answer_log WHERE question_id='$qid' AND is_correct=1 AND $id_col IN ($lower_str)";
                                $rl = $conn->query($sql_rl)->fetch_assoc()['c'];
                            }
                            $r_value = ($group_size > 0) ? ($ru - $rl) / $group_size : 0;
                            
                            $r_txt = "พอใช้"; $r_class = "bg-secondary";
                            if($r_value >= 0.40) { $r_txt = "ดีมาก"; $r_class = "badge-r-good"; }
                            elseif($r_value >= 0.20) { $r_txt = "พอใช้"; $r_class = "badge-p-medium"; }
                            elseif($r_value < 0) { $r_txt = "ควรปรับปรุง"; $r_class = "badge-r-poor"; }

                            // Distractors
                            $distractors = [];
                            $sql_dist = "SELECT student_answer, COUNT(*) as c FROM tb_exam_answer_log WHERE question_id = '$qid' GROUP BY student_answer";
                            $res_dist = $conn->query($sql_dist);
                            while($d = $res_dist->fetch_assoc()){ $distractors[$d['student_answer']] = $d['c']; }
                    ?>
                        <tr class="main-row" data-bs-toggle="collapse" data-bs-target="#detail-<?php echo $qid; ?>">
                            <td class="text-center fw-bold text-muted" style="vertical-align: middle;"><?php echo $i++; ?></td>
                            <td style="vertical-align: middle;">
                                <div class="fw-bold text-dark mb-1 text-truncate" style="max-width: 450px; font-size:1.05rem;">
                                    <?php echo strip_tags($q['question_text']); ?>
                                </div>
                                <small class="text-muted"><i class="fa-solid fa-key" style="color:var(--neon-pink);"></i> เฉลย: <strong><?php echo $q['correct_answer']; ?></strong></small>
                            </td>
                            <td class="text-center" style="vertical-align: middle;">
                                <span class="badge badge-glow <?php echo $p_class; ?> rounded-pill w-100 py-2">
                                    <?php echo number_format($p_percent, 0); ?>% (<?php echo $p_txt; ?>)
                                </span>
                            </td>
                            <td class="text-center" style="vertical-align: middle;">
                                <span class="badge badge-glow <?php echo $r_class; ?> rounded-pill w-100 py-2">
                                    r = <?php echo number_format($r_value, 2); ?>
                                </span>
                            </td>
                            <td class="text-center text-muted" style="vertical-align: middle;">
                                <i class="fa-solid fa-chevron-down opacity-50"></i>
                            </td>
                        </tr>

                        <tr class="collapse" id="detail-<?php echo $qid; ?>">
                            <td colspan="5" class="p-0 border-0">
                                <div class="expanded-content p-4">
                                    <div class="row g-4 align-items-start">
                                        <div class="col-md-7 border-end border-muted">
                                            <div class="mb-3 p-3 bg-white rounded border shadow-sm">
                                                <h6 class="fw-bold text-dark mb-2">โจทย์ฉบับเต็ม:</h6>
                                                <div class="text-dark" style="font-size: 1rem; line-height: 1.6;">
                                                    <?php echo $q['question_text']; ?>
                                                </div>
                                            </div>

                                            <h6 class="fw-bold text-dark mb-3">
                                                <i class="fa-solid fa-chart-simple me-2" style="color:var(--neon-pink);"></i> 
                                                ตัวเลือกที่ตอบ (Distractor)
                                            </h6>
                                            <?php 
                                            $options = ($q['question_type'] == 3) ? ['True', 'False'] : ['a', 'b', 'c', 'd'];
                                            foreach($options as $opt): 
                                                $count = isset($distractors[$opt]) ? $distractors[$opt] : 0;
                                                $bar_percent = ($total_students > 0) ? ($count / $total_students) * 100 : 0;
                                                $is_ans = (strtolower($q['correct_answer']) == strtolower($opt));
                                                
                                                $bar_color = 'bg-secondary'; $txt_color = 'text-muted';
                                                if ($is_ans) { $bar_color = 'bg-success'; $txt_color = 'text-success fw-bold'; }
                                                elseif ($count > 0) { $bar_color = 'bg-danger'; } 
                                            ?>
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="distractor-label text-uppercase small <?php echo $txt_color; ?>" style="width:40px;">
                                                    <?php echo $opt; ?>
                                                </div>
                                                <div class="flex-grow-1 mx-2">
                                                    <div class="progress progress-distractor shadow-sm">
                                                        <div class="progress-bar <?php echo $bar_color; ?>" style="width: <?php echo $bar_percent; ?>%"></div>
                                                    </div>
                                                </div>
                                                <div class="small fw-bold text-muted" style="width: 90px; text-align: right;">
                                                    <?php echo $count; ?> คน
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="col-md-5">
                                            <div class="bg-white p-3 rounded-4 shadow-sm h-100 border border-light position-relative" style="overflow:hidden;">
                                                <div style="position:absolute; top:-10px; right:-10px; width:50px; height:50px; background:var(--neon-pink); opacity:0.1; border-radius:50%;"></div>
                                                
                                                <h6 class="fw-bold text-dark mb-3"><i class="fa-solid fa-microscope me-2" style="color:var(--neon-pink);"></i> เจาะลึกอำนาจจำแนก</h6>
                                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 rounded bg-light">
                                                    <span class="small text-muted">กลุ่มเก่ง (Top 33%)</span>
                                                    <span class="fw-bold text-success"><i class="fa-solid fa-user-check"></i> <?php echo $ru; ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mb-3 p-2 rounded bg-light">
                                                    <span class="small text-muted">กลุ่มอ่อน (Bottom 33%)</span>
                                                    <span class="fw-bold text-danger"><i class="fa-solid fa-user-xmark"></i> <?php echo $rl; ?></span>
                                                </div>
                                                <div class="text-center mt-3">
                                                    <div class="display-6 fw-bold" style="color:var(--dark-bg);"><?php echo number_format($r_value, 2); ?></div>
                                                    <span class="badge rounded-pill mt-2 <?php echo $r_class; ?>"><?php echo $r_txt; ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">ยังไม่มีข้อมูลการสอบ</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="text-center mt-4 text-muted small opacity-50">
            ระบบวิเคราะห์ข้อสอบอัจฉริยะ &copy; <?php echo date('Y'); ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>