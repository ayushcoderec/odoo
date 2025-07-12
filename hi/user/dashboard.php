<?php
// user/dashboard.php
require_once '../db.php';
require_once '../auth/session.php';

checkUserLogin();

$message = '';
$activeTab = $_GET['tab'] ?? 'overview';
$userId = $_SESSION['user_id'];

// Handle various user actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_skill_offered':
            $skillName = $_POST['skill_name'];
            $skillDescription = $_POST['skill_description'];
            $stmt = $pdo->prepare("INSERT INTO skills_offered (user_id, skill_name, skill_description) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $skillName, $skillDescription]);
            $message = "Skill added successfully! It will be reviewed by admin.";
            break;
            
        case 'add_skill_wanted':
            $skillName = $_POST['skill_name'];
            $skillDescription = $_POST['skill_description'];
            $stmt = $pdo->prepare("INSERT INTO skills_wanted (user_id, skill_name, skill_description) VALUES (?, ?, ?)");
            $stmt->execute([$userId, $skillName, $skillDescription]);
            $message = "Skill request added successfully!";
            break;
            
        case 'send_swap_request':
            $providerId = $_POST['provider_id'];
            $offeredSkillId = $_POST['offered_skill_id'];
            $wantedSkillId = $_POST['wanted_skill_id'];
            $messageText = $_POST['message'];
            
            $stmt = $pdo->prepare("INSERT INTO swap_requests (requester_id, provider_id, offered_skill_id, wanted_skill_id, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $providerId, $offeredSkillId, $wantedSkillId, $messageText]);
            $message = "Swap request sent successfully!";
            break;
            
        case 'respond_to_request':
            $requestId = $_POST['request_id'];
            $response = $_POST['response'];
            $stmt = $pdo->prepare("UPDATE swap_requests SET status = ? WHERE id = ? AND provider_id = ?");
            $stmt->execute([$response, $requestId, $userId]);
            $message = "Request " . $response . " successfully!";
            break;
            
        case 'cancel_request':
            $requestId = $_POST['request_id'];
            $stmt = $pdo->prepare("UPDATE swap_requests SET status = 'cancelled' WHERE id = ? AND requester_id = ?");
            $stmt->execute([$requestId, $userId]);
            $message = "Request cancelled successfully!";
            break;
            
        case 'submit_rating':
            $swapId = $_POST['swap_id'];
            $ratedId = $_POST['rated_id'];
            $rating = $_POST['rating'];
            $feedback = $_POST['feedback'];
            
            $stmt = $pdo->prepare("INSERT INTO ratings (swap_id, rater_id, rated_id, rating, feedback) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$swapId, $userId, $ratedId, $rating, $feedback]);
            $message = "Rating submitted successfully!";
            break;
            
        case 'update_profile':
            $name = $_POST['name'];
            $email = $_POST['email'];
            $location = $_POST['location'];
            $availability = $_POST['availability'];
            $isPublic = isset($_POST['is_public']) ? 1 : 0;
            
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, location = ?, availability = ?, is_public = ? WHERE id = ?");
            $stmt->execute([$name, $email, $location, $availability, $isPublic, $userId]);
            $message = "Profile updated successfully!";
            break;
    }
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Fetch user's skills
$stmt = $pdo->prepare("SELECT * FROM skills_offered WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$mySkillsOffered = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM skills_wanted WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$mySkillsWanted = $stmt->fetchAll();

// Fetch swap requests
$stmt = $pdo->prepare("
    SELECT sr.*, u1.name as requester_name, u2.name as provider_name, 
           so.skill_name as offered_skill, sw.skill_name as wanted_skill
    FROM swap_requests sr 
    JOIN users u1 ON sr.requester_id = u1.id 
    JOIN users u2 ON sr.provider_id = u2.id 
    JOIN skills_offered so ON sr.offered_skill_id = so.id 
    JOIN skills_wanted sw ON sr.wanted_skill_id = sw.id 
    WHERE sr.requester_id = ? OR sr.provider_id = ?
    ORDER BY sr.created_at DESC
");
$stmt->execute([$userId, $userId]);
$swapRequests = $stmt->fetchAll();

// Fetch available skills for swapping
$stmt = $pdo->prepare("
    SELECT so.*, u.name as user_name, u.location, u.availability, u.id as user_id
    FROM skills_offered so 
    JOIN users u ON so.user_id = u.id 
    WHERE so.status = 'approved' AND so.user_id != ? AND u.status = 'active' AND u.is_public = 1
    ORDER BY so.created_at DESC
");
$stmt->execute([$userId]);
$availableSkills = $stmt->fetchAll();

// Fetch user's ratings
$stmt = $pdo->prepare("
    SELECT r.*, u.name as rater_name 
    FROM ratings r 
    JOIN users u ON r.rater_id = u.id 
    WHERE r.rated_id = ? 
    ORDER BY r.created_at DESC
");
$stmt->execute([$userId]);
$myRatings = $stmt->fetchAll();

// Calculate average rating
$avgRating = 0;
if (count($myRatings) > 0) {
    $totalRating = array_sum(array_column($myRatings, 'rating'));
    $avgRating = round($totalRating / count($myRatings), 1);
}

// Fetch platform messages
$platformMessages = $pdo->query("SELECT * FROM platform_messages ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Statistics
$totalOfferedSkills = count($mySkillsOffered);
$totalWantedSkills = count($mySkillsWanted);
$totalSwapRequests = count($swapRequests);
$acceptedSwaps = count(array_filter($swapRequests, function($req) { return $req['status'] == 'accepted'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Skill Swap Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .nav-tabs {
            background: white;
            border-bottom: 2px solid #eee;
            padding: 0 20px;
        }
        
        .nav-tabs ul {
            list-style: none;
            display: flex;
            max-width: 1200px;
            margin: 0 auto;
            flex-wrap: wrap;
        }
        
        .nav-tabs li {
            margin-right: 30px;
        }
        
        .nav-tabs a {
            display: block;
            padding: 15px 0;
            text-decoration: none;
            color: #666;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .nav-tabs a:hover,
        .nav-tabs a.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #666;
            font-size: 1.1em;
        }
        
        .content-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.8em;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 30px;
            background: #667eea;
            border-radius: 2px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-right: 10px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-outline {
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-3px);
        }
        
        .card-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-size: 1.2em;
            font-weight: 600;
            color: #333;
        }
        
        .skill-card {
            border-left: 4px solid #667eea;
        }
        
        .swap-card {
            border-left: 4px solid #28a745;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
            display: inline-block;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-accepted {
            background: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stars {
            color: #ffc107;
            font-size: 1.2em;
        }
        
        .rating-input {
            display: flex;
            gap: 5px;
            margin-bottom: 10px;
        }
        
        .rating-input input[type="radio"] {
            display: none;
        }
        
        .rating-input label {
            cursor: pointer;
            font-size: 24px;
            color: #ddd;
            transition: color 0.3s ease;
        }
        
        .rating-input label:hover,
        .rating-input input[type="radio"]:checked ~ label,
        .rating-input input[type="radio"]:checked + label {
            color: #ffc107;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #c82333;
        }
        
        .hidden {
            display: none;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
            width: 90%;
            position: relative;
        }
        
        .close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #999;
        }
        
        .close:hover {
            color: #333;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-tabs ul {
                flex-direction: column;
            }
            
            .nav-tabs li {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <h2><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p>Welcome to your dashboard!</p>
                </div>
            </div>
            <div>
                <a href="../auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="nav-tabs">
        <ul>
            <li><a href="?tab=overview" class="<?php echo $activeTab == 'overview' ? 'active' : ''; ?>">Overview</a></li>
            <li><a href="?tab=my-skills" class="<?php echo $activeTab == 'my-skills' ? 'active' : ''; ?>">My Skills</a></li>
            <li><a href="?tab=find-skills" class="<?php echo $activeTab == 'find-skills' ? 'active' : ''; ?>">Find Skills</a></li>
            <li><a href="?tab=swaps" class="<?php echo $activeTab == 'swaps' ? 'active' : ''; ?>">Swap Requests</a></li>
            <li><a href="?tab=ratings" class="<?php echo $activeTab == 'ratings' ? 'active' : ''; ?>">Ratings</a></li>
            <li><a href="?tab=profile" class="<?php echo $activeTab == 'profile' ? 'active' : ''; ?>">Profile</a></li>
        </ul>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <!-- Overview Tab -->
        <div class="<?php echo $activeTab != 'overview' ? 'hidden' : ''; ?>">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalOfferedSkills; ?></div>
                    <div class="stat-label">Skills Offered</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalWantedSkills; ?></div>
                    <div class="stat-label">Skills Wanted</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalSwapRequests; ?></div>
                    <div class="stat-label">Total Requests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $avgRating; ?></div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>
            
            <?php if ($platformMessages): ?>
            <div class="content-section">
                <h2 class="section-title">Platform Messages</h2>
                <?php foreach ($platformMessages as $msg): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo htmlspecialchars($msg['title']); ?></h3>
                        <small><?php echo date('M d, Y', strtotime($msg['created_at'])); ?></small>
                    </div>
                    <p><?php echo htmlspecialchars($msg['message']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- My Skills Tab -->
        <div class="<?php echo $activeTab != 'my-skills' ? 'hidden' : ''; ?>">
            <div class="content-section">
                <h2 class="section-title">Add New Skill Offered</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_skill_offered">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="skill_name">Skill Name</label>
                            <input type="text" id="skill_name" name="skill_name" required>
                        </div>
                        <div class="form-group">
                            <label for="skill_description">Description</label>
                            <textarea id="skill_description" name="skill_description" required></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Skill</button>
                </form>
            </div>
            
            <div class="content-section">
                <h2 class="section-title">Add New Skill Wanted</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_skill_wanted">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="skill_name_wanted">Skill Name</label>
                            <input type="text" id="skill_name_wanted" name="skill_name" required>
                        </div>
                        <div class="form-group">
                            <label for="skill_description_wanted">Description</label>
                            <textarea id="skill_description_wanted" name="skill_description" required></textarea>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Skill Request</button>
                </form>
            </div>
            
            <div class="content-section">
                <h2 class="section-title">My Skills Offered</h2>
                <?php if ($mySkillsOffered): ?>
                <div class="grid">
                    <?php foreach ($mySkillsOffered as $skill): ?>
                    <div class="card skill-card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo htmlspecialchars($skill['skill_name']); ?></h3>
                            <span class="status-badge status-<?php echo $skill['status']; ?>">
                                <?php echo ucfirst($skill['status']); ?>
                            </span>
                        </div>
                        <p><?php echo htmlspecialchars($skill['skill_description']); ?></p>
                        <small>Added: <?php echo date('M d, Y', strtotime($skill['created_at'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h3>No skills offered yet</h3>
                    <p>Add your first skill to start swapping!</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="content-section">
                <h2 class="section-title">Skills I Want to Learn</h2>
                <?php if ($mySkillsWanted): ?>
                <div class="grid">
                    <?php foreach ($mySkillsWanted as $skill): ?>
                    <div class="card skill-card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo htmlspecialchars($skill['skill_name']); ?></h3>
                        </div>
                        <p><?php echo htmlspecialchars($skill['skill_description']); ?></p>
                        <small>Added: <?php echo date('M d, Y', strtotime($skill['created_at'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h3>No skill requests yet</h3>
                    <p>Add skills you want to learn!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Find Skills Tab -->
        <div class="<?php echo $activeTab != 'find-skills' ? 'hidden' : ''; ?>">
            <div class="content-section">
                <h2 class="section-title">Available Skills</h2>
                <?php if ($availableSkills): ?>
                <div class="grid">
                    <?php foreach ($availableSkills as $skill): ?>
                    <div class="card skill-card">
                        <div class="card-header">
                            <h3 class="card-title"><?php echo htmlspecialchars($skill['skill_name']); ?></h3>
                            <button class="btn btn-outline" onclick="openSwapModal(<?php echo $skill['id']; ?>, '<?php echo htmlspecialchars($skill['skill_name']); ?>', <?php echo $skill['user_id']; ?>, '<?php echo htmlspecialchars($skill['user_name']); ?>')">
                                Request Swap
                            </button>
                        </div>
                        <p><?php echo htmlspecialchars($skill['skill_description']); ?></p>
                        <div style="margin-top: 10px;">
                            <strong>Offered by:</strong> <?php echo htmlspecialchars($skill['user_name']); ?><br>
                            <strong>Location:</strong> <?php echo htmlspecialchars($skill['location']); ?><br>
                            <strong>Availability:</strong> <?php echo htmlspecialchars($skill['availability']); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h3>No skills available</h3>
                    <p>Check back later for new skills!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Swap Requests Tab -->
        <div class="<?php echo $activeTab != 'swaps' ? 'hidden' : ''; ?>">
            <div class="content-section">
                <h2 class="section-title">Swap Requests</h2>
                <?php if ($swapRequests): ?>
                <div class="grid">
                    <?php foreach ($swapRequests as $request): ?>
                    <div class="card swap-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <?php echo $request['requester_id'] == $userId ? 'Sent' : 'Received'; ?> Request
                            </h3>
                            <span class="status-badge status-<?php echo $request['status']; ?>">
                                <?php echo ucfirst($request['status']); ?>
                            </span>
                        </div>
                        <p><strong>Offered:</strong> <?php echo htmlspecialchars($request['offered_skill']); ?></p>
                        <p><strong>Wanted:</strong> <?php echo htmlspecialchars($request['wanted_skill']); ?></p>
                        <p><strong>With:</strong> <?php echo $request['requester_id'] == $userId ? htmlspecialchars($request['provider_name']) : htmlspecialchars($request['requester_name']); ?></p>
                        <?php if ($request['message']): ?>
                        <p><strong>Message:</strong> <?php echo htmlspecialchars($request['message']); ?></p>
                        <?php endif; ?>
                        <small>Date: <?php echo date('M d, Y', strtotime($request['created_at'])); ?></small>
                        
                       <div style="margin-top: 15px;">
                            <?php if ($request['provider_id'] == $userId && $request['status'] == 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="respond_to_request">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <button type="submit" name="response" value="accepted" class="btn btn-success">Accept</button>
                                <button type="submit" name="response" value="rejected" class="btn btn-danger">Reject</button>
                            </form>
                            <?php elseif ($request['requester_id'] == $userId && $request['status'] == 'pending'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="cancel_request">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <button type="submit" class="btn btn-warning">Cancel Request</button>
                            </form>
                            <?php endif; ?>
                            
                            <?php if ($request['status'] == 'accepted'): ?>
                            <button class="btn btn-outline" onclick="openRatingModal(<?php echo $request['id']; ?>, <?php echo $request['requester_id'] == $userId ? $request['provider_id'] : $request['requester_id']; ?>, '<?php echo $request['requester_id'] == $userId ? htmlspecialchars($request['provider_name']) : htmlspecialchars($request['requester_name']); ?>')">
                                Rate Partner
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h3>No swap requests</h3>
                    <p>Start requesting skills to see your swaps here!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ratings Tab -->
        <div class="<?php echo $activeTab != 'ratings' ? 'hidden' : ''; ?>">
            <div class="content-section">
                <h2 class="section-title">My Ratings</h2>
                <div class="rating-display">
                    <div class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span <?php echo $i <= $avgRating ? 'style="color: #ffc107;"' : 'style="color: #ddd;"'; ?>>★</span>
                        <?php endfor; ?>
                    </div>
                    <span>Average: <?php echo $avgRating; ?>/5 (<?php echo count($myRatings); ?> ratings)</span>
                </div>
                
                <?php if ($myRatings): ?>
                <div class="grid">
                    <?php foreach ($myRatings as $rating): ?>
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Rating from <?php echo htmlspecialchars($rating['rater_name']); ?></h3>
                            <div class="stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span <?php echo $i <= $rating['rating'] ? 'style="color: #ffc107;"' : 'style="color: #ddd;"'; ?>>★</span>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php if ($rating['feedback']): ?>
                        <p><?php echo htmlspecialchars($rating['feedback']); ?></p>
                        <?php endif; ?>
                        <small>Date: <?php echo date('M d, Y', strtotime($rating['created_at'])); ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <h3>No ratings yet</h3>
                    <p>Complete some skill swaps to receive ratings!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Profile Tab -->
        <div class="<?php echo $activeTab != 'profile' ? 'hidden' : ''; ?>">
            <div class="content-section">
                <h2 class="section-title">Update Profile</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($user['location']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="availability">Availability</label>
                            <select id="availability" name="availability">
                                <option value="weekdays" <?php echo $user['availability'] == 'weekdays' ? 'selected' : ''; ?>>Weekdays</option>
                                <option value="weekends" <?php echo $user['availability'] == 'weekends' ? 'selected' : ''; ?>>Weekends</option>
                                <option value="evenings" <?php echo $user['availability'] == 'evenings' ? 'selected' : ''; ?>>Evenings</option>
                                <option value="flexible" <?php echo $user['availability'] == 'flexible' ? 'selected' : ''; ?>>Flexible</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="is_public" name="is_public" <?php echo $user['is_public'] ? 'checked' : ''; ?>>
                            <label for="is_public">Make my profile public (allow others to see my skills)</label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Swap Request Modal -->
    <div id="swapModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeSwapModal()">&times;</span>
            <h2>Request Skill Swap</h2>
            <form method="POST">
                <input type="hidden" name="action" value="send_swap_request">
                <input type="hidden" name="provider_id" id="provider_id">
                <input type="hidden" name="wanted_skill_id" id="wanted_skill_id">
                
                <div class="form-group">
                    <label>Skill you want to learn:</label>
                    <p id="wanted_skill_name" style="font-weight: bold; color: #667eea;"></p>
                </div>
                
                <div class="form-group">
                    <label>From:</label>
                    <p id="provider_name" style="font-weight: bold;"></p>
                </div>
                
                <div class="form-group">
                    <label for="offered_skill_id">Skill you offer in exchange:</label>
                    <select id="offered_skill_id" name="offered_skill_id" required>
                        <option value="">Select a skill to offer</option>
                        <?php foreach ($mySkillsOffered as $skill): ?>
                        <?php if ($skill['status'] == 'approved'): ?>
                        <option value="<?php echo $skill['id']; ?>"><?php echo htmlspecialchars($skill['skill_name']); ?></option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="message">Message (optional):</label>
                    <textarea id="message" name="message" placeholder="Introduce yourself and explain why you'd like to learn this skill..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Send Request</button>
            </form>
        </div>
    </div>
    
    <!-- Rating Modal -->
    <div id="ratingModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeRatingModal()">&times;</span>
            <h2>Rate Your Swap Partner</h2>
            <form method="POST">
                <input type="hidden" name="action" value="submit_rating">
                <input type="hidden" name="swap_id" id="swap_id">
                <input type="hidden" name="rated_id" id="rated_id">
                
                <div class="form-group">
                    <label>Rating for:</label>
                    <p id="rated_name" style="font-weight: bold;"></p>
                </div>
                
                <div class="form-group">
                    <label>Rating:</label>
                    <div class="rating-input">
                        <input type="radio" id="star5" name="rating" value="5">
                        <label for="star5">★</label>
                        <input type="radio" id="star4" name="rating" value="4">
                        <label for="star4">★</label>
                        <input type="radio" id="star3" name="rating" value="3">
                        <label for="star3">★</label>
                        <input type="radio" id="star2" name="rating" value="2">
                        <label for="star2">★</label>
                        <input type="radio" id="star1" name="rating" value="1">
                        <label for="star1">★</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="feedback">Feedback (optional):</label>
                    <textarea id="feedback" name="feedback" placeholder="How was your experience learning with this person?"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Submit Rating</button>
            </form>
        </div>
    </div>
    
    <script>
        function openSwapModal(skillId, skillName, providerId, providerName) {
            document.getElementById('wanted_skill_id').value = skillId;
            document.getElementById('wanted_skill_name').textContent = skillName;
            document.getElementById('provider_id').value = providerId;
            document.getElementById('provider_name').textContent = providerName;
            document.getElementById('swapModal').style.display = 'block';
        }
        
        function closeSwapModal() {
            document.getElementById('swapModal').style.display = 'none';
        }
        
        function openRatingModal(swapId, ratedId, ratedName) {
            document.getElementById('swap_id').value = swapId;
            document.getElementById('rated_id').value = ratedId;
            document.getElementById('rated_name').textContent = ratedName;
            document.getElementById('ratingModal').style.display = 'block';
        }
        
        function closeRatingModal() {
            document.getElementById('ratingModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const swapModal = document.getElementById('swapModal');
            const ratingModal = document.getElementById('ratingModal');
            if (event.target == swapModal) {
                closeSwapModal();
            }
            if (event.target == ratingModal) {
                closeRatingModal();
            }
        }
        
        // Star rating interaction
        const stars = document.querySelectorAll('.rating-input input[type="radio"]');
        const labels = document.querySelectorAll('.rating-input label');
        
        labels.forEach((label, index) => {
            label.addEventListener('mouseover', function() {
                for (let i = 0; i <= index; i++) {
                    labels[i].style.color = '#ffc107';
                }
                for (let i = index + 1; i < labels.length; i++) {
                    labels[i].style.color = '#ddd';
                }
            });
            
            label.addEventListener('mouseout', function() {
                const checkedStar = document.querySelector('.rating-input input[type="radio"]:checked');
                if (checkedStar) {
                    const checkedIndex = Array.from(stars).indexOf(checkedStar);
                    for (let i = 0; i <= checkedIndex; i++) {
                        labels[i].style.color = '#ffc107';
                    }
                    for (let i = checkedIndex + 1; i < labels.length; i++) {
                        labels[i].style.color = '#ddd';
                    }
                } else {
                    labels.forEach(label => label.style.color = '#ddd');
                }
            });
        });
    </script>
</body>
</html>