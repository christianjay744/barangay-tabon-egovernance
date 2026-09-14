<?php
function log_activity(PDO $pdo, $userId, $action, $details='') {
    $stmt = $pdo->prepare("INSERT INTO activities(user_id,action,details) VALUES(?,?,?)");
    $stmt->execute([$userId, $action, $details]);
}
function document_number($id) {
    return 'BT-' . date('Y') . '-' . str_pad($id, 6, '0', STR_PAD_LEFT);
}
function verify_url($docNo) {
    return 'verify.php?doc=' . urlencode($docNo);
}
