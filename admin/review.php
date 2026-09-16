<?php
session_start();

require '../config/db.php';


/*
|--------------------------------------------------------------------------
| ENSURE EVERY REQUEST HAS A DOCUMENT NUMBER
| Format: BT-YYYY-000001
|--------------------------------------------------------------------------
|
| This also repairs older requests whose document_number is NULL/blank.
| The numeric part uses the request ID, so request #2 becomes 000002,
| request #3 becomes 000003, and so on.
|
*/
$missing_numbers = $pdo->query("
    SELECT id, created_at
    FROM document_requests
    WHERE document_number IS NULL
       OR TRIM(document_number) = ''
    ORDER BY id ASC
")->fetchAll(PDO::FETCH_ASSOC);

if ($missing_numbers) {

    $update_document_number = $pdo->prepare("
        UPDATE document_requests
        SET document_number = ?
        WHERE id = ?
          AND (
                document_number IS NULL
                OR TRIM(document_number) = ''
              )
    ");

    foreach ($missing_numbers as $row) {

        $request_id = (int) $row['id'];

        $request_year = date('Y');

        if (!empty($row['created_at'])) {

            $created_time = strtotime($row['created_at']);

            if ($created_time !== false) {
                $request_year = date('Y', $created_time);
            }
        }

        $document_number =
            'BT-' .
            $request_year .
            '-' .
            str_pad((string) $request_id, 6, '0', STR_PAD_LEFT);

        $update_document_number->execute([
            $document_number,
            $request_id
        ]);
    }
}

/*
|--------------------------------------------------------------------------
| PROCESS APPROVE / REJECT
|--------------------------------------------------------------------------
*/

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id = intval($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if ($id > 0 && in_array($action, ['approve', 'reject'])) {

        if ($action === 'approve') {

            $status = 'Approved';

        } else {

            $status = 'Rejected';

        }

        /*
        | Update the document request
        */
        $stmt = $pdo->prepare("
            UPDATE document_requests
            SET status = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $status,
            $id
        ]);

        $message = "Document request has been " . strtolower($status) . ".";
        $message_type = $action === 'approve' ? 'success' : 'danger';
    }
}


/*
|--------------------------------------------------------------------------
| GET REQUESTS
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');

$sql = "
    SELECT
        d.*,
        u.full_name
    FROM document_requests d
    JOIN users u ON u.id = d.user_id
    WHERE 1=1
";

$params = [];


/*
| Search
*/

if ($search !== '') {

    $sql .= "
        AND (
            d.document_number LIKE ?
            OR d.document_type LIKE ?
            OR u.full_name LIKE ?
        )
    ";

    $search_value = "%{$search}%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
}


/*
| Status filter
*/

if ($status_filter !== '') {

    $sql .= " AND d.status = ? ";

    $params[] = $status_filter;
}


$sql .= "
    ORDER BY d.created_at DESC
";


$stmt = $pdo->prepare($sql);

$stmt->execute($params);

$requests = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$count_total = $pdo->query("
    SELECT COUNT(*)
    FROM document_requests
")->fetchColumn();


$count_pending = $pdo->query("
    SELECT COUNT(*)
    FROM document_requests
    WHERE status = 'Pending'
")->fetchColumn();


$count_approved = $pdo->query("
    SELECT COUNT(*)
    FROM document_requests
    WHERE status = 'Approved'
")->fetchColumn();


$count_rejected = $pdo->query("
    SELECT COUNT(*)
    FROM document_requests
    WHERE status = 'Rejected'
")->fetchColumn();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Document Review | Barangay Tabon</title>

    <link rel="stylesheet" href="../assets/style.css">

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css"
        rel="stylesheet"
    >


    <style>
        
        .admin-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: 50%;
        }
        .view-button {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .action-muted {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            opacity: 0.7;
            white-space: nowrap;
        }

        .purpose {
            display: inline-block;
            max-width: 260px;
            line-height: 1.4;
        }

        /* PUROK CLEARANCE */
        .clearance-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #ffd700;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        .clearance-link:hover {
            text-decoration: underline;
        }

        .clearance-thumb {
            width: 58px;
            height: 58px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid rgba(255, 215, 0, .25);
            background: #0a0a0a;
            display: block;
        }

        .modal-clearance {
            margin-top: 20px;
            padding: 18px;
            border-radius: 12px;
            border: 1px solid rgba(255, 215, 0, .16);
            background: rgba(255, 215, 0, .035);
        }

        .modal-clearance-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
            color: #ffd700;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .5px;
        }

        .modal-clearance-image {
            display: block;
            width: 100%;
            max-height: 420px;
            object-fit: contain;
            border-radius: 10px;
            border: 1px solid #2b2b2b;
            background: #050505;
        }

        .modal-clearance-open {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 12px;
            color: #ffd700;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
        }

        .clearance-missing {
            color: #888;
            font-size: 12px;
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

            <strong>
                BARANGAY TABON
            </strong>

            <span>
                ADMIN PORTAL
            </span>

        </div>

    </div>


    <nav class="review-nav">

        <a href="dashboard.php">

            <i class="ri-dashboard-line"></i>

            Dashboard

        </a>


        <a href="review.php"
           class="active">

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


        <a href="../index.php">

            <i class="ri-home-5-line"></i>

            View Website

        </a>

    </nav>


    <div class="sidebar-bottom">

        <a href="../logout.php"
           class="logout-link">

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

            <div class="admin-label">
                ADMINISTRATION
            </div>

            <h1>
                Document Review
            </h1>

            <p>
                Review and manage resident document requests.
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

                <strong>
                    Administrator
                </strong>

                <span>
                    Barangay Staff
                </span>

            </div>

        </div>

    </header>



    <!-- =====================================================
         ALERT
    ====================================================== -->

    <?php if ($message): ?>

        <div class="review-alert <?= $message_type ?>">

            <i class="
                <?= $message_type === 'success'
                    ? 'ri-checkbox-circle-line'
                    : 'ri-error-warning-line'
                ?>
            "></i>

            <?= htmlspecialchars($message) ?>

        </div>

    <?php endif; ?>



    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <section class="review-stats">


        <div class="review-stat">

            <div class="stat-icon total">

                <i class="ri-file-list-3-line"></i>

            </div>

            <div>

                <span>
                    TOTAL REQUESTS
                </span>

                <strong>
                    <?= $count_total ?>
                </strong>

            </div>

        </div>



        <div class="review-stat">

            <div class="stat-icon pending">

                <i class="ri-time-line"></i>

            </div>

            <div>

                <span>
                    PENDING
                </span>

                <strong>
                    <?= $count_pending ?>
                </strong>

            </div>

        </div>



        <div class="review-stat">

            <div class="stat-icon approved">

                <i class="ri-checkbox-circle-line"></i>

            </div>

            <div>

                <span>
                    APPROVED
                </span>

                <strong>
                    <?= $count_approved ?>
                </strong>

            </div>

        </div>



        <div class="review-stat">

            <div class="stat-icon rejected">

                <i class="ri-close-circle-line"></i>

            </div>

            <div>

                <span>
                    REJECTED
                </span>

                <strong>
                    <?= $count_rejected ?>
                </strong>

            </div>

        </div>


    </section>



    <!-- =====================================================
         FILTER
    ====================================================== -->

    <section class="review-panel">


        <div class="panel-heading">

            <div>

                <span>
                    DOCUMENT MANAGEMENT
                </span>

                <h2>
                    Document Requests
                </h2>

            </div>


            <div class="panel-icon">

                <i class="ri-file-shield-2-line"></i>

            </div>

        </div>



        <form
            method="get"
            class="review-filter"
        >

            <div class="search-box">

                <i class="ri-search-line"></i>

                <input
                    type="text"
                    name="search"
                    value="<?= htmlspecialchars($search) ?>"
                    placeholder="Search document number, resident..."
                >

            </div>


            <select name="status">

                <option value="">
                    All Status
                </option>

                <option
                    value="Pending"
                    <?= $status_filter === 'Pending'
                        ? 'selected'
                        : '' ?>
                >
                    Pending
                </option>

                <option
                    value="Approved"
                    <?= $status_filter === 'Approved'
                        ? 'selected'
                        : '' ?>
                >
                    Approved
                </option>

                <option
                    value="Rejected"
                    <?= $status_filter === 'Rejected'
                        ? 'selected'
                        : '' ?>
                >
                    Rejected
                </option>

            </select>


            <button
                type="submit"
                class="filter-button"
            >

                <i class="ri-filter-3-line"></i>

                Filter

            </button>


            <a
                href="review.php"
                class="clear-button"
            >

                Clear

            </a>

        </form>



        <!-- =================================================
             REQUEST TABLE
        ================================================== -->

        <div class="request-table-wrapper">

            <table class="request-table">

                <thead>

                    <tr>

                        <th>
                            DOCUMENT
                        </th>

                        <th>
                            RESIDENT
                        </th>

                        <th>
                            TYPE
                        </th>

                        <th>
                            PURPOSE
                        </th>

                        <th>
                            PUROK CLEARANCE
                        </th>

                        <th>
                            DATE
                        </th>

                        <th>
                            STATUS
                        </th>

                        <th>
                            ACTION
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php if (count($requests) > 0): ?>

                    <?php foreach ($requests as $request): ?>

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
                                                trim((string)($request['document_number'] ?? '')) !== ''
                                                    ? $request['document_number']
                                                    : 'Not assigned yet'
                                            ) ?>

                                        </strong>

                                        <span>

                                            #<?= htmlspecialchars(
                                                $request['id']
                                            ) ?>

                                        </span>

                                    </div>

                                </div>

                            </td>


                            <!-- RESIDENT -->

                            <td>

                                <div class="resident-cell">

                                    <div class="resident-avatar">

                                        <?= strtoupper(
                                            substr(
                                                $request['full_name'],
                                                0,
                                                1
                                            )
                                        ) ?>

                                    </div>

                                    <span>

                                        <?= htmlspecialchars(
                                            $request['full_name']
                                        ) ?>

                                    </span>

                                </div>

                            </td>


                            <!-- TYPE -->

                            <td>

                                <span class="document-type">

                                    <?= htmlspecialchars(
                                        $request['document_type']
                                    ) ?>

                                </span>

                            </td>


                            <!-- PURPOSE -->

                            <td>

                                <span class="purpose">

                                    <?= htmlspecialchars(
                                        $request['purpose'] ?? 'N/A'
                                    ) ?>

                                </span>

                            </td>


                            <!-- PUROK CLEARANCE -->

                            <td>

                                <?php
                                $clearance_path = trim(
                                    (string)($request['purok_clearance_image'] ?? '')
                                );
                                ?>

                                <?php if ($clearance_path !== ''): ?>

                                    <a
                                        href="../<?= htmlspecialchars($clearance_path, ENT_QUOTES, 'UTF-8') ?>"
                                        target="_blank"
                                        rel="noopener"
                                        class="clearance-link"
                                        title="Open Purok Clearance image"
                                    >
                                        <img
                                            src="../<?= htmlspecialchars($clearance_path, ENT_QUOTES, 'UTF-8') ?>"
                                            alt="Purok Clearance"
                                            class="clearance-thumb"
                                        >
                                        <span>View</span>
                                    </a>

                                <?php else: ?>

                                    <span class="clearance-missing">
                                        No image
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- DATE -->

                            <td>

                                <span class="date-cell">

                                    <?= htmlspecialchars(
                                        $request['created_at']
                                        ?? 'N/A'
                                    ) ?>

                                </span>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php
                                $status = strtolower(
                                    $request['status'] ?? 'pending'
                                );
                                ?>

                                <span
                                    class="
                                        request-status
                                        <?= htmlspecialchars($status) ?>
                                    "
                                >

                                    <span></span>

                                    <?= htmlspecialchars(
                                        $request['status']
                                    ) ?>

                                </span>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <?php
                                $status_lower = strtolower(
                                    trim((string)($request['status'] ?? 'Pending'))
                                );
                                ?>

                                <?php if (in_array($status_lower, ['approved', 'released'], true)): ?>

                                    <a
                                        href="../document.php?id=<?= (int)$request['id'] ?>"
                                        class="view-button"
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

                                    <button
                                        type="button"
                                        class="view-button"
                                        onclick="openReview(<?= (int)$request['id'] ?>)"
                                    >
                                        <i class="ri-file-search-line"></i>
                                        Review
                                    </button>

                                <?php endif; ?>

                            </td>

                        </tr>



                        <!-- =================================================
                             REVIEW MODAL
                        ================================================== -->

                        <div
                            class="review-modal"
                            id="modal-<?= (int)$request['id'] ?>"
                        >

                            <div
                                class="modal-overlay"
                                onclick="closeReview(
                                    <?= (int)$request['id'] ?>
                                )"
                            ></div>


                            <div class="review-modal-box">


                                <button
                                    type="button"
                                    class="modal-close"
                                    onclick="closeReview(
                                        <?= (int)$request['id'] ?>
                                    )"
                                >

                                    <i class="ri-close-line"></i>

                                </button>



                                <!-- MODAL HEADER -->

                                <div class="modal-header">

                                    <div class="modal-document-icon">

                                        <i class="ri-file-shield-2-line"></i>

                                    </div>

                                    <div>

                                        <span>
                                            DOCUMENT REQUEST
                                        </span>

                                        <h2>
                                            Review Document
                                        </h2>

                                    </div>

                                </div>



                                <!-- DOCUMENT NUMBER -->

                                <div class="modal-number">

                                    <span>
                                        DOCUMENT NUMBER
                                    </span>

                                    <strong>

                                        <?= htmlspecialchars(
                                            $request['document_number']
                                        ) ?>

                                    </strong>

                                </div>



                                <!-- DETAILS -->

                                <div class="modal-details">


                                    <div>

                                        <span>
                                            Resident
                                        </span>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $request['full_name']
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div>

                                        <span>
                                            Document Type
                                        </span>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $request['document_type']
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div>

                                        <span>
                                            Purpose
                                        </span>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $request['purpose']
                                                ?? 'N/A'
                                            ) ?>

                                        </strong>

                                    </div>


                                    <div>

                                        <span>
                                            Current Status
                                        </span>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $request['status']
                                            ) ?>

                                        </strong>

                                    </div>


                                </div>



                                <!-- PUROK CLEARANCE -->

                                <div class="modal-clearance">

                                    <div class="modal-clearance-title">
                                        <i class="ri-image-line"></i>
                                        PUROK CLEARANCE
                                    </div>

                                    <?php
                                    $modal_clearance_path = trim(
                                        (string)($request['purok_clearance_image'] ?? '')
                                    );
                                    ?>

                                    <?php if ($modal_clearance_path !== ''): ?>

                                        <a
                                            href="../<?= htmlspecialchars($modal_clearance_path, ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <img
                                                src="../<?= htmlspecialchars($modal_clearance_path, ENT_QUOTES, 'UTF-8') ?>"
                                                alt="Purok Clearance uploaded by resident"
                                                class="modal-clearance-image"
                                            >
                                        </a>

                                        <a
                                            href="../<?= htmlspecialchars($modal_clearance_path, ENT_QUOTES, 'UTF-8') ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="modal-clearance-open"
                                        >
                                            <i class="ri-external-link-line"></i>
                                            Open full image
                                        </a>

                                    <?php else: ?>

                                        <div class="clearance-missing">
                                            No Purok Clearance image was uploaded for this request.
                                        </div>

                                    <?php endif; ?>

                                </div>


                                <!-- HASH -->

                                <div class="modal-hash">

                                    <div class="hash-label">

                                        <i class="ri-fingerprint-line"></i>

                                        DOCUMENT HASH

                                    </div>

                                    <code>

                                        <?= htmlspecialchars(
                                            $request['document_hash']
                                            ?? 'Not generated'
                                        ) ?>

                                    </code>

                                </div>



                                <!-- BLOCKCHAIN -->

                                <div class="modal-blockchain">

                                    <div class="blockchain-small-icon">

                                        <i class="ri-links-line"></i>

                                    </div>

                                    <div>

                                        <strong>
                                            Blockchain Status
                                        </strong>

                                        <span>

                                            <?= htmlspecialchars(
                                                $request['blockchain_status']
                                                ?? 'Pending'
                                            ) ?>

                                        </span>

                                    </div>

                                </div>



                                <!-- =================================================
                                     ADMIN DECISION
                                ================================================== -->

                                <?php if (
                                    strtolower(
                                        $request['status']
                                        ?? ''
                                    ) === 'pending'
                                ): ?>

                                    <form
                                        method="post"
                                        class="decision-form"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$request['id'] ?>"
                                        >


                                        <label>
                                            Admin Remarks
                                        </label>


                                        <textarea
                                            name="remarks"
                                            placeholder="Enter review remarks..."
                                        ></textarea>


                                        <div class="decision-buttons">


                                            <button
                                                type="submit"
                                                name="action"
                                                value="reject"
                                                class="reject-button"
                                                onclick="
                                                    return confirm(
                                                        'Reject this document request?'
                                                    );
                                                "
                                            >

                                                <i class="ri-close-circle-line"></i>

                                                Reject

                                            </button>


                                            <button
                                                type="submit"
                                                name="action"
                                                value="approve"
                                                class="approve-button"
                                                onclick="
                                                    return confirm(
                                                        'Approve this document request?'
                                                    );
                                                "
                                            >

                                                <i class="ri-checkbox-circle-line"></i>

                                                Approve Document

                                            </button>


                                        </div>

                                    </form>

                                <?php else: ?>

                                    <div class="already-reviewed">

                                        <i class="ri-information-line"></i>

                                        This request has already been reviewed.

                                    </div>

                                <?php endif; ?>


                            </div>

                        </div>


                    <?php endforeach; ?>


                <?php else: ?>

                    <tr>

                        <td
                            colspan="8"
                            class="empty-state"
                        >

                            <i class="ri-file-search-line"></i>

                            <strong>
                                No document requests found
                            </strong>

                            <span>
                                There are no requests matching your search.
                            </span>

                        </td>

                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>



    <!-- =====================================================
         SECURITY NOTICE
    ====================================================== -->

    <section class="security-notice">

        <div class="security-icon">

            <i class="ri-shield-check-line"></i>

        </div>

        <div>

            <strong>
                Secure Document Management
            </strong>

            <p>
                Approved documents can be verified through
                the public Document Verification Center.
                Document hashes provide an additional
                integrity check for the prototype.
            </p>

        </div>

    </section>


</main>



<!-- =========================================================
     JAVASCRIPT
========================================================= -->

<script>

function openReview(id) {

    const modal = document.getElementById(
        'modal-' + id
    );

    if (modal) {

        modal.classList.add('show');

        document.body.classList.add('modal-open');

    }

}


function closeReview(id) {

    const modal = document.getElementById(
        'modal-' + id
    );

    if (modal) {

        modal.classList.remove('show');

        document.body.classList.remove('modal-open');

    }

}


/*
| ESC closes modal
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (event.key === 'Escape') {

            document
                .querySelectorAll('.review-modal.show')
                .forEach(function(modal) {

                    modal.classList.remove('show');

                });

            document.body.classList.remove('modal-open');

        }

    }
);

</script>


</body>

</html>
