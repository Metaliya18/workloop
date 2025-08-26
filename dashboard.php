<?php
session_start();
require '../config/db.php';

// Check admin authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Get admin's proper name
$adminName = 'Admin';
if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
    $adminName = $_SESSION['username'];
}

// Try to get full name from database if available
try {
    $conn = new PDO("mysql:host=localhost;dbname=ems", 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT full_name FROM employees WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['full_name'])) {
            $adminName = $result['full_name'];
        }
    }
} catch(PDOException $e) {
    // Keep default name if database error
}

// Database queries for dashboard statistics
$dashboardData = [];

try {
    $conn = new PDO("mysql:host=localhost;dbname=ems", 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $total_emp_query = $conn->query("SELECT COUNT(*) as count FROM employees");
    $total_emp_result = $total_emp_query->fetch(PDO::FETCH_ASSOC);
    $dashboardData['total_employees'] = $total_emp_result['count'] ?? 0;
    
    $active_emp_query = $conn->query("SELECT COUNT(*) as count FROM employees e INNER JOIN users u ON e.user_id = u.id WHERE u.is_approved = 1 AND u.role = 'employee'");
    $active_emp_result = $active_emp_query->fetch(PDO::FETCH_ASSOC);
    $dashboardData['approved_employees'] = $active_emp_result['count'] ?? 0;
    
    $today = date('Y-m-d');
    $present_today = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today' AND status = 'Present'")->fetch(PDO::FETCH_ASSOC);
    $total_today = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE date = '$today'")->fetch(PDO::FETCH_ASSOC);
    $dashboardData['present_today'] = $present_today['count'] ?? 0;
    $dashboardData['total_today'] = $total_today['count'] ?? 0;
    $dashboardData['attendance_rate'] = $dashboardData['total_today'] > 0 ? round(($dashboardData['present_today'] / $dashboardData['total_today']) * 100) : 0;
    
    $pending_leaves = $conn->query("SELECT COUNT(*) as count FROM leave_requests WHERE status = 'Pending'")->fetch(PDO::FETCH_ASSOC);
    $dashboardData['pending_leaves'] = $pending_leaves['count'] ?? 0;
    
    $active_departments = $conn->query("SELECT COUNT(DISTINCT department) as count FROM employees WHERE department IS NOT NULL AND department != ''")->fetch(PDO::FETCH_ASSOC);
    $dashboardData['active_departments'] = $active_departments['count'] ?? 0;
    
    // SHOW ONLY 3 RECENT LEAVE REQUESTS (as per your request)
    $recent_leaves = $conn->query("SELECT lr.*, e.full_name as employee_name FROM leave_requests lr LEFT JOIN employees e ON lr.employee_id = e.id ORDER BY lr.created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    $dashboardData['recent_leaves'] = $recent_leaves;
    
    $dept_stats = $conn->query("SELECT department, COUNT(*) as count FROM employees WHERE department IS NOT NULL AND department != '' GROUP BY department")->fetchAll(PDO::FETCH_ASSOC);
    $dashboardData['department_stats'] = $dept_stats;
    
    $pending_approval = $conn->query("SELECT COUNT(*) as count FROM employees e INNER JOIN users u ON e.user_id = u.id WHERE u.is_approved = 0 AND u.role = 'employee'");
    $pending_result = $pending_approval->fetch(PDO::FETCH_ASSOC);
    $dashboardData['pending_employees'] = $pending_result['count'] ?? 0;
    
} catch(PDOException $e) {
    error_log("Dashboard Database Error: " . $e->getMessage());
    $dashboardData = [
        'total_employees' => 0,
        'present_today' => 0,
        'total_today' => 0,
        'attendance_rate' => 0,
        'pending_leaves' => 0,
        'active_departments' => 0,
        'recent_leaves' => [],
        'department_stats' => [],
        'approved_employees' => 0,
        'pending_employees' => 0
    ];
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode($dashboardData);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Employee Management System</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<style>
body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    margin: 0;
    color: white;
}

/* NAVBAR: EMS BRAND ON THE EXTREME LEFT, NO GAP */
.navbar {
    background: rgba(255, 255, 255, 0.1) !important;
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    padding: 15px 0;
}

.navbar-brand {
    color: white !important;
    font-weight: 500;
    margin-left: 0;
    padding-left: 0;
}

.navbar-nav {
    margin-left: auto;
}

.nav-link {
    color: white !important;
    font-weight: 500;
    transition: all 0.3s ease;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    transform: translateY(-2px);
}

.container-main {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 30px;
    margin: 20px auto;
    color: white;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.glass-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    color: white;
    transition: all 0.3s ease;
}

.glass-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
}

.stat-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(15px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s ease;
    height: 160px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    align-items: center;
    color: white;
    position: relative;
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
}

.stat-icon {
    font-size: 1.6rem;
    color: #ff7f50;
    margin: 0;
    position: absolute;
    top: 5px;
    left: 50%;
    transform: translateX(-50%);
}

.stat-number {
    font-size: 2.8rem;
    font-weight: 700;
    color: white;
    margin: 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
}

.stat-label {
    color: white;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    text-align: center;
    position: absolute;
    bottom: 15px;
    left: 0;
    right: 0;
    margin: 0;
    padding: 0 10px;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.quick-action {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    text-decoration: none;
    color: white;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.quick-action:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-5px);
    color: white;
    text-decoration: none;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
}

.activity-item {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
    transition: all 0.3s ease;
}

.activity-item:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(5px);
}

.loading-spinner {
    width: 20px;
    height: 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-left: 2px solid #4ade80;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    display: inline-block;
    margin-left: 10px;
}

.update-indicator {
    position: fixed;
    top: 80px;
    right: 20px;
    background: rgba(40, 167, 69, 0.9);
    color: white;
    padding: 10px 15px;
    border-radius: 8px;
    font-size: 14px;
    z-index: 1000;
    opacity: 0;
    transform: translateY(-20px);
    transition: all 0.3s ease;
}

.update-indicator.show {
    opacity: 1;
    transform: translateY(0);
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.badge {
    font-size: 0.75rem;
    padding: 4px 8px;
}

@media (max-width: 768px) {
    .container-main {
        padding: 20px;
        margin: 10px;
    }
    
    .stat-card {
        height: 140px;
        padding: 15px;
    }
    
    .stat-number {
        font-size: 2.2rem;
    }
    
    .stat-label {
        font-size: 12px;
        bottom: 10px;
    }
    
    .stat-icon {
        font-size: 1.3rem;
        top: 4px;
    }
}
</style>
</head>
<body>

<!-- NAVIGATION - EMS BRAND ON THE EXTREME LEFT -->
<nav class="navbar navbar-expand-lg">
    <div class="container-fluid px-2"> <!-- Adjusted for no left gap -->
        <a class="navbar-brand" href="../index.php">
            <i class="fas fa-users"></i> EMS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="employees.php">
                        <i class="fas fa-users"></i> Employees
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="employees_add.php">
                        <i class="fas fa-user-plus"></i> Add Employee
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="attendance.php">
                        <i class="fas fa-calendar-check"></i> Attendance
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="leaves.php">
                        <i class="fas fa-plane"></i> Leaves
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="assign_project.php">
                        <i class="fas fa-tasks"></i> Assign Project
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="project_status.php">
                        <i class="fas fa-clipboard-check"></i>  Project Status
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="up_pass.php">
                        <i class="fas fa-key"></i>  Up Pass
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="payroll.php">
                        <i class="fas fa-money-bill-wave"></i> Payroll
                    </a>
                </li>
                <li class="nav-item">
                    <span class="nav-link">Welcome, <?= htmlspecialchars($adminName) ?></span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Update Indicator -->
<div class="update-indicator" id="updateIndicator">
<i class="fas fa-sync-alt me-2"></i>Dashboard updated
</div>

<div class="container mt-4">
<div class="container-main">

<!-- Dashboard Header -->
<div class="glass-card">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="mb-2">
                <i class="fas fa-chart-line me-3"></i>
                Welcome back, <?= htmlspecialchars($adminName) ?>!
            </h1>
            <p class="mb-0" style="color: rgba(255, 255, 255, 0.8);">
                Here's what's happening with your business today • <span id="currentDateTime"><?php echo date('l, F j, Y'); ?></span>
                <span class="loading-spinner" id="loadingIndicator" style="display: none;"></span>
            </p>
        </div>
        <div class="col-md-4 text-end">
            <div class="d-flex gap-3 justify-content-end align-items-center">
                <div class="text-center">
                    <div style="font-size: 2rem; color: #4ade80;">
                        <i class="fas fa-database"></i>
                    </div>
                    <small style="color: rgba(255, 255, 255, 0.6);">Live Data</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Main Statistics -->
<div class="row g-4 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-number" id="totalEmployees"><?php echo $dashboardData['total_employees']; ?></div>
            <div class="stat-label">Total Employees</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-number" id="approvedEmployees"><?php echo $dashboardData['approved_employees']; ?></div>
            <div class="stat-label">Active Employees</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-number" id="attendanceRate"><?php echo $dashboardData['attendance_rate']; ?>%</div>
            <div class="stat-label">Attendance Rate</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-number" id="activeDepartments"><?php echo $dashboardData['active_departments']; ?></div>
            <div class="stat-label">Active Departments</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="glass-card mb-4">
    <h3 class="text-white mb-4">
        <i class="fas fa-bolt text-warning me-2"></i>
        Quick Actions
    </h3>
    <div class="quick-actions">
        <a href="employees_add.php" class="quick-action" style="background: linear-gradient(135deg, #28a745, #20c997);">
            <div style="font-size: 2.5rem; margin-bottom: 15px; color: white;">
                <i class="fas fa-user-plus"></i>
            </div>
            <div style="font-size: 1.1rem; font-weight: 600;">Add Employee</div>
            <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.7);">Create new employee account</div>
        </a>
        <a href="employees.php" class="quick-action">
            <div style="font-size: 2.5rem; margin-bottom: 15px; color: #4ade80;">
                <i class="fas fa-users"></i>
            </div>
            <div style="font-size: 1.1rem; font-weight: 600;">View All Employees</div>
            <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.7);">Manage employee records</div>
        </a>
        <a href="attendance.php" class="quick-action">
            <div style="font-size: 2.5rem; margin-bottom: 15px; color: #4ade80;">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div style="font-size: 1.1rem; font-weight: 600;">Attendance</div>
            <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.7);">Track daily attendance</div>
        </a>
        <a href="leaves.php" class="quick-action">
            <div style="font-size: 2.5rem; margin-bottom: 15px; color: #4ade80;">
                <i class="fas fa-plane"></i>
            </div>
            <div style="font-size: 1.1rem; font-weight: 600;">Manage Leaves</div>
            <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.7);">Approve leave requests</div>
        </a>
        <a href="payroll.php" class="quick-action">
            <div style="font-size: 2.5rem; margin-bottom: 15px; color: #4ade80;">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div style="font-size: 1.1rem; font-weight: 600;">Generate Payroll</div>
            <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.7);">Process salary payments</div>
        </a>
    </div>
</div>

<!-- Content Row -->
<div class="row g-4">
    <!-- Recent Activity -->
    <div class="col-lg-8">
        <div class="glass-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="text-white">
                    <i class="fas fa-history text-info me-2"></i>
                    Recent Leave Requests
                </h3>
                <a href="leaves.php" class="btn btn-sm" style="background: linear-gradient(135deg, #4ade80, #22c55e); color: white; border: none; border-radius: 25px;">
                    View All
                </a>
            </div>
            <div id="recentLeaves">
                <?php if (empty($dashboardData['recent_leaves'])): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-4x mb-3" style="color: rgba(255, 255, 255, 0.3);"></i>
                        <h5 class="text-white mb-2">No Recent Activity</h5>
                        <p style="color: rgba(255, 255, 255, 0.6);">Leave requests will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach($dashboardData['recent_leaves'] as $leave): ?>
                        <div class="activity-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($leave['employee_name'] ?? 'Unknown Employee'); ?></strong>
                                    <span style="color: rgba(255, 255, 255, 0.7);"> requested </span>
                                    <strong><?php echo htmlspecialchars($leave['leave_type']); ?></strong>
                                </div>
                                <div>
                                    <?php
                                    $badge_class = 'warning';
                                    if ($leave['status'] == 'Approved') $badge_class = 'success';
                                    if ($leave['status'] == 'Rejected') $badge_class = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?>"><?php echo $leave['status']; ?></span>
                                </div>
                            </div>
                            <small style="color: rgba(255, 255, 255, 0.5);">
                                <?php echo date('M d, Y', strtotime($leave['created_at'])); ?> •
                                <?php echo date('M d', strtotime($leave['start_date'])); ?> to <?php echo date('M d', strtotime($leave['end_date'])); ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                    <!-- "View More" button after recent leaves -->
                    <div class="text-center mt-3">
                        <a href="leaves.php" class="btn btn-outline-light btn-sm">View More <i class="fas fa-chevron-right ms-1"></i></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Department Analytics -->
    <div class="col-lg-4">
        <div class="glass-card h-100">
            <h3 class="text-white mb-4">
                <i class="fas fa-chart-pie text-success me-2"></i>
                Department Overview
            </h3>
            <div id="departmentStats">
                <?php if (empty($dashboardData['department_stats'])): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-building fa-3x mb-3" style="color: rgba(255, 255, 255, 0.3);"></i>
                        <p style="color: rgba(255, 255, 255, 0.6);">No department data available</p>
                    </div>
                <?php else: ?>
                    <?php foreach($dashboardData['department_stats'] as $dept): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 p-2" style="background: rgba(255, 255, 255, 0.05); border-radius: 8px;">
                            <span><?php echo htmlspecialchars($dept['department']); ?></span>
                            <strong><?php echo $dept['count']; ?> employees</strong>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</div> <!-- Close container-main -->
</div> <!-- Close container -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
// Enhanced dashboard JavaScript
let updateInterval;
let isUpdating = false;

// Function to update dashboard data
async function updateDashboardData() {
    if (isUpdating) return;
    isUpdating = true;
    const loadingIndicator = document.getElementById('loadingIndicator');
    const updateIndicator = document.getElementById('updateIndicator');
    
    try {
        loadingIndicator.style.display = 'inline-block';
        const response = await fetch('dashboard.php?ajax=1', {
            method: 'GET',
            headers: { 'Content-Type': 'application/json', }
        });
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        updateElement('totalEmployees', data.total_employees);
        updateElement('approvedEmployees', data.approved_employees);
        updateElement('attendanceRate', data.attendance_rate + '%');
        updateElement('activeDepartments', data.active_departments);
        document.getElementById('currentDateTime').textContent = new Date().toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
        });
        updateIndicator.classList.add('show');
        setTimeout(() => { updateIndicator.classList.remove('show'); }, 2000);
        console.log('Dashboard updated successfully');
    } catch (error) {
        console.error('Error updating dashboard:', error);
    } finally {
        loadingIndicator.style.display = 'none';
        isUpdating = false;
    }
}

// Helper function to update element with animation
function updateElement(id, newValue) {
    const element = document.getElementById(id);
    if (element && element.textContent !== newValue.toString()) {
        element.style.transform = 'scale(1.1)';
        element.style.color = '#4ade80';
        element.textContent = newValue;
        setTimeout(() => {
            element.style.transform = 'scale(1)';
            element.style.color = '';
        }, 300);
    }
}

// Initialize real-time updates
function startRealTimeUpdates() {
    updateInterval = setInterval(updateDashboardData, 30000);
    console.log('Real-time updates started (30 second interval)');
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    startRealTimeUpdates();
    document.querySelectorAll('.stat-card').forEach(card => {
        card.addEventListener('mouseenter', () => {
            card.style.transform = 'translateY(-8px) scale(1.02)';
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = 'translateY(0) scale(1)';
        });
    });
    document.querySelectorAll('.quick-action').forEach(action => {
        action.addEventListener('mouseenter', () => {
            action.style.transform = 'translateY(-5px) scale(1.02)';
        });
        action.addEventListener('mouseleave', () => {
            action.style.transform = 'translateY(0) scale(1)';
        });
    });
});

// Stop updates when page is hidden
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        clearInterval(updateInterval);
    } else {
        startRealTimeUpdates();
        updateDashboardData();
    }
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    clearInterval(updateInterval);
});
</script>
</body>
</html>
