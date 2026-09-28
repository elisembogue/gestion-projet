<?php
session_start();
require_once "db.php";

// Vérification session admin
if (!isset($_SESSION['matricule']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: connexion.php');
    exit;
}

// Méthode POST uniquement
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: showUser.php?message=InvalidRequest');
    exit;
}

// Récupération et validation du matricule
$matricule = trim((string) ($_POST['id'] ?? ''));
if ($matricule === '') {
    header('Location: showUser.php?message=DeleteFail');
    exit;
}

// Suppression sécurisée avec requête préparée
$stmt = mysqli_prepare($conn, "DELETE FROM users WHERE matricule = ?");
if (!$stmt) {
    header('Location: showUser.php?message=DeleteFail');
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $matricule);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: showUser.php?message=' . ($ok ? 'DeleteSuccess' : 'DeleteFail'));
exit;
?>