<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

// ลบข้อสอบ
if (isset($_GET['del_q_id'])) {
    $qid = $_GET['del_q_id'];
    $old = $conn->query("SELECT image_path, audio_path FROM tb_exam_questions WHERE question_id = $qid")->fetch_assoc();
    if($old['image_path']) @unlink("uploads/" . $old['image_path']);
    if($old['audio_path']) @unlink("uploads/" . $old['audio_path']);
    $conn->query("DELETE FROM tb_exam_questions WHERE question_id = $qid");
    echo "<script>alert('ลบเรียบร้อย'); window.location='exam_bank.php';</script>";
}

// Filter Logic
$search_exam = isset($_GET['exam']) ? $_GET['exam'] : '';
$search_subj = isset($_GET['subj']) ? $_GET['subj'] : '';

$where_clause = "WHERE 1=1";
if ($search_exam) $where_clause .= " AND e.exam_name LIKE '%$search_exam%'";
if ($search_subj != 'all' && $search_subj != '') $where_clause .= " AND c.subject_code = '$search_subj'";

$sql_groups = "SELECT c.subject_code, e.exam_name, e.exam_id, COUNT(q.question_id) as qty 
               FROM tb_exam_questions q
               JOIN tb_exam_sets e ON q.exam_id = e.exam_id
               JOIN tb_work w ON e.work_id = w.work_id
               JOIN tb_course_info c ON w.course_id = c.id
               $where_clause
               GROUP BY c.subject_code, e.exam_name, e.exam_id
               ORDER BY c.subject_code ASC, e.exam_name ASC";
$res_groups = $conn->query($sql_groups);

$res_subjects = $conn->query("SELECT DISTINCT subject_code FROM tb_course_info ORDER BY subject_code ASC");
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>คลังข้อสอบ (Real-time)</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 25px; }
        
        .text-pink { color: #d63384 !important; }
        .bg-pink { background-color: #d63384 !important; }
        
        .bank-card {
            background: white; border: 1px solid #eee; border-radius: 15px;
            padding: 20px; transition: all 0.2s; position: relative; overflow: hidden;
            border-left: 5px solid #d63384; box-shadow: 0 2px 8px rgba(0,0,0,0.02);
            cursor: pointer;
        }
        .bank-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(214, 51, 132, 0.15); border-color: #d63384; }
        
        .bank-icon {
            position: absolute; right: 10px; bottom: 10px; font-size: 3rem; 
            color: #f8f9fa; transform: rotate(-15deg); z-index: 0;
        }
        .card-content { position: relative; z-index: 1; }
        
        .filter-bar { background: white; padding: 15px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 25px; }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-server text-pink"></i> คลังข้อสอบรวม</h3>
                <small class="text-muted">ดึงข้อมูลอัตโนมัติจากระบบสร้างข้อสอบ</small>
            </div>

            <div class="filter-bar d-flex gap-2 flex-wrap align-items-center">
                <span class="fw-bold text-secondary small me-2"><i class="fa-solid fa-filter"></i> กรองข้อมูล:</span>
                <form method="GET" class="d-flex gap-2">
                    <select name="subj" class="form-select form-select-sm border-secondary fw-bold" onchange="this.form.submit()" style="width: 150px;">
                        <option value="all">ทุกรายวิชา</option>
                        <?php while($s = $res_subjects->fetch_assoc()): ?>
                            <option value="<?php echo $s['subject_code']; ?>" <?php if($search_subj==$s['subject_code']) echo 'selected'; ?>>
                                <?php echo $s['subject_code']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    
                    <input type="text" name="exam" class="form-control form-control-sm border-secondary" placeholder="ค้นหาชื่อชุดข้อสอบ..." value="<?php echo htmlspecialchars($search_exam); ?>" style="width: 200px;">
                    <button type="submit" class="btn btn-dark btn-sm"><i class="fa-solid fa-search"></i></button>

                    <?php if($search_subj != '' || $search_exam != ''): ?>
                        <a href="exam_bank.php" class="btn btn-outline-danger btn-sm text-nowrap"><i class="fa-solid fa-rotate-left"></i> ล้าง</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="row g-3">
                <?php if($res_groups->num_rows > 0): ?>
                    <?php while($g = $res_groups->fetch_assoc()): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="bank-card" onclick="openBankModal('<?php echo $g['exam_id']; ?>', '<?php echo htmlspecialchars($g['exam_name']); ?>')">
                                <i class="fa-solid fa-folder-open bank-icon"></i>
                                <div class="card-content">
                                    <span class="badge bg-dark mb-2 shadow-sm"><?php echo $g['subject_code']; ?></span>
                                    <h5 class="fw-bold text-dark mb-1 text-truncate" title="<?php echo $g['exam_name']; ?>">
                                        <?php echo $g['exam_name']; ?>
                                    </h5>
                                    <div class="text-pink small fw-bold">
                                        <i class="fa-solid fa-layer-group"></i> <?php echo $g['qty']; ?> ข้อ
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12 text-center py-5 text-muted opacity-50">
                        <i class="fa-regular fa-folder-open display-1 mb-3"></i>
                        <h4>ยังไม่มีข้อสอบในระบบ</h4>
                        <p>สร้างข้อสอบในเมนู "จัดการงาน" แล้วข้อสอบจะมาปรากฏที่นี่เองครับ</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="bankModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="fa-solid fa-list-ul text-pink"></i> <span id="modalTitle">รายการข้อสอบ</span></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light" id="modalContent">
                    <div class="text-center py-5"><div class="spinner-border text-pink"></div></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openBankModal(examId, examName) {
            document.getElementById('modalTitle').innerText = examName;
            var myModal = new bootstrap.Modal(document.getElementById('bankModal'));
            myModal.show();
            
            var contentDiv = document.getElementById('modalContent');
            contentDiv.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-pink"></div><p class="mt-2 text-muted">กำลังโหลดข้อสอบ...</p></div>';
            
            var xhr = new XMLHttpRequest();
            xhr.open("POST", "get_bank_details.php", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onreadystatechange = function() {
                if (this.readyState === 4 && this.status === 200) {
                    contentDiv.innerHTML = this.responseText;
                }
            };
            xhr.send("exam_id=" + examId);
        }
    </script>
</body>
</html>