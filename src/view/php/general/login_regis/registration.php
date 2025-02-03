<?php
$error_message = '';
$password_class = '';
$email_class = '';
$fname_class = '';
$lname_class = '';
$form_valid = true;
$registration_success = false;

$first_name = isset($_POST['first_name']) ? $_POST['first_name'] : '';
$last_name = isset($_POST['last_name']) ? $_POST['last_name'] : '';
$email = isset($_POST['email']) ? $_POST['email'] : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
$account_type = 'Uploader';
$online_status = '0';
$forgot_pass = '0';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include '../../../../model/db_connection.php';

    $first_name = $db->real_escape_string(trim(preg_replace('/\s+/', ' ', $_POST['first_name'])));
    $last_name = $db->real_escape_string(trim(preg_replace('/\s+/', ' ', $_POST['last_name'])));
    $email = $db->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($first_name)) {
        $error_message = "This field is required.";
        $fname_class = 'is-invalid';
        $form_valid = false;
    }
    if (empty($last_name)) {
        $error_message = "This field is required.";
        $lname_class = 'is-invalid';
        $form_valid = false;
    }
    if (empty($email)) {
        $error_message = "This field is required.";
        $email_class = 'is-invalid';
        $form_valid = false;
    }
    if (empty($password)) {
        $error_message = "This field is required.";
        $password_class = 'is-invalid';
        $form_valid = false;
    }
    if (empty($confirm_password)) {
        $error_message = "This field is required.";
        $password_class = 'is-invalid';
        $form_valid = false;
    }

    if ($form_valid) {
        if (!preg_match("/^[a-zA-ZÀ-ÿ\s'-.]+$/", $first_name)) {
            $error_message = "First Name contains invalid characters.";
            $fname_class = 'is-invalid';
            $form_valid = false;
        }
        if (!preg_match("/^[a-zA-ZÀ-ÿ\s'-.]+$/", $last_name)) {
            $error_message = "Last Name contains invalid characters.";
            $lname_class = 'is-invalid';
            $form_valid = false;
        }

        $email_check_sql = "SELECT * FROM users WHERE email = '$email'";
        $result = $db->query($email_check_sql);

        if ($result->num_rows > 0) {
            $error_message = "The email address is already taken.";
            $email_class = 'is-invalid';
            $form_valid = false;
        } elseif ($password === $confirm_password) {
            if (strlen($password) < 8 || strlen($password) > 16) {
                $error_message = "Password must be between 8 and 16 characters.";
                $password_class = 'is-invalid';
                $form_valid = false;
            } else {
                if ($form_valid) {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

                    $sql = "INSERT INTO users (first_name, last_name, email, password, account_type, online_status, forgot_pass) 
                            VALUES ('$first_name', '$last_name', '$email', '$hashed_password', '$account_type', '$online_status', '$forgot_pass')";

                    if ($db->query($sql) === TRUE) {
                        $registration_success = true;
                    } else {
                        $error_message = "Error: " . $sql . "<br>" . $db->error;
                    }
                }
            }
        } else {
            $error_message = "Passwords do not match.";
            $password_class = 'is-invalid';
            $form_valid = false;
        }
    }
    $db->close();
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
                                    <p class="text-start text-muted mb-4">Create a new account</p>
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
                                <p>Changed your mind? <a href="../../../../../public/index.php" class="link-secondary">Go Back</a></p>
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