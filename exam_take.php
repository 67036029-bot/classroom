<?php
// ยืดเวลาการทำงานของ Script ป้องกัน Timeout
set_time_limit(60); 

session_start();
include 'db.php';

// 1. Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') { header("Location: login.php"); exit(); }

$std_id = $_SESSION['std_id'];
$exam_id = isset($_GET['exam_id']) ? $_GET['exam_id'] : 0;

// ตรวจสอบ PIN (ต้องผ่านด่านกรอกรหัสมาก่อน)
if (!isset($_SESSION['exam_unlocked_' . $exam_id]) || $_SESSION['exam_unlocked_' . $exam_id] !== true) {
    header("Location: exam_pin_check.php?exam_id=$exam_id");
    exit();
}

// 2. ดึงข้อมูลข้อสอบ
$sql_exam = "SELECT * FROM tb_exam_sets WHERE exam_id = '$exam_id' AND is_active = 1";
$res_exam = $conn->query($sql_exam);
if ($res_exam->num_rows == 0) { exit("<center><h1>ไม่พบข้อสอบ หรือการสอบถูกปิดแล้ว</h1></center>"); }
$exam = $res_exam->fetch_assoc();

// 3. เริ่มต้นทำข้อสอบ (บันทึกเวลาเริ่ม)
$chk_log = $conn->query("SELECT * FROM tb_exam_results WHERE std_id = '$std_id' AND exam_id = '$exam_id'");
if ($chk_log->num_rows > 0 && $chk_log->fetch_assoc()['status'] == 1) { 
    echo "<script>alert('คุณส่งข้อสอบชุดนี้ไปแล้ว'); window.location='student_dashboard.php';</script>"; 
    exit(); 
}
if ($chk_log->num_rows == 0) {
    $start_ip = $_SERVER['REMOTE_ADDR'];
    $conn->query("INSERT INTO tb_exam_results (exam_id, std_id, start_time, start_ip) VALUES ('$exam_id', '$std_id', NOW(), '$start_ip')");
}

// 4. สุ่มโจทย์
$sql_q = "SELECT * FROM tb_exam_questions WHERE exam_id = '$exam_id' ORDER BY RAND()";
$res_q = $conn->query($sql_q);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สอบ: <?php echo $exam['exam_name']; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* --- Light Focus Theme --- */
        :root {
            --bg-light: #f0f2f5;
            --card-white: #ffffff;
            --text-dark: #212529;
            --pink-neon: #ff007f;
            --navbar-bg: #212529;
        }

        body { 
            font-family: 'Sarabun', sans-serif; 
            background-color: var(--bg-light); 
            color: var(--text-dark);
            user-select: none; /* ห้ามคลุมดำ */
            padding-top: 80px; /* เว้นที่ให้ Timer Bar */
            padding-bottom: 50px;
        }

        /* 1. Sticky Timer Bar */
        .timer-bar { 
            position: fixed; top: 0; left: 0; width: 100%; height: 65px;
            background: var(--navbar-bg); 
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 20px; z-index: 1000;
            border-bottom: 3px solid var(--pink-neon);
        }
        .exam-title { font-weight: bold; font-size: 1.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 70%; }
        .timer-box { 
            background: var(--pink-neon); color: white; 
            padding: 5px 18px; border-radius: 50px; 
            font-weight: 800; font-size: 1.2rem; 
            box-shadow: 0 0 10px rgba(255, 0, 127, 0.5);
        }

        /* 2. Question Card */
        .question-card { 
            background: var(--card-white); 
            border-radius: 16px; 
            padding: 30px; 
            margin-bottom: 25px; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            transition: transform 0.2s;
        }
        .question-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.08); }

        .q-badge { 
            background: var(--text-dark); color: white; 
            font-size: 0.9rem; padding: 3px 12px; border-radius: 50px; 
            margin-right: 10px; font-weight: bold;
        }
        
        /* Highlight Styling for Exam Content */
        .q-text { 
            font-size: 1.15rem; font-weight: 500; line-height: 1.8; margin-bottom: 20px; color: #333; 
        }
        /* Style adjustments for HTML content */
        .q-text b, .q-text strong { color: #000; font-weight: 800; }
        .q-text u { text-decoration-color: var(--pink-neon); text-decoration-thickness: 2px; text-underline-offset: 3px; }
        .q-text p { margin-bottom: 0.5rem; }

        /* 3. Choice Styling */
        .choice-container { display: block; position: relative; margin-bottom: 12px; }
        .choice-container input[type="radio"] { position: absolute; opacity: 0; cursor: pointer; height: 0; width: 0; }

        .choice-box { 
            display: flex; align-items: center; padding: 15px 20px; 
            background: #f8f9fa; border: 2px solid #e9ecef; 
            border-radius: 12px; cursor: pointer; transition: all 0.2s; font-size: 1rem; color: #555;
        }
        .choice-box:hover { background: #e9ecef; }
        
        .choice-marker {
            width: 24px; height: 24px; border-radius: 50%; border: 2px solid #adb5bd; margin-right: 15px;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: 0.2s;
        }
        .choice-marker::after { content: ""; width: 12px; height: 12px; border-radius: 50%; background: white; transform: scale(0); transition: 0.2s; }

        .choice-container input:checked ~ .choice-box { 
            background: #fff0f6; border-color: var(--pink-neon); color: var(--pink-neon); font-weight: bold;
            box-shadow: 0 4px 10px rgba(255, 0, 127, 0.1);
        }
        .choice-container input:checked ~ .choice-box .choice-marker { background: var(--pink-neon); border-color: var(--pink-neon); }
        .choice-container input:checked ~ .choice-box .choice-marker::after { transform: scale(1); }

        /* 4. TF & Text Input */
        .tf-group { display: flex; gap: 15px; }
        .tf-option { flex: 1; }
        .btn-outline-custom {
            width: 100%; padding: 15px; border-radius: 12px; font-weight: bold; font-size: 1.1rem;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            border: 2px solid #e9ecef; background: #f8f9fa; color: #6c757d; transition: 0.2s;
        }
        .btn-check:checked + .btn-true { background-color: #198754; color: white; border-color: #198754; box-shadow: 0 4px 10px rgba(25, 135, 84, 0.3); }
        .btn-check:checked + .btn-false { background-color: #dc3545; color: white; border-color: #dc3545; box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3); }

        .text-ans-input { background: #fff; border: 2px solid #dee2e6; color: #333; border-radius: 10px; padding: 15px; width: 100%; font-size: 1.1rem; }
        .text-ans-input:focus { outline: none; border-color: var(--pink-neon); box-shadow: 0 0 0 4px rgba(255, 0, 127, 0.1); }

        /* Submit Button */
        .btn-submit-exam {
            background: linear-gradient(90deg, #212529, #343a40); border: none; padding: 15px 50px; border-radius: 50px;
            color: white; font-weight: 800; font-size: 1.2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: 0.3s;
        }
        .btn-submit-exam:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.3); }

        /* Modal */
        .modal-header-danger { background-color: #ffe6e6; border-bottom: 2px solid #dc3545; }
        .text-danger-dark { color: #842029; }
    </style>
</head>
<body oncontextmenu="return false;"> 

    <div class="timer-bar">
        <div class="exam-title text-truncate">
            <i class="fa-solid fa-file-lines text-warning me-2"></i> <?php echo $exam['exam_name']; ?>
        </div>
        <div class="timer-box" id="timer">--:--</div>
    </div>

    <div class="container pb-5" style="max-width: 800px;">
        <form action="exam_save.php" method="POST" id="examForm">
            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
            <input type="hidden" name="cheat_count" id="cheat_count" value="0">

            <?php 
            if($res_q->num_rows > 0): 
                $i = 1;
                while($q = $res_q->fetch_assoc()):
                    $qid = $q['question_id'];
            ?>
            <div class="question-card">
                <div class="d-flex justify-content-between mb-3">
                    <span class="q-badge">ข้อที่ <?php echo $i++; ?></span>
                    <span class="text-muted small fw-bold"><i class="fa-solid fa-star text-warning"></i> <?php echo $q['points']; ?> คะแนน</span>
                </div>
                
                <div class="q-text">
                    <?php echo $q['question_text']; ?>
                </div>
                
                <?php if($q['image_path']): ?>
                    <div class="mb-4 text-center">
                        <img src="uploads/<?php echo $q['image_path']; ?>" class="img-fluid rounded border shadow-sm" style="max-height: 300px;">
                    </div>
                <?php endif; ?>
                <?php if($q['audio_path']): ?>
                    <div class="mb-4 p-2 bg-light rounded border text-center">
                        <audio controls class="w-100"><source src="uploads/<?php echo $q['audio_path']; ?>" type="audio/mpeg"></audio>
                    </div>
                <?php endif; ?>

                <div class="mt-3">
                    <?php if($q['question_type'] == 1): // ปรนัย ?>
                        <?php 
                            $choices = [ ['k'=>'a','v'=>$q['choice_a']], ['k'=>'b','v'=>$q['choice_b']], ['k'=>'c','v'=>$q['choice_c']], ['k'=>'d','v'=>$q['choice_d']] ];
                            shuffle($choices);
                        ?>
                        <?php foreach($choices as $c): $unique_id = "q_".$qid."_".$c['k']; ?>
                        <label class="choice-container" for="<?php echo $unique_id; ?>">
                            <input type="radio" name="ans[<?php echo $qid; ?>]" id="<?php echo $unique_id; ?>" value="<?php echo $c['k']; ?>">
                            <div class="choice-box">
                                <span class="choice-marker"></span>
                                <?php echo $c['v']; ?>
                            </div>
                        </label>
                        <?php endforeach; ?>

                    <?php elseif($q['question_type'] == 3): // True/False ?>
                        <div class="tf-group">
                            <div class="tf-option">
                                <input type="radio" class="btn-check" name="ans[<?php echo $qid; ?>]" id="q_<?php echo $qid; ?>_t" value="True">
                                <label class="btn btn-outline-custom btn-true" for="q_<?php echo $qid; ?>_t">
                                    <i class="fa-solid fa-check-circle"></i> TRUE (ถูก)
                                </label>
                            </div>
                            <div class="tf-option">
                                <input type="radio" class="btn-check" name="ans[<?php echo $qid; ?>]" id="q_<?php echo $qid; ?>_f" value="False">
                                <label class="btn btn-outline-custom btn-false" for="q_<?php echo $qid; ?>_f">
                                    <i class="fa-solid fa-times-circle"></i> FALSE (ผิด)
                                </label>
                            </div>
                        </div>

                    <?php else: // อัตนัย ?>
                        <label class="text-muted small fw-bold mb-2">คำตอบของคุณ:</label>
                        <input type="text" name="ans[<?php echo $qid; ?>]" class="text-ans-input" placeholder="พิมพ์คำตอบที่นี่..." autocomplete="off">
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; else: ?>
                <div class="text-center py-5 text-muted">ไม่พบข้อสอบ</div>
            <?php endif; ?>

            <div class="text-center mt-5 mb-5">
                <button type="submit" class="btn-submit-exam" onclick="return confirm('ยืนยันส่งคำตอบ?');">
                    <i class="fa-solid fa-paper-plane me-2"></i> ส่งกระดาษคำตอบ
                </button>
            </div>
        </form>
    </div>

    <div class="modal fade" id="cheatModal" data-bs-backdrop="static" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header modal-header-danger">
                    <h5 class="modal-title fw-bold text-danger-dark"><i class="fa-solid fa-triangle-exclamation"></i> ตรวจพบพฤติกรรมต้องสงสัย!</h5>
                </div>
                <div class="modal-body text-center p-4">
                    <i class="fa-solid fa-eye-slash fa-4x text-danger mb-3 opacity-50"></i>
                    <h4 class="fw-bold text-dark">ห้ามสลับหน้าจอระหว่างสอบ</h4>
                    <p class="text-muted">ระบบได้บันทึกพฤติกรรมของคุณแล้ว<br>หากทำผิดครบ <strong class="text-danger">3 ครั้ง</strong> ระบบจะส่งข้อสอบทันที</p>
                    <div class="mt-3">
                        <span class="badge bg-danger fs-6 rounded-pill px-3 py-2">เตือนครั้งที่ <span id="warn-count">0</span> / 3</span>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-dark fw-bold px-4 rounded-pill" data-bs-dismiss="modal">รับทราบ</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- 1. Timer Logic ---
        let timeLeft = <?php echo $exam['time_limit']; ?> * 60; 
        const timerEl = document.getElementById('timer');
        
        const timerInterval = setInterval(() => {
            let m = Math.floor(timeLeft / 60); 
            let s = timeLeft % 60; 
            s = s < 10 ? '0' + s : s;
            timerEl.innerHTML = `${m}:${s}`;
            
            // เปลี่ยนสีเวลาเมื่อใกล้หมด (น้อยกว่า 1 นาที)
            if (timeLeft < 60) { 
                timerEl.style.backgroundColor = '#dc3545'; 
                timerEl.style.animation = 'pulse-timer 0.5s infinite'; 
            }

            if (timeLeft <= 0) { 
                clearInterval(timerInterval); 
                document.getElementById('examForm').submit(); 
            }
            timeLeft--;
        }, 1000);

        // --- 2. Anti-Cheat Logic ---
        let cheatCount = 0;
        const cheatModal = new bootstrap.Modal(document.getElementById('cheatModal'));
        
        function logCheat(type) {
            const formData = new FormData();
            formData.append('exam_id', '<?php echo $exam_id; ?>');
            formData.append('std_id', '<?php echo $std_id; ?>');
            formData.append('event_type', type);
            fetch('cheat_log.php', { method: 'POST', body: formData }).catch(console.error);
        }

        document.addEventListener("visibilitychange", () => {
            if (document.hidden) {
                cheatCount++;
                document.getElementById('cheat_count').value = cheatCount;
                document.getElementById('warn-count').innerText = cheatCount;
                cheatModal.show();
                logCheat('TAB_SWITCH');

                if (cheatCount >= 3) { 
                    alert('คุณทำผิดกฎเกินกำหนด ระบบจะส่งข้อสอบทันที');
                    document.getElementById('examForm').submit(); 
                }
            }
        });
        
        // Disable Right Click & Copy
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 'c' || e.key === 'v' || e.key === 'x' || e.key === 'a') {
                    e.preventDefault();
                }
            }
        });

        // Keep Alive
        function keepSessionAlive() { fetch("keep_alive.php"); }
        setInterval(keepSessionAlive, 300000); 
    </script>
</body>
</html>