<?php
session_start();
require_once "db.php";
if (!isset($_SESSION['matricule']) || strtolower((string) ($_SESSION['role'] ?? '')) !== 'employe') { header("Location: connexion.php"); exit; }

$matricule = (int) $_SESSION['matricule'];
$tab = $_GET['tab'] ?? 'overview';
$allowedTabs = ['overview','commandes','projets','taches','progression','presence','chat','profil'];
if (!in_array($tab, $allowedTabs, true)) $tab = 'overview';
function showTab(string $section, string $tab): bool { return $tab === 'overview' || $tab === $section; }
$flash = '';

mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_progress (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL, employe_id INT NOT NULL, note TEXT NOT NULL, progress_value TINYINT UNSIGNED NOT NULL DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_project (projet_id), INDEX idx_employe (employe_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS employe_presence (id INT AUTO_INCREMENT PRIMARY KEY, employe_id INT NOT NULL, presence_date DATE NOT NULL, first_seen_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, UNIQUE KEY uniq_presence (employe_id, presence_date), INDEX idx_presence_date (presence_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_plans (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL UNIQUE, employe_id INT NOT NULL, planned_start_date DATE NOT NULL, planned_end_date DATE NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_task_titles (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL, employe_id INT NOT NULL, title VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX idx_title_project (projet_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS project_subtasks (id INT AUTO_INCREMENT PRIMARY KEY, projet_id INT NOT NULL, task_title_id INT NOT NULL, employe_id INT NOT NULL, label VARCHAR(255) NOT NULL, is_done TINYINT(1) NOT NULL DEFAULT 0, done_at DATE DEFAULT NULL, is_validated TINYINT(1) NOT NULL DEFAULT 0, validated_at DATE DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

function ensureColumn(mysqli $conn, string $table, string $column, string $definition): void {
    $sql = "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?";
    $stmt = mysqli_prepare($conn, $sql); if (!$stmt) return;
    mysqli_stmt_bind_param($stmt, "ss", $table, $column); mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: ['c' => 0]; mysqli_stmt_close($stmt);
    if ((int) ($row['c'] ?? 0) === 0) mysqli_query($conn, "ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
}
ensureColumn($conn, 'project_subtasks', 'is_validated', "TINYINT(1) NOT NULL DEFAULT 0");
ensureColumn($conn, 'project_subtasks', 'validated_at', "DATE DEFAULT NULL");

function syncProjectCompletionStatus(mysqli $conn, int $employeId, ?int $projectId = null): void {
    $sql = "SELECT p.id,
                   p.statut,
                   (SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id = p.id) AS planned_units,
                   (SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id = p.id AND ps.is_validated = 1) AS done_units,
                   (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id) AS total_tasks,
                   (SELECT COUNT(*) FROM taches t WHERE t.projet_id = p.id AND t.statut = 'terminee') AS done_tasks,
                   (SELECT COALESCE(MAX(progress_value), 0) FROM project_progress pr WHERE pr.projet_id = p.id) AS latest_progress
            FROM projet p
            JOIN projets_employes pe ON pe.projet_id = p.id
            WHERE pe.employe_id = ?";
    $types = "i";
    $params = [$employeId];

    if ($projectId !== null) {
        $sql .= " AND p.id = ?";
        $types .= "i";
        $params[] = $projectId;
    }

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return;
    }

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $projects = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) ?: [];
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

// Enregistrement présence automatique
$today = date('Y-m-d');
$presenceStmt = mysqli_prepare($conn, "INSERT INTO employe_presence (employe_id, presence_date, first_seen_at, last_seen_at) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE last_seen_at = NOW()");
mysqli_stmt_bind_param($presenceStmt, "is", $matricule, $today); mysqli_stmt_execute($presenceStmt); mysqli_stmt_close($presenceStmt);

syncProjectCompletionStatus($conn, $matricule);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $prenom=trim((string)($_POST['prenom']??'')); $nom=trim((string)($_POST['nom']??'')); $email=trim((string)($_POST['email']??''));
        if ($prenom!==''&&$nom!==''&&filter_var($email,FILTER_VALIDATE_EMAIL)) {
            $photoPath=null; $uploadError='';
            if(isset($_FILES['photo'])&&(int)($_FILES['photo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
                $fileErr=(int)($_FILES['photo']['error']??UPLOAD_ERR_OK);
                if($fileErr===UPLOAD_ERR_OK){
                    $tmpPath=(string)($_FILES['photo']['tmp_name']??''); $size=(int)($_FILES['photo']['size']??0);
                    $ext=strtolower((string)pathinfo((string)($_FILES['photo']['name']??''),PATHINFO_EXTENSION));
                    if(!in_array($ext,['jpg','jpeg','png','webp'],true)){$uploadError="Format invalide.";}
                    elseif($size>2*1024*1024){$uploadError="Image trop lourde.";}
                    else{
                        $uploadDir=__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'profiles';
                        if(!is_dir($uploadDir))mkdir($uploadDir,0777,true);
                        $fileName='u'.$matricule.'_'.date('YmdHis').'.'.$ext; $targetPath=$uploadDir.DIRECTORY_SEPARATOR.$fileName;
                        if(is_dir($uploadDir)&&(move_uploaded_file($tmpPath,$targetPath)||@copy($tmpPath,$targetPath))){$photoPath='../uploads/profiles/'.$fileName;}
                        else{$uploadError="Échec du téléversement.";}
                    }
                }else{$uploadError="Fichier invalide (code ".$fileErr.").";}
            }
            if($uploadError!==''){$flash=$uploadError;}
            else{
                if($photoPath!==null){$st=mysqli_prepare($conn,"UPDATE users SET prenom=?,nom=?,email=?,photo=? WHERE matricule=?");mysqli_stmt_bind_param($st,"ssssi",$prenom,$nom,$email,$photoPath,$matricule);}
                else{$st=mysqli_prepare($conn,"UPDATE users SET prenom=?,nom=?,email=? WHERE matricule=?");mysqli_stmt_bind_param($st,"sssi",$prenom,$nom,$email,$matricule);}
                if(mysqli_stmt_execute($st)){$_SESSION['prenom']=$prenom;$_SESSION['nom']=$nom;$_SESSION['email']=$email;$flash="Profil mis à jour.";}
                else{$flash="Échec de mise à jour.";}
                mysqli_stmt_close($st);
            }
        }else{$flash="Informations invalides.";}
    }

    if ($action === 'take_order') {
        $projectId=(int)($_POST['project_id']??0);
        if($projectId>0){
            mysqli_begin_transaction($conn);
            try{
                $chk=mysqli_prepare($conn,"SELECT id FROM projets_employes WHERE projet_id=? LIMIT 1");
                mysqli_stmt_bind_param($chk,"i",$projectId);mysqli_stmt_execute($chk);
                $taken=mysqli_fetch_assoc(mysqli_stmt_get_result($chk));mysqli_stmt_close($chk);
                if($taken)throw new RuntimeException("Commande déjà prise.");
                $up=mysqli_prepare($conn,"UPDATE projet SET statut='en_cours',date_debut=COALESCE(date_debut,CURDATE()) WHERE id=? AND statut='en_attente'");
                mysqli_stmt_bind_param($up,"i",$projectId);mysqli_stmt_execute($up);
                if(mysqli_stmt_affected_rows($up)<1){mysqli_stmt_close($up);throw new RuntimeException("Commande indisponible.");}
                mysqli_stmt_close($up);
                $ins=mysqli_prepare($conn,"INSERT INTO projets_employes (projet_id,employe_id) VALUES (?,?)");
                mysqli_stmt_bind_param($ins,"ii",$projectId,$matricule);mysqli_stmt_execute($ins);mysqli_stmt_close($ins);
                mysqli_commit($conn);$flash="Commande prise en charge.";
            }catch(Throwable $e){mysqli_rollback($conn);$flash=$e->getMessage();}
        }
    }

    if ($action === 'add_task') {
        $projectId=(int)($_POST['project_id']??0);$title=trim((string)($_POST['title']??''));
        $subtaskLabelsRaw=trim((string)($_POST['subtask_labels']??($_POST['subtask_label']??'')));
        $plannedEndDate=trim((string)($_POST['planned_end_date']??''));$todayDate=date('Y-m-d');
        $subtaskLabels=array_values(array_filter(array_map('trim',preg_split('/\r\n|\r|\n/',$subtaskLabelsRaw))));
        if($projectId>0&&$title!==''&&count($subtaskLabels)>0){
            $authProject=mysqli_prepare($conn,"SELECT projet_id FROM projets_employes WHERE projet_id=? AND employe_id=? LIMIT 1");
            mysqli_stmt_bind_param($authProject,"ii",$projectId,$matricule);mysqli_stmt_execute($authProject);
            $projectAllowed=mysqli_fetch_assoc(mysqli_stmt_get_result($authProject));mysqli_stmt_close($authProject);
            if(!$projectAllowed){$flash="Projet non autorisé.";}
            else{
                $planCheck=mysqli_prepare($conn,"SELECT planned_end_date FROM project_plans WHERE projet_id=? LIMIT 1");
                mysqli_stmt_bind_param($planCheck,"i",$projectId);mysqli_stmt_execute($planCheck);
                $planRow=mysqli_fetch_assoc(mysqli_stmt_get_result($planCheck));mysqli_stmt_close($planCheck);
                $existingEndDate=$planRow['planned_end_date']??null;$canContinue=true;
                if(empty($existingEndDate)){
                    if(DateTime::createFromFormat('Y-m-d',$plannedEndDate)===false||$plannedEndDate<$todayDate){$flash="Date de fin invalide.";$canContinue=false;}
                    else{$plan=mysqli_prepare($conn,"INSERT INTO project_plans (projet_id,employe_id,planned_start_date,planned_end_date) VALUES (?,?,CURDATE(),?) ON DUPLICATE KEY UPDATE employe_id=VALUES(employe_id)");mysqli_stmt_bind_param($plan,"iis",$projectId,$matricule,$plannedEndDate);mysqli_stmt_execute($plan);mysqli_stmt_close($plan);}
                }
                if($canContinue){
                    $existingTitle=mysqli_prepare($conn,"SELECT id FROM project_task_titles WHERE projet_id=? AND employe_id=? AND title=? LIMIT 1");
                    mysqli_stmt_bind_param($existingTitle,"iis",$projectId,$matricule,$title);mysqli_stmt_execute($existingTitle);
                    $existingTitleRow=mysqli_fetch_assoc(mysqli_stmt_get_result($existingTitle));mysqli_stmt_close($existingTitle);
                    if($existingTitleRow){$taskTitleId=(int)$existingTitleRow['id'];}
                    else{$insTitle=mysqli_prepare($conn,"INSERT INTO project_task_titles (projet_id,employe_id,title) VALUES (?,?,?)");mysqli_stmt_bind_param($insTitle,"iis",$projectId,$matricule,$title);mysqli_stmt_execute($insTitle);$taskTitleId=(int)mysqli_insert_id($conn);mysqli_stmt_close($insTitle);}
                    if($taskTitleId>0){
                        $insCount=0;
                        foreach($subtaskLabels as $subtask){if($subtask==='')continue;$insSub=mysqli_prepare($conn,"INSERT INTO project_subtasks (projet_id,task_title_id,employe_id,label) VALUES (?,?,?,?)");mysqli_stmt_bind_param($insSub,"iiis",$projectId,$taskTitleId,$matricule,$subtask);mysqli_stmt_execute($insSub);mysqli_stmt_close($insSub);$insCount++;}
                        $flash=$insCount." sous-tâche(s) enregistrée(s).";
                    }
                }
            }
        }else{$flash="Champs invalides.";}
    }

    if ($action === 'complete_subtask') {
        $subtaskId=(int)($_POST['subtask_id']??0);
        if($subtaskId>0){$mark=mysqli_prepare($conn,"UPDATE project_subtasks SET is_done=1,done_at=CURDATE() WHERE id=? AND employe_id=?");mysqli_stmt_bind_param($mark,"ii",$subtaskId,$matricule);mysqli_stmt_execute($mark);if(mysqli_stmt_affected_rows($mark)>0)$flash="Sous-tâche marquée (en attente validation).";mysqli_stmt_close($mark);}
    }

    if ($action === 'add_progress') {
        $projectId=(int)($_POST['project_id']??0);$note=trim((string)($_POST['note']??''));$progress=max(0,min(100,(int)($_POST['progress_value']??0)));
        if($projectId>0&&$note!==''){$st=mysqli_prepare($conn,"INSERT INTO project_progress (projet_id,employe_id,note,progress_value) VALUES (?,?,?,?)");mysqli_stmt_bind_param($st,"iisi",$projectId,$matricule,$note,$progress);mysqli_stmt_execute($st);mysqli_stmt_close($st);syncProjectCompletionStatus($conn,$matricule,$projectId);$flash=$progress>=100?"Progression enregistrée. Projet marqué comme terminé.":"Progression enregistrée.";}
    }
}

$uSt=mysqli_prepare($conn,"SELECT nom,prenom,email,photo FROM users WHERE matricule=?");
mysqli_stmt_bind_param($uSt,"i",$matricule);mysqli_stmt_execute($uSt);
$user=mysqli_fetch_assoc(mysqli_stmt_get_result($uSt));mysqli_stmt_close($uSt);

$photoRelPath=(string)($user['photo']??'');$photoRelPathDisplay=$photoRelPath;
if($photoRelPath!==''&&preg_match('/^https?:\/\//i',$photoRelPath)!==1&&strpos($photoRelPath,'uploads/')===0)$photoRelPathDisplay='../'.$photoRelPath;
$hasAvatar=false;
if($photoRelPathDisplay!==''){
    if(preg_match('/^https?:\/\//i',$photoRelPathDisplay)===1){$hasAvatar=true;}
    else{$photoAbsPath=__DIR__.DIRECTORY_SEPARATOR.str_replace(['/',  '\\'],DIRECTORY_SEPARATOR,$photoRelPathDisplay);$hasAvatar=is_file($photoAbsPath);}
}
$avatar=$hasAvatar?$photoRelPathDisplay:'';

$pendingOrders=mysqli_fetch_all(mysqli_query($conn,"SELECT p.id,p.type_projet,c.nom client_nom,c.prenom client_prenom FROM projet p JOIN users c ON c.matricule=p.id_client LEFT JOIN projets_employes pe ON pe.projet_id=p.id WHERE p.statut='en_attente' AND pe.id IS NULL ORDER BY p.created_at"),MYSQLI_ASSOC);
$projects=mysqli_fetch_all(mysqli_query($conn,"SELECT p.id,p.type_projet,c.nom client_nom,c.prenom client_prenom,(SELECT COUNT(*) FROM taches t WHERE t.projet_id=p.id) total_tasks,(SELECT COUNT(*) FROM taches t WHERE t.projet_id=p.id AND t.statut='terminee') done_tasks,(SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id=p.id) planned_units,(SELECT COUNT(*) FROM project_subtasks ps WHERE ps.projet_id=p.id AND ps.is_validated=1) done_units,(SELECT planned_start_date FROM project_plans pp WHERE pp.projet_id=p.id LIMIT 1) planned_start_date,(SELECT planned_end_date FROM project_plans pp WHERE pp.projet_id=p.id LIMIT 1) planned_end_date,(SELECT COALESCE(MAX(progress_value),0) FROM project_progress pr WHERE pr.projet_id=p.id) latest_progress FROM projet p JOIN projets_employes pe ON pe.projet_id=p.id JOIN users c ON c.matricule=p.id_client WHERE pe.employe_id=$matricule ORDER BY p.created_at DESC"),MYSQLI_ASSOC);
$stats=mysqli_fetch_assoc(mysqli_query($conn,"SELECT COALESCE(SUM(state='a_faire'),0) todo_count,COALESCE(SUM(state='en_cours'),0) doing_count,COALESCE(SUM(state='terminee'),0) done_count FROM (SELECT ptt.id,CASE WHEN COALESCE(SUM(ps.is_validated=1),0)=0 THEN 'a_faire' WHEN COALESCE(SUM(ps.is_validated=1),0)<COUNT(ps.id) THEN 'en_cours' ELSE 'terminee' END AS state FROM project_task_titles ptt LEFT JOIN project_subtasks ps ON ps.task_title_id=ptt.id WHERE ptt.employe_id=$matricule GROUP BY ptt.id) title_states"))??['todo_count'=>0,'doing_count'=>0,'done_count'=>0];

$totalPlannedUnits=0;$totalDoneUnits=0;
foreach($projects as $project){$totalPlannedUnits+=max(0,(int)($project['planned_units']??0));$totalDoneUnits+=max(0,(int)($project['done_units']??0));}
$completion=$totalPlannedUnits>0?(int)min(100,round(($totalDoneUnits/$totalPlannedUnits)*100)):0;

$monthStart=date('Y-m-01');$todayDate=date('Y-m-d');$daysElapsed=(int)date('j');
$presentDaysRow=mysqli_fetch_assoc(mysqli_query($conn,"SELECT COUNT(*) AS c FROM employe_presence WHERE employe_id=$matricule AND presence_date BETWEEN '$monthStart' AND '$todayDate'"))??['c'=>0];
$presentDays=(int)($presentDaysRow['c']??0);$absentDays=max(0,$daysElapsed-$presentDays);
$presenceRows=mysqli_fetch_all(mysqli_query($conn,"SELECT presence_date,first_seen_at,last_seen_at FROM employe_presence WHERE employe_id=$matricule ORDER BY presence_date DESC LIMIT 10"),MYSQLI_ASSOC);

$projectPieSeries=[];
foreach($projects as $project){
    $planned=(int)($project['planned_units']??0);$done=(int)($project['done_units']??0);
    $projectPieSeries[]=['id'=>(int)$project['id'],'label'=>'#'.(int)$project['id'].' '.(string)$project['type_projet'],'done'=>$done,'remaining'=>max(0,$planned-$done),'deadline'=>$project['planned_end_date']??null];
}

$subtaskRows=mysqli_fetch_all(mysqli_query($conn,"SELECT ps.id,ps.projet_id,ps.task_title_id,ps.label,ps.is_done,ps.done_at,ps.is_validated,ps.validated_at,ptt.title,p.type_projet FROM project_subtasks ps JOIN project_task_titles ptt ON ptt.id=ps.task_title_id JOIN projet p ON p.id=ps.projet_id WHERE ps.employe_id=$matricule ORDER BY ps.projet_id DESC,ptt.id DESC,ps.id DESC"),MYSQLI_ASSOC);
$dailyLogs=mysqli_fetch_all(mysqli_query($conn,"SELECT ps.validated_at,ps.label,ptt.title,p.type_projet FROM project_subtasks ps JOIN project_task_titles ptt ON ptt.id=ps.task_title_id JOIN projet p ON p.id=ps.projet_id WHERE ps.employe_id=$matricule AND ps.is_validated=1 ORDER BY ps.validated_at DESC,ps.id DESC LIMIT 20"),MYSQLI_ASSOC);

$projectDeadlineById=[];foreach($projects as $project)$projectDeadlineById[(int)$project['id']]=$project['planned_end_date']??null;
$planGrouped=[];
foreach($subtaskRows as $row){
    $projectId=(int)$row['projet_id'];$title=(string)($row['title']??'');
    if(!isset($planGrouped[$projectId]))$planGrouped[$projectId]=['project_name'=>(string)($row['type_projet']??''),'planned_end_date'=>$projectDeadlineById[$projectId]??null,'titles'=>[]];
    if(!isset($planGrouped[$projectId]['titles'][$title]))$planGrouped[$projectId]['titles'][$title]=[];
    $planGrouped[$projectId]['titles'][$title][]=$row;
}

$maxPendingId=0;foreach($pendingOrders as $o)$maxPendingId=max($maxPendingId,(int)($o['id']??0));
$maxProjectId=0;foreach($projects as $p)$maxProjectId=max($maxProjectId,(int)($p['id']??0));
if(!isset($_SESSION['employe_seen_tabs'])||!is_array($_SESSION['employe_seen_tabs']))$_SESSION['employe_seen_tabs']=['commandes'=>0,'projets'=>0];
if($tab==='commandes')$_SESSION['employe_seen_tabs']['commandes']=$maxPendingId;
if($tab==='projets')$_SESSION['employe_seen_tabs']['projets']=$maxProjectId;
$newCommandesCount=0;foreach($pendingOrders as $o){if((int)($o['id']??0)>(int)$_SESSION['employe_seen_tabs']['commandes'])$newCommandesCount++;}
$newProjetsCount=0;foreach($projects as $p){if((int)($p['id']??0)>(int)$_SESSION['employe_seen_tabs']['projets'])$newProjetsCount++;}
$menuBadges=['commandes'=>$newCommandesCount,'projets'=>$newProjetsCount];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Dashboard Employé | EltaRH</title>
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

<?php $menu=['overview'=>['Vue d\'ensemble','fa-th-large'],'commandes'=>['Commandes','fa-inbox'],'projets'=>['Projets','fa-briefcase'],'taches'=>['Tâches','fa-list-check'],'progression'=>['Progression','fa-chart-pie'],'presence'=>['Présence','fa-user-check'],'chat'=>['Chat','fa-comments'],'profil'=>['Profil','fa-user']]; ?>

<!-- Sidebar desktop -->
<aside class="fixed left-0 top-0 h-screen w-80 bg-fuchsia-700 dark:bg-gray-800 border-r border-fuchsia-800 dark:border-gray-700 shadow-xl hidden md:flex flex-col z-40">
    <div class="px-5 py-5 flex items-center border-b border-fuchsia-600 dark:border-gray-700 bg-fuchsia-800 dark:bg-gray-900">
        <img src="../image/logo EltaRH.png" alt="EltaRH" class="h-20 w-auto">
    </div>
    <nav class="flex-1 px-3 py-5 space-y-1">
        <?php foreach($menu as $k=>$m): ?>
            <a href="?tab=<?= $k ?>" <?= $k==='chat'?'data-chat-link="desktop"':'' ?>
               class="flex items-center justify-between px-4 py-3 rounded-xl transition-colors
               <?= $tab===$k?'bg-white text-fuchsia-700 font-bold shadow':'text-white hover:bg-fuchsia-600 dark:hover:bg-fuchsia-900/30' ?>">
                <span><i class="fas <?= $m[1] ?> w-5 text-center mr-2"></i><?= $m[0] ?></span>
                <?php if($k==='chat'): ?>
                    <span data-chat-badge class="hidden min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center"></span>
                <?php elseif(isset($menuBadges[$k])&&(int)$menuBadges[$k]>0): ?>
                    <span class="inline-flex min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center animate-pulse"><?= (int)$menuBadges[$k]>99?'99+':(int)$menuBadges[$k] ?></span>
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

<!-- Overlay + Sidebar mobile -->
<div id="mobileOverlay" class="fixed inset-0 bg-black/50 hidden z-30 md:hidden"></div>
<aside id="mobileSidebar" class="fixed left-0 top-0 h-screen w-[88vw] max-w-80 bg-fuchsia-700 dark:bg-gray-800 border-r border-fuchsia-800 dark:border-gray-700 shadow-xl transform -translate-x-full transition-transform z-40 md:hidden">
    <div class="px-5 py-5 border-b border-fuchsia-600 dark:border-gray-700 bg-fuchsia-800 dark:bg-gray-900 flex items-center justify-between">
        <img src="../image/logo EltaRH.png" alt="EltaRH" class="h-16 w-auto">
        <button id="closeMobileMenu" class="w-9 h-9 rounded-lg bg-white/20 text-white"><i class="fas fa-xmark"></i></button>
    </div>
    <nav class="px-3 py-5 space-y-1">
        <?php foreach($menu as $k=>$m): ?>
            <a href="?tab=<?= $k ?>" <?= $k==='chat'?'data-chat-link="mobile"':'' ?>
               class="flex items-center justify-between px-4 py-3 rounded-xl transition-colors
               <?= $tab===$k?'bg-white text-fuchsia-700 font-bold':'text-white hover:bg-fuchsia-600' ?>">
                <span><i class="fas <?= $m[1] ?> w-5 text-center mr-2"></i><?= $m[0] ?></span>
                <?php if($k==='chat'): ?>
                    <span data-chat-badge class="hidden min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center"></span>
                <?php elseif(isset($menuBadges[$k])&&(int)$menuBadges[$k]>0): ?>
                    <span class="inline-flex min-w-5 h-5 px-1 rounded-full bg-red-400 text-white text-[11px] items-center justify-center animate-pulse"><?= (int)$menuBadges[$k]>99?'99+':(int)$menuBadges[$k] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>

<main class="md:ml-80 min-h-screen px-3 sm:px-6 lg:px-8 pb-8 pt-0 space-y-6 bg-gray-50 dark:bg-gray-900">
    <header class="top-header sticky top-0 z-30 rounded-none md:rounded-b-2xl p-3 sm:p-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-center gap-3">
            <button id="openMobileMenu" class="md:hidden w-10 h-10 rounded-xl bg-fuchsia-100 dark:bg-gray-700 text-fuchsia-700 dark:text-white"><i class="fas fa-bars"></i></button>
            <a href="?tab=profil" class="block">
                <?php if($avatar!==''): ?><img src="<?= htmlspecialchars($avatar, ENT_QUOTES, 'UTF-8') ?>" class="w-12 h-12 rounded-full border-2 border-primary object-cover">
                <?php else: ?><div class="w-12 h-12 rounded-full border-2 border-primary bg-fuchsia-50 dark:bg-gray-700 flex items-center justify-center text-primary"><i class="fas fa-user"></i></div><?php endif; ?>
            </a>
            <div class="min-w-0">
                <h1 class="truncate text-lg sm:text-xl font-black">Bienvenue, <?= htmlspecialchars($user['prenom'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="text-sm text-gray-500 dark:text-gray-300"><?= htmlspecialchars(($user['prenom']??'').' '.($user['nom']??''), ENT_QUOTES, 'UTF-8') ?></p>
                <p class="truncate text-xs text-gray-400"><?= htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        </div>
        <div class="flex w-full sm:w-auto items-center justify-end gap-3">
            <p class="text-sm text-gray-400 hidden sm:block"><?= date('d/m/Y') ?></p>
            <button id="darkToggle" class="w-10 h-10 rounded-xl bg-fuchsia-50 dark:bg-gray-700 text-fuchsia-700 dark:text-white border border-fuchsia-200 dark:border-gray-600"><i class="fa-solid fa-moon"></i></button>
        </div>
    </header>

    <?php if($flash!==''): ?>
        <div class="rounded-xl px-4 py-3 border bg-fuchsia-50 text-fuchsia-800 border-fuchsia-300 dark:bg-fuchsia-900/20 dark:text-fuchsia-200 dark:border-fuchsia-800">
            <?= htmlspecialchars($flash, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <!-- Stats -->
    <section class="grid grid-cols-2 xl:grid-cols-4 gap-4">
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm"><p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">En attente</p><p class="text-3xl font-black text-primary mt-1"><?= count($pendingOrders) ?></p></article>
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm"><p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">Projets</p><p class="text-3xl font-black text-fuchsia-500 mt-1"><?= count($projects) ?></p></article>
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm"><p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">Terminées</p><p class="text-3xl font-black text-green-600 mt-1"><?= (int)$stats['done_count'] ?></p></article>
        <article class="stat-card bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm"><p class="text-xs uppercase text-gray-400 font-semibold tracking-wide">Progression</p><p class="text-3xl font-black text-gray-700 dark:text-white mt-1"><?= $completion ?>%</p></article>
    </section>

    <!-- Commandes -->
    <?php if(showTab('commandes',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-4 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Commandes en attente</h2>
            <div class="space-y-3">
                <?php if(!$pendingOrders): ?><p class="text-sm text-gray-400">Aucune commande.</p><?php endif; ?>
                <?php foreach($pendingOrders as $o): ?>
                    <div class="border border-gray-100 dark:border-gray-700 rounded-xl p-4 flex justify-between gap-3 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition">
                        <div>
                            <p class="font-semibold">#<?= (int)$o['id'] ?> — <?= htmlspecialchars($o['type_projet'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-sm text-gray-500">Client : <?= htmlspecialchars($o['client_prenom'].' '.$o['client_nom'], ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="action" value="take_order">
                            <input type="hidden" name="project_id" value="<?= (int)$o['id'] ?>">
                            <button class="px-4 py-2 rounded-xl bg-primary hover:bg-fuchsia-800 text-white text-sm font-semibold transition">Prendre</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Projets -->
    <?php if(showTab('projets',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-4 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Mes projets</h2>
            <div class="grid md:grid-cols-2 gap-4">
                <?php if(!$projects): ?><p class="text-sm text-gray-400">Aucun projet.</p><?php endif; ?>
                <?php foreach($projects as $p):
                    $planned=(int)($p['planned_units']??0);$done=(int)($p['done_units']??0);
                    $pr=$planned>0?(int)min(100,round(($done/$planned)*100)):((int)$p['total_tasks']>0?(int)round(((int)$p['done_tasks']/(int)$p['total_tasks'])*100):(int)$p['latest_progress']);
                    $start=!empty($p['planned_start_date'])?strtotime((string)$p['planned_start_date']):time();
                    $end=!empty($p['planned_end_date'])?strtotime((string)$p['planned_end_date']):$start;
                    $todayTs=strtotime(date('Y-m-d'));$totalDays=max(1,(int)floor(($end-$start)/86400)+1);
                    $elapsedDays=max(0,min((int)floor(($todayTs-$start)/86400)+1,$totalDays));
                    $expected=(int)round(($elapsedDays/$totalDays)*100);$delta=$pr-$expected;
                ?>
                    <article class="border border-gray-100 dark:border-gray-700 rounded-xl p-4 hover:border-fuchsia-200 dark:hover:border-fuchsia-800 transition">
                        <p class="font-semibold text-primary">#<?= (int)$p['id'] ?> — <?= htmlspecialchars($p['type_projet'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-sm text-gray-500">Client : <?= htmlspecialchars($p['client_prenom'].' '.$p['client_nom'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-gray-400 mt-1">Sous-tâches : <?= $done ?>/<?= max($planned,0) ?></p>
                        <p class="text-xs text-gray-400">Fin prévue : <?= !empty($p['planned_end_date'])?htmlspecialchars(date('d/m/Y',strtotime((string)$p['planned_end_date'])),ENT_QUOTES,'UTF-8'):'Non définie' ?></p>
                        <div class="mt-2"><div class="flex justify-between text-xs mb-1 text-gray-400"><span>Progression réelle</span><span class="font-semibold text-primary"><?= $pr ?>%</span></div><div class="h-2 bg-gray-100 dark:bg-gray-700 rounded-full"><div class="h-full bg-primary rounded-full" style="width:<?= $pr ?>%"></div></div></div>
                        <p class="mt-1 text-xs <?= $delta>=0?'text-green-600':'text-red-500' ?>">Attendue : <?= $expected ?>% (<?= $delta>=0?'en avance':'en retard' ?> de <?= abs($delta) ?>%)</p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Tâches -->
    <?php if(showTab('taches',$tab)): ?>
        <section class="grid xl:grid-cols-2 gap-4">
            <form method="POST" class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm space-y-3">
                <input type="hidden" name="action" value="add_task">
                <h2 class="text-lg font-bold border-b border-fuchsia-100 dark:border-gray-700 pb-2">Planifier une tâche</h2>
                <select name="project_id" required class="w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-sm">
                    <option value="">Sélectionner un projet</option>
                    <?php foreach($projects as $p): ?><option value="<?= (int)$p['id'] ?>">#<?= (int)$p['id'] ?> — <?= htmlspecialchars($p['type_projet'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                </select>
                <input type="date" name="planned_end_date" min="<?= date('Y-m-d') ?>" class="w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-sm">
                <p class="text-xs text-gray-400">Date de fin : à saisir une fois par projet.</p>
                <input type="text" name="title" required placeholder="Titre de tâche (ex : Développement API)" class="w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-sm">
                <textarea name="subtask_labels" required rows="4" placeholder="Sous-tâches (une par ligne)" class="w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-sm"></textarea>
                <button class="w-full rounded-xl bg-primary hover:bg-fuchsia-800 text-white py-2.5 font-semibold text-sm transition">Ajouter au projet</button>
            </form>
            <article class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
                <h2 class="text-lg font-bold mb-3 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Plan détaillé</h2>
                <div class="space-y-3 max-h-[360px] overflow-auto pr-1">
                    <?php if(!$planGrouped): ?><p class="text-sm text-gray-400">Aucun plan enregistré.</p><?php endif; ?>
                    <?php foreach($planGrouped as $projectId=>$plan): ?>
                        <div class="border border-gray-100 dark:border-gray-700 rounded-xl p-3">
                            <p class="font-semibold text-primary">#<?= (int)$projectId ?> — <?= htmlspecialchars($plan['project_name'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs text-gray-400 mb-2">Fin prévue : <?= !empty($plan['planned_end_date'])?htmlspecialchars(date('d/m/Y',strtotime((string)$plan['planned_end_date'])),ENT_QUOTES,'UTF-8'):'Non définie' ?></p>
                            <?php foreach($plan['titles'] as $title=>$subRows): ?>
                                <div class="mb-2 rounded-lg bg-fuchsia-50 dark:bg-gray-700/40 p-2">
                                    <p class="text-sm font-semibold"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></p>
                                    <ul class="mt-1 space-y-0.5">
                                        <?php foreach($subRows as $sr): ?><li class="text-xs text-gray-500 dark:text-gray-400">— <?= htmlspecialchars($sr['label'], ENT_QUOTES, 'UTF-8') ?></li><?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h3 class="text-lg font-bold mb-3 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Cocher les sous-tâches effectuées</h3>
            <div class="space-y-3">
                <?php if(!$planGrouped): ?><p class="text-sm text-gray-400">Aucune sous-tâche.</p><?php endif; ?>
                <?php foreach($planGrouped as $projectId=>$plan): ?>
                    <article class="border border-gray-100 dark:border-gray-700 rounded-xl p-3">
                        <p class="font-semibold">#<?= (int)$projectId ?> — <?= htmlspecialchars($plan['project_name'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-gray-400 mb-2">Fin prévue : <?= !empty($plan['planned_end_date'])?htmlspecialchars(date('d/m/Y',strtotime((string)$plan['planned_end_date'])),ENT_QUOTES,'UTF-8'):'Non définie' ?></p>
                        <?php foreach($plan['titles'] as $title=>$subRows): ?>
                            <div class="mb-2">
                                <p class="text-sm text-primary font-semibold"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></p>
                                <div class="space-y-2 mt-1">
                                    <?php foreach($subRows as $s):
                                        $doneByEmploye=(int)($s['is_done']??0)===1;$validated=(int)($s['is_validated']??0)===1;
                                        $statusLabel='En attente';
                                        if($validated){$statusDate=!empty($s['validated_at'])?(string)$s['validated_at']:(string)($s['done_at']??'');$statusLabel=$statusDate!==''?'Effectuée le '.date('d/m/Y',strtotime($statusDate)):'Effectuée';}
                                        elseif($doneByEmploye){$statusLabel='En attente validation admin';}
                                    ?>
                                        <div class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 dark:border-gray-700 p-2.5">
                                            <div>
                                                <p class="text-sm"><?= htmlspecialchars($s['label'], ENT_QUOTES, 'UTF-8') ?></p>
                                                <p class="text-xs text-gray-400"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></p>
                                            </div>
                                            <?php if(!$doneByEmploye): ?>
                                                <form method="POST"><input type="hidden" name="action" value="complete_subtask"><input type="hidden" name="subtask_id" value="<?= (int)$s['id'] ?>"><button class="px-3 py-1.5 rounded-xl bg-green-600 hover:bg-green-700 text-white text-xs font-semibold transition">Effectuer</button></form>
                                            <?php elseif(!$validated): ?>
                                                <button class="px-3 py-1.5 rounded-xl bg-amber-100 text-amber-700 text-xs font-semibold cursor-not-allowed" disabled>En attente</button>
                                            <?php else: ?>
                                                <span class="px-3 py-1.5 rounded-xl bg-green-50 text-green-700 text-xs font-semibold">✓ Effectuée</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h3 class="text-lg font-bold mb-3 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Historique des validations</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-gray-400 border-b border-gray-100 dark:border-gray-700"><th class="pb-2">Date</th><th class="pb-2">Projet</th><th class="pb-2">Titre</th><th class="pb-2">Sous-tâche</th></tr></thead>
                    <tbody>
                        <?php if(!$dailyLogs): ?><tr><td class="py-2 text-gray-400" colspan="4">Aucune validation.</td></tr><?php endif; ?>
                        <?php foreach($dailyLogs as $log): ?>
                            <tr class="border-t border-gray-50 dark:border-gray-700/50">
                                <td class="py-2.5"><?= htmlspecialchars(date('d/m/Y',strtotime((string)$log['validated_at'])),ENT_QUOTES,'UTF-8') ?></td>
                                <td class="py-2.5"><?= htmlspecialchars($log['type_projet'],ENT_QUOTES,'UTF-8') ?></td>
                                <td class="py-2.5"><?= htmlspecialchars($log['title'],ENT_QUOTES,'UTF-8') ?></td>
                                <td class="py-2.5"><?= htmlspecialchars($log['label'],ENT_QUOTES,'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- Progression -->
    <?php if(showTab('progression',$tab)): ?>
        <section class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
            <?php if(!$projectPieSeries): ?><article class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm"><p class="text-sm text-gray-400">Aucun projet.</p></article><?php endif; ?>
            <?php foreach($projectPieSeries as $pie): ?>
                <article class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
                    <h3 class="text-sm font-bold mb-1 text-primary"><?= htmlspecialchars($pie['label'],ENT_QUOTES,'UTF-8') ?></h3>
                    <p class="text-xs text-gray-400 mb-3">Fin prévue : <?= !empty($pie['deadline'])?htmlspecialchars(date('d/m/Y',strtotime((string)$pie['deadline'])),ENT_QUOTES,'UTF-8'):'Non définie' ?></p>
                    <div class="h-48"><canvas id="employeProjectPie<?= (int)$pie['id'] ?>" class="w-full h-full"></canvas></div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <!-- Présence -->
    <?php if(showTab('presence',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-3 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Ma présence</h2>
            <p class="mb-1 text-sm text-green-600 font-semibold">Présence enregistrée automatiquement (<?= date('d/m/Y') ?>)</p>
            <div class="mb-4 rounded-xl border border-fuchsia-100 dark:border-gray-700 p-3 bg-fuchsia-50 dark:bg-gray-700/30">
                <p class="text-sm font-semibold">Absences du mois (<?= date('m/Y') ?>)</p>
                <p class="text-xs text-gray-500 dark:text-gray-300">Présences : <?= $presentDays ?> / <?= $daysElapsed ?> | Absences : <?= $absentDays ?></p>
                <?php if($absentDays>5): ?><p class="mt-1 text-xs text-red-600 font-semibold">Attention : vous avez dépassé 5 jours d'absence ce mois.</p><?php endif; ?>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-gray-400 border-b border-gray-100 dark:border-gray-700"><th class="pb-2">Date</th><th class="pb-2">Première connexion</th><th class="pb-2">Dernière activité</th></tr></thead>
                    <tbody>
                        <?php foreach($presenceRows as $row): ?>
                            <tr class="border-t border-gray-50 dark:border-gray-700/50">
                                <td class="py-2.5"><?= htmlspecialchars(date('d/m/Y',strtotime($row['presence_date'])),ENT_QUOTES,'UTF-8') ?></td>
                                <td class="py-2.5 text-gray-500"><?= htmlspecialchars(date('H:i:s',strtotime($row['first_seen_at'])),ENT_QUOTES,'UTF-8') ?></td>
                                <td class="py-2.5 text-gray-500"><?= htmlspecialchars(date('H:i:s',strtotime($row['last_seen_at'])),ENT_QUOTES,'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <!-- Profil -->
    <?php if(showTab('profil',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl p-5 border border-gray-200 dark:border-gray-700 shadow-sm">
            <h2 class="text-lg font-bold mb-4 border-b border-fuchsia-100 dark:border-gray-700 pb-2">Modifier mon profil</h2>
            <form method="POST" enctype="multipart/form-data" class="grid md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="update_profile">
                <div class="md:col-span-2 flex items-center gap-4">
                    <?php if($avatar!==''): ?><img src="<?= htmlspecialchars($avatar,ENT_QUOTES,'UTF-8') ?>" class="w-16 h-16 rounded-full border-2 border-primary object-cover">
                    <?php else: ?><div class="w-16 h-16 rounded-full border-2 border-primary bg-fuchsia-50 dark:bg-gray-700 flex items-center justify-center text-primary text-xl"><i class="fas fa-user"></i></div><?php endif; ?>
                    <div class="flex-1"><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Photo de profil</label><input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-sm"></div>
                </div>
                <div><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Prénom</label><input name="prenom" required value="<?= htmlspecialchars($user['prenom']??'',ENT_QUOTES,'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700"></div>
                <div><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Nom</label><input name="nom" required value="<?= htmlspecialchars($user['nom']??'',ENT_QUOTES,'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700"></div>
                <div class="md:col-span-2"><label class="text-sm font-medium text-gray-600 dark:text-gray-300">Email</label><input type="email" name="email" required value="<?= htmlspecialchars($user['email']??'',ENT_QUOTES,'UTF-8') ?>" class="mt-1 w-full rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700"></div>
                <div class="md:col-span-2"><button class="px-6 py-2.5 rounded-xl bg-primary hover:bg-fuchsia-800 text-white font-semibold transition">Sauvegarder</button></div>
            </form>
        </section>
    <?php endif; ?>

    <!-- Chat -->
    <?php if(showTab('chat',$tab)): ?>
        <section class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 shadow-sm overflow-hidden">
            <div class="bg-primary text-white px-5 py-3 font-bold text-sm tracking-wide">Chat client/admin</div>
            <div class="grid grid-cols-1 md:grid-cols-[220px_minmax(0,1fr)] h-auto md:h-[500px]">
                <div id="chatContacts" class="max-h-56 md:max-h-none border-b md:border-b-0 md:border-r border-gray-100 dark:border-gray-700 overflow-auto bg-fuchsia-50 dark:bg-gray-900/30"></div>
                <div class="flex flex-col">
                    <div id="chatTarget" class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 text-sm font-semibold text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-900/20">Choisissez un contact</div>
                    <div id="chatMessages" class="min-h-[280px] md:min-h-0 flex-1 overflow-auto p-4 space-y-2 bg-gray-50 dark:bg-gray-900/30"></div>
                    <div class="p-3 border-t border-gray-100 dark:border-gray-700 flex flex-col sm:flex-row gap-2 bg-white dark:bg-gray-800">
                        <input id="chatInput" type="text" placeholder="Votre message..." class="flex-1 rounded-xl border border-gray-200 dark:border-gray-600 px-3 py-2 bg-white dark:bg-gray-700 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30">
                        <button id="chatSend" class="w-full sm:w-auto px-4 py-2 rounded-xl bg-primary hover:bg-fuchsia-800 text-white text-sm font-semibold transition">Envoyer</button>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<script>
const root=document.documentElement,darkToggle=document.getElementById('darkToggle');
function syncIcon(){if(!darkToggle)return;darkToggle.innerHTML=root.classList.contains('dark')?'<i class="fa-solid fa-sun"></i>':'<i class="fa-solid fa-moon"></i>';}
if(localStorage.getItem('theme')==='dark')root.classList.add('dark');
syncIcon();
if(darkToggle)darkToggle.addEventListener('click',()=>{root.classList.toggle('dark');localStorage.setItem('theme',root.classList.contains('dark')?'dark':'light');syncIcon();});

const openBtn=document.getElementById('openMobileMenu'),closeBtn=document.getElementById('closeMobileMenu'),mobileSidebar=document.getElementById('mobileSidebar'),overlay=document.getElementById('mobileOverlay');
function toggleMenu(show){if(!mobileSidebar||!overlay)return;mobileSidebar.classList.toggle('-translate-x-full',!show);overlay.classList.toggle('hidden',!show);}
if(openBtn)openBtn.addEventListener('click',()=>toggleMenu(true));
if(closeBtn)closeBtn.addEventListener('click',()=>toggleMenu(false));
if(overlay)overlay.addEventListener('click',()=>toggleMenu(false));

const employePies=<?= json_encode($projectPieSeries,JSON_UNESCAPED_UNICODE) ?>;
employePies.forEach(p=>{const c=document.getElementById(`employeProjectPie${p.id}`);if(!c)return;new Chart(c,{type:'pie',data:{labels:['Terminées','Restantes'],datasets:[{data:[p.done,p.remaining],backgroundColor:['#C026D3','#f3e8ff']}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{font:{family:'Poppins',size:11}}}}}});});
</script>
<script>
window.__CHAT_CONFIG__ = {
    apiUrl: 'chat_api.php',
    myId: '<?= htmlspecialchars((string)$matricule,ENT_QUOTES,'UTF-8') ?>',
    storageKey: 'employe_chat_seen',
    activeContactKey: 'employe_chat_contact'
};
</script>
<script src="../js/dashboard-chat.js"></script>
</body>
</html>
