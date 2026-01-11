<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$std_id = isset($_GET['std_id']) ? (int)$_GET['std_id'] : 0;

if ($exam_id == 0 || $std_id == 0) exit("Invalid Request");

// 1. ข้อมูลนักเรียน & ข้อสอบ
$student = $conn->query("SELECT * FROM tb_students WHERE id = '$std_id'")->fetch_assoc();
$exam_set_res = $conn->query("SELECT e.exam_id, e.work_id, e.exam_name, w.full_score FROM tb_exam_sets e JOIN tb_work w ON e.work_id = w.work_id WHERE e.exam_id = '$exam_id'");
$exam_set = $exam_set_res->fetch_assoc();
$work_id = $exam_set['work_id'];

// --- Logic A: ล้างประวัติ (Reset) ---
if (isset($_POST['reset_attempt'])) {
    if ($work_id) {
        $conn->query("DELETE FROM tb_score WHERE work_id='$work_id' AND std_id='$std_id'");
        $conn->query("DELETE FROM tb_exam_answer_log WHERE exam_id='$exam_id' AND std_id='$std_id'");
        $conn->query("DELETE FROM tb_exam_results WHERE exam_id='$exam_id' AND std_id='$std_id'");
        echo "<script>alert('รีเซ็ตเรียบร้อย! นักเรียนสามารถเข้าสอบใหม่ได้ทันที'); window.location='exam_monitor.php?exam_id=$exam_id';</script>";
    }
}

// --- Logic B: บันทึกคะแนนใหม่ (Manual Grade) ---
if (isset($_POST['save_grading'])) {
    $corrections = isset($_POST['is_correct']) ? $_POST['is_correct'] : []; 
    
    // ดึงคะแนนเต็มรายข้อ
    $q_points = [];
    $res_points = $conn->query("SELECT question_id, points FROM tb_exam_questions WHERE exam_id = '$exam_id'");
    while($row = $res_points->fetch_assoc()) { $q_points[$row['question_id']] = $row['points']; }

    $total_new_score = 0;
    foreach ($corrections as $qid => $status) {
        $conn->query("UPDATE tb_exam_answer_log SET is_correct = '$status' WHERE exam_id = '$exam_id' AND std_id = '$std_id' AND question_id = '$qid'");
        if ($status == 1) {
            $p = isset($q_points[$qid]) ? $q_points[$qid] : 1;
            $total_new_score += $p;
        }
    }

    // 1. อัปเดตผลสอบในตาราง exam_results
    $conn->query("UPDATE tb_exam_results SET score_obtained = '$total_new_score' WHERE exam_id = '$exam_id' AND std_id = '$std_id'");
    
    // 2. อัปเดตคะแนนเก็บในตาราง tb_score (เชื่อมโยงกัน)
    // [FIXED]: เปลี่ยนจาก SELECT id เป็น SELECT score_id ตามโครงสร้างจริง
    $chk = $conn->query("SELECT score_id FROM tb_score WHERE work_id='$work_id' AND std_id='$std_id'");
    
    if ($chk->num_rows > 0) {
        $conn->query("UPDATE tb_score SET score_point = '$total_new_score' WHERE work_id='$work_id' AND std_id='$std_id'");
    } else {
        $conn->query("INSERT INTO tb_score (std_id, work_id, score_point) VALUES ('$std_id', '$work_id', '$total_new_score')");
    }

    echo "<script>alert('บันทึกผลการตรวจเรียบร้อย (คะแนนใหม่: $total_new_score)'); window.location='admin_grading.php?exam_id=$exam_id&std_id=$std_id';</script>";
}

// 2. ดึงโจทย์ + คำตอบ
$sql = "SELECT q.*, a.student_answer, a.is_correct as status
        FROM tb_exam_questions q
        LEFT JOIN tb_exam_answer_log a ON q.question_id = a.question_id AND a.std_id = '$std_id'
        WHERE q.exam_id = '$exam_id'
        ORDER BY q.question_id ASC";
$result = $conn->query($sql);

// คะแนนปัจจุบัน
$current_score_row = $conn->query("SELECT score_obtained FROM tb_exam_results WHERE exam_id = '$exam_id' AND std_id = '$std_id'");
$current_score = ($current_score_row->num_rows > 0) ? $current_score_row->fetch_assoc()['score_obtained'] : "0";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ตรวจข้อสอบ</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 25px; padding-bottom: 50px; }
        
        /* Sticky Header Info */
        .info-bar {
            background: white; padding: 15px 20px; border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-left: 5px solid #d63384;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 15px; z-index: 1000; margin-bottom: 25px;
            flex-wrap: wrap; gap: 10px;
        }

        /* Question Card */
        .q-card {
            background: white; border-radius: 12px; border: 1px solid #eee;
            margin-bottom: 20px; overflow: hidden; transition: 0.2s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }
        .q-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.08); }
        
        /* Status Borders */
        .status-correct { border-left: 5px solid #198754; background-color: #f8fff9; }
        .status-wrong { border-left: 5px solid #dc3545; background-color: #fff5f5; }
        
        /* Card Body */
        .q-body { padding: 20px; }
        .q-text { font-size: 1.1rem; font-weight: bold; color: #212529; margin-bottom: 15px; }
        
        /* Answer Comparison Box */
        .ans-box {
            background: #fff; border: 1px solid #dee2e6; border-radius: 10px; padding: 15px;
            display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between;
        }
        .ans-student { flex: 1; min-width: 200px; }
        .ans-key { flex: 1; min-width: 200px; border-left: 1px dashed #ccc; padding-left: 15px; color: #198754; font-weight: bold; }
        
        /* Checkbox Button Group (Custom) */
        .btn-check-group { display: flex; gap: 5px; }
        .btn-check-custom {
            flex: 1; padding: 8px 15px; border-radius: 50px; border: 1px solid #ddd;
            background: white; color: #6c757d; cursor: pointer; font-weight: bold;
            display: flex; align-items: center; justify-content: center; transition: 0.2s;
        }
        .btn-check-custom:hover { background: #f1f1f1; }
        
        /* Input Checked States */
        input[type="radio"]:checked + .btn-correct {
            background-color: #198754; color: white; border-color: #198754;
            box-shadow: 0 4px 10px rgba(25, 135, 84, 0.3);
        }
        input[type="radio"]:checked + .btn-wrong {
            background-color: #dc3545; color: white; border-color: #dc3545;
            box-shadow: 0 4px 10px rgba(220, 53, 69, 0.3);
        }
        
        .text-pink { color: #d63384; }
        .btn-pink { background-color: #d63384; color: white; border: none; }
        .btn-pink:hover { background-color: #a61e61; color: white; }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            
            <form method="POST">
                <div class="info-bar">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-light rounded-circle p-2 border"><i class="fa-solid fa-user-graduate fs-4 text-muted"></i></div>
                        <div>
                            <h5 class="fw-bold mb-0 text-dark lh-1">
                                <?php echo $student['title'].$student['firstname']." ".$student['lastname']; ?>
                            </h5>
                            <small class="text-muted d-block mt-1">
                                คะแนน: <span class="fw-bold text-pink fs-6"><?php echo $current_score; ?></span> / <?php echo $exam_set['full_score']; ?>
                            </small>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" name="save_grading" class="btn btn-pink shadow-sm fw-bold px-4 rounded-pill">
                            <i class="fa-solid fa-floppy-disk me-1"></i> บันทึก
                        </button>
                        
                        <div class="vr mx-1"></div>
                        
                        <button type="submit" name="reset_attempt" class="btn btn-outline-danger btn-sm rounded-pill" onclick="return confirm('⚠️ ยืนยันล้างผลสอบทั้งหมด?');" formnovalidate>
                            <i class="fa-solid fa-rotate-right"></i> รีเซ็ต
                        </button>
                        <a href="exam_monitor.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-dark btn-sm rounded-pill">กลับ</a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-10 mx-auto">
                        
                        <?php 
                        $i = 1;
                        if ($result->num_rows > 0):
                            while ($row = $result->fetch_assoc()): 
                                $is_correct = ($row['status'] == 1);
                                $card_class = $is_correct ? "status-correct" : "status-wrong";
                        ?>
                        
                        <div class="q-card <?php echo $card_class; ?>">
                            <div class="q-body">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-dark rounded-pill px-3">ข้อที่ <?php echo $i++; ?></span>
                                    <span class="badge bg-white text-dark border"><?php echo $row['points']; ?> คะแนน</span>
                                </div>
                                
                                <div class="q-text">
                                    <?php echo nl2br($row['question_text']); ?>
                                    <?php if($row['image_path']): ?>
                                        <br><img src="uploads/<?php echo $row['image_path']; ?>" style="max-height:150px; border-radius:8px; margin-top:10px; border:1px solid #ddd;">
                                    <?php endif; ?>
                                </div>

                                <div class="ans-box">
                                    <div class="ans-student">
                                        <small class="text-muted d-block fw-bold">คำตอบนักเรียน:</small>
                                        <span class="<?php echo $is_correct ? 'text-success' : 'text-danger'; ?> fs-5">
                                            <?php 
                                                if($row['question_type']==1) {
                                                    $k = $row['student_answer'];
                                                    echo ($k) ? "<span class='fw-bold text-uppercase'>$k</span>. ".$row['choice_'.$k] : "<em class='text-muted'>- ไม่ตอบ -</em>";
                                                } else {
                                                    echo htmlspecialchars($row['student_answer'] ?? "- ไม่ตอบ -");
                                                }
                                            ?>
                                        </span>
                                    </div>
                                    <div class="ans-key">
                                        <small class="text-secondary d-block fw-normal mb-1"><i class="fa-solid fa-key"></i> เฉลย:</small>
                                        <?php 
                                            if($row['question_type']==1) {
                                                $k = $row['correct_answer'];
                                                echo "<span class='text-uppercase'>$k</span>. ".$row['choice_'.$k];
                                            } else {
                                                echo htmlspecialchars($row['correct_answer']);
                                            }
                                        ?>
                                    </div>
                                </div>

                                <div class="mt-3 d-flex justify-content-end align-items-center gap-3">
                                    <small class="text-muted fw-bold">ผลการตรวจ:</small>
                                    <div class="btn-check-group" style="width: 180px;">
                                        <label style="flex:1;">
                                            <input type="radio" class="d-none" name="is_correct[<?php echo $row['question_id']; ?>]" value="1" <?php if($is_correct) echo "checked"; ?>>
                                            <span class="btn-check-custom btn-correct"><i class="fa-solid fa-check me-1"></i> ถูก</span>
                                        </label>
                                        <label style="flex:1;">
                                            <input type="radio" class="d-none" name="is_correct[<?php echo $row['question_id']; ?>]" value="0" <?php if(!$is_correct) echo "checked"; ?>>
                                            <span class="btn-check-custom btn-wrong"><i class="fa-solid fa-xmark me-1"></i> ผิด</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php endwhile; ?>
                        
                        <?php else: ?>
                            <div class="alert alert-warning text-center">ไม่พบข้อมูลคำตอบ</div>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>