<?php
session_start();

// Prevent direct access without coming from registration
if (!isset($_SESSION['registration_in_progress'])) {
    header("Location: index3.php");
    exit();
}

// Prevent returning after OTP verification
if (isset($_SESSION['otp_verified'])) {
    header("Location: index3.php");
    exit();
}

// Prevent caching of this page
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['email'])) {
    echo "No email found in session.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Verify OTP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary: #3D8D7A;
            --secondary: #B3D8A8;
            --accent: #FBFFE4;
            --teal: #A3D1C6;
            --dark: #2a6d5e;
        }
        body {
            margin: 0;
            background: linear-gradient(135deg, var(--secondary), var(--teal), var(--accent));
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .otp-container {
            background: rgba(251, 255, 228, 0.92);
            backdrop-filter: blur(10px);
            border-radius: 22px;
            padding: 40px 35px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 12px 30px rgba(61, 141, 122, 0.25);
            border: 1px solid rgba(163, 209, 198, 0.5);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .otp-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(61, 141, 122, 0.3);
        }
        .otp-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .otp-header h2 {
            color: var(--primary);
            font-weight: 700;
            font-size: 2.1rem;
            letter-spacing: 0.5px;
        }
        .email-display {
            background: linear-gradient(45deg, var(--primary), var(--dark));
            color: white;
            padding: 8px 25px;
            border-radius: 50px;
            font-size: 16px;
            display: inline-block;
            margin: 15px 0 25px;
            box-shadow: 0 4px 15px rgba(61, 141, 122, 0.2);
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-label {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 16px;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.8);
            border: 2px solid var(--teal);
            border-radius: 12px;
            padding: 14px 20px;
            color: var(--dark);
            font-size: 16px;
            transition: all 0.3s;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.05);
            text-align: center;
            letter-spacing: 5px;
            font-weight: bold;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.95);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.2);
            outline: none;
        }
        .btn-verify {
            background: linear-gradient(45deg, var(--primary), var(--dark));
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            box-shadow: 0 6px 15px rgba(61, 141, 122, 0.3);
            width: 100%;
        }
        .btn-verify:hover {
            background: linear-gradient(45deg, var(--dark), var(--primary));
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(61, 141, 122, 0.4);
        }
        .back-link {
            display: block;
            text-align: center;
            color: var(--primary);
            font-weight: 600;
            margin-top: 20px;
            text-decoration: none;
            transition: color 0.3s;
        }
        .back-link:hover {
            color: var(--dark);
            text-decoration: underline;
        }
        .alert {
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 25px;
        }
        .otp-instructions {
            color: #5d8a7f;
            text-align: center;
            margin-bottom: 25px;
            font-size: 15px;
        }
        @media (max-width: 576px) {
            .otp-container {
                padding: 30px 25px;
            }
            .otp-header h2 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="otp-container">
        <div class="otp-header">
            <h2>OTP Verification</h2>
            <div class="email-display"><?= htmlspecialchars($_SESSION['email']) ?></div>
        </div>
        
        <?php if (isset($_SESSION['otp_success'])): ?>
            <div class="alert alert-success text-center" role="alert">
                ✅ <?= $_SESSION['otp_success']; unset($_SESSION['otp_success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['otp_error'])): ?>
            <div class="alert alert-danger text-center" role="alert">
                ❌ <?= $_SESSION['otp_error']; unset($_SESSION['otp_error']); ?>
            </div>
        <?php endif; ?>
        
        <p class="otp-instructions">Enter the 6-digit OTP sent to your email address</p>
        
        <form action="check_otp.php" method="POST">
            <div class="form-group">
                <label class="form-label">OTP Code</label>
                <input type="text" name="otp_input" class="form-control" 
                       placeholder="••••••" maxlength="6" required pattern="\d{6}" />
            </div>
            
            <button type="submit" class="btn btn-verify">Verify OTP</button>
            
            <a href="index3.php" class="back-link">← Back to Login</a>
        </form>
    </div>

    <script>
        history.pushState(null, null, location.href);
        window.onpopstate = function() {
            history.go(1);
        };
        
        window.addEventListener('pageshow', function(event) {
            if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
                window.location.reload();
            }
        });

        // Auto-focus on OTP input
        document.querySelector('input[name="otp_input"]').focus();
        
        // Auto-advance to next input (if using multiple inputs)
        const inputs = document.querySelectorAll('input[type="text"]');
        inputs.forEach((input, index) => {
            input.addEventListener('input', function() {
                if (this.value.length === 1 && index < inputs.length - 1) {
                    inputs[index + 1].focus();
                }
            });
        });
    </script>
    
    <?php if (isset($_SESSION['show_alert'])): ?>
    <script>
        alert('<?= addslashes($_SESSION['show_alert']) ?>');
        window.location.href = 'index3.php';
    </script>
    <?php unset($_SESSION['show_alert']); ?>
    <?php endif; ?>
</body>
</html>
