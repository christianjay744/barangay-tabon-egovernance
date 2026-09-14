<?php
session_start();

require '../config/db.php';

/*
|--------------------------------------------------------------------------
| LIVE DASHBOARD COUNTS
|--------------------------------------------------------------------------
*/

$count_total = (int) $pdo->query("
    SELECT COUNT(*)
    FROM document_requests
")->fetchColumn();

$count_pending = (int) $pdo->query("
    SELECT COUNT(*)
    FROM document_requests
    WHERE status = 'Pending'
")->fetchColumn();

$count_approved = (int) $pdo->query("
    SELECT COUNT(*)
    FROM document_requests
    WHERE status = 'Approved'
")->fetchColumn();

/*
| Count requests that already have blockchain information.
| A row is counted when it has a generated document hash OR a non-pending
| blockchain status.
*/
$count_blockchain = (int) $pdo->query("
    SELECT COUNT(*)
    FROM document_requests
    WHERE
        (document_hash IS NOT NULL AND TRIM(document_hash) <> '')
        OR
        (
            blockchain_status IS NOT NULL
            AND TRIM(blockchain_status) <> ''
            AND LOWER(TRIM(blockchain_status)) NOT IN ('pending', 'not generated', 'not recorded')
        )
")->fetchColumn();

/*
|--------------------------------------------------------------------------
| RECENT CLIENT REQUESTS
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        d.*,
        u.full_name
    FROM document_requests d
    JOIN users u ON u.id = d.user_id
    ORDER BY d.created_at DESC
    LIMIT 6
");

$stmt->execute();
$recent_requests = $stmt->fetchAll();

function statusClass($status)
{
    $status = strtolower(trim((string) $status));

    if (in_array($status, ['approved', 'released'], true)) {
        return 'approved';
    }

    if ($status === 'rejected') {
        return 'rejected';
    }

    return 'pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Administrator Dashboard | Barangay Tabon</title>

    <link rel="stylesheet" href="../assets/style.css">
    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css"
        rel="stylesheet"
    >

    <style>
        .admin-avatar {
            overflow: hidden;
        }

        .admin-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 50%;
        }

        .dashboard-request-type {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 8px;
            background: #474b4860;
            font-size: 13px;
            font-weight: 600;
        }

        .dashboard-actions {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 22px;
        }

        .dashboard-action-card {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border: 1px solid #3835358f;
            border-radius: 12px;
            color: inherit;
            text-decoration: none;
            background: #0c0c0c8c;
            transition: 0.2s ease;
        }

        .dashboard-action-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.8);
        }

        .dashboard-action-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: #000000d5;
            color: #0fa15d;
            font-size: 20px;
            flex-shrink: 0;
        }

        .dashboard-action-card strong,
        .dashboard-action-card span {
            display: block;
        }

        .dashboard-action-card span {
            margin-top: 3px;
            font-size: 12px;
            color: #e8ebec6e;
        }

        .blockchain-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 9px;
            border-radius: 999px;
            background: #3e41416e;
            color: #1f5fa8;
            font-size: 12px;
            font-weight: 700;
        }


        .dashboard-purpose {
            max-width: 230px;
            white-space: normal;
            line-height: 1.45;
        }

        .dashboard-action-link,
        .dashboard-action-muted {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        .dashboard-action-link {
            color: #19b66d;
            text-decoration: none;
            background: rgba(25, 182, 109, 0.12);
            border: 1px solid rgba(25, 182, 109, 0.25);
        }

        .dashboard-action-link:hover {
            background: rgba(25, 182, 109, 0.2);
        }

        .dashboard-action-muted {
            color: #9ca3af;
            background: rgba(156, 163, 175, 0.08);
            border: 1px solid rgba(156, 163, 175, 0.12);
        }

        @media (max-width: 700px) {
            .dashboard-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="review-page">

<!-- =========================================================
     SIDEBAR
========================================================= -->
<aside class="review-sidebar">

    <div class="review-logo">

        <div class="review-logo-icon">
            <i class="ri-government-line"></i>
        </div>

        <div>
            <strong>BARANGAY TABON</strong>
            <span>ADMIN PORTAL</span>
        </div>

    </div>

    <nav class="review-nav">

        <a href="dashboard.php" class="active">
            <i class="ri-dashboard-line"></i>
            Dashboard
        </a>

        <a href="review.php">
            <i class="ri-file-search-line"></i>
            Document Review

            <?php if ($count_pending > 0): ?>
                <span class="nav-badge">
                    <?= $count_pending ?>
                </span>
            <?php endif; ?>
        </a>

        <a href="../verify.php">
            <i class="ri-shield-check-line"></i>
            Verify Document
        </a>

        <a href="../MyWebsite.php?view=website">
    <i class="ri-home-5-line"></i>
    View Website
</a>

    </nav>

    <div class="sidebar-bottom">
        <a href="../logout.php" class="logout-link">
            <i class="ri-logout-box-r-line"></i>
            Logout
        </a>
    </div>

</aside>

<!-- =========================================================
     MAIN CONTENT
========================================================= -->
<main class="review-content">

    <!-- TOP BAR -->
    <header class="review-topbar">

        <div>
            <div class="admin-label">ADMINISTRATION</div>

            <h1>Administrator Dashboard</h1>

            <p>
                Monitor document requests and blockchain-protected records.
            </p>
        </div>

        <div class="admin-profile">

            <div class="admin-avatar">
                <img
                    src="barangay-tabon-seal.png"
                    alt="Barangay Tabon Seal"
                >
            </div>

            <div>
                <strong>Administrator</strong>
                <span>Barangay Staff</span>
            </div>

        </div>

    </header>

    <!-- =====================================================
         LIVE STATISTICS
    ====================================================== -->
    <section class="review-stats">

        <div class="review-stat">
            <div class="stat-icon total">
                <i class="ri-file-list-3-line"></i>
            </div>

            <div>
                <span>TOTAL DOCUMENT REQUESTS</span>
                <strong><?= number_format($count_total) ?></strong>
            </div>
        </div>

        <div class="review-stat">
            <div class="stat-icon pending">
                <i class="ri-time-line"></i>
            </div>

            <div>
                <span>PENDING REQUESTS</span>
                <strong><?= number_format($count_pending) ?></strong>
            </div>
        </div>

        <div class="review-stat">
            <div class="stat-icon approved">
                <i class="ri-checkbox-circle-line"></i>
            </div>

            <div>
                <span>APPROVED REQUESTS</span>
                <strong><?= number_format($count_approved) ?></strong>
            </div>
        </div>

        <div class="review-stat">
            <div class="stat-icon total">
                <i class="ri-links-line"></i>
            </div>

            <div>
                <span>BLOCKCHAIN RECORDS</span>
                <strong><?= number_format($count_blockchain) ?></strong>
            </div>
        </div>

    </section>

    <!-- =====================================================
         RECENT REQUESTS
    ====================================================== -->
    <section class="review-panel">

        <div class="panel-heading">

            <div>
                <span>RECENT ACTIVITY</span>
                <h2>Recent Document Requests</h2>
            </div>

            <div class="panel-icon">
                <i class="ri-file-history-line"></i>
            </div>

        </div>

        <div class="request-table-wrapper">

            <table class="request-table">

                <thead>
                    <tr>
                        <th>DOCUMENT</th>
                        <th>RESIDENT</th>
                        <th>TYPE</th>
                        <th>PURPOSE</th>
                        <th>DATE</th>
                        <th>STATUS</th>
                        <th>BLOCKCHAIN</th>
                        <th>ACTION</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($recent_requests) > 0): ?>

                    <?php foreach ($recent_requests as $request): ?>

                        <?php
                        $statusText = trim((string) ($request['status'] ?? 'Pending'));
                        if ($statusText === '') {
                            $statusText = 'Pending';
                        }

                        $status = strtolower($statusText);

                        $documentNumber = trim((string) ($request['document_number'] ?? ''));
                        $documentType = trim((string) ($request['document_type'] ?? ''));
                        $purpose = trim((string) ($request['purpose'] ?? ''));
                        $fullName = trim((string) ($request['full_name'] ?? ''));

                        if ($documentType === '') {
                            $documentType = 'Document Request';
                        }

                        if ($purpose === '') {
                            $purpose = 'N/A';
                        }

                        if ($fullName === '') {
                            $fullName = 'Unknown Resident';
                        }

                        $blockchainStatus = trim(
                            (string) ($request['blockchain_status'] ?? 'Pending')
                        );

                        if ($blockchainStatus === '') {
                            $blockchainStatus = 'Pending';
                        }
                        ?>

                        <tr>

                            <!-- DOCUMENT -->
                            <td>
                                <div class="document-cell">

                                    <div class="document-icon">
                                        <i class="ri-file-text-line"></i>
                                    </div>

                                    <div>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $documentNumber !== ''
                                                    ? $documentNumber
                                                    : 'Not assigned yet',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>

                                        <span>
                                            Request #<?= (int) $request['id'] ?>
                                        </span>
                                    </div>

                                </div>
                            </td>

                            <!-- RESIDENT -->
                            <td>
                                <div class="resident-cell">

                                    <div class="resident-avatar">
                                        <?= htmlspecialchars(
                                            strtoupper(substr($fullName, 0, 1)),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </div>

                                    <span>
                                        <?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>
                                    </span>

                                </div>
                            </td>

                            <!-- TYPE -->
                            <td>
                                <span class="dashboard-request-type">
                                    <?= htmlspecialchars($documentType, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <!-- PURPOSE -->
                            <td>
                                <div class="dashboard-purpose">
                                    <?= htmlspecialchars($purpose, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>

                            <!-- DATE -->
                            <td>
                                <span class="date-cell">
                                    <?= htmlspecialchars(
                                        (string) ($request['created_at'] ?? 'N/A'),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>
                            </td>

                            <!-- STATUS -->
                            <td>
                                <span class="request-status <?= htmlspecialchars(statusClass($status), ENT_QUOTES, 'UTF-8') ?>">
                                    <span></span>
                                    <?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <!-- BLOCKCHAIN -->
                            <td>
                                <span class="blockchain-badge">
                                    <i class="ri-links-line"></i>
                                    <?= htmlspecialchars($blockchainStatus, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>

                            <!-- ACTION -->
                            <td>

                                <?php if (in_array($status, ['approved', 'released'], true)): ?>

                                    <a
                                        href="../document.php?id=<?= (int) $request['id'] ?>"
                                        class="dashboard-action-link"
                                    >
                                        <i class="ri-eye-line"></i>
                                        View Document
                                    </a>

                                <?php elseif ($status === 'rejected'): ?>

                                    <span class="dashboard-action-muted">
                                        <i class="ri-close-circle-line"></i>
                                        Rejected
                                    </span>

                                <?php else: ?>

                                    <span class="dashboard-action-muted">
                                        <i class="ri-time-line"></i>
                                        Waiting for approval
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="8" class="empty-state">

                            <i class="ri-file-search-line"></i>

                            <strong>No document requests found</strong>

                            <span>
                                New resident requests will appear here.
                            </span>

                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

        <!-- QUICK ACTIONS -->
        <div class="dashboard-actions">

            <a href="review.php" class="dashboard-action-card">
                <div class="dashboard-action-icon">
                    <i class="ri-file-search-line"></i>
                </div>

                <div>
                    <strong>Review Document Requests</strong>
                    <span>Approve or reject resident requests.</span>
                </div>
            </a>

            <a href="../verify.php" class="dashboard-action-card">
                <div class="dashboard-action-icon">
                    <i class="ri-shield-check-line"></i>
                </div>

                <div>
                    <strong>Verify Documents</strong>
                    <span>Check document authenticity and hashes.</span>
                </div>
            </a>

        </div>

    </section>

    <!-- SECURITY NOTICE -->
    <section class="security-notice">

        <div class="security-icon">
            <i class="ri-shield-check-line"></i>
        </div>

        <div>
            <strong>Secure Document Management</strong>

            <p>
                Dashboard statistics are loaded directly from the
                document request database. Blockchain Records counts
                requests that already contain blockchain information.
            </p>
        </div>

    </section>

</main>

<script>
/* Refresh dashboard data every 15 seconds. */
setInterval(function () {
    window.location.reload();
}, 15000);
</script>

</body>
</html>


