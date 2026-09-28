<?php
session_start();
require_once "db.php";
if (!isset($_SESSION['matricule']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'client') { header("Location: connexion.php"); exit; }
$matricule = (int) $_SESSION['matricule'];
$tab = $_GET['tab'] ?? 'overview';
if (!in_array($tab, ['overview','commandes','progression','chat','profil'], true)) $tab = 'overview';
function showC(string $s,string $t): bool { return $t === 'overview' || $t === $s; }
$flash='';

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_progress (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL, employe_id INT NOT NULL, note TEXT NOT NULL, progress_value TINYINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_plans (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL UNIQUE, employe_id INT NOT NULL, planned_start_date DATE NOT NULL, planned_end_date DATE NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_task_titles (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL, employe_id INT NOT NULL, title VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_subtasks (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL, task_title_id INT NOT NULL, employe_id INT NOT NULL, label VARCHAR(255) NOT NULL, is_done TINYINT(1) NOT NULL DEFAULT 0, done_at DATE DEFAULT NULL, is_validated TINYINT(1) NOT NULL DEFAULT 0, validated_at DATE DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");

function ensureColumn(mysqli $conn, string $table, string $column, string $definition): void {
    $sql = "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, "ss", $table, $column);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['c' => 0];
    mysqli_stmt_close($stmt);
    if ((int) ($row['c'] ?? 0) === 0) mysqli_query($conn, "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}
ensureColumn($conn, 'project_subtasks', 'is_validated', "TINYINT(1) NOT NULL DEFAULT 0");
ensureColumn($conn, 'project_subtasks', 'validated_at', "DATE DEFAULT NULL");

function syncProjectsCompletionForClient(mysqli $conn, int $clientId): void {
    $stmt = mysqli_prepare($conn, "SELECT p.id,
                                          (SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id = p.id) AS planned_units,
                                          (SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id = p.id AND ps.is_validated = 1) AS done_units,
                                          (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id) AS total_tasks,
                                          (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id AND t.statut = 'terminee') AS done_tasks,
                                          (SELECT COALESCE(MAX(progress_value), 0) FROM project_progress pr WHERE pr.projet_id = p.id) AS latest_progress
                                   FROM projet p
                                   WHERE p.id_client = ? AND p.statut <> 'termine'");
    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param($stmt, "i", $clientId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $projects = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);

    $update = mysqli_prepare($conn, "UPDATE projet SET statut = 'termine', date_fin = COALESCE(date_fin, CURDATE()) WHERE id = ? AND statut <> 'termine'");
    if (!$update) {
        return;
    }

    foreach ($projects as $project) {
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

syncProjectsCompletionForClient($conn, $matricule);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {
    $prenom = trim((string) ($_POST['prenom'] ?? ''));
    $nom = trim((string) ($_POST['nom'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    if ($prenom !== '' && $nom !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $photoPath = null; $uploadError = '';
        if (isset($_FILES['photo']) && (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $fileErr = (int) ($_FILES['photo']['error'] ?? UPLOAD_ERR_OK);
            if ($fileErr === UPLOAD_ERR_OK) {
                $tmpPath = (string) ($_FILES['photo']['tmp_name'] ?? '');
                $size = (int) ($_FILES['photo']['size'] ?? 0);
                $ext = strtolower((string) pathinfo((string) ($_FILES['photo']['name'] ?? ''), PATHINFO_EXTENSION));
                $allowedExt = ['jpg','jpeg','png','webp'];
                if (!in_array($ext, $allowedExt, true)) { $uploadError = "Format image invalide."; }
                elseif ($size > 2 * 1024 * 1024) { $uploadError = "Image trop lourde (max 2 Mo)."; }
                else {
                    $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'profiles';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $fileName = 'u' . $matricule . '_' . date('YmdHis') . '.' . $ext;
                    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
                    if (is_dir($uploadDir) && (move_uploaded_file($tmpPath, $targetPath) || @copy($tmpPath, $targetPath))) { $photoPath = '../uploads/profiles/' . $fileName; }
                    else { $uploadError = "Échec du téléversement."; }
                }
            } else { $uploadError = "Fichier photo invalide (code " . $fileErr . ")."; }
        }
        if ($uploadError !== '') { $flash = $uploadError; }
        else {
            if ($photoPath !== null) { $st = mysqli_prepare($conn, "UPDATE users SET prenom=?, nom=?, email=?, photo=? WHERE matricule=?"); mysqli_stmt_bind_param($st, "ssssi", $prenom, $nom, $email, $photoPath, $matricule); }
            else { $st = mysqli_prepare($conn, "UPDATE users SET prenom=?, nom=?, email=? WHERE matricule=?"); mysqli_stmt_bind_param($st, "sssi", $prenom, $nom, $email, $matricule); }
            if (mysqli_stmt_execute($st)) { $_SESSION['prenom']=$prenom; $_SESSION['nom']=$nom; $_SESSION['email']=$email; $flash="Profil mis à jour."; }
            else { $flash="Échec de mise à jour."; }
            mysqli_stmt_close($st);
        }
    } else { $flash="Informations invalides."; }
}

$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nom,prenom,email,photo FROM users WHERE matricule=$matricule")) ?: [];
$photoRelPath = (string) ($me['photo'] ?? '');
$photoRelPathDisplay = $photoRelPath;
if ($photoRelPath !== '' && preg_match('/^https?:\/\//i', $photoRelPath) !== 1 && strpos($photoRelPath, 'uploads/') === 0) $photoRelPathDisplay = '../' . $photoRelPath;
$hasAvatar = false;
if ($photoRelPathDisplay !== '') {
    if (preg_match('/^https?:\/\//i', $photoRelPathDisplay) === 1) { $hasAvatar = true; }
    else { $photoAbsPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace(['/',  '\\'], DIRECTORY_SEPARATOR, $photoRelPathDisplay); $hasAvatar = is_file($photoAbsPath); }
}
$avatar = $hasAvatar ? $photoRelPathDisplay : '';
$projects = mysqli_fetch_all(mysqli_query($conn, "SELECT p.id,p.type_projet,p.statut,COALESCE(e.nom,'Non assigne') emp_nom,COALESCE(e.prenom,'') emp_prenom,(SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id=p.id) planned_units,(SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id=p.id AND ps.is_validated=1) done_units,(SELECT planned_end_date FROM project_plans pp WHERE pp.projet_id=p.id LIMIT 1) planned_end_date,(SELECT COALESCE(MAX(progress_value),0) FROM project_progress pr WHERE pr.projet_id=p.id) latest_progress FROM projet p LEFT JOIN projets_employes pe ON pe.projet_id=p.id LEFT JOIN users e ON e.matricule=pe.employe_id WHERE p.id_client=$matricule ORDER BY p.created_at DESC"), MYSQLI_ASSOC);
$pending=0;$ongoing=0;$done=0;
foreach($projects as $p){ if($p['statut']==='en_attente')$pending++; elseif($p['statut']==='en_cours')$ongoing++; elseif($p['statut']==='termine')$done++; }
$projectPieSeries = [];
foreach ($projects as $project) {
    $planned=(int)($project['planned_units']??0); $doneUnits=(int)($project['done_units']??0);
    $projectPieSeries[]=['id'=>(int)$project['id'],'label'=>'#'.(int)$project['id'].' '.(string)$project['type_projet'],'done'=>$doneUnits,'remaining'=>max(0,$planned-$doneUnits),'deadline'=>$project['planned_end_date']??null];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Dashboard Client | EltaRH</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.tailwindcss.com" onerror="this.onerror=null;this.src='../js/cdn.js';"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>tailwind.config={darkMode:'class',theme:{extend:{colors:{primary:'#C026D3'},fontFamily:{poppins:['Poppins','sans-serif']}}}}</script>
<style>
.pulse-dot{animation:pulse 1s infinite}
@keyframes pulse{0%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(1.2)}100%{opacity:1;transform:scale(1)}}
/* Sidebar */
.sidebar-header { background-color: #a21caf; }
/* Header top */
.top-header { background-color: #ffffff; border-bottom: 2px solid #f0abfc; }
.dark .top-header { background-color: #1f2937; border-bottom-color: #7e22ce; }
/* Cards */
.stat-card { border-left: 4px solid #C026D3; }
</style>
</head>
<body class="font-poppins bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100">

<!-- Sidebar desktop -->
<aside class="fixed left-0 top-0 h-screen w-80 bg-fuchsia-700 dark:bg-gray-800 border-r border-fuchsia-800 dark:border-gray-700 shadow-xl hidden md:flex flex-col z-40">
    <div class="px-5 py-4 flex items-center gap-3 border-b border-fuchsia-600 dark:border-gray-700 bg-fuchsia-800 dark:bg-gray-900">
        <img src="../image/logo EltaRH.png" alt="EltaRH" class="h-20 w-auto">
    </div>
    <?php $menu=['overview'=>['Vue d\'ensemble','fa-th-large'],'commandes'=>['Mes commandes','fa-inbox'],'progression'=>['Progression','fa-chart-pie'],'chat'=>['Chat','fa-comments'],'profil'=>['Profil','fa-user']]; ?>
    <nav class="flex-1 px-3 py-5 space-y-1">
        <?php foreach($menu as $k=>$m): ?>
            <a href="?tab=<?= $k ?>" <?= $k==='chat'?'data-chat-link="desktop"':'' ?>
               class="flex items-center justify-between gap-3 px-4 py-3 rounded-xl transition-colors
               <?= $tab===$k ? 'bg-white text-fuchsia-700 font-bold shadow' : 'text-white hover:bg-fuchsia-600 dark:hover:bg-fuchsia-900/30' ?>">
                <span><i class="fas <?= $m[1] ?> w-5 text-center"></i><span class="ml-2"><?= $m[0] ?></span></span>
                <?php if($k==='chat'): ?><span data-chat-badge class="hidden min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center"></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="p-4 border-t border-fuchsia-600 dark:border-gray-700">
        <a href="logout.php" class="flex items-center gap-3 px-4 py-3 rounded-xl text-white hover:bg-fuchsia-600 transition font-semibold">
            <i class="fas fa-right-from-bracket w-5 text-center"></i>Déconnexion
        </a>
    </div>
</aside>

<!-- Overlay + Sidebar mobile -->
<div id="mobileOverlay" class="fixed inset-0 bg-black/50 hidden z-30 md:hidden"></div>
<aside id="mobileSidebar" class="fixed left-0 top-0 h-screen w-[88vw] max-w-80 bg-fuchsia-700 dark:bg-gray-800 border-r border-fuchsia-800 dark:border-gray-700 shadow-xl transform -translate-x-full transition-transform z-40 md:hidden">
    <div class="px-5 py-4 border-b border-fuchsia-600 dark:border-gray-700 bg-fuchsia-800 dark:bg-gray-900 flex items-center justify-between">
        <img src="../image/logo EltaRH.png" alt="EltaRH" class="h-16 w-auto">
        <button id="closeMobileMenu" class="w-9 h-9 rounded-lg bg-white/20 text-white"><i class="fas fa-xmark"></i></button>
    </div>
    <nav class="px-3 py-5 space-y-1">
        <?php foreach($menu as $k=>$m): ?>
            <a href="?tab=<?= $k ?>" <?= $k==='chat'?'data-chat-link="mobile"':'' ?>
               class="flex items-center justify-between px-4 py-3 rounded-xl transition-colors
               <?= $tab===$k ? 'bg-white text-fuchsia-700 font-bold' : 'text-white hover:bg-fuchsia-600' ?>">
                <span><i class="fas <?= $m[1] ?> w-5 text-center mr-2"></i><?= $m[0] ?></span>
                <?php if($k==='chat'): ?><span data-chat-badge class="hidden min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center"></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<main class="md:ml-80 min-h-screen px-3 sm:px-6 lg:px-8 pb-8 pt-0 space-y-6 bg-gray-50 dark:bg-gray-900">
    <!-- Header top -->
    <header class="top-header sticky top-0 z-30 rounded-none md:rounded-b-2xl p-3 sm:p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <button id="openMobileMenu" class="md:hidden w-10 h-10 rounded-xl bg-fuchsia-100 dark:bg-gray-700 text-fuchsia-700 dark:text-white"><i class="fas fa-bars"></i></button>
            <a href="?tab=profil" class="block">
                <?php if($avatar!==''): ?><img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" class="w-12 h-12 rounded-full border-2 border-primary object-cover">
                <?php else: ?><div class="w-12 h-12 rounded-full border-2 border-primary bg-fuchsia-50 dark:bg-gray-700 flex items-center justify-center text-primary"><i class="fas fa-user"></i></div><?php endif; ?>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-lg sm:text-xl font-black text-gray-800 dark:text-white">Bienvenue, <?= htmlspecialchars($me['prenom'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-sm text-gray-500 dark:text-gray-300"><?= htmlspecialchars(($me['prenom']??'').' '.($me['nom']??''), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="truncate text-xs text-gray-400"><?= htmlspecialchars((string)($me['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="flex w-full sm:w-auto items-center justify-end gap-3">
            <p class="text-sm text-gray-500 hidden sm:block"><?= date('d/m/Y') ?></p>
            <button id="darkToggle" class="w-10 h-10 rounded-xl bg-fuchsia-50 dark:bg-gray-700 text-fuchsia-700 dark:text-white border border-fuchsia-200 dark:border-gray-600"><i class="fa-solid fa-moon"></i></button>
            <a href="Commande.php" class="px-4 py-2 rounded-xl bg-primary text-white font-semibold hover:bg-fuchsia-800 transition">+ Commande</a>
        </div>
    </header>

    <?php if($flash!==''): ?>
        <div class="rounded-xl px-4 py-3 border bg-fuchsia-50 text-fuchsia-800 border-fuchsia-300 dark:bg-fuchsia-900/20 dark:text-fuchsia-200 dark:border-fuchsia-800">
            <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Stat cards -->
    <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">En attente</p>
            <p class="text-3xl font-black text-primary mt-1"><?= $pending ?></p>
        </article>
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">En cours</p>
            <p class="text-3xl font-black text-fuchsia-500 mt-1"><?= $ongoing ?></p>
        </article>
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">Terminées</p>
            <p class="text-3xl font-black text-green-600 mt-1"><?= $done ?></p>
        </article>
    </section>

    <!-- Commandes -->
    <?php if(showC('commandes',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-4 text-gray-800 dark:text-white border-b border-fuchsia-100 dark:border-gray-700 pb-2">Mes commandes</h2>
            <div class="space-y-3">
                <?php if(!$projects): ?><p class="text-sm text-gray-400">Aucune commande.</p><?php endif; ?>
                <?php foreach($projects as $p):
                    $planned=(int)($p['planned_units']??0);$doneUnits=(int)($p['done_units']??0);
                    $pr=$planned>0?(int)min(100,round(($doneUnits/$planned)*100)):(int)$p['latest_progress'];
                ?>
                    <div class="border border-gray-100 dark:border-gray-700 rounded-xl p-4 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition">
                        <div class="flex justify-between gap-2">
                            <p class="font-semibold text-primary">#<?= (int)$p['id'] ?> - <?= htmlspecialchars($p['type_projet'], ENT_QUOTES, 'UTF-8') ?></p>
                            <span class="text-xs px-2 py-1 rounded-full bg-fuchsia-50 dark:bg-fuchsia-900/30 text-primary font-medium"><?= htmlspecialchars($p['statut'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Employé : <?= htmlspecialchars(trim($p['emp_prenom'].' '.$p['emp_nom']), ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-gray-400">Fin prévue : <?= !empty($p['planned_end_date']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$p['planned_end_date'])), ENT_QUOTES, 'UTF-8') : 'Non définie' ?></p>
                        <div class="mt-3">
                            <div class="flex justify-between text-xs mb-1 text-gray-500"><span>Progression</span><span class="font-semibold text-primary"><?= $pr ?>%</span></div>
                            <div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full"><div class="h-full bg-primary rounded-full transition-all" style="width:<?= $pr ?>%"></div></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Progression -->
    <?php if(showC('progression',$tab)): ?>
        <section class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php if(!$projectPieSeries): ?>
                <article class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                    <p class="text-sm text-gray-400">Aucun projet à afficher.</p>
                </article>
            <?php endif; ?>
            <?php foreach($projectPieSeries as $pie): ?>
                <article class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <h3 class="text-sm font-bold mb-1 text-primary"><?= htmlspecialchars($pie['label'], ENT_QUOTES, 'UTF-8') ?></h3>
                    <p class="text-xs text-gray-400 mb-3">Fin prévue : <?= !empty($pie['deadline']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$pie['deadline'])), ENT_QUOTES, 'UTF-8') : 'Non définie' ?></p>
                    <div class="h-48"><canvas id="clientProjectPie<?= (int)$pie['id'] ?>" class="w-full h-full"></canvas></div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <!-- Profil -->
    <?php if(showC('profil',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-4 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Modifier mon profil</h2>
            <form method="POST" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="update_profile">
                <div class="md:col-span-2 flex items-center gap-4">
                    <?php if($avatar!==''): ?><img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" class="w-16 h-16 rounded-full border-2 border-primary object-cover">
                    <?php else: ?><div class="w-16 h-16 rounded-full border-2 border-primary bg-fuchsia-50 dark:bg-gray-700 flex items-center justify-center text-primary text-xl"><i class="fas fa-user"></i></div><?php endif; ?>
                    <div class="flex-1">
                        <label class="text-sm font-medium text-gray-600 dark:text-gray-300">Photo de profil</label>
                        <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-sm">
                    </div>
                </div>
                <div><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Prénom</label><input name="prenom" required value="<?= htmlspecialchars($me['prenom']??'', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700"></div>
                <div><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Nom</label><input name="nom" required value="<?= htmlspecialchars($me['nom']??'', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700"></div>
                <div class="md:col-span-2"><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Email</label><input type="email" name="email" required value="<?= htmlspecialchars($me['email']??'', ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700"></div>
                <div class="md:col-span-2"><button class="px-6 py-2.5 rounded-xl bg-primary text-white font-semibold hover:bg-fuchsia-800 transition">Sauvegarder</button></div>
            </form>
        </section>
    <?php endif; ?>

    <!-- Chat -->
    <?php if(showC('chat',$tab)): ?>
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
// ── Dark mode ────────────────────────────────────────────────
const root=document.documentElement,darkToggle=document.getElementById('darkToggle');
function syncIcon(){if(!darkToggle)return;darkToggle.innerHTML=root.classList.contains('dark')?'<i class="fa-solid fa-sun"></i>':'<i class="fa-solid fa-moon"></i>';}
if(localStorage.getItem('theme')==='dark')root.classList.add('dark');
syncIcon();
if(darkToggle)darkToggle.addEventListener('click',()=>{root.classList.toggle('dark');localStorage.setItem('theme',root.classList.contains('dark')?'dark':'light');syncIcon();});

// ── Menu mobile ───────────────────────────────────────────────
const openBtn=document.getElementById('openMobileMenu'),closeBtn=document.getElementById('closeMobileMenu'),mobileSidebar=document.getElementById('mobileSidebar'),overlay=document.getElementById('mobileOverlay');
function toggleMenu(show){if(!mobileSidebar||!overlay)return;mobileSidebar.classList.toggle('-translate-x-full',!show);overlay.classList.toggle('hidden',!show);}
if(openBtn)openBtn.addEventListener('click',()=>toggleMenu(true));
if(closeBtn)closeBtn.addEventListener('click',()=>toggleMenu(false));
if(overlay)overlay.addEventListener('click',()=>toggleMenu(false));

// ── Graphiques progression ────────────────────────────────────
const clientPies=<?= json_encode($projectPieSeries, JSON_UNESCAPED_UNICODE) ?>;
clientPies.forEach(p=>{
    const c=document.getElementById(`clientProjectPie${p.id}`);if(!c)return;
    new Chart(c,{type:'pie',data:{labels:['Terminées','Restantes'],datasets:[{data:[p.done,p.remaining],backgroundColor:['#C026D3','#f3e8ff']}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{family:'Poppins',size:11}}}}}});
});
</script>
<script>
window.__CHAT_CONFIG__ = {
    apiUrl: 'chat_api.php',
    myId: '<?= htmlspecialchars((string) $matricule, ENT_QUOTES, 'UTF-8') ?>',
    storageKey: 'client_chat_seen',
    activeContactKey: 'client_chat_contact'
};
</script>
<script src="../js/dashboard-chat.js"></script>
</body>
</html>
