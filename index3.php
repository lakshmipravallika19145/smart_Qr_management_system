<?php
session_start();

if (isset($_SESSION['otp_verified'])) {
    unset($_SESSION['otp_verified']);
}
unset(
    $_SESSION['reset_email'],
    $_SESSION['reset_otp'],
    $_SESSION['reset_otp_verified']
);
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (isset($_SESSION['is_logged_in'])) {
    if ($_SESSION['role'] === 'Admin') {
        header("Location: admin_dashboard.php");
    } else if ($_SESSION['role'] === 'User') {
        header("Location: user_dashboard.php");
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Bootstrap Auth Page</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script src="https://accounts.google.com/gsi/client" async defer></script>
  <style>
    :root {
      --primary: #3D8D7A;
      --secondary: #B3D8A8;
      --accent: #FBFFE4;
      --teal: #A3D1C6;
      --dark: #2a6d5e;
    }
    body {
      background: linear-gradient(135deg, var(--primary), var(--teal), var(--accent));
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .glass {
      background: rgba(251, 255, 228, 0.92);
      backdrop-filter: blur(10px);
      border-radius: 22px;
      box-shadow: 0 12px 30px rgba(61, 141, 122, 0.25);
      border: 1px solid rgba(163, 209, 198, 0.5);
      padding: 40px 35px;
      margin: 0 auto;
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .glass:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 35px rgba(61, 141, 122, 0.3);
    }
    .fw-bold {
      color: var(--primary);
      font-size: 2rem;
      letter-spacing: 1px;
      margin-bottom: 10px;
    }
    .toggle-btn {
      color: var(--primary);
      font-weight: 600;
      font-size: 1.1rem;
      transition: color 0.2s;
    }
    .toggle-btn:hover {
      color: var(--dark);
      text-decoration: underline;
    }
    .form-label {
      color: var(--dark);
      font-weight: 600;
      margin-bottom: 7px;
      font-size: 15px;
    }
    .form-control {
      background: rgba(255, 255, 255, 0.8);
      border: 2px solid var(--teal);
      border-radius: 12px;
      padding: 13px 18px;
      color: var(--dark);
      font-size: 16px;
      transition: all 0.3s;
      box-shadow: inset 0 2px 5px rgba(0,0,0,0.05);
    }
    .form-control:focus {
      background: rgba(255, 255, 255, 0.95);
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(61, 141, 122, 0.15);
      outline: none;
    }
    .btn-3d, .btn-primary {
      background: linear-gradient(45deg, var(--primary), var(--dark));
      border: none;
      border-radius: 12px;
      padding: 14px;
      font-size: 18px;
      font-weight: 600;
      letter-spacing: 0.5px;
      transition: all 0.3s;
      box-shadow: 0 6px 15px rgba(61, 141, 122, 0.2);
      color: #fff;
    }
    .btn-3d:hover, .btn-primary:hover {
      background: linear-gradient(45deg, var(--dark), var(--primary));
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(61, 141, 122, 0.3);
      color: #fff;
    }
    
    .forgot-password {
      color: var(--primary);
      font-weight: 500;
      font-size: 15px;
      text-decoration: none;
      transition: color 0.2s;
    }
    .forgot-password:hover {
      color: var(--dark);
      text-decoration: underline;
    }
    #admin-secret-box {
      background: rgba(163, 209, 198, 0.12);
      border-radius: 12px;
      padding: 12px;
      margin-bottom: 15px;
      border-left: 4px solid var(--primary);
    }
    @media (max-width: 991px) {
      .glass {
        padding: 30px 10px;
      }
    }
    @media (max-width: 600px) {
      .glass {
        padding: 20px 5px;
      }
      .fw-bold {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center py-5">
<!-- Home icon top-right -->
<a href="home.php" id="home-icon" style="position: fixed; top: 24px; right: 32px; z-index: 1000; text-decoration: none;">
  <i class="bi bi-house-door-fill" style="font-size: 2.2rem; color: #3D8D7A; background: rgba(251,255,228,0.92); border-radius: 50%; padding: 8px; box-shadow: 0 2px 8px rgba(61,141,122,0.12);"></i>
</a>

  <div class="container glass col-md-8 col-lg-6">
    <div class="row">
      <div class="col-md-5 text-center mb-4 mb-md-0 d-flex flex-column justify-content-center">
        <h2 class="fw-bold">Welcome!</h2>
        <p>Join us for Exploring the world that you have ever seen</p>
        <div>
          <span class="toggle-btn me-3" id="show-login" style="cursor: pointer;">Login</span> | 
          <span class="toggle-btn ms-3" id="show-signup" style="cursor: pointer;">Signup</span>
        </div>
      </div>
      <div class="col-md-7">
        <form id="login-form" action="login.php" method="POST">
          <h4 class="mb-3">Login</h4>
          <div class="mb-3">
            <select class="form-select" name="role" required>
              <option value="">Select Role</option>
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="mb-3">
            <input type="email" class="form-control" name="email" placeholder="Email" required
              pattern="[a-zA-Z0-9._%+-]+@gmail\.com$" 
              title="Email must be in the format: example@gmail.com" />
          </div>
          <div class="mb-3">
            <input type="password" class="form-control" name="password" placeholder="Password" required
              pattern="^(?=[A-Za-z0-9])(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$"
              title="Password must be at least 8 characters, contain a letter, a number, a special character, start with a letter or number, and contain no spaces." />
            <div class="text-end mt-1">
              <button type="button" id="forgot-password-btn" class="btn btn-link p-0 forgot-password">Forgot Password?</button>
            </div>
          </div>
          <button type="submit" class="btn btn-3d w-100">Login</button>
        <div id="g_id_onload"
     data-client_id="53102166009-2l3ib41tts0h0hplsc80c66jv95msp2n.apps.googleusercontent.com"
     data-callback="handleGoogleLogin"
     data-auto_prompt="false"
     data-auto_select="true" data-context="use"> </div>
<div class="g_id_signin mt-2"
     data-type="standard"
     data-size="large"
     data-theme="outline"
     data-text="continue_with"
     data-shape="rectangular"
     data-logo_alignment="left">
</div>
        </form>
        <form id="signup-form" class="d-none" action="register.php" method="POST">
          <h4 class="mb-3">Signup</h4>
          <div class="mb-3">
            <input type="text" class="form-control" name="name" placeholder="Full Name" required
              pattern="^[A-Za-z ]+$"
              title="Name must contain only alphabets and spaces." />
          </div>
          <div class="mb-3">
            <input type="email" class="form-control" name="email" placeholder="Email" required
              pattern="[a-zA-Z0-9._%+-]+@gmail\.com$" 
              title="Email must end with @gmail.com" />
          </div>
          <div class="mb-3">
            <input type="tel" class="form-control" name="mobile" placeholder="Mobile Number" required 
              pattern="[0-9]{10}" 
              title="Mobile number must be exactly 10 digits" />
          </div>
          <div class="mb-3">
            <input type="number" class="form-control" name="age" placeholder="Age" required min="1" max="120" />
          </div>
          <div class="mb-3">
            <select class="form-select" name="gender" required>
              <option value="">Select Gender</option>
              <option>Male</option>
              <option>Female</option>
              <option>Other</option>
            </select>
          </div>
          <div class="mb-3">
            <select class="form-select" name="role" id="signup-role" required>
              <option value="">Select Role</option>
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="mb-3" id="admin-secret-box" style="display: none;">
            <input type="password" class="form-control" name="admin_code" id="admin-code" placeholder="Enter Admin Secret Code" />
          </div>
          <div class="mb-3">
            <input type="password" class="form-control" name="password" placeholder="Password" id="signup-password" required
              pattern="^(?=[A-Za-z0-9])(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$"
              title="Password must be at least 8 characters, contain a letter, a number, a special character, start with a letter or number, and contain no spaces." />
          </div>
          <div class="mb-3">
            <input type="password" class="form-control" placeholder="Confirm Password" id="confirm-password" required />
          </div>
          <button type="submit" class="btn btn-3d w-100">Signup</button>
       <div id="g_id_onload"
     data-client_id="53102166009-2l3ib41tts0h0hplsc80c66jv95msp2n.apps.googleusercontent.com"
     data-callback="handleGoogleLogin"
     data-auto_prompt="false"
     data-auto_select="true" data-context="use"> </div>
<div class="g_id_signin mt-2"
     data-type="standard"
     data-size="large"
     data-theme="outline"
     data-text="continue_with"
     data-shape="rectangular"
     data-logo_alignment="left">
</div>
        </form>

        <!-- Inside the right column, after the signup form -->
<div class="text-center mt-4">
    <a href="superadmin_login.php" class="btn btn-warning">
        <i class="fas fa-star"></i> Super Admin Login
    </a>
</div>

      </div>
    </div>
  </div>

  <script>

// Block back navigation
history.pushState(null, null, location.href);
window.onpopstate = function() {
    history.go(1);
};

// Force fresh page load on navigation
window.addEventListener('pageshow', function(event) {
    if (event.persisted || performance.navigation.type === 2) {
        window.location.reload();
    }
});

// Clear reset-related session variables
sessionStorage.removeItem('reset_in_progress');

    const loginForm = document.getElementById('login-form');
    const signupForm = document.getElementById('signup-form');
    const showLogin = document.getElementById('show-login');
    const showSignup = document.getElementById('show-signup');

    showLogin.onclick = () => {
      loginForm.classList.remove('d-none');
      signupForm.classList.add('d-none');
    };

    showSignup.onclick = () => {
      signupForm.classList.remove('d-none');
      loginForm.classList.add('d-none');
    };

    document.getElementById('signup-role').addEventListener('change', function () {
      const adminSecretBox = document.getElementById('admin-secret-box');
      if (this.value === 'admin') {
        adminSecretBox.style.display = 'block';
        document.getElementById('admin-code').setAttribute('required', 'required');
      } else {
        adminSecretBox.style.display = 'none';
        document.getElementById('admin-code').removeAttribute('required');
      }
    });

    signupForm.addEventListener('submit', function (e) {
      const password = document.getElementById('signup-password').value;
      const confirmPassword = document.getElementById('confirm-password').value;
      const role = document.getElementById('signup-role').value;
      const adminCode = document.getElementById('admin-code').value;

      if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
        return;
      }

      if (role === 'admin' && adminCode.trim() === '') {
        e.preventDefault();
        alert('Please enter the admin secret code.');
        return;
      }
    });

    document.getElementById('forgot-password-btn').onclick = function() {
      const role = document.querySelector('select[name="role"]').value;
      const email = document.querySelector('input[name="email"]').value.trim();
      if (!role) {
          alert("Please select your role.");
          return;
      }
      if (!email) {
          alert("Please enter your registered email.");
          return;
      }
      window.location.href = 'forgot_password.php?email=' + 
          encodeURIComponent(email) + '&role=' + encodeURIComponent(role);
    };

   
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.querySelector('select[name="role"]');
    const backButton = document.querySelector('.back-link');
    
    if (backButton) {
        backButton.addEventListener('click', function(e) {
            if (roleSelect.value) {
                e.preventDefault();
                roleSelect.selectedIndex = 0; // Reset selection
            }
        });
    }
});

// Google Sign-In Callback Function
function handleGoogleLogin(response) {
    const id_token = response.credential;
    console.log("Google ID Token:", id_token); // For debugging

    // Send the ID token to your backend for verification and authentication
    fetch('http://localhost/addwise_7/addwise_4/addwise_1/auth/google/callback.php', { // This must match your Authorized Redirect URI
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ id_token: id_token })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Backend response:', data);
        if (data.success) {
            // If backend successfully processes, redirect to dashboard or update UI
            if (data.role === 'Admin') { // Assuming your backend sends role
                window.location.href = 'admin_dashboard.php';
            } else { // Default to User or specified role
                window.location.href = 'user_dashboard.php';
            }
        } else {
            alert('Google login/signup failed: ' + (data.message || 'Unknown error.'));
        }
    })
    .catch(error => {
        console.error('Error sending Google ID token to backend:', error);
        alert('An error occurred during Google sign-in. Please try again.');
    });
}

  </script>
</body>
</html>
