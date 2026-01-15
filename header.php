<?php
// file: header.php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตรวจสอบสิทธิ์ (ถ้าไม่ได้ Login ให้ดีดออก)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') { 
    header("Location: login.php"); 
    exit(); 
}

include_once 'db.php'; // ใช้ include_once ป้องกันการเรียกซ้ำ
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* CSS หลักของเว็บไซต์ */
        body { font-family: 'Sarabun', sans-serif; background-color: #f4f6f9; }
        .sidebar-container { display: flex; min-height: 100vh; }
        .content-area { flex-grow: 1; padding: 25px; }

        /* Hero Banner Style */
        .hero-banner {
            background: linear-gradient(90deg, #212529 0%, #1a1a1a 100%);
            color: white;
            border-radius: 12px;
            padding: 20px 25px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border-left: 6px solid #d63384;
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;
        }
        .hero-text h3 { font-size: 1.5rem; font-weight: bold; margin: 0; }
        .hero-text small { color: #adb5bd; font-size: 0.85rem; }
        .student-count-badge {
            background-color: rgba(214, 51, 132, 0.1); color: #ff85c0; border: 1px solid #d63384;
            padding: 8px 20px; border-radius: 50px; font-weight: bold; font-size: 0.9rem;
            display: flex; align-items: center; gap: 8px;
        }

        /* Table Style */
        .card-table { 
            border: none; border-radius: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); 
            overflow: hidden; height: calc(100vh - 130px); display: flex; flex-direction: column;
        }
        .card-header-custom {
            background: white; padding: 15px 20px; border-bottom: 1px solid #eee;
            display: flex; justify-content: space-between; align-items: center;
        }
        .table-responsive { flex-grow: 1; overflow-y: auto; }
        .table-custom thead th {
            background-color: #212529; color: white; padding: 12px 15px; font-weight: 500;
            border: none; position: sticky; top: 0; z-index: 10;
        }
        .table-custom tbody td {
            padding: 10px 15px; vertical-align: middle; border-bottom: 1px solid #f8f9fa;
            color: #495057; font-size: 0.95rem;
        }
        .table-hover tbody tr:hover { background-color: #fff0f6; cursor: pointer; }
        .table-hover tbody tr:hover td { color: #d63384; font-weight: 600; }
        
        .form-select-sm-custom {
            border-radius: 20px; border: 1px solid #dee2e6; padding-left: 15px;
            font-weight: bold; color: #333; background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include 'sidebar.php'; ?>
        <div class="content-area">