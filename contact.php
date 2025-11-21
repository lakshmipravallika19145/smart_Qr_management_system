<?php
// Start the session to store messages
session_start();

// Initialize variables to store form data and errors
$name = $email = $subject = $message = "";
$errors = [];

// Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Include the database connection file
    require_once "config.php";

    // --- Data Sanitization and Validation ---

    // Name: required, sanitize
    if (empty(trim($_POST["name"]))) {
        $errors[] = "Name is required.";
    } else {
        $name = htmlspecialchars(trim($_POST["name"]));
    }

    // Email: required, validate format, sanitize
    if (empty(trim($_POST["email"]))) {
        $errors[] = "Email is required.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        $email = htmlspecialchars(trim($_POST["email"]));
    }

    // Subject: required, sanitize
    if (empty(trim($_POST["subject"]))) {
        $errors[] = "Subject is required.";
    } else {
        $subject = htmlspecialchars(trim($_POST["subject"]));
    }

    // Message: required, sanitize
    if (empty(trim($_POST["message"]))) {
        $errors[] = "Message is required.";
    } else {
        $message = htmlspecialchars(trim($_POST["message"]));
    }


    // --- Database Insertion ---

    // If there are no validation errors, proceed to insert into DB
    if (empty($errors)) {
        // Prepare an insert statement to prevent SQL Injection
        $sql = "INSERT INTO contacts (name, email, subject, message) VALUES (?, ?, ?, ?)";

        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssss", $param_name, $param_email, $param_subject, $param_message);

            // Set parameters
            $param_name = $name;
            $param_email = $email;
            $param_subject = $subject;
            $param_message = $message;

            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Success! Set a success message and redirect to clear the form
                $_SESSION['success_message'] = "Thank you! Your message has been sent successfully.";
                header("Location: contact.php");
                exit();
            } else {
                $_SESSION['error_message'] = "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        } else {
             $_SESSION['error_message'] = "Database statement preparation failed.";
        }
    } else {
        // If there are validation errors, store them in the session
        $_SESSION['error_message'] = implode("<br>", $errors);
    }
    
    // Close connection
    $conn->close();

    // Redirect back to the contact page to show errors
    header("Location: contact.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - My Awesome Website</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="contact-container">
        <div class="container py-5">
            <div class="row">
                <div class="col-lg-10 mx-auto">
                    <div class="card shadow-lg border-0">
                        <div class="card-body p-5">
                            <h1 class="card-title text-center mb-4">Get in Touch</h1>
                            <p class="card-text text-center text-muted mb-5">We'd love to hear from you! Please fill out the form below to contact us.</p>
                            
                            <?php if (isset($_SESSION['success_message'])): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success_message']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['success_message']); ?>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['error_message'])): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error_message']; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error_message']); ?>
                            <?php endif; ?>


                            <form id="contactForm" action="submit_query.php" method="post" novalidate>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                        <div class="invalid-feedback">Please enter your full name.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                        <div class="invalid-feedback">Please enter a valid email address.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="subject" class="form-label">Subject</label>
                                        <input type="text" class="form-control" id="subject" name="subject" required>
                                        <div class="invalid-feedback">Please enter a subject.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="message" class="form-label">Message</label>
                                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                                        <div class="invalid-feedback">Please enter your message.</div>
                                    </div>
                                    <div class="col-12 text-center">
                                        <button type="submit" class="btn btn-primary btn-lg px-5">
                                            <i class="fas fa-paper-plane me-2"></i>Send Message
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="script.js"></script>
</body>
</html>