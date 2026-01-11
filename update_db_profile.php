<?php
include 'db.php';
// เพิ่มคอลัมน์ email และ ชื่อไทย ถ้ายังไม่มี
$sql = "ALTER TABLE tb_course_info 
        ADD COLUMN teacher_email VARCHAR(100) NOT NULL DEFAULT 'admin@school.ac.th',
        ADD COLUMN teacher_fullname VARCHAR(200) NOT NULL DEFAULT 'คุณครู ใจดี';";

if ($conn->query($sql)) {
    echo "<h1>✅ อัปเกรดฐานข้อมูลสำเร็จ!</h1>";
    echo "<p>เพิ่มช่อง teacher_email และ teacher_fullname เรียบร้อยแล้ว</p>";
} else {
    echo "ฐานข้อมูลอาจจะอัปเดตไปแล้ว หรือเกิดข้อผิดพลาด: " . $conn->error;
}
?>