<?php
session_start();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Display success/error messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success">' . $_SESSION['success_message'] . '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger">' . $_SESSION['error_message'] . '</div>';
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: index3.php");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] !== 'Admin') {
    header("Location: index3.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Database connection
$host = 'localhost';
$dbname = 'addwise';
$username = 'root';
$password = 'Qazqaz12#';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get all QR codes with assignment status
$qrCodes = [];
try {
    $stmt = $pdo->prepare("
        SELECT q.*, u.full_name as assigned_user_name, u.email as assigned_user_email 
        FROM qr_codes q 
        LEFT JOIN users u ON q.assigned_to = u.id 
        ORDER BY q.created_at DESC
    ");
    $stmt->execute();
    $qrCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching QR codes: " . $e->getMessage();
}

// Get all users except current admin
$users = [];
try {
    $stmt = $pdo->prepare("SELECT id, full_name, email, mobile, age, gender, role, is_verified FROM users WHERE id != ? ORDER BY role, full_name");
    $stmt->execute([$_SESSION['user_id']]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
}

// Get admin users for display only
$admins = [];
$regularUsers = [];
foreach ($users as $user) {
    if ($user['role'] === 'Admin') {
        $admins[] = $user;
    } else {
        $regularUsers[] = $user;
    }
}

// Get available QR codes for assignment
$availableQRs = [];
try {
    $stmt = $pdo->prepare("SELECT id, code FROM qr_codes WHERE assigned_to IS NULL ORDER BY code");
    $stmt->execute();
    $availableQRs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching available QR codes: " . $e->getMessage();
}

// Get statistics
$totalUsers = count($regularUsers);
$totalAdmins = count($admins);
$totalQRCodes = count($qrCodes);
$activeQRCodes = count(array_filter($qrCodes, function($qr) { return $qr['assigned_to'] !== null; }));

// QR Generation logic for Admin (insert after statistics and before HTML output)
require_once('phpqrcode/phpqrcode/qrlib.php');
$adminQrError = '';
$adminQrSuccess = '';
$adminQrCount = isset($_POST['adminQrCount']) ? (int)$_POST['adminQrCount'] : 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_admin_qr'])) {
    if ($adminQrCount < 1 || $adminQrCount > 100) {
        $adminQrError = "Please enter a number between 1 and 100.";
    } else {
        try {
            $pdo->beginTransaction();
            for ($i = 0; $i < $adminQrCount; $i++) {
                $code = '';
                for ($j = 0; $j < 16; $j++) {
                    $code .= (string)rand(0, 9);
                }
                ob_start();
                QRcode::png($code, null, QR_ECLEVEL_L, 6, 2);
                $imageString = ob_get_contents();
                ob_end_clean();
                $base64Image = base64_encode($imageString);
                $stmt = $pdo->prepare("INSERT INTO qr_codes (code, image_data) VALUES (?, ?)");
                $stmt->execute([$code, $base64Image]);
            }
            $pdo->commit();
            $adminQrSuccess = "$adminQrCount QR codes generated successfully!";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $adminQrError = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        body {
            background: #f6fafb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .dashboard-container {
            max-width: 1300px;
            margin: 20px auto;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 6px 24px rgba(61,141,122,0.10);
            overflow: hidden;
        }
        .top-navbar {
            background: linear-gradient(135deg, #3D8D7A, #2a6d5e);
            padding: 15px 30px;
            color: white;
        }
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white !important;
            text-decoration: none;
        }
        .nav-tabs {
            background: #f8f9fa;
            border-bottom: 2px solid #3D8D7A;
            padding: 0 30px;
        }
        .nav-tabs .nav-link {
            color: #2a6d5e;
            font-weight: 600;
            border: none;
            padding: 15px 25px;
            margin-right: 5px;
            border-radius: 0;
        }
        .nav-tabs .nav-link.active {
            background: #3D8D7A;
            color: white;
            border-bottom: 3px solid #2a6d5e;
        }
        .nav-tabs .nav-link:hover {
            background: rgba(61, 141, 122, 0.1);
            color: #3D8D7A;
        }
        .main-content {
            padding: 30px;
        }
        .stats-row {
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #fff, #f8f9fa);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid #e3e6e8;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #3D8D7A;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #5a7d76;
            font-weight: 600;
        }
        .content-section {
            display: none;
            animation: fadeIn 0.3s ease-in;
        }
        .content-section.active {
            display: block;
        }
        .qr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .qr-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e3e6e8;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        .qr-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.1);
        }
        .qr-image {
            width: 120px;
            height: 120px;
            margin: 0 auto 15px auto;
            border: 2px solid #A3D1C6;
            border-radius: 8px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .qr-image img {
            max-width: 100%;
            max-height: 100%;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        .user-table, .admin-table, .request-table {
            width: 100%;
            margin-top: 20px;
        }
        .user-table th, .admin-table th, .request-table th {
            background: #3D8D7A;
            color: white;
            padding: 12px;
            font-weight: 600;
        }
        .user-table td, .admin-table td, .request-table td {
            padding: 12px;
            border-bottom: 1px solid #e3e6e8;
            vertical-align: middle;
        }
        .user-table tbody tr:hover, .admin-table tbody tr:hover, .request-table tbody tr:hover {
            background: rgba(163, 209, 198, 0.1);
        }
        .btn-edit {
            background: #3D8D7A;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            margin-right: 5px;
            font-size: 0.85rem;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        .btn-assign {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            font-weight: 600;
        }
        .request-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #3D8D7A;
        }
        .logout-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 600;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (max-width: 768px) {
            .qr-grid {
                grid-template-columns: 1fr;
            }
            .main-content {
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Top Navbar -->
    <div class="top-navbar d-flex justify-content-between align-items-center">
        <a href="admin_dashboard.php" class="navbar-brand">Addwise Admin Dashboard</a>
        <div>
            <a href="view_profile_admin.php" class="btn btn-outline-light me-2"><i class="fas fa-user"></i> View Profile</a>
            <a href="edit_profile_admin.php" class="btn btn-outline-light me-2"><i class="fas fa-user-edit"></i> Edit Profile</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>
    </div>
    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab">
                <i class="fas fa-chart-bar me-2"></i>Dashboard
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="qrcodes-tab" data-bs-toggle="tab" data-bs-target="#qrcodes" type="button" role="tab">
                <i class="fas fa-qrcode me-2"></i>QR Codes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button" role="tab">
                <i class="fas fa-users me-2"></i>Users
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="admins-tab" data-bs-toggle="tab" data-bs-target="#admins" type="button" role="tab">
                <i class="fas fa-user-shield me-2"></i>Admins
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="gps-tab" data-bs-toggle="tab" data-bs-target="#gps" type="button" role="tab">
                <i class="fas fa-map-marker-alt me-2"></i>GPS Locations
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="generateqr-tab" data-bs-toggle="tab" data-bs-target="#generateqr" type="button" role="tab">
                <i class="fas fa-plus-square me-2"></i>Generate QR's
            </button>
        </li>
    </ul>

    <div class="main-content">
        <div class="tab-content" id="adminTabsContent">
            
            <!-- Dashboard Tab -->
            <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
                <h3 class="mb-4" style="color: #3D8D7A;">
                    <i class="fas fa-chart-bar me-2"></i>Dashboard Overview
                </h3>
                <div class="row stats-row">
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $totalUsers ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $totalAdmins ?></div>
                            <div class="stat-label">Total Admins</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $totalQRCodes ?></div>
                            <div class="stat-label">Total QR Codes</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6 mb-3">
                        <div class="stat-card">
                            <div class="stat-number"><?= $activeQRCodes ?></div>
                            <div class="stat-label">Active QRs</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- QR Codes Tab -->
            <div class="tab-pane fade" id="qrcodes" role="tabpanel">
                <h3 class="mb-4" style="color: #3D8D7A;">
                    <i class="fas fa-qrcode me-2"></i>QR Code Management
                </h3>
                <div class="qr-grid">
                    <?php foreach ($qrCodes as $qr): ?>
                        <div class="qr-card">
                            <div class="text-center mb-3">
                                <strong>QR Code: <?= htmlspecialchars($qr['code']) ?></strong>
                            </div>
                            <div class="qr-image">
    <img id="qr-img-<?= $qr['id'] ?>" src="data:image/png;base64,<?= $qr['image_data'] ?>" alt="QR Code">
</div>
<div class="d-flex justify-content-center gap-2 mt-2">
    <button class="btn btn-outline-primary btn-sm" onclick="printQRCode('qr-img-<?= $qr['id'] ?>')">
        <i class="fas fa-print"></i> Print
    </button>
    <button class="btn btn-outline-success btn-sm" onclick="downloadQRCode('qr-img-<?= $qr['id'] ?>', '<?= htmlspecialchars($qr['code']) ?>')">
        <i class="fas fa-download"></i> Download
    </button>
</div>
                            <div class="text-center mt-3">
                                <?php if ($qr['assigned_to']): ?>
                                    <div class="status-active mb-2">
                                        <i class="fas fa-check-circle me-1"></i>Active
                                    </div>
                                    <div class="text-muted">
                                        <strong>Assigned to:</strong><br>
                                        <?= htmlspecialchars($qr['assigned_user_name']) ?><br>
                                        <small><?= htmlspecialchars($qr['assigned_user_email']) ?></small>
                                    </div>
                                <?php else: ?>
                                    <div class="status-inactive">
                                        <i class="fas fa-times-circle me-1"></i>Inactive
                                    </div>
                                    <div class="text-muted mt-2">Not assigned to any user</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (empty($qrCodes)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-qrcode fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No QR Codes Available</h5>
                        <p class="text-muted">QR codes will appear here once created by Super Admin.</p>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Users Tab -->
            <div class="tab-pane fade" id="users" role="tabpanel">
                <h3 class="mb-4" style="color: #3D8D7A;">
                    <i class="fas fa-users me-2"></i>User Management
                </h3>
                <?php if (!empty($regularUsers)): ?>
                    <div class="table-responsive">
                        <table class="table user-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Verified</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($regularUsers as $user): ?>
    <tr>
        <td><?= htmlspecialchars($user['full_name']) ?></td>
        <td><?= htmlspecialchars($user['email']) ?></td>
        <td><?= htmlspecialchars($user['mobile']) ?></td>
        <td><?= htmlspecialchars($user['age']) ?></td>
        <td><?= htmlspecialchars($user['gender']) ?></td>
        <td>
            <span class="badge bg-<?= $user['is_verified'] ? 'success' : 'warning' ?>">
                <?= $user['is_verified'] ? 'Yes' : 'No' ?>
            </span>
        </td>
        <td>
            <div class="action-buttons">
            <button class="btn-edit" onclick="window.location.href='edit_user.php?id=<?= $user['id'] ?>'">
    <i class="fas fa-edit"></i> Edit
</button>

                <button class="btn-delete" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['full_name']) ?>')">
                    <i class="fas fa-trash"></i> Delete
                </button>
                <button class="btn-whatsapp" onclick="sendViaWhatsApp('+91<?= htmlspecialchars($user['mobile']) ?>', '<?= htmlspecialchars($user['full_name']) ?>')">
                    <i class="fab fa-whatsapp"></i> WhatsApp
                </button>
                <button class="btn-email" onclick="sendViaMail('<?= htmlspecialchars($user['email']) ?>', '<?= htmlspecialchars($user['full_name']) ?>', '<?= htmlspecialchars($user['mobile']) ?>', '<?= htmlspecialchars($user['age']) ?>', '<?= htmlspecialchars($user['gender']) ?>')">
                    <i class="fas fa-envelope"></i> Email
                </button>
            </div>
        </td>
    </tr>
<?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Users Found</h5>
                    </div>
                <?php endif; ?>
            </div>
            <!-- Admins Tab -->
            <div class="tab-pane fade" id="admins" role="tabpanel">
                <h3 class="mb-4" style="color: #3D8D7A;">
                    <i class="fas fa-user-shield me-2"></i>Admin Users (View Only)
                </h3>
                <?php if (!empty($admins)): ?>
                    <div class="table-responsive">
                        <table class="table admin-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Mobile</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Verified</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($admin['full_name']) ?></td>
                                        <td><?= htmlspecialchars($admin['email']) ?></td>
                                        <td><?= htmlspecialchars($admin['mobile']) ?></td>
                                        <td><?= htmlspecialchars($admin['age']) ?></td>
                                        <td><?= htmlspecialchars($admin['gender']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $admin['is_verified'] ? 'success' : 'warning' ?>">
                                                <?= $admin['is_verified'] ? 'Yes' : 'No' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">View Only</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-user-shield fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Other Admins Found</h5>
                    </div>
                <?php endif; ?>
            </div>
            <!-- GPS Tab -->
            <div class="tab-pane fade" id="gps" role="tabpanel">
                <h3 class="mb-4" style="color: #3D8D7A;">
                    <i class="fas fa-map-marker-alt me-2"></i>Live GPS Locations
                </h3>
                <!-- Filter Panel -->
                <div class="card mb-3">
                  <div class="card-body">
                    <form id="adminFilterForm" class="row g-3 align-items-end">
                      <div class="col-md-3">
                        <label for="adminQrSelect" class="form-label">QR Code</label>
                        <select class="form-select" id="adminQrSelect" name="qr_code" required>
                          <option value="">Select QR</option>
                          <?php
                          // Only show QR codes that have entries in qr_locations
                          $stmt = $pdo->query('SELECT DISTINCT q.code FROM qr_codes q INNER JOIN qr_locations l ON l.code = q.code ORDER BY q.code ASC');
                          while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            echo '<option value="' . htmlspecialchars($row['code']) . '">' . htmlspecialchars($row['code']) . '</option>';
                          }
                          ?>
                        </select>
                      </div>
                      <div class="col-md-3">
                        <label for="adminStartDatetime" class="form-label">Start Datetime</label>
                        <input type="datetime-local" class="form-control" id="adminStartDatetime" name="start" required>
                      </div>
                      <div class="col-md-3">
                        <label for="adminEndDatetime" class="form-label">End Datetime</label>
                        <input type="datetime-local" class="form-control" id="adminEndDatetime" name="end" required>
                      </div>
                      <div class="col-md-3">
                        <button type="submit" class="btn btn-success w-100"><i class="fas fa-search"></i> Filter Route</button>
                      </div>
                    </form>
                    <div class="mt-4" id="adminFilterMapContainer" style="display:none;">
                      <div id="adminFilterMap" style="height: 400px; width: 100%; border-radius: 12px;"></div>
                    </div>
                    <div id="adminFilterRouteInfo" class="mt-3"></div>
                  </div>
                </div>
                <!-- End Filter Panel -->
                <div id="gps-map" style="height: 450px; width: 100%; border-radius: 12px; margin-bottom: 24px;"></div>
                <div class="mb-3">
                    <form id="qr-search-form" class="d-flex align-items-center gap-2">
                        <input type="text" id="qr-search-input" class="form-control" placeholder="Enter 16-digit QR code" maxlength="16" minlength="16" pattern="[0-9]{16}" required style="max-width: 300px;" autocomplete="off">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Search</button>
                    </form>
                </div>
                <div id="qr-search-result" class="mt-3"></div>
                <div id="qr-route-map-container" style="display:none; margin-top:20px;">
                    <h5>QR Route Map</h5>
                    <div id="qr-route-map" style="height: 400px; width: 100%; border-radius: 12px;"></div>
                </div>
            </div>
            <!-- Generate QR's Tab -->
            <div class="tab-pane fade" id="generateqr" role="tabpanel">
                <h3 class="mb-4" style="color: #3D8D7A;">
                    <i class="fas fa-plus-square me-2"></i>Generate QR Codes
                </h3>
                <?php if ($adminQrSuccess): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($adminQrSuccess) ?></div>
                <?php endif; ?>
                <?php if ($adminQrError): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($adminQrError) ?></div>
                <?php endif; ?>
                <form method="post" class="qr-generator mb-4">
                    <input type="number" name="adminQrCount" min="1" max="100" required value="0" placeholder="Number">
                    <button type="submit" name="generate_admin_qr" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Generate QR Codes
                    </button>
                </form>
                <div class="alert alert-info">Generated QR codes will appear in the QR Codes tab.</div>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<section style="padding: 50px 20px; background: #f2fdfc; border-top: 3px solid #3D8D7A;">
    <div class="container text-center">
        <h2 style="color: #3D8D7A; font-weight: 700;" class="mb-4">
            <i class="fas fa-star me-2"></i>Why Choose Addwise?
        </h2>
        <div class="row justify-content-center g-4">
            <!-- Card 1 -->
            <div class="col-md-4">
                <div class="p-4 rounded shadow-sm bg-white h-100">
                    <i class="fas fa-lock fa-2x mb-3 text-success"></i>
                    <h5 class="fw-bold mb-2">Secure Admin Controls</h5>
                    <p class="text-muted">Role-based access ensures only verified admins can manage users and QR data.</p>
                </div>
            </div>
            <!-- Card 2 -->
            <div class="col-md-4">
                <div class="p-4 rounded shadow-sm bg-white h-100">
                    <i class="fas fa-qrcode fa-2x mb-3 text-info"></i>
                    <h5 class="fw-bold mb-2">Smart QR Management</h5>
                    <p class="text-muted">Generate, assign, and track QR codes efficiently for any user or request.</p>
                </div>
            </div>
            <!-- Card 3 -->
            <div class="col-md-4">
                <div class="p-4 rounded shadow-sm bg-white h-100">
                    <i class="fas fa-users fa-2x mb-3 text-primary"></i>
                    <h5 class="fw-bold mb-2">User & Admin Insights</h5>
                    <p class="text-muted">View user details, verification status, and manage admin privileges seamlessly.</p>
                </div>
            </div>
            <!-- Card 4 -->
            <div class="col-md-4">
                <div class="p-4 rounded shadow-sm bg-white h-100">
                    <i class="fas fa-clock fa-2x mb-3 text-warning"></i>
                    <h5 class="fw-bold mb-2">Real-Time Requests</h5>
                    <p class="text-muted">Monitor and approve QR code requests instantly with assignment tools built in.</p>
                </div>
            </div>
            <!-- Card 5 -->
            <div class="col-md-4">
                <div class="p-4 rounded shadow-sm bg-white h-100">
                    <i class="fas fa-chart-pie fa-2x mb-3 text-danger"></i>
                    <h5 class="fw-bold mb-2">Data Overview</h5>
                    <p class="text-muted">Visual dashboard stats show QR activity, user counts, and pending actions at a glance.</p>
                </div>
            </div>
        </div>
    </div>
</section>
<footer style="background-color: #3D8D7A; color: white; padding: 20px 30px; margin-top: 50px; border-top: 4px solid #2a6d5e;">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
        <p class="mb-2 mb-md-0">&copy; <?= date("Y") ?> Addwise Admin Panel. All rights reserved.</p>
        <p class="mb-0">Designed with ❤️ by k lakshmi pravallika</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function editUser(userId) {
    // Redirect to edit user page
    window.location.href = 'edit_user.php?id=' + userId;
}

function deleteUser(userId, userName) {
    if (confirm('Are you sure you want to delete user: ' + userName + '?')) {
        // Create form and submit
        var form = document.createElement('form');
        form.method = 'post';
        form.action = 'delete_user.php';
        
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'user_id';
        input.value = userId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

// Prevent back button
history.pushState(null, null, location.href);
window.onpopstate = function() { history.go(1); };
window.addEventListener('pageshow', function(event) {
    if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
        window.location.reload();
    }
});

function deleteUser(userId, userName) {
    if (confirm('Are you sure you want to delete user: ' + userName + '?')) {
        // Create form
        var form = document.createElement('form');
        form.method = 'post';
        form.action = 'delete_user.php';
        
        // User ID
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'user_id';
        input.value = userId;
        form.appendChild(input);
        
        // CSRF Token
        var csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = '<?= $_SESSION['csrf_token'] ?>';
        form.appendChild(csrf);
        
        document.body.appendChild(form);
        form.submit();
    }
}
function printQRCode(imgId) {
    var img = document.getElementById(imgId);
    if (!img) return;
    var win = window.open('', '_blank');
    win.document.write('<html><head><title>Print QR Code</title></head><body style="text-align:center;">');
    win.document.write('<img src="' + img.src + '" style="width:200px;height:200px;"/><br>');
    win.document.write('</body></html>');
    win.document.close();
    win.focus();
    win.print();
    setTimeout(function() { win.close(); }, 1000);
}

function downloadQRCode(imgId, code) {
    var img = document.getElementById(imgId);
    if (!img) return;
    var link = document.createElement('a');
    link.href = img.src;
    link.download = code + '_qrcode.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
function sendViaWhatsApp(phone, qrData) {
    // WhatsApp Web doesn't support direct image sending via URL.
    // Instead, send a message with a download link or instructions.
    // You could upload the QR to your server and use the URL, or instruct the admin to download and send manually.

    // For demonstration, we'll open WhatsApp with a prefilled message.
    let message = encodeURIComponent("Hello! Here is your QR code. Please contact admin to receive the image.");
    window.open(`https://wa.me/${phone}?text=${message}`, '_blank');
    // Optionally, show a modal with the QR code and download option.
}

function sendViaMail(email, qrData, name) {
    // Prepare an email with a subject and body
    let subject = encodeURIComponent("Your QR Code");
    let body = encodeURIComponent(`Hello ${name},\n\nPlease find your QR code attached.\n\n(You may need to download and attach the QR code manually.)`);
    window.location.href = `mailto:${email}?subject=${subject}&body=${body}`;

    // Optionally, show a modal with the QR code and a download button for manual attachment.
}

// --- GPS Locations Tab Logic ---
let gpsMap, gpsMarkers = [], gpsData = [], qrMarkerHighlight = null;

function fetchLiveLocations() {
    // AJAX to get all live-tracked users (with qr, user, and location info)
    return fetch('admin_gps_api.php?action=all')
        .then(res => res.json())
        .then(data => {
            gpsData = data;
            return data;
        });
}

function renderGpsMap() {
    if (!gpsMap) {
        gpsMap = L.map('gps-map').setView([20, 78], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(gpsMap);
    }
    // Remove old markers
    gpsMarkers.forEach(m => gpsMap.removeLayer(m));
    gpsMarkers = [];
    gpsData.forEach(item => {
        if (item.latitude && item.longitude) {
            const marker = L.marker([item.latitude, item.longitude]).addTo(gpsMap);
            marker.bindPopup(`<b>${item.full_name}</b><br>QR: ${item.code}<br>Lat: ${item.latitude}<br>Lng: ${item.longitude}`);
            marker.on('click', function() {
                if (item.code) {
                    fetchAndShowQRRoute(item.code);
                }
            });
            gpsMarkers.push(marker);
        }
    });
}

function focusOnQR(qrCode) {
    // Find the QR in gpsData
    const found = gpsData.find(item => (item.code && item.code.toString() === qrCode));
    const resultDiv = document.getElementById('qr-search-result');
    if (qrMarkerHighlight) {
        gpsMap.removeLayer(qrMarkerHighlight);
        qrMarkerHighlight = null;
    }
    if (found && found.latitude && found.longitude) {
        resultDiv.innerHTML = `<div class='alert alert-success'><b>User:</b> ${found.full_name}<br><b>QR:</b> ${found.code}<br><b>Latitude:</b> ${found.latitude}<br><b>Longitude:</b> ${found.longitude}</div>`;
        qrMarkerHighlight = L.marker([found.latitude, found.longitude], {icon: L.icon({iconUrl: 'https://cdn-icons-png.flaticon.com/512/684/684908.png', iconSize: [32, 32], iconAnchor: [16, 32]})}).addTo(gpsMap);
        gpsMap.setView([found.latitude, found.longitude], 15);
    } else {
        resultDiv.innerHTML = `<div class='alert alert-danger'>QR code not found or no live location.</div>`;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Only initialize when GPS tab is shown
    document.getElementById('gps-tab').addEventListener('shown.bs.tab', function() {
        fetchLiveLocations().then(() => {
            renderGpsMap();
        });
    });
    // QR search form
    document.getElementById('qr-search-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const qrCode = document.getElementById('qr-search-input').value;
        focusOnQR(qrCode);
    });
});

let qrRouteMap = null, qrRoutePolyline = null, qrRouteMarkers = [];

function showQRRouteMap(locations) {
    if (!qrRouteMap) {
        qrRouteMap = L.map('qr-route-map').setView([20, 78], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(qrRouteMap);
    }
    // Remove old polyline and markers
    if (qrRoutePolyline) qrRouteMap.removeLayer(qrRoutePolyline);
    qrRouteMarkers.forEach(m => qrRouteMap.removeLayer(m));
    qrRouteMarkers = [];
    if (!locations || locations.length === 0) return;
    const latlngs = locations.map(l => [parseFloat(l.latitude), parseFloat(l.longitude)]);
    qrRoutePolyline = L.polyline(latlngs, {color: 'blue', weight: 5}).addTo(qrRouteMap);
    // Fit map to route
    qrRouteMap.fitBounds(qrRoutePolyline.getBounds(), {padding: [30, 30]});
    // Add markers
    locations.forEach((loc, idx) => {
        const marker = L.marker([loc.latitude, loc.longitude]).addTo(qrRouteMap);
        marker.bindPopup(`<b>Location ${idx+1}</b><br>${loc.location_name || ''}<br>${loc.created_at}`);
        qrRouteMarkers.push(marker);
    });
}

function fetchAndShowQRRoute(qrCode) {
    fetch(`api/qr.php/${qrCode}`)
        .then(res => res.json())
        .then(data => {
            if (data.qr) {
                // Show details
                let html = `<div class='alert alert-info'><b>QR:</b> ${data.qr.code}<br>`;
                if (data.user) {
                    html += `<b>User:</b> ${data.user.full_name} (${data.user.email})<br>`;
                }
                html += `<b>Created:</b> ${data.qr.created_at}</div>`;
                document.getElementById('qr-search-result').innerHTML = html;
                // Show map
                document.getElementById('qr-route-map-container').style.display = 'block';
                showQRRouteMap(data.locations);
            } else {
                document.getElementById('qr-search-result').innerHTML = `<div class='alert alert-danger'>QR code not found.</div>`;
                document.getElementById('qr-route-map-container').style.display = 'none';
            }
        })
        .catch(() => {
            document.getElementById('qr-search-result').innerHTML = `<div class='alert alert-danger'>Error fetching QR details.</div>`;
            document.getElementById('qr-route-map-container').style.display = 'none';
        });
}

// Extend QR search form logic
const qrSearchForm = document.getElementById('qr-search-form');
if (qrSearchForm) {
    qrSearchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const qrCode = document.getElementById('qr-search-input').value;
        fetchAndShowQRRoute(qrCode);
    });
}

let adminFilterMap = null, adminFilterPolyline = null, adminFilterMarkers = [];

document.getElementById('adminFilterForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const code = document.getElementById('adminQrSelect').value;
  const start = document.getElementById('adminStartDatetime').value.replace('T', ' ') + ':00';
  const end = document.getElementById('adminEndDatetime').value.replace('T', ' ') + ':59';

  if (!code || !start || !end) return;

  fetch(`api/qr_filter.php?code=${encodeURIComponent(code)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`)
    .then(res => res.json())
    .then(data => {
      if (adminFilterMap) {
        adminFilterMap.remove();
        adminFilterMap = null;
        adminFilterPolyline = null;
        adminFilterMarkers = [];
      }
      document.getElementById('adminFilterMapContainer').style.display = 'block';
      adminFilterMap = L.map('adminFilterMap').setView([20, 78], 5);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19}).addTo(adminFilterMap);

      if (data.locations && data.locations.length > 0) {
        const latlngs = data.locations.map(l => [parseFloat(l.latitude), parseFloat(l.longitude)]);
        adminFilterPolyline = L.polyline(latlngs, {color: 'red', weight: 5}).addTo(adminFilterMap);
        adminFilterMap.fitBounds(adminFilterPolyline.getBounds(), {padding: [30, 30]});
        data.locations.forEach((loc, idx) => {
          const marker = L.marker([loc.latitude, loc.longitude]).addTo(adminFilterMap);
          marker.bindPopup(`<b>Location ${idx+1}</b><br>${loc.location_name || ''}<br>${loc.created_at}`);
          adminFilterMarkers.push(marker);
        });
        document.getElementById('adminFilterRouteInfo').innerHTML = `<div class='alert alert-info'><b>QR:</b> ${data.code}<br><b>Points:</b> ${data.locations.length}</div>`;
      } else {
        document.getElementById('adminFilterRouteInfo').innerHTML = '<div class="alert alert-warning">No route data found for this QR code in the selected period.</div>';
      }
      setTimeout(() => { if (adminFilterMap) adminFilterMap.invalidateSize(); }, 400);
    })
    .catch(() => {
      document.getElementById('adminFilterRouteInfo').innerHTML = '<div class="alert alert-danger">Error fetching route data.</div>';
    });
});
</script>
</body>
</html>
