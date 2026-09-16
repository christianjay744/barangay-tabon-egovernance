<?php

require 'config/db.php';
require 'config/auth.php';

$msg = '';
$success = '';

/*
|--------------------------------------------------------------------------
| PROCESS REGISTRATION
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $address = trim($_POST['address'] ?? '');
    $birth = !empty($_POST['birth_date'])
        ? $_POST['birth_date']
        : null;

    $contact = trim($_POST['contact_number'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        !$name ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen($pass) < 6 ||
        !$address
    ) {

        $msg = 'Please complete all required fields. Password must be at least 6 characters.';

    } elseif ($pass !== $confirm) {

        $msg = 'Passwords do not match.';

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | START DATABASE TRANSACTION
            |--------------------------------------------------------------------------
            */

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | CREATE USER
            |--------------------------------------------------------------------------
            */

            $s = $pdo->prepare("
                INSERT INTO users
                (
                    full_name,
                    email,
                    password_hash
                )
                VALUES
                (?, ?, ?)
            ");

            $s->execute([
                $name,
                $email,
                password_hash($pass, PASSWORD_DEFAULT)
            ]);


            $uid = $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | CREATE RESIDENT PROFILE
            |--------------------------------------------------------------------------
            */

            $s = $pdo->prepare("
                INSERT INTO residents
                (
                    user_id,
                    address,
                    birth_date,
                    contact_number
                )
                VALUES
                (?, ?, ?, ?)
            ");

            $s->execute([
                $uid,
                $address,
                $birth,
                $contact
            ]);


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | REDIRECT TO LOGIN
            |--------------------------------------------------------------------------
            */

            header(
                'Location: login.php?success=' .
                urlencode('Registration successful. Please sign in.')
            );

            exit;


        } catch (Exception $e) {

            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $msg = 'Registration failed. The email may already be registered.';
        }
    }
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

    <title>
        Create Account | Barangay Tabon
    </title>


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


    <style>

        /* =====================================================
           REGISTER PAGE
        ===================================================== */

        .register-form {

            display: flex;

            flex-direction: column;

            gap: 0;

        }


        /* =====================================================
           FORM GRID
        ===================================================== */

        .register-grid {

            display: grid;

            grid-template-columns: 1fr 1fr;

            gap: 16px;

        }


        .register-grid .full {

            grid-column: 1 / -1;

        }


        /* =====================================================
           PASSWORD STRENGTH
        ===================================================== */

        .password-strength {

            margin-top: 8px;

            font-size: 11px;

            color: #777;

        }


        .strength-bar {

            height: 4px;

            width: 100%;

            background: rgba(255,255,255,.08);

            border-radius: 10px;

            overflow: hidden;

            margin-top: 6px;

        }


        .strength-progress {

            height: 100%;

            width: 0%;

            transition: .3s;

        }


        /* =====================================================
           SUCCESS / ERROR
        ===================================================== */

        .register-message {

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 13px 15px;

            border-radius: 12px;

            margin-bottom: 20px;

            font-size: 13px;

            line-height: 1.5;

        }


        .register-error {

            color: #ff7070;

            background: rgba(255,70,70,.07);

            border: 1px solid rgba(255,70,70,.18);

        }


        .register-success {

            color: #31d07b;

            background: rgba(49,208,123,.07);

            border: 1px solid rgba(49,208,123,.18);

        }


        /* =====================================================
           PASSWORD TOGGLE
        ===================================================== */

        .password-wrapper {

            position: relative;

        }


        .password-wrapper input {

            padding-right: 48px;

        }


        .password-toggle {

            position: absolute;

            right: 13px;

            top: 50%;

            transform: translateY(-50%);

            border: none;

            background: transparent;

            color: #777;

            cursor: pointer;

            font-size: 19px;

            padding: 5px;

        }


        .password-toggle:hover {

            color: #f5c542;

        }


        /* =====================================================
           REGISTER INFO
        ===================================================== */

        .registration-info {

            margin-top: 18px;

            padding: 13px 15px;

            border-radius: 12px;

            background: rgba(245,197,66,.035);

            border: 1px solid rgba(245,197,66,.1);

            color: #888;

            font-size: 11px;

            line-height: 1.6;

        }


        .registration-info i {

            color: #f5c542;

            margin-right: 5px;

        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media (max-width: 700px) {

            .register-grid {

                grid-template-columns: 1fr;

            }

            .register-grid .full {

                grid-column: auto;

            }

        }

    </style>

</head>


<body>


<div class="auth-page">


    <div class="auth-container">


        <!-- ==================================================
             LEFT SIDE
        ================================================== -->

        <div class="auth-brand">


            <!-- ICON -->

            <div class="brand-icon">

                <i class="ri-user-add-line"></i>

            </div>


            <!-- TITLE -->

            <h1>

                Create Your

                <span>
                    Digital Account
                </span>

            </h1>


            <!-- DESCRIPTION -->

            <p>

                Register for Barangay Tabon's digital government
                services and manage your documents securely online.

            </p>


            <!-- SECURITY FEATURES -->

            <div class="security-list">


                <div class="security-item">

                    <i class="ri-user-shield-line"></i>

                    <span>
                        Secure Resident Account
                    </span>

                </div>


                <div class="security-item">

                    <i class="ri-file-shield-line"></i>

                    <span>
                        Digital Document Services
                    </span>

                </div>


                <div class="security-item">

                    <i class="ri-links-line"></i>

                    <span>
                        Blockchain-Ready Records
                    </span>

                </div>


                <div class="security-item">

                    <i class="ri-shield-check-line"></i>

                    <span>
                        Protected Personal Information
                    </span>

                </div>


            </div>


        </div>



        <!-- ==================================================
             RIGHT SIDE
        ================================================== -->

        <div class="auth-form-area">


            <h2>
                Create Account
            </h2>


            <p class="auth-subtitle">

                Enter your information below to get started.

            </p>



            <!-- ==================================================
                 ERROR MESSAGE
            ================================================== -->

            <?php if ($msg): ?>

                <div class="register-message register-error">

                    <i class="ri-error-warning-line"></i>

                    <span>

                        <?= htmlspecialchars($msg) ?>

                    </span>

                </div>

            <?php endif; ?>



            <!-- ==================================================
                 REGISTRATION FORM
            ================================================== -->

            <form
                method="POST"
                class="register-form"
            >


                <div class="register-grid">


                    <!-- FULL NAME -->

                    <div class="form-group full">

                        <label for="full_name">

                            Full Name

                        </label>


                        <div class="input-box">

                            <i class="ri-user-line"></i>


                            <input
                                type="text"
                                id="full_name"
                                name="full_name"
                                placeholder="Juan Dela Cruz"
                                value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                                required
                            >

                        </div>

                    </div>



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
                                placeholder="you@example.com"
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                autocomplete="email"
                                required
                            >

                        </div>

                    </div>



                    <!-- CONTACT -->

                    <div class="form-group">

                        <label for="contact_number">

                            Contact Number

                        </label>


                        <div class="input-box">

                            <i class="ri-phone-line"></i>


                            <input
                                type="text"
                                id="contact_number"
                                name="contact_number"
                                placeholder="09XXXXXXXXX"
                                value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                            >

                        </div>

                    </div>



                    <!-- PASSWORD -->

                    <div class="form-group">

                        <label for="password">

                            Password

                        </label>


                        <div class="input-box password-wrapper">

                            <i class="ri-lock-password-line"></i>


                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Create a password"
                                minlength="6"
                                autocomplete="new-password"
                                required
                                oninput="checkPasswordStrength()"
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePassword('password','passwordIcon')"
                            >

                                <i
                                    id="passwordIcon"
                                    class="ri-eye-line"
                                ></i>

                            </button>

                        </div>


                        <div class="password-strength">

                            <span id="strengthText">
                                Minimum 6 characters
                            </span>


                            <div class="strength-bar">

                                <div
                                    id="strengthProgress"
                                    class="strength-progress"
                                ></div>

                            </div>

                        </div>

                    </div>



                    <!-- CONFIRM PASSWORD -->

                    <div class="form-group">

                        <label for="confirm_password">

                            Confirm Password

                        </label>


                        <div class="input-box password-wrapper">

                            <i class="ri-lock-line"></i>


                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                placeholder="Repeat your password"
                                minlength="6"
                                autocomplete="new-password"
                                required
                            >


                            <button
                                type="button"
                                class="password-toggle"
                                onclick="togglePassword('confirm_password','confirmIcon')"
                            >

                                <i
                                    id="confirmIcon"
                                    class="ri-eye-line"
                                ></i>

                            </button>

                        </div>

                    </div>



                    <!-- ADDRESS -->

                    <div class="form-group full">

                        <label for="address">

                            Complete Address

                        </label>


                        <div class="input-box">

                            <i class="ri-map-pin-line"></i>


                            <input
                                type="text"
                                id="address"
                                name="address"
                                placeholder="Barangay Tabon, Bislig City"
                                value="<?= htmlspecialchars($_POST['address'] ?? '') ?>"
                                required
                            >

                        </div>

                    </div>



                    <!-- BIRTH DATE -->

                    <div class="form-group">

                        <label for="birth_date">

                            Birth Date

                        </label>


                        <div class="input-box">

                            <i class="ri-calendar-line"></i>


                            <input
                                type="date"
                                id="birth_date"
                                name="birth_date"
                                value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>"
                            >

                        </div>

                    </div>



                    <!-- ACCOUNT TYPE -->

                    <div class="form-group">

                        <label>

                            Account Type

                        </label>


                        <div class="input-box">

                            <i class="ri-shield-user-line"></i>


                            <input
                                type="text"
                                value="Resident"
                                readonly
                                style="color:#f5c542;"
                            >

                        </div>

                    </div>


                </div>



                <!-- INFORMATION -->

                <div class="registration-info">

                    <i class="ri-information-line"></i>

                    Your account will be registered as a
                    <strong>Resident</strong>.
                    You can use your account to request and
                    verify Barangay Tabon digital documents.

                </div>



                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="primary-btn"
                    style="margin-top:20px;"
                >

                    <i class="ri-user-add-line"></i>

                    Create Account

                </button>


            </form>



            <!-- LOGIN -->

            <div class="auth-footer">

                Already have an account?

                <a href="login.php">

                    Sign In

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
     JAVASCRIPT
================================================== -->

<script>


/*
|--------------------------------------------------------------------------
| PASSWORD VISIBILITY
|--------------------------------------------------------------------------
*/

function togglePassword(inputId, iconId) {

    const input =
        document.getElementById(inputId);

    const icon =
        document.getElementById(iconId);


    if (input.type === 'password') {

        input.type = 'text';

        icon.className = 'ri-eye-off-line';

    } else {

        input.type = 'password';

        icon.className = 'ri-eye-line';

    }

}



/*
|--------------------------------------------------------------------------
| PASSWORD STRENGTH
|--------------------------------------------------------------------------
*/

function checkPasswordStrength() {

    const password =
        document.getElementById('password').value;

    const progress =
        document.getElementById('strengthProgress');

    const text =
        document.getElementById('strengthText');


    let strength = 0;


    if (password.length >= 6) {

        strength += 25;

    }

    if (password.length >= 8) {

        strength += 25;

    }

    if (/[A-Z]/.test(password)) {

        strength += 15;

    }

    if (/[0-9]/.test(password)) {

        strength += 15;

    }

    if (/[^A-Za-z0-9]/.test(password)) {

        strength += 20;

    }


    progress.style.width = strength + '%';


    if (password.length === 0) {

        text.textContent =
            'Minimum 6 characters';

    }

    else if (strength < 40) {

        text.textContent =
            'Weak password';

    }

    else if (strength < 70) {

        text.textContent =
            'Medium password';

    }

    else {

        text.textContent =
            'Strong password';

    }

}



/*
|--------------------------------------------------------------------------
| CONFIRM PASSWORD CHECK
|--------------------------------------------------------------------------
*/

document
    .querySelector('form')
    .addEventListener('submit', function(event) {

        const password =
            document.getElementById('password').value;

        const confirm =
            document.getElementById('confirm_password').value;


        if (password !== confirm) {

            event.preventDefault();

            alert('Passwords do not match.');

        }

    });

</script>


</body>

</html>