<?php
session_start();

// Database connection
try {
    $pdo = new PDO("mysql:host=localhost;dbname=ems", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$alertType = '';
$showAlert = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_role = isset($_POST['role']) ? trim($_POST['role']) : '';
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $current_password = isset($_POST['current_password']) ? trim($_POST['current_password']) : '';
    $new_password = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    $confirm_password = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

    if (empty($selected_role) || empty($username) || empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'Please fill all fields.';
        $alertType = 'warning';
        $showAlert = true;
    } elseif ($new_password !== $confirm_password) {
        $message = 'New passwords do not match.';
        $alertType = 'danger';
        $showAlert = true;
    } elseif (strlen($new_password) < 6) {
        $message = 'Password must be at least 6 characters long.';
        $alertType = 'danger';
        $showAlert = true;
    } else {
        try {
            // Find user by username and selected role
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = ? AND is_approved = 1");
            $stmt->execute([$username, $selected_role]);
            $target_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$target_user) {
                $message = 'User not found with username "' . $username . '" and role "' . $selected_role . '".';
                $alertType = 'danger';
                $showAlert = true;
            } else {
                // Verify current password
                if (password_verify($current_password, $target_user['password'])) {
                    // Hash new password
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update password
                    $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = ?");
                    $update_result = $update_stmt->execute([$new_password_hash, $target_user['id'], $selected_role]);
                    
                    if ($update_result && $update_stmt->rowCount() > 0) {
                        // Optional: Log password change
                        try {
                            $log_stmt = $pdo->prepare("INSERT INTO password_updates (user_id, old_password_hash, updated_at) VALUES (?, ?, NOW())");
                            $log_stmt->execute([$target_user['id'], $target_user['password']]);
                        } catch (PDOException $e) {
                            // Log failed but password change succeeded
                        }
                        
                        $message = '✅ Password updated successfully for ' . ucfirst($selected_role) . ': ' . $username;
                        $alertType = 'success';
                        $showAlert = true;
                    } else {
                        $message = 'Failed to update password. Please try again.';
                        $alertType = 'danger';
                        $showAlert = true;
                    }
                } else {
                    $message = '❌ Current password is incorrect for user: ' . $username;
                    $alertType = 'danger';
                    $showAlert = true;
                }
            }
        } catch (PDOException $e) {
            error_log("Password change error: " . $e->getMessage());
            $message = 'Database error occurred. Please try again later.';
            $alertType = 'danger';
            $showAlert = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - Select User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 50px auto;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            color: white;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h2 {
            color: white;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }

        .step {
            display: flex;
            align-items: center;
            margin: 0 10px;
        }

        .step-number {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 8px;
        }

        .form-control, .form-select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            color: white;
            padding: 12px 15px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }

        .form-control:focus, .form-select:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .form-select option {
            background: #333;
            color: white;
        }

        .role-indicator {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .role-admin {
            background: rgba(220, 53, 69, 0.3);
            color: #fff;
        }

        .role-employee {
            background: rgba(40, 167, 69, 0.3);
            color: #fff;
        }

        .btn-primary {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: rgba(108, 117, 125, 0.2);
            border: 2px solid rgba(108, 117, 125, 0.3);
            color: white;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: rgba(108, 117, 125, 0.3);
            color: white;
            text-decoration: none;
        }

        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 20px;
            padding: 15px;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            color: #d4edda;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #f8d7da;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.2);
            color: #fff3cd;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
        }

        .password-requirements {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .input-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            z-index: 10;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="glass-card">
        <div class="header">
            <h2><i class="fas fa-key me-2"></i>Change User Password</h2>
            <p>Select role and user to change password</p>
        </div>

        <div class="step-indicator">
            <div class="step">
                <div class="step-number">1</div>
                <span>Select Role</span>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <span>Enter Details</span>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <span>Update Password</span>
            </div>
        </div>

        <?php if ($showAlert): ?>
            <div class="alert alert-<?= $alertType ?>">
                <i class="fas fa-<?= $alertType === 'success' ? 'check-circle' : ($alertType === 'warning' ? 'exclamation-triangle' : 'times-circle') ?> me-2"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="passwordForm">
            <!-- Step 1: Role Selection -->
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-users me-2"></i>Select User Role <span class="text-danger">*</span>
                </label>
                <select name="role" class="form-select" required id="roleSelect">
                    <option value="">Choose Role...</option>
                    <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>
                        👨‍💼 Admin
                    </option>
                    <option value="employee" <?= (isset($_POST['role']) && $_POST['role'] === 'employee') ? 'selected' : '' ?>>
                        👤 Employee
                    </option>
                </select>
            </div>

            <!-- Step 2: Username -->
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-user me-2"></i>Username <span class="text-danger">*</span>
                </label>
                <input type="text" name="username" class="form-control" 
                       placeholder="Enter username" required 
                       value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                <small class="text-light">Enter the exact username for the selected role</small>
            </div>

            <!-- Password Requirements Info
            <div class="password-requirements">
                <h6><i class="fas fa-info-circle me-2"></i>Password Requirements:</h6>
                <ul class="mb-0 ps-3">
                    <li>Must be at least 6 characters long</li>
                    <li>Use strong combination of letters, numbers, and symbols</li>
                    <li>Current password of the target user is required</li>
                </ul>
            </div> -->

            <!-- Step 3: Password Fields -->
            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-lock me-2"></i>Current Password (of target user) <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <input type="password" name="current_password" class="form-control" 
                           placeholder="Current password of target user" required id="currentPass">
                    <i class="fas fa-eye password-toggle" onclick="togglePassword('currentPass', this)"></i>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">
                    <i class="fas fa-key me-2"></i>New Password <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <input type="password" name="new_password" class="form-control" 
                           placeholder="Enter new password" required minlength="6" id="newPass">
                    <i class="fas fa-eye password-toggle" onclick="togglePassword('newPass', this)"></i>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">
                    <i class="fas fa-check-circle me-2"></i>Confirm New Password <span class="text-danger">*</span>
                </label>
                <div class="input-group">
                    <input type="password" name="confirm_password" class="form-control" 
                           placeholder="Confirm new password" required minlength="6" id="confirmPass">
                    <i class="fas fa-eye password-toggle" onclick="togglePassword('confirmPass', this)"></i>
                </div>
            </div>

            <div class="d-grid gap-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sync-alt me-2"></i>Change Password
                </button>
                
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </form>

        <!-- User Guide
        <div class="mt-4 p-3" style="background: rgba(255, 255, 255, 0.05); border-radius: 10px;">
            <h6><i class="fas fa-question-circle me-2"></i>How to use:</h6>
            <ol class="mb-0 ps-3">
                <li><strong>Select Role:</strong> Choose Admin or Employee</li>
                <li><strong>Enter Username:</strong> Type the exact username</li>
                <li><strong>Current Password:</strong> Enter the user's current password</li>
                <li><strong>New Password:</strong> Set the new password (min 6 chars)</li>
                <li><strong>Confirm:</strong> Re-enter new password to confirm</li>
            </ol>
        </div>
    </div>
</div> -->

<?php if ($showAlert && $alertType === 'success'): ?>
<script>
// Clear form and show success alert after successful password change
document.getElementById('passwordForm').reset();
setTimeout(function() {
    alert('🎉 Password Changed Successfully!\n\nPassword has been updated for the selected user.\nThey can now login with the new password.');
}, 500);
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Toggle password visibility
function togglePassword(fieldId, icon) {
    const field = document.getElementById(fieldId);
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Real-time password confirmation check
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('newPass');
    const confirmPassword = document.getElementById('confirmPass');
    
    function checkPasswordMatch() {
        if (confirmPassword.value && newPassword.value !== confirmPassword.value) {
            confirmPassword.style.borderColor = 'rgba(220, 53, 69, 0.8)';
            confirmPassword.setCustomValidity('Passwords do not match');
        } else {
            confirmPassword.style.borderColor = 'rgba(255, 255, 255, 0.3)';
            confirmPassword.setCustomValidity('');
        }
    }
    
    confirmPassword.addEventListener('input', checkPasswordMatch);
    newPassword.addEventListener('input', checkPasswordMatch);

    // Role selection indicator
    const roleSelect = document.getElementById('roleSelect');
    roleSelect.addEventListener('change', function() {
        const selectedRole = this.value;
        if (selectedRole) {
            console.log('Selected role:', selectedRole);
        }
    });
});
</script>

</body>
</html>
