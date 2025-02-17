<?php
session_start();
$error_message = '';
$email_class = '';

// Include the database connection file
include '../../../../model/db_connection.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../../../../vendor/autoload.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize the email input
    $email = $db->real_escape_string($_POST['email']);

    // Check if the email exists in the database
    $sql = "SELECT * FROM users WHERE email = '$email'";
    $result = $db->query($sql);

    if ($result->num_rows > 0) {
        // Generate a unique token
        $token = bin2hex(random_bytes(50));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour")); // Token expires in 1 hour
        echo "Generated Token: $token\n";
        echo "Expiration Time: $expires\n";
        // Store the token in the database
        $update_sql = "UPDATE users SET reset_token = '$token', reset_token_expires = '$expires' WHERE email = '$email'";
        if ($db->query($update_sql)) {
            // Send the reset email using Gmail
            $reset_link = "http://localhost:3000/src/view/php/general/login_regis/reset_password.php?token=$token";

            // Create the email content
            $email_content = "Click the following link to reset your password: $reset_link";

            // Create a new PHPMailer instance
            $mail = new PHPMailer(true);

            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com'; // Gmail SMTP server
                $mail->SMTPAuth = true;
                $mail->Username = 'samcistmdd@gmail.com'; // Your Gmail address
                $mail->Password = 'tbfy ejqm fuuf atmb'; // Your Gmail password or app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
                $mail->Port = 587; // TCP port to connect to

                // Recipients
                $mail->setFrom('noreply@gmail.com', 'Inventory Management System'); // Sender email and name
                $mail->addAddress($email); // Recipient email

                // Content
                $mail->isHTML(false); // Set email format to plain text
                $mail->Subject = 'Password Reset Request';
                $mail->Body = $email_content;

                // Send the email
                $mail->send();
                $_SESSION['reset_email_sent'] = true;
                header("Location: reset_email_sent.php");
                exit();
            } catch (Exception $e) {
                $error_message = "Failed to send reset email. Error: " . $mail->ErrorInfo;
                $email_class = 'is-invalid';
            }
        } else {
            $error_message = "Failed to generate reset token. Please try again.";
            $email_class = 'is-invalid';
        }
    } else {
        $error_message = "Email address not found.";
        $email_class = 'is-invalid';
    }

    $db->close();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="/src/view/styles/css/forgot_password.css?v=1.0">
    <link rel="icon" type="png" href="../../../../../public/assets/img/SLU Logo.png">
</head>

<body>

    <div class="container py-5 mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow custom-card-size">
                    <div class="card-body">
                        <h1 class="text-center mb-4">Forgot Password</h1>
                        <p class="text-center">Enter your email address to receive a password reset link.</p>

                        <form action="" method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="form-floating">
                                    <input type="email" class="form-control <?php echo $email_class; ?>" id="email" name="email" required>
                                    <label for="email">Enter your email</label>
                                    <div class="invalid-feedback">
                                        <?php echo $error_message; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary w-100 py-2">Send Reset Link</button>
                            </div>

                            <div class="text-center mt-3">
                                <p>Remember your password? <a href="../../../../../public/index.php" class="link-secondary text-dark">Sign In</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bottom">
        <?php include "../footer.php" ?>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
