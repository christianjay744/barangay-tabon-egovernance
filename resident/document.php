<?php

require '../config/db.php';
require '../config/auth.php';
require '../config/helpers.php';

require_login();

/* =========================================================
   RESIDENT ONLY
   ========================================================= */

if (($_SESSION['role'] ?? '') !== 'resident') {
    header('Location: ../admin/dashboard.php');
    exit;
}

/* =========================================================
   GET ALL DOCUMENT REQUESTS FOR LOGGED-IN RESIDENT
   ========================================================= */

$stmt = $pdo->prepare("\n    SELECT *\n    FROM document_requests\n    WHERE user_id = ?\n    ORDER BY created_at DESC, id DESC\n");

$stmt->execute([
    $_SESSION['user_id']
]);

$documents = $stmt->fetchAll();

/* =========================================================
   STATISTICS
   ========================================================= */

$total_documents = count($documents);
$pending_documents = 0;
$approved_documents = 0;
$released_documents = 0;
$rejected_documents = 0;

foreach ($documents as $document) {

    $status = strtolower(trim((string) ($document['status'] ?? 'pending')));

    if ($status === 'pending') {
        $pending_documents++;
    }

    if ($status === 'approved') {
        $approved_documents++;
    }

    if ($status === 'released') {
        $released_documents++;
    }

    if ($status === 'rejected') {
        $rejected_documents++;
    }
}

function document_status_class($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === 'approved') {
        return 'status-approved';
    }

    if ($status === 'released') {
        return 'status-released';
    }

    if ($status === 'rejected') {
        return 'status-rejected';
    }

    return 'status-pending';
}

function document_status_icon($status)
{
    $status = strtolower(trim((string) $status));

    if ($status === 'approved') {
        return 'ri-checkbox-circle-line';
    }

    if ($status === 'released') {
        return 'ri-shield-check-line';
    }

    if ($status === 'rejected') {
        return 'ri-close-circle-line';
    }

    return 'ri-time-line';
}

function format_request_date($value)
{
    if (empty($value)) {
        return 'N/A';
    }

    $timestamp = strtotime($value);

    if (!$timestamp) {
        return $value;
    }

    return date('M d, Y - h:i A', $timestamp);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>My Documents | Barangay Tabon</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --bg: #050505;
            --bg2: #0b0b0b;
            --card: #101010;
            --card2: #0d0d0d;
            --gold: #f5c542;
            --gold-light: #ffd96a;
            --white: #ffffff;
            --text: #eeeeee;
            --muted: #8d8d8d;
            --green: #31d07b;
            --blue: #4da3ff;
            --red: #ff6464;
            --border: rgba(255,255,255,.08);
        }

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
                radial-gradient(circle at 10% 10%, rgba(245,197,66,.08), transparent 28%),
                radial-gradient(circle at 90% 90%, rgba(245,197,66,.05), transparent 30%),
                var(--bg);
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(255,255,255,.012) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.012) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 18px 5%;
            background: rgba(5,5,5,.88);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .brand-icon {
            width: 44px;
            height: 44px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            color: #000;
            font-size: 22px;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
        }

        .brand strong {
            display: block;
            color: #fff;
            font-size: 14px;
            letter-spacing: 1px;
        }

        .brand span {
            display: block;
            margin-top: 3px;
            color: var(--gold);
            font-size: 10px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        .top-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 15px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: rgba(255,255,255,.035);
            color: white;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            transition: .2s ease;
        }

        .btn:hover {
            transform: translateY(-1px);
            border-color: rgba(245,197,66,.45);
            color: var(--gold);
        }

        .btn-gold {
            color: #000;
            border: none;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
        }

        .btn-gold:hover {
            color: #000;
        }

        .page {
            width: min(1180px, 92%);
            margin: 42px auto 80px;
            position: relative;
            z-index: 1;
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
        }

        .eyebrow {
            color: var(--gold);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2.5px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .page-header h1 {
            color: #fff;
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.1;
        }

        .page-header p {
            margin-top: 8px;
            color: var(--muted);
            font-size: 13px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            padding: 18px;
            border: 1px solid var(--border);
            border-radius: 15px;
            background: rgba(255,255,255,.025);
        }

        .stat-card i {
            display: inline-grid;
            place-items: center;
            width: 38px;
            height: 38px;
            margin-bottom: 14px;
            border-radius: 10px;
            color: #000;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            font-size: 18px;
        }

        .stat-card span {
            display: block;
            color: var(--muted);
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .stat-card strong {
            display: block;
            margin-top: 6px;
            color: #fff;
            font-size: 28px;
        }

        .panel {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: var(--card);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding: 22px;
            border-bottom: 1px solid var(--border);
        }

        .panel-header h2 {
            color: #fff;
            font-size: 18px;
        }

        .panel-header p {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 880px;
        }

        th,
        td {
            padding: 16px 18px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            color: #777;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 1.3px;
            text-transform: uppercase;
            background: #0b0b0b;
        }

        td {
            color: #d8d8d8;
            font-size: 13px;
        }

        tbody tr:hover {
            background: rgba(245,197,66,.025);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .doc-number {
            color: var(--gold-light);
            font-weight: 800;
            white-space: nowrap;
        }

        .doc-number small {
            display: block;
            margin-top: 4px;
            color: #666;
            font-size: 10px;
            font-weight: 500;
        }

        .doc-type {
            color: #fff;
            font-weight: 700;
        }

        .purpose {
            max-width: 260px;
            color: #a8a8a8;
            line-height: 1.5;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-pending {
            color: var(--gold-light);
            background: rgba(245,197,66,.08);
            border: 1px solid rgba(245,197,66,.18);
        }

        .status-approved {
            color: var(--blue);
            background: rgba(77,163,255,.08);
            border: 1px solid rgba(77,163,255,.18);
        }

        .status-released {
            color: var(--green);
            background: rgba(49,208,123,.08);
            border: 1px solid rgba(49,208,123,.18);
        }

        .status-rejected {
            color: var(--red);
            background: rgba(255,100,100,.08);
            border: 1px solid rgba(255,100,100,.18);
        }

        .action-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 9px 12px;
            border-radius: 9px;
            color: #000;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            text-decoration: none;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .action-muted {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #777;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }

        .empty {
            padding: 55px 20px;
            text-align: center;
            color: var(--muted);
        }

        .empty i {
            display: block;
            margin-bottom: 12px;
            color: var(--gold);
            font-size: 34px;
        }

        .empty strong {
            display: block;
            margin-bottom: 6px;
            color: #fff;
            font-size: 16px;
        }

        @media (max-width: 900px) {
            .stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 600px) {
            .topbar {
                padding: 14px 4%;
            }

            .brand > div:last-child {
                display: none;
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
                width: 94%;
                margin-top: 26px;
            }

            .page-header {
                align-items: flex-start;
                flex-direction: column;
            }

            .stats {
                grid-template-columns: 1fr 1fr;
            }

            .stat-card strong {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>

<header class="topbar">

    <div class="brand">
        <div class="brand-icon">
            <i class="ri-government-line"></i>
        </div>

        <div>
            <strong>BARANGAY TABON</strong>
            <span>Resident Portal</span>
        </div>
    </div>

    <div class="top-actions">
        <a href="dashboard.php" class="btn">
            <i class="ri-dashboard-line"></i>
            <span>Dashboard</span>
        </a>

        <a href="request.php" class="btn btn-gold">
            <i class="ri-file-add-line"></i>
            <span>New Request</span>
        </a>
    </div>

</header>

<main class="page">

    <div class="page-header">
        <div>
            <div class="eyebrow">Resident Documents</div>
            <h1>My Document Requests</h1>
            <p>View every document request you have submitted to Barangay Tabon.</p>
        </div>
    </div>

    <section class="stats">

        <div class="stat-card">
            <i class="ri-file-list-3-line"></i>
            <span>Total Requests</span>
            <strong><?= number_format($total_documents) ?></strong>
        </div>

        <div class="stat-card">
            <i class="ri-time-line"></i>
            <span>Pending</span>
            <strong><?= number_format($pending_documents) ?></strong>
        </div>

        <div class="stat-card">
            <i class="ri-checkbox-circle-line"></i>
            <span>Approved</span>
            <strong><?= number_format($approved_documents) ?></strong>
        </div>

        <div class="stat-card">
            <i class="ri-shield-check-line"></i>
            <span>Released</span>
            <strong><?= number_format($released_documents) ?></strong>
        </div>

    </section>

    <section class="panel">

        <div class="panel-header">
            <div>
                <h2>All Requested Documents</h2>
                <p>Your latest request appears first.</p>
            </div>
        </div>

        <?php if (count($documents) > 0): ?>

            <div class="table-wrap">

                <table>
                    <thead>
                        <tr>
                            <th>Document No.</th>
                            <th>Document Type</th>
                            <th>Purpose</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php foreach ($documents as $document): ?>

                        <?php
                        $status = $document['status'] ?? 'Pending';
                        $status_lower = strtolower(trim((string) $status));
                        $document_number = trim((string) ($document['document_number'] ?? ''));
                        ?>

                        <tr>

                            <td>
                                <div class="doc-number">
                                    <?= e($document_number !== '' ? $document_number : 'Not assigned yet') ?>
                                    <small>Request #<?= (int) $document['id'] ?></small>
                                </div>
                            </td>

                            <td>
                                <div class="doc-type">
                                    <?= e($document['document_type'] ?? 'Document Request') ?>
                                </div>
                            </td>

                            <td>
                                <div class="purpose">
                                    <?= e($document['purpose'] ?? 'N/A') ?>
                                </div>
                            </td>

                            <td>
                                <?= e(format_request_date($document['created_at'] ?? null)) ?>
                            </td>

                            <td>
                                <span class="status <?= e(document_status_class($status)) ?>">
                                    <i class="<?= e(document_status_icon($status)) ?>"></i>
                                    <?= e($status) ?>
                                </span>
                            </td>

                            <td>

                                <?php if (in_array($status_lower, ['approved', 'released'], true)): ?>

                                    <a
                                        href="../document.php?id=<?= (int) $document['id'] ?>"
                                        class="action-link"
                                    >
                                        <i class="ri-eye-line"></i>
                                        View Document
                                    </a>

                                <?php elseif ($status_lower === 'rejected'): ?>

                                    <span class="action-muted">
                                        <i class="ri-close-circle-line"></i>
                                        Rejected
                                    </span>

                                <?php else: ?>

                                    <span class="action-muted">
                                        <i class="ri-time-line"></i>
                                        Waiting for approval
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
                <i class="ri-file-list-3-line"></i>
                <strong>No document requests yet</strong>
                <span>Your submitted document requests will appear here.</span>

                <div style="margin-top:18px;">
                    <a href="request.php" class="btn btn-gold">
                        <i class="ri-file-add-line"></i>
                        Request a Document
                    </a>
                </div>
            </div>

        <?php endif; ?>

    </section>

</main>

</body>
</html>
