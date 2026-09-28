<?php
session_start();
require_once "db.php";
if (!isset($_SESSION['matricule']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'admin') {
    header("Location: connexion.php"); exit;
}

$matricule = (int) $_SESSION['matricule'];
$tab = $_GET['tab'] ?? 'overview';
$allowed = ['overview','commandes','projets','employes','progression','presence','chat','profil'];
if (!in_array($tab, $allowed, true)) $tab = 'overview';
function showA(string $s, string $t): bool { return $t === 'overview' || $t === $s; }
$flash = '';

// ── Tables ────────────────────────────────────────────────────
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_progress (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL, employe_id INT NOT NULL, note TEXT NOT NULL, progress_value TINYINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS employe_presence (id INT AUTO_INCREMENT PRIMARY KEY, employe_id INT NOT NULL, presence_date DATE NOT NULL, first_seen_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, UNIQUE KEY uniq_presence (employe_id, presence_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS absence_alerts (id INT AUTO_INCREMENT PRIMARY KEY, employe_id INT NOT NULL, month_key VARCHAR(7) NOT NULL, days_absent INT NOT NULL, alert_level TINYINT NOT NULL DEFAULT 1, sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uniq_alert (employe_id, month_key, alert_level)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_plans (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL UNIQUE, employe_id INT NOT NULL, planned_start_date DATE NOT NULL, planned_end_date DATE NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_task_titles (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL, employe_id INT NOT NULL, title VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_subtasks (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL, task_title_id INT NOT NULL, employe_id INT NOT NULL, label VARCHAR(255) NOT NULL, is_done TINYINT(1) NOT NULL DEFAULT 0, done_at DATE DEFAULT NULL, is_validated TINYINT(1) NOT NULL DEFAULT 0, validated_at DATE DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function ensureColumn(mysqli $conn, string $table, string $column, string $definition): void {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, "ss", $table, $column);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['c' => 0];
    mysqli_stmt_close($stmt);
    if ((int)($row['c'] ?? 0) === 0) mysqli_query($conn, "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}
ensureColumn($conn, 'absence_alerts', 'alert_level', "TINYINT NOT NULL DEFAULT 1");
ensureColumn($conn, 'project_subtasks', 'is_validated', "TINYINT(1) NOT NULL DEFAULT 0");
ensureColumn($conn, 'project_subtasks', 'validated_at', "DATE DEFAULT NULL");

function syncProjectsCompletionForAdmin(mysqli $conn): void {
    $sql = "SELECT p.id,
                   (SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id = p.id) AS planned_units,
                   (SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id = p.id AND ps.is_validated = 1) AS done_units,
                   (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id) AS total_tasks,
                   (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id AND t.statut = 'terminee') AS done_tasks,
                   (SELECT COALESCE(MAX(progress_value), 0) FROM project_progress pr WHERE pr.projet_id = p.id) AS latest_progress
            FROM projet p
            WHERE p.statut <> 'termine'";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        return;
    }

    $update = mysqli_prepare($conn, "UPDATE projet SET statut = 'termine', date_fin = COALESCE(date_fin, CURDATE()) WHERE id = ? AND statut <> 'termine'");
    if (!$update) {
        return;
    }

    while ($project = mysqli_fetch_assoc($result)) {
        $plannedUnits = (int) ($project['planned_units'] ?? 0);
        $doneUnits = (int) ($project['done_units'] ?? 0);
        $totalTasks = (int) ($project['total_tasks'] ?? 0);
        $doneTasks = (int) ($project['done_tasks'] ?? 0);
        $latestProgress = (int) ($project['latest_progress'] ?? 0);

        if ($plannedUnits > 0) {
            $completion = (int) min(100, round(($doneUnits / $plannedUnits) * 100));
        } elseif ($totalTasks > 0) {
            $completion = (int) min(100, round(($doneTasks / $totalTasks) * 100));
        } else {
            $completion = $latestProgress;
        }

        if ($completion >= 100) {
            $id = (int) $project['id'];
            mysqli_stmt_bind_param($update, "i", $id);
            mysqli_stmt_execute($update);
        }
    }

    mysqli_stmt_close($update);
}

syncProjectsCompletionForAdmin($conn);

// ── AJAX : reset badge employes ───────────────────────────────
// Appelé par le JS quand l'admin clique sur l'onglet Utilisateurs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_badge') {
    $badgeKey = trim((string) ($_POST['key'] ?? ''));
    if (in_array($badgeKey, ['commandes', 'projets', 'employes'], true)) {
        if (!isset($_SESSION['admin_seen_tabs']) || !is_array($_SESSION['admin_seen_tabs'])) {
            $_SESSION['admin_seen_tabs'] = ['commandes' => 0, 'projets' => 0, 'employes' => 0];
        }
        // Pour "employes" on met une valeur haute pour masquer le badge
        if ($badgeKey === 'employes') {
            // On récupère le max actuel des matricules employés
            $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(MAX(matricule),0) AS m FROM users WHERE role='employe'"));
            $_SESSION['admin_seen_tabs']['employes'] = (int)($row['m'] ?? 0);
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

// ── POST profil ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $prenom = trim((string)($_POST['prenom'] ?? '')); $nom = trim((string)($_POST['nom'] ?? '')); $email = trim((string)($_POST['email'] ?? ''));
    if ($prenom !== '' && $nom !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $photoPath = null; $uploadError = '';
        if (isset($_FILES['photo']) && (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $fileErr = (int)($_FILES['photo']['error'] ?? UPLOAD_ERR_OK);
            if ($fileErr === UPLOAD_ERR_OK) {
                $tmpPath = (string)($_FILES['photo']['tmp_name'] ?? ''); $size = (int)($_FILES['photo']['size'] ?? 0);
                $ext = strtolower((string)pathinfo((string)($_FILES['photo']['name'] ?? ''), PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) { $uploadError = "Format invalide."; }
                elseif ($size > 2*1024*1024) { $uploadError = "Image trop lourde."; }
                else {
                    $uploadDir = __DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'profiles';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $fileName = 'u'.$matricule.'_'.date('YmdHis').'.'.$ext;
                    if (is_dir($uploadDir) && (move_uploaded_file($tmpPath, $uploadDir.DIRECTORY_SEPARATOR.$fileName) || @copy($tmpPath, $uploadDir.DIRECTORY_SEPARATOR.$fileName))) {
                        $photoPath = '../uploads/profiles/'.$fileName;
                    } else { $uploadError = "Échec du téléversement."; }
                }
            } else { $uploadError = "Fichier invalide."; }
        }
        if ($uploadError !== '') { $flash = $uploadError; }
        else {
            if ($photoPath !== null) { $st = mysqli_prepare($conn, "UPDATE users SET prenom=?,nom=?,email=?,photo=? WHERE matricule=?"); mysqli_stmt_bind_param($st, "ssssi", $prenom, $nom, $email, $photoPath, $matricule); }
            else { $st = mysqli_prepare($conn, "UPDATE users SET prenom=?,nom=?,email=? WHERE matricule=?"); mysqli_stmt_bind_param($st, "sssi", $prenom, $nom, $email, $matricule); }
            if (mysqli_stmt_execute($st)) { $_SESSION['prenom']=$prenom; $_SESSION['nom']=$nom; $_SESSION['email']=$email; $flash="Profil mis à jour."; }
            else { $flash="Échec de mise à jour."; }
            mysqli_stmt_close($st);
        }
    } else { $flash="Informations invalides."; }
}

// ── POST validation sous-tâche ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'validate_subtask') {
    $subtaskId = (int)($_POST['subtask_id'] ?? 0);
    if ($subtaskId > 0) {
        $st = mysqli_prepare($conn, "UPDATE project_subtasks SET is_validated=1, validated_at=CURDATE() WHERE id=? AND is_done=1");
        if ($st) { mysqli_stmt_bind_param($st, "i", $subtaskId); mysqli_stmt_execute($st); $flash = mysqli_stmt_affected_rows($st) > 0 ? "Sous-tâche validée." : "Sous-tâche introuvable."; mysqli_stmt_close($st); }
    }
}

// ── Données ───────────────────────────────────────────────────
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nom,prenom,email,photo FROM users WHERE matricule=$matricule")) ?: [];
$photoRelPath = (string)($me['photo'] ?? '');
$photoRelPathDisplay = ($photoRelPath !== '' && strpos($photoRelPath, 'uploads/') === 0) ? '../'.$photoRelPath : $photoRelPath;
$hasAvatar = $photoRelPathDisplay !== '' && (preg_match('/^https?:\/\//i', $photoRelPathDisplay) || is_file(__DIR__.DIRECTORY_SEPARATOR.str_replace(['/',  '\\'], DIRECTORY_SEPARATOR, $photoRelPathDisplay)));
$avatar = $hasAvatar ? $photoRelPathDisplay : '';

$pending     = mysqli_fetch_all(mysqli_query($conn, "SELECT p.id,p.type_projet,c.nom client_nom,c.prenom client_prenom FROM projet p JOIN users c ON c.matricule=p.id_client LEFT JOIN projets_employes pe ON pe.projet_id=p.id WHERE p.statut='en_attente' AND pe.id IS NULL ORDER BY p.created_at"), MYSQLI_ASSOC);
$ongoing     = mysqli_fetch_all(mysqli_query($conn, "SELECT p.id,p.type_projet,c.nom client_nom,c.prenom client_prenom,COALESCE(e.nom,'Non assigné') emp_nom,COALESCE(e.prenom,'') emp_prenom,(SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id=p.id) planned_units,(SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id=p.id AND ps.is_validated=1) done_units,(SELECT planned_end_date FROM project_plans pp WHERE pp.projet_id=p.id LIMIT 1) planned_end_date,(SELECT COALESCE(MAX(progress_value),0) FROM project_progress pr WHERE pr.projet_id=p.id) latest_progress FROM projet p JOIN users c ON c.matricule=p.id_client LEFT JOIN projets_employes pe ON pe.projet_id=p.id LEFT JOIN users e ON e.matricule=pe.employe_id WHERE p.statut='en_cours' ORDER BY p.created_at DESC"), MYSQLI_ASSOC);
$loads       = mysqli_fetch_all(mysqli_query($conn, "SELECT u.matricule,u.prenom,u.nom,COUNT(CASE WHEN p.statut='en_cours' THEN 1 END) active_projects FROM users u LEFT JOIN projets_employes pe ON pe.employe_id=u.matricule LEFT JOIN projet p ON p.id=pe.projet_id WHERE u.role='employe' GROUP BY u.matricule,u.prenom,u.nom ORDER BY active_projects DESC,u.nom"), MYSQLI_ASSOC);
$stats       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT (SELECT COUNT(*) FROM projet WHERE statut='en_attente') pending_count,(SELECT COUNT(*) FROM projet WHERE statut='en_cours') ongoing_count,(SELECT COUNT(*) FROM projet WHERE statut='termine') done_count,(SELECT COUNT(*) FROM users WHERE role='employe') employee_count")) ?: ['pending_count'=>0,'ongoing_count'=>0,'done_count'=>0,'employee_count'=>0];
$todayPresence   = mysqli_fetch_all(mysqli_query($conn, "SELECT u.matricule,u.prenom,u.nom,p.first_seen_at,p.last_seen_at FROM users u LEFT JOIN employe_presence p ON p.employe_id=u.matricule AND p.presence_date=CURDATE() WHERE u.role='employe' ORDER BY u.nom,u.prenom"), MYSQLI_ASSOC);
$monthStart  = date('Y-m-01'); $todayDate = date('Y-m-d'); $monthKey = date('Y-m'); $daysElapsed = (int)date('j');
$monthlyPresence = mysqli_fetch_all(mysqli_query($conn, "SELECT u.matricule,u.prenom,u.nom,COALESCE(p.present_days,0) present_days FROM users u LEFT JOIN (SELECT employe_id,COUNT(*) present_days FROM employe_presence WHERE presence_date BETWEEN '$monthStart' AND '$todayDate' GROUP BY employe_id) p ON p.employe_id=u.matricule WHERE u.role='employe' ORDER BY u.nom,u.prenom"), MYSQLI_ASSOC);
foreach ($monthlyPresence as &$row) { $row['absent_days'] = max(0, $daysElapsed - (int)$row['present_days']); } unset($row);

// Alertes absences
foreach ($monthlyPresence as $row) {
    $absentDays = (int)($row['absent_days'] ?? 0); if ($absentDays <= 5) continue;
    $chk = mysqli_prepare($conn, "SELECT id FROM absence_alerts WHERE employe_id=? AND month_key=? LIMIT 1"); if (!$chk) continue;
    $empId = (int)$row['matricule']; mysqli_stmt_bind_param($chk, "is", $empId, $monthKey); mysqli_stmt_execute($chk);
    if (mysqli_fetch_assoc(mysqli_stmt_get_result($chk))) { mysqli_stmt_close($chk); continue; } mysqli_stmt_close($chk);
    $msg = "Alerte absence : {$absentDays} jours d'absence ce mois. Merci de contacter l'administration.";
    $ms = mysqli_prepare($conn, "INSERT INTO messages (sender_id,receiver_id,message) VALUES (?,?,?)");
    if ($ms) { mysqli_stmt_bind_param($ms, "iis", $matricule, $empId, $msg); mysqli_stmt_execute($ms); mysqli_stmt_close($ms); }
    $ins = mysqli_prepare($conn, "INSERT INTO absence_alerts (employe_id,month_key,days_absent) VALUES (?,?,?)");
    if ($ins) { mysqli_stmt_bind_param($ins, "isi", $empId, $monthKey, $absentDays); mysqli_stmt_execute($ins); mysqli_stmt_close($ins); }
}

$pendingSubtasks = mysqli_fetch_all(mysqli_query($conn, "SELECT ps.id,ps.projet_id,ps.label,ps.done_at,p.type_projet,ptt.title,u.prenom,u.nom FROM project_subtasks ps JOIN projet p ON p.id=ps.projet_id JOIN project_task_titles ptt ON ptt.id=ps.task_title_id JOIN users u ON u.matricule=ps.employe_id WHERE ps.is_done=1 AND ps.is_validated=0 ORDER BY ps.done_at DESC,ps.id DESC"), MYSQLI_ASSOC);
$projectPieSeries = [];
foreach ($ongoing as $proj) {
    $pl=(int)($proj['planned_units']??0); $dn=(int)($proj['done_units']??0);
    $projectPieSeries[]=['id'=>(int)$proj['id'],'label'=>'#'.(int)$proj['id'].' '.(string)$proj['type_projet'],'done'=>$dn,'remaining'=>max(0,$pl-$dn)];
}

// ── Badges menu ───────────────────────────────────────────────
$maxPendingId = 0; foreach($pending as $o) $maxPendingId = max($maxPendingId, (int)($o['id']??0));
$maxOngoingId = 0; foreach($ongoing as $p) $maxOngoingId = max($maxOngoingId, (int)($p['id']??0));
$maxEmployeId = 0; foreach($loads as $l) $maxEmployeId = max($maxEmployeId, (int)($l['matricule']??0));

if (!isset($_SESSION['admin_seen_tabs']) || !is_array($_SESSION['admin_seen_tabs'])) {
    $_SESSION['admin_seen_tabs'] = ['commandes' => 0, 'projets' => 0, 'employes' => 0];
}
// Mise à jour côté PHP uniquement pour commandes et projets (tab normal)
// employes est géré via AJAX car il redirige vers showUser.php
if ($tab === 'commandes') $_SESSION['admin_seen_tabs']['commandes'] = $maxPendingId;
if ($tab === 'projets')   $_SESSION['admin_seen_tabs']['projets']   = $maxOngoingId;

$newCommandesCount = 0; foreach($pending as $o) { if((int)($o['id']??0) > (int)$_SESSION['admin_seen_tabs']['commandes']) $newCommandesCount++; }
$newProjetsCount   = 0; foreach($ongoing as $p) { if((int)($p['id']??0) > (int)$_SESSION['admin_seen_tabs']['projets'])   $newProjetsCount++; }
$newEmployesCount  = 0; foreach($loads as $l)   { if((int)($l['matricule']??0) > (int)$_SESSION['admin_seen_tabs']['employes'])  $newEmployesCount++; }
$menuBadges = ['commandes' => $newCommandesCount, 'projets' => $newProjetsCount, 'employes' => $newEmployesCount];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Admin | EltaRH</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com" onerror="this.onerror=null;this.src='../js/cdn.js';"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#C026D3'},fontFamily:{poppins:['Poppins','sans-serif']}}}}</script>
<style>
.pulse-dot{animation:pulse 1s infinite}
@keyframes pulse{0%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(1.2)}100%{opacity:1;transform:scale(1)}}
.top-header{background:#fff;border-bottom:2px solid #f0abfc;}
.dark .top-header{background:#1f2937;border-bottom-color:#7e22ce;}
.stat-card{border-left:4px solid #C026D3;}
</style>
</head>
<body class="font-poppins bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100">

<?php $menu=['overview'=>['Vue d\'ensemble','fa-chart-pie'],'commandes'=>['Commandes','fa-inbox'],'projets'=>['Projets','fa-briefcase'],'employes'=>['Utilisateurs','fa-users'],'progression'=>['Progression','fa-chart-pie'],'presence'=>['Présence','fa-user-check'],'chat'=>['Chat','fa-comments'],'profil'=>['Profil','fa-user']]; ?>

<!-- Sidebar desktop -->
<aside class="fixed left-0 top-0 h-screen w-80 bg-fuchsia-700 dark:bg-gray-800 border-r border-fuchsia-800 dark:border-gray-700 shadow-xl hidden md:flex flex-col z-40">
    <div class="px-5 py-5 flex items-center border-b border-fuchsia-600 dark:border-gray-700 bg-fuchsia-800 dark:bg-gray-900">
        <img src="../image/logo EltaRH.png" alt="EltaRH" class="h-20 w-auto">
    </div>
    <nav class="flex-1 px-3 py-5 space-y-1">
        <?php foreach($menu as $k=>$m): ?>
            <a href="<?= $k==='employes'?'showUser.php':'?tab='.$k ?>"
               <?= $k==='chat'?'data-chat-link="desktop"':'' ?>
               <?= $k==='employes'?'data-reset-badge="employes"':'' ?>
               class="flex items-center justify-between px-4 py-3 rounded-xl transition-colors
               <?= $tab===$k?'bg-white text-fuchsia-700 font-bold shadow':'text-white hover:bg-fuchsia-600 dark:hover:bg-fuchsia-900/30' ?>">
                <span><i class="fas <?= $m[1] ?> w-5 text-center mr-2"></i><?= $m[0] ?></span>
                <?php if($k==='chat'): ?>
                    <span data-chat-badge class="hidden min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center"></span>
                <?php elseif(isset($menuBadges[$k]) && (int)$menuBadges[$k] > 0): ?>
                    <span data-badge="<?= $k ?>" class="inline-flex min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center animate-pulse"><?= (int)$menuBadges[$k]>99?'99+':(int)$menuBadges[$k] ?></span>
                <?php else: ?>
                    <span data-badge="<?= $k ?>" class="hidden min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-fuchsia-600 dark:border-gray-700">
        <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white hover:bg-fuchsia-600 transition font-semibold">
            <i class="fas fa-right-from-bracket w-5 text-center"></i>Déconnexion
        </a>
    </div>
</aside>

<!-- Overlay + Sidebar mobile (bug > corrigé) -->
<div id="mobileOverlay" class="fixed inset-0 bg-black/50 hidden z-30 md:hidden"></div>
<aside id="mobileSidebar" class="fixed left-0 top-0 h-screen w-[88vw] max-w-80 bg-fuchsia-700 dark:bg-gray-800 border-r border-fuchsia-800 dark:border-gray-700 shadow-xl transform -translate-x-full transition-transform z-40 md:hidden">
    <div class="px-5 py-5 border-b border-fuchsia-600 dark:border-gray-700 bg-fuchsia-800 dark:bg-gray-900 flex items-center justify-between">
        <img src="../image/logo EltaRH.png" alt="EltaRH" class="h-16 w-auto">
        <button id="closeMobileMenu" class="w-9 h-9 rounded-lg bg-white/20 text-white"><i class="fas fa-xmark"></i></button>
    </div>
    <nav class="px-3 py-5 space-y-1">
        <?php foreach($menu as $k=>$m): ?>
            <a href="<?= $k==='employes'?'showUser.php':'?tab='.$k ?>"
               <?= $k==='chat'?'data-chat-link="mobile"':'' ?>
               <?= $k==='employes'?'data-reset-badge="employes"':'' ?>
               class="flex items-center justify-between px-4 py-3 rounded-xl transition-colors
               <?= $tab===$k?'bg-white text-fuchsia-700 font-bold':'text-white hover:bg-fuchsia-600' ?>">
                <span><i class="fas <?= $m[1] ?> w-5 text-center mr-2"></i><?= $m[0] ?></span>
                <?php if($k==='chat'): ?>
                    <span data-chat-badge class="hidden min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center"></span>
                <?php elseif(isset($menuBadges[$k]) && (int)$menuBadges[$k] > 0): ?>
                    <span data-badge="<?= $k ?>" class="inline-flex min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center animate-pulse"><?= (int)$menuBadges[$k]>99?'99+':(int)$menuBadges[$k] ?></span>
                <?php else: ?>
                    <span data-badge="<?= $k ?>" class="hidden min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center"></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<main class="md:ml-80 min-h-screen px-3 sm:px-6 lg:px-8 pb-8 pt-0 space-y-6 bg-gray-50 dark:bg-gray-900">
    <!-- Header -->
    <header class="top-header sticky top-0 z-30 rounded-none md:rounded-b-2xl p-3 sm:p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <button id="openMobileMenu" class="md:hidden w-10 h-10 rounded-xl bg-fuchsia-100 dark:bg-gray-700 text-fuchsia-700 dark:text-white"><i class="fas fa-bars"></i></button>
            <a href="?tab=profil" class="block">
                <?php if($avatar!==''): ?><img src="<?= htmlspecialchars($avatar,ENT_QUOTES,'UTF-8') ?>" class="w-12 h-12 rounded-full border-2 border-primary object-cover">
                <?php else: ?><div class="w-12 h-12 rounded-full border-2 border-primary bg-fuchsia-50 dark:bg-gray-700 flex items-center justify-center text-primary"><i class="fas fa-user"></i></div><?php endif; ?>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-lg sm:text-xl font-black">Bienvenue, <?= htmlspecialchars($me['prenom']??'',ENT_QUOTES,'UTF-8') ?></h1>
                <p class="text-sm text-gray-500 dark:text-gray-300"><?= htmlspecialchars(($me['prenom']??'').' '.($me['nom']??''),ENT_QUOTES,'UTF-8') ?></p>
                <p class="truncate text-xs text-gray-400"><?= htmlspecialchars((string)($me['email']??''),ENT_QUOTES,'UTF-8') ?></p>
            </div>
        </div>
        <div class="flex w-full sm:w-auto items-center justify-end gap-3">
            <p class="text-sm text-gray-400 hidden sm:block"><?= date('d/m/Y') ?></p>
            <button id="darkToggle" class="w-10 h-10 rounded-xl bg-fuchsia-50 dark:bg-gray-700 text-fuchsia-700 dark:text-white border border-fuchsia-200 dark:border-gray-600"><i class="fa-solid fa-moon"></i></button>
        </div>
    </header>

    <?php if($flash!==''): ?>
        <div class="rounded-xl px-4 py-3 border bg-fuchsia-50 text-fuchsia-800 border-fuchsia-300 dark:bg-fuchsia-900/20 dark:text-fuchsia-200">
            <?= htmlspecialchars($flash,ENT_QUOTES,'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm"><p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">En attente</p><p class="text-3xl font-black text-primary mt-1"><?= (int)$stats['pending_count'] ?></p></article>
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm"><p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">En cours</p><p class="text-3xl font-black text-fuchsia-500 mt-1"><?= (int)$stats['ongoing_count'] ?></p></article>
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm"><p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">Terminés</p><p class="text-3xl font-black text-green-600 mt-1"><?= (int)$stats['done_count'] ?></p></article>
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm"><p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">Employés</p><p class="text-3xl font-black text-gray-700 dark:text-white mt-1"><?= (int)$stats['employee_count'] ?></p></article>
    </section>

    <!-- Commandes -->
    <?php if(showA('commandes',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-4 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Commandes en attente</h2>
            <div class="space-y-3">
                <?php if(!$pending): ?><p class="text-sm text-gray-400">Aucune commande.</p><?php endif; ?>
                <?php foreach($pending as $o): ?>
                    <div class="border border-gray-100 dark:border-gray-700 rounded-xl p-4 hover:border-fuchsia-200 transition">
                        <p class="font-semibold text-primary">#<?= (int)$o['id'] ?> — <?= htmlspecialchars($o['type_projet'],ENT_QUOTES,'UTF-8') ?></p>
                        <p class="text-sm text-gray-500">Client : <?= htmlspecialchars($o['client_prenom'].' '.$o['client_nom'],ENT_QUOTES,'UTF-8') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Projets -->
    <?php if(showA('projets',$tab)): ?>
        <section class="grid xl:grid-cols-3 gap-4">
            <div class="xl:col-span-2 bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <h2 class="text-lg font-bold mb-4 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Projets en cours</h2>
                <div class="grid md:grid-cols-2 gap-4">
                    <?php if(!$ongoing): ?><p class="text-sm text-gray-400">Aucun projet.</p><?php endif; ?>
                    <?php foreach($ongoing as $p):
                        $pl=(int)($p['planned_units']??0);$dn=(int)($p['done_units']??0);
                        $pr=$pl>0?(int)min(100,round(($dn/$pl)*100)):(int)$p['latest_progress'];
                    ?>
                        <article class="border border-gray-100 dark:border-gray-700 rounded-xl p-4 hover:border-fuchsia-200 transition">
                            <p class="font-semibold text-primary">#<?= (int)$p['id'] ?> — <?= htmlspecialchars($p['type_projet'],ENT_QUOTES,'UTF-8') ?></p>
                            <p class="text-sm text-gray-500">Employé : <?= htmlspecialchars(trim($p['emp_prenom'].' '.$p['emp_nom']),ENT_QUOTES,'UTF-8') ?></p>
                            <p class="text-xs text-gray-400">Sous-tâches : <?= $dn ?>/<?= $pl ?></p>
                            <p class="text-xs text-gray-400">Fin prévue : <?= !empty($p['planned_end_date'])?htmlspecialchars(date('d/m/Y',strtotime((string)$p['planned_end_date'])),ENT_QUOTES,'UTF-8'):'Non définie' ?></p>
                            <div class="mt-2"><div class="flex justify-between text-xs mb-1 text-gray-400"><span>Progression</span><span class="font-semibold text-primary"><?= $pr ?>%</span></div><div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full"><div class="h-full bg-primary rounded-full" style="width:<?= $pr ?>%"></div></div></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
            <aside class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <h3 class="text-base font-bold mb-3 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Charge employés</h3>
                <div class="space-y-2">
                    <?php foreach($loads as $l): ?>
                        <div class="rounded-xl bg-fuchsia-50 dark:bg-gray-700/40 p-3 flex justify-between items-center text-sm">
                            <span><?= htmlspecialchars($l['prenom'].' '.$l['nom'],ENT_QUOTES,'UTF-8') ?></span>
                            <span class="font-black text-primary"><?= (int)$l['active_projects'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-4 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Validation des sous-tâches</h2>
            <div class="space-y-3">
                <?php if(!$pendingSubtasks): ?><p class="text-sm text-gray-400">Aucune sous-tâche en attente.</p><?php endif; ?>
                <?php foreach($pendingSubtasks as $s): ?>
                    <div class="border border-gray-100 dark:border-gray-700 rounded-xl p-4">
                        <div class="flex justify-between gap-2 mb-1">
                            <p class="font-semibold text-primary">#<?= (int)$s['projet_id'] ?> — <?= htmlspecialchars($s['type_projet'],ENT_QUOTES,'UTF-8') ?></p>
                            <span class="text-xs px-2 py-1 rounded-full bg-amber-50 text-amber-700 font-medium">En attente</span>
                        </div>
                        <p class="text-sm text-gray-500">Employé : <?= htmlspecialchars(trim($s['prenom'].' '.$s['nom']),ENT_QUOTES,'UTF-8') ?></p>
                        <p class="text-sm text-gray-500">Titre : <?= htmlspecialchars($s['title'],ENT_QUOTES,'UTF-8') ?></p>
                        <p class="text-xs text-gray-400">Sous-tâche : <?= htmlspecialchars($s['label'],ENT_QUOTES,'UTF-8') ?></p>
                        <form method="POST" class="mt-2">
                            <input type="hidden" name="action" value="validate_subtask">
                            <input type="hidden" name="subtask_id" value="<?= (int)$s['id'] ?>">
                            <button class="px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-semibold transition">Valider</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Utilisateurs -->
    <?php if(showA('employes',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-bold">Liste des employés</h2>
                <a href="showUser.php" class="px-4 py-2 rounded-xl bg-primary text-white font-semibold hover:bg-fuchsia-800 transition text-sm">Ouvrir showUser</a>
            </div>
            <div class="h-[560px] rounded-xl border border-gray-100 dark:border-gray-700 overflow-hidden">
                <iframe src="showUser.php" class="w-full h-full"></iframe>
            </div>
        </section>
    <?php endif; ?>

    <!-- Progression -->
    <?php if(showA('progression',$tab)): ?>
        <section class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php if(!$projectPieSeries): ?><article class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm"><p class="text-sm text-gray-400">Aucun projet en cours.</p></article><?php endif; ?>
            <?php foreach($projectPieSeries as $ps): ?>
                <article class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <h3 class="text-sm font-bold mb-3 text-primary"><?= htmlspecialchars($ps['label'],ENT_QUOTES,'UTF-8') ?></h3>
                    <div class="h-48"><canvas id="adminProjectPie<?= (int)$ps['id'] ?>" class="w-full h-full"></canvas></div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <!-- Présence -->
    <?php if(showA('presence',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-4 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Présence aujourd'hui</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-gray-400 border-b border-gray-100 dark:border-gray-700"><th class="pb-2">Employé</th><th class="pb-2">Statut</th><th class="pb-2">Première connexion</th><th class="pb-2">Dernière activité</th></tr></thead>
                    <tbody>
                        <?php foreach($todayPresence as $row): $present=!empty($row['first_seen_at']); ?>
                            <tr class="border-t border-gray-50 dark:border-gray-700/50">
                                <td class="py-2.5"><?= htmlspecialchars($row['prenom'].' '.$row['nom'],ENT_QUOTES,'UTF-8') ?></td>
                                <td class="py-2.5"><?= $present?'<span class="px-2 py-1 rounded-full bg-green-50 text-green-700 text-xs font-medium">Présent</span>':'<span class="px-2 py-1 rounded-full bg-red-50 text-red-600 text-xs font-medium">Absent</span>' ?></td>
                                <td class="py-2.5 text-gray-500"><?= $present?htmlspecialchars(date('H:i:s',strtotime($row['first_seen_at'])),ENT_QUOTES,'UTF-8'):'—' ?></td>
                                <td class="py-2.5 text-gray-500"><?= $present?htmlspecialchars(date('H:i:s',strtotime($row['last_seen_at'])),ENT_QUOTES,'UTF-8'):'—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-4 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Absences du mois (<?= date('m/Y') ?>)</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-gray-400 border-b border-gray-100 dark:border-gray-700"><th class="pb-2">Employé</th><th class="pb-2">Présences</th><th class="pb-2">Absences</th><th class="pb-2">État</th></tr></thead>
                    <tbody>
                        <?php foreach($monthlyPresence as $row): $absentDays=(int)$row['absent_days']; ?>
                            <tr class="border-t border-gray-50 dark:border-gray-700/50">
                                <td class="py-2.5"><?= htmlspecialchars($row['prenom'].' '.$row['nom'],ENT_QUOTES,'UTF-8') ?></td>
                                <td class="py-2.5"><?= (int)$row['present_days'] ?> / <?= $daysElapsed ?></td>
                                <td class="py-2.5"><?= $absentDays ?></td>
                                <td class="py-2.5"><?= $absentDays>5?'<span class="px-2 py-1 rounded-full bg-red-50 text-red-600 text-xs font-medium">Alerte envoyée</span>':'<span class="px-2 py-1 rounded-full bg-green-50 text-green-700 text-xs font-medium">OK</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- Profil -->
    <?php if(showA('profil',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-4 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Modifier mon profil</h2>
            <form method="POST" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="update_profile">
                <div class="md:col-span-2 flex items-center gap-4">
                    <?php if($avatar!==''): ?><img src="<?= htmlspecialchars($avatar,ENT_QUOTES,'UTF-8') ?>" class="w-16 h-16 rounded-full border-2 border-primary object-cover">
                    <?php else: ?><div class="w-16 h-16 rounded-full border-2 border-primary bg-fuchsia-50 dark:bg-gray-700 flex items-center justify-center text-primary text-xl"><i class="fas fa-user"></i></div><?php endif; ?>
                    <div class="flex-1"><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Photo de profil</label><input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-sm"></div>
                </div>
                <div><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Prénom</label><input name="prenom" required value="<?= htmlspecialchars($me['prenom']??'',ENT_QUOTES,'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700"></div>
                <div><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Nom</label><input name="nom" required value="<?= htmlspecialchars($me['nom']??'',ENT_QUOTES,'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700"></div>
                <div class="md:col-span-2"><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Email</label><input type="email" name="email" required value="<?= htmlspecialchars($me['email']??'',ENT_QUOTES,'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700"></div>
                <div class="md:col-span-2"><button class="px-6 py-2.5 rounded-xl bg-primary text-white font-semibold hover:bg-fuchsia-800 transition">Sauvegarder</button></div>
            </form>
        </section>
    <?php endif; ?>

    <!-- Chat -->
    <?php if(showA('chat',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="bg-primary text-white px-5 py-3 font-bold text-sm tracking-wide">Chat équipes</div>
            <div class="grid grid-cols-1 md:grid-cols-[220px_minmax(0,1fr)] h-auto md:h-[500px]">
                <div id="chatContacts" class="max-h-56 md:max-h-none border-b md:border-b-0 md:border-r border-gray-100 dark:border-gray-700 overflow-auto bg-fuchsia-50 dark:bg-gray-900/30"></div>
                <div class="flex flex-col">
                    <div id="chatTarget" class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 text-sm font-semibold text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-900/20">Choisissez un contact</div>
                    <div id="chatMessages" class="min-h-[280px] md:min-h-0 flex-1 overflow-auto p-4 space-y-2 bg-gray-50 dark:bg-gray-900/30"></div>
                    <div class="p-3 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row gap-2 bg-white dark:bg-gray-800">
                        <input id="chatInput" type="text" placeholder="Votre message..." class="flex-1 rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <button id="chatSend" class="w-full sm:w-auto px-4 py-2 rounded-xl bg-primary text-white text-sm font-semibold hover:bg-fuchsia-800 transition">Envoyer</button>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<script>
// ── Dark mode ─────────────────────────────────────────────────
const root = document.documentElement;
const darkToggle = document.getElementById('darkToggle');
function syncIcon() {
    if (!darkToggle) return;
    darkToggle.innerHTML = root.classList.contains('dark')
        ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
}
if (localStorage.getItem('theme') === 'dark') root.classList.add('dark');
syncIcon();
if (darkToggle) darkToggle.addEventListener('click', () => {
    root.classList.toggle('dark');
    localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
    syncIcon();
});

// ── Menu mobile ───────────────────────────────────────────────
const openBtn = document.getElementById('openMobileMenu');
const closeBtn = document.getElementById('closeMobileMenu');
const mobileSidebar = document.getElementById('mobileSidebar');
const overlay = document.getElementById('mobileOverlay');
function toggleMenu(show) {
    if (!mobileSidebar || !overlay) return;
    mobileSidebar.classList.toggle('-translate-x-full', !show);
    overlay.classList.toggle('hidden', !show);
}
if (openBtn) openBtn.addEventListener('click', () => toggleMenu(true));
if (closeBtn) closeBtn.addEventListener('click', () => toggleMenu(false));
if (overlay) overlay.addEventListener('click', () => toggleMenu(false));

// ── AJAX : reset badge "Utilisateurs" ─────────────────────────
// Quand l'admin clique sur le lien "Utilisateurs" (data-reset-badge="employes"),
// on envoie une requête POST AJAX AVANT la navigation pour réinitialiser la session.
// On masque aussi le badge immédiatement côté JS (sans attendre le rechargement).
document.querySelectorAll('[data-reset-badge]').forEach(link => {
    link.addEventListener('click', async function(e) {
        const key = this.getAttribute('data-reset-badge');

        // 1. Masquer le badge immédiatement dans le DOM (les deux sidebars)
        document.querySelectorAll(`[data-badge="${key}"]`).forEach(badge => {
            badge.textContent = '';
            badge.classList.add('hidden');
            badge.classList.remove('inline-flex', 'animate-pulse');
        });

        // 2. Envoyer la requête AJAX pour mettre à jour la SESSION côté serveur
        try {
            await fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'reset_badge', key: key })
            });
        } catch (err) {
            console.error('reset_badge AJAX error:', err);
        }
        // 3. Laisser la navigation normale se faire (on ne fait pas e.preventDefault())
    });
});

// ── Graphiques ────────────────────────────────────────────────
const adminPies = <?= json_encode($projectPieSeries, JSON_UNESCAPED_UNICODE) ?>;
adminPies.forEach(p => {
    const c = document.getElementById(`adminProjectPie${p.id}`); if (!c) return;
    new Chart(c, {
        type: 'pie',
        data: { labels: ['Terminées', 'Restantes'], datasets: [{ data: [p.done, p.remaining], backgroundColor: ['#C026D3', '#f3e8ff'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { family: 'Poppins', size: 11 } } } } }
    });
});
</script>
<script>
window.__CHAT_CONFIG__ = {
    apiUrl: 'chat_api.php',
    myId: '<?= htmlspecialchars((string)$matricule, ENT_QUOTES, 'UTF-8') ?>',
    storageKey: 'admin_chat_seen',
    activeContactKey: 'admin_chat_contact'
};
</script>
<script src="../js/dashboard-chat.js"></script>
</body>
</html>
