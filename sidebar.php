<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* Custom Sidebar Styles - Fixed Layout */
    .sidebar-wrapper {
        width: 240px;
        height: 100vh; /* บังคับความสูงเท่าหน้าจอ */
        background: #1a1a1a; 
        border-right: 1px solid rgba(255,255,255,0.05);
        display: flex;
        flex-direction: column;
        transition: all 0.3s;
        z-index: 1000;
        font-family: 'Sarabun', sans-serif;
        
        /* ทำให้เมนูติดขอบจอ ไม่ไหลตาม content */
        position: sticky;
        top: 0;
        overflow-y: hidden; 
    }

    /* CSS สำหรับส่วนโลโก้ (ตาม Code ที่คุณให้มา) */
    .brand-section {
        padding: 15px 15px;
        background: linear-gradient(180deg, #212529 0%, #1a1a1a 100%);
        border-bottom: 1px solid rgba(255,255,255,0.05);
        display: flex;
        align-items: center;
        gap: 12px;
        text-decoration: none;
        flex-shrink: 0;
    }
    
    .brand-logo-circle {
        width: 40px; height: 40px;
        background: white;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid #d63384;
        box-shadow: 0 0 10px rgba(214, 51, 132, 0.3);
        flex-shrink: 0;
        overflow: hidden;
    }

    /* ส่วน Profile */
    .user-profile-mini {
        margin: 10px;
        padding: 10px;
        background: rgba(255,255,255,0.03);
        border-radius: 10px;
        border: 1px solid rgba(255,255,255,0.05);
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    .profile-icon {
        width: 32px; height: 32px;
        background: linear-gradient(135deg, #d63384 0%, #a61e61 100%);
        color: white;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem;
        box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    }

    /* เมนู (Scroll ได้เฉพาะตรงนี้) */
    .menu-group { 
        padding: 0 8px; 
        flex-grow: 1; 
        overflow-y: auto; /* Scrollbar */
        scrollbar-width: thin;
        scrollbar-color: #444 #1a1a1a;
    }
    .menu-group::-webkit-scrollbar { width: 4px; }
    .menu-group::-webkit-scrollbar-track { background: #1a1a1a; }
    .menu-group::-webkit-scrollbar-thumb { background-color: #444; border-radius: 4px; }

    .menu-label {
        font-size: 0.65rem;
        color: rgba(255,255,255,0.3);
        text-transform: uppercase;
        letter-spacing: 1px;
        margin: 12px 10px 4px 10px;
        font-weight: bold;
    }

    .nav-link-custom {
        color: #adb5bd !important;
        padding: 8px 12px;
        margin-bottom: 2px;
        border-radius: 8px;
        transition: all 0.2s ease;
        font-weight: 500;
        display: flex;
        align-items: center;
        text-decoration: none;
        border: 1px solid transparent;
        font-size: 0.85rem;
    }
    .nav-link-custom i {
        width: 22px; text-align: center; margin-right: 8px; font-size: 1rem;
        transition: 0.2s;
    }
    .nav-link-custom:hover {
        background: rgba(255, 255, 255, 0.05);
        color: white !important;
        transform: translateX(3px);
    }
    .nav-link-custom:hover i { color: #d63384; }

    .nav-link-custom.active {
        background: rgba(214, 51, 132, 0.15);
        color: #ff85c0 !important;
        border: 1px solid rgba(214, 51, 132, 0.3);
        font-weight: 700;
        box-shadow: 0 0 10px rgba(214, 51, 132, 0.1);
    }
    .nav-link-custom.active i { color: #ff85c0; }

    /* Footer (Fixed Bottom) */
    .sidebar-footer {
        padding: 15px;
        border-top: 1px solid rgba(255,255,255,0.05);
        text-align: center;
        flex-shrink: 0;
        background: #1a1a1a;
    }
    .btn-logout {
        background: rgba(220, 53, 69, 0.1);
        color: #ff6b6b;
        border: 1px solid rgba(220, 53, 69, 0.2);
        width: 100%;
        padding: 8px;
        border-radius: 8px;
        transition: 0.2s;
        text-decoration: none;
        display: block;
        font-weight: bold;
        font-size: 0.85rem;
    }
    .btn-logout:hover {
        background: #dc3545; color: white;
    }
</style>

<div class="sidebar-wrapper flex-shrink-0">
    
    <a href="index.php" class="brand-section">
        <div class="brand-logo-circle">
            <img src="images/logo.png" alt="Logo" style="width: 25px; height: 25px; object-fit: contain;" onerror="this.src='https://cdn-icons-png.flaticon.com/512/2995/2995620.png'">
        </div>
        <div style="line-height: 1.1;">
            <div class="text-white fw-bold" style="font-size: 0.85rem;">Classroom Management</div>
            <div style="color: #d63384; font-size: 0.7rem; font-weight: bold;">Bansuanjananusorn School</div>
        </div>
    </a>
    
    <div class="user-profile-mini">
        <div class="profile-icon">
            <i class="fa-solid fa-chalkboard-user"></i>
        </div>
        <div style="overflow: hidden;">
            <div class="text-white fw-bold text-truncate" style="font-size: 0.85rem;">
                <?php echo isset($TeacherName) ? $TeacherName : (isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Teacher'); ?>
            </div>
            <div class="text-white-50" style="font-size: 0.65rem;"> สถานะ: ออนไลน์</div>
        </div>
    </div>

    <div class="menu-group">
        <div class="menu-label">เมนูหลัก</div>
        
        <a href="index.php" class="nav-link-custom <?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-pie"></i> ภาพรวม
        </a>
        
        <a href="manage_work.php" class="nav-link-custom <?php echo ($current_page == 'manage_work.php' || $current_page == 'edit_work.php' || $current_page == 'exam_create.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-layer-group"></i> งาน & ข้อสอบ
        </a>

        <a href="exam_monitor.php" class="nav-link-custom <?php echo ($current_page == 'exam_monitor.php' || $current_page == 'admin_grading.php' || $current_page == 'exam_analysis.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-desktop"></i> ระบบคุมสอบ
        </a>

        <a href="record_score.php" class="nav-link-custom <?php echo ($current_page == 'record_score.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-list-check"></i> บันทึกคะแนน
        </a>

        <div class="menu-label">รายงาน</div>

        <a href="report_grade.php" class="nav-link-custom <?php echo ($current_page == 'report_grade.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-invoice"></i> สรุปผลการเรียน
        </a>
		
		<a href="report_overview.php" class="nav-link-custom <?php echo ($current_page == 'report_overview.php') ? 'active' : ''; ?>">
    <i class="fa-solid fa-chart-line"></i> สรุปผลสัมฤทธิ์ (KPI)
</a>

        <a href="competency.php" class="nav-link-custom <?php echo ($current_page == 'competency.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-award"></i> ประเมินสมรรถนะ
        </a>

        <a href="evaluation_setup.php" class="nav-link-custom <?php echo ($current_page == 'evaluation_setup.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-check-to-slot"></i> ประเมินการสอน
        </a>
        
        <a href="exam_bank.php" class="nav-link-custom <?php echo ($current_page == 'exam_bank.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-server"></i> คลังข้อสอบ
        </a>
		
		<a href="teacher_tools.php" class="nav-link-custom <?php echo ($current_page == 'teacher_tools.php') ? 'active' : ''; ?>">
    <i class="fa-solid fa-toolbox"></i> เครื่องมือครู
</a>

        <div class="menu-label">ตั้งค่า</div>

        <a href="list_students.php" class="nav-link-custom <?php echo ($current_page == 'list_students.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-user-group"></i> รายชื่อนักเรียน
        </a>

        <a href="settings.php" class="nav-link-custom <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-sliders"></i> ตั้งค่าระบบ
        </a>
    </div>
    
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout">
            <i class="fa-solid fa-power-off me-2"></i> ออกจากระบบ
        </a>
    </div>
</div>