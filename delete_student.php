<?php
session_start();

// 1. SECURITY: ตรวจสอบสิทธิ์ (สำคัญมาก ห้ามลบ)
// ป้องกันไม่ให้คนนอก หรือนักเรียน แอบพิมพ์ URL เข้ามาลบข้อมูล
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    die("Access Denied: คุณไม่มีสิทธิ์เข้าถึงหน้านี้");
}

include 'db.php';

if (isset($_GET['id'])) {
    // แปลงค่า ID ให้เป็นตัวเลขเท่านั้น (ป้องกันการใส่ Code แปลกปลอม)
    $id = (int) $_GET['id'];

    // เริ่มกระบวนการ Transaction (ทำทั้งหมด หรือไม่ทำเลย)
    $conn->begin_transaction();

    try {
        // --- ขั้นตอนที่ 1: ลบข้อมูลที่เกี่ยวข้อง (ลูก) โดยใช้ Prepared Statement ---
        
        // 1.1 ลบคำตอบรายข้อ
        $stmt1 = $conn->prepare("DELETE FROM tb_exam_answer_log WHERE std_id = ?");
        $stmt1->bind_param("i", $id);
        $stmt1->execute();
        $stmt1->close();

        // 1.2 ลบประวัติการเข้าสอบ
        $stmt2 = $conn->prepare("DELETE FROM tb_exam_results WHERE std_id = ?");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $stmt2->close();

        // 1.3 ลบคะแนนจากสมุดพกหลัก
        $stmt3 = $conn->prepare("DELETE FROM tb_score WHERE std_id = ?");
        $stmt3->bind_param("i", $id);
        $stmt3->execute();
        $stmt3->close();
        
        // 1.4 ลบผลการประเมิน (ถ้ามี)
        $stmt4 = $conn->prepare("DELETE FROM tb_eval_results WHERE std_id = ?");
        $stmt4->bind_param("i", $id);
        $stmt4->execute();
        $stmt4->close();

        // --- ขั้นตอนที่ 2: ลบข้อมูลนักเรียน (แม่) ---
        $stmtMain = $conn->prepare("DELETE FROM tb_students WHERE id = ?");
        $stmtMain->bind_param("i", $id);
        $stmtMain->execute();
        $stmtMain->close();

        // ถ้าทุกอย่างผ่านฉลุย -> ยืนยันการลบ
        $conn->commit();

        echo "<script>
                alert('ลบข้อมูลนักเรียนและประวัติทั้งหมดเรียบร้อยแล้ว');
                window.location = 'list_students.php';
              </script>";

    } catch (Exception $e) {
        // ถ้ามีอะไรผิดพลาด -> ยกเลิกทั้งหมด (ข้อมูลจะไม่หาย)
        $conn->rollback();
        echo "<script>
                alert('เกิดข้อผิดพลาด: " . $e->getMessage() . "');
                window.location = 'list_students.php';
              </script>";
    }
} else {
    // กรณีไม่มี ID ส่งมา
    header("Location: list_students.php");
}
?>