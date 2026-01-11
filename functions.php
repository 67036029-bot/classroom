<?php
// ไฟล์ functions.php รวมสูตรคำนวณไว้ที่เดียว

// 1. ฟังก์ชันตัดเกรด (0-4) ตามเกณฑ์โรงเรียน
function calculateGrade($total_score) {
    // แปลงค่าเป็นตัวเลข (ป้องกันกรณีเป็นข้อความ)
    $score = floatval($total_score);

    // (ทางเลือก) ถ้าต้องการให้ปัดเศษทศนิยมก่อนตัดเกรด (เช่น 79.5 เป็น 80) ให้เอาเครื่องหมาย // หน้าบรรทัดด้านล่างออก
    // $score = round($score); 

    // ตรวจสอบช่วงคะแนน (ไล่จากมากไปน้อย)
    if ($score >= 80) {
        return "4";     // 80-100
    } elseif ($score >= 75) {
        return "3.5";   // 75-79
    } elseif ($score >= 70) {
        return "3";     // 70-74
    } elseif ($score >= 65) {
        return "2.5";   // 65-69
    } elseif ($score >= 60) {
        return "2";     // 60-64
    } elseif ($score >= 55) {
        return "1.5";   // 55-59
    } elseif ($score >= 50) {
        return "1";     // 50-54
    } else {
        return "0";     // 0-49
    }
}

// 2. ฟังก์ชันประเมินสมรรถนะ (1-3)
function calculateCompetency($total_score) {
    if (!is_numeric($total_score)) $total_score = 0;
    if ($total_score >= 70) return "3"; // ดีเยี่ยม
    elseif ($total_score >= 60) return "2"; // ผ่านเกณฑ์
    else return "1"; // ปรับปรุง
}

// 3. ฟังก์ชันแปลงวันที่เป็นภาษาไทย
function thaiDate($date_time) {
    if($date_time == "") return "-";
    $strDate = date("j/n/Y", strtotime($date_time));
    $year = date("Y", strtotime($date_time)) + 543;
    return date("j/n", strtotime($date_time)) . "/" . substr($year, 2, 2);
}
?>