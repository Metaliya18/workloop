<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$projects = [];
$message = '';
$adminName = 'Admin';

// Database connection with robust error handling[241][257]
try {
    $conn = new PDO("mysql:host=localhost;dbname=ems;charset=utf8mb4", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Test connection with a simple query
    $conn->query("SELECT 1");
    
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    $message = '<div class="alert-error">❌ Database connection failed. Please check your database settings.</div>';
    $conn = null;
}

// Only proceed if connection successful
if ($conn !== null) {
    try {
        // Get admin's full name safely
        $adminStmt = $conn->prepare("SELECT full_name FROM employees WHERE user_id = ? LIMIT 1");
        $adminStmt->execute([$_SESSION['user_id']]);
        $adminResult = $adminStmt->fetch();
        if ($adminResult && !empty($adminResult['full_name'])) {
            $adminName = $adminResult['full_name'];
        }

        // Fetch projects with improved query - handles missing columns gracefully[256][259]
        $projectQuery = "
            SELECT 
                ap.id,
                ap.project_name,
                ap.start_date,
                ap.end_date,
                ap.description,
                e.full_name as employee_name,
                e.department,
                'Assigned' as status,
                NOW() as created_at
            FROM assign_project ap
            LEFT JOIN employees e ON ap.employee_id = e.id
            ORDER BY ap.id DESC
            LIMIT 100
        ";
        
        $projectStmt = $conn->prepare($projectQuery);
        $projectStmt->execute();
        $projects = $projectStmt->fetchAll();

        if (empty($projects)) {
            // Check if table exists and has data
            $tableCheck = $conn->query("SELECT COUNT(*) as count FROM assign_project");
            $tableResult = $tableCheck->fetch();
            
            if ($tableResult['count'] == 0) {
                $message = '<div class="alert-warning">⚠️ No projects found. Start by assigning some projects!</div>';
            }
        }

    } catch (PDOException $e) {
        error_log("Project fetch error: " . $e->getMessage());
        
        // Check if table exists
        try {
            $conn->query("DESCRIBE assign_project");
        } catch (PDOException $tableError) {
            $message = '<div class="alert-error">❌ Table "assign_project" not found. Please create the table first.</div>';
        }
        
        if (empty($message)) {
            $message = '<div class="alert-error">❌ Error loading projects. Database query failed.</div>';
        }
        $projects = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Status</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding-top: 40px;
        }
        
        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px 20px 0 0;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            color: white;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
        }
        
        .admin-info {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 15px 25px;
            border-radius: 25px;
            display: inline-block;
            margin-top: 15px;
            font-weight: 600;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Content */
        .content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0 0 20px 20px;
            padding: 40px;
        }
        
        .content-title {
            color: white;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        /* Table */
        .table-container {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            overflow-x: auto;
        }
        
        .projects-table {
            width: 100%;
            border-collapse: collapse;
            color: white;
            min-width: 800px;
        }
        
        .projects-table th {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            white-space: nowrap;
        }
        
        .projects-table td {
            padding: 15px 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            vertical-align: top;
        }
        
        .projects-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .project-name {
            font-weight: 600;
            color: #fff;
        }
        
        .employee-info {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .department-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 3px;
            display: inline-block;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            background: rgba(52, 152, 219, 0.3);
            color: #3498db;
            border: 1px solid rgba(52, 152, 219, 0.5);
        }
        
        .date-info {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }
        
        /* Messages */
        .alert-success, .alert-error, .alert-warning {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-weight: 500;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            color: #d4edda;
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            color: #f8d7da;
        }
        
        .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            color: #fff3cd;
        }
        
        /* No data message */
        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .no-data-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .no-data h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: white;
        }
        
        .no-data p {
            font-size: 16px;
            margin-bottom: 30px;
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 12px 25px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            font-weight: 600;
            transition: all 0.3s ease;
            margin: 5px;
        }
        
        .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            text-decoration: none;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: rgba(52, 152, 219, 0.3);
            border-color: rgba(52, 152, 219, 0.5);
        }
        
        .btn-container {
            text-align: center;
            margin-top: 30px;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            color: white;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.8;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding-top: 20px;
            }
            
            .header, .content {
                padding: 25px 15px;
            }
            
            .projects-table {
                font-size: 14px;
            }
            
            .projects-table th,
            .projects-table td {
                padding: 10px 8px;
            }
            
            .stats-container {
                grid-template-columns: 1fr 1fr;
            }
        }
        
        /* Animation */
        .container {
            animation: fadeIn 0.6s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>📊 Project Status</h1>
        <p>Monitor and track all assigned projects</p>
        <div class="admin-info">
            Welcome, <?php echo htmlspecialchars($adminName); ?>!
        </div>
    </div>
    
    <!-- Content -->
    <div class="content">
        <?php echo $message; ?>
        
        <h2 class="content-title">Projects Overview</h2>
        
        <?php if (!empty($projects)): ?>
        <!-- Stats -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($projects); ?></div>
                <div class="stat-label">Total Projects</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_unique(array_column($projects, 'employee_name'))); ?></div>
                <div class="stat-label">Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_unique(array_filter(array_column($projects, 'department')))); ?></div>
                <div class="stat-label">Departments</div>
            </div>
        </div>
        
        <!-- Projects Table -->
        <div class="table-container">
            <table class="projects-table">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Employee</th>
                        <th>Status</th>
                        <th>Timeline</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td>
                            <div class="project-name">
                                <?php echo htmlspecialchars($project['project_name']); ?>
                            </div>
                        </td>
                        <td>
                            <div class="employee-info">
                                <?php echo htmlspecialchars($project['employee_name'] ?: 'Not Assigned'); ?>
                                <?php if (!empty($project['department'])): ?>
                                <div class="department-badge">
                                    <?php echo htmlspecialchars($project['department']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge">
                                <?php echo htmlspecialchars($project['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="date-info">
                                <strong>Start:</strong> <?php echo htmlspecialchars($project['start_date'] ?: 'Not Set'); ?><br>
                                <strong>End:</strong> <?php echo htmlspecialchars($project['end_date'] ?: 'Not Set'); ?>
                            </div>
                        </td>
                        <td>
                            <div style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <?php echo htmlspecialchars(substr($project['description'] ?: 'No description', 0, 100)); ?>
                                <?php if (strlen($project['description'] ?: '') > 100): ?>...<?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <!-- No Data Message -->
        <div class="no-data">
            <div class="no-data-icon">📋</div>
            <h3>No Projects Found</h3>
            <p>Projects will appear here once they are created and assigned.</p>
            <a href="assign_project.php" class="btn btn-primary">Assign New Project</a>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="btn-container">
            <a href="assign_project.php" class="btn btn-primary">+ Assign New Project</a>
            <a href="dashboard.php" class="btn">← Back to Dashboard</a>
        </div>
    </div>
</div>

</body>
</html>
