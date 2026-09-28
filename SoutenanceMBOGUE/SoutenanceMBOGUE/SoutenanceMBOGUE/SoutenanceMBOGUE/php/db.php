<?php

// PARAMÈTRES DE CONNEXION XAMPP
$host = "localhost";
$username = "root";
$password = ""; // vide par défaut sous XAMPP
$database = "gestionprojet"; // ⚠ mets EXACTEMENT le nom réel

// CONNEXION MYSQLI PROCÉDURAL
$conn = mysqli_connect($host, $username, $password, $database);

// VÉRIFICATION
if (!$conn) {
    die("Erreur connexion : " . mysqli_connect_error());
}
// 🔹 FONCTION REQUISE PAR login.php
function getDBConnection() {
    $host     = "localhost";
    $username = "root";
    $password = "";
    $dbname   = "gestionprojet";

    $conn = mysqli_connect($host, $username, $password, $dbname);

    if (!$conn) {
        return null; // login.php gère l'erreur de son côté
    }

    mysqli_set_charset($conn, "utf8");
    return $conn;

}





?>
