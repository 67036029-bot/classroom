<?php
include 'db.php';

// กำหนดรหัสผ่านใหม่ที่คุณต้องการใช้ (แก้ตรงนี้ได้)
$new_password = "1234"; 

// เข้ารหัสรหัสผ่าน
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// อัปเดตลงฐานข้อมูล (บังคับแก้ของครูคนแรกที่เจอ)
// หมายเหตุ: ตรวจสอบชื่อตารางและชื่อคอลัมน์ให้ตรงกับ SQL ของคุณ
// จากไฟล์ที่คุณส่งมา ตารางชื่อ tb_course_info และคอลัมน์ชื่อ teacher_password
$sql = "UPDATE tb_course_info SET teacher_password = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $hashed_password);

if ($stmt->execute()) {
    echo "<h1>✅ รีเซ็ตรหัสผ่านสำเร็จ!</h1>";
    echo "<h3>รหัสผ่านใหม่ของคุณคือ: " . $new_password . "</h3>";
    echo "<p>ตอนนี้ฐานข้อมูลเก็บรหัสแบบ Hash เรียบร้อยแล้ว: " . $hashed_password . "</p>";
    echo "<br><a href='login.php'>คลิกเพื่อไปหน้าล็อกอิน</a>";
} else {
    echo "<h1>❌ เกิดข้อผิดพลาด</h1>";
    echo "Error: " . $conn->error;
}
?>