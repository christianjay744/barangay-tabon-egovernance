<?php

require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

/*
|--------------------------------------------------------------------------
| RESIDENT ONLY
|--------------------------------------------------------------------------
*/

if ($_SESSION['role'] !== 'resident') {
    header('Location: ../admin/dashboard.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| RESIDENT PROFILE
|--------------------------------------------------------------------------
*/

$s = $pdo->prepare("
    SELECT *
    FROM residents
    WHERE user_id = ?
    LIMIT 1
");

$s->execute([
    $_SESSION['user_id']
]);

$resident = $s->fetch();


/*
|--------------------------------------------------------------------------
| DOCUMENT REQUESTS
|--------------------------------------------------------------------------
*/

$s = $pdo->prepare("
    SELECT *
    FROM document_requests
    WHERE user_id = ?
    ORDER BY created_at DESC
");

$s->execute([
    $_SESSION['user_id']
]);

$docs = $s->fetchAll();


/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/

$totalDocs = count($docs);

$pendingDocs = 0;
$approvedDocs = 0;
$releasedDocs = 0;

foreach ($docs as $doc) {

    if ($doc['status'] === 'Pending') {
        $pendingDocs++;
    }

    if ($doc['status'] === 'Approved') {
        $approvedDocs++;
    }

    if ($doc['status'] === 'Released') {
        $releasedDocs++;
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
        Resident Dashboard | Barangay Tabon
    </title>


    <!-- ICONS -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css"
    >


    <!-- GOOGLE FONT -->

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <style>

        /* =====================================================
           ROOT
        ===================================================== */

        :root {

            --bg: #050505;
            --bg2: #0b0b0b;
            --card: #101010;

            --gold: #f5c542;
            --gold-light: #ffd96a;

            --white: #ffffff;
            --text: #eeeeee;
            --muted: #888888;

            --green: #31d07b;
            --blue: #4da3ff;
            --red: #ff6464;

            --border: rgba(255,255,255,.07);

        }


        /* =====================================================
           RESET
        ===================================================== */

        * {

            margin: 0;

            padding: 0;

            box-sizing: border-box;

        }


        body {

            min-height: 100vh;

            font-family: 'Inter', sans-serif;

            color: var(--text);

            background:

                radial-gradient(
                    circle at 10% 10%,
                    rgba(245,197,66,.09),
                    transparent 28%
                ),

                radial-gradient(
                    circle at 90% 90%,
                    rgba(245,197,66,.06),
                    transparent 30%
                ),

                var(--bg);

        }


        /* =====================================================
           SIDEBAR
        ===================================================== */

        .sidebar {

            position: fixed;

            left: 0;

            top: 0;

            bottom: 0;

            width: 250px;

            background:

                linear-gradient(
                    180deg,
                    #0d0d0d,
                    #070707
                );

            border-right: 1px solid var(--border);

            padding: 25px 16px;

            z-index: 100;

        }


        /* =====================================================
           BRAND
        ===================================================== */

        .brand {

            display: flex;

            align-items: center;

            gap: 12px;

            padding: 0 10px 25px;

            border-bottom: 1px solid var(--border);

        }


        .brand-icon {

            width: 44px;

            height: 44px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 12px;

            color: #000;

            font-size: 22px;

            background:
                linear-gradient(
                    135deg,
                    var(--gold-light),
                    var(--gold)
                );

            box-shadow:
                0 0 25px rgba(245,197,66,.15);

        }


        .brand h2 {

            font-size: 14px;

            color: white;

            font-weight: 800;

        }


        .brand span {

            display: block;

            margin-top: 4px;

            color: var(--gold);

            font-size: 9px;

            letter-spacing: 1.5px;

            text-transform: uppercase;

        }


        /* =====================================================
           NAV
        ===================================================== */

        .sidebar-menu {

            margin-top: 28px;

        }


        .menu-title {

            padding: 0 12px;

            margin-bottom: 10px;

            color: #555;

            font-size: 9px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 2px;

        }


        .sidebar-menu a {

            display: flex;

            align-items: center;

            gap: 13px;

            padding: 13px 14px;

            margin-bottom: 6px;

            border-radius: 11px;

            text-decoration: none;

            color: #888;

            font-size: 13px;

            font-weight: 600;

            transition: .25s;

        }


        .sidebar-menu a i {

            font-size: 19px;

        }


        .sidebar-menu a:hover {

            color: white;

            background: rgba(255,255,255,.04);

        }


        .sidebar-menu a.active {

            color: #000;

            background:
                linear-gradient(
                    135deg,
                    var(--gold-light),
                    var(--gold)
                );

            box-shadow:
                0 8px 25px rgba(245,197,66,.12);

        }


        /* =====================================================
           SIDEBAR BOTTOM
        ===================================================== */

        .sidebar-bottom {

            position: absolute;

            left: 16px;

            right: 16px;

            bottom: 20px;

        }


        .user-mini {

            padding: 13px;

            border-radius: 12px;

            background: rgba(255,255,255,.035);

            border: 1px solid var(--border);

            margin-bottom: 8px;

        }


        .user-mini-name {

            font-size: 12px;

            font-weight: 700;

            color: white;

            white-space: nowrap;

            overflow: hidden;

            text-overflow: ellipsis;

        }


        .user-mini-role {

            color: var(--gold);

            font-size: 10px;

            margin-top: 4px;

            text-transform: uppercase;

        }


        .logout {

            color: #ff7777 !important;

        }


        .logout:hover {

            background: rgba(255,70,70,.07) !important;

        }


        /* =====================================================
           MAIN
        ===================================================== */

        .main {

            margin-left: 250px;

            min-height: 100vh;

            padding: 35px 40px 60px;

        }


        /* =====================================================
           TOP HEADER
        ===================================================== */

        .top-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 30px;

        }


        .welcome-text .eyebrow {

            color: var(--gold);

            font-size: 10px;

            text-transform: uppercase;

            letter-spacing: 2px;

            font-weight: 700;

            margin-bottom: 7px;

        }


        .welcome-text h1 {

            font-size: 28px;

            color: white;

        }


        .welcome-text p {

            color: var(--muted);

            font-size: 13px;

            margin-top: 6px;

        }


        .profile-button {

            display: flex;

            align-items: center;

            gap: 10px;

            padding: 10px 14px;

            border-radius: 12px;

            border: 1px solid var(--border);

            background: rgba(255,255,255,.035);

        }


        .profile-avatar {

            width: 38px;

            height: 38px;

            border-radius: 10px;

            display: flex;

            justify-content: center;

            align-items: center;

            background: rgba(245,197,66,.1);

            color: var(--gold);

            font-size: 19px;

        }


        .profile-info strong {

            display: block;

            font-size: 12px;

            color: white;

        }


        .profile-info span {

            display: block;

            color: var(--gold);

            font-size: 9px;

            text-transform: uppercase;

            margin-top: 3px;

        }


        /* =====================================================
           STATS
        ===================================================== */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 16px;

            margin-bottom: 25px;

        }


        .stat-card {

            position: relative;

            padding: 20px;

            border-radius: 16px;

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.045),
                    rgba(255,255,255,.015)
                );

            border: 1px solid var(--border);

            overflow: hidden;

            transition: .25s;

        }


        .stat-card:hover {

            transform: translateY(-3px);

            border-color:
                rgba(245,197,66,.2);

        }


        .stat-card::after {

            content: "";

            position: absolute;

            width: 70px;

            height: 70px;

            right: -25px;

            bottom: -25px;

            border-radius: 50%;

            background:
                rgba(245,197,66,.06);

        }


        .stat-icon {

            width: 42px;

            height: 42px;

            border-radius: 11px;

            display: flex;

            align-items: center;

            justify-content: center;

            color: var(--gold);

            background:
                rgba(245,197,66,.08);

            font-size: 20px;

            margin-bottom: 15px;

        }


        .stat-card h3 {

            font-size: 25px;

            color: white;

        }


        .stat-card p {

            margin-top: 5px;

            color: #777;

            font-size: 11px;

        }


        /* =====================================================
           CONTENT GRID
        ===================================================== */

        .content-grid {

            display: grid;

            grid-template-columns:
                1fr 1.5fr;

            gap: 20px;

        }


        .card {

            background:
                linear-gradient(
                    145deg,
                    rgba(255,255,255,.04),
                    rgba(255,255,255,.015)
                );

            border: 1px solid var(--border);

            border-radius: 18px;

            padding: 24px;

        }


        .card-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 20px;

        }


        .card-header h2 {

            font-size: 16px;

            color: white;

        }


        .card-header p {

            font-size: 10px;

            color: #666;

            margin-top: 4px;

        }


        /* =====================================================
           PROFILE
        ===================================================== */

        .identity-status {

            display: inline-flex;

            align-items: center;

            gap: 6px;

            padding: 6px 10px;

            border-radius: 50px;

            background: rgba(49,208,123,.08);

            border: 1px solid rgba(49,208,123,.18);

            color: var(--green);

            font-size: 9px;

            font-weight: 700;

            text-transform: uppercase;

        }


        .profile-box {

            padding: 18px;

            border-radius: 14px;

            background: rgba(0,0,0,.25);

            border: 1px solid var(--border);

        }


        .profile-row {

            display: flex;

            gap: 13px;

            padding: 14px 0;

            border-bottom: 1px solid var(--border);

        }


        .profile-row:last-child {

            border-bottom: none;

            padding-bottom: 0;

        }


        .profile-row:first-child {

            padding-top: 0;

        }


        .profile-row i {

            width: 34px;

            height: 34px;

            display: flex;

            align-items: center;

            justify-content: center;

            color: var(--gold);

            border-radius: 9px;

            background: rgba(245,197,66,.07);

            font-size: 17px;

            flex-shrink: 0;

        }


        .profile-row small {

            display: block;

            color: #666;

            font-size: 9px;

            text-transform: uppercase;

            letter-spacing: 1px;

            margin-bottom: 4px;

        }


        .profile-row strong {

            display: block;

            color: #ddd;

            font-size: 12px;

            word-break: break-word;

        }


        /* =====================================================
           REQUEST FORM
        ===================================================== */

        .request-card {

            margin-top: 20px;

        }


        .request-link {

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 15px;

            border-radius: 12px;

            color: white;

            text-decoration: none;

            background:
                linear-gradient(
                    135deg,
                    rgba(245,197,66,.12),
                    rgba(245,197,66,.03)
                );

            border: 1px solid rgba(245,197,66,.15);

            transition: .25s;

        }


        .request-link:hover {

            transform: translateY(-2px);

            border-color:
                rgba(245,197,66,.4);

        }


        .request-link-left {

            display: flex;

            align-items: center;

            gap: 12px;

        }


        .request-link-left i {

            color: var(--gold);

            font-size: 21px;

        }


        .request-link strong {

            display: block;

            font-size: 12px;

        }


        .request-link span {

            display: block;

            color: #777;

            font-size: 10px;

            margin-top: 3px;

        }


        .request-link > i {

            color: var(--gold);

        }


        /* =====================================================
           REQUEST TABLE
        ===================================================== */

        .requests-card {

            margin-top: 20px;

        }


        .table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 650px;

        }


        th {

            text-align: left;

            padding: 12px;

            color: #666;

            font-size: 9px;

            text-transform: uppercase;

            letter-spacing: 1px;

            border-bottom: 1px solid var(--border);

        }


        td {

            padding: 15px 12px;

            color: #bbb;

            font-size: 11px;

            border-bottom: 1px solid rgba(255,255,255,.045);

        }


        tr:last-child td {

            border-bottom: none;

        }


        .doc-number {

            color: white;

            font-weight: 700;

        }


        .doc-type {

            color: var(--gold-light);

        }


        /* =====================================================
           STATUS BADGES
        ===================================================== */

        .badge {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            padding: 6px 9px;

            border-radius: 50px;

            font-size: 9px;

            font-weight: 700;

        }


        .badge-pending {

            color: #f5c542;

            background: rgba(245,197,66,.08);

        }


        .badge-approved {

            color: var(--blue);

            background: rgba(77,163,255,.08);

        }


        .badge-released {

            color: var(--green);

            background: rgba(49,208,123,.08);

        }


        .badge-rejected {

            color: var(--red);

            background: rgba(255,100,100,.08);

        }


        /* =====================================================
           BLOCKCHAIN
        ===================================================== */

        .blockchain {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            color: #777;

            font-size: 9px;

        }


        .blockchain i {

            color: var(--gold);

        }


        /* =====================================================
           VIEW BUTTON
        ===================================================== */

        .view-btn {

            display: inline-flex;

            align-items: center;

            gap: 5px;

            color: var(--gold);

            text-decoration: none;

            font-size: 10px;

            font-weight: 700;

            padding: 7px 10px;

            border-radius: 8px;

            background: rgba(245,197,66,.06);

            border: 1px solid rgba(245,197,66,.1);

        }


        .view-btn:hover {

            background: rgba(245,197,66,.12);

        }


        /* =====================================================
           EMPTY
        ===================================================== */

        .empty {

            text-align: center;

            padding: 50px 20px;

        }


        .empty i {

            display: block;

            font-size: 45px;

            color: #333;

            margin-bottom: 15px;

        }


        .empty h3 {

            color: #aaa;

            font-size: 14px;

        }


        .empty p {

            color: #555;

            font-size: 11px;

            margin-top: 6px;

        }


        /* =====================================================
           MOBILE
        ===================================================== */

        @media(max-width: 1100px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);

            }

            .content-grid {

                grid-template-columns: 1fr;

            }

        }


        @media(max-width: 800px) {

            .sidebar {

                width: 72px;

                padding: 20px 10px;

            }


            .brand {

                justify-content: center;

                padding-left: 0;

                padding-right: 0;

            }


            .brand h2,
            .brand span,
            .menu-title,
            .sidebar-menu span,
            .user-mini,
            .sidebar-bottom .logout span {

                display: none;

            }


            .sidebar-menu a {

                justify-content: center;

                padding: 13px;

            }


            .sidebar-menu a i {

                font-size: 21px;

            }


            .main {

                margin-left: 72px;

                padding: 25px 20px;

            }


            .top-header {

                align-items: flex-start;

            }


            .profile-info {

                display: none;

            }

        }


        @media(max-width: 600px) {

            .stats {

                grid-template-columns: 1fr 1fr;

                gap: 10px;

            }


            .stat-card {

                padding: 15px;

            }


            .stat-card h3 {

                font-size: 21px;

            }


            .welcome-text h1 {

                font-size: 22px;

            }


            .profile-button {

                padding: 8px;

            }


            .card {

                padding: 18px;

            }

        }


    </style>

</head>


<body>


<!-- =========================================================
     SIDEBAR
========================================================= -->

<aside class="sidebar">


    <!-- BRAND -->

    <div class="brand">

        <div class="brand-icon">

            <i class="ri-government-line"></i>

        </div>


        <div>

            <h2>
                Barangay Tabon
            </h2>

            <span>
                Resident Portal
            </span>

        </div>

    </div>



    <!-- MENU -->

    <nav class="sidebar-menu">


        <div class="menu-title">
            Main Menu
        </div>


        <a
            href="dashboard.php"
            class="active"
        >

            <i class="ri-dashboard-line"></i>

            <span>
                Dashboard
            </span>

        </a>


        <a href="request.php">

            <i class="ri-file-add-line"></i>

            <span>
                Request Document
            </span>

        </a>


        <a href="../verify.php">

            <i class="ri-shield-check-line"></i>

            <span>
                Verify Document
            </span>

        </a>


        <a href="document.php">

            <i class="ri-folder-shield-2-line"></i>

            <span>
                My Documents
            </span>

        </a>
        
        <a href="../MyWebsite.php">
    <i class="ri-home-5-line"></i>
    View Website
</a>
        


    </nav>



    <!-- BOTTOM -->

    <div class="sidebar-bottom">


        <div class="user-mini">

            <div class="user-mini-name">

                <?= e($_SESSION['full_name']) ?>

            </div>

            <div class="user-mini-role">

                Resident

            </div>

        </div>


        <a
            href="../logout.php"
            class="sidebar-menu logout"
            style="display:flex;"
        >

            <i class="ri-logout-box-r-line"></i>

            <span>
                Logout
            </span>

        </a>


    </div>


</aside>



<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main class="main">


    <!-- TOP HEADER -->

    <header class="top-header">


        <div class="welcome-text">

            <div class="eyebrow">
                Resident Portal
            </div>

            <h1>
                Welcome, <?= e($_SESSION['full_name']) ?>
            </h1>

            <p>
                Manage your Barangay Tabon digital services and documents.
            </p>

        </div>


        <div class="profile-button">

            <div class="profile-avatar">

                <i class="ri-user-3-line"></i>

            </div>


            <div class="profile-info">

                <strong>
                    <?= e($_SESSION['full_name']) ?>
                </strong>

                <span>
                    Verified Resident
                </span>

            </div>

        </div>


    </header>



    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="stats">


        <!-- TOTAL -->

        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-file-list-3-line"></i>

            </div>

            <h3>
                <?= $totalDocs ?>
            </h3>

            <p>
                Total Requests
            </p>

        </div>



        <!-- PENDING -->

        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-time-line"></i>

            </div>

            <h3>
                <?= $pendingDocs ?>
            </h3>

            <p>
                Pending Requests
            </p>

        </div>



        <!-- APPROVED -->

        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-checkbox-circle-line"></i>

            </div>

            <h3>
                <?= $approvedDocs ?>
            </h3>

            <p>
                Approved Documents
            </p>

        </div>



        <!-- RELEASED -->

        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-shield-check-line"></i>

            </div>

            <h3>
                <?= $releasedDocs ?>
            </h3>

            <p>
                Released Documents
            </p>

        </div>


    </section>



    <!-- =====================================================
         PROFILE + QUICK REQUEST
    ====================================================== -->

    <section class="content-grid">


        <!-- PROFILE -->

        <div class="card">


            <div class="card-header">

                <div>

                    <h2>
                        Resident Profile
                    </h2>

                    <p>
                        Your registered information
                    </p>

                </div>


                <div class="identity-status">

                    <i class="ri-checkbox-circle-fill"></i>

                    <?= e($resident['identity_status'] ?? 'Pending') ?>

                </div>

            </div>



            <div class="profile-box">


                <!-- NAME -->

                <div class="profile-row">

                    <i class="ri-user-line"></i>

                    <div>

                        <small>
                            Full Name
                        </small>

                        <strong>
                            <?= e($_SESSION['full_name']) ?>
                        </strong>

                    </div>

                </div>



                <!-- ADDRESS -->

                <div class="profile-row">

                    <i class="ri-map-pin-line"></i>

                    <div>

                        <small>
                            Address
                        </small>

                        <strong>
                            <?= e($resident['address'] ?? 'Not provided') ?>
                        </strong>

                    </div>

                </div>



                <!-- CONTACT -->

                <div class="profile-row">

                    <i class="ri-phone-line"></i>

                    <div>

                        <small>
                            Contact Number
                        </small>

                        <strong>
                            <?= e($resident['contact_number'] ?? 'Not provided') ?>
                        </strong>

                    </div>

                </div>



                <!-- BIRTH DATE -->

                <div class="profile-row">

                    <i class="ri-calendar-line"></i>

                    <div>

                        <small>
                            Birth Date
                        </small>

                        <strong>
                            <?= e($resident['birth_date'] ?? 'Not provided') ?>
                        </strong>

                    </div>

                </div>


            </div>


        </div>



        <!-- QUICK ACTION -->

        <div class="card">


            <div class="card-header">

                <div>

                    <h2>
                        Request a Document
                    </h2>

                    <p>
                        Start a new digital document request
                    </p>

                </div>


                <i
                    class="ri-file-add-line"
                    style="
                        font-size:28px;
                        color:#f5c542;
                    "
                ></i>

            </div>



            <p
                style="
                    color:#777;
                    font-size:12px;
                    line-height:1.7;
                    margin-bottom:20px;
                "
            >

                Request official Barangay documents online.
                Your request will be reviewed by an authorized
                Barangay officer.

            </p>


            <a
                href="request.php"
                class="request-link"
            >

                <div class="request-link-left">

                    <i class="ri-add-circle-line"></i>

                    <div>

                        <strong>
                            Create New Request
                        </strong>

                        <span>
                            Submit a document request
                        </span>

                    </div>

                </div>


                <i class="ri-arrow-right-line"></i>

            </a>



            <div
                style="
                    margin-top:12px;
                "
            >

                <a
                    href="../verify.php"
                    class="request-link"
                >

                    <div class="request-link-left">

                        <i class="ri-qr-scan-2-line"></i>

                        <div>

                            <strong>
                                Verify a Document
                            </strong>

                            <span>
                                Check document authenticity
                            </span>

                        </div>

                    </div>


                    <i class="ri-arrow-right-line"></i>

                </a>

            </div>


        </div>


    </section>



    <!-- =====================================================
         REQUESTS
    ====================================================== -->

    <section class="card requests-card">


        <div class="card-header">

            <div>

                <h2>
                    My Document Requests
                </h2>

                <p>
                    Track your digital document applications
                </p>

            </div>


            <a
                href="request.php"
                class="view-btn"
            >

                <i class="ri-add-line"></i>

                New Request

            </a>

        </div>



        <?php if ($docs): ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Document No.
                            </th>

                            <th>
                                Document
                            </th>

                            <th>
                                Purpose
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Blockchain
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach ($docs as $d): ?>


                        <tr>


                            <td>

                                <div class="doc-number">

                                    <?= e(
                                        $d['document_number']
                                        ?: '#' . $d['id']
                                    ) ?>

                                </div>

                            </td>


                            <td>

                                <div class="doc-type">

                                    <?= e($d['document_type']) ?>

                                </div>

                            </td>


                            <td>

                                <?= e($d['purpose']) ?>

                            </td>


                            <!-- STATUS -->

                            <td>


                                <?php

                                $status = $d['status'];

                                $statusClass = 'badge-pending';

                                $statusIcon = 'ri-time-line';

                                if ($status === 'Approved') {

                                    $statusClass = 'badge-approved';

                                    $statusIcon =
                                        'ri-checkbox-circle-line';

                                }

                                if ($status === 'Released') {

                                    $statusClass = 'badge-released';

                                    $statusIcon =
                                        'ri-shield-check-line';

                                }

                                if ($status === 'Rejected') {

                                    $statusClass = 'badge-rejected';

                                    $statusIcon =
                                        'ri-close-circle-line';

                                }

                                ?>


                                <span
                                    class="badge <?= $statusClass ?>"
                                >

                                    <i
                                        class="<?= $statusIcon ?>"
                                    ></i>

                                    <?= e($status) ?>

                                </span>


                            </td>



                            <!-- BLOCKCHAIN -->

                            <td>

                                <div class="blockchain">

                                    <i class="ri-links-line"></i>

                                    <?= e(
                                        $d['blockchain_status']
                                        ?: 'Pending'
                                    ) ?>

                                </div>

                            </td>



                            <!-- ACTION -->

                            <td>


                                <?php

                                if (
                                    $d['status'] === 'Released' ||
                                    $d['status'] === 'Approved'
                                ):

                                ?>


                                    <a
                                        href="../document.php?id=<?= (int)$d['id'] ?>"
                                        class="view-btn"
                                    >

                                        <i class="ri-eye-line"></i>

                                        View

                                    </a>


                                <?php else: ?>


                                    <span
                                        style="
                                            color:#555;
                                            font-size:10px;
                                        "
                                    >

                                        Not available

                                    </span>


                                <?php endif; ?>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php else: ?>


            <div class="empty">

                <i class="ri-file-search-line"></i>

                <h3>
                    No document requests yet
                </h3>

                <p>
                    Start by requesting your first Barangay document.
                </p>


                <a
                    href="request.php"
                    class="view-btn"
                    style="
                        margin-top:15px;
                    "
                >

                    <i class="ri-add-line"></i>

                    Request Document

                </a>

               

            </div>


        <?php endif; ?>


    </section>



</main>


</body>

</html>
