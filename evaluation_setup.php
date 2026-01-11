<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { header("Location: login.php"); exit(); }
include 'db.php';

// ฟังก์ชันแปลผล (คงเดิม)
function interpret($score) {
    if ($score >= 4.51) return ["ยอดเยี่ยม", "text-success"];
    if ($score >= 3.51) return ["ดีมาก", "text-primary"];
    if ($score >= 2.51) return ["ดี", "text-info"];
    if ($score >= 1.51) return ["พอใช้", "text-warning"];
    return ["ปรับปรุง", "text-danger"];
}

// 1. Actions (คงเดิม)
if (isset($_POST['toggle_status'])) {
    $status = isset($_POST['is_active']) ? 1 : 0;
    $conn->query("UPDATE tb_eval_config SET is_active = '$status' WHERE id = 1");
    echo "<script>window.location='evaluation_setup.php';</script>";
}
if (isset($_POST['add_q'])) {
    $q_text = $conn->real_escape_string($_POST['q_text']);
    if(!empty($q_text)) $conn->query("INSERT INTO tb_eval_questions (q_text) VALUES ('$q_text')");
    echo "<script>window.location='evaluation_setup.php';</script>";
}
if (isset($_GET['del_q'])) {
    $qid = $_GET['del_q'];
    $conn->query("DELETE FROM tb_eval_questions WHERE q_id = '$qid'");
    $conn->query("DELETE FROM tb_eval_results WHERE q_id = '$qid'");
    echo "<script>window.location='evaluation_setup.php';</script>";
}

$config = $conn->query("SELECT * FROM tb_eval_config WHERE id = 1")->fetch_assoc();
$is_active = $config['is_active'];

// 2. Stats Calculation (Logic เดิมจากเวอร์ชันแรก)
$sql_stat = "SELECT q.q_id, q.q_text, COUNT(r.score) as n, AVG(r.score) as mean, STDDEV(r.score) as sd 
             FROM tb_eval_questions q LEFT JOIN tb_eval_results r ON q.q_id = r.q_id 
             GROUP BY q.q_id ORDER BY mean DESC"; // เรียงมากไปน้อย เพื่อหา Top 3
$res_stat = $conn->query($sql_stat);

$data = []; 
$total_sum_mean = 0; 
$total_items = 0; 
$total_respondents = 0;

while($row = $res_stat->fetch_assoc()) {
    $row['mean'] = $row['mean'] ?? 0;
    $row['sd'] = $row['sd'] ?? 0;
    $data[] = $row;
    if ($row['n'] > 0) {
        $total_sum_mean += $row['mean'];
        $total_items++;
        $total_respondents = max($total_respondents, $row['n']);
    }
}
$overall_mean = ($total_items > 0) ? ($total_sum_mean / $total_items) : 0;
$overall_result = interpret($overall_mean);

// ดึงข้อมูล Top 3 และ Bottom 1 (นำข้อมูลเดิมกลับมาแสดง)
$top3 = array_slice($data, 0, 3); 
$bottom = end($data);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ผลประเมินการสอน</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Sarabun', sans-serif; background-color: #f8f9fa; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 25px; }
        .text-pink { color: #d63384 !important; }
        
        /* --- New Card Design (Modern Gradient) --- */
        .stat-card {
            border: none; border-radius: 16px; padding: 20px;
            color: white; position: relative; overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: transform 0.2s; height: 100%;
        }
        .stat-card:hover { transform: translateY(-5px); }
        
        /* Icon Background Effect */
        .stat-icon-bg {
            position: absolute; right: -10px; bottom: -10px;
            font-size: 5rem; opacity: 0.15; transform: rotate(-15deg);
        }

        /* ธีมสีการ์ด */
        .card-mean { background: linear-gradient(135deg, #d63384 0%, #a61e61 100%); } /* ชมพูเข้ม */
        .card-users { background: linear-gradient(135deg, #212529 0%, #495057 100%); } /* ดำเทา */
        .card-top { background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); color: #333; border-left: 5px solid #198754; } /* ขาว-เขียว */
        .card-low { background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%); color: #333; border-left: 5px solid #dc3545; } /* ขาว-แดง */

        /* Rank Badge for Top 3 */
        .rank-badge { 
            width: 24px; height: 24px; border-radius: 50%; 
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 0.75rem; font-weight: bold; color: white; margin-right: 8px;
        }
        .bg-rank-1 { background-color: #ffc107; }
        .bg-rank-2 { background-color: #adb5bd; }
        .bg-rank-3 { background-color: #cd7f32; }

        /* Table */
        .table-custom thead th { background-color: #212529; color: white; border: none; padding: 15px; }
        .table-hover tbody tr:hover { background-color: #fff0f6; }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>

        <div class="content-area">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-chart-simple text-pink"></i> ผลประเมินการสอน</h3>
                    <small class="text-muted">วิเคราะห์ข้อมูลเชิงสถิติจากนักเรียน</small>
                </div>
                
                <form method="POST" class="d-flex align-items-center bg-white px-3 py-2 rounded shadow-sm border">
                    <input type="hidden" name="toggle_status" value="1">
                    <span class="fw-bold small me-2 <?php echo $is_active ? 'text-success' : 'text-muted'; ?>">
                        <?php echo $is_active ? 'สถานะ: เปิดรับ' : 'สถานะ: ปิดอยู่'; ?>
                    </span>
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" name="is_active" onchange="this.form.submit()" <?php if($is_active) echo "checked"; ?>>
                    </div>
                </form>
            </div>

            <div class="row g-4 mb-4">
                
                <div class="col-md-3">
                    <div class="stat-card card-mean">
                        <h6 class="opacity-75 mb-2">คะแนนเฉลี่ยรวม (Mean)</h6>
                        <div class="d-flex align-items-end">
                            <h1 class="fw-bold mb-0 display-4"><?php echo number_format($overall_mean, 2); ?></h1>
                            <span class="badge bg-white text-pink ms-2 mb-2"><?php echo $overall_result[0]; ?></span>
                        </div>
                        <i class="fa-solid fa-star stat-icon-bg"></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card card-users">
                        <h6 class="opacity-75 mb-2">ผู้ประเมินทั้งหมด</h6>
                        <div class="d-flex align-items-end">
                            <h1 class="fw-bold mb-0 display-4"><?php echo $total_respondents; ?></h1>
                            <small class="opacity-75 ms-2 mb-2">คน</small>
                        </div>
                        <i class="fa-solid fa-users stat-icon-bg"></i>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card card-top">
                        <h6 class="fw-bold text-success mb-3"><i class="fa-solid fa-thumbs-up"></i> จุดแข็ง (Top 3)</h6>
                        <?php if(!empty($data) && $top3[0]['n'] > 0): ?>
                            <?php foreach($top3 as $idx => $item): if($idx > 2) break; ?>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="rank-badge bg-rank-<?php echo $idx+1; ?>"><?php echo $idx+1; ?></span>
                                    <div class="text-truncate small fw-bold text-dark" style="max-width: 150px;">
                                        <?php echo $item['q_text']; ?>
                                    </div>
                                    <span class="ms-auto badge bg-light text-dark border"><?php echo number_format($item['mean'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted small py-3">ยังไม่มีข้อมูล</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="stat-card card-low">
                        <h6 class="fw-bold text-danger mb-3"><i class="fa-solid fa-triangle-exclamation"></i> สิ่งที่ควรพัฒนา</h6>
                        <?php if(!empty($data) && $bottom['n'] > 0): ?>
                            <div class="p-2 bg-danger bg-opacity-10 rounded border border-danger border-opacity-25">
                                <div class="text-dark fw-bold small text-truncate mb-1"><?php echo $bottom['q_text']; ?></div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-danger fw-bold display-6 mb-0"><?php echo number_format($bottom['mean'], 2); ?></span>
                                    <small class="text-muted">SD: <?php echo number_format($bottom['sd'], 2); ?></small>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted small py-3">ยังไม่มีข้อมูล</div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark">ตารางวิเคราะห์รายข้อ (เรียงจากมากไปน้อย)</h6>
                    <button class="btn btn-sm btn-outline-dark rounded-pill" type="button" data-bs-toggle="collapse" data-bs-target="#addQForm">
                        <i class="fa-solid fa-plus"></i> เพิ่มหัวข้อ
                    </button>
                </div>
                
                <div class="collapse bg-light p-3 border-bottom" id="addQForm">
                    <form method="POST" class="d-flex gap-2">
                        <input type="text" name="q_text" class="form-control" placeholder="พิมพ์หัวข้อประเมินใหม่..." required>
                        <button type="submit" name="add_q" class="btn btn-dark text-nowrap">บันทึก</button>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" width="50">#</th>
                                    <th>หัวข้อการประเมิน</th>
                                    <th class="text-center" width="100">Mean</th>
                                    <th class="text-center" width="100">S.D.</th>
                                    <th class="text-center" width="120">แปลผล</th>
                                    <th class="text-center" width="60">ลบ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (!empty($data)) {
                                    $i=1;
                                    foreach($data as $row): 
                                        $res = interpret($row['mean']);
                                ?>
                                <tr>
                                    <td class="text-center fw-bold text-muted"><?php echo $i++; ?></td>
                                    <td>
                                        <span class="text-dark fw-bold"><?php echo $row['q_text']; ?></span>
                                        <div class="small text-muted"><i class="fa-solid fa-user"></i> <?php echo $row['n']; ?> คน</div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border fs-6"><?php echo ($row['n']>0) ? number_format($row['mean'], 2) : '-'; ?></span>
                                    </td>
                                    <td class="text-center text-muted small"><?php echo ($row['n']>0) ? number_format($row['sd'], 2) : '-'; ?></td>
                                    <td class="text-center fw-bold small <?php echo $res[1]; ?>"><?php echo ($row['n']>0) ? $res[0] : '-'; ?></td>
                                    <td class="text-center">
                                        <a href="evaluation_setup.php?del_q=<?php echo $row['q_id']; ?>" class="text-danger opacity-50 hover-opacity-100" onclick="return confirm('ลบ?');"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endforeach;
                                } else {
                                    echo "<tr><td colspan='6' class='text-center py-5 text-muted'>ยังไม่มีหัวข้อประเมิน</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>