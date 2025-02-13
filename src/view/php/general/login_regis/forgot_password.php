<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="/src/view/styles/css/registration.css?v=1.0">
    <link rel="icon" type="png" href="../../../../../public/assets/img/SLU Logo.png">
    <style>
        .container {
            padding-bottom: 60px;
        }
    </style>
</head>

<body>

    <div class="container py-5 mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card shadow extra-large-card">
                    <div class="card-body">
                        <h1 class="text-center mb-4">Forgot Password</h1>
                        <p class="text-center">Enter your email address to receive a password reset link.</p>

                        <form action="process_forgot_password.php" method="post">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="form-floating">
                                    <input type="email" class="form-control" id="email" name="email" required>
                                    <label for="email">Enter your email</label>
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
