<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header('Location: ../login.php');
    exit;
}

require '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

try {
    // Get employee information
    $emp_stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ?");
    $emp_stmt->execute([$user_id]);
    $employee = $emp_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get attendance summary for current month
    $current_month = date('Y-m');
    $attendance_stmt = $pdo->prepare("SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
        FROM attendance 
        WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?");
    $attendance_stmt->execute([$user_id, $current_month]);
    $attendance = $attendance_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate attendance percentage
    $attendance_rate = $attendance['total_days'] > 0 ? 
        round(($attendance['present_days'] / $attendance['total_days']) * 100) : 0;
    
    // Get recent leave requests
    $leaves_stmt = $pdo->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY created_at DESC LIMIT 5");
    $leaves_stmt->execute([$user_id]);
    $recent_leaves = $leaves_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get pending leave count
    $pending_leaves_stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM leave_requests WHERE employee_id = ? AND status = 'Pending'");
    $pending_leaves_stmt->execute([$user_id]);
    $pending_leaves = $pending_leaves_stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    // Get today's attendance status
    $today = date('Y-m-d');
    $today_attendance_stmt = $pdo->prepare("SELECT status FROM attendance WHERE employee_id = ? AND date = ?");
    $today_attendance_stmt->execute([$user_id, $today]);
    $today_status = $today_attendance_stmt->fetch(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $attendance = ['total_days' => 0, 'present_days' => 0, 'absent_days' => 0];
    $recent_leaves = [];
    $pending_leaves = 0;
    $attendance_rate = 0;
    $today_status = null;
}

include '../includes/header.php';
?>

<style>
body {
    font-family: 'Poppins', sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    color: white;
}

.glass-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    border-radius: 20px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
    color: white;
    transition: all 0.3s ease;
}

.glass-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 35px 60px rgba(0, 0, 0, 0.15);
}

.stat-card {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 25px;
    text-align: center;
    height: 180px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    transition: all 0.3s ease;
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-8px);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 8px;
    background: linear-gradient(45deg, #fff, #e0e0e0);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.stat-label {
    font-size: 1rem;
    font-weight: 500;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 15px;
    background: linear-gradient(135deg, #ff6b6b, #ffa500);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
}

.list-group-item {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px !important;
    margin-bottom: 10px;
    color: white;
    font-weight: 500;
    transition: all 0.3s ease;
    padding: 20px;
}

.list-group-item:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateX(10px);
}

.badge {
    font-size: 0.8rem;
    padding: 6px 12px;
}

.status-present { background: rgba(40, 167, 69, 0.8); }
.status-absent { background: rgba(220, 53, 69, 0.8); }
.status-pending { background: rgba(255, 193, 7, 0.8); }
.status-approved { background: rgba(40, 167, 69, 0.8); }
.status-rejected { background: rgba(220, 53, 69, 0.8); }
</style>

Navigation
<nav class="navbar navbar-expand-lg" style="background: rgba(0, 0, 0, 0.1); backdrop-filter: blur(10px);">
<div class="container">
    <a class="navbar-brand text-white" href="dashboard.php">
        <i class="fas fa-user-circle me-2"></i>EMS Employee
    </a>
    <div class="navbar-nav ms-auto">
        <a class="nav-link text-white" href="dashboard.php">Dashboard</a>
        <a class="nav-link text-white" href="profile.php">Profile</a>
        <a class="nav-link text-white" href="attendance.php">Attendance</a>
        <a class="nav-link text-white" href="myproject.php">My Project</a>
        <a class="nav-link text-white" href="leave_request.php">Leave</a>
        <span class="nav-link text-white">Welcome, <?= htmlspecialchars($employee['full_name'] ?? $username) ?></span>
        <a class="nav-link text-white" href="../logout.php">Logout</a>
    </div>
</div>
</nav>

<div class="container mt-4">
    <!-- Welcome Header -->
    <div class="glass-card text-center">
        <h1 class="mb-3">
            <i class="fas fa-tachometer-alt me-3"></i>
            Welcome, <?= htmlspecialchars($employee['full_name'] ?? $username) ?>!
        </h1>
        <p class="mb-0">Employee Dashboard • <?= date('l, F j, Y') ?></p>
        
        <?php if ($today_status): ?>
            <div class="mt-3">
                <span class="badge <?= $today_status['status'] == 'Present' ? 'status-present' : 'status-absent' ?> fs-6">
                    Today: <?= $today_status['status'] ?>
                </span>
            </div>
        <?php else: ?>
            <div class="mt-3">
                <span class="badge bg-warning text-dark fs-6">
                    Today: Not Marked
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-number"><?= $attendance['present_days'] ?? 0 ?></div>
                <div class="stat-label">Present This Month</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-number"><?= $attendance['absent_days'] ?? 0 ?></div>
                <div class="stat-label">Absent Days</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-plane"></i>
                </div>
                <div class="stat-number"><?= $pending_leaves ?></div>
                <div class="stat-label">Pending Leaves</div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-number"><?= $attendance_rate ?>%</div>
                <div class="stat-label">Attendance Rate</div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Quick Actions -->
        <div class="col-lg-8">
            <div class="glass-card">
                <h3><i class="fas fa-bolt text-warning me-2"></i>Quick Actions</h3>
                <div class="list-group">
                    <a href="profile.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-user me-3"></i>👤 My Profile
                        <small class="text-muted d-block">View and update personal information</small>
                    </a>
                    <a href="attendance.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-clock me-3"></i>🗓 Mark / View Attendance
                        <small class="text-muted d-block">Mark today's attendance or view history</small>
                    </a>
                    <a href="leave_request.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-plane me-3"></i>📝 Apply for Leave
                        <small class="text-muted d-block">Submit new leave requests</small>
                    </a>
                    <a href="salary.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-money-bill-wave me-3"></i>💰 View Salary
                        <small class="text-muted d-block">Check salary details and payslips</small>
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="col-lg-4">
            <div class="glass-card">
                <h3><i class="fas fa-history text-info me-2"></i>Recent Leaves</h3>
                
                <?php if (empty($recent_leaves)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-calendar fa-3x mb-3" style="color: rgba(255, 255, 255, 0.3);"></i>
                        <p style="color: rgba(255, 255, 255, 0.6);">No leave requests yet</p>
                        <a href="leave_request.php" class="btn btn-primary btn-sm">Apply for Leave</a>
                    </div>
                <?php else: ?>
                    <?php foreach($recent_leaves as $leave): ?>
                        <div style="background: rgba(255, 255, 255, 0.05); border-radius: 8px; padding: 12px; margin-bottom: 10px;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($leave['leave_type']) ?></strong>
                                    <br>
                                    <small style="color: rgba(255, 255, 255, 0.7);">
                                        <?= date('M d', strtotime($leave['start_date'])) ?> - <?= date('M d', strtotime($leave['end_date'])) ?>
                                    </small>
                                </div>
                                <div>
                                    <?php
                                    $status_class = 'status-pending';
                                    if ($leave['status'] == 'Approved') $status_class = 'status-approved';
                                    if ($leave['status'] == 'Rejected') $status_class = 'status-rejected';
                                    ?>
                                    <span class="badge <?= $status_class ?>"><?= $leave['status'] ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-center mt-3">
                        <a href="leave_request.php" class="btn btn-outline-light btn-sm">View All Leaves</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Employee Info -->
            <div class="glass-card">
                <h3><i class="fas fa-id-card text-success me-2"></i>My Info</h3>
                <div class="employee-info">
                    <p><strong>Name:</strong> <?= htmlspecialchars($employee['full_name'] ?? 'Not set') ?></p>
                    <p><strong>Employee ID:</strong> EMP-<?= str_pad($user_id, 4, '0', STR_PAD_LEFT) ?></p>
                    <p><strong>Department:</strong> <?= htmlspecialchars($employee['department'] ?? 'Not assigned') ?></p>
                    <p><strong>Position:</strong> <?= htmlspecialchars($employee['position'] ?? 'Not assigned') ?></p>
                    <p><strong>Join Date:</strong> <?= $employee['join_date'] ? date('M d, Y', strtotime($employee['join_date'])) : 'Not set' ?></p>
                </div>
                <a href="profile.php" class="btn btn-outline-light btn-sm w-100 mt-2">
                    <i class="fas fa-edit me-2"></i>Edit Profile
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Add hover effects
document.querySelectorAll('.stat-card').forEach(card => {
    card.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-8px) scale(1.02)';
    });
    card.addEventListener('mouseleave', () => {
        card.style.transform = 'translateY(0) scale(1)';
    });
});

// Add click animation to quick actions
document.querySelectorAll('.list-group-item').forEach(item => {
    item.addEventListener('click', () => {
        item.style.transform = 'scale(0.98)';
        setTimeout(() => {
            item.style.transform = 'translateX(10px)';
        }, 100);
    });
});
</script>
