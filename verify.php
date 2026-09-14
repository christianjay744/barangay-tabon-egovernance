<?php
require 'config/db.php';

$doc = trim($_GET['doc'] ?? '');
$d = null;
$message = '';

if ($doc) {

    $s = $pdo->prepare("
        SELECT
            d.*,
            u.full_name
        FROM document_requests d
        JOIN users u ON u.id = d.user_id
        WHERE d.document_number = ?
        LIMIT 1
    ");

    $s->execute([$doc]);
    $d = $s->fetch();

    if (!$d) {
        $message = 'DOCUMENT NOT FOUND';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Document Verification | Barangay Tabon</title>

    <!-- Main CSS -->
    <link rel="stylesheet" href="assets/style.css">

    <!-- Remix Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css"
        rel="stylesheet"
    >



    <style>
        /* QR CAMERA SCANNER */
        .qr-scan-actions {
            margin-top: 16px;
            display: flex;
            justify-content: center;
        }

        .qr-scan-button,
        .qr-stop-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 18px;
            border: 0;
            border-radius: 10px;
            cursor: pointer;
            font: inherit;
            font-weight: 700;
        }

        .qr-scan-button {
            background: #f5c542;
            color: #111;
        }

        .qr-stop-button {
            margin-top: 14px;
            background: #2a2a2a;
            color: #fff;
        }

        .qr-scanner-card {
            display: none;
            margin-top: 24px;
            padding: 24px;
            border-radius: 16px;
            border: 1px solid rgba(255,255,255,.10);
            background: rgba(255,255,255,.03);
            text-align: center;
        }

        .qr-scanner-card.show {
            display: block;
        }

        .qr-scanner-card h3 {
            margin-bottom: 8px;
        }

        .qr-scanner-card p {
            margin-bottom: 18px;
            opacity: .75;
        }

        #qr-reader {
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
            overflow: hidden;
            border-radius: 12px;
            background: #fff;
        }

        #qr-scan-result {
            margin-top: 14px;
            min-height: 20px;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            .qr-scan-button,
            .qr-stop-button {
                width: 100%;
            }

            .qr-scanner-card {
                padding: 18px 12px;
            }
        }
    </style>

</head>


<body>


<div class="verification-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="verification-header">

        <div class="verification-brand">

            <div class="verification-logo">
                <i class="ri-government-line"></i>
            </div>

            <div>

                <strong>
                    BARANGAY TABON
                </strong>

                <span>
                    DIGITAL GOVERNMENT SERVICES
                </span>

            </div>

        </div>


        <a href="MyWebsite.php" class="back-home">

            <i class="ri-arrow-left-line"></i>

            Back to Home

        </a>

    </header>



    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="verification-main">


        <!-- HERO -->

        <section class="verification-hero">

            <div class="verification-shield">

                <i class="ri-shield-check-line"></i>

            </div>


            <div class="verification-label">
                DOCUMENT AUTHENTICATION CENTER
            </div>


            <h1>
                Verify a Document
            </h1>


            <p>
                Confirm the authenticity of a Barangay Tabon
                digital document using its unique document number.
            </p>

        </section>



        <!-- =================================================
             VERIFICATION CARD
        ================================================== -->

        <section class="verification-card">


            <div class="verification-card-header">

                <div>

                    <div class="small-label">
                        SECURE VERIFICATION
                    </div>

                    <h2>
                        Enter Document Number
                    </h2>

                </div>


                <div class="secure-badge">

                    <i class="ri-lock-line"></i>

                    Secure

                </div>

            </div>



            <!-- VERIFICATION FORM -->

            <form method="get" class="verification-form">


                <div class="input-wrapper">

                    <i class="ri-file-search-line"></i>

                    <input
                        type="text"
                        name="doc"
                        value="<?= htmlspecialchars($doc) ?>"
                        placeholder="BT-2026-000001"
                        autocomplete="off"
                        required
                    >

                </div>


                <button
                    type="submit"
                    class="verify-button">

                    <i class="ri-search-line"></i>

                    Verify Document

                </button>


            </form>



            <div class="verification-help">

                <i class="ri-information-line"></i>

                Enter the document number printed on the
                official Barangay document.

            </div>


            <div class="qr-scan-actions">

                <button
                    type="button"
                    class="qr-scan-button"
                    onclick="startQrScanner()"
                >
                    <i class="ri-qr-scan-2-line"></i>
                    Scan QR Code
                </button>

            </div>


        </section>


        <!-- QR CAMERA SCANNER -->

        <section class="qr-scanner-card" id="qrScannerCard">

            <h3>Scan Document QR Code</h3>

            <p>
                Allow camera access, then point the camera at the
                Barangay Tabon document QR code.
            </p>

            <div id="qr-reader"></div>

            <div id="qr-scan-result">
                Camera is not running.
            </div>

            <button
                type="button"
                class="qr-stop-button"
                onclick="stopQrScanner()"
            >
                <i class="ri-stop-circle-line"></i>
                Stop Scanner
            </button>

        </section>



        <?php if ($d): ?>

        <!-- =================================================
             SUCCESS RESULT
        ================================================== -->

        <section class="verification-result valid-result">


            <div class="result-icon">

                <i class="ri-checkbox-circle-line"></i>

            </div>


            <div class="result-content">

                <div class="verified-status">

                    <span></span>

                    AUTHENTIC DOCUMENT

                </div>


                <h2>
                    Record Found Successfully
                </h2>


                <p>
                    This document number exists in the
                    Barangay Tabon digital records.
                </p>

            </div>


            <div class="verified-status">

                <span></span>

                VERIFIED

            </div>


        </section>



        <!-- =================================================
             DOCUMENT INFORMATION
        ================================================== -->

        <section class="details-card">


            <div class="details-header">

                <div class="details-icon">

                    <i class="ri-file-text-line"></i>

                </div>


                <div>

                    <span>
                        VERIFIED RECORD
                    </span>

                    <h2>
                        Document Information
                    </h2>

                </div>

            </div>



            <div class="details-grid">


                <!-- DOCUMENT NUMBER -->

                <div class="detail-item">

                    <span>
                        Document Number
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $d['document_number']
                        ) ?>
                    </strong>

                </div>


                <!-- DOCUMENT TYPE -->

                <div class="detail-item">

                    <span>
                        Document Type
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $d['document_type']
                        ) ?>
                    </strong>

                </div>


                <!-- HOLDER -->

                <div class="detail-item">

                    <span>
                        Document Holder
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            $d['full_name']
                        ) ?>
                    </strong>

                </div>


                <!-- STATUS -->

                <div class="detail-item">

                    <span>
                        Status
                    </span>

                    <strong class="detail-status">

                        <span></span>

                        <?= htmlspecialchars(
                            $d['status']
                        ) ?>

                    </strong>

                </div>


                <!-- BLOCKCHAIN -->

                <div class="detail-item">

                    <span>
                        Blockchain Status
                    </span>

                    <strong class="detail-status">

                        <span></span>

                        <?= htmlspecialchars(
                            $d['blockchain_status']
                        ) ?>

                    </strong>

                </div>


                <!-- PURPOSE -->

                <div class="detail-item">

                    <span>
                        Purpose
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $d['purpose'] ?? 'N/A'
                        ) ?>

                    </strong>

                </div>


                <!-- CREATED -->

                <div class="detail-item">

                    <span>
                        Date Created
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $d['created_at'] ?? 'N/A'
                        ) ?>

                    </strong>

                </div>


            </div>

        </section>



        <!-- =================================================
             BLOCKCHAIN INTEGRITY
        ================================================== -->

        <section class="integrity-card">


            <div class="integrity-header">

                <div class="integrity-icon">

                    <i class="ri-links-line"></i>

                </div>


                <div>

                    <span>
                        DIGITAL INTEGRITY
                    </span>

                    <h2>
                        Blockchain Record
                    </h2>

                </div>

            </div>



            <!-- HASH -->

            <div class="hash-box">

                <div class="hash-title">

                    <i class="ri-fingerprint-line"></i>

                    SHA-256 DOCUMENT HASH

                </div>


                <code>

                    <?= htmlspecialchars(
                        $d['document_hash'] ?? 'N/A'
                    ) ?>

                </code>

            </div>



            <!-- BLOCKCHAIN STATUS -->

            <div class="blockchain-status">


                <div class="blockchain-icon">

                    <i class="ri-links-line"></i>

                </div>


                <div>

                    <strong>
                        Blockchain Protection
                    </strong>

                    <span>
                        Document integrity record
                    </span>

                </div>


                <div class="blockchain-indicator">

                    <span></span>

                    <?= htmlspecialchars(
                        $d['blockchain_status']
                    ) ?>

                </div>


            </div>


        </section>



        <?php elseif ($message): ?>


        <!-- =================================================
             NOT FOUND
        ================================================== -->

        <section class="verification-result invalid-result">


            <div class="result-icon">

                <i class="ri-close-circle-line"></i>

            </div>


            <div class="result-content">

                <div class="verified-status"
                     style="color:#ff5757;">

                    <span></span>

                    VERIFICATION FAILED

                </div>


                <h2>
                    Document Not Found
                </h2>


                <p>
                    No digital record was found for:
                    <strong>
                        <?= htmlspecialchars($doc) ?>
                    </strong>
                </p>

            </div>


        </section>


        <?php endif; ?>



        <!-- =================================================
             HOW IT WORKS
        ================================================== -->

        <section class="how-section">


            <div class="section-heading">

                <span>
                    SIMPLE & SECURE
                </span>

                <h2>
                    How Verification Works
                </h2>

            </div>



            <div class="steps">


                <!-- STEP 1 -->

                <div class="step">

                    <div class="step-number">
                        STEP 01
                    </div>

                    <i class="ri-file-search-line"></i>

                    <h3>
                        Enter Document Number
                    </h3>

                    <p>
                        Enter the unique document number
                        printed on your official document.
                    </p>

                </div>


                <!-- STEP 2 -->

                <div class="step">

                    <div class="step-number">
                        STEP 02
                    </div>

                    <i class="ri-database-2-line"></i>

                    <h3>
                        Check Digital Record
                    </h3>

                    <p>
                        The system searches the Barangay
                        digital records for a matching document.
                    </p>

                </div>


                <!-- STEP 3 -->

                <div class="step">

                    <div class="step-number">
                        STEP 03
                    </div>

                    <i class="ri-shield-check-line"></i>

                    <h3>
                        Confirm Authenticity
                    </h3>

                    <p>
                        The record and document hash can be
                        compared to confirm document integrity.
                    </p>

                </div>


            </div>


        </section>



        <!-- =================================================
             FOOTER
        ================================================== -->

        <footer class="verification-footer">

            <div>

                <i class="ri-shield-check-line"></i>

                Secure Digital Document Verification

            </div>


            <div>

                Barangay Tabon © 2026

            </div>

        </footer>


    </main>

</div>




<!-- QR Scanner Library -->
<script src="https://unpkg.com/html5-qrcode" defer></script>

<script>
let qrScanner = null;
let qrScannerStarting = false;

function setQrMessage(message) {
    const result = document.getElementById('qr-scan-result');
    if (result) {
        result.textContent = message;
    }
}

function extractDocumentNumber(decodedText) {
    const value = String(decodedText || '').trim();

    if (!value) {
        return '';
    }

    try {
        const url = new URL(value, window.location.href);
        const documentNumber = url.searchParams.get('doc');

        if (documentNumber) {
            return documentNumber.trim();
        }
    } catch (error) {
        // Not a URL; continue and treat the QR content as a document number.
    }

    return value;
}

async function startQrScanner() {
    const scannerCard = document.getElementById('qrScannerCard');

    if (scannerCard) {
        scannerCard.classList.add('show');
        scannerCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    if (qrScanner || qrScannerStarting) {
        return;
    }

    if (typeof Html5Qrcode === 'undefined') {
        setQrMessage('QR scanner library could not be loaded. Check your internet connection.');
        return;
    }

    qrScannerStarting = true;
    setQrMessage('Requesting camera access...');

    try {
        const cameras = await Html5Qrcode.getCameras();

        if (!cameras || cameras.length === 0) {
            setQrMessage('No camera was found on this device.');
            qrScannerStarting = false;
            return;
        }

        let cameraId = cameras[0].id;

        for (const camera of cameras) {
            const label = String(camera.label || '').toLowerCase();
            if (
                label.includes('back') ||
                label.includes('rear') ||
                label.includes('environment')
            ) {
                cameraId = camera.id;
                break;
            }
        }

        qrScanner = new Html5Qrcode('qr-reader');

        await qrScanner.start(
            cameraId,
            {
                fps: 10,
                qrbox: { width: 250, height: 250 }
            },
            async function(decodedText) {
                const documentNumber = extractDocumentNumber(decodedText);

                if (!documentNumber) {
                    setQrMessage('QR code was read, but no document number was found.');
                    return;
                }

                setQrMessage('QR code detected. Verifying document...');

                try {
                    await stopQrScanner(false);
                } catch (error) {
                    // Continue to verification even if camera shutdown reports an error.
                }

                window.location.href =
                    'verify.php?doc=' + encodeURIComponent(documentNumber);
            },
            function() {
                // Normal frame-by-frame scan misses are ignored.
            }
        );

        setQrMessage('Scanner ready. Point the camera at the QR code.');
    } catch (error) {
        qrScanner = null;
        setQrMessage('Camera permission was denied or the camera is unavailable.');
        console.error(error);
    } finally {
        qrScannerStarting = false;
    }
}

async function stopQrScanner(updateMessage = true) {
    if (!qrScanner) {
        if (updateMessage) {
            setQrMessage('Camera is not running.');
        }
        return;
    }

    const scannerToStop = qrScanner;
    qrScanner = null;

    try {
        if (scannerToStop.isScanning) {
            await scannerToStop.stop();
        }

        await scannerToStop.clear();
    } catch (error) {
        console.error(error);
    }

    if (updateMessage) {
        setQrMessage('Scanner stopped.');
    }
}
</script>

</body>

</html>