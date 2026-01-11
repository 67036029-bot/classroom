<?php
include 'db.php';

echo "<h2>🔍 กำลังตรวจสอบระบบล็อกอิน...</h2>";

// 1. ตรวจสอบความยาวของคอลัมน์ในฐานข้อมูล
$table_check = $conn->query("SHOW COLUMNS FROM tb_course_info LIKE 'teacher_password'");
$column_info = $table_check->fetch_assoc();
$type = $column_info['Type']; // เช่น varchar(50) หรือ varchar(255)

echo "<li>ขนาดช่องเก็บรหัสผ่านปัจจุบัน: <strong>$type</strong> ";

// ดึงตัวเลขในวงเล็บออกมา
preg_match('/\d+/', $type, $matches);
$length = isset($matches[0]) ? intval($matches[0]) : 0;

if ($length < 60) {
    echo " <span style='color:red;'>❌ (สั้นเกินไป! ต้อง 60+ ถึงจะเก็บ Hash ได้)</span>";
    
    // --- สั่งแก้ให้อัตโนมัติ ---
    echo "<br>... กำลังขยายช่องเก็บข้อมูลเป็น 255 ตัวอักษร ...";
    $conn->query("ALTER TABLE tb_course_info MODIFY teacher_password VARCHAR(255)");
    echo " -> <span style='color:green;'>✅ แก้ไขสำเร็จ!</span>";
} else {
    echo " <span style='color:green;'>✅ (ขนาดถูกต้องแล้ว)</span>";
}
echo "</li><hr>";

// 2. ตรวจสอบข้อมูลปัจจุบันและรีเซ็ตรหัสผ่าน
echo "<li>กำลังรีเซ็ตรหัสผ่านเป็น <strong>1234</strong> ใหม่อีกครั้ง...</li>";

$new_pass = "1234";
$new_hash = password_hash($new_pass, PASSWORD_DEFAULT);

// อัปเดตลง DB
$conn->query("UPDATE tb_course_info SET teacher_password = '$new_hash' LIMIT 1");

// 3. ทดสอบดึงออกมาเช็คทันที
$sql = "SELECT teacher_password FROM tb_course_info LIMIT 1";
$result = $conn->query($sql);
$row = $result->fetch_assoc();
$stored_hash = $row['teacher_password'];

echo "<li>ข้อมูลในฐานข้อมูลตอนนี้: <code style='background:#eee;padding:2px;'>$stored_hash</code></li>";
echo "<li>ความยาวข้อมูล: " . strlen($stored_hash) . " ตัวอักษร (ต้อง 60)</li>";

if (password_verify("1234", $stored_hash)) {
    echo "<h1>🎉 สำเร็จ! ระบบยืนยันว่ารหัส '1234' ถูกต้องแล้ว</h1>";
    echo "<a href='login.php' style='font-size:20px; font-weight:bold;'>👉 คลิกที่นี่เพื่อไปหน้าล็อกอิน</a>";
} else {
    echo "<h1>❌ ยังล้มเหลว</h1>";
    echo "แปลว่า Database ตัดข้อมูลทิ้ง หรือการอัปเดตไม่สมบูรณ์";
}
?>