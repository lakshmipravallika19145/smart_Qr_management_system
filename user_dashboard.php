<?php
session_start();

// Security check FIRST (before any output)
if (!isset($_SESSION['is_logged_in']) || $_SESSION['role'] !== 'User') {
    header("Location: index3.php");
    exit();
}

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

// User ID from session
$user_id = $_SESSION['user_id'];

// 🚨 QR DELETION HANDLER MUST COME HERE 🚨
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_qr_id'])) {
    $delete_id = intval($_POST['delete_qr_id']);
    $stmt = $pdo->prepare("DELETE FROM qr_codes WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$delete_id, $user_id]);
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

// Fetch user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch assigned QR codes with assignment date
$stmt = $pdo->prepare("
    SELECT q.*, r.requested_at AS assignment_date 
    FROM qr_codes q
    LEFT JOIN qr_requests r ON q.id = r.assigned_qr_id
    WHERE q.assigned_to = ?
");
$stmt->execute([$user_id]);
$qr_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Session timeout (30 minutes)
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: index3.php");
    exit();
}
$_SESSION['LAST_ACTIVITY'] = time();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --primary: #3D8D7A;
            --light: #B3D8A8;
            --bg: #FBFFE4;
            --accent: #A3D1C6;
            --white: #fff;
        }
        body {
            background: var(--bg);
            font-family: 'Segoe UI', 'Arial', sans-serif;
            min-height: 100vh;
            padding-top: 32px;
        }
        .dashboard-header {
            background: linear-gradient(90deg, var(--primary) 70%, var(--accent) 100%);
            color: var(--white);
            border-radius: 20px;
            padding: 32px 36px 24px 36px;
            margin-bottom: 36px;
            box-shadow: 0 8px 32px 0 rgba(61, 141, 122, 0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dashboard-header h1 {
            font-weight: 700;
            font-size: 2.1rem;
            letter-spacing: 1px;
            margin: 0;
        }
        .profile-actions .btn {
            margin-left: 10px;
            font-weight: 500;
            border-radius: 10px;
            padding: 8px 18px;
            font-size: 1rem;
            transition: background 0.2s, color 0.2s;
        }
        .btn-profile {
            background: var(--accent);
            color: var(--primary);
            border: none;
        }
        .btn-profile:hover {
            background: var(--light);
            color: var(--primary);
        }
        .btn-edit {
            background: var(--primary);
            color: var(--white);
            border: 2px solid var(--accent);
        }
        .btn-edit:hover {
            background: var(--accent);
            color: var(--primary);
        }
        .btn-logout {
            background: var(--white);
            color: var(--primary);
            border: 2px solid var(--primary);
        }
        .btn-logout:hover {
            background: var(--primary);
            color: var(--white);
        }
        .card {
            background: var(--white);
            border-radius: 18px;
            box-shadow: 0 6px 24px 0 rgba(61, 141, 122, 0.13);
            border: none;
            margin-bottom: 28px;
        }
        .card-header {
            background: var(--primary) !important;
            color: var(--white) !important;
            border-radius: 18px 18px 0 0 !important;
            font-weight: 600;
            font-size: 1.25rem;
            padding: 18px 24px;
            letter-spacing: 0.5px;
        }
        .card-body {
            background: var(--bg);
            border-radius: 0 0 18px 18px;
            padding: 36px 28px;
        }
        .qr-image {
            border: 2.5px solid var(--light);
            border-radius: 14px;
            padding: 14px;
            background: var(--white);
            max-width: 230px;
            box-shadow: 0 2px 12px 0 rgba(163, 209, 198, 0.18);
        }
        .alert-info, .alert-warning {
            background: var(--accent);
            color: var(--primary);
            border: none;
            border-radius: 10px;
            font-size: 1.07rem;
        }
        .alert-info i, .alert-warning i {
            color: var(--primary);
        }
        @media (max-width: 767px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                padding: 18px 10px;
            }
            .dashboard-header h1 {
                font-size: 1.3rem;
            }
     .profile-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: nowrap; /* Prevent wrapping */
    flex-shrink: 0;
}

            .card-body {
                padding: 22px 10px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="dashboard-header">
        <h1>
            <i class="fas fa-user-circle me-2"></i> 
            Welcome, <?php echo htmlspecialchars($user['full_name']); ?>
        </h1>
        <div class="profile-actions">
            <a href="view_profile_users.php" class="btn btn-profile">
                <i class="fas fa-id-badge me-1"></i> View Profile
            </a>
            <a href="edit_profile_users.php" class="btn btn-edit">
                <i class="fas fa-user-edit me-1"></i> Edit Profile
            </a>
           
<a href="logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>


        </div>
    </div>
<?php if (isset($_SESSION['message'])): ?>
    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
    <?php unset($_SESSION['message']); ?>
<?php elseif (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!--add devices -->
<div class="card p-2 mb-2">
    <div class="d-flex justify-content-center">
        <div class="btn-group" role="group">
            <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
                <i class="fas fa-camera me-1"></i> Add Device
            </a>
            <a href="#" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                <i class="fas fa-upload me-1"></i> Upload QR File
            </a>
        </div>
    </div>
</div>
<!-- Manual QR Code Input -->

<div class="card p-3 mb-3">
    <h5>Enter QR Code Manually</h5>
    <form id="manualQrForm">
        <div class="input-group">
            <input type="text" id="manual_qr" name="manual_qr" class="form-control" placeholder="Enter 16-digit QR code" maxlength="16" required pattern="\d{16}">
            <button type="submit" class="btn btn-primary">Add Device</button>
        </div>
    </form>
</div>
   <!-- QR Code Section -->
<div class="card">
    <div class="card-header">
        <i class="fas fa-qrcode me-2"></i> Your QR Code
    </div>
    <div class="card-body">
        <?php if (!empty($qr_codes)): ?>
            <?php foreach ($qr_codes as $qr): ?>
                <div class="qr-box mb-4 p-3 border rounded">
                    <div class="row align-items-center">
                        <div class="col-md-4 text-center">
                            <?php if (!empty($qr['image_data'])): ?>
                                <img src="data:image/png;base64,<?= $qr['image_data'] ?>" 
                                     class="img-fluid qr-image" alt="QR Code">
                            <?php else: ?>
                                <div class="alert alert-warning p-2">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    QR image missing
                                </div>
                        <?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <h5>QR Code Details</h5>
                            <ul class="list-unstyled">
                                <li><strong>Code:</strong> <?= htmlspecialchars($qr['code']) ?></li>
                                <?php if ($qr['created_at']): ?>
                                    <li><strong>Assigned:</strong> <?= date('M d, Y h:i A', strtotime($qr['created_at'])) ?></li>
                                <?php else: ?>
                                    <li><strong>Assigned:</strong> Not available</li>
                                <?php endif; ?>
                                <li id="latlng-<?= $qr['id'] ?>" class="text-primary small"></li>
                            </ul>
                            <!-- Location Section -->
                            <div class="mb-2">
                                <div class="d-flex gap-2 mb-2">
                                    <button class="btn btn-outline-success btn-sm" onclick="showLiveLocationModal(<?= $qr['id'] ?>)"><i class="fas fa-location-arrow me-1"></i> Start Live</button>
                                    <button class="btn btn-outline-primary btn-sm" onclick="showManualLocationModal(<?= $qr['id'] ?>)"><i class="fas fa-map-marker-alt me-1"></i> Add Manually</button>
                                    <button class="btn btn-outline-danger btn-sm" onclick="stopLocation(<?= $qr['id'] ?>)"><i class="fas fa-stop me-1"></i> Stop</button>
                                </div>
                                <div id="location-status-<?= $qr['id'] ?>" class="text-muted small"></div>
                            </div>
                            <!-- Button Row -->
                            <div class="d-flex gap-2 mt-3">
                                <!-- Route Button -->
                                <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#routeModal" onclick="showRouteModal('<?= htmlspecialchars($qr['code']) ?>')">
                                    <i class="fas fa-route me-1"></i> Route
                                </button>
                                <!-- Delete Button -->
                                <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this QR code?');" class="d-inline">
                                    <input type="hidden" name="delete_qr_id" value="<?= htmlspecialchars($qr['id']) ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No QR codes assigned yet. Scan a QR code to get started.
            </div>
      
        <?php endif; ?>
    </div>
</div>

<!-- Add Device Modal -->
<div class="modal fade" id="addDeviceModal" tabindex="-1" aria-labelledby="addDeviceModalLabel">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDeviceModalLabel">
                    <i class="fas fa-qrcode me-2"></i>Scan QR Code
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qr-reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
                <p class="mt-3 text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Position the QR code within the scanning area
                </p>
            </div>
    
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Upload QR File Modal -->
<div class="modal fade" id="uploadFileModal" tabindex="-1" aria-labelledby="uploadFileModalLabel">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="upload_qr_file.php" method="POST" enctype="multipart/form-data">
        <div class="modal-header">
          <h5 class="modal-title" id="uploadFileModalLabel">Upload QR Image</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <input type="file" name="qr_image" class="form-control" accept="image/*" required>
          <p class="text-muted mt-2"><i class="fas fa-info-circle me-1"></i>Select a QR image file (PNG, JPG, etc.)</p>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Upload & Assign</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Manual Location Modal -->
<div class="modal fade" id="manualLocationModal" tabindex="-1" aria-labelledby="manualLocationModalLabel">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="manualLocationForm">
        <div class="modal-header">
          <h5 class="modal-title" id="manualLocationModalLabel">Add Location Manually</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="manual_qr_id" name="qr_id">
          <div class="mb-3">
            <label for="manual_latitude" class="form-label">Latitude</label>
            <input type="number" step="any" class="form-control" id="manual_latitude" name="latitude" required>
          </div>
          <div class="mb-3">
            <label for="manual_longitude" class="form-label">Longitude</label>
            <input type="number" step="any" class="form-control" id="manual_longitude" name="longitude" required>
          </div>
          <div class="mb-3">
            <label for="manual_location_name" class="form-label">Location Name (optional)</label>
            <input type="text" class="form-control" id="manual_location_name" name="location_name">
          </div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save Location</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Live Location Modal -->
<div class="modal fade" id="liveLocationModal" tabindex="-1" aria-labelledby="liveLocationModalLabel">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="liveLocationModalLabel">Live Location Tracking</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" onclick="stopLiveLocationModal()"></button>
      </div>
      <div class="modal-body">
        <div id="live-map" style="height: 350px; width: 100%; border-radius: 10px;"></div>
        <div class="mt-3">
          <span class="fw-bold">Latitude:</span> <span id="live-lat"></span>
          <span class="fw-bold ms-4">Longitude:</span> <span id="live-lng"></span>
        </div>
        <div id="live-location-status" class="text-muted mt-2"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="stopLiveLocationModal()">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Route Modal -->
<div class="modal fade" id="routeModal" tabindex="-1" aria-labelledby="routeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="routeModalLabel"><i class="fas fa-route me-2"></i>QR Route</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="route-map" style="height: 400px; width: 100%; border-radius: 12px;"></div>
        <div id="route-info" class="mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Filter Button -->
<button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#filterModal">
  <i class="fas fa-filter"></i> Filter Route
</button>
<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form id="filterForm">
        <div class="modal-header">
          <h5 class="modal-title" id="filterModalLabel"><i class="fas fa-filter me-2"></i>Filter QR Route</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-4">
              <label for="qrSelect" class="form-label">QR Code</label>
              <select class="form-select" id="qrSelect" name="qr_code" required>
                <option value="">Select QR</option>
                <?php
                // Only show QR codes assigned to this user that have entries in qr_locations
                $userId = $_SESSION['user_id'];
                $stmt = $pdo->prepare('SELECT q.code FROM qr_codes q WHERE q.assigned_to = ? AND EXISTS (SELECT 1 FROM qr_locations l WHERE l.code = q.code) ORDER BY q.code ASC');
                $stmt->execute([$userId]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo '<option value="' . htmlspecialchars($row['code']) . '">' . htmlspecialchars($row['code']) . '</option>';
                }
                ?>
              </select>
            </div>
            <div class="col-md-4">
              <label for="startDatetime" class="form-label">Start Datetime</label>
              <input type="datetime-local" class="form-control" id="startDatetime" name="start" required>
            </div>
            <div class="col-md-4">
              <label for="endDatetime" class="form-label">End Datetime</label>
              <input type="datetime-local" class="form-control" id="endDatetime" name="end" required>
            </div>
          </div>
          <div class="mt-4" id="filterMapContainer" style="display:none;">
            <div id="filterMap" style="height: 400px; width: 100%; border-radius: 12px;"></div>
          </div>
          <div id="filterRouteInfo" class="mt-3"></div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success"><i class="fas fa-search"></i> Apply Filter</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </form>
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
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let qrScanner;

function startCamera() {
    const qrRegionId = "qr-reader";
    
    // Remove aria-hidden when scanner starts
    const modal = document.getElementById('addDeviceModal');
    modal.removeAttribute('aria-hidden');
    
    qrScanner = new Html5Qrcode(qrRegionId);

    qrScanner.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: { width: 300, height: 300 }
        },
        (decodedText) => {
            qrScanner.stop();
            document.getElementById(qrRegionId).innerHTML = "";

            // Extract numeric code
            let qrCodeValue = decodedText;
            const codeMatch = decodedText.match(/\d{16}/);
            if (codeMatch) {
                qrCodeValue = codeMatch[0];
            }

            // Send to backend
            fetch("add_device.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "qr_code=" + encodeURIComponent(qrCodeValue)
            })
            .then(res => res.text())
            .then(response => {
                if (response.startsWith("SUCCESS:")) {
                    const qrCode = response.split(":")[1];
                    alert(`Device assigned successfully!\nQR Code: ${qrCode}`);
                    
                    // Close modal and restore aria-hidden
                    const modal = bootstrap.Modal.getInstance(document.getElementById('addDeviceModal'));
                    modal.hide();
                    
                    location.reload();
                } else {
                    alert(response);
                }
            })
            .catch(error => {
                alert("Error: " + error);
            });
        },
        (errorMessage) => {
            // Optional error handling
        }
    ).catch(err => {
        alert("Camera error: " + err);
        // Restore aria-hidden on error
        modal.setAttribute('aria-hidden', 'true');
    });
}

function stopCamera() {
    if (qrScanner) {
        qrScanner.stop().then(() => {
            document.getElementById("qr-reader").innerHTML = "";
            
            // Restore aria-hidden when camera stops
            const modal = document.getElementById('addDeviceModal');
            modal.setAttribute('aria-hidden', 'true');
        }).catch(e => {
            console.error("Stop error:", e);
        });
    }
}

// Modal event listeners with proper accessibility handling
document.getElementById('addDeviceModal').addEventListener('shown.bs.modal', function() {
    // Modal is now visible, remove aria-hidden
    this.removeAttribute('aria-hidden');
    startCamera();
});

document.getElementById('addDeviceModal').addEventListener('hidden.bs.modal', function() {
    // Modal is now hidden, restore aria-hidden
    this.setAttribute('aria-hidden', 'true');
    stopCamera();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.getElementById('manualQrForm').addEventListener('submit', function (e) {
    e.preventDefault();

    const qrCode = document.getElementById('manual_qr').value;

    fetch('manual_qr_add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'qr_code=' + encodeURIComponent(qrCode)
    })
    .then(response => response.json())
    .then(data => {
        Swal.fire({
            icon: data.success ? 'success' : 'error',
            title: data.success ? 'Success' : 'Failed',
            text: data.message,
        }).then(() => {
            if (data.success) {
                location.reload();
            }
        });
    });
});
</script>

<!-- Leaflet JS & CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const qrLatLng = {};
const liveTrackingState = JSON.parse(localStorage.getItem('liveTrackingState') || '{}');
let liveLocationWatchId = null;
let liveLocationQrId = null;
let liveMap = null;
let liveMarker = null;
let routeMap = null, routePolyline = null, routeMarkers = [];
let filterMap = null, filterPolyline = null, filterMarkers = [];

// Fetch and render location for each QR
<?php foreach ($qr_codes as $qr): ?>
(function() {
    const qrId = <?= $qr['id'] ?>;
    fetch('qr_location_api.php?action=get&qr_id=' + qrId)
        .then(res => res.json())
        .then(data => {
            if (data.latitude && data.longitude) {
                qrLatLng[qrId] = {lat: data.latitude, lng: data.longitude};
                document.getElementById('latlng-' + qrId).innerHTML = `<b>Lat:</b> ${data.latitude} <b>Lng:</b> ${data.longitude}`;
                document.getElementById('location-status-' + qrId).innerText = 'Last updated: ' + (data.updated_at || 'N/A');
            } else {
                document.getElementById('latlng-' + qrId).innerHTML = '';
                document.getElementById('location-status-' + qrId).innerText = 'No location set.';
            }
        });
    // Auto-restart live tracking if it was active before refresh
    if (liveTrackingState[qrId]) {
        setTimeout(() => showLiveLocationModal(qrId), 500);
    }
})();
<?php endforeach; ?>

function showLiveLocationModal(qrId) {
    liveLocationQrId = qrId;
    const modal = new bootstrap.Modal(document.getElementById('liveLocationModal'));
    modal.show();
    setTimeout(() => startLiveLocationTracking(qrId), 400); // Wait for modal to render
    // Mark as tracking in localStorage
    liveTrackingState[qrId] = true;
    localStorage.setItem('liveTrackingState', JSON.stringify(liveTrackingState));
}

function stopLiveLocationModal() {
    if (liveLocationWatchId !== null) {
        navigator.geolocation.clearWatch(liveLocationWatchId);
        liveLocationWatchId = null;
    }
    if (liveMap) {
        liveMap.remove();
        liveMap = null;
        liveMarker = null;
    }
    document.getElementById('live-location-status').innerText = '';
    // Remove tracking state
    if (liveLocationQrId) {
        delete liveTrackingState[liveLocationQrId];
        localStorage.setItem('liveTrackingState', JSON.stringify(liveTrackingState));
    }
}

function startLiveLocationTracking(qrId) {
    if (!navigator.geolocation) {
        document.getElementById('live-location-status').innerText = 'Geolocation is not supported by your browser.';
        return;
    }
    document.getElementById('live-location-status').innerText = 'Tracking live location...';
    // Setup map
    if (!liveMap) {
        liveMap = L.map('live-map').setView([20, 78], 4);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(liveMap);
    }
    // Watch position every 15 seconds
    let lastUpdate = 0;
    liveLocationWatchId = navigator.geolocation.watchPosition(function(position) {
        const now = Date.now();
        if (now - lastUpdate < 14000) return; // Only update every 15s
        lastUpdate = now;
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        document.getElementById('live-lat').innerText = lat;
        document.getElementById('live-lng').innerText = lng;
        document.getElementById('live-location-status').innerText = 'Location updated!';
        // Update map
        if (liveMarker) liveMap.removeLayer(liveMarker);
        liveMarker = L.marker([lat, lng]).addTo(liveMap);
        liveMap.setView([lat, lng], 15);
        // Send to backend
        updateLocation(qrId, lat, lng, 'Live Location', true);
        // Also update dashboard coordinates
        document.getElementById('latlng-' + qrId).innerHTML = `<b>Lat:</b> ${lat} <b>Lng:</b> ${lng}`;
    }, function() {
        document.getElementById('live-location-status').innerText = 'Unable to retrieve your location.';
    }, { enableHighAccuracy: true, maximumAge: 0, timeout: 10000 });
}

// Manual location modal (unchanged)
function showManualLocationModal(qrId) {
    document.getElementById('manual_qr_id').value = qrId;
    const modal = new bootstrap.Modal(document.getElementById('manualLocationModal'));
    modal.show();
}

document.getElementById('manualLocationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const qrId = document.getElementById('manual_qr_id').value;
    const lat = document.getElementById('manual_latitude').value;
    const lng = document.getElementById('manual_longitude').value;
    const name = document.getElementById('manual_location_name').value;
    updateLocation(qrId, lat, lng, name);
    bootstrap.Modal.getInstance(document.getElementById('manualLocationModal')).hide();
});

// Update location via API
function updateLocation(qrId, lat, lng, name, isLive = false) {
    fetch('qr_location_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=update&qr_id=' + encodeURIComponent(qrId) + '&latitude=' + encodeURIComponent(lat) + '&longitude=' + encodeURIComponent(lng) + '&location_name=' + encodeURIComponent(name)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('location-status-' + qrId).innerText = 'Location updated!';
            document.getElementById('latlng-' + qrId).innerHTML = `<b>Lat:</b> ${lat} <b>Lng:</b> ${lng}`;
            if (qrMaps[qrId]) {
                if (qrMarkers[qrId]) qrMaps[qrId].removeLayer(qrMarkers[qrId]);
                const marker = L.marker([lat, lng]).addTo(qrMaps[qrId]);
                marker.bindPopup(name || 'Current Location').openPopup();
                qrMarkers[qrId] = marker;
                qrMaps[qrId].setView([lat, lng], 15);
            }
            if (!isLive && liveMap && liveMarker) {
                liveMap.setView([lat, lng], 15);
                liveMarker.setLatLng([lat, lng]);
            }
        } else {
            alert('Failed to update location: ' + data.message);
        }
    });
}

// Stop location tracking
function stopLocation(qrId) {
    fetch('qr_location_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=stop&qr_id=' + encodeURIComponent(qrId)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('location-status-' + qrId).innerText = 'Location tracking stopped.';
            document.getElementById('latlng-' + qrId).innerHTML = '';
            if (qrMaps[qrId] && qrMarkers[qrId]) {
                qrMaps[qrId].removeLayer(qrMarkers[qrId]);
                qrMarkers[qrId] = null;
            }
        } else {
            alert('Failed to stop location: ' + data.message);
        }
    });
}

function showRouteModal(qrCode) {
    // Reset modal content
    document.getElementById('route-info').innerHTML = '<div class="text-muted">Loading route...</div>';
    // Remove and re-create the map container to avoid Leaflet duplicate map error
    const mapContainer = document.getElementById('route-map');
    if (routeMap) {
        routeMap.remove();
        routeMap = null;
        routePolyline = null;
        routeMarkers = [];
    }
    // Force re-create the map container
    mapContainer.innerHTML = '';
    mapContainer.style.height = '400px';
    mapContainer.style.width = '100%';
    // Wait for modal to be fully shown before initializing map
    const modalEl = document.getElementById('routeModal');
    const onShown = function() {
        routeMap = L.map('route-map').setView([20, 78], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
        }).addTo(routeMap);
        // Fetch route data
        fetch(`api/qr.php/${encodeURIComponent(qrCode)}`)
            .then(res => res.json())
            .then(data => {
                if (data.qr && Array.isArray(data.locations) && data.locations.length > 0) {
                    const latlngs = data.locations.map(l => [parseFloat(l.latitude), parseFloat(l.longitude)]);
                    routePolyline = L.polyline(latlngs, {color: 'blue', weight: 5}).addTo(routeMap);
                    routeMap.fitBounds(routePolyline.getBounds(), {padding: [30, 30]});
                    data.locations.forEach((loc, idx) => {
                        const marker = L.marker([loc.latitude, loc.longitude]).addTo(routeMap);
                        marker.bindPopup(`<b>Location ${idx+1}</b><br>${loc.location_name || ''}<br>${loc.created_at}`);
                        routeMarkers.push(marker);
                    });
                    document.getElementById('route-info').innerHTML = `<div class='alert alert-info'><b>QR:</b> ${data.qr.code}<br><b>Points:</b> ${data.locations.length}</div>`;
                } else {
                    document.getElementById('route-info').innerHTML = '<div class="alert alert-warning">No route data found for this QR code.</div>';
                }
            })
            .catch(() => {
                document.getElementById('route-info').innerHTML = '<div class="alert alert-danger">Error fetching route data.</div>';
            });
        // Remove event listener after running once
        modalEl.removeEventListener('shown.bs.modal', onShown);
        // Fix map resize after modal shown
        setTimeout(() => { if (routeMap) routeMap.invalidateSize(); }, 400);
    };
    modalEl.addEventListener('shown.bs.modal', onShown);
}

document.getElementById('filterForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const code = document.getElementById('qrSelect').value;
  const start = document.getElementById('startDatetime').value.replace('T', ' ') + ':00';
  const end = document.getElementById('endDatetime').value.replace('T', ' ') + ':59';

  if (!code || !start || !end) return;

  fetch(`api/qr_filter.php?code=${encodeURIComponent(code)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`)
    .then(res => res.json())
    .then(data => {
      if (filterMap) {
        filterMap.remove();
        filterMap = null;
        filterPolyline = null;
        filterMarkers = [];
      }
      document.getElementById('filterMapContainer').style.display = 'block';
      filterMap = L.map('filterMap').setView([20, 78], 5);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19}).addTo(filterMap);

      if (data.locations && data.locations.length > 0) {
        const latlngs = data.locations.map(l => [parseFloat(l.latitude), parseFloat(l.longitude)]);
        filterPolyline = L.polyline(latlngs, {color: 'red', weight: 5}).addTo(filterMap);
        filterMap.fitBounds(filterPolyline.getBounds(), {padding: [30, 30]});
        data.locations.forEach((loc, idx) => {
          const marker = L.marker([loc.latitude, loc.longitude]).addTo(filterMap);
          marker.bindPopup(`<b>Location ${idx+1}</b><br>${loc.location_name || ''}<br>${loc.created_at}`);
          filterMarkers.push(marker);
        });
        document.getElementById('filterRouteInfo').innerHTML = `<div class='alert alert-info'><b>QR:</b> ${data.code}<br><b>Points:</b> ${data.locations.length}</div>`;
      } else {
        document.getElementById('filterRouteInfo').innerHTML = '<div class="alert alert-warning">No route data found for this QR code in the selected period.</div>';
      }
      setTimeout(() => { if (filterMap) filterMap.invalidateSize(); }, 400);
    })
    .catch(() => {
      document.getElementById('filterRouteInfo').innerHTML = '<div class="alert alert-danger">Error fetching route data.</div>';
    });
});

document.getElementById('filterModal').addEventListener('hidden.bs.modal', function() {
  if (filterMap) {
    filterMap.remove();
    filterMap = null;
    filterPolyline = null;
    filterMarkers = [];
  }
  document.getElementById('filterMapContainer').style.display = 'none';
  document.getElementById('filterRouteInfo').innerHTML = '';
});
</script>

</body>
</html>
