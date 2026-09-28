<?php
session_start();
require "db.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profile_pic'])) {
    $matricule = $_SESSION['matricule'];
    $file = $_FILES['profile_pic'];

    if ($file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "uploads" . DIRECTORY_SEPARATOR . "profiles";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $db_upload_dir = "../uploads/profiles/";

            // Suppression de l'ancienne photo
            $stmt = mysqli_prepare($conn, "SELECT photo FROM users WHERE matricule = ?");
            mysqli_stmt_bind_param($stmt, "s", $matricule);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $old = mysqli_fetch_assoc($res);

            $oldPath = (string) ($old['photo'] ?? '');
            if ($oldPath !== '' && preg_match('/^https?:\/\//i', $oldPath) !== 1) {
                $oldPathFs = $oldPath;
                if (strpos($oldPath, 'uploads/') === 0) {
                    $oldPathFs = '../' . $oldPath;
                }
                $oldAbsPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $oldPathFs);
                if (is_file($oldAbsPath)) {
                    unlink($oldAbsPath);
                }
            }

            $new_name = "admin_" . $matricule . "_" . time() . "." . $ext;
            $final_path = $upload_dir . DIRECTORY_SEPARATOR . $new_name;

            if (move_uploaded_file($file['tmp_name'], $final_path)) {
                $up_stmt = mysqli_prepare($conn, "UPDATE users SET photo = ? WHERE matricule = ?");
                $db_path = $db_upload_dir . $new_name;
                mysqli_stmt_bind_param($up_stmt, "ss", $db_path, $matricule);
                mysqli_stmt_execute($up_stmt);
                header("Location: adminDashboard.php?success=1");
                exit;
            }
        }
    }
}
header("Location: adminDashboard.php?error=1");
exit;







