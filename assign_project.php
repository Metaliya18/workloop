<?php
session_start();
if(!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: login.php");
    exit;
}

$message = "";

// 15 Pre-defined Projects Array[10]
$predefinedProjects = [
    "Website Development Project",
    "Mobile App Development",
    "Database Management System", 
    "E-commerce Platform",
    "Customer Management System",
    "Inventory Management System",
    "HR Management Portal",
    "Financial Tracking System",
    "Social Media Platform",
    "Learning Management System",
    "Content Management System",
    "Project Management Tool",
    "Digital Marketing Campaign",
    "Cloud Migration Project",
    "Cybersecurity Implementation"
];

try {
    $conn = new PDO("mysql:host=localhost;dbname=ems", "root", "");
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get employees with departments
    $stmt = $conn->query("SELECT id, full_name, department FROM employees ORDER BY department, full_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if($_SERVER["REQUEST_METHOD"] === "POST") {
        $project_name = trim($_POST["project_name"]);
        $employee_id = $_POST["employee_id"];
        $start_date = $_POST["start_date"];
        $end_date = !empty($_POST["end_date"]) ? $_POST["end_date"] : NULL;
        $description = trim($_POST["description"]);

        // Validation
        if($project_name && $employee_id && $start_date) {
            // Insert into your 5-field table
            $stmt = $conn->prepare("INSERT INTO assign_project (project_name, employee_id, start_date, end_date, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$project_name, $employee_id, $start_date, $end_date, $description]);
            $message = '<div class="alert alert-success">✅ Project assigned successfully!</div>';
        } else {
            $message = '<div class="alert alert-warning">⚠️ Please fill all required fields.</div>';
        }
    }
} catch(PDOException $e) {
    $message = '<div class="alert alert-danger">❌ Error: ' . $e->getMessage() . '</div>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Assign Project</title>
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
            max-width: 600px;
            margin: 0 auto;
            padding-top: 50px;
        }
        
        /* Themed Card */
        .form-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h2 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .form-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: white;
            font-size: 15px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        .required {
            color: #ff6b6b;
        }
        
        .form-control, .form-select {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }
        
        .form-select option {
            background: rgba(102, 126, 234, 0.9);
            color: white;
        }
        
        .form-select optgroup {
            background: rgba(102, 126, 234, 0.8);
            color: #fff3cd;
            font-weight: 600;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-textarea {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            resize: vertical;
            min-height: 120px;
        }
        
        .form-textarea::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }
        
        .btn-container {
            text-align: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 15px 40px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            margin: 5px;
        }
        
        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-3px);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
        }
        
        /* Alert messages */
        .alert {
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
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #f8d7da;
        }
        
        .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            color: #fff3cd;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding-top: 20px;
            }
            
            .form-card {
                padding: 25px 20px;
                margin: 10px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                margin: 5px 0;
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
    <div class="form-card">
        <!-- Header -->
        <div class="form-header">
            <h2>📋 Assign Project</h2>
            <p>Create and assign projects to your team members</p>
        </div>
        
        <!-- Messages -->
        <?= $message ?>
        
        <!-- Form -->
        <form method="POST" action="">
            <!-- 1. Project Name Dropdown (15 Projects) -->
            <div class="form-group">
                <label class="form-label">
                    📁 Project Name <span class="required">*</span>
                </label>
                <select name="project_name" class="form-select" required>
                    <option value="">Select Project</option>
                    <?php foreach ($predefinedProjects as $project): ?>
                        <option value="<?= htmlspecialchars($project) ?>">
                            <?= htmlspecialchars($project) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 2. Assign To Employee -->
            <div class="form-group">
                <label class="form-label">
                    👤 Assign To Employee <span class="required">*</span>
                </label>
                <select name="employee_id" class="form-select" required>
                    <option value="">Select Employee</option>
                    <?php
                    $currentDept = "";
                    foreach ($employees as $employee) {
                        if ($currentDept !== $employee['department']) {
                            if ($currentDept !== "") echo '</optgroup>';
                            $currentDept = $employee['department'] ?: "No Department";
                            echo '<optgroup label="🏢 '.htmlspecialchars($currentDept).'">';
                        }
                        echo '<option value="'.htmlspecialchars($employee['id']).'">'.htmlspecialchars($employee['full_name']).'</option>';
                    }
                    if ($currentDept !== "") echo '</optgroup>';
                    ?>
                </select>
            </div>

            <!-- 3. Start Date & 4. End Date -->
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">
                        📅 Start Date <span class="required">*</span>
                    </label>
                    <input type="date" name="start_date" class="form-control" required />
                </div>
                <div class="form-group">
                    <label class="form-label">
                        📅 End Date
                    </label>
                    <input type="date" name="end_date" class="form-control" />
                </div>
            </div>

            <!-- 5. Project Description -->
            <div class="form-group">
                <label class="form-label">
                    📝 Project Description
                </label>
                <textarea name="description" class="form-textarea" 
                          placeholder="Enter project description, requirements, and objectives..."></textarea>
            </div>

            <div class="btn-container">
                <button type="submit" class="btn btn-primary">
                    🚀 Assign Project
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    ← Back to Dashboard
                </a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set minimum date to today
    const today = new Date().toISOString().split('T');
    const startDate = document.querySelector('input[name="start_date"]');
    const endDate = document.querySelector('input[name="end_date"]');
    
    startDate.setAttribute('min', today);
    
    // End date should not be before start date
    startDate.addEventListener('change', function() {
        if(endDate.value && endDate.value < this.value) {
            endDate.value = '';
        }
        endDate.setAttribute('min', this.value);
    });
});
</script>

</body>
</html>
