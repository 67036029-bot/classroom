<?php
session_start();
include 'db.php';

// ตั้งค่า Timezone ให้ตรงกับไทย
date_default_timezone_set('Asia/Bangkok');

// 1. Security Check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

$std_id = $_SESSION['std_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
    $answers = isset($_POST['ans']) ? $_POST['ans'] : []; 
    $cheat_count = isset($_POST['cheat_count']) ? intval($_POST['cheat_count']) : 0;

    if ($exam_id == 0) exit("Invalid Exam ID");

    // เริ่ม Transaction
    $conn->begin_transaction();

    try {
        // --- 1. ตรวจให้คะแนน ---
        $sql_key = "SELECT question_id, question_type, correct_answer, points FROM tb_exam_questions WHERE exam_id = ?";
        $stmt_key = $conn->prepare($sql_key);
        $stmt_key->bind_param("i", $exam_id);
        $stmt_key->execute();
        $res_key = $stmt_key->get_result();

        $score = 0;
        $log_data = []; 

        while ($key = $res_key->fetch_assoc()) {
            $qid = $key['question_id'];
            $points = $key['points'];
            $correct = trim($key['correct_answer']);
            $q_type = $key['question_type'];

            $student_ans = isset($answers[$qid]) ? trim($answers[$qid]) : "";
            
            $is_correct = 0;
            $point_earned = 0;

            if ($q_type != 2) { 
                if (strcasecmp($student_ans, $correct) == 0) {
                    $score += $points;
                    $is_correct = 1;
                    $point_earned = $points;
                }
            } else { 
                if (strcasecmp($student_ans, $correct) == 0) {
                    $score += $points;
                    $is_correct = 1;
                    $point_earned = $points;
                }
            }

            $log_data[] = [
                'qid' => $qid,
                'ans' => $student_ans,
                'is_correct' => $is_correct,
                'score' => $point_earned
            ];
        }
        $stmt_key->close();

        // คะแนนสุทธิ (ปัดเศษ)
        $final_score = round($score); 

        // --- 2. บันทึกคำตอบรายข้อ (Log) ---
        $del_log = $conn->prepare("DELETE FROM tb_exam_answer_log WHERE exam_id = ? AND std_id = ?");
        $del_log->bind_param("ii", $exam_id, $std_id);
        $del_log->execute();
        $del_log->close();

        $ins_log = $conn->prepare("INSERT INTO tb_exam_answer_log (exam_id, std_id, question_id, student_answer, is_correct, score_earned) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($log_data as $log) {
            $ins_log->bind_param("iiisid", $exam_id, $std_id, $log['qid'], $log['ans'], $log['is_correct'], $log['score']);
            $ins_log->execute();
        }
        $ins_log->close();

        // --- 3. อัปเดตตารางผลสอบ (หัวใจสำคัญที่แก้ปัญหา) ---
        // พยายาม UPDATE ก่อน
        $sql_update_exam = "UPDATE tb_exam_results 
                            SET submit_time = NOW(), 
                                score_obtained = ?, 
                                cheat_count = ?, 
                                status = 1 
                            WHERE exam_id = ? AND std_id = ?";
        $stmt1 = $conn->prepare($sql_update_exam);
        $stmt1->bind_param("diii", $final_score, $cheat_count, $exam_id, $std_id);
        $stmt1->execute();
        
        // [FIX] เช็คว่ามีแถวถูกแก้ไขไหม? ถ้าเป็น 0 แปลว่าหาไม่เจอ หรือข้อมูลไม่เปลี่ยน
        if ($stmt1->affected_rows == 0) {
            // เช็คว่ามีข้อมูลอยู่แล้วแต่สถานะเป็น 1 แล้วหรือเปล่า?
            $check_exist = $conn->query("SELECT status FROM tb_exam_results WHERE exam_id = '$exam_id' AND std_id = '$std_id'");
            
            if ($check_exist->num_rows == 0) {
                // ถ้าไม่มีข้อมูลเลย -> ให้ INSERT ใหม่ (กันเหนียว)
                $ip = $_SERVER['REMOTE_ADDR'];
                $stmt_fix = $conn->prepare("INSERT INTO tb_exam_results (exam_id, std_id, start_time, submit_time, score_obtained, cheat_count, status, start_ip) VALUES (?, ?, NOW(), NOW(), ?, ?, 1, ?)");
                $stmt_fix->bind_param("iiids", $exam_id, $std_id, $final_score, $cheat_count, $ip);
                $stmt_fix->execute();
                $stmt_fix->close();
            } else {
                // ถ้ามีข้อมูลอยู่แล้ว แต่ affected_rows = 0 อาจเพราะข้อมูลเดิมเหมือนกันเป๊ะ (ไม่มีอะไรต้องกังวล)
            }
        }
        $stmt1->close();

        // --- 4. อัปเดตลง Gradebook (tb_score) ---
        $res_work = $conn->query("SELECT work_id FROM tb_exam_sets WHERE exam_id = '$exam_id'");
        if ($res_work->num_rows > 0) {
            $work_id = $res_work->fetch_assoc()['work_id'];
            
            // ใช้ REPLACE INTO เพื่อความชัวร์ (ถ้ามีทับ ถ้าไม่มีเพิ่ม)
            $conn->query("DELETE FROM tb_score WHERE std_id = '$std_id' AND work_id = '$work_id'");
            $conn->query("INSERT INTO tb_score (std_id, work_id, score_point) VALUES ('$std_id', '$work_id', '$final_score')");
        }

        $conn->commit();

        echo "<script>
                alert('✅ ส่งข้อสอบเรียบร้อย!\\nคะแนนที่คุณได้คือ: $final_score คะแนน');
                window.location = 'student_dashboard.php';
              </script>";

    } catch (Exception $e) {
        $conn->rollback();
        echo "<div style='color:red; text-align:center; margin-top:50px;'>";
        echo "<h3>❌ เกิดข้อผิดพลาดในการบันทึก</h3>";
        echo "<p>ระบบกำลังพยายามบันทึกข้อมูล กรุณาลองกด Refresh หรือแจ้งครูผู้สอน</p>";
        echo "<p>Error: " . $e->getMessage() . "</p>";
        echo "<a href='student_dashboard.php'>กลับหน้าหลัก</a>";
        echo "</div>";
    }
} else {
    header("Location: student_dashboard.php");
}
?>