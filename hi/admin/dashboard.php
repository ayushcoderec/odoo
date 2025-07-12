<?php
// admin/dashboard.php
require_once '../db.php';
require_once '../auth/session.php';

checkAdminLogin();

$message = '';
$activeTab = $_GET['tab'] ?? 'users';

// Handle various admin actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'ban_user':
            $userId = $_POST['user_id'];
            $stmt = $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
            $stmt->execute([$userId]);
            $message = "User banned successfully!";
            break;
            
        case 'unban_user':
            $userId = $_POST['user_id'];
            $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?");
            $stmt->execute([$userId]);
            $message = "User unbanned successfully!";
            break;
            
        case 'approve_skill':
            $skillId = $_POST['skill_id'];
            $stmt = $pdo->prepare("UPDATE skills_offered SET status = 'approved' WHERE id = ?");
            $stmt->execute([$skillId]);
            $message = "Skill approved successfully!";
            break;
            
        case 'reject_skill':
            $skillId = $_POST['skill_id'];
            $stmt = $pdo->prepare("UPDATE skills_offered SET status = 'rejected' WHERE id = ?");
            $stmt->execute([$skillId]);
            $message = "Skill rejected successfully!";
            break;
            
        case 'send_message':
            $title = $_POST['title'];
            $messageText = $_POST['message'];
            $stmt = $pdo->prepare("INSERT INTO platform_messages (title, message) VALUES (?, ?)");
            $stmt->execute([$title, $messageText]);
            $message = "Platform message sent successfully!";
            break;
    }
}

// Fetch data for different sections
$users = $pdo->query("SELECT id, name, email, location, status, created_at FROM users ORDER BY created_at DESC")->fetchAll();
$pendingSkills = $pdo->query("SELECT so.*, u.name as user_name FROM skills_offered so JOIN users u ON so.user_id = u.id WHERE so.status = 'pending' ORDER BY so.created_at DESC")->fetchAll();
$swapRequests = $pdo->query("SELECT sr.*, u1.name as requester_name, u2.name as provider_name, so.skill_name as offered_skill, sw.skill_name as wanted_skill FROM swap_requests sr JOIN users u1 ON sr.requester_id = u1.id JOIN users u2 ON sr.provider_id = u2.id JOIN skills_offered so ON sr.offered_skill_id = so.id JOIN skills_wanted sw ON sr.wanted_skill_id = sw.id ORDER BY sr.created_at DESC")->fetchAll();
$ratings = $pdo->query("SELECT r.*, u1.name as rater_name, u2.name as rated_name FROM ratings r JOIN users u1 ON r.rater_id = u1.id JOIN users u2 ON r.rated_id = u2.id ORDER BY r.created_at DESC")->fetchAll();

// Statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalSkills = $pdo->query("SELECT COUNT(*) FROM skills_offered")->fetchColumn();
$totalSwaps = $pdo->query("SELECT COUNT(*) FROM swap_requests")->fetchColumn();
$pendingSwaps = $pdo->query("SELECT COUNT(*) FROM swap_requests WHERE status = 'pending'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        
        .header {
            background: #667eea;
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
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
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
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 1.8em;
            margin-bottom: 20px;
            color: #333;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            margin-right: 5px;
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
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-banned {
            background: #f8d7da;
            color: #721c24;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <h1>Admin Dashboard</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</span>
                <a href="?action=logout" class="logout-btn">Logout</a>
            </div>
        </div>
    </div>
    
    <div class="nav-tabs">
        <ul>
            <li><a href="?tab=users" class="<?php echo $activeTab == 'users' ? 'active' : ''; ?>">Users</a></li>
            <li><a href="?tab=skills" class="<?php echo $activeTab == 'skills' ? 'active' : ''; ?>">Skills</a></li>
            <li><a href="?tab=swaps" class="<?php echo $activeTab == 'swaps' ? 'active' : ''; ?>">Swaps</a></li>
            <li><a href="?tab=ratings" class="<?php echo $activeTab == 'ratings' ? 'active' : ''; ?>">Ratings</a></li>
            <li><a href="?tab=messages" class="<?php echo $activeTab == 'messages' ? 'active' : ''; ?>">Messages</a></li>
            <li><a href="?tab=reports" class="<?php echo $activeTab == 'reports' ? 'active' : ''; ?>">Reports</a></li>
        </ul>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <!-- Statistics Dashboard -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalUsers; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalSkills; ?></div>
                <div class="stat-label">Total Skills</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalSwaps; ?></div>
                <div class="stat-label">Total Swaps</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pendingSwaps; ?></div>
                <div class="stat-label">Pending Swaps</div>
            </div>
        </div>
        
        <!-- Users Management -->
        <div class="content-section <?php echo $activeTab != 'users' ? 'hidden' : ''; ?>">
            <h2 class="section-title">Users Management</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['location'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $user['status']; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                        <td>
                            <?php if ($user['status'] == 'active'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="ban_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to ban this user?')">Ban</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="unban_user">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" class="btn btn-success">Unban</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Skills Management -->
        <div class="content-section <?php echo $activeTab != 'skills' ? 'hidden' : ''; ?>">
            <h2 class="section-title">Skills Management</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Skill Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingSkills as $skill): ?>
                    <tr>
                        <td><?php echo $skill['id']; ?></td>
                        <td><?php echo htmlspecialchars($skill['user_name']); ?></td>
                        <td><?php echo htmlspecialchars($skill['skill_name']); ?></td>
                        <td><?php echo htmlspecialchars(substr($skill['skill_description'], 0, 50)) . '...'; ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $skill['status']; ?>">
                                <?php echo ucfirst($skill['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($skill['created_at'])); ?></td>
                        <td>
                            <?php if ($skill['status'] == 'pending'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="approve_skill">
                                    <input type="hidden" name="skill_id" value="<?php echo $skill['id']; ?>">
                                    <button type="submit" class="btn btn-success">Approve</button>
                                </form>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="reject_skill">
                                    <input type="hidden" name="skill_id" value="<?php echo $skill['id']; ?>">
                                    <button type="submit" class="btn btn-danger">Reject</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Swaps Management -->
        <div class="content-section <?php echo $activeTab != 'swaps' ? 'hidden' : ''; ?>">
            <h2 class="section-title">Swaps Management</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Requester</th>
                        <th>Provider</th>
                        <th>Offered Skill</th>
                        <th>Wanted Skill</th>
                        <th>Status</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($swapRequests as $swap): ?>
                    <tr>
                        <td><?php echo $swap['id']; ?></td>
                        <td><?php echo htmlspecialchars($swap['requester_name']); ?></td>
                        <td><?php echo htmlspecialchars($swap['provider_name']); ?></td>
                        <td><?php echo htmlspecialchars($swap['offered_skill']); ?></td>
                        <td><?php echo htmlspecialchars($swap['wanted_skill']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $swap['status']; ?>">
                                <?php echo ucfirst($swap['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($swap['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Ratings Management -->
        <div class="content-section <?php echo $activeTab != 'ratings' ? 'hidden' : ''; ?>">
            <h2 class="section-title">Ratings & Feedback</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Rater</th>
                        <th>Rated User</th>
                        <th>Rating</th>
                        <th>Feedback</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ratings as $rating): ?>
                    <tr>
                        <td><?php echo $rating['id']; ?></td>
                        <td><?php echo htmlspecialchars($rating['rater_name']); ?></td>
                        <td><?php echo htmlspecialchars($rating['rated_name']); ?></td>
                        <td>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span style="color: <?php echo $i <= $rating['rating'] ? '#ffc107' : '#ddd'; ?>">★</span>
                            <?php endfor; ?>
                        </td>
                        <td><?php echo htmlspecialchars(substr($rating['feedback'], 0, 50)) . '...'; ?></td>
                        <td><?php echo date('Y-m-d', strtotime($rating['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Platform Messages -->
        <div class="content-section <?php echo $activeTab != 'messages' ? 'hidden' : ''; ?>">
            <h2 class="section-title">Send Platform Message</h2>
            <form method="POST">
                <input type="hidden" name="action" value="send_message">
                <div class="form-group">
                    <label for="title">Message Title</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-group">
                    <label for="message">Message Content</label>
                    <textarea id="message" name="message" required></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Send Message</button>
            </form>
        </div>
        
        <!-- Reports -->
        <div class="content-section <?php echo $activeTab != 'reports' ? 'hidden' : ''; ?>">
            <h2 class="section-title">Platform Reports</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(); ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'banned'")->fetchColumn(); ?></div>
                    <div class="stat-label">Banned Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pdo->query("SELECT COUNT(*) FROM skills_offered WHERE status = 'approved'")->fetchColumn(); ?></div>
                    <div class="stat-label">Approved Skills</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pdo->query("SELECT COUNT(*) FROM swap_requests WHERE status = 'accepted'")->fetchColumn(); ?></div>
                    <div class="stat-label">Successful Swaps</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pdo->query("SELECT AVG(rating) FROM ratings")->fetchColumn(); ?></div>
                    <div class="stat-label">Average Rating</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pdo->query("SELECT COUNT(*) FROM ratings")->fetchColumn(); ?></div>
                    <div class="stat-label">Total Ratings</div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Simple tab switching
        document.querySelectorAll('.nav-tabs a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const tab = this.getAttribute('href').split('=')[1];
                
                // Hide all sections
                document.querySelectorAll('.content-section').forEach(section => {
                    section.classList.add('hidden');
                });
                
                // Show selected section
                document.querySelector('.content-section.' + tab).classList.remove('hidden');
                
                // Update active tab
                document.querySelectorAll('.nav-tabs a').forEach(a => a.classList.remove('active'));
                this.classList.add('active');
                
                // Update URL
                window.history.pushState({}, '', '?tab=' + tab);
            });
        });
    </script>
</body>
</html>

<?php
// Handle logout
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>