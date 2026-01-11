<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

$exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;

if ($exam_id == 0) {
    echo "<script>alert('ไม่พบรหัสข้อสอบ'); window.history.back();</script>";
    exit();
}

// เริ่มกระบวนการ Re-grade
$conn->begin_transaction();

try {
    // 1. ดึงเฉลยล่าสุด (Key Map)
    $key_map = [];
    $sql_q = "SELECT question_id, question_type, correct_answer, points FROM tb_exam_questions WHERE exam_id = '$exam_id'";
    $res_q = $conn->query($sql_q);
    while ($row = $res_q->fetch_assoc()) {
        $key_map[$row['question_id']] = [
            'correct' => trim($row['correct_answer']),
            'points' => floatval($row['points']),
            'type' => $row['question_type']
        ];
    }

    // 2. ดึงรายชื่อนักเรียนที่ส่งข้อสอบแล้ว
    $sql_std = "SELECT std_id FROM tb_exam_results WHERE exam_id = '$exam_id' AND status = 1";
    $res_std = $conn->query($sql_std);
    $updated_count = 0;

    while ($std = $res_std->fetch_assoc()) {
        $std_id = $std['std_id'];
        $total_score = 0;

        // 3. ดึงคำตอบของนักเรียนคนนี้
        $sql_ans = "SELECT question_id, student_answer FROM tb_exam_answer_log WHERE exam_id = '$exam_id' AND std_id = '$std_id'";
        $res_ans = $conn->query($sql_ans);

        while ($ans = $res_ans->fetch_assoc()) {
            $qid = $ans['question_id'];
            $student_ans = trim($ans['student_answer']);
            
            // ถ้าข้อนี้มีอยู่ในเฉลย (กันกรณีลบโจทย์ทิ้งไปแล้ว)
            if (isset($key_map[$qid])) {
                $correct = $key_map[$qid]['correct'];
                $points = $key_map[$qid]['points'];
                
                $is_correct = 0;
                $score_earned = 0;

                // ตรวจคำตอบใหม่
                if (strcasecmp($student_ans, $correct) == 0) {
                    $is_correct = 1;
                    $score_earned = $points;
                    $total_score += $points;
                }

                // อัปเดตสถานะถูก/ผิด ใน Log รายข้อ (เพื่อให้หน้าวิเคราะห์ข้อสอบถูกต้อง)
                $stmt_log = $conn->prepare("UPDATE tb_exam_answer_log SET is_correct = ?, score_earned = ? WHERE exam_id = ? AND std_id = ? AND question_id = ?");
                $stmt_log->bind_param("idiii", $is_correct, $score_earned, $exam_id, $std_id, $qid);
                $stmt_log->execute();
                $stmt_log->close();
            }
        }

        // ปัดเศษคะแนนรวม
        $final_score = round($total_score);

        // 4. อัปเดตตารางผลสอบรวม (tb_exam_results)
        $conn->query("UPDATE tb_exam_results SET score_obtained = '$final_score' WHERE exam_id = '$exam_id' AND std_id = '$std_id'");

        // 5. อัปเดตตารางคะแนนเก็บ (tb_score)
        // ต้องหา work_id ก่อน
        $work_res = $conn->query("SELECT work_id FROM tb_exam_sets WHERE exam_id = '$exam_id'");
        if ($work_res->num_rows > 0) {
            $work_id = $work_res->fetch_assoc()['work_id'];
            $conn->query("UPDATE tb_score SET score_point = '$final_score' WHERE std_id = '$std_id' AND work_id = '$work_id'");
        }

        $updated_count++;
    }

    $conn->commit();
    echo "<script>alert('✅ คำนวณคะแนนใหม่เรียบร้อยแล้ว ($updated_count คน)'); window.location='exam_monitor.php?exam_id=$exam_id';</script>";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
?>