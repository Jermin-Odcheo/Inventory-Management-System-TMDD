<?php
session_start();
include 'db.php'; // Include your database connection

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details, roles, and departments
$query = "SELECT u.id, u.username, u.roles, u.departments FROM users u WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch allowed modules based on roles
$roles = explode(',', $user['roles']);
$modules = [];

foreach ($roles as $role) {
    $role = trim($role);
    $query = "SELECT m.name FROM role_privileges rp 
              JOIN modules m ON rp.module_id = m.id 
              WHERE rp.role_name = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $role);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!in_array($row['name'], $modules)) {
            $modules[] = $row['name'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.5/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="/src/view/styles/css/registration.css?v=1.0">
    <link rel="icon" type="png" href="../../../../../public/assets/img/SLU Logo.png">
    <style>
        .container {
            padding-bottom: 60px;
        }
        .custom-link {
            color: #ff0000; /* Change this to your desired color */
        }
    </style>
</head>

<body>

    <div class="container py-5 mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-12 col-md-12">
                <div class="card shadow extra-large-card custom-card-size">
                    <div class="card-body">
                        <form action="" method="post" enctype="multipart/form-data">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <h1 class="text-start mb-4">Create Account</h1>
                                </div>
                                <div class="col-md-6 text-end">
                                    <img src="../../../../../public/assets/img/SLU Logo.png" style="height: 50px;">
                                </div>
                            </div>

                            <div class="row g-4">
                                <div class="col-md-6">
                                    <h6 class="form-label mb-2">First Name</h6>
                                    <div class="form-floating">
                                        <input type="text" class="form-control <?php echo $fname_class; ?>" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
                                        <label for="first_name">Enter your first name</label>
                                        <div class="invalid-feedback">
                                            <?php echo $error_message; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="form-label mb-2">Last Name</h6>
                                    <div class="form-floating">
                                        <input type="text" class="form-control <?php echo $lname_class; ?>" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
                                        <label for="last_name">Enter your last name</label>
                                        <div class="invalid-feedback">
                                            <?php echo $error_message; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="form-label mb-2">Email Address</h6>
                                    <div class="form-floating">
                                        <input type="email" class="form-control <?php echo $email_class; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                        <label for="email">Enter your email address</label>
                                        <div class="invalid-feedback">
                                            <?php echo $error_message; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4"></div>

                            <div class="row g-4 align-items-center">
                                <div class="col-md-6">
                                    <h6 class="form-label mb-2">Password</h6>
                                    <div class="form-floating position-relative">
                                        <input type="password" class="form-control <?php echo $password_class; ?>" id="password" name="password">
                                        <label for="password">Create your password</label>
                                        <div class="invalid-feedback">
                                            <?php echo $error_message; ?>
                                        </div>
                                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('password', this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="form-label mb-2">Confirm Password</h6>
                                    <div class="form-floating position-relative">
                                        <input type="password" class="form-control <?php echo $password_class; ?>" id="confirm_password" name="confirm_password">
                                        <label for="confirm_password">Repeat your password</label>
                                        <div class="invalid-feedback">
                                            <?php echo $error_message; ?>
                                        </div>
                                        <button type="button" class="password-toggle" onclick="togglePasswordVisibility('confirm_password', this)">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to all the <a href="#" data-bs-toggle="modal" data-bs-target="#myModal">Terms and Privacy Policy</a>
                                </label>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary w-100 py-2">Sign Up</button>
                            </div>

                            <div class="text-center mt-3">
                                <p>Changed your mind? <a href="../../../../../public/index.php" class="link-secondary text-dark">Go Back</a></p>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="successModalLabel">Registration Successful</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Your account has been created successfully!</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="okButton">OK</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="bottom">
        <?php include "../footer.php" ?>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../../../control/js/show_pwd.js"></script>
    <script src="../../../../control/js/registration.js"></script>
    <script>
        <?php if ($registration_success): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            });
        <?php endif; ?>

        document.getElementById('okButton').addEventListener('click', function() {
            window.location.href = '../../../../../public/index.php';
        });
    </script>
</body>
</html>