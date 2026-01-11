<?php
// api_save_score.php - รองรับ Batch Save
ob_start(); 
session_start();
error_reporting(0); 
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'Access Denied']);
    exit();
}

include 'db.php';

// รับข้อมูล JSON
$input = json_decode(file_get_contents('php://input'), true);
$dataItems = [];

// ตรวจสอบว่าเป็นข้อมูลเดี่ยวหรืออาเรย์
if (isset($input['std_id'])) {
    $dataItems[] = $input; // แปลงเดี่ยวเป็นอาเรย์
} elseif (is_array($input)) {
    $dataItems = $input; // เป็นอาเรย์อยู่แล้ว
}

if (empty($dataItems)) {
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => 'No Data']);
    exit();
}

$conn->begin_transaction();
$success_count = 0;

try {
    // เตรียม Statement ครั้งเดียว ใช้ซ้ำได้ (ประหยัดทรัพยากรมาก)
    $stmt_check = $conn->prepare("SELECT score_id FROM tb_score WHERE std_id = ? AND work_id = ?");
    $stmt_del = $conn->prepare("DELETE FROM tb_score WHERE std_id = ? AND work_id = ?");
    $stmt_upd = $conn->prepare("UPDATE tb_score SET score_point = ? WHERE std_id = ? AND work_id = ?");
    $stmt_ins = $conn->prepare("INSERT INTO tb_score (std_id, work_id, score_point) VALUES (?, ?, ?)");

    foreach ($dataItems as $item) {
        $std_id = $item['std_id'];
        $work_id = $item['work_id'];
        $score = trim($item['score']);

        // เช็คว่ามีคะแนนเดิมไหม
        $stmt_check->bind_param("ii", $std_id, $work_id);
        $stmt_check->execute();
        $exists = $stmt_check->get_result()->num_rows > 0;

        if ($score === "") {
            if ($exists) {
                $stmt_del->bind_param("ii", $std_id, $work_id);
                $stmt_del->execute();
            }
        } elseif ($exists) {
            $stmt_upd->bind_param("sii", $score, $std_id, $work_id);
            $stmt_upd->execute();
        } else {
            $stmt_ins->bind_param("iis", $std_id, $work_id, $score);
            $stmt_ins->execute();
        }
        $success_count++;
    }

    $conn->commit();
    ob_end_clean();
    echo json_encode(['status' => 'success', 'count' => $success_count]);

} catch (Exception $e) {
    $conn->rollback();
    ob_end_clean();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>