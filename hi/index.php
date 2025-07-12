<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill Swap Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 2.5em;
            font-weight: 300;
        }
        
        .subtitle {
            color: #666;
            margin-bottom: 40px;
            font-size: 1.1em;
        }
        
        .portal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 40px;
        }
        
        .portal-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 30px 20px;
            text-decoration: none;
            color: #333;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .portal-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            border-color: #667eea;
        }
        
        .portal-icon {
            font-size: 3em;
            margin-bottom: 15px;
        }
        
        .portal-title {
            font-size: 1.3em;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .portal-desc {
            color: #666;
            font-size: 0.9em;
        }
        
        .admin-card {
            background: #e3f2fd;
        }
        
        .user-card {
            background: #f3e5f5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Skill Swap Platform</h1>
        <p class="subtitle">Connect, Share, and Learn Together</p>
        
        <div class="portal-grid">
            <a href="admin/login.php" class="portal-card admin-card">
                <div class="portal-icon">👨‍💼</div>
                <div class="portal-title">Admin Portal</div>
                <div class="portal-desc">Manage platform and users</div>
            </a>
            
            <a href="user/login.php" class="portal-card user-card">
                <div class="portal-icon">👤</div>
                <div class="portal-title">User Portal</div>
                <div class="portal-desc">Start swapping skills</div>
            </a>
        </div>
    </div>
</body>
</html>