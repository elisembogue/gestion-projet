<?php
require "db.php";

// 1. On définit le mot de passe qu'on veut (ex: 123456)
$nouveau_password_clair = "123456"; 

// 2. On crée le HASH sécurisé
$hash = password_hash($nouveau_password_clair, PASSWORD_DEFAULT);

// 3. On met à jour l'utilisateur dans la base de données
// REMPLACE l'email ci-dessous par celui de ton image
$email_a_reparer = "mbeltalove@gmail.com"; 

$sql = "UPDATE users SET password = ? WHERE email = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ss", $hash, $email_a_reparer);

if (mysqli_stmt_execute($stmt)) {
    echo "✅ Succès ! Le mot de passe pour $email_a_reparer est maintenant : $nouveau_password_clair";
    echo "<br>Tu peux maintenant te connecter sur la page de connexion.";
} else {
    echo "❌ Erreur lors de la mise à jour.";
}
?>
