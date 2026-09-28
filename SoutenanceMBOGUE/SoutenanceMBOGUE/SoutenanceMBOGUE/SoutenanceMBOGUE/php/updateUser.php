<?php
session_start();
require_once "db.php";

if (!isset($_SESSION['matricule']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: connexion.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: showUser.php?message=InvalidRequest');
    exit;
}

$matricule = trim((string) ($_POST['matricule'] ?? ''));
$prenom = trim((string) ($_POST['prenom'] ?? ''));
$nom = trim((string) ($_POST['nom'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$telephone = trim((string) ($_POST['telephone'] ?? ''));
$date_naissance = trim((string) ($_POST['date_naissance'] ?? ''));
$sexe = strtolower(trim((string) ($_POST['sexe'] ?? '')));

if ($matricule === '' || $prenom === '' || $nom === '' || $email === '' || $telephone === '' || $date_naissance === '' || $sexe === '') {
    header('Location: showUser.php?message=UpdateFail');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: showUser.php?message=UpdateFail');
    exit;
}

if (!in_array($sexe, ['masculin', 'feminin'], true)) {
    header('Location: showUser.php?message=UpdateFail');
    exit;
}

$sql = "UPDATE users SET prenom = ?, nom = ?, email = ?, telephone = ?, date_naissance = ?, sexe = ? WHERE matricule = ?";
$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    header('Location: showUser.php?message=UpdateFail');
    exit;
}

mysqli_stmt_bind_param($stmt, 'sssssss', $prenom, $nom, $email, $telephone, $date_naissance, $sexe, $matricule);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: showUser.php?message=' . ($ok ? 'UpdateSuccess' : 'UpdateFail'));
exit;
?>

