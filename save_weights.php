<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // รับค่าโหมดการคำนวณ (1=Weight, 0=Direct)
    $calc_mode = isset($_POST['calc_mode']) ? intval($_POST['calc_mode']) : 1;

    // รับค่าน้ำหนัก
    $w1 = intval($_POST['w_k1']);
    $w2 = intval($_POST['w_mid']);
    $w3 = intval($_POST['w_k2']);
    $w4 = intval($_POST['w_final']);
    
    $sum = $w1 + $w2 + $w3 + $w4;
    
    // ถ้าเป็นโหมด Weight ต้องรวมได้ 100 เท่านั้น (ถ้า Direct ไม่บังคับ แต่เช็คไว้ก็ดี)
    if ($calc_mode == 1 && $sum != 100) {
        echo "<script>alert('❌ ผลรวมน้ำหนักต้องเท่ากับ 100%'); window.history.back();</script>";
        exit();
    }

    // อัปเดตข้อมูล (ใช้ LIMIT 1 เพราะมีวิชาเดียว)
    $sql = "UPDATE tb_course_info SET 
            calc_mode = '$calc_mode',
            weight_k1 = '$w1', 
            weight_mid = '$w2', 
            weight_k2 = '$w3', 
            weight_final = '$w4' 
            LIMIT 1"; // หรือใช้ WHERE id = ... ถ้ามีหลายวิชา

    if ($conn->query($sql)) {
        echo "<script>alert('✅ บันทึกการตั้งค่าเรียบร้อย'); window.location='report_grade.php';</script>";
    } else {
        echo "Error: " . $conn->error;
    }
}
?>