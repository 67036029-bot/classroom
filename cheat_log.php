<?php
include 'db.php';

if (isset($_POST['exam_id']) && isset($_POST['event_type'])) {
    $exam_id = (int)$_POST['exam_id']; // แปลงเป็นตัวเลขทันทีเพื่อความชัวร์
    $std_id = (int)$_POST['std_id'];
    $event_type = $_POST['event_type'];

    // 1. บันทึก Log ด้วย Prepared Statement
    $stmt = $conn->prepare("INSERT INTO tb_cheat_events (exam_id, std_id, event_type) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $exam_id, $std_id, $event_type);
    $stmt->execute();
    $stmt->close();
    
    // 2. อัปเดต Cheat Count (ใช้ Prepared Statement เช่นกันเพื่อความปลอดภัยสูงสุด)
    $stmt_update = $conn->prepare("UPDATE tb_exam_results SET cheat_count = cheat_count + 1 WHERE exam_id = ? AND std_id = ?");
    $stmt_update->bind_param("ii", $exam_id, $std_id);
    $stmt_update->execute();
    $stmt_update->close();

    echo "success";
} else {
    http_response_code(400);
    echo "Invalid Parameters";
}
?>