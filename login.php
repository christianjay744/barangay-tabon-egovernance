<?php

require 'config/db.php';
require 'config/auth.php';
require 'config/helpers.php';

/*
|--------------------------------------------------------------------------
| PROCESS LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Find user
    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $stmt->execute([$email]);

    $u = $stmt->fetch();

    // Check login credentials
    if (
        !$u ||
        !password_verify($password, $u['password_hash'])
    ) {

        header(
            'Location: index.php?error=' .
            urlencode('Invalid email or password')
        );

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE SESSION
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_id'] = $u['id'];
    $_SESSION['role'] = $u['role'];
    $_SESSION['full_name'] = $u['full_name'];

    /*
    |--------------------------------------------------------------------------
    | ACTIVITY LOG
    |--------------------------------------------------------------------------
    */

    log_activity(
        $pdo,
        $u['id'],
        'LOGIN',
        'User logged in'
    );

    /*
    |--------------------------------------------------------------------------
    | REDIRECT USER
    |--------------------------------------------------------------------------
    */

    if ($u['role'] === 'admin') {

        header('Location: admin/dashboard.php');

    } else {

        header('Location: resident/dashboard.php');
    }

    exit;
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login | Barangay Tabon</title>


    <!-- MAIN CSS -->

    <link
        rel="stylesheet"
        href="assets/style.css"
    >


    <!-- REMIX ICONS -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css"
    >

</head>


<body>


<div class="auth-page">

    <div class="auth-container">


        <!-- ==================================================
             LEFT SIDE - BRANDING
        =================================================== -->

        <div class="auth-brand">


            <div class="brand-icon">

                <i class="ri-government-line"></i>

            </div>


            <h1>

                Barangay Tabon

                <span>
                    Digital Services
                </span>

            </h1>


            <p>

                Secure digital government services powered by
                blockchain technology and tamper-resistant records.

            </p>


            <!-- SECURITY FEATURES -->

            <div class="security-list">


                <div class="security-item">

                    <i class="ri-shield-check-line"></i>

                    <span>
                        Secure Document Verification
                    </span>

                </div>


                <div class="security-item">

                    <i class="ri-links-line"></i>

                    <span>
                        Blockchain-Protected Records
                    </span>

                </div>


                <div class="security-item">

                    <i class="ri-lock-line"></i>

                    <span>
                        Protected User Accounts
                    </span>

                </div>


            </div>


        </div>



        <!-- ==================================================
             RIGHT SIDE - LOGIN FORM
        =================================================== -->

        <div class="auth-form-area">


            <div class="login-icon">

                <i class="ri-user-line"></i>

            </div>


            <h2>
                Welcome Back
            </h2>


            <p class="auth-subtitle">

                Sign in to access your account.

            </p>



            <!-- ==================================================
                 ERROR MESSAGE
            =================================================== -->

            <?php if (!empty($_GET['error'])): ?>

                <div class="login-error">

                    <i class="ri-error-warning-line"></i>

                    <span>
                        <?= htmlspecialchars($_GET['error']) ?>
                    </span>

                </div>

            <?php endif; ?>



            <!-- ==================================================
                 LOGIN FORM
            =================================================== -->

            <form
                method="POST"
                action=""
                class="login-form"
            >


                <!-- EMAIL -->

                <div class="form-group">

                    <label for="email">

                        Email Address

                    </label>


                    <div class="input-box">

                        <i class="ri-mail-line"></i>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="Enter your email"
                            autocomplete="email"
                            required
                        >

                    </div>

                </div>



                <!-- PASSWORD -->

                <div class="form-group">

                    <label for="password">

                        Password

                    </label>


                    <div class="input-box">

                        <i class="ri-lock-password-line"></i>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            onclick="togglePassword()"
                        >

                            <i
                                id="passwordIcon"
                                class="ri-eye-line"
                            ></i>

                        </button>

                    </div>

                </div>



                <!-- LOGIN BUTTON -->

                <button
                    type="submit"
                    class="primary-btn"
                >

                    <i class="ri-login-box-line"></i>

                    Sign In

                </button>


            </form>



            <!-- REGISTER -->

            <div class="auth-footer">

                Don't have an account?

                <a href="register.php">

                    Create Account

                </a>

            </div>



            <!-- HOME -->

            <div class="auth-footer back-home">

                <a href="MyWebsite.php">

                    <i class="ri-arrow-left-line"></i>

                    Back to Home

                </a>

            </div>


        </div>

    </div>

</div>



<!-- ==================================================
     PASSWORD TOGGLE
=================================================== -->

<script>

function togglePassword() {

    const password =
        document.getElementById('password');

    const icon =
        document.getElementById('passwordIcon');


    if (password.type === 'password') {

        password.type = 'text';

        icon.className = 'ri-eye-off-line';

    } else {

        password.type = 'password';

        icon.className = 'ri-eye-line';

    }

}

</script>


</body>

</html>