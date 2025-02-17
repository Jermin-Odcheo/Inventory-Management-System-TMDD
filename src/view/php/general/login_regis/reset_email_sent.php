<?php
session_start();
if (!isset($_SESSION['reset_email_sent'])) {
    header("Location: forgot_password.php");
    exit();
}
unset($_SESSION['reset_email_sent']);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Email Sent</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="/src/view/styles/css/reset_email_sent.css?v=1.0">
    <link rel="icon" type="png" href="../../../../../public/assets/img/SLU Logo.png">
</head>

<body>

    <div class="container py-5 mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow custom-card-size">
                    <div class="card-body">
                        <h1 class="text-center mb-4">Reset Email Sent</h1>
                        <p class="text-center">We've sent an email to your address with a link to reset your password. Please check your inbox.</p>
                        <div class="text-center mt-4">
                            <a href="../../../../../public/index.php" class="btn btn-primary w-100 py-2">Return to Login</a>
                        </div>
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