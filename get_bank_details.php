<?php
include 'db.php';

// รับค่า exam_id ที่ส่งมาจาก exam_bank.php
if (isset($_POST['exam_id'])) {
    $exam_id = $conn->real_escape_string($_POST['exam_id']);
    
    // 1. ดึงข้อสอบจากตารางจริง (tb_exam_questions)
    $sql = "SELECT * FROM tb_exam_questions 
            WHERE exam_id = '$exam_id' 
            ORDER BY question_id ASC";
    $result = $conn->query($sql);
    ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold text-secondary mb-0">
            <i class="fa-solid fa-layer-group"></i> รายการข้อสอบในชุดนี้
        </h6>
        <span class="badge bg-pink text-white"><?php echo $result->num_rows; ?> ข้อ</span>
    </div>

    <?php if ($result->num_rows > 0): ?>
        <div class="list-group list-group-flush border rounded">
            <?php $i=1; while($q = $result->fetch_assoc()): ?>
                <div class="list-group-item p-3">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="me-3 w-100">
                            <div class="mb-2">
                                <span class="badge bg-secondary rounded-pill me-1">ข้อที่ <?php echo $i++; ?></span>
                                
                                <?php if($q['question_type']==2): ?>
                                    <span class="badge bg-primary">เติมคำ</span>
                                <?php elseif($q['question_type']==3): ?>
                                    <span class="badge bg-success">True/False</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark">ปรนัย</span>
                                <?php endif; ?>

                                <span class="badge bg-light text-muted border">Pts: <?php echo $q['points']; ?></span>
                            </div>

                            <?php if($q['image_path']): ?>
                                <div class="mb-2">
                                    <img src="uploads/<?php echo $q['image_path']; ?>" class="img-fluid rounded border shadow-sm" style="max-height: 150px;">
                                </div>
                            <?php endif; ?>

                            <?php if($q['audio_path']): ?>
                                <div class="mb-2">
                                    <audio controls class="w-100" style="height:35px;">
                                        <source src="uploads/<?php echo $q['audio_path']; ?>" type="audio/mpeg">
                                    </audio>
                                </div>
                            <?php endif; ?>

                            <div class="fw-bold text-dark mb-2" style="font-size: 1.05rem;">
                                <?php echo nl2br($q['question_text']); ?>
                            </div>
                            
                            <div class="small text-muted bg-light p-2 rounded border border-light">
                                <i class="fa-solid fa-key text-success"></i> เฉลย: 
                                <?php 
                                if($q['question_type'] == 1) {
                                    // ปรนัย: แสดงตัวเลือกที่ถูก
                                    $key = strtolower(trim($q['correct_answer']));
                                    // ป้องกัน Error กรณีเฉลยไม่มีใน choice
                                    $ans_text = isset($q['choice_'.$key]) ? $q['choice_'.$key] : "-"; 
                                    echo "<strong class='text-dark'>" . strtoupper($key) . ". " . $ans_text . "</strong>";
                                } else {
                                    // อัตนัย / TF
                                    echo "<strong class='text-success'>{$q['correct_answer']}</strong>";
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div class="flex-shrink-0 ms-2">
                            <a href="exam_bank.php?del_q_id=<?php echo $q['question_id']; ?>" 
                               class="btn btn-outline-danger btn-sm border-0 rounded-circle"
                               style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center;"
                               onclick="return confirm('⚠️ คำเตือน: การลบที่นี่คือการลบข้อสอบจริงออกจากชุดข้อสอบ\nและจะหายไปจากหน้าสอบของนักเรียนทันที\n\nยืนยันหรือไม่?');">
                                <i class="fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center border-0 bg-warning bg-opacity-10 text-warning">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> ไม่พบข้อสอบในชุดนี้
        </div>
    <?php endif; ?>

    <?php
}
?>