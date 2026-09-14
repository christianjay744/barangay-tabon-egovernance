<?php
require 'config/db.php';
require 'config/auth.php';

// Allow logged-in users to view the public website
$viewWebsite = isset($_GET['view']) && $_GET['view'] === 'website';

// Only administrators can publish new Barangay activities.
// Normalize the role so values such as Admin / ADMIN / Administrator also work.
$sessionRole = strtolower(trim((string) ($_SESSION['role'] ?? '')));
$isAdmin = !empty($_SESSION['user_id'])
    && in_array($sessionRole, ['admin', 'administrator'], true);

// Fallback: read the current user's role from the database if the session role
// is missing or uses an unexpected value. This keeps the Post New Activity
// button visible for real administrator accounts.
if (!$isAdmin && !empty($_SESSION['user_id'])) {
    try {
        $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
        $roleStmt->execute([(int) $_SESSION['user_id']]);
        $databaseRole = strtolower(trim((string) $roleStmt->fetchColumn()));

        $isAdmin = in_array($databaseRole, ['admin', 'administrator'], true);
    } catch (Throwable $roleError) {
        // Keep the page working even if the users table/role field differs.
    }
}

$activityMessage = '';
$activityMessageType = '';
$activities = [];

/* =========================================================
   BARANGAY ACTIVITY POSTS
   ========================================================= */
try {
    // This creates the activity table automatically the first time the page runs.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barangay_activities (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(180) NOT NULL,
            category VARCHAR(100) NOT NULL DEFAULT 'COMMUNITY ACTIVITY',
            description TEXT NULL,
            image_path VARCHAR(255) NOT NULL,
            created_by INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // One activity can contain many pictures. Existing posts continue to use
    // barangay_activities.image_path as their primary/fallback picture.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barangay_activity_images (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            activity_id INT UNSIGNED NOT NULL,
            image_path VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_activity_id (activity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Handle activity deletion from an administrator.
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['delete_activity'])
    ) {
        if (!$isAdmin) {
            http_response_code(403);
            $activityMessage = 'Only administrators can delete Barangay activities.';
            $activityMessageType = 'error';
        } else {
            $activityId = (int) ($_POST['activity_id'] ?? 0);

            if ($activityId <= 0) {
                throw new RuntimeException('Invalid activity selected.');
            }

            $deleteLookup = $pdo->prepare("
                SELECT image_path
                FROM barangay_activities
                WHERE id = ?
                LIMIT 1
            ");
            $deleteLookup->execute([$activityId]);
            $activityToDelete = $deleteLookup->fetch(PDO::FETCH_ASSOC);

            if (!$activityToDelete) {
                throw new RuntimeException('Activity not found.');
            }

            // Collect every picture attached to this post.
            $deleteImagesLookup = $pdo->prepare("
                SELECT image_path
                FROM barangay_activity_images
                WHERE activity_id = ?
                ORDER BY sort_order ASC, id ASC
            ");
            $deleteImagesLookup->execute([$activityId]);
            $pathsToDelete = $deleteImagesLookup->fetchAll(PDO::FETCH_COLUMN);

            // Keep backward compatibility for older one-picture posts.
            $primaryPath = (string) ($activityToDelete['image_path'] ?? '');
            if ($primaryPath !== '') {
                $pathsToDelete[] = $primaryPath;
            }
            $pathsToDelete = array_values(array_unique($pathsToDelete));

            $pdo->beginTransaction();
            try {
                $deleteImages = $pdo->prepare("
                    DELETE FROM barangay_activity_images
                    WHERE activity_id = ?
                ");
                $deleteImages->execute([$activityId]);

                $deleteActivity = $pdo->prepare("
                    DELETE FROM barangay_activities
                    WHERE id = ?
                ");
                $deleteActivity->execute([$activityId]);

                $pdo->commit();
            } catch (Throwable $deleteError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $deleteError;
            }

            // Remove all uploaded files belonging to the deleted post.
            $allowedPrefix = 'assets/uploads/activities/';
            foreach ($pathsToDelete as $pathToDelete) {
                $relativeImagePath = str_replace('\\', '/', (string) $pathToDelete);

                if (str_starts_with($relativeImagePath, $allowedPrefix)) {
                    $absoluteImagePath = __DIR__ . '/' . $relativeImagePath;

                    if (is_file($absoluteImagePath)) {
                        @unlink($absoluteImagePath);
                    }
                }
            }

            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?view=website&deleted=1#events');
            exit;
        }
    }

    // Handle a new activity post from an administrator.
    if (
        $_SERVER['REQUEST_METHOD'] === 'POST'
        && isset($_POST['post_activity'])
    ) {
        if (!$isAdmin) {
            http_response_code(403);
            $activityMessage = 'Only administrators can post Barangay activities.';
            $activityMessageType = 'error';
        } else {
            $title = trim((string) ($_POST['activity_title'] ?? ''));
            $category = trim((string) ($_POST['activity_category'] ?? 'COMMUNITY ACTIVITY'));
            $description = trim((string) ($_POST['activity_description'] ?? ''));

            if ($title === '') {
                throw new RuntimeException('Please enter an activity title.');
            }

            if (empty($_FILES['activity_images'])) {
                throw new RuntimeException('Please choose at least one picture to upload.');
            }

            $files = $_FILES['activity_images'];
            $fileNames = $files['name'] ?? [];
            $tmpNames = $files['tmp_name'] ?? [];
            $errors = $files['error'] ?? [];
            $sizes = $files['size'] ?? [];

            if (!is_array($fileNames)) {
                $fileNames = [$fileNames];
                $tmpNames = [$tmpNames];
                $errors = [$errors];
                $sizes = [$sizes];
            }

            $selectedIndexes = [];
            foreach ($fileNames as $index => $originalName) {
                $errorCode = $errors[$index] ?? UPLOAD_ERR_NO_FILE;
                if ($errorCode !== UPLOAD_ERR_NO_FILE) {
                    $selectedIndexes[] = $index;
                }
            }

            if (count($selectedIndexes) === 0) {
                throw new RuntimeException('Please choose at least one picture to upload.');
            }

            if (count($selectedIndexes) > 10) {
                throw new RuntimeException('You can upload a maximum of 10 pictures in one activity post.');
            }

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ];

            $uploadRelativeDir = 'assets/uploads/activities';
            $uploadAbsoluteDir = __DIR__ . '/' . $uploadRelativeDir;

            if (!is_dir($uploadAbsoluteDir)) {
                if (!mkdir($uploadAbsoluteDir, 0775, true) && !is_dir($uploadAbsoluteDir)) {
                    throw new RuntimeException('Unable to create the activity upload folder.');
                }
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $savedImagePaths = [];

            try {
                foreach ($selectedIndexes as $position => $index) {
                    $errorCode = $errors[$index] ?? UPLOAD_ERR_NO_FILE;
                    $tmpName = (string) ($tmpNames[$index] ?? '');
                    $fileSize = (int) ($sizes[$index] ?? 0);

                    if ($errorCode !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('One of the pictures could not be uploaded. Please try again.');
                    }

                    if ($fileSize > 5 * 1024 * 1024) {
                        throw new RuntimeException('Each picture must be 5 MB or smaller.');
                    }

                    $mime = $finfo->file($tmpName);
                    if (!isset($allowedTypes[$mime])) {
                        throw new RuntimeException('Only JPG, PNG, and WEBP pictures are allowed.');
                    }

                    $fileName = 'activity_' . date('Ymd_His') . '_'
                        . bin2hex(random_bytes(5)) . '_' . ($position + 1)
                        . '.' . $allowedTypes[$mime];

                    $absolutePath = $uploadAbsoluteDir . '/' . $fileName;
                    $relativePath = $uploadRelativeDir . '/' . $fileName;

                    if (!move_uploaded_file($tmpName, $absolutePath)) {
                        throw new RuntimeException('Unable to save one of the uploaded pictures.');
                    }

                    $savedImagePaths[] = $relativePath;
                }

                $pdo->beginTransaction();

                $insertActivity = $pdo->prepare("
                    INSERT INTO barangay_activities
                        (title, category, description, image_path, created_by)
                    VALUES
                        (?, ?, ?, ?, ?)
                ");

                // The first picture is also stored as image_path for compatibility
                // with existing code and older database records.
                $insertActivity->execute([
                    $title,
                    $category !== '' ? $category : 'COMMUNITY ACTIVITY',
                    $description,
                    $savedImagePaths[0],
                    (int) $_SESSION['user_id'],
                ]);

                $activityId = (int) $pdo->lastInsertId();

                $insertImage = $pdo->prepare("
                    INSERT INTO barangay_activity_images
                        (activity_id, image_path, sort_order)
                    VALUES
                        (?, ?, ?)
                ");

                foreach ($savedImagePaths as $position => $relativePath) {
                    $insertImage->execute([
                        $activityId,
                        $relativePath,
                        $position,
                    ]);
                }

                $pdo->commit();
            } catch (Throwable $uploadError) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                foreach ($savedImagePaths as $savedPath) {
                    $absoluteSavedPath = __DIR__ . '/' . $savedPath;
                    if (is_file($absoluteSavedPath)) {
                        @unlink($absoluteSavedPath);
                    }
                }

                throw $uploadError;
            }

            // Prevent duplicate posts when the page is refreshed.
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?view=website&posted=1#events');
            exit;
        }
    }

    if (isset($_GET['posted']) && $_GET['posted'] === '1') {
        $activityMessage = 'New Barangay activity posted successfully.';
        $activityMessageType = 'success';
    }

    if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
        $activityMessage = 'Barangay activity deleted successfully.';
        $activityMessageType = 'success';
    }

    $activityQuery = $pdo->query("
        SELECT id, title, category, description, image_path, created_at
        FROM barangay_activities
        ORDER BY created_at DESC, id DESC
        LIMIT 12
    ");

    $activities = $activityQuery->fetchAll(PDO::FETCH_ASSOC);

    // Load all pictures for the retrieved posts. If a post predates the
    // multi-picture feature, fall back to its original image_path value.
    if (!empty($activities)) {
        $activityIds = array_map(
            static fn(array $activity): int => (int) $activity['id'],
            $activities
        );

        $placeholders = implode(',', array_fill(0, count($activityIds), '?'));
        $imagesStmt = $pdo->prepare("
            SELECT activity_id, image_path
            FROM barangay_activity_images
            WHERE activity_id IN ($placeholders)
            ORDER BY activity_id ASC, sort_order ASC, id ASC
        ");
        $imagesStmt->execute($activityIds);

        $imagesByActivity = [];
        foreach ($imagesStmt->fetchAll(PDO::FETCH_ASSOC) as $imageRow) {
            $imagesByActivity[(int) $imageRow['activity_id']][] = $imageRow['image_path'];
        }

        foreach ($activities as &$activity) {
            $activityId = (int) $activity['id'];
            $activity['images'] = $imagesByActivity[$activityId] ?? [];

            if (empty($activity['images']) && !empty($activity['image_path'])) {
                $activity['images'][] = $activity['image_path'];
            }
        }
        unset($activity);
    }

} catch (Throwable $e) {
    $activityMessage = $e->getMessage();
    $activityMessageType = 'error';
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

    <title>Barangay Tabon | Official Website</title>

    <!-- Remix Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7f6;
            color: #222;
            line-height: 1.6;
        }

        /* =========================
           HEADER / NAVIGATION
        ========================== */

        header {
            width: 100%;
            background: #134e3f;
            position: sticky;
            top: 0;
            z-index: 1000;

            box-shadow:
                0 3px 15px rgba(0, 0, 0, 0.12);
        }

        .navbar {
            max-width: 1200px;
            margin: auto;

            display: flex;
            justify-content: space-between;
            align-items: center;

            padding: 15px 25px;
        }

    .logo {
    display: flex;
    align-items: center;
    gap: 12px;

    color: white;
    text-decoration: none;
}

.logo-image {
    width: 90px;
    height: 90px;

    display: flex;
    align-items: center;
    justify-content: center;

    background: white;

    border-radius: 50%;

    overflow: hidden;

    box-shadow: 0 3px 10px rgba(0, 0, 0, 0.18);
}

.logo-image img {
    width: 100%;
    height: 100%;

    object-fit: contain;

    padding: .1px;
}

.logo-text {
    display: flex;
    flex-direction: column;
}

.logo-text strong {
    font-size: 18px;
    font-weight: 700;

    letter-spacing: 0.5px;
}

.logo-text span {
    font-size: 11px;

    letter-spacing: 1.5px;

    opacity: 0.8;
}

@media (max-width: 600px) {

    .logo-image {
        width: 45px;
        height: 45px;
    }

    .logo-text strong {
        font-size: 14px;
    }

    .logo-text span {
        font-size: 9px;
    }
}

           

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-links a {
            color: white;
            text-decoration: none;

            padding: 9px 13px;
            border-radius: 6px;

            font-size: 14px;

            transition: 0.2s;
        }

        .nav-links a:hover {
            background: rgba(255,255,255,0.12);
        }

        .nav-login {
            background: #ffffff !important;
            color: #065f46 !important;
            font-weight: bold;
        }


        /* =========================
           HERO
        ========================== */

        .hero {
            min-height: 620px;

            display: flex;
            align-items: center;

            position: relative;

            background:
                linear-gradient(
                    rgba(12, 100, 74, 0.82),
                    rgba(8, 78, 58, 0.82)
                ),
                url('assets/images/barangay-hall.jpg')
                center / cover no-repeat;

            color: white;
        }

        .hero-content {
            max-width: 1200px;
            width: 100%;

            margin: auto;
            padding: 80px 25px;
        }

        .hero-label {
            display: inline-block;

            background: rgba(255,255,255,0.15);

            border: 1px solid rgba(255,255,255,0.25);

            padding: 8px 16px;

            border-radius: 50px;

            margin-bottom: 20px;

            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .hero h1 {
            max-width: 750px;

            font-size: 54px;

            line-height: 1.1;

            margin-bottom: 20px;
        }

        .hero p {
            max-width: 670px;

            font-size: 18px;

            color: #e5f4ef;

            margin-bottom: 30px;
        }

        .hero-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;

            padding: 13px 22px;

            border-radius: 8px;

            text-decoration: none;

            font-weight: bold;

            transition: 0.2s;
        }

        .btn-primary {
            background: white;
            color: #1c7e62;
        }

        .btn-primary:hover {
            background: #e8f5ef;
        }

        .btn-outline {
            border: 1px solid rgba(255,255,255,0.6);
            color: white;
        }

        .btn-outline:hover {
            background: rgba(255,255,255,0.1);
        }


        /* =========================
           GENERAL SECTION
        ========================== */

        section {
            padding: 80px 25px;
        }

        .section-container {
            max-width: 1200px;
            margin: auto;
        }

        .section-heading {
            text-align: center;
            margin-bottom: 45px;
        }

        .section-heading span {
            color: #067a58;

            font-size: 13px;
            font-weight: bold;

            text-transform: uppercase;

            letter-spacing: 2px;
        }

        .section-heading h2 {
            font-size: 36px;

            color: #19352d;

            margin-top: 5px;
        }

        .section-heading p {
            max-width: 680px;

            margin: 10px auto 0;

            color: #66726e;
        }


        /* =========================
           ABOUT
        ========================== */

        .about {
            background: white;
        }

        .about-grid {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 55px;

            align-items: center;
        }

        .about-image img {
            width: 100%;
            height: 420px;

            object-fit: cover;

            border-radius: 16px;

            box-shadow:
                0 15px 35px rgba(0,0,0,0.12);
        }

        .about-content h2 {
            font-size: 36px;

            color: #064e3b;

            margin-bottom: 18px;
        }

        .about-content p {
            color: #5b6662;

            margin-bottom: 16px;
        }

        .about-features {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 14px;

            margin-top: 25px;
        }

        .about-feature {
            display: flex;
            gap: 10px;
            align-items: center;

            background: #f1f8f5;

            padding: 14px;

            border-radius: 9px;

            color: #065f46;

            font-weight: bold;
        }

        .about-feature i {
            font-size: 22px;
        }


        /* =========================
           EVENTS
        ========================== */

        .events {
            background: #f5f7f6;
        }

        .event-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 25px;
        }

        .event-card {
            background: white;

            border-radius: 13px;

            overflow: hidden;

            box-shadow:
                0 5px 20px rgba(0,0,0,0.08);

            transition: 0.25s;
        }

        .event-card:hover {
            transform: translateY(-5px);

            box-shadow:
                0 12px 30px rgba(0,0,0,0.12);
        }

        .event-image {
            height: 220px;
            overflow: hidden;
        }

        .event-image img {
            width: 100%;
            height: 100%;

            object-fit: cover;

            transition: 0.3s;
        }

        .event-card:hover .event-image img {
            transform: scale(1.04);
        }

        .event-content {
            padding: 22px;
        }

        .event-category {
            display: inline-block;

            background: #e8f5ef;
            color: #087454;

            padding: 5px 10px;

            border-radius: 20px;

            font-size: 11px;
            font-weight: bold;

            margin-bottom: 11px;
        }

        .event-content h3 {
            font-size: 20px;

            margin-bottom: 8px;

            color: #183b30;
        }

        .event-content p {
            color: #68736f;
            font-size: 14px;
        }


        /* =========================
           PROGRAMS
        ========================== */

        .programs {
            background: #1f5e4e;
            color: white;
        }

        .programs .section-heading h2 {
            color: white;
        }

        .programs .section-heading span {
            color: #a7f3d0;
        }

        .programs .section-heading p {
            color: #cbe4dc;
        }

        .program-grid {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;
        }

        .program-card {
            background: rgba(255,255,255,0.08);

            border: 1px solid
                rgba(255,255,255,0.12);

            padding: 28px 22px;

            border-radius: 12px;

            transition: 0.25s;
        }

        .program-card:hover {
            background: rgba(255,255,255,0.13);

            transform: translateY(-4px);
        }

        .program-icon {
            width: 55px;
            height: 55px;

            background: white;
            color: #087454;

            border-radius: 12px;

            display: flex;
            justify-content: center;
            align-items: center;

            font-size: 28px;

            margin-bottom: 18px;
        }

        .program-card h3 {
            margin-bottom: 9px;
        }

        .program-card p {
            color: #cee4dc;
            font-size: 14px;
        }


        /* =========================
           DOCUMENTARY / GALLERY
        ========================== */

        .documentary {
            background: white;
        }

        .gallery {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            grid-auto-rows: 230px;

            gap: 12px;
        }

        .gallery-item {
            position: relative;
            overflow: hidden;

            border-radius: 10px;
        }

        .gallery-item img {
            width: 100%;
            height: 100%;

            object-fit: cover;

            transition: 0.3s;
        }

        .gallery-item:hover img {
            transform: scale(1.07);
        }

        .gallery-item.large {
            grid-column: span 2;
            grid-row: span 2;
        }

        .gallery-overlay {
            position: absolute;

            left: 0;
            right: 0;
            bottom: 0;

            padding: 50px 18px 16px;

            background:
                linear-gradient(
                    transparent,
                    rgba(0,0,0,0.75)
                );

            color: white;
        }

        .gallery-overlay strong {
            display: block;
        }

        .gallery-overlay span {
            font-size: 12px;
            opacity: 0.85;
        }


        /* =========================
           SERVICES / VERIFY
        ========================== */

        .services {
            background: #f1f7f4;
        }

        .service-box {
            background: white;

            padding: 45px;

            border-radius: 16px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 40px;

            box-shadow:
                0 8px 30px rgba(0,0,0,0.06);
        }

        .service-box h2 {
            color: #064e3b;

            font-size: 30px;

            margin-bottom: 8px;
        }

        .service-box p {
            color: #66726e;

            max-width: 650px;
        }

        .verify-btn {
            white-space: nowrap;

            background: #087454;
            color: white;

            padding: 14px 23px;

            border-radius: 8px;

            text-decoration: none;

            font-weight: bold;
        }


        /* =========================
           CONTACT
        ========================== */

        .contact {
            background: white;
        }

        .contact-grid {
            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;
        }

        .contact-card {
            padding: 30px;

            background: #f5f8f7;

            border-radius: 10px;

            text-align: center;
        }

        .contact-card i {
            width: 55px;
            height: 55px;

            margin: auto auto 15px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            background: #087454;
            color: white;

            font-size: 25px;
        }

        .contact-card h3 {
            color: #064e3b;
            margin-bottom: 5px;
        }

        .contact-card p {
            color: #68736f;
            font-size: 14px;
        }


        /* =========================
           FOOTER
        ========================== */

        footer {
            background: #166b56;
            color: #d3e5df;

            padding: 35px 25px;
        }

        .footer-content {
            max-width: 1200px;
            margin: auto;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 20px;
        }

        footer strong {
            color: white;
        }

        footer p {
            font-size: 13px;
        }


        /* =========================
           ACTIVITY POSTING
        ========================== */

        .activity-admin-tools {
            margin: -20px auto 35px;
            max-width: 900px;
        }

        .activity-admin-row {
            display: flex;
            justify-content: center;
            margin-bottom: 18px;
        }

        .post-activity-button {
            border: 0;
            border-radius: 9px;
            padding: 13px 20px;
            background: #087454;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .post-activity-button:hover {
            background: #065f46;
        }

        .activity-post-panel {
            display: none;
            background: #fff;
            border: 1px solid #dfe9e5;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 10px 30px rgba(0,0,0,.08);
        }

        .activity-post-panel.show {
            display: block;
        }

        .activity-post-panel h3 {
            color: #064e3b;
            margin-bottom: 18px;
        }

        .activity-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 15px;
        }

        .activity-field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .activity-field.full {
            grid-column: 1 / -1;
        }

        .activity-field label {
            color: #24483e;
            font-size: 13px;
            font-weight: 700;
        }

        .activity-field input,
        .activity-field textarea {
            width: 100%;
            border: 1px solid #cfdcd7;
            border-radius: 8px;
            padding: 12px 13px;
            font: inherit;
            outline: none;
            background: #fbfdfc;
        }

        .activity-field input:focus,
        .activity-field textarea:focus {
            border-color: #087454;
            box-shadow: 0 0 0 3px rgba(8,116,84,.10);
        }

        .activity-field textarea {
            min-height: 110px;
            resize: vertical;
        }

        .activity-form-actions {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 5px;
        }

        .activity-submit {
            border: 0;
            border-radius: 8px;
            padding: 12px 18px;
            background: #087454;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }

        .activity-cancel {
            border: 1px solid #cfdcd7;
            border-radius: 8px;
            padding: 12px 18px;
            background: #fff;
            color: #42534e;
            font-weight: 700;
            cursor: pointer;
        }

        .activity-message {
            max-width: 900px;
            margin: -15px auto 25px;
            padding: 13px 16px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
        }

        .activity-message.success {
            color: #075f46;
            background: #dff7ed;
            border: 1px solid #b7ead7;
        }

        .activity-message.error {
            color: #a11919;
            background: #fff0f0;
            border: 1px solid #f2c3c3;
        }

        .event-date {
            display: block;
            margin-top: 12px;
            color: #89938f;
            font-size: 12px;
        }

        .activity-upload-help {
            color: #6d7a76;
            font-size: 12px;
            line-height: 1.5;
        }

        .activity-picture-picker {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .activity-file-input {
            position: absolute;
            width: 1px !important;
            height: 1px;
            padding: 0 !important;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0 !important;
        }

        .activity-add-pictures-button,
        .activity-clear-pictures-button {
            border-radius: 8px;
            padding: 11px 15px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .activity-add-pictures-button {
            border: 0;
            background: #087454;
            color: #fff;
        }

        .activity-add-pictures-button:hover {
            background: #065f46;
        }

        .activity-clear-pictures-button {
            border: 1px solid #d7e2de;
            background: #fff;
            color: #4e625b;
        }

        .activity-clear-pictures-button:hover {
            border-color: #bdccc6;
            background: #f7faf9;
        }

        .activity-picture-previews {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(135px, 1fr));
            gap: 12px;
            margin-top: 4px;
        }

        .activity-picture-preview {
            position: relative;
            min-width: 0;
            overflow: hidden;
            border: 1px solid #dce7e3;
            border-radius: 11px;
            background: #f7faf9;
        }

        .activity-picture-preview img {
            display: block;
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .activity-picture-preview-name {
            display: block;
            padding: 8px 10px;
            color: #53665f;
            font-size: 11px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .activity-remove-picture {
            position: absolute;
            top: 7px;
            right: 7px;
            width: 29px;
            height: 29px;
            border: 0;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: rgba(20, 27, 25, .88);
            color: #fff;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0,0,0,.18);
        }

        .activity-remove-picture:hover {
            background: #b42323;
        }

        .activity-photo-limit {
            color: #a11919;
            font-weight: 700;
        }

        .event-photo-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 3px;
            background: #e6ece9;
        }

        .event-photo-grid.single {
            grid-template-columns: 1fr;
        }

        .event-photo-grid img {
            width: 100%;
            height: 155px;
            display: block;
            object-fit: cover;
        }

        .event-photo-grid.single img {
            height: 220px;
        }

        .event-photo-count {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            color: #61706b;
            font-size: 12px;
            font-weight: 600;
        }

        .activity-delete-form {
            margin-top: 14px;
        }

        .activity-delete-button {
            width: 100%;
            border: 1px solid #efb6b6;
            border-radius: 8px;
            padding: 10px 12px;
            background: #fff5f5;
            color: #b42323;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .activity-delete-button:hover {
            background: #b42323;
            color: #fff;
            border-color: #b42323;
        }

        .empty-activity-card {
            grid-column: 1 / -1;
            padding: 35px;
            text-align: center;
            background: #fff;
            border: 1px dashed #cfdcd7;
            border-radius: 13px;
            color: #68736f;
        }

        /* =========================
           RESPONSIVE
        ========================== */

        @media (max-width: 950px) {

            .nav-links a:not(.nav-login) {
                display: none;
            }

            .hero h1 {
                font-size: 42px;
            }

            .about-grid {
                grid-template-columns: 1fr;
            }

            .event-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .program-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .gallery {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .contact-grid {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 650px) {

            .activity-form-grid {
                grid-template-columns: 1fr;
            }

            .activity-form-actions {
                flex-direction: column-reverse;
            }

            .activity-submit,
            .activity-cancel {
                width: 100%;
            }

            .navbar {
                padding: 12px 16px;
            }

            .logo-text strong {
                font-size: 15px;
            }

            .logo-text span {
                font-size: 9px;
            }

            .hero {
                min-height: 570px;
            }

            .hero h1 {
                font-size: 36px;
            }

            .hero p {
                font-size: 16px;
            }

            section {
                padding: 60px 18px;
            }

            .section-heading h2 {
                font-size: 29px;
            }

            .event-grid {
                grid-template-columns: 1fr;
            }

            .program-grid {
                grid-template-columns: 1fr;
            }

            .gallery {
                grid-template-columns: 1fr;
                grid-auto-rows: 260px;
            }

            .gallery-item.large {
                grid-column: span 1;
                grid-row: span 1;
            }

            .service-box {
                padding: 30px 22px;

                flex-direction: column;

                align-items: flex-start;
            }

            .footer-content {
                flex-direction: column;

                text-align: center;
            }

        }

    </style>
</head>


<body>


<!-- =========================
     NAVIGATION
========================= -->

<header>

    <nav class="navbar">

      <a href="#home" class="logo">

    <div class="logo-image">
        <img src="assets/images/barangay-tabon-seal.png" alt="Barangay Tabon Seal">
    </div>

    <div class="logo-text">
        <strong>BARANGAY TABON</strong>
        <span>E-GOVERNANCE PORTAL</span>
    </div>

</a>


        <div class="nav-links">

            <a href="#home">
                Home
            </a>

            <a href="#about">
                About
            </a>

            <a href="#events">
                Events
            </a>

            <a href="#programs">
                Programs
            </a>

            <a href="#gallery">
                Gallery
            </a>

            <a href="verify.php">
                Verify
            </a>

            <?php if (!empty($_SESSION['user_id'])): ?>

                <?php if ($isAdmin): ?>

                    <a
                        href="admin/dashboard.php"
                        class="nav-login"
                    >
                        Dashboard
                    </a>

                <?php else: ?>

                    <a
                        href="resident/dashboard.php"
                        class="nav-login"
                    >
                        Dashboard
                    </a>

                <?php endif; ?>

            <?php else: ?>

                <a
                    href="login.php"
                    class="nav-login"
                >
                    Login
                </a>

            <?php endif; ?>

        </div>

    </nav>

</header>


<!-- =========================
     HERO
========================= -->

<section class="hero" id="home">

    <div class="hero-content">

        <span class="hero-label">
            OFFICIAL BARANGAY INFORMATION PORTAL
        </span>

        <h1>
            Welcome to Barangay Tabon
        </h1>

        <p>
            Building a transparent, accessible, and connected
            community through digital governance, public services,
            community programs, and secure document verification.
        </p>

        <div class="hero-buttons">

            <a
                href="#events"
                class="btn btn-primary"
            >
                <i class="ri-calendar-event-line"></i>
                View Events
            </a>

            <a
                href="verify.php"
                class="btn btn-outline"
            >
                <i class="ri-shield-check-line"></i>
                Verify Document
            </a>

        </div>

    </div>

</section>


<!-- =========================
     ABOUT
========================= -->

<section class="about" id="about">

    <div class="section-container">

        <div class="about-grid">

            <div class="about-image">

                <img
                    src="assets/images/barangay-hall.jpg"
                    alt="Barangay Tabon Hall"
                >

            </div>


            <div class="about-content">

                <span
                    style="
                        color:#087454;
                        font-weight:bold;
                        font-size:13px;
                    "
                >
                    ABOUT OUR BARANGAY
                </span>

                <h2>
                    Barangay Tabon
                </h2>

                <p>
                    Barangay Tabon is committed to providing
                    accessible public services and maintaining
                    transparent communication between the barangay
                    government and its residents.
                </p>

                <p>
                    Through the e-Governance portal, residents can
                    access barangay information, learn about
                    community programs, view events, and verify
                    official documents online.
                </p>

                <div class="about-features">

                    <div class="about-feature">
                        <i class="ri-shield-check-line"></i>
                        Secure Services
                    </div>

                    <div class="about-feature">
                        <i class="ri-community-line"></i>
                        Community First
                    </div>

                    <div class="about-feature">
                        <i class="ri-eye-line"></i>
                        Transparency
                    </div>

                    <div class="about-feature">
                        <i class="ri-customer-service-2-line"></i>
                        Public Service
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     EVENTS
========================= -->

<section class="events" id="events">

    <div class="section-container">

        <div class="section-heading">

            <span>
                COMMUNITY ACTIVITIES
            </span>

            <h2>
                Barangay Events
            </h2>

            <p>
                Photos and documentation from activities,
                celebrations, meetings, and community projects.
            </p>

        </div>

        <?php if ($activityMessage !== ''): ?>
            <div class="activity-message <?= htmlspecialchars($activityMessageType) ?>">
                <?= htmlspecialchars($activityMessage) ?>
            </div>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
            <div class="activity-admin-tools">

                <div class="activity-admin-row">
                    <button
                        type="button"
                        class="post-activity-button"
                        onclick="toggleActivityForm(true)"
                    >
                        <i class="ri-image-add-line"></i>
                        Post New Activity
                    </button>
                </div>

                <form
                    method="post"
                    enctype="multipart/form-data"
                    class="activity-post-panel"
                    id="activityPostPanel"
                >
                    <h3>Post a New Barangay Activity</h3>

                    <div class="activity-form-grid">

                        <div class="activity-field">
                            <label for="activity_title">Activity Title</label>
                            <input
                                id="activity_title"
                                type="text"
                                name="activity_title"
                                maxlength="180"
                                placeholder="Example: Community Clean-Up Drive"
                                required
                            >
                        </div>

                        <div class="activity-field">
                            <label for="activity_category">Category</label>
                            <input
                                id="activity_category"
                                type="text"
                                name="activity_category"
                                maxlength="100"
                                value="COMMUNITY ACTIVITY"
                                placeholder="Community Activity"
                            >
                        </div>

                        <div class="activity-field full">
                            <label for="activity_images">Activity Pictures (up to 10)</label>

                            <div class="activity-picture-picker">
                                <input
                                    id="activity_images"
                                    class="activity-file-input"
                                    type="file"
                                    name="activity_images[]"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                    required
                                    onchange="addActivityPictures(this)"
                                >

                                <label
                                    for="activity_images"
                                    class="activity-add-pictures-button"
                                >
                                    <i class="ri-image-add-line"></i>
                                    Add Pictures
                                </label>

                                <button
                                    type="button"
                                    class="activity-clear-pictures-button"
                                    id="activityClearPictures"
                                    onclick="clearActivityPictures()"
                                    hidden
                                >
                                    <i class="ri-delete-bin-line"></i>
                                    Clear All
                                </button>
                            </div>

                            <div class="activity-upload-help" id="activityPhotoCount">
                                No pictures selected yet. Click Add Pictures again anytime to add more.
                            </div>

                            <div
                                class="activity-picture-previews"
                                id="activityPicturePreviews"
                                aria-live="polite"
                            ></div>
                        </div>

                        <div class="activity-field full">
                            <label for="activity_description">Description</label>
                            <textarea
                                id="activity_description"
                                name="activity_description"
                                placeholder="Write a short description of the Barangay activity..."
                            ></textarea>
                        </div>

                        <div class="activity-form-actions">
                            <button
                                type="button"
                                class="activity-cancel"
                                onclick="toggleActivityForm(false)"
                            >
                                Cancel
                            </button>

                            <button
                                type="submit"
                                name="post_activity"
                                value="1"
                                class="activity-submit"
                            >
                                <i class="ri-upload-cloud-2-line"></i>
                                Publish Activity
                            </button>
                        </div>

                    </div>
                </form>

            </div>
        <?php endif; ?>

        <div class="event-grid">

            <?php if (!empty($activities)): ?>

                <?php foreach (array_slice($activities, 0, 6) as $activity): ?>

                    <article class="event-card">

                        <?php $activityImages = $activity['images'] ?? [$activity['image_path']]; ?>

                        <div class="event-photo-grid <?= count($activityImages) === 1 ? 'single' : '' ?>">
                            <?php foreach ($activityImages as $photoIndex => $imagePath): ?>
                                <img
                                    src="<?= htmlspecialchars($imagePath) ?>"
                                    alt="<?= htmlspecialchars($activity['title']) ?> - Photo <?= $photoIndex + 1 ?>"
                                    loading="lazy"
                                >
                            <?php endforeach; ?>
                        </div>

                        <div class="event-content">
                            <span class="event-category">
                                <?= htmlspecialchars($activity['category']) ?>
                            </span>

                            <h3>
                                <?= htmlspecialchars($activity['title']) ?>
                            </h3>

                            <?php if (trim((string) $activity['description']) !== ''): ?>
                                <p>
                                    <?= nl2br(htmlspecialchars($activity['description'])) ?>
                                </p>
                            <?php endif; ?>

                            <?php if (count($activityImages) > 1): ?>
                                <span class="event-photo-count">
                                    <i class="ri-gallery-line"></i>
                                    <?= count($activityImages) ?> pictures in this post
                                </span>
                            <?php endif; ?>

                            <span class="event-date">
                                <i class="ri-calendar-line"></i>
                                <?= htmlspecialchars(date('F j, Y', strtotime($activity['created_at']))) ?>
                            </span>

                            <?php if ($isAdmin): ?>
                                <form method="post" class="activity-delete-form">
                                    <input
                                        type="hidden"
                                        name="activity_id"
                                        value="<?= (int) $activity['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_activity"
                                        value="1"
                                        class="activity-delete-button"
                                        onclick="return confirm('Delete this Barangay activity and all of its pictures?');"
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                        Delete Post & Pictures
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty-activity-card">
                    <i class="ri-image-add-line"></i>
                    No Barangay activities have been posted yet.
                    <?php if ($isAdmin): ?>
                        Use <strong>Post New Activity</strong> above to publish the first one.
                    <?php endif; ?>
                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- =========================
     PROGRAMS
========================= -->

<section class="programs" id="programs">

    <div class="section-container">

        <div class="section-heading">

            <span>
                PUBLIC SERVICE
            </span>

            <h2>
                Barangay Programs
            </h2>

            <p>
                Replace these sample program categories with
                Barangay Tabon's actual programs and services.
            </p>

        </div>


        <div class="program-grid">


            <div class="program-card">

                <div class="program-icon">
                    <i class="ri-heart-pulse-line"></i>
                </div>

                <h3>
                    Health Programs
                </h3>

                <p>
                    Information about health services,
                    medical programs, consultations,
                    and community wellness activities.
                </p>

            </div>


            <div class="program-card">

                <div class="program-icon">
                    <i class="ri-graduation-cap-line"></i>
                </div>

                <h3>
                    Education & Youth
                </h3>

                <p>
                    Programs supporting students,
                    children, youth development,
                    education, and community participation.
                </p>

            </div>


            <div class="program-card">

                <div class="program-icon">
                    <i class="ri-recycle-line"></i>
                </div>

                <h3>
                    Environment
                </h3>

                <p>
                    Community clean-up, waste management,
                    environmental protection,
                    and beautification activities.
                </p>

            </div>


            <div class="program-card">

                <div class="program-icon">
                    <i class="ri-shield-user-line"></i>
                </div>

                <h3>
                    Peace & Safety
                </h3>

                <p>
                    Community safety activities,
                    disaster preparedness,
                    peace and order programs.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     DOCUMENTARY / GALLERY
========================= -->

<section
    class="documentary"
    id="gallery"
>

    <div class="section-container">

        <div class="section-heading">

            <span>
                DOCUMENTARY
            </span>

            <h2>
                Community Gallery
            </h2>

            <p>
                A visual record of Barangay Tabon's
                community activities, projects, programs,
                and important events.
            </p>

        </div>

        <div class="gallery">

            <?php if (!empty($activities)): ?>

                <?php
                $galleryPhotos = [];
                foreach ($activities as $activity) {
                    foreach (($activity['images'] ?? [$activity['image_path']]) as $imagePath) {
                        $galleryPhotos[] = [
                            'image_path' => $imagePath,
                            'title' => $activity['title'],
                            'category' => $activity['category'],
                        ];

                        if (count($galleryPhotos) >= 16) {
                            break 2;
                        }
                    }
                }
                ?>

                <?php foreach ($galleryPhotos as $index => $photo): ?>

                    <div class="gallery-item <?= $index === 0 ? 'large' : '' ?>">

                        <img
                            src="<?= htmlspecialchars($photo['image_path']) ?>"
                            alt="<?= htmlspecialchars($photo['title']) ?>"
                            loading="lazy"
                        >

                        <div class="gallery-overlay">
                            <strong>
                                <?= htmlspecialchars($photo['title']) ?>
                            </strong>

                            <span>
                                <?= htmlspecialchars($photo['category']) ?>
                            </span>
                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="gallery-item large">
                    <img
                        src="assets/images/gallery-1.jpg"
                        alt="Barangay Community Activity"
                    >
                    <div class="gallery-overlay">
                        <strong>Barangay Community Activity</strong>
                        <span>New activity pictures will appear here.</span>
                    </div>
                </div>

            <?php endif; ?>

        </div>

    </div>

</section>


<!-- =========================
     DOCUMENT VERIFICATION
========================= -->

<section class="services">

    <div class="section-container">

        <div class="service-box">

            <div>

                <h2>
                    Verify Barangay Documents
                </h2>

                <p>
                    Check whether a barangay-issued document
                    is authentic using the Barangay Tabon
                    blockchain-based document verification
                    system.
                </p>

            </div>

            <a
                href="verify.php"
                class="verify-btn"
            >
                <i class="ri-shield-check-line"></i>
                Verify Document
            </a>

        </div>

    </div>

</section>


<!-- =========================
     CONTACT
========================= -->

<section class="contact" id="contact">

    <div class="section-container">

        <div class="section-heading">

            <span>
                CONTACT
            </span>

            <h2>
                Visit Barangay Tabon
            </h2>

            <p>
                Replace the information below with the
                official contact details of Barangay Tabon.
            </p>

        </div>


        <div class="contact-grid">


            <div class="contact-card">

                <i class="ri-map-pin-line"></i>

                <h3>
                    Barangay Hall
                </h3>

                <p>
                    Enter the official Barangay Tabon
                    address here.
                </p>

            </div>


            <div class="contact-card">

                <i class="ri-phone-line"></i>

                <h3>
                    Contact Number
                </h3>

                <p>
                    Enter official contact number
                </p>

            </div>


            <div class="contact-card">

                <i class="ri-time-line"></i>

                <h3>
                    Office Hours
                </h3>

                <p>
                    Enter official barangay office
                    operating hours.
                </p>

            </div>


        </div>

    </div>

</section>


<!-- =========================
     FOOTER
========================= -->

<footer>

    <div class="footer-content">

        <div>

            <strong>
                Barangay Tabon
            </strong>

            <p>
                Blockchain-Based E-Governance
                Document Verification System
            </p>

        </div>

        <p>
            &copy; <?= date('Y') ?>
            Barangay Tabon.
            All Rights Reserved.
        </p>

    </div>

</footer>



<script>
function toggleActivityForm(show) {
    const panel = document.getElementById('activityPostPanel');

    if (!panel) {
        return;
    }

    panel.classList.toggle('show', show);

    if (show) {
        panel.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }
}

const MAX_ACTIVITY_PICTURES = 10;
const MAX_ACTIVITY_PICTURE_BYTES = 5 * 1024 * 1024;
const ALLOWED_ACTIVITY_PICTURE_TYPES = [
    'image/jpeg',
    'image/png',
    'image/webp'
];

let selectedActivityPictures = [];

function activityPictureKey(file) {
    return [file.name, file.size, file.lastModified, file.type].join('::');
}

function addActivityPictures(input) {
    const incomingPictures = Array.from(input.files || []);
    const existingKeys = new Set(
        selectedActivityPictures.map(activityPictureKey)
    );

    let skippedForLimit = 0;
    let skippedInvalid = 0;

    incomingPictures.forEach(function(file) {
        if (!ALLOWED_ACTIVITY_PICTURE_TYPES.includes(file.type)) {
            skippedInvalid++;
            return;
        }

        if (file.size > MAX_ACTIVITY_PICTURE_BYTES) {
            skippedInvalid++;
            return;
        }

        const key = activityPictureKey(file);

        if (existingKeys.has(key)) {
            return;
        }

        if (selectedActivityPictures.length >= MAX_ACTIVITY_PICTURES) {
            skippedForLimit++;
            return;
        }

        selectedActivityPictures.push(file);
        existingKeys.add(key);
    });

    syncActivityPictureInput();

    let notice = '';

    if (skippedForLimit > 0) {
        notice = 'Maximum 10 pictures per post. Extra pictures were not added.';
    } else if (skippedInvalid > 0) {
        notice = 'Some pictures were skipped. Use JPG, PNG, or WEBP files up to 5 MB each.';
    }

    renderActivityPicturePreviews(notice);
}

function syncActivityPictureInput() {
    const input = document.getElementById('activity_images');

    if (!input) {
        return;
    }

    if (typeof DataTransfer === 'undefined') {
        // Modern Chrome, Edge, Firefox and Safari support DataTransfer.
        // If it is unavailable, the server still accepts multi-select in one selection.
        return;
    }

    const transfer = new DataTransfer();

    selectedActivityPictures.forEach(function(file) {
        transfer.items.add(file);
    });

    input.files = transfer.files;
}

function removeActivityPicture(index) {
    if (index < 0 || index >= selectedActivityPictures.length) {
        return;
    }

    selectedActivityPictures.splice(index, 1);
    syncActivityPictureInput();
    renderActivityPicturePreviews();
}

function clearActivityPictures() {
    selectedActivityPictures = [];
    syncActivityPictureInput();
    renderActivityPicturePreviews();
}

function renderActivityPicturePreviews(notice) {
    const previewBox = document.getElementById('activityPicturePreviews');
    const countBox = document.getElementById('activityPhotoCount');
    const clearButton = document.getElementById('activityClearPictures');

    if (!previewBox || !countBox) {
        return;
    }

    previewBox.innerHTML = '';

    selectedActivityPictures.forEach(function(file, index) {
        const card = document.createElement('div');
        card.className = 'activity-picture-preview';

        const image = document.createElement('img');
        const objectUrl = URL.createObjectURL(file);
        image.src = objectUrl;
        image.alt = 'Selected picture ' + (index + 1);
        image.onload = function() {
            URL.revokeObjectURL(objectUrl);
        };

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'activity-remove-picture';
        removeButton.setAttribute('aria-label', 'Remove ' + file.name);
        removeButton.title = 'Remove picture';
        removeButton.innerHTML = '&times;';
        removeButton.addEventListener('click', function() {
            removeActivityPicture(index);
        });

        const fileName = document.createElement('span');
        fileName.className = 'activity-picture-preview-name';
        fileName.textContent = file.name;
        fileName.title = file.name;

        card.appendChild(image);
        card.appendChild(removeButton);
        card.appendChild(fileName);
        previewBox.appendChild(card);
    });

    const count = selectedActivityPictures.length;

    if (notice) {
        countBox.textContent = notice;
        countBox.classList.add('activity-photo-limit');
    } else {
        countBox.classList.remove('activity-photo-limit');

        if (count === 0) {
            countBox.textContent = 'No pictures selected yet. Click Add Pictures again anytime to add more.';
        } else {
            countBox.textContent = count
                + (count === 1 ? ' picture selected. ' : ' pictures selected. ')
                + 'You can click Add Pictures again to add more.';
        }
    }

    if (clearButton) {
        clearButton.hidden = count === 0;
    }
}

const activityPostForm = document.getElementById('activityPostPanel');

if (activityPostForm) {
    activityPostForm.addEventListener('submit', function(event) {
        if (selectedActivityPictures.length === 0) {
            event.preventDefault();
            renderActivityPicturePreviews('Please add at least one picture before publishing.');
        }
    });
}
</script>

</body>
</html>