<?php
// ============================================================
//  chat_api.php  —  API unique du chat (compatible INT et VARCHAR)
// ============================================================
session_start();
require_once "db.php";
mysqli_report(MYSQLI_REPORT_OFF);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['matricule'], $_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// ── On garde le matricule tel quel depuis la session ──────────
// Pas de cast en int : si c'est un VARCHAR en BDD ça doit rester string
$me   = trim((string) $_SESSION['matricule']);
$role = strtolower(trim((string) $_SESSION['role']));

// ── Détection automatique du type de la colonne matricule ────
// Cela permet de choisir "s" ou "i" dans bind_param automatiquement
function getBindType(mysqli $conn): string {
    $res = mysqli_query($conn,
        "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME   = 'users'
           AND COLUMN_NAME  = 'matricule'
         LIMIT 1"
    );
    $row = $res ? mysqli_fetch_assoc($res) : null;
    $type = strtolower((string) ($row['DATA_TYPE'] ?? 'varchar'));
    // Types entiers MySQL
    return in_array($type, ['int','tinyint','smallint','mediumint','bigint'], true) ? 'i' : 's';
}

$bindType = getBindType($conn); // 'i' pour INT, 's' pour VARCHAR

function jsonResponse(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function preparedResult(mysqli $conn, string $sql, string $types = '', array $params = []): ?mysqli_result {
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }

    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        return null;
    }

    $result = mysqli_stmt_get_result($stmt);
    mysqli_stmt_close($stmt);
    return $result ?: null;
}

// ── Chargement des contacts selon le rôle ────────────────────
// Chaque contact inclut last_message_id et last_sender_id
// pour que le JS puisse afficher les badges de messages non lus
function loadContacts(mysqli $conn, string $me, string $role, string $bt): array {

    // Vérifier si la table projets_employes existe
    $check = mysqli_query($conn, "SHOW TABLES LIKE 'projets_employes'");
    $hasProjects = ($check && mysqli_num_rows($check) > 0);

    if (!$hasProjects) {
        // Fallback : tous les utilisateurs sauf soi
        $sql  = "SELECT matricule, nom, prenom, role FROM users WHERE matricule != ? ORDER BY nom, prenom";
        $result = preparedResult($conn, $sql, $bt, [$me]);
    } elseif ($role === 'employe') {
        $sql  = "SELECT DISTINCT u.matricule, u.nom, u.prenom, u.role
                 FROM users u
                 WHERE u.matricule != ?
                   AND (u.role = 'admin'
                        OR (u.role = 'client' AND EXISTS (
                              SELECT 1 FROM projet p
                              JOIN projets_employes pe ON pe.projet_id = p.id
                              WHERE pe.employe_id = ? AND p.id_client = u.matricule
                        )))
                 ORDER BY CASE u.role WHEN 'admin' THEN 1 ELSE 2 END, u.nom, u.prenom";
        $result = preparedResult($conn, $sql, $bt.$bt, [$me, $me]);
    } elseif ($role === 'client') {
        $sql  = "SELECT DISTINCT u.matricule, u.nom, u.prenom, u.role
                 FROM users u
                 WHERE u.matricule != ?
                   AND u.role = 'employe'
                   AND EXISTS (
                         SELECT 1 FROM projet p
                         JOIN projets_employes pe ON pe.projet_id = p.id
                         WHERE p.id_client = ? AND pe.employe_id = u.matricule
                   )
                 ORDER BY u.nom, u.prenom";
        $result = preparedResult($conn, $sql, $bt.$bt, [$me, $me]);
    } else {
        // admin : tous les employés
        $sql  = "SELECT matricule, nom, prenom, role
                 FROM users
                 WHERE matricule != ? AND role = 'employe'
                 ORDER BY nom, prenom";
        $result = preparedResult($conn, $sql, $bt, [$me]);
    }

    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];

    // Pour chaque contact, récupérer le dernier message échangé
    foreach ($rows as &$row) {
        $row['label'] = trim(($row['prenom'] ?? '') . ' ' . ($row['nom'] ?? ''));

        $metaSql  = "SELECT id, sender_id
                     FROM messages
                     WHERE (sender_id = ? AND receiver_id = ?)
                        OR (sender_id = ? AND receiver_id = ?)
                     ORDER BY id DESC
                     LIMIT 1";
        $cId      = (string) $row['matricule'];
        $metaResult = preparedResult($conn, $metaSql, $bt.$bt.$bt.$bt, [$me, $cId, $cId, $me]);
        $meta = $metaResult ? (mysqli_fetch_assoc($metaResult) ?: ['id' => 0, 'sender_id' => null]) : ['id' => 0, 'sender_id' => null];

        $row['last_message_id'] = (int) ($meta['id'] ?? 0);
        $row['last_sender_id']  = (string) ($meta['sender_id'] ?? '');
    }
    unset($row);

    return $rows;
}

// ── Routage ───────────────────────────────────────────────────
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ACTION : contacts
if ($action === 'contacts') {
    jsonResponse(['contacts' => loadContacts($conn, $me, $role, $bindType)]);
}

// ACTION : messages
if ($action === 'messages') {
    $contactId = trim((string) ($_GET['contact_id'] ?? ''));
    if ($contactId === '') {
        jsonResponse(['error' => 'contact_id manquant'], 422);
    }

    // Vérification autorisation
    $allowed = array_column(loadContacts($conn, $me, $role, $bindType), 'matricule');
    // Comparaison en string pour INT et VARCHAR
    $allowedStr = array_map('strval', $allowed);
    if (!in_array($contactId, $allowedStr, true)) {
        jsonResponse(['error' => 'Contact non autorisé'], 403);
    }

    $sql  = "SELECT m.id, m.sender_id, m.receiver_id, m.message, m.created_at,
                    u.nom AS sender_nom, u.prenom AS sender_prenom
             FROM messages m
             JOIN users u ON u.matricule = m.sender_id
             WHERE (m.sender_id = ? AND m.receiver_id = ?)
                OR (m.sender_id = ? AND m.receiver_id = ?)
             ORDER BY m.created_at ASC, m.id ASC";
    $result = preparedResult($conn, $sql, $bindType.$bindType.$bindType.$bindType, [$me, $contactId, $contactId, $me]);
    if ($result === null) {
        jsonResponse(['error' => 'Impossible de charger les messages'], 500);
    }
    $messages = mysqli_fetch_all($result, MYSQLI_ASSOC) ?: [];

    jsonResponse(['messages' => $messages]);
}

// ACTION : send
if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiverId = trim((string) ($_POST['receiver_id'] ?? ''));
    $message    = trim((string) ($_POST['message']     ?? ''));

    if ($receiverId === '' || $message === '') {
        jsonResponse(['error' => 'Données invalides'], 422);
    }

    // Vérification autorisation
    $allowed    = array_column(loadContacts($conn, $me, $role, $bindType), 'matricule');
    $allowedStr = array_map('strval', $allowed);
    if (!in_array($receiverId, $allowedStr, true)) {
        jsonResponse(['error' => 'Destinataire non autorisé'], 403);
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
    if (!$stmt) {
        jsonResponse(['error' => 'Impossible d\'envoyer le message'], 500);
    }
    mysqli_stmt_bind_param($stmt, $bindType.$bindType.'s', $me, $receiverId, $message);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    jsonResponse($ok ? ['success' => true] : ['error' => "Envoi impossible"], $ok ? 200 : 500);
}

jsonResponse(['error' => 'Action inconnue'], 400);
