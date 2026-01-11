<?php
session_start();
include 'db.php';

// --- 🛑 เพิ่มส่วนนี้เข้าไปครับ ---
if (isset($_SESSION['force_change_pwd']) && $_SESSION['force_change_pwd'] === true) {
    header("Location: force_change_pwd.php");
    exit();
}

// --- Security Check ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') { 
    header("Location: login.php"); 
    exit();
}

$std_id = $_SESSION['std_id']; 

// 1. ดึงข้อมูลนักเรียน
$sql_std = "SELECT * FROM tb_students WHERE id = '$std_id'";
$res_std = $conn->query($sql_std);
$std = $res_std->fetch_assoc();

// 2. ข้อมูลวิชา (Logic เดิม)
$room = $std['room'];
$parts = explode('/', $room);
$grade = $parts[0];

$display_subject_name = $SubjectName; 
$display_subject_code = $SubjectCode;

if (strpos($room, 'ม.5') !== false) { 
    $display_subject_name = "ภาษาอังกฤษพื้นฐาน";
    $display_subject_code = "อ32101"; 
} elseif (strpos($room, 'ม.6') !== false) { 
    $display_subject_name = "ภาษาอังกฤษพื้นฐาน";
    $display_subject_code = "อ33101";
}

// 3. ดึงงานและคะแนน
$sql_works = "SELECT w.*, e.exam_id, e.is_active, e.time_limit 
              FROM tb_work w
              LEFT JOIN tb_exam_sets e ON w.work_id = e.work_id
              WHERE w.target_room = 'all' 
              OR w.target_room = 'grade:$grade' 
              OR w.target_room = '$room'
              ORDER BY w.work_type, w.work_id";
$res_works = $conn->query($sql_works);

// คำนวณยอดรวม
$total_full = 0;
$total_get = 0;
$total_works = 0;
$count_submitted = 0;
$count_missing = 0;
$works_data = [];

if ($res_works->num_rows > 0) {
    while($w = $res_works->fetch_assoc()) {
        $total_works++;
        $wid = $w['work_id'];
        $chk = $conn->query("SELECT score_point FROM tb_score WHERE std_id = '$std_id' AND work_id = '$wid'")->fetch_assoc();
        $point = ($chk) ? $chk['score_point'] : null;
        
        $exam_id = $w['exam_id'];
        $exam_done = false;
        if ($exam_id) {
            $chk_exam = $conn->query("SELECT status FROM tb_exam_results WHERE std_id = '$std_id' AND exam_id = '$exam_id'")->fetch_assoc();
            if ($chk_exam && $chk_exam['status'] == 1) $exam_done = true;
        }

        $w['student_score'] = $point;
        $w['exam_done'] = $exam_done;
        $works_data[] = $w;

        $total_full += $w['full_score'];
        if ($point !== null) {
            $total_get += $point;
            $count_submitted++;
        } else {
            if ($exam_done) {
                $count_submitted++; // ส่งสอบแล้วแต่รอตรวจ
            } else {
                $count_missing++;
            }
        }
    }
}

// คำนวณ %
$percent = ($total_full > 0) ? ($total_get / $total_full) * 100 : 0;

// 4. แจ้งเตือนประเมิน
$show_eval_alert = false;
$chk_conf = $conn->query("SELECT is_active FROM tb_eval_config WHERE id = 1");
if ($chk_conf->num_rows > 0) {
    $conf = $chk_conf->fetch_assoc();
    if ($conf['is_active'] == 1) {
        $chk_done = $conn->query("SELECT id FROM tb_eval_results WHERE std_id = '$std_id' LIMIT 1");
        if ($chk_done->num_rows == 0) $show_eval_alert = true;
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - <?php echo $std['firstname']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --pink-neon: #ff007f;
            --pink-soft: #ffb6c1;
            --black-bg: #121212;
            --card-bg: #ffffff;
            --green-neon: #00ff88;
        }

        body { 
            font-family: 'Sarabun', sans-serif; 
            background-color: #f3f4f6;
            padding-bottom: 50px;
        }
        
        /* 1. Modern Top Banner (Black & Pink Theme) */
        .top-banner {
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
            border-bottom-left-radius: 40px;
            border-bottom-right-radius: 40px;
            padding: 30px 20px 100px 20px; /* เพิ่มพื้นที่ด้านล่างให้ Card ลอยทับ */
            position: relative;
            overflow: hidden;
            color: white;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        
        /* Abstract Background Shapes */
        .bg-shape {
            position: absolute; border-radius: 50%;
            filter: blur(50px); opacity: 0.4;
        }
        .shape-1 { width: 200px; height: 200px; background: var(--pink-neon); top: -80px; right: -50px; }
        .shape-2 { width: 150px; height: 150px; background: #6610f2; bottom: 10px; left: -50px; }

        .top-content { position: relative; z-index: 1; }

        .btn-logout-modern {
            background: rgba(255,255,255,0.1); 
            backdrop-filter: blur(5px);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            width: 40px; height: 40px;
            border-radius: 50%; 
            display: flex; align-items: center; justify-content: center;
            transition: 0.3s; text-decoration: none;
        }
        .btn-logout-modern:hover { background: var(--pink-neon); border-color: var(--pink-neon); transform: rotate(90deg); }

        /* 2. Floating Score Card with Animated Ring */
        .score-wrapper {
            margin-top: -80px;
            padding: 0 20px;
            margin-bottom: 30px;
            position: relative; z-index: 10;
        }
        .score-card {
            background: var(--card-bg);
            border-radius: 25px;
            padding: 25px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            display: flex; align-items: center; justify-content: space-between;
            border: 1px solid rgba(0,0,0,0.05);
        }

        /* Animated SVG Ring */
        .progress-ring { position: relative; width: 100px; height: 100px; }
        .progress-ring__circle {
            transition: stroke-dashoffset 1.5s ease-in-out;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        .progress-text {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        /* 3. Stat Grid */
        .stat-box {
            background: white; border-radius: 20px;
            padding: 15px; text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: transform 0.2s; border: 1px solid #f0f0f0;
        }
        .stat-box:active { transform: scale(0.95); }
        .stat-box h3 { font-weight: 800; margin: 0; font-size: 1.8rem; }
        
        /* 4. Glowing Green Button (Highlight) */
        .btn-start-exam {
            background: linear-gradient(90deg, #00b09b, #96c93d); /* Neon Green Gradient */
            color: white; border: none;
            padding: 10px 25px; border-radius: 50px;
            font-weight: 800; font-size: 0.9rem;
            text-transform: uppercase; letter-spacing: 1px;
            box-shadow: 0 0 15px rgba(0, 176, 155, 0.6); /* Glow Effect */
            animation: pulse-green 1.5s infinite;
            transition: 0.3s;
            text-decoration: none; display: inline-block;
        }
        .btn-start-exam:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 0 25px rgba(0, 176, 155, 0.9);
            color: white;
        }

        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(0, 176, 155, 0.7); }
            70% { box-shadow: 0 0 0 10px rgba(0, 176, 155, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 176, 155, 0); }
        }

        /* Work List Items */
        .work-item {
            background: white; border-radius: 18px; padding: 18px;
            margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            border-left: 5px solid transparent;
            transition: 0.2s; display: flex; align-items: center; justify-content: space-between;
        }
        .work-item:hover { transform: translateX(5px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }
        
        .work-item.type-exam { border-left-color: var(--pink-neon); }
        .work-item.type-score { border-left-color: #333; }

        .work-icon {
            width: 45px; height: 45px; border-radius: 12px;
            background: #f8f9fa; color: #333;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem; margin-right: 15px;
        }
        .type-exam .work-icon { background: rgba(255, 0, 127, 0.1); color: var(--pink-neon); }

        /* Alert Box */
        .alert-eval {
            background: linear-gradient(45deg, #212529, #343a40);
            color: white; border: none; border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        .btn-eval { background: var(--pink-neon); color: white; border-radius: 30px; font-weight: bold; border:none; padding: 5px 20px; }
        .btn-eval:hover { background: white; color: var(--pink-neon); }

    </style>
</head>
<body>

    <div class="top-banner">
        <div class="bg-shape shape-1"></div>
        <div class="bg-shape shape-2"></div>
        
        <div class="top-content d-flex justify-content-between align-items-start">
            <div>
                <span class="badge bg-white text-dark mb-2 px-3 rounded-pill fw-bold shadow-sm" style="font-size: 0.7rem;">STUDENT ID: <?php echo $std['std_code']; ?></span>
                <h1 class="fw-bold mb-0" style="text-shadow: 0 2px 10px rgba(0,0,0,0.5);"><?php echo $std['firstname']; ?></h1>
                <p class="opacity-75 mb-0 small"><?php echo $std['lastname']; ?> | ห้อง <?php echo $std['room']; ?></p>
            </div>
            <a href="logout.php" class="btn-logout-modern">
                <i class="fa-solid fa-power-off"></i>
            </a>
        </div>
        
        <div class="top-content mt-4">
            <p class="opacity-50 small text-uppercase mb-0 fw-bold" style="letter-spacing: 2px;">CURRENT SUBJECT</p>
            <div class="d-flex align-items-end gap-2">
                <h4 class="mb-0 fw-bold"><?php echo $display_subject_name; ?></h4>
                <span class="badge border border-light fw-normal bg-transparent"><?php echo $display_subject_code; ?></span>
            </div>
        </div>
    </div>

    <div class="score-wrapper">
        <div class="score-card">
            <div>
                <div class="text-secondary small fw-bold text-uppercase mb-1">Total Score</div>
                <h1 class="fw-bold text-dark mb-0" style="font-size: 3rem; line-height: 1;">
                    <?php echo number_format($total_get, 0); ?>
                </h1>
                <div class="text-muted small fw-bold">จากคะแนนเต็ม <?php echo $total_full; ?></div>
            </div>
            
            <div class="progress-ring">
                <svg width="100" height="100">
                    <circle stroke="#f0f0f0" stroke-width="8" fill="transparent" r="42" cx="50" cy="50" />
                    <circle class="progress-ring__circle" stroke="#ff007f" stroke-width="8" stroke-linecap="round" fill="transparent" r="42" cx="50" cy="50" style="stroke-dasharray: 264; stroke-dashoffset: 264;" />
                </svg>
                <div class="progress-text">
                    <div class="fw-bold text-dark" style="font-size: 1.2rem;"><?php echo number_format($percent, 0); ?>%</div>
                </div>
            </div>
        </div>
    </div>

    <?php if($show_eval_alert): ?>
    <div class="container px-4 mb-4">
        <div class="alert alert-eval d-flex align-items-center justify-content-between p-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-dark rounded-circle p-2"><i class="fa-solid fa-bullhorn"></i></div>
                <div style="line-height: 1.2;">
                    <strong>แบบประเมินมาแล้ว!</strong><br>
                    <small class="opacity-75">ช่วยประเมินการสอนหน่อยนะครับ</small>
                </div>
            </div>
            <a href="evaluation_form.php" class="btn btn-eval shadow-sm">ทำเลย</a>
        </div>
    </div>
    <?php endif; ?>

    <div class="container px-4 mb-4">
        <div class="row g-3">
            <div class="col-6">
                <div class="stat-box">
                    <h3 class="text-success"><?php echo $count_submitted; ?></h3>
                    <span class="text-muted small fw-bold"><i class="fa-solid fa-check-circle text-success me-1"></i> ส่งแล้ว</span>
                </div>
            </div>
            <div class="col-6">
                <div class="stat-box">
                    <h3 class="<?php echo ($count_missing>0)?'text-danger':'text-muted'; ?>">
                        <?php echo $count_missing; ?>
                    </h3>
                    <span class="text-muted small fw-bold"><i class="fa-solid fa-clock text-danger me-1"></i> ค้างส่ง</span>
                </div>
            </div>
        </div>
    </div>

    <div class="container px-4">
        <h6 class="fw-bold text-muted mb-3 ps-2 border-start border-4 border-dark">&nbsp;งานและการบ้าน</h6>

        <?php if(count($works_data) > 0): ?>
            <?php foreach($works_data as $row): 
                $point = $row['student_score'];
                $has_exam = ($row['exam_id'] && $row['is_active'] == 1);
                $is_done = $row['exam_done'];
                
                // Style Logic
                $icon = "fa-book";
                $type_class = "type-score";
                if($row['work_type'] >= 3) { 
                    $icon = "fa-pen-to-square"; 
                    $type_class = "type-exam";
                }
            ?>
            <div class="work-item <?php echo $type_class; ?>">
                <div class="d-flex align-items-center" style="overflow: hidden;">
                    <div class="work-icon">
                        <i class="fa-solid <?php echo $icon; ?>"></i>
                    </div>
                    <div style="min-width: 0;">
                        <div class="fw-bold text-dark text-truncate" style="font-size: 0.95rem;"><?php echo $row['work_name']; ?></div>
                        <div class="text-muted small fw-bold" style="font-size: 0.7rem;">
                            <?php 
                                $types = [1=>'เก็บคะแนน', 2=>'เก็บคะแนน', 3=>'สอบกลางภาค', 4=>'สอบปลายภาค'];
                                echo $types[$row['work_type']];
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="text-end ps-2">
                    <?php if($point !== null): ?>
                        <div class="fw-bold text-dark fs-5"><?php echo $point; ?></div>
                        <div class="small text-muted" style="font-size: 0.65rem;">เต็ม <?php echo $row['full_score']; ?></div>
                    
                    <?php elseif($is_done): ?>
                        <span class="badge bg-secondary rounded-pill fw-normal px-3 py-2">รอตรวจ</span>
                    
                    <?php elseif($has_exam): ?>
                        <a href="exam_pin_check.php?exam_id=<?php echo $row['exam_id']; ?>" class="btn-start-exam">
                            <i class="fa-solid fa-play me-1"></i> เริ่มสอบ
                        </a>
                    
                    <?php else: ?>
                        <span class="badge bg-danger bg-opacity-10 text-danger rounded-pill fw-normal px-3 py-2">ค้างส่ง</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="fa-solid fa-mug-hot fs-1 mb-3 opacity-25"></i>
                <p>ดีใจด้วย! ยังไม่มีการบ้าน</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // --- 1. Circle Animation ---
            const circle = document.querySelector('.progress-ring__circle');
            const radius = circle.r.baseVal.value;
            const circumference = radius * 2 * Math.PI;
            
            // ตั้งค่าเริ่มต้น (ซ่อนเส้น)
            circle.style.strokeDasharray = `${circumference} ${circumference}`;
            circle.style.strokeDashoffset = circumference;

            // คำนวณ % ที่ต้องการ
            const percent = <?php echo $percent; ?>;
            const offset = circumference - (percent / 100) * circumference;

            // สั่งให้วิ่ง (หน่วงเวลา 300ms เพื่อความสวย)
            setTimeout(() => {
                circle.style.strokeDashoffset = offset;
            }, 300);
        });
    </script>
</body>
</html>