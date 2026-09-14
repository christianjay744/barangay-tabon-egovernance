<?php
require_once '../config/db.php';
require_once '../config/auth.php';
require_once '../config/helpers.php';

require_login();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    exit('Invalid document ID.');
}

/*
|--------------------------------------------------------------------------
| Check that the document belongs to the logged-in resident
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT id, user_id
    FROM document_requests
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    exit('Document not found.');
}

if (
    $_SESSION['role'] !== 'admin' &&
    (int)$document['user_id'] !== (int)$_SESSION['user_id']
) {
    exit('You are not allowed to view this document.');
}

/*
|--------------------------------------------------------------------------
| Open the main document viewer
|--------------------------------------------------------------------------
*/
header("Location: ../document.php?id=" . $id);
exit;
?>

