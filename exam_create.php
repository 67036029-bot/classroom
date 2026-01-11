<?php
session_start();
// Debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

$work_id = isset($_GET['work_id']) ? $_GET['work_id'] : 0;
if ($work_id == 0) exit("Invalid Work ID");

$work = $conn->query("SELECT * FROM tb_work WHERE work_id = $work_id")->fetch_assoc();

// 1. ตรวจสอบ/สร้างชุดข้อสอบ
$sql_check = "SELECT * FROM tb_exam_sets WHERE work_id = $work_id";
$res_check = $conn->query($sql_check);
if ($res_check->num_rows == 0) {
    $new_pin = strval(rand(1000, 9999));
    $exam_name = "แบบทดสอบ: " . $conn->real_escape_string($work['work_name']);
    $conn->query("INSERT INTO tb_exam_sets (work_id, exam_name, access_pin) VALUES ('$work_id', '$exam_name', '$new_pin')");
    $exam = $conn->query($sql_check)->fetch_assoc();
} else {
    $exam = $res_check->fetch_assoc();
}
$exam_id = $exam['exam_id'];

// --- LOGIC 1: บันทึกการตั้งค่า ---
if (isset($_POST['save_settings'])) {
    $time = $_POST['time_limit'];
    $active = (int)$_POST['is_active']; 
    $manual_pin = $conn->real_escape_string($_POST['access_pin']);
    
    $conn->query("UPDATE tb_exam_sets SET time_limit = '$time', is_active = '$active', access_pin = '$manual_pin' WHERE exam_id = $exam_id");
    echo "<script>alert('✅ บันทึกการตั้งค่าเรียบร้อย'); window.location='exam_create.php?work_id=$work_id';</script>";
}

// --- LOGIC 2: บันทึกข้อสอบใหม่ (Bulk) ---
if (isset($_POST['save_bulk'])) {
    if (isset($_POST['q']) && is_array($_POST['q'])) {
        $count = 0;
        foreach ($_POST['q'] as $idx => $item) {
            // เช็คค่าว่าง (ตัด tag html ออกก่อนเช็ค เพื่อดูว่ามีตัวหนังสือจริงๆ ไหม)
            if (trim(strip_tags($item['text'])) == "" && trim($item['text']) == "") continue;

            $q_type = $item['type'];
            $q_text = $conn->real_escape_string($item['text']); // เก็บ HTML ทั้งก้อน
            $points = floatval($item['points']);
            
            $ca = isset($item['choice_a']) ? $conn->real_escape_string($item['choice_a']) : "";
            $cb = isset($item['choice_b']) ? $conn->real_escape_string($item['choice_b']) : "";
            $cc = isset($item['choice_c']) ? $conn->real_escape_string($item['choice_c']) : "";
            $cd = isset($item['choice_d']) ? $conn->real_escape_string($item['choice_d']) : "";
            
            $ans = "";
            if ($q_type == 1) $ans = $item['correct_mcq'];
            elseif ($q_type == 2) $ans = $conn->real_escape_string($item['correct_text']);
            elseif ($q_type == 3) $ans = $item['correct_tf'];

            $img_sql = "NULL";
            if (!empty($_FILES['q']['name'][$idx]['image'])) {
                $ext = pathinfo($_FILES['q']['name'][$idx]['image'], PATHINFO_EXTENSION);
                $new_name = "img_" . time() . "_{$idx}_" . rand(100,999) . "." . $ext;
                if (move_uploaded_file($_FILES['q']['tmp_name'][$idx]['image'], "uploads/" . $new_name)) $img_sql = "'$new_name'";
            }
            $aud_sql = "NULL";
            if (!empty($_FILES['q']['name'][$idx]['audio'])) {
                $ext = pathinfo($_FILES['q']['name'][$idx]['audio'], PATHINFO_EXTENSION);
                $new_name = "aud_" . time() . "_{$idx}_" . rand(100,999) . "." . $ext;
                if (move_uploaded_file($_FILES['q']['tmp_name'][$idx]['audio'], "uploads/" . $new_name)) $aud_sql = "'$new_name'";
            }

            $sql = "INSERT INTO tb_exam_questions (exam_id, question_type, question_text, image_path, audio_path, choice_a, choice_b, choice_c, choice_d, correct_answer, points) 
                    VALUES ('$exam_id', '$q_type', '$q_text', $img_sql, $aud_sql, '$ca', '$cb', '$cc', '$cd', '$ans', '$points')";
            if($conn->query($sql)) $count++;
        }
        echo "<script>alert('✅ เพิ่มข้อสอบใหม่เรียบร้อย $count ข้อ'); window.location='exam_create.php?work_id=$work_id';</script>";
    }
}

// --- LOGIC 3: แก้ไขข้อสอบเดิม ---
if (isset($_POST['update_single'])) {
    $qid = $_POST['edit_qid'];
    $q_type = $_POST['question_type'];
    $q_text = $conn->real_escape_string($_POST['question']); // เก็บ HTML
    $points = floatval($_POST['points']);
    
    $ca = ($q_type == 1) ? $conn->real_escape_string($_POST['choice_a']) : "";
    $cb = ($q_type == 1) ? $conn->real_escape_string($_POST['choice_b']) : "";
    $cc = ($q_type == 1) ? $conn->real_escape_string($_POST['choice_c']) : "";
    $cd = ($q_type == 1) ? $conn->real_escape_string($_POST['choice_d']) : "";
    
    if ($q_type == 1) $ans = $_POST['correct_mcq'];
    elseif ($q_type == 2) $ans = $conn->real_escape_string($_POST['correct_text']);
    else $ans = $_POST['correct_tf'];

    $old = $conn->query("SELECT image_path, audio_path FROM tb_exam_questions WHERE question_id = $qid")->fetch_assoc();
    $img_up = ""; $aud_up = "";

    if(!empty($_FILES['image_file']['name'])){ 
        if($old['image_path']) @unlink("uploads/".$old['image_path']);
        $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
        $new = "img_" . time() . "_" . rand(100,999) . "." . $ext;
        move_uploaded_file($_FILES['image_file']['tmp_name'], "uploads/" . $new);
        $img_up = ", image_path = '$new'";
    }
    if(!empty($_FILES['audio_file']['name'])){ 
        if($old['audio_path']) @unlink("uploads/".$old['audio_path']);
        $ext = pathinfo($_FILES['audio_file']['name'], PATHINFO_EXTENSION);
        $new = "aud_" . time() . "_" . rand(100,999) . "." . $ext;
        move_uploaded_file($_FILES['audio_file']['tmp_name'], "uploads/" . $new);
        $aud_up = ", audio_path = '$new'";
    }

    $sql = "UPDATE tb_exam_questions SET question_type='$q_type', question_text='$q_text', points='$points', 
            choice_a='$ca', choice_b='$cb', choice_c='$cc', choice_d='$cd', correct_answer='$ans' 
            $img_up $aud_up WHERE question_id='$qid'";
    
    $conn->query($sql);
    echo "<script>alert('✅ แก้ไขเรียบร้อย'); window.location='exam_create.php?work_id=$work_id';</script>";
}

// --- LOGIC 4: ลบข้อสอบ ---
if (isset($_GET['del_q'])) {
    $qid = $_GET['del_q'];
    $old = $conn->query("SELECT image_path, audio_path FROM tb_exam_questions WHERE question_id = $qid")->fetch_assoc();
    if($old['image_path']) @unlink("uploads/".$old['image_path']);
    if($old['audio_path']) @unlink("uploads/".$old['audio_path']);
    $conn->query("DELETE FROM tb_exam_questions WHERE question_id = $qid");
    echo "<script>window.location='exam_create.php?work_id=$work_id';</script>";
}

// โหมดแก้ไข
$edit_mode = false; 
$edit_data = null;
if (isset($_GET['edit_q'])) {
    $edit_qid = $_GET['edit_q'];
    $res_edit = $conn->query("SELECT * FROM tb_exam_questions WHERE question_id = $edit_qid");
    if ($res_edit->num_rows > 0) { $edit_mode = true; $edit_data = $res_edit->fetch_assoc(); }
}

$existing_q = $conn->query("SELECT * FROM tb_exam_questions WHERE exam_id = '$exam_id' ORDER BY question_id ASC");
$total_score = $conn->query("SELECT SUM(points) as total FROM tb_exam_questions WHERE exam_id = '$exam_id'")->fetch_assoc()['total'] ?? 0;
$total_score = floatval($total_score);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อสอบ</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">

    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f0f2f5; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 25px; width: 100%; overflow-x: hidden; }
        .row { margin-right: 0; margin-left: 0; }
        .col-12, .col-md-2, .col-md-3, .col-md-7 { padding-right: 12px; padding-left: 12px; }

        .settings-card { background: #212529; color: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 25px; border-left: 5px solid #ff007f; }
        .form-control-dark { background: #333; border: 1px solid #444; color: white; text-align: center; font-weight: bold; }
        .form-control-dark:focus { background: #444; border-color: #ff007f; color: white; }
        .btn-save-settings { width: 100%; background: #ff007f; border: none; color: white; font-weight: bold; padding: 10px; border-radius: 8px; transition: 0.3s; }
        .btn-save-settings:hover { background: #d6006b; }

        .q-card-exist { border: 1px solid #e9ecef; border-radius: 12px; margin-bottom: 12px; overflow: hidden; background: white; box-shadow: 0 2px 5px rgba(0,0,0,0.02); }
        .accordion-button { font-weight: bold; color: #333; background: white; }
        .accordion-button:not(.collapsed) { background-color: #fff0f6; color: #ff007f; box-shadow: none; }
        .q-badge { background: #212529; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; margin-right: 12px; flex-shrink: 0; }

        .choice-display-box { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 8px; padding: 10px 15px; display: flex; align-items: center; transition: 0.2s; }
        .choice-display-box.correct { border: 2px solid #198754; background-color: #f0fff4; color: #0f5132; }
        .choice-label { font-weight: bold; margin-right: 10px; width: 25px; text-align: center; }

        .q-card-new { background: #fff; border: 2px dashed #ff007f; border-radius: 12px; padding: 25px; margin-bottom: 25px; position: relative; }
        .btn-add-more { width: 100%; padding: 15px; border: 2px dashed #bbb; border-radius: 12px; color: #666; font-weight: bold; background: transparent; transition: 0.2s; }
        .btn-add-more:hover { border-color: #ff007f; color: #ff007f; background: #fff0f6; }
        
        .save-bar-float { position: fixed; bottom: 20px; right: 30px; z-index: 999; }
        .choice-input { border-radius: 0 6px 6px 0; border: 1px solid #ddd; border-left: none; }
        .choice-prefix { width: 40px; background: #f8f9fa; border: 1px solid #ddd; display: flex; align-items: center; justify-content: center; font-weight: bold; border-radius: 6px 0 0 6px; }

        /* ปรับแต่ง Summernote ให้ดูดีขึ้น */
        .note-editor.note-frame { border-radius: 8px; border-color: #dee2e6; }
        .note-toolbar { border-radius: 8px 8px 0 0; background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; }
        .note-statusbar { display: none; } /* ซ่อนแถบลากขยายด้านล่างให้ดูคลีน */
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>
        
        <div class="content-area">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-layer-group text-danger me-2"></i> จัดการข้อสอบ</h3>
                    <small class="text-muted fw-bold"><?php echo $work['work_name']; ?></small>
                </div>
                <a href="manage_work.php" class="btn btn-outline-dark rounded-pill px-4 fw-bold">กลับหน้ารวม</a>
            </div>

            <div class="row g-4">
                <div class="col-12">
                    <form method="POST" class="settings-card row g-3 align-items-end">
                        <div class="col-md-2">
                            <label class="text-white-50 small mb-1">สถานะระบบ</label>
                            <select name="is_active" class="form-select form-control-dark">
                                <option value="1" <?php if($exam['is_active']==1) echo 'selected'; ?>>🟢 เปิดสอบ</option>
                                <option value="0" <?php if($exam['is_active']==0) echo 'selected'; ?>>🔴 ปิดสอบ</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="text-white-50 small mb-1">เวลา (นาที)</label>
                            <input type="number" name="time_limit" class="form-control form-control-dark" value="<?php echo $exam['time_limit']; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="text-white-50 small mb-1">PIN เข้าสอบ</label>
                            <input type="text" name="access_pin" class="form-control form-control-dark text-warning" value="<?php echo $exam['access_pin']; ?>" maxlength="4">
                        </div>
                        <div class="col-md-3">
                            <label class="text-white-50 small mb-1">จำนวนข้อสอบ / คะแนนรวม</label>
                            <div class="form-control form-control-dark bg-secondary border-0">
                                <?php echo $existing_q->num_rows; ?> ข้อ / <?php echo $total_score; ?> คะแนน
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" name="save_settings" class="btn-save-settings">
                                <i class="fa-solid fa-floppy-disk me-2"></i> บันทึกตั้งค่า
                            </button>
                        </div>
                    </form>
                </div>

                <div class="col-12">
                    
                    <?php if($edit_mode): ?>
                        <div class="card border-warning shadow mb-4">
                            <div class="card-header bg-warning text-dark fw-bold">
                                <i class="fa-solid fa-pen-to-square"></i> กำลังแก้ไขข้อสอบ
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="edit_qid" value="<?php echo $edit_data['question_id']; ?>">
                                    <div class="row g-2 mb-3">
                                        <div class="col-md-3">
                                            <select name="question_type" id="edit_type" class="form-select fw-bold" onchange="toggleEditChoice()">
                                                <option value="1" <?php if($edit_data['question_type']==1) echo 'selected'; ?>>ปรนัย (4 ตัวเลือก)</option>
                                                <option value="2" <?php if($edit_data['question_type']==2) echo 'selected'; ?>>เติมคำ (อัตนัย)</option>
                                                <option value="3" <?php if($edit_data['question_type']==3) echo 'selected'; ?>>ถูก/ผิด (True/False)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="input-group">
                                                <span class="input-group-text">คะแนน</span>
                                                <input type="number" step="0.01" name="points" class="form-control text-center fw-bold" value="<?php echo floatval($edit_data['points']); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <label class="small fw-bold text-muted mb-1">โจทย์คำถาม:</label>
                                    <textarea name="question" class="form-control mb-3 summernote-editor" required><?php echo $edit_data['question_text']; ?></textarea>

                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label class="small fw-bold text-muted">รูปประกอบ (ถ้ามี)</label>
                                            <input type="file" name="image_file" class="form-control form-control-sm" accept="image/*">
                                        </div>
                                        <div class="col-6">
                                            <label class="small fw-bold text-muted">เสียงประกอบ (ถ้ามี)</label>
                                            <input type="file" name="audio_file" class="form-control form-control-sm" accept="audio/*">
                                        </div>
                                    </div>
                                    
                                    <div id="edit_choice_area" class="mb-3">
                                        <div class="row g-2 mb-2">
                                            <div class="col-6"><input type="text" name="choice_a" class="form-control" placeholder="A" value="<?php echo $edit_data['choice_a']; ?>"></div>
                                            <div class="col-6"><input type="text" name="choice_b" class="form-control" placeholder="B" value="<?php echo $edit_data['choice_b']; ?>"></div>
                                            <div class="col-6"><input type="text" name="choice_c" class="form-control" placeholder="C" value="<?php echo $edit_data['choice_c']; ?>"></div>
                                            <div class="col-6"><input type="text" name="choice_d" class="form-control" placeholder="D" value="<?php echo $edit_data['choice_d']; ?>"></div>
                                        </div>
                                        <label class="small fw-bold">เฉลย:</label>
                                        <select name="correct_mcq" class="form-select w-auto d-inline-block">
                                            <option value="a" <?php if($edit_data['correct_answer']=='a') echo 'selected'; ?>>A</option>
                                            <option value="b" <?php if($edit_data['correct_answer']=='b') echo 'selected'; ?>>B</option>
                                            <option value="c" <?php if($edit_data['correct_answer']=='c') echo 'selected'; ?>>C</option>
                                            <option value="d" <?php if($edit_data['correct_answer']=='d') echo 'selected'; ?>>D</option>
                                        </select>
                                    </div>
                                    <div id="edit_text_area" class="mb-3" style="display:none;"><input type="text" name="correct_text" class="form-control" value="<?php echo $edit_data['correct_answer']; ?>"></div>
                                    <div id="edit_tf_area" class="mb-3" style="display:none;">
                                        <div class="btn-group"><input type="radio" class="btn-check" name="correct_tf" id="etf1" value="True" <?php if($edit_data['correct_answer']=='True') echo 'checked'; ?>><label class="btn btn-outline-success" for="etf1">True</label><input type="radio" class="btn-check" name="correct_tf" id="etf2" value="False" <?php if($edit_data['correct_answer']=='False') echo 'checked'; ?>><label class="btn btn-outline-danger" for="etf2">False</label></div>
                                    </div>

                                    <div class="d-flex gap-2 mt-4">
                                        <button type="submit" name="update_single" class="btn btn-success fw-bold">บันทึก</button>
                                        <a href="exam_create.php?work_id=<?php echo $work_id; ?>" class="btn btn-secondary">ยกเลิก</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <script>
                            function toggleEditChoice() {
                                let t = document.getElementById('edit_type').value;
                                document.getElementById('edit_choice_area').style.display = (t==1)?'block':'none';
                                document.getElementById('edit_text_area').style.display = (t==2)?'block':'none';
                                document.getElementById('edit_tf_area').style.display = (t==3)?'block':'none';
                            }
                            window.onload = toggleEditChoice;
                        </script>

                    <?php else: ?>

                        <h5 class="fw-bold mb-3"><i class="fa-solid fa-list-ol"></i> ข้อสอบที่บันทึกแล้ว</h5>
                        <div class="accordion mb-5" id="accordionQ">
                            <?php 
                            if($existing_q->num_rows > 0): 
                                $i = 1;
                                while($row = $existing_q->fetch_assoc()):
                                    // Strip tags for summary title (to show clean text)
                                    $summary_text = strip_tags($row['question_text']);
                            ?>
                            <div class="accordion-item q-card-exist">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#col<?php echo $row['question_id']; ?>">
                                        <div class="d-flex w-100 align-items-center">
                                            <span class="q-badge"><?php echo $i++; ?></span>
                                            <span class="text-truncate me-3 fw-bold" style="max-width: 70%;">
                                                <?php echo htmlspecialchars(mb_strimwidth($summary_text, 0, 80, "...")); ?>
                                            </span>
                                            <span class="badge bg-light text-dark border ms-auto me-2"><?php echo floatval($row['points']); ?> คะแนน</span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="col<?php echo $row['question_id']; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionQ">
                                    <div class="accordion-body bg-white pt-4">
                                        
                                        <div class="d-flex justify-content-end gap-2 mb-3">
                                            <a href="exam_create.php?work_id=<?php echo $work_id; ?>&edit_q=<?php echo $row['question_id']; ?>" class="btn btn-warning btn-sm fw-bold px-3 shadow-sm"><i class="fa-solid fa-pen me-1"></i> แก้ไข</a>
                                            <a href="exam_create.php?work_id=<?php echo $work_id; ?>&del_q=<?php echo $row['question_id']; ?>" class="btn btn-danger btn-sm fw-bold px-3 shadow-sm" onclick="return confirm('ยืนยันลบข้อนี้?');"><i class="fa-solid fa-trash me-1"></i> ลบ</a>
                                        </div>

                                        <div class="mb-4 lh-base text-dark" style="font-size: 1.1rem;">
                                            <?php echo $row['question_text']; ?>
                                        </div>

                                        <?php if($row['image_path']): ?>
                                            <div class="mb-4"><img src="uploads/<?php echo $row['image_path']; ?>" class="img-fluid rounded border shadow-sm" style="max-height: 200px;"></div>
                                        <?php endif; ?>

                                        <?php if($row['question_type'] == 1): ?>
                                            <div class="row g-3">
                                                <?php $choices = ['a', 'b', 'c', 'd']; foreach($choices as $ch): 
                                                    $is_correct = ($row['correct_answer'] == $ch);
                                                    $bg_class = $is_correct ? 'correct' : ''; ?>
                                                    <div class="col-md-6">
                                                        <div class="choice-display-box <?php echo $bg_class; ?>">
                                                            <span class="choice-label"><?php echo strtoupper($ch); ?></span>
                                                            <span class="flex-grow-1"><?php echo $row['choice_'.$ch]; ?></span>
                                                            <?php if($is_correct): ?><i class="fa-solid fa-circle-check text-success fs-5 ms-2"></i><?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php elseif($row['question_type'] == 2): ?>
                                            <div class="alert alert-success d-inline-block px-4 fw-bold"><i class="fa-solid fa-pen mb-1 me-2"></i> เฉลย: "<?php echo $row['correct_answer']; ?>"</div>
                                        <?php else: ?>
                                            <div class="d-flex gap-3">
                                                <button class="btn <?php echo ($row['correct_answer']=='True')?'btn-success':'btn-outline-secondary'; ?> fw-bold px-4" disabled>TRUE</button>
                                                <button class="btn <?php echo ($row['correct_answer']=='False')?'btn-danger':'btn-outline-secondary'; ?> fw-bold px-4" disabled>FALSE</button>
                                            </div>
                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                            <?php endwhile; else: ?>
                                <div class="text-center py-5 text-muted border rounded bg-white">
                                    <i class="fa-regular fa-folder-open fs-1 mb-2 opacity-25"></i><br>ยังไม่มีข้อสอบ
                                </div>
                            <?php endif; ?>
                        </div>

                        <form method="POST" enctype="multipart/form-data" id="bulkForm">
                            <h5 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-circle-plus"></i> เพิ่มข้อสอบใหม่</h5>
                            
                            <div id="new_q_container"></div>

                            <button type="button" class="btn-add-more mb-5" onclick="addNewCard()">
                                <i class="fa-solid fa-plus-circle fa-lg"></i> กดเพื่อเพิ่มข้อสอบอีก 1 ข้อ
                            </button>

                            <div class="save-bar-float">
                                <button type="submit" name="save_bulk" class="btn btn-dark fw-bold rounded-pill px-4 py-2 shadow-lg" onclick="return confirm('บันทึกข้อสอบใหม่ทั้งหมด?');">
                                    <i class="fa-solid fa-floppy-disk me-2"></i> บันทึกข้อสอบใหม่ (<span id="count_new">0</span>)
                                </button>
                            </div>
                        </form>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <template id="q_template">
        <div class="q-card-new">
            <button type="button" class="btn-close float-end" onclick="removeCard(this)"></button>
            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="badge bg-danger rounded-pill">New</span>
                <span class="fw-bold text-danger">ข้อที่ <span class="q-idx">1</span></span>
            </div>
            
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <select name="q[IDX][type]" class="form-select form-select-sm fw-bold border-danger q-type" onchange="changeType(this)">
                        <option value="1">ปรนัย (4 ตัวเลือก)</option>
                        <option value="2">เติมคำ (อัตนัย)</option>
                        <option value="3">ถูก/ผิด (True/False)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" step="0.01" name="q[IDX][points]" class="form-control form-control-sm text-center fw-bold" value="1">
                </div>
                <div class="col-md-7">
                    <div class="input-group input-group-sm">
                        <input type="file" name="q[IDX][image]" class="form-control" accept="image/*">
                        <input type="file" name="q[IDX][audio]" class="form-control" accept="audio/*">
                    </div>
                </div>
            </div>
            
            <label class="small fw-bold text-muted mb-1">โจทย์คำถาม:</label>
            <textarea name="q[IDX][text]" class="form-control mb-3 summernote-new" rows="2" placeholder="พิมพ์โจทย์..." required></textarea>
            
            <div class="choice-area bg-light p-3 rounded">
                <div class="row g-2 mb-2">
                    <div class="col-6"><div class="input-group input-group-sm"><span class="choice-prefix">A</span><input type="text" name="q[IDX][choice_a]" class="form-control choice-input"></div></div>
                    <div class="col-6"><div class="input-group input-group-sm"><span class="choice-prefix">B</span><input type="text" name="q[IDX][choice_b]" class="form-control choice-input"></div></div>
                    <div class="col-6"><div class="input-group input-group-sm"><span class="choice-prefix">C</span><input type="text" name="q[IDX][choice_c]" class="form-control choice-input"></div></div>
                    <div class="col-6"><div class="input-group input-group-sm"><span class="choice-prefix">D</span><input type="text" name="q[IDX][choice_d]" class="form-control choice-input"></div></div>
                </div>
                <select name="q[IDX][correct_mcq]" class="form-select form-select-sm text-success fw-bold w-auto"><option value="a">เฉลย A</option><option value="b">เฉลย B</option><option value="c">เฉลย C</option><option value="d">เฉลย D</option></select>
            </div>
            
            <div class="text-area bg-light p-3 rounded" style="display:none;">
                <input type="text" name="q[IDX][correct_text]" class="form-control form-control-sm border-success" placeholder="เฉลยคำตอบ">
            </div>
            <div class="tf-area bg-light p-3 rounded text-center" style="display:none;">
                <div class="btn-group btn-group-sm">
                    <input type="radio" class="btn-check" name="q[IDX][correct_tf]" id="tf_t_IDX" value="True" checked><label class="btn btn-outline-success" for="tf_t_IDX">True</label>
                    <input type="radio" class="btn-check" name="q[IDX][correct_tf]" id="tf_f_IDX" value="False"><label class="btn btn-outline-danger" for="tf_f_IDX">False</label>
                </div>
            </div>
        </div>
    </template>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>

    <script>
        // Config for Editor
        const summernoteConfig = {
            placeholder: 'พิมพ์โจทย์ที่นี่...',
            tabsize: 2,
            height: 120,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'italic', 'clear']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['color', ['color']],
            ]
        };

        // Initialize Editor for Edit Mode
        $(document).ready(function() {
            $('.summernote-editor').summernote(summernoteConfig);
        });

        let nextNum = <?php echo $existing_q->num_rows + 1; ?>;
        let addedCount = 0;

        function addNewCard() {
            addedCount++;
            const container = document.getElementById('new_q_container');
            let html = document.getElementById('q_template').innerHTML;
            let uniqueIdx = Date.now() + Math.floor(Math.random() * 1000); 
            html = html.replace(/IDX/g, uniqueIdx);
            
            // Create wrapper
            const div = document.createElement('div');
            div.innerHTML = html;
            
            let currentNum = nextNum + (document.querySelectorAll('.q-card-new').length);
            div.querySelector('.q-idx').innerText = currentNum;
            container.appendChild(div);
            updateSaveBar();
            
            // *** Initialize Summernote for the NEW textarea ***
            $(div).find('.summernote-new').summernote(summernoteConfig);

            window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
        }

        function removeCard(btn) {
            btn.closest('.q-card-new').remove();
            addedCount--;
            updateSaveBar();
            let cards = document.querySelectorAll('.q-card-new .q-idx');
            cards.forEach((span, index) => { span.innerText = nextNum + index; });
        }

        function changeType(select) {
            const card = select.closest('.q-card-new');
            const type = select.value;
            card.querySelector('.choice-area').style.display = (type==1)?'block':'none';
            card.querySelector('.text-area').style.display = (type==2)?'block':'none';
            card.querySelector('.tf-area').style.display = (type==3)?'block':'none';
        }

        function updateSaveBar() { document.getElementById('count_new').innerText = document.querySelectorAll('.q-card-new').length; }

        <?php if($existing_q->num_rows == 0): ?>
        window.onload = addNewCard;
        <?php endif; ?>
    </script>
</body>
</html>