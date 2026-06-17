<?php
// admin/dashboard.php

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'path' => '/',
        'secure' => false, // Passez à true si vous utilisez du HTTPS en production
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Vérification stricte : si l'utilisateur n'est pas connecté OU qu'il n'est pas admin
if (!isset($_SESSION['user']) || strtolower(trim($_SESSION['user']['role'])) !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// On inclut la connexion à la base de données
require_once '../config/database.php';

// Génération d'un jeton CSRF spécifique si absent
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- RÉCUPÉRATION DES STATISTIQUES ---
try {
    // Nombre de messages de contact reçus
    $countContact = $bdd->query("SELECT COUNT(*) FROM contact_requests")->fetchColumn();
    
    // Nombre de commentaires (avis) totaux non supprimés
    $countComments = $bdd->query("SELECT COUNT(*) FROM comments WHERE is_deleted = 0")->fetchColumn();
    
    // Nombre de véhicules enregistrés
    $countVehicules = $bdd->query("SELECT COUNT(*) FROM voiture")->fetchColumn();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Panel Admin - Scuderia Exhibition</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Style sombre et épuré type Dashboard Pro */
        body { font-family: 'Segoe UI', sans-serif; background: #0c0c0c; color: #fff; margin: 0; padding: 0; display: flex; }
        .sidebar { width: 250px; background: #141414; height: 100vh; position: fixed; border-right: 1px solid #222; padding: 20px; box-sizing: border-box; }
        .sidebar h2 { color: #ff2828; font-size: 20px; margin-bottom: 30px; text-transform: uppercase; letter-spacing: 1px; }
        .sidebar a { display: block; color: #aaa; padding: 12px 15px; text-decoration: none; border-radius: 5px; margin-bottom: 5px; font-size: 15px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: #ff2828; color: #fff; font-weight: bold; }
        
        .main-content { margin-left: 250px; padding: 40px; width: calc(100% - 250px); box-sizing: border-box; }
        .header-dash { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222; padding-bottom: 15px; margin-bottom: 30px; }
        .header-dash h1 { margin: 0; font-size: 28px; }
        .btn-back { background: #222; color: #fff; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-size: 14px; transition: 0.3s; }
        .btn-back:hover { background: #ff2828; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: #141414; padding: 25px; border-radius: 6px; border-left: 4px solid #ff2828; display: flex; align-items: center; justify-content: space-between; }
        .stat-card i { font-size: 36px; color: #333; }
        .stat-info h3 { margin: 0; font-size: 14px; color: #aaa; text-transform: uppercase; }
        .stat-info p { margin: 5px 0 0 0; font-size: 28px; font-weight: bold; }
        
        .data-section { background: #141414; padding: 25px; border-radius: 6px; border: 1px solid #222; margin-bottom: 30px; }
        .data-section h2 { margin-top: 0; font-size: 18px; color: #ffaa00; border-bottom: 1px solid #222; padding-bottom: 10px; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; margin-top: 10px; }
        th { padding: 12px; color: #aaa; border-bottom: 2px solid #222; text-transform: uppercase; font-size: 12px; }
        td { padding: 12px; border-bottom: 1px solid #1f1f1f; color: #ccc; vertical-align: top; }
        tr:hover td { background: #1a1a1a; }
        
        /* Styles spécifiques pour le rendu dynamique des avis */
        .dashboard-meta { display: flex; gap: 20px; margin-bottom: 25px; }
        .car-group { background: #1c1c1c; border-radius: 6px; padding: 20px; margin-bottom: 25px; border: 1px solid #2a2a2a; }
        .car-group-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 10px; margin-bottom: 15px; }
        .car-group-header h2 { margin: 0; font-size: 16px; color: #fff; }
        .global-badge { background: #ff2828; color: #fff; padding: 3px 8px; font-size: 11px; border-radius: 12px; font-weight: bold; }
        .review-row { background: #141414; padding: 15px; border-radius: 4px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border-left: 3px solid #ff2828; }
        .review-row.flagged { border-left-color: #ffaa00; opacity: 0.6; }
        .review-info h4 { margin: 0 0 5px 0; font-size: 14px; }
        .review-info p { margin: 5px 0 0 0; font-size: 13px; color: #aaa; font-style: italic; }
        .stars { color: #ffaa00; font-size: 12px; }
        .review-actions { display: flex; gap: 10px; }
        
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 5px; transition: 0.2s; }
        .btn-flag { background: #ffaa00; color: #000; }
        .btn-flag:hover:not(:disabled) { background: #e09500; }
        .btn-flag:disabled { background: #333; color: #666; cursor: not-allowed; }
        .btn-delete { background: #ff2828; color: #fff; }
        .btn-delete:hover { background: #cc1f1f; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2><i class="fa-solid fa-gauge"></i> Scuderia Admin</h2>
        <a href="#messages" class="active"><i class="fa-solid fa-envelope"></i> Messages reçus</a>
        <a href="#avis"><i class="fa-solid fa-comments"></i> Modération Avis</a>
        <a href="#garage"><i class="fa-solid fa-car"></i> Gestion Garage</a>
    </div>

    <div class="main-content">
        <div class="header-dash">
            <h1>Tableau de bord</h1>
            <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Retour au site</a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3>Messages Contact</h3>
                    <p><?php echo $countContact; ?></p>
                </div>
                <i class="fa-solid fa-envelope-open-text"></i>
            </div>
            <div class="stat-card" style="border-left-color: #ffaa00;">
                <div class="stat-info">
                    <h3>Avis Clients</h3>
                    <p><?php echo $countComments; ?></p>
                </div>
                <i class="fa-solid fa-star"></i>
            </div>
            <div class="stat-card" style="border-left-color: #00cc66;">
                <div class="stat-info">
                    <h3>Modèles 3D</h3>
                    <p><?php echo $countVehicules; ?></p>
                </div>
                <i class="fa-solid fa-car-side"></i>
            </div>
        </div>

        <input type="hidden" id="admin-csrf" value="<?php echo $_SESSION['csrf_token']; ?>">

        <div class="data-section" id="messages">
            <h2><i class="fa-solid fa-envelope"></i> Demandes de Contact Récentes</h2>
            <div id="contact-messages-container">
                <p style="opacity:0.5; font-style:italic;">Chargement des messages...</p>
            </div>
        </div>

        <div class="data-section" id="avis">
            <h2><i class="fa-solid fa-comments"></i> Modération des Avis Clients</h2>
            
            <div style="margin-bottom: 20px;">
                <label for="star-filter" style="font-size:14px; color:#aaa;">Filtrer par note : </label>
                <select id="star-filter" style="background:#222; color:#fff; border:1px solid #454545; padding:6px 12px; border-radius:4px; outline:none; cursor:pointer;">
                    <option value="all">Tous les avis</option>
                    <option value="5">5 Étoiles</option>
                    <option value="4">4 Étoiles</option>
                    <option value="3">3 Étoiles</option>
                    <option value="2">2 Étoiles</option>
                    <option value="1">1 Étoile</option>
                </select>
            </div>

            <div id="dashboard-container">
                <p style="opacity:0.5; font-style:italic;">Chargement des avis en cours...</p>
            </div>
        </div>

    </div>

    <script src="../assets/js/admin.js" defer></script>
</body>
</html>