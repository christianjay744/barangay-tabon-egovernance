<?php

/* =========================================================
   BARANGAY TABON
   DIGITAL DOCUMENT VIEWER / VERIFICATION
   ========================================================= */

require 'config/db.php';
require 'config/auth.php';
require 'config/helpers.php';

require_login();


/* =========================================================
   GET DOCUMENT
   ========================================================= */

$id = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        d.*,
        u.full_name,
        r.address
    FROM document_requests d
    JOIN users u
        ON u.id = d.user_id
    LEFT JOIN residents r
        ON r.user_id = u.id
    WHERE d.id = ?
    LIMIT 1
");

$stmt->execute([$id]);

$d = $stmt->fetch();


/* =========================================================
   VALIDATION
   ========================================================= */

if (!$d) {
    exit('Document not found.');
}


/* =========================================================
   ACCESS CONTROL
   ========================================================= */

if (
    $_SESSION['role'] !== 'admin' &&
    (int) $d['user_id'] !== (int) $_SESSION['user_id']
) {
    exit('Forbidden.');
}


/* =========================================================
   DOCUMENT STATUS
   ========================================================= */

if (!in_array($d['status'], ['Approved', 'Released'], true)) {
    exit('Document is not available.');
}


/* =========================================================
   GENERATE SHA-256 DOCUMENT HASH
   ========================================================= */

if (empty($d['document_hash'])) {

    $content =
        'Barangay Tabon|' .
        ($d['document_number'] ?? '') . '|' .
        ($d['document_type'] ?? '') . '|' .
        ($d['full_name'] ?? '') . '|' .
        ($d['address'] ?? '') . '|' .
        ($d['purpose'] ?? '') . '|' .
        ($d['approved_at'] ?? '');

    $hash = hash('sha256', $content);


    /* Update document */

    $stmt = $pdo->prepare("
        UPDATE document_requests
        SET
            document_hash = ?,
            status = 'Released',
            released_at = NOW()
        WHERE id = ?
    ");

    $stmt->execute([
        $hash,
        $id
    ]);


    /* Update local values */

    $d['document_hash'] = $hash;
    $d['status'] = 'Released';


    /* =====================================================
       RECORD ON BLOCKCHAIN
       ===================================================== */

    require_once 'config/blockchain.php';

    $result = record_blockchain(
        $d['document_number'],
        $hash
    );


    $blockchainTx = $result['tx'] ?? null;
    $blockchainStatus = $result['status'] ?? 'Pending';


    /* Update blockchain information */

    $stmt = $pdo->prepare("
        UPDATE document_requests
        SET
            blockchain_tx = ?,
            blockchain_status = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $blockchainTx,
        $blockchainStatus,
        $id
    ]);


    /* Update local values */

    $d['blockchain_tx'] = $blockchainTx;
    $d['blockchain_status'] = $blockchainStatus;


    /* =====================================================
       ACTIVITY LOG
       ===================================================== */

    log_activity(
        $pdo,
        $_SESSION['user_id'],
        'DOCUMENT_RELEASED',
        'Released ' . $d['document_number']
    );
}


/* =========================================================
   VERIFICATION URL
   ========================================================= */

$verify = verify_url(
    $d['document_number']
);


/* =========================================================
   QR CODE
   ========================================================= */

$qr = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='
    . urlencode($verify);


/* =========================================================
   BLOCKCHAIN STATUS
   ========================================================= */

$blockchainStatus = $d['blockchain_status'] ?? 'Pending';

$isBlockchainVerified = in_array(
    strtolower($blockchainStatus),
    [
        'confirmed',
        'verified',
        'success'
    ],
    true
);


/* =========================================================
   DISPLAY STATUS
   ========================================================= */

$status = $d['status'] ?? 'Unknown';

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
        <?= e($d['document_type']) ?> |
        Barangay Tabon
    </title>


    <!-- Google Fonts -->

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
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap"
        rel="stylesheet"
    >


    <!-- Remix Icons -->

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css"
        rel="stylesheet"
    >


    <style>

        /* =================================================
           ROOT
        ================================================= */

        :root {

            --black: #050505;
            --black-2: #0b0b0b;
            --card: #111111;

            --gold: #f5c542;
            --gold-light: #ffd86b;
            --gold-dark: #a47a00;

            --white: #ffffff;
            --text: #eeeeee;
            --muted: #999999;

            --green: #31d07b;
            --red: #ff5c5c;

            --border: rgba(255,255,255,.08);
        }


        /* =================================================
           RESET
        ================================================= */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            min-height: 100vh;

            font-family: 'Inter', sans-serif;

            color: var(--text);

            background:

                radial-gradient(
                    circle at 15% 10%,
                    rgba(245,197,66,.12),
                    transparent 30%
                ),

                radial-gradient(
                    circle at 85% 80%,
                    rgba(245,197,66,.08),
                    transparent 30%
                ),

                var(--black);
        }


        /* =================================================
           GRID BACKGROUND
        ================================================= */

        body::before {

            content: "";

            position: fixed;

            inset: 0;

            pointer-events: none;

            background-image:

                linear-gradient(
                    rgba(255,255,255,.015) 1px,
                    transparent 1px
                ),

                linear-gradient(
                    90deg,
                    rgba(255,255,255,.015) 1px,
                    transparent 1px
                );

            background-size: 40px 40px;
        }


        /* =================================================
           TOP BAR
        ================================================= */

        .topbar {

            width: 100%;

            padding: 20px 5%;

            display: flex;

            align-items: center;

            justify-content: space-between;

            gap: 20px;

            position: sticky;

            top: 0;

            z-index: 100;

            background: rgba(5,5,5,.82);

            backdrop-filter: blur(20px);

            border-bottom: 1px solid var(--border);
        }


        /* =================================================
           BRAND
        ================================================= */

        .brand {

            display: flex;

            align-items: center;

            gap: 13px;
        }


        .brand-icon {

            width: 46px;

            height: 46px;

            flex-shrink: 0;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 13px;

            color: #000;

            font-size: 23px;

            background:
                linear-gradient(
                    135deg,
                    var(--gold-light),
                    var(--gold)
                );

            box-shadow:
                0 0 30px rgba(245,197,66,.18);
        }


        .brand-text h3 {

            color: white;

            font-size: 15px;

            font-weight: 800;

            letter-spacing: 1px;
        }


        .brand-text span {

            display: block;

            margin-top: 3px;

            color: var(--gold);

            font-size: 10px;

            text-transform: uppercase;

            letter-spacing: 2px;
        }


        /* =================================================
           TOP ACTIONS
        ================================================= */

        .top-actions {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        /* =================================================
           BUTTON
        ================================================= */

        .btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            padding: 11px 17px;

            border-radius: 10px;

            border: 1px solid var(--border);

            background: rgba(255,255,255,.04);

            color: white;

            text-decoration: none;

            font-size: 13px;

            font-weight: 600;

            cursor: pointer;

            transition: .25s ease;
        }


        .btn:hover {

            transform: translateY(-2px);

            border-color: rgba(245,197,66,.5);

            color: var(--gold);
        }


        .btn-gold {

            color: #000;

            border: none;

            background:
                linear-gradient(
                    135deg,
                    var(--gold-light),
                    var(--gold)
                );
        }


        .btn-gold:hover {

            color: #000;

            box-shadow:
                0 10px 35px rgba(245,197,66,.22);
        }


        /* =================================================
           PAGE
        ================================================= */

        .page {

            width: min(1100px, 92%);

            margin: 50px auto 80px;
        }


        /* =================================================
           PAGE HEADER
        ================================================= */

        .page-header {

            margin-bottom: 25px;

            display: flex;

            align-items: flex-end;

            justify-content: space-between;

            gap: 20px;
        }


        .eyebrow {

            margin-bottom: 8px;

            color: var(--gold);

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 3px;
        }


        .page-header h1 {

            color: white;

            font-size: clamp(26px, 4vw, 42px);

            line-height: 1.1;
        }


        .page-header p {

            margin-top: 8px;

            color: var(--muted);

            font-size: 14px;
        }


        /* =================================================
           DOCUMENT OUTER BORDER
        ================================================= */

        .document-wrapper {

            padding: 1px;

            position: relative;

            border-radius: 24px;

            background:
                linear-gradient(
                    135deg,
                    rgba(245,197,66,.6),
                    rgba(255,255,255,.05),
                    rgba(245,197,66,.2)
                );
        }


        /* =================================================
           DOCUMENT
        ================================================= */

        .document {

            position: relative;

            overflow: hidden;

            padding: 60px;

            border-radius: 23px;

            background:

                radial-gradient(
                    circle at 50% 0%,
                    rgba(245,197,66,.08),
                    transparent 35%
                ),

                #0d0d0d;
        }


        /* Gold side line */

        .document::after {

            content: "";

            position: absolute;

            top: 0;

            left: 0;

            width: 5px;

            height: 100%;

            background:
                linear-gradient(
                    var(--gold),
                    transparent,
                    var(--gold)
                );
        }


        /* Decorative star */

        .document::before {

            content: "✦";

            position: absolute;

            top: 20px;

            right: 35px;

            color: rgba(245,197,66,.035);

            font-size: 90px;

            pointer-events: none;
        }


        /* =================================================
           SEAL
        ================================================= */

        .seal {

            width: 100px;

            height: 100px;

            margin: 0 auto 22px;

            position: relative;

            display: flex;

            align-items: center;

            justify-content: center;

            border: 2px solid var(--gold);

            border-radius: 50%;

            color: var(--gold);

            box-shadow:
                0 0 0 7px rgba(245,197,66,.05),
                0 0 35px rgba(245,197,66,.10);
        }


        .seal::before {

            content: "";

            position: absolute;

            inset: 7px;

            border: 1px dashed rgba(245,197,66,.6);

            border-radius: 50%;
        }


        .seal i {

            font-size: 38px;

            position: relative;

            z-index: 2;
        }


        /* =================================================
           DOCUMENT HEADER
        ================================================= */

        .doc-header {

            padding-bottom: 35px;

            text-align: center;

            border-bottom: 1px solid rgba(245,197,66,.18);
        }


        .barangay {

            color: var(--gold);

            font-size: 13px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: 5px;
        }


        .doc-header h2 {

            margin: 13px 0;

            color: white;

            font-family: 'Playfair Display', serif;

            font-size: clamp(30px, 5vw, 50px);

            text-transform: uppercase;
        }


        .doc-number {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            margin-top: 8px;

            padding: 9px 15px;

            border: 1px solid rgba(245,197,66,.25);

            border-radius: 50px;

            color: var(--gold-light);

            background: rgba(245,197,66,.08);

            font-size: 12px;

            font-weight: 600;
        }


        /* =================================================
           STATUS
        ================================================= */

        .status-row {

            margin: 25px 0 35px;

            display: flex;

            align-items: center;

            justify-content: center;

            flex-wrap: wrap;

            gap: 10px;
        }


        .status {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 8px 13px;

            border-radius: 50px;

            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            letter-spacing: .5px;
        }


        .status-released {

            color: var(--green);

            background: rgba(49,208,123,.08);

            border: 1px solid rgba(49,208,123,.2);
        }


        .status-blockchain {

            color: var(--gold-light);

            background: rgba(245,197,66,.08);

            border: 1px solid rgba(245,197,66,.2);
        }


        /* =================================================
           CERTIFICATE BODY
        ================================================= */

        .certificate-body {

            max-width: 820px;

            margin: auto;

            text-align: center;
        }


        .intro {

            margin-bottom: 15px;

            color: var(--muted);

            font-size: 14px;

            letter-spacing: 1px;
        }


        .holder {

            margin: 8px 0 20px;

            color: var(--gold-light);

            font-family: 'Playfair Display', serif;

            font-size: clamp(26px, 4vw, 40px);
        }


        .description {

            color: #cfcfcf;

            font-size: 15px;

            line-height: 1.9;
        }


        .highlight {

            color: white;

            font-weight: 700;
        }


        /* =================================================
           INFORMATION GRID
        ================================================= */

        .info-grid {

            margin-top: 40px;

            display: grid;

            grid-template-columns: repeat(2, 1fr);

            gap: 14px;
        }


        .info-card {

            padding: 20px;

            text-align: left;

            border: 1px solid var(--border);

            border-radius: 14px;

            background: rgba(255,255,255,.025);

            transition: .25s ease;
        }


        .info-card:hover {

            transform: translateY(-2px);

            border-color: rgba(245,197,66,.3);

            background: rgba(245,197,66,.035);
        }


        .info-card i {

            display: block;

            margin-bottom: 12px;

            color: var(--gold);

            font-size: 21px;
        }


        .info-label {

            margin-bottom: 5px;

            color: #777;

            font-size: 10px;

            text-transform: uppercase;

            letter-spacing: 1.5px;
        }


        .info-value {

            color: white;

            font-size: 14px;

            font-weight: 600;

            word-break: break-word;
        }


        /* =================================================
           SECURITY
        ================================================= */

        .security-section {

            margin-top: 45px;

            padding-top: 35px;

            border-top: 1px solid var(--border);
        }


        .section-title {

            display: flex;

            align-items: center;

            gap: 10px;

            margin-bottom: 18px;

            color: white;

            font-size: 15px;

            font-weight: 700;
        }


        .section-title i {

            color: var(--gold);

            font-size: 20px;
        }


        /* =================================================
           HASH
        ================================================= */

        .hash-box {

            padding: 18px;

            overflow: hidden;

            border: 1px solid rgba(245,197,66,.15);

            border-radius: 13px;

            background: #080808;
        }


        .hash-label {

            margin-bottom: 9px;

            color: var(--gold);

            font-size: 10px;

            text-transform: uppercase;

            letter-spacing: 2px;
        }


        .hash {

            display: block;

            color: #aaa;

            font-family: monospace;

            font-size: 12px;

            line-height: 1.7;

            word-break: break-all;
        }


        /* =================================================
           BLOCKCHAIN
        ================================================= */

        .blockchain-card {

            margin-top: 15px;

            padding: 20px;

            display: grid;

            grid-template-columns: 50px 1fr;

            align-items: center;

            gap: 15px;

            border: 1px solid rgba(245,197,66,.18);

            border-radius: 15px;

            background:
                linear-gradient(
                    135deg,
                    rgba(245,197,66,.07),
                    rgba(255,255,255,.02)
                );
        }


        .blockchain-icon {

            width: 50px;

            height: 50px;

            display: flex;

            align-items: center;

            justify-content: center;

            border-radius: 13px;

            color: #000;

            font-size: 24px;

            background:
                linear-gradient(
                    135deg,
                    var(--gold-light),
                    var(--gold)
                );
        }


        .blockchain-title {

            margin-bottom: 5px;

            color: white;

            font-size: 13px;

            font-weight: 700;
        }


        .blockchain-status {

            color: var(--green);

            font-size: 12px;

            font-weight: 600;
        }


        .blockchain-pending {

            color: var(--gold-light);
        }


        .tx {

            margin-top: 7px;

            color: #777;

            font-family: monospace;

            font-size: 10px;

            line-height: 1.6;

            word-break: break-all;
        }


        /* =================================================
           VERIFICATION AREA
        ================================================= */

        .verification-area {

            margin-top: 45px;

            padding: 30px;

            display: grid;

            grid-template-columns: 1fr 280px;

            align-items: center;

            gap: 30px;

            border: 1px solid var(--border);

            border-radius: 18px;

            background: rgba(255,255,255,.025);
        }


        .verification-text h3 {

            margin-bottom: 10px;

            color: white;

            font-size: 21px;
        }


        .verification-text p {

            max-width: 550px;

            margin-bottom: 18px;

            color: var(--muted);

            font-size: 13px;

            line-height: 1.7;
        }


        /* =================================================
           QR CODE
        ================================================= */

        .qr-box {

            width: fit-content;

            margin-left: auto;

            padding: 15px;

            border-radius: 15px;

            background: white;

            box-shadow:
                0 0 35px rgba(245,197,66,.12);
        }


        .qr-box img {

            display: block;

            width: 220px;

            height: 220px;
        }




        .qr-scanner-section {
            display: none;
            margin-top: 25px;
            padding: 25px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: rgba(255,255,255,.025);
        }

        .qr-scanner-section.show {
            display: block;
        }

        .qr-scanner-section h3 {
            margin-bottom: 18px;
            color: white;
            font-size: 18px;
        }

        #qr-reader {
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
            overflow: hidden;
            border-radius: 14px;
            background: #ffffff;
        }

        #qr-result {
            margin-top: 15px;
            color: var(--muted);
            font-size: 13px;
            text-align: center;
        }

        .verification-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .scanner-stop {
            margin-top: 15px;
        }


        /* =================================================
           FOOTER
        ================================================= */

        .doc-footer {

            margin-top: 45px;

            padding-top: 25px;

            display: flex;

            justify-content: space-between;

            gap: 20px;

            border-top: 1px solid var(--border);

            color: #666;

            font-size: 11px;

            line-height: 1.6;
        }


        .official {

            color: var(--gold);

            font-weight: 700;
        }


        /* =================================================
           PRINT
        ================================================= */

        @media print {

            body {

                background: white;

                color: black;
            }


            body::before,
            .topbar,
            .page-header,
            .verification-area {

                display: none !important;
            }


            .page {

                width: 100%;

                margin: 0;
            }


            .document-wrapper {

                padding: 0;

                border: none;
            }


            .document {

                padding: 40px;

                border: 2px solid #222;

                border-radius: 0;

                background: white;

                color: black;

                box-shadow: none;
            }


            .document::before,
            .document::after {

                display: none;
            }


            .seal {

                color: #111;

                border-color: #222;

                box-shadow: none;
            }


            .barangay,
            .doc-header h2,
            .holder {

                color: #111;
            }


            .description,
            .intro {

                color: #333;
            }


            .info-card {

                border-color: #ccc;

                background: white;
            }


            .info-value {

                color: #111;
            }


            .security-section {

                border-color: #ccc;
            }


            .hash-box,
            .blockchain-card {

                border-color: #ccc;

                background: white;
            }


            .hash {

                color: #222;
            }


            .blockchain-status {

                color: #111;
            }
        }


        /* =================================================
           TABLET
        ================================================= */

        @media (max-width: 800px) {

            .document {

                padding: 40px 25px;
            }


            .page {

                width: 94%;

                margin-top: 30px;
            }


            .page-header {

                align-items: flex-start;

                flex-direction: column;
            }


            .info-grid {

                grid-template-columns: 1fr;
            }


            .verification-area {

                grid-template-columns: 1fr;

                text-align: center;
            }


            .verification-text p {

                margin-left: auto;

                margin-right: auto;
            }


            .qr-box {

                margin: auto;
            }


            .doc-footer {

                flex-direction: column;
            }
        }


        /* =================================================
           MOBILE
        ================================================= */

        @media (max-width: 550px) {

            .topbar {

                padding: 14px 4%;
            }


            .brand-text {

                display: none;
            }


            .top-actions {

                margin-left: auto;
            }


            .top-actions .btn span {

                display: none;
            }


            .top-actions .btn {

                width: 42px;

                height: 42px;

                padding: 0;
            }


            .page {

                width: 95%;

                margin-top: 20px;
            }


            .document {

                padding: 30px 17px;
            }


            .seal {

                width: 80px;

                height: 80px;
            }


            .seal i {

                font-size: 28px;
            }


            .barangay {

                font-size: 10px;

                letter-spacing: 3px;
            }


            .doc-header h2 {

                font-size: 30px;
            }


            .doc-number {

                font-size: 10px;

                max-width: 100%;

                word-break: break-word;

                text-align: center;
            }


            .holder {

                font-size: 27px;
            }


            .description {

                font-size: 14px;

                line-height: 1.8;
            }


            .verification-area {

                padding: 20px 15px;
            }


            .qr-box img {

                width: 190px;

                height: 190px;
            }


            .blockchain-card {

                grid-template-columns: 1fr;

                text-align: center;
            }


            .blockchain-icon {

                margin: auto;
            }
        }

    </style>

</head>


<body>


<!-- =====================================================
     TOP NAVIGATION
===================================================== -->

<header class="topbar">

    <div class="brand">

        <div class="brand-icon">

            <i class="ri-government-line"></i>

        </div>


        <div class="brand-text">

            <h3>
                BARANGAY TABON
            </h3>

            <span>
                E-Governance Portal
            </span>

        </div>

    </div>


    <div class="top-actions">

        <a
            href="javascript:history.back()"
            class="btn"
        >

            <i class="ri-arrow-left-line"></i>

            <span>
                Back
            </span>

        </a>


        <button
            type="button"
            onclick="window.print()"
            class="btn btn-gold"
        >

            <i class="ri-printer-line"></i>

            <span>
                Print Document
            </span>

        </button>

    </div>

</header>



<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="page">


    <!-- PAGE HEADER -->

    <div class="page-header">

        <div>

            <div class="eyebrow">
                Digital Document
            </div>

            <h1>
                Document Details
            </h1>

            <p>
                Secure electronic record with
                blockchain verification.
            </p>

        </div>

    </div>



    <!-- =================================================
         DOCUMENT
    ================================================= -->

    <div class="document-wrapper">

        <article class="document">


            <!-- SEAL -->

            <div class="seal">

                <i class="ri-government-line"></i>

            </div>



            <!-- DOCUMENT HEADER -->

            <div class="doc-header">

                <div class="barangay">

                    Republic of the Philippines

                </div>


                <h2>
                    Barangay Tabon
                </h2>


                <div class="barangay">

                    Bislig City

                </div>


                <div class="doc-number">

                    <i class="ri-file-shield-2-line"></i>

                    DOCUMENT NO.

                    <?= e($d['document_number']) ?>

                </div>

            </div>



            <!-- =================================================
                 STATUS
            ================================================= -->

            <div class="status-row">


                <div class="status status-released">

                    <i class="ri-checkbox-circle-fill"></i>

                    <?= e($status) ?>

                </div>


                <div class="status status-blockchain">

                    <i class="ri-links-line"></i>

                    Blockchain:

                    <?= e($blockchainStatus) ?>

                </div>

            </div>



            <!-- =================================================
                 CERTIFICATE BODY
            ================================================= -->

            <div class="certificate-body">


                <p class="intro">

                    THIS DOCUMENT CERTIFIES THAT

                </p>


                <div class="holder">

                    <?= e($d['full_name']) ?>

                </div>


                <p class="description">

                    residing at

                    <span class="highlight">

                        <?= e($d['address'] ?? 'Not provided') ?>

                    </span>

                    has officially requested and received

                    <span class="highlight">

                        <?= e($d['document_type']) ?>

                    </span>

                    from Barangay Tabon for the purpose of

                    <span class="highlight">

                        <?= e($d['purpose']) ?>

                    </span>.

                </p>



                <!-- =================================================
                     INFORMATION
                ================================================= -->

                <div class="info-grid">


                    <!-- HOLDER -->

                    <div class="info-card">

                        <i class="ri-user-3-line"></i>

                        <div class="info-label">
                            Document Holder
                        </div>

                        <div class="info-value">

                            <?= e($d['full_name']) ?>

                        </div>

                    </div>


                    <!-- DOCUMENT TYPE -->

                    <div class="info-card">

                        <i class="ri-file-text-line"></i>

                        <div class="info-label">
                            Document Type
                        </div>

                        <div class="info-value">

                            <?= e($d['document_type']) ?>

                        </div>

                    </div>


                    <!-- ADDRESS -->

                    <div class="info-card">

                        <i class="ri-map-pin-line"></i>

                        <div class="info-label">
                            Address
                        </div>

                        <div class="info-value">

                            <?= e($d['address'] ?? 'Not provided') ?>

                        </div>

                    </div>


                    <!-- APPROVED DATE -->

                    <div class="info-card">

                        <i class="ri-calendar-check-line"></i>

                        <div class="info-label">
                            Approved Date
                        </div>

                        <div class="info-value">

                            <?= e($d['approved_at'] ?? 'N/A') ?>

                        </div>

                    </div>

                </div>



                <!-- =================================================
                     DOCUMENT SECURITY
                ================================================= -->

                <section class="security-section">


                    <div class="section-title">

                        <i class="ri-shield-check-line"></i>

                        Document Security

                    </div>


                    <!-- SHA-256 -->

                    <div class="hash-box">

                        <div class="hash-label">

                            SHA-256 Document Hash

                        </div>


                        <code class="hash">

                            <?= e($d['document_hash']) ?>

                        </code>

                    </div>



                    <!-- BLOCKCHAIN -->

                    <div class="blockchain-card">


                        <div class="blockchain-icon">

                            <i class="ri-links-line"></i>

                        </div>


                        <div>

                            <div class="blockchain-title">

                                Blockchain Record

                            </div>


                            <?php if ($isBlockchainVerified): ?>

                                <div class="blockchain-status">

                                    <i class="ri-checkbox-circle-fill"></i>

                                    Blockchain Verified

                                </div>

                            <?php else: ?>

                                <div class="blockchain-status blockchain-pending">

                                    <i class="ri-time-line"></i>

                                    <?= e($blockchainStatus) ?>

                                </div>

                            <?php endif; ?>


                            <?php if (!empty($d['blockchain_tx'])): ?>

                                <div class="tx">

                                    Transaction:

                                    <?= e($d['blockchain_tx']) ?>

                                </div>

                            <?php endif; ?>

                        </div>

                    </div>

                </section>



                <!-- =================================================
                     QR VERIFICATION
                ================================================= -->

                <section class="verification-area">


                    <div class="verification-text">


                        <div class="section-title">

                            <i class="ri-qr-scan-2-line"></i>

                            Authenticity Verification

                        </div>


                        <h3>

                            Verify this document

                        </h3>


                        <p>

                            Scan the QR code using a mobile device
                            to open the official Barangay Tabon
                            verification page and check the
                            document record.

                        </p>


                        <div class="verification-actions">

                            <a
                                href="<?= e($verify) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn btn-gold"
                            >
                                <i class="ri-shield-check-line"></i>
                                Open Verification
                            </a>

                            <button
                                type="button"
                                class="btn"
                                onclick="startScanner()"
                            >
                                <i class="ri-qr-scan-2-line"></i>
                                Scan QR Code
                            </button>

                        </div>

                    </div>



                    <!-- QR -->

                    <div class="qr-box">

                        <img
                            src="<?= e($qr) ?>"
                            alt="Document Verification QR Code"
                        >

                    </div>

                </section>

                <section
                    class="qr-scanner-section"
                    id="scannerSection"
                >

                    <h3>
                        <i class="ri-camera-line"></i>
                        QR Code Scanner
                    </h3>

                    <div id="qr-reader"></div>

                    <p id="qr-result">
                        Point your camera at a printed QR code or a QR code shown on another device.
                    </p>

                    <div class="scanner-stop">
                        <button
                            type="button"
                            class="btn"
                            onclick="stopScanner()"
                        >
                            <i class="ri-close-circle-line"></i>
                            Stop Scanner
                        </button>
                    </div>

                </section>



                <!-- =================================================
                     FOOTER
                ================================================= -->

                <footer class="doc-footer">


                    <div>

                        <span class="official">

                            BARANGAY TABON

                        </span>

                        <br>

                        Digital E-Governance
                        Document System

                    </div>


                    <div>

                        This electronically issued document
                        contains a unique SHA-256 hash and
                        blockchain record for authenticity
                        verification.

                    </div>

                </footer>


            </div>

        </article>

    </div>

</main>




<script src="https://unpkg.com/html5-qrcode"></script>

<script>
let qrScanner = null;
let qrScannerRunning = false;

async function startScanner() {
    const scannerSection = document.getElementById('scannerSection');
    const result = document.getElementById('qr-result');

    scannerSection.classList.add('show');
    scannerSection.scrollIntoView({ behavior: 'smooth', block: 'center' });

    if (qrScannerRunning) {
        return;
    }

    if (typeof Html5Qrcode === 'undefined') {
        result.textContent = 'QR scanner library could not be loaded.';
        return;
    }

    try {
        qrScanner = qrScanner || new Html5Qrcode('qr-reader');

        const cameras = await Html5Qrcode.getCameras();

        if (!cameras || cameras.length === 0) {
            result.textContent = 'No camera was found on this device.';
            return;
        }

        let cameraId = cameras[0].id;

        for (const camera of cameras) {
            const label = (camera.label || '').toLowerCase();
            if (
                label.includes('back') ||
                label.includes('rear') ||
                label.includes('environment')
            ) {
                cameraId = camera.id;
                break;
            }
        }

        await qrScanner.start(
            cameraId,
            {
                fps: 10,
                qrbox: { width: 250, height: 250 }
            },
            async function(decodedText) {
                result.textContent = 'QR code detected. Opening verification page...';

                try {
                    await stopScanner(false);
                } catch (error) {
                    console.error(error);
                }

                window.location.href = decodedText;
            },
            function() {
                // Normal scan misses are ignored while the camera is running.
            }
        );

        qrScannerRunning = true;
        result.textContent = 'Scanner active. Point your camera at the QR code.';
    } catch (error) {
        console.error(error);
        qrScannerRunning = false;
        result.textContent = 'Camera access is unavailable or permission was denied.';
    }
}

async function stopScanner(hideSection = true) {
    const scannerSection = document.getElementById('scannerSection');
    const result = document.getElementById('qr-result');

    if (qrScanner && qrScannerRunning) {
        try {
            await qrScanner.stop();
            await qrScanner.clear();
        } catch (error) {
            console.error(error);
        }
    }

    qrScanner = null;
    qrScannerRunning = false;
    result.textContent = 'Scanner stopped.';

    if (hideSection) {
        scannerSection.classList.remove('show');
    }
}
</script>

</body>

</html>