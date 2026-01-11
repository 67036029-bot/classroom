<?php
include 'db.php';

// 1. ดึงรหัสผ่านปัจจุบันของครูมา (ซึ่งน่าจะเป็น 99999 หรือข้อความปกติ)
$sql = "SELECT id, teacher_password FROM tb_course_info LIMIT 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $current_pass = $row['teacher_password'];
    $id = $row['id'];

    // ตรวจสอบว่ามันถูก Hash ไปแล้วหรือยัง? (ถ้าความยาว 60 ตัวอักษรแสดงว่าน่าจะ Hash แล้ว)
    if (strlen($current_pass) < 60) {
        // ทำการเข้ารหัสรหัสผ่านเดิม
        $hashed_password = password_hash($current_pass, PASSWORD_DEFAULT);
        
        // อัปเดตกลับลงฐานข้อมูล
        $update = $conn->prepare("UPDATE tb_course_info SET teacher_password = ? WHERE id = ?");
        $update->bind_param("si", $hashed_password, $id);
        
        if ($update->execute()) {
            echo "<h1>✅ เข้ารหัสรหัสผ่านเรียบร้อยแล้ว!</h1>";
            echo "<p>รหัสผ่านเดิม: $current_pass</p>";
            echo "<p>รหัสผ่านใหม่ (ใน DB): $hashed_password</p>";
            echo "<p>ตอนนี้คุณสามารถใช้ไฟล์ login.php แบบใหม่ได้แล้ว</p>";
        } else {
            echo "Error updating record: " . $conn->error;
        }
    } else {
        echo "<h1>⚠️ รหัสผ่านน่าจะถูกเข้ารหัสอยู่แล้ว ไม่ต้องทำซ้ำ</h1>";
    }
} else {
    echo "ไม่พบข้อมูลครูในตาราง tb_course_info";
}
?>