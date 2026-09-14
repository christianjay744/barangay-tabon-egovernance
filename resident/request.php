<?php
require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

if ($_SESSION['role'] !== 'resident') {
    exit('Forbidden');
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $document_type = trim($_POST['document_type'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $requirements_text = trim($_POST['requirements_text'] ?? '');


    /*
    |--------------------------------------------------------------------------
    | ALLOWED DOCUMENT TYPES
    |--------------------------------------------------------------------------
    */

    $allowed_document_types = [
        'Barangay Clearance',
        'Certificate of Residency',
        'Certificate of Indigency',
        'Certificate of Completion'
    ];


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($document_type === '' || $purpose === '') {

        $message = 'Please complete all required fields.';
        $message_type = 'error';

    } elseif (!in_array(
        $document_type,
        $allowed_document_types,
        true
    )) {

        $message = 'Invalid document type selected.';
        $message_type = 'error';

    } else {

        try {

            $s = $pdo->prepare("
                INSERT INTO document_requests
                (
                    user_id,
                    document_type,
                    purpose,
                    requirements_text
                )
                VALUES (?, ?, ?, ?)
            ");

            $s->execute([
                $_SESSION['user_id'],
                $document_type,
                $purpose,
                $requirements_text
            ]);


            /*
            |--------------------------------------------------------------------------
            | GET NEW REQUEST ID
            |--------------------------------------------------------------------------
            */

            $request_id = (int) $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | GENERATE DOCUMENT NUMBER
            | Example: BT-2026-000001
            |--------------------------------------------------------------------------
            */

            $document_number =
                'BT-' .
                date('Y') .
                '-' .
                str_pad(
                    (string) $request_id,
                    6,
                    '0',
                    STR_PAD_LEFT
                );


            /*
            |--------------------------------------------------------------------------
            | SAVE DOCUMENT NUMBER
            |--------------------------------------------------------------------------
            */

            $update = $pdo->prepare("
                UPDATE document_requests
                SET document_number = ?
                WHERE id = ?
            ");

            $update->execute([
                $document_number,
                $request_id
            ]);


            /*
            |--------------------------------------------------------------------------
            | ACTIVITY LOG
            |--------------------------------------------------------------------------
            */

            log_activity(
                $pdo,
                $_SESSION['user_id'],
                'DOCUMENT_REQUEST',
                'Requested ' .
                $document_type .
                ' - ' .
                $document_number
            );


            $message =
                'Document request submitted successfully. ' .
                'Document Number: ' .
                $document_number;

            $message_type = 'success';

        } catch (Exception $e) {

            $message =
                'Unable to submit your request. Please try again.';

            $message_type = 'error';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Request Document | Barangay Tabon</title>

    <link rel="stylesheet" href="../assets/style.css">

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css"
        rel="stylesheet"
    >

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background:
                radial-gradient(circle at top right, rgba(255, 193, 7, .08), transparent 35%),
                radial-gradient(circle at bottom left, rgba(255, 165, 0, .05), transparent 35%),
                #050505;
            color: #fff;
            font-family: Arial, Helvetica, sans-serif;
            min-height: 100vh;
        }

        /* =========================
           SIDEBAR
        ========================= */

        .resident-sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100vh;
            background: rgba(10, 10, 10, .96);
            border-right: 1px solid rgba(255, 193, 7, .18);
            padding: 25px 18px;
            z-index: 1000;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px 25px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            margin-bottom: 25px;
        }

        .brand-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            background: linear-gradient(135deg, #ffd700, #ff9d00);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 24px;
        }

        .brand-text h3 {
            margin: 0;
            font-size: 15px;
            color: #ffd700;
        }

        .brand-text span {
            display: block;
            color: #777;
            font-size: 11px;
            margin-top: 3px;
        }

        .menu-title {
            color: #555;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1.5px;
            margin: 20px 12px 10px;
            text-transform: uppercase;
        }

        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 13px;
            padding: 13px 14px;
            border-radius: 10px;
            color: #999;
            text-decoration: none;
            font-size: 14px;
            transition: .25s;
        }

        .sidebar-menu a i {
            font-size: 20px;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            color: #ffd700;
            background: rgba(255, 215, 0, .08);
            box-shadow: inset 3px 0 0 #ffd700;
        }

        .logout-link {
            margin-top: 20px;
            border-top: 1px solid rgba(255,255,255,.07);
            padding-top: 20px !important;
        }

        .logout-link:hover {
            color: #ff5555 !important;
            box-shadow: inset 3px 0 0 #ff5555 !important;
        }

        /* =========================
           MAIN
        ========================= */

        .main-content {
            margin-left: 260px;
            padding: 35px;
            min-height: 100vh;
        }

        .top-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
        }

        .page-title h1 {
            margin: 0;
            font-size: 30px;
            color: #fff;
        }

        .page-title p {
            margin: 8px 0 0;
            color: #777;
            font-size: 14px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffd700, #ff9800);
            color: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .user-name {
            font-size: 13px;
            color: #ddd;
        }

        .user-role {
            display: block;
            font-size: 10px;
            color: #777;
            margin-top: 3px;
        }

        /* =========================
           REQUEST LAYOUT
        ========================= */

        .request-layout {
            max-width: 1100px;
            display: grid;
            grid-template-columns: 330px 1fr;
            gap: 25px;
        }

        .info-card,
        .request-card {
            background: rgba(15,15,15,.92);
            border: 1px solid rgba(255,215,0,.14);
            border-radius: 18px;
            box-shadow: 0 15px 50px rgba(0,0,0,.35);
        }

        /* =========================
           INFO CARD
        ========================= */

        .info-card {
            padding: 25px;
            height: fit-content;
        }

        .info-icon {
            width: 58px;
            height: 58px;
            border-radius: 15px;
            background: rgba(255,215,0,.08);
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 29px;
            margin-bottom: 20px;
        }

        .info-card h2 {
            margin: 0 0 10px;
            font-size: 19px;
        }

        .info-card > p {
            color: #777;
            line-height: 1.6;
            font-size: 13px;
        }

        .step {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .step-number {
            min-width: 30px;
            height: 30px;
            border-radius: 50%;
            background: rgba(255,215,0,.1);
            color: #ffd700;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
        }

        .step-content strong {
            display: block;
            font-size: 13px;
            color: #ddd;
            margin-bottom: 4px;
        }

        .step-content span {
            color: #666;
            font-size: 11px;
        }

        /* =========================
           FORM
        ========================= */

        .request-card {
            padding: 32px;
        }

        .request-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,.07);
        }

        .request-header i {
            font-size: 30px;
            color: #ffd700;
        }

        .request-header h2 {
            margin: 0;
            font-size: 21px;
        }

        .request-header p {
            margin: 5px 0 0;
            color: #666;
            font-size: 12px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            color: #bbb;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 9px;
        }

        .required {
            color: #ffd700;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #777;
            font-size: 18px;
        }

        .form-control,
        .form-group select,
        .form-group textarea {
            width: 100%;
            border: 1px solid #292929;
            background: #0a0a0a;
            color: #eee;
            border-radius: 10px;
            padding: 14px 15px;
            outline: none;
            font-size: 13px;
            transition: .25s;
        }

        .input-wrapper .form-control {
            padding-left: 44px;
        }

        .form-control:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #ffd700;
            box-shadow: 0 0 0 3px rgba(255,215,0,.07);
        }

        .form-group select {
            cursor: pointer;
        }

        .form-group textarea {
            min-height: 130px;
            resize: vertical;
            line-height: 1.5;
        }

        .form-hint {
            display: block;
            margin-top: 7px;
            color: #555;
            font-size: 10px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        /* =========================
           ALERT
        ========================= */

        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert.success {
            background: rgba(46, 204, 113, .08);
            border: 1px solid rgba(46,204,113,.25);
            color: #5ee695;
        }

        .alert.error {
            background: rgba(255, 70, 70, .08);
            border: 1px solid rgba(255,70,70,.25);
            color: #ff7777;
        }

        /* =========================
           BUTTONS
        ========================= */

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 30px;
            padding-top: 22px;
            border-top: 1px solid rgba(255,255,255,.07);
        }

        .btn {
            border: 0;
            border-radius: 10px;
            padding: 13px 22px;
            font-weight: bold;
            cursor: pointer;
            text-decoration: none;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: .25s;
        }

        .btn-secondary {
            background: #171717;
            color: #999;
            border: 1px solid #292929;
        }

        .btn-secondary:hover {
            color: #fff;
            border-color: #444;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ffd700, #ff9d00);
            color: #000;
            box-shadow: 0 8px 25px rgba(255,183,0,.15);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(255,183,0,.25);
        }

        /* =========================
           SECURITY NOTICE
        ========================= */

        .security-notice {
            margin-top: 22px;
            padding: 15px;
            border-radius: 10px;
            background: rgba(255,215,0,.035);
            border: 1px solid rgba(255,215,0,.1);
            display: flex;
            gap: 10px;
        }

        .security-notice i {
            color: #ffd700;
            font-size: 19px;
        }

        .security-notice p {
            margin: 0;
            color: #777;
            font-size: 11px;
            line-height: 1.5;
        }

        /* =========================
           MOBILE
        ========================= */

        @media(max-width: 900px) {

            .resident-sidebar {
                width: 75px;
                padding: 20px 10px;
            }

            .brand-text,
            .menu-title,
            .sidebar-menu span {
                display: none;
            }

            .brand {
                justify-content: center;
                padding: 5px 0 20px;
            }

            .sidebar-menu a {
                justify-content: center;
                padding: 13px;
            }

            .main-content {
                margin-left: 75px;
                padding: 25px;
            }

            .request-layout {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 600px) {

            .main-content {
                margin-left: 0;
                padding: 18px;
            }

            .resident-sidebar {
                position: relative;
                width: 100%;
                height: auto;
                display: none;
            }

            .top-header {
                align-items: flex-start;
            }

            .page-title h1 {
                font-size: 24px;
            }

            .user-info {
                display: none;
            }

            .request-card {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

    </style>

</head>

<body>

<!-- =========================
     SIDEBAR
========================= -->

<aside class="resident-sidebar">

    <div class="brand">

        <div class="brand-icon">
            <i class="ri-government-line"></i>
        </div>

        <div class="brand-text">
            <h3>BARANGAY TABON</h3>
            <span>Resident Portal</span>
        </div>

    </div>


    <div class="menu-title">
        Main Menu
    </div>

    <nav class="sidebar-menu">

        <a href="dashboard.php">
            <i class="ri-dashboard-line"></i>
            <span>Dashboard</span>
        </a>

        <a href="request.php" class="active">
            <i class="ri-file-add-line"></i>
            <span>Request Document</span>
        </a>

        <a href="document.php">
            <i class="ri-folder-user-line"></i>
            <span>My Documents</span>
        </a>

        <a href="../verify.php">
            <i class="ri-shield-check-line"></i>
            <span>Verify Document</span>
        </a>

    </nav>


    <div class="menu-title">
        Account
    </div>

    <nav class="sidebar-menu">

        <a href="../logout.php" class="logout-link">
            <i class="ri-logout-box-r-line"></i>
            <span>Logout</span>
        </a>

    </nav>

</aside>


<!-- =========================
     MAIN CONTENT
========================= -->

<main class="main-content">

    <div class="top-header">

        <div class="page-title">

            <h1>Request Document</h1>

            <p>
                Request official Barangay Tabon documents online.
            </p>

        </div>


        <div class="user-info">

            <div class="user-avatar">
                <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
            </div>

            <div class="user-name">

                <?= e($_SESSION['full_name']) ?>

                <span class="user-role">
                    RESIDENT ACCOUNT
                </span>

            </div>

        </div>

    </div>


    <div class="request-layout">


        <!-- =========================
             INFORMATION
        ========================= -->

        <div class="info-card">

            <div class="info-icon">
                <i class="ri-file-shield-2-line"></i>
            </div>

            <h2>Online Document Request</h2>

            <p>
                Submit your request electronically. Your request
                will be reviewed by the Barangay Officer before
                the document is approved and released.
            </p>


            <div class="step">

                <div class="step-number">
                    1
                </div>

                <div class="step-content">

                    <strong>Submit Request</strong>

                    <span>
                        Fill out the document request form.
                    </span>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    2
                </div>

                <div class="step-content">

                    <strong>Officer Review</strong>

                    <span>
                        Barangay staff verifies your request.
                    </span>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    3
                </div>

                <div class="step-content">

                    <strong>Document Approval</strong>

                    <span>
                        Approved requests are prepared for release.
                    </span>

                </div>

            </div>


            <div class="step">

                <div class="step-number">
                    4
                </div>

                <div class="step-content">

                    <strong>QR Verification</strong>

                    <span>
                        Released documents receive a verification QR code.
                    </span>

                </div>

            </div>

        </div>


        <!-- =========================
             REQUEST FORM
        ========================= -->

        <div class="request-card">

            <div class="request-header">

                <i class="ri-file-add-line"></i>

                <div>

                    <h2>Document Request Form</h2>

                    <p>
                        Provide the information required for your request.
                    </p>

                </div>

            </div>


            <?php if ($message): ?>

                <div class="alert <?= $message_type ?>">

                    <?php if ($message_type === 'success'): ?>

                        <i class="ri-checkbox-circle-line"></i>

                    <?php else: ?>

                        <i class="ri-error-warning-line"></i>

                    <?php endif; ?>

                    <?= e($message) ?>

                </div>

            <?php endif; ?>


            <form method="POST" action="request.php">


                <!-- DOCUMENT TYPE -->

                <div class="form-group">

                    <label>
                        Document Type
                        <span class="required">*</span>
                    </label>

                    <div class="input-wrapper">

                        <i class="ri-file-text-line"></i>

                        <select
                            name="document_type"
                            class="form-control"
                            required
                        >

                            <option value="">
                                Select Document Type
                            </option>

                            <option value="Certificate of Completion">
                                Certificate of Completion
                            </option>

                            <option value="Barangay Clearace">
                                Barangay Clearance
                            </option>

                            <option value="Certificate of Residency">
                                Certificate of Residency
                            </option>

                            <option value="Certificate of Indigency">
                                Certificate of Indigency
                            </option>

                            <option value="Certificate of Good Moral">
                                Certificate of Good Moral
                            </option>

                            <option value="Other Certification">
                                Other Certification
                            </option>

                        </select>

                    </div>

                    <span class="form-hint">
                        Select the official document you want to request.
                    </span>

                </div>


                <!-- PURPOSE -->

                <div class="form-group">

                    <label>
                        Purpose of Request
                        <span class="required">*</span>
                    </label>

                    <div class="input-wrapper">

                        <i class="ri-information-line"></i>

                        <input
                            type="text"
                            name="purpose"
                            class="form-control"
                            placeholder="Example: Employment requirement"
                            required
                        >

                    </div>

                    <span class="form-hint">
                        Explain why you need this document.
                    </span>

                </div>


                <!-- REQUIREMENTS -->

                <div class="form-group">

                    <label>
                        Requirements / Additional Notes
                    </label>

                    <textarea
                        name="requirements_text"
                        placeholder="Enter any additional information, requirements, or notes for the Barangay Officer..."
                    ></textarea>

                    <span class="form-hint">
                        Optional. You may provide additional information
                        that can help process your request.
                    </span>

                </div>


                <!-- SECURITY -->

                <div class="security-notice">

                    <i class="ri-shield-check-line"></i>

                    <p>
                        Your request is recorded in the Barangay Tabon
                        E-Governance System. Once approved and released,
                        the document can be secured with a SHA-256 hash,
                        blockchain transaction record, and QR verification.
                    </p>

                </div>


                <!-- ACTIONS -->

                <div class="form-actions">

                    <a
                        href="dashboard.php"
                        class="btn btn-secondary"
                    >
                        <i class="ri-arrow-left-line"></i>
                        Back to Dashboard
                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="ri-send-plane-fill"></i>
                        Submit Request
                    </button>

                </div>


            </form>

        </div>

    </div>

</main>


</body>
</html>