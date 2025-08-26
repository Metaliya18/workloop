<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$showAlert = false;
$alertMessage = '';
$alertType = '';
$adminName = 'Admin';

try {
    $conn = new PDO("mysql:host=localhost;dbname=ems", 'root', '');
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get user's full name
    if (isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT full_name FROM employees WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['full_name'])) {
            $adminName = $result['full_name'];
        }
    }

    // Handle password update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $currentPassword = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
        $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
        $confirmPassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
        $userId = $_SESSION['user_id'];

        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            // $message = '<div class="alert alert-warning">⚠️ Please fill all fields.</div>';
            // $showAlert = true;
            // $alertMessage = 'Please fill all fields.';
            // $alertType = 'warning';
        } elseif ($newPassword !== $confirmPassword) {
            $message = '<div class="alert alert-error">❌ New passwords do not match.</div>';
            $showAlert = true;
            $alertMessage = 'New passwords do not match.';
            $alertType = 'error';
        } elseif (strlen($newPassword) < 6) {
            $message = '<div class="alert alert-error">❌ Password must be at least 6 characters long.</div>';
            $showAlert = true;
            $alertMessage = 'Password must be at least 6 characters long.';
            $alertType = 'error';
        } else {
            try {
                // Get current password hash
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Password verification
                    if (password_verify($currentPassword, $user['password'])) {
                        // Hash new password
                        $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        
                        // Update password in database
                        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $updateResult = $updateStmt->execute([$newPasswordHash, $userId]);
                        
                        if ($updateResult && $updateStmt->rowCount() > 0) {
                            $message = '<div class="alert alert-success">✅ Password updated successfully!</div>';
                            $showAlert = true;
                            $alertMessage = '🎉 Password updated successfully!\n\nYour password has been changed.\nYou can now use your new password to login.';
                            $alertType = 'success';
                        } else {
                            $message = '<div class="alert alert-error">❌ Failed to update password in database.</div>';
                            $showAlert = true;
                            $alertMessage = 'Failed to update password. Please try again.';
                            $alertType = 'error';
                        }
                    } else {
                        $message = '<div class="alert alert-error">❌ Current password is incorrect.</div>';
                        $showAlert = true;
                        $alertMessage = 'Current password is incorrect. Please check and try again.';
                        $alertType = 'error';
                    }
                } else {
                    $message = '<div class="alert alert-error">❌ User not found with ID: ' . $userId . '</div>';
                    $showAlert = true;
                    $alertMessage = 'User not found. Please login again.';
                    $alertType = 'error';
                }
            } catch (PDOException $e) {
                error_log("Password update error: " . $e->getMessage());
                $message = '<div class="alert alert-error">❌ Database error: ' . $e->getMessage() . '</div>';
                $showAlert = true;
                $alertMessage = 'Database error occurred. Please try again later.';
                $alertType = 'error';
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    $message = '<div class="alert alert-error">❌ Database connection failed.</div>';
    $showAlert = true;
    $alertMessage = 'Database connection failed. Please check your connection.';
    $alertType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 500px;
            margin: 50px auto;
        }
        
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
            font-size: 28px;
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
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 15px;
            font-weight: 600;
        }
        
        .form-container {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0 0 20px 20px;
            padding: 40px;
        }
        
        .form-title {
            color: white;
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: white;
            font-size: 14px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .form-control:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.15);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }
        
        .required {
            color: #ff6b6b;
        }
        
        .btn {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 10px;
        }
        
        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(255, 255, 255, 0.2);
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
        
        @media (max-width: 768px) {
            .container {
                margin: 20px auto;
            }
            
            .header, .form-container {
                padding: 25px 20px;
            }
        }
        
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
    <div class="header">
        <h1>🔒 Update Password</h1>
        <p>Change your account password securely</p>
        <div class="admin-info">
            Welcome, <?php echo htmlspecialchars($adminName); ?>!
        </div>
    </div>
    
    <div class="form-container">
        <?php echo $message; ?>
        
        
        <form method="POST" action="up_pass.php" id="passwordForm">
            <div class="form-group">
                <label>Current Password <span class="required">*</span></label>
                <input type="password" name="current_password" class="form-control" 
                       placeholder="Enter your current password" required>
            </div>
            
            <div class="form-group">
                <label>New Password <span class="required">*</span></label>
                <input type="password" name="new_password" class="form-control" 
                       placeholder="Enter your new password" required minlength="6">
            </div>
            
            <div class="form-group">
                <label>Confirm New Password <span class="required">*</span></label>
                <input type="password" name="confirm_password" class="form-control" 
                       placeholder="Confirm your new password" required minlength="6">
            </div>
            
            <div style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary" id="updateBtn">
                    🔄 Update Password
                </button>
                <a href="dashboard.php" class="btn btn-secondary">
                    ← Back to Dashboard
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Alert Messages -->
<?php if ($showAlert): ?>
<script>
window.onload = function() {
    <?php if ($alertType === 'success'): ?>
    alert('<?php echo addslashes($alertMessage); ?>');
    // Clear form after successful update
    document.getElementById('passwordForm').reset();
    <?php else: ?>
    alert('<?php echo addslashes($alertMessage); ?>');
    <?php endif; ?>
};
</script>
<?php endif; ?>

</body>
</html>
