<?php
session_start();
// ตรวจสอบสิทธิ์ครู
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    http_response_code(403);
    echo "Access Denied";
    exit();
}
include 'db.php';

if (isset($_POST['std_id'])) {
    $id = intval($_POST['std_id']);
    
    // รีเซ็ตรหัสผ่านกลับเป็น '12345'
    $default_pass = '12345'; 
    // หมายเหตุ: ในอนาคตถ้าต้องการเข้ารหัส (Hash) ให้ใช้ password_hash($default_pass, PASSWORD_DEFAULT)
    // แต่เพื่อความง่ายในเฟสแรก เราจะเก็บเป็นข้อความธรรมดาก่อนตามที่คุณต้องการ
    
    $stmt = $conn->prepare("UPDATE tb_students SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $default_pass, $id);
    
    if ($stmt->execute()) {
        echo "success";
    } else {
        echo "error";
    }
    $stmt->close();
}
?>