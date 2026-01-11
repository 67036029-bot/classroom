<?php
// 1. ตั้งค่า Timezone ของ PHP ให้เป็นเวลาไทย (สำคัญมากสำหรับฟังก์ชัน date())
date_default_timezone_set('Asia/Bangkok');

// 2. ข้อมูลเชื่อมต่อฐานข้อมูล (ตามที่คุณระบุ)
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "grade_db";

// 3. สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// 4. ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 5. การตั้งค่าภาษาและเวลาของ MySQL
$conn->set_charset("utf8"); // รองรับภาษาไทย
$conn->query("SET time_zone = '+07:00'"); // บังคับให้ MySQL ใช้เวลาไทย (แก้ปัญหาเวลาสอบเพี้ยน)

// 6. ดึงข้อมูลโรงเรียน/วิชา (ส่วนนี้จำเป็นสำหรับแสดงหัวเว็บทุกหน้า)
// ใช้คำสั่ง LIMIT 1 เพื่อดึงข้อมูลแถวแรกสุดมาเสมอ
$sql_info = "SELECT * FROM tb_course_info ORDER BY id ASC LIMIT 1";
$result_info = $conn->query($sql_info);
$course_info = $result_info->fetch_assoc();

// ตรวจสอบว่ามีข้อมูลไหม? (ถ้าไม่มี ให้ใส่ค่าสมมติ เพื่อกันหน้าเว็บพัง)
if ($course_info) {
    $TeacherName = $course_info['teacher_name'];
    $SubjectName = $course_info['subject_name'];
    $SubjectCode = $course_info['subject_code'];
    $Semester    = $course_info['semester'];
    $AcadYear    = $course_info['year'];
    $SchoolName  = $course_info['school_name'];
} else {
    // กรณีฐานข้อมูลรายวิชายังว่างเปล่า (ค่า Default)
    $TeacherName = "ยังไม่ได้ตั้งค่า";
    $SubjectName = "ระบบบันทึกผลการเรียน";
    $SubjectCode = "---";
    $Semester    = "1";
    $AcadYear    = date("Y") + 543;
    $SchoolName  = "โรงเรียน...";
}
?>