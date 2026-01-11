<?php
session_start();

// 1. SECURITY: ตรวจสอบสิทธิ์
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    die("Access Denied");
}

include 'db.php';

if (isset($_GET['id'])) {
    $id = (int) $_GET['id']; // Force int เพื่อความปลอดภัย

    // เริ่ม Transaction
    $conn->begin_transaction();

    try {
        // 1. ลบคะแนนของนักเรียนทุกคนในงานนี้
        $stmt1 = $conn->prepare("DELETE FROM tb_score WHERE work_id = ?");
        $stmt1->bind_param("i", $id);
        $stmt1->execute();
        $stmt1->close();

        // 2. ลบชุดข้อสอบที่ผูกกับงานนี้ (แต่ยังไม่ลบตัวข้อสอบย่อย เพื่อเก็บไว้ในคลัง)
        $stmt2 = $conn->prepare("DELETE FROM tb_exam_sets WHERE work_id = ?");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $stmt2->close();

        // 3. ลบตัวงานหลัก
        $stmtMain = $conn->prepare("DELETE FROM tb_work WHERE work_id = ?");
        $stmtMain->bind_param("i", $id);
        
        if ($stmtMain->execute()) {
            $stmtMain->close();
            $conn->commit(); // ยืนยันการลบ

            // แสดงผลสวยงามด้วย SweetAlert (คงฟีเจอร์เดิมของคุณไว้)
            echo "
            <!DOCTYPE html>
            <html>
            <head>
                <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
                <link href='https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600&display=swap' rel='stylesheet'>
                <style>body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; }</style>
            </head>
            <body>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'ลบเรียบร้อย!',
                            text: 'งานและข้อมูลคะแนนถูกลบออกจากระบบแล้ว',
                            showConfirmButton: false,
                            timer: 1500
                        }).then(() => {
                            window.location = 'manage_work.php';
                        });
                    });
                </script>
            </body>
            </html>";
        } else {
            throw new Exception("Execute failed");
        }

    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error: ไม่สามารถลบข้อมูลได้'); window.location='manage_work.php';</script>";
    }
} else {
    header("Location: manage_work.php");
}
?>