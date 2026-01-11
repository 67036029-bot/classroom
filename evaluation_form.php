<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') { header("Location: login.php"); exit(); }
include 'db.php';

$std_id = $_SESSION['std_id'];

// เช็ค config
$config = $conn->query("SELECT is_active FROM tb_eval_config WHERE id = 1")->fetch_assoc();
if ($config['is_active'] == 0) { header("Location: student_dashboard.php"); exit(); }

// เช็คประวัติ
$check = $conn->query("SELECT id FROM tb_eval_results WHERE std_id = '$std_id' LIMIT 1");
if ($check->num_rows > 0) {
    echo "<script>alert('คุณได้ทำการประเมินไปแล้ว'); window.location='student_dashboard.php';</script>";
    exit();
}

// บันทึก
if (isset($_POST['submit_eval'])) {
    $answers = $_POST['score']; 
    if (is_array($answers)) {
        foreach ($answers as $qid => $score) {
            $qid = (int)$qid; $score = (int)$score;
            $conn->query("INSERT INTO tb_eval_results (std_id, q_id, score) VALUES ('$std_id', '$qid', '$score')");
        }
        echo "<script>alert('ขอบคุณสำหรับการประเมินครับ'); window.location='student_dashboard.php';</script>";
    }
}

$questions = $conn->query("SELECT * FROM tb_eval_questions ORDER BY q_id ASC");
$total_q = $questions->num_rows;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>แบบประเมินการสอน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f0f2f5; height: 100vh; display: flex; align-items: center; justify-content: center; }
        
        .eval-card { 
            width: 100%; max-width: 600px; height: 100%; max-height: 100%;
            background: white; display: flex; flex-direction: column;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        
        @media (min-width: 768px) {
            .eval-card { height: auto; min-height: 600px; max-height: 90vh; border-radius: 20px; margin: 20px; }
        }

        .header-area { padding: 20px 20px 0 20px; flex-shrink: 0; background: white; z-index: 10; }
        .progress { height: 8px; border-radius: 10px; background-color: #eee; margin-top: 15px; }
        .progress-bar { background-color: #d63384; transition: width 0.3s ease; }

        /* พื้นที่คำถาม (Scroll ได้) */
        .question-container { 
            flex-grow: 1; 
            padding: 20px; 
            overflow-y: auto; 
            -webkit-overflow-scrolling: touch;
        }
        
        .step { display: none; animation: fadeIn 0.3s; padding-bottom: 40px; } /* เพิ่ม Padding ล่าง */
        .step.active { display: block; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        .q-number { font-size: 0.9rem; color: #999; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; display: block; }
        .q-text { font-size: 1.5rem; font-weight: 800; color: #333; line-height: 1.4; margin-bottom: 25px; }

        .rating-group { display: flex; flex-direction: column; gap: 12px; }
        .rating-item input { display: none; }
        
        .rating-label { 
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 20px; border: 2px solid #f0f0f0; border-radius: 12px;
            cursor: pointer; transition: 0.2s; background: white; color: #555;
        }
        
        .rating-item input:checked + .rating-label { 
            border-color: #d63384; background-color: #fff0f6; color: #d63384; font-weight: bold;
            box-shadow: 0 4px 12px rgba(214, 51, 132, 0.15); transform: scale(1.01);
        }
        .rating-label:hover { border-color: #d63384; }
        .score-num { font-size: 1.2rem; font-weight: 800; }

        /* ปุ่มย้อนกลับแบบใหม่ (ฝังในเนื้อหา) */
        .btn-back-inline {
            display: block; width: 100%; margin-top: 30px;
            padding: 12px; text-align: center;
            background-color: #f8f9fa; border: 1px solid #ddd;
            border-radius: 50px; color: #6c757d; font-weight: bold;
            transition: 0.2s; text-decoration: none;
        }
        .btn-back-inline:hover { background-color: #e2e6ea; color: #333; }
        .btn-back-inline:disabled { opacity: 0.5; pointer-events: none; display: none; } /* ซ่อนถ้าเป็นข้อแรก */
    </style>
</head>
<body>

    <div class="eval-card">
        <div class="header-area">
            <div class="d-flex justify-content-between align-items-end">
                <small class="text-muted fw-bold">แบบประเมินการสอน</small>
                <small class="text-pink fw-bold"><span id="current-step-num">1</span> / <?php echo $total_q; ?></small>
            </div>
            <div class="progress">
                <div class="progress-bar" role="progressbar" style="width: 0%" id="progress-bar"></div>
            </div>
        </div>

        <form method="POST" id="evalForm" class="d-flex flex-column flex-grow-1" style="overflow: hidden;">
            <div class="question-container">
                
                <?php 
                $idx = 0;
                if($questions->num_rows > 0):
                    while($q = $questions->fetch_assoc()): 
                        $idx++;
                ?>
                <div class="step" id="step-<?php echo $idx; ?>">
                    <span class="q-number">Question <?php echo $idx; ?></span>
                    <h2 class="q-text"><?php echo $q['q_text']; ?></h2>
                    
                    <div class="rating-group">
                        <div class="rating-item">
                            <input type="radio" name="score[<?php echo $q['q_id']; ?>]" id="q<?php echo $q['q_id']; ?>_5" value="5" onclick="autoNext(<?php echo $idx; ?>)">
                            <label class="rating-label" for="q<?php echo $q['q_id']; ?>_5"><span>🤩 มากที่สุด</span><span class="score-num">5</span></label>
                        </div>
                        <div class="rating-item">
                            <input type="radio" name="score[<?php echo $q['q_id']; ?>]" id="q<?php echo $q['q_id']; ?>_4" value="4" onclick="autoNext(<?php echo $idx; ?>)">
                            <label class="rating-label" for="q<?php echo $q['q_id']; ?>_4"><span>😊 มาก</span><span class="score-num">4</span></label>
                        </div>
                        <div class="rating-item">
                            <input type="radio" name="score[<?php echo $q['q_id']; ?>]" id="q<?php echo $q['q_id']; ?>_3" value="3" onclick="autoNext(<?php echo $idx; ?>)">
                            <label class="rating-label" for="q<?php echo $q['q_id']; ?>_3"><span>😐 ปานกลาง</span><span class="score-num">3</span></label>
                        </div>
                        <div class="rating-item">
                            <input type="radio" name="score[<?php echo $q['q_id']; ?>]" id="q<?php echo $q['q_id']; ?>_2" value="2" onclick="autoNext(<?php echo $idx; ?>)">
                            <label class="rating-label" for="q<?php echo $q['q_id']; ?>_2"><span>😕 น้อย</span><span class="score-num">2</span></label>
                        </div>
                        <div class="rating-item">
                            <input type="radio" name="score[<?php echo $q['q_id']; ?>]" id="q<?php echo $q['q_id']; ?>_1" value="1" onclick="autoNext(<?php echo $idx; ?>)">
                            <label class="rating-label" for="q<?php echo $q['q_id']; ?>_1"><span>😞 น้อยที่สุด</span><span class="score-num">1</span></label>
                        </div>
                    </div>

                    <?php if($idx > 1): ?>
                    <button type="button" class="btn-back-inline" onclick="prevStep()">
                        <i class="fa-solid fa-arrow-up"></i> ย้อนกลับไปข้อก่อนหน้า
                    </button>
                    <?php endif; ?>

                </div>
                <?php endwhile; endif; ?>

                <div class="step" id="step-<?php echo $total_q + 1; ?>">
                    <div class="text-center py-5">
                        <i class="fa-solid fa-check-circle text-success" style="font-size: 5rem;"></i>
                        <h2 class="fw-bold mt-4">ประเมินครบถ้วนแล้ว</h2>
                        <p class="text-muted mb-5">ตรวจสอบความถูกต้อง และกดปุ่มส่งข้อมูล</p>
                        
                        <button type="submit" name="submit_eval" class="btn btn-pink btn-lg w-100 rounded-pill py-3 shadow fw-bold mb-3">
                            ยืนยันการส่งแบบประเมิน
                        </button>
                        
                        <button type="button" class="btn btn-light text-muted w-100 rounded-pill py-3 border" onclick="prevStep()">
                            กลับไปแก้ไข
                        </button>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <script>
        let currentStep = 1;
        const totalSteps = <?php echo $total_q; ?>;

        function showStep(step) {
            // ซ่อนทุกหน้า
            document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
            // โชว์หน้าปัจจุบัน
            const currentEl = document.getElementById('step-' + step);
            if(currentEl) currentEl.classList.add('active');

            // อัปเดต Progress Bar
            let progress = ((step - 1) / totalSteps) * 100;
            if (step > totalSteps) progress = 100;
            document.getElementById('progress-bar').style.width = progress + '%';
            
            if(step <= totalSteps) document.getElementById('current-step-num').innerText = step;
        }

        function nextStep() {
            if (currentStep <= totalSteps) {
                currentStep++;
                showStep(currentStep);
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                currentStep--;
                showStep(currentStep);
            }
        }

        function autoNext(stepIndex) {
            setTimeout(() => {
                if (stepIndex === currentStep) {
                    nextStep();
                }
            }, 300);
        }

        showStep(1);
    </script>
</body>
</html>