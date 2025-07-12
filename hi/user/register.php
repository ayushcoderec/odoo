<?php
// user/register.php
require_once '../db.php';
require_once '../auth/session.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $location = $_POST['location'] ?? '';
    $availability = $_POST['availability'] ?? '';
    
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, location, availability) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $location, $availability]);
        $message = "Registration successful! Please login.";
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            $message = "Email already exists!";
        } else {
            $message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .form-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            padding: 40px;
            width: 100%;
            max-width: 500px;
        }
        
        h2 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            font-size: 2em;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        
        input[type="text"], input[type="email"], input[type="password"], textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        input:focus, textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        textarea {
            height: 80px;
            resize: vertical;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .message {
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 5px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border-color: #f5c6cb;
        }
        
        .links {
            text-align: center;
            margin-top: 20px;
        }
        
        .links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 10px;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>User Registration</h2>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'Error') !== false || strpos($message, 'exists') !== false ? 'error' : ''; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="location">Location (Optional)</label>
                <input type="text" id="location" name="location" placeholder="e.g., New York, NY">
            </div>
            
            <div class="form-group">
                <label for="availability">Availability (Optional)</label>
                <textarea id="availability" name="availability" placeholder="e.g., Weekends, Evenings"></textarea>
            </div>
            
            <button type="submit" class="btn">Register</button>
        </form>
        
        <div class="links">
            <a href="login.php">Already have an account? Login</a>
            <a href="../index.php">Home</a>
        </div>
    </div>
</body>
</html>