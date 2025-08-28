<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header('Location: ../login.php');
    exit;
}

require '../config/db.php';

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Employee';
$today = date('Y-m-d');

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $project_id = $_POST['project_id'];
    $new_status = $_POST['status'];
    $comment = $_POST['status_comment'] ?? '';
    
    try {
        $stmt = $pdo->prepare('INSERT INTO project_status (project_id, status, status_comment, updated_by, status_date) VALUES (?, ?, ?, ?, NOW())');
        $stmt->execute([$project_id, $new_status, $comment, $user_id]);
        $success_message = "Status updated successfully!";
    } catch (Exception $e) {
        $error_message = "Error updating status: " . $e->getMessage();
    }
}

try {
    // Get employee info using user_id
    $empStmt = $pdo->prepare('SELECT id, user_id, full_name, department FROM employees WHERE user_id = ?');
    $empStmt->execute([$user_id]);
    $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$employee) {
        throw new Exception("Employee with user_id $user_id not found!");
    }
    
    // Get the actual database id to use in project queries
    $employee_database_id = $employee['id'];
    
    // Get projects assigned to this employee
    $stmt = $pdo->prepare('
        SELECT 
            ap.*,
            COALESCE(ps.status, "Assigned") as current_status,
            ps.status_comment,
            ps.status_date
        FROM assign_project ap
        LEFT JOIN (
            SELECT 
                project_id, 
                status, 
                status_comment, 
                status_date,
                ROW_NUMBER() OVER (PARTITION by project_id ORDER BY status_date DESC) as rn
            FROM project_status
        ) ps ON ps.project_id = ap.id AND ps.rn = 1
        WHERE ap.employee_id = ?
        ORDER BY ap.start_date ASC
    ');
    $stmt->execute([$employee_database_id]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Database Error: " . $e->getMessage();
    $projects = [];
    $employee = null;
}

function getStatusBadge($status) {
    $badges = [
        'Assigned' => ['bg-primary', 'fas fa-clipboard-list'],
        'In Progress' => ['bg-warning text-dark', 'fas fa-spinner'],
        'Completed' => ['bg-success', 'fas fa-check-circle'],
        'On Hold' => ['bg-secondary', 'fas fa-pause-circle'],
        'Cancelled' => ['bg-danger', 'fas fa-times-circle']
    ];
    
    $status = $status ?: 'Assigned';
    $badge = $badges[$status] ?? ['bg-secondary', 'fas fa-question-circle'];
    return [$badge[0], $badge[1], $status];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Assigned Projects</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: white;
            font-family: 'Segoe UI', sans-serif;
        }
        
        .navbar {
            background: rgba(0, 0, 0, 0.4) !important;
            backdrop-filter: blur(15px);
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .table-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .table {
            color: #333;
            margin: 0;
        }
        
        .table th {
            background: rgba(102, 126, 234, 0.1);
            border: none;
            padding: 15px 10px;
            font-weight: 600;
            color: #495057;
        }
        
        .table td {
            padding: 15px 10px;
            vertical-align: middle;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }
        
        .table tbody tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .employee-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="dashboard.php">
                <i class="fas fa-briefcase me-3"></i>Employee Portal
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home me-2"></i>Dashboard
                </a>
                <a class="nav-link active" href="my_project.php">
                    <i class="fas fa-project-diagram me-2"></i>My Projects
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <!-- Header -->
        <div class="main-container">
            <div class="text-center mb-4">
                <h1 class="display-5 fw-bold mb-3">
                    <i class="fas fa-user-circle me-3"></i>My Project Dashboard
                </h1>
                <p class="lead">Welcome back, <strong><?= htmlspecialchars($employee['full_name'] ?? $username) ?></strong>!</p>
            </div>

            <!-- Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Employee Info Stats -->
            <?php if ($employee): ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4><i class="fas fa-user text-primary"></i></h4>
                        <h5><?= htmlspecialchars($employee['full_name']) ?></h5>
                        <small>Employee Name</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4><i class="fas fa-id-badge text-success"></i></h4>
                        <h5><?= $employee['user_id'] ?></h5>
                        <small>Employee ID</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4><i class="fas fa-building text-warning"></i></h4>
                        <h5><?= htmlspecialchars($employee['department']) ?></h5>
                        <small>Department</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4><i class="fas fa-tasks text-info"></i></h4>
                        <h5><?= count($projects) ?></h5>
                        <small>Total Projects</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Projects Table -->
        <div class="main-container">
            <h4 class="mb-4">
                <i class="fas fa-project-diagram me-2"></i>My Assigned Projects (<?= count($projects) ?>)
            </h4>
            
            <div class="table-container">
                <?php if (empty($projects)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open fa-5x mb-4 text-muted"></i>
                        <h3 class="text-muted">No Projects Found</h3>
                        <p class="text-muted">No projects assigned to you yet.</p>
                        
                        <div class="alert alert-info mt-4">
                            <h6><i class="fas fa-info-circle me-2"></i>For Admin:</h6>
                            <p>To assign projects, use Employee ID: <strong><?= $employee['id'] ?? $user_id ?></strong></p>
                            
                            <div class="bg-dark p-3 rounded">
                                <code style="color: #00ff00;">
                                    INSERT INTO assign_project (project_name, employee_id, start_date, end_date, description)<br>
                                    VALUES ('New Project', <?= $employee['id'] ?? $user_id ?>, '<?= date('Y-m-d') ?>', '<?= date('Y-m-d', strtotime('+15 days')) ?>', 'Project description');
                                </code>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success mb-4">
                        <h5><i class="fas fa-check-circle me-2"></i>Projects Found!</h5>
                        <p class="mb-0">You have <?= count($projects) ?> project(s) assigned to you.</p>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Project Name</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Current Status</th>
                                    <th>Last Updated</th>
                                    <th>Comments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $project): ?>
                                <?php list($badgeClass, $badgeIcon, $statusText) = getStatusBadge($project['current_status']); ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($project['project_name']) ?></strong>
                                        <?php if ($project['description']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars(substr($project['description'], 0, 50)) ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y', strtotime($project['start_date'])) ?></td>
                                    <td><?= $project['end_date'] ? date('M d, Y', strtotime($project['end_date'])) : '-' ?></td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>">
                                            <i class="<?= $badgeIcon ?> me-1"></i><?= $statusText ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $project['status_date'] ? date('M d, H:i', strtotime($project['status_date'])) : 'Not updated' ?>
                                    </td>
                                    <td>
                                        <?= $project['status_comment'] ? htmlspecialchars(substr($project['status_comment'], 0, 30)) . '...' : '-' ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal" 
                                                data-project-id="<?= $project['id'] ?>" 
                                                data-project-name="<?= htmlspecialchars($project['project_name']) ?>"
                                                data-current-status="<?= $project['current_status'] ?>">
                                            <i class="fas fa-edit me-1"></i>Update
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Project Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="project_id" id="modal_project_id">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Project:</label>
                            <p id="modal_project_name" class="fw-bold"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label fw-bold">New Status:</label>
                            <select class="form-select" name="status" id="status" required 
                                    style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3); color: white;">
                                <option value="Assigned">Assigned</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="On Hold">On Hold</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status_comment" class="form-label fw-bold">Comments:</label>
                            <textarea class="form-control" name="status_comment" id="status_comment" rows="3" 
                                    placeholder="Add your comments here..."
                                    style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3); color: white;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('statusModal').addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const projectId = button.getAttribute('data-project-id');
            const projectName = button.getAttribute('data-project-name');
            const currentStatus = button.getAttribute('data-current-status');
            
            document.getElementById('modal_project_id').value = projectId;
            document.getElementById('modal_project_name').textContent = projectName;
            document.getElementById('status').value = currentStatus || 'Assigned';
        });
    </script>
</body>
</html>
