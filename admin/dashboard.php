<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
// Génération d'un token CSRF si non existant pour sécuriser les actions
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ferrari Admin - Gestion des Avis</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #050505; color: #fff; font-family: 'Arial', sans-serif; margin: 0; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ff2828; padding-bottom: 15px; margin-bottom: 25px; }
        .header h1 { margin: 0; color: #ff2828; font-size: 24px; }
        .btn-back { background: #222; color: #fff; padding: 8px 15px; border-radius: 5px; text-decoration: none; border: 1px solid #444; font-size: 14px; }
        
        /* Zone de statistiques et filtres */
        .dashboard-meta { display: flex; gap: 20px; flex-wrap: wrap; margin-bottom: 25px; }
        .stat-card { background: #0f0f0f; border: 1px solid #222; border-radius: 8px; padding: 15px; min-width: 180px; }
        .stat-card h4 { margin: 0 0 10px 0; font-size: 12px; color: #888; text-transform: uppercase; }
        .stat-card .count { font-size: 22px; font-weight: bold; color: #ff2828; }

        .filter-section { background: #0f0f0f; border: 1px solid #222; padding: 15px; border-radius: 8px; margin-bottom: 25px; display: flex; align-items: center; gap: 15px; }
        .filter-section select { background: #151515; color: #fff; border: 1px solid #333; padding: 8px 12px; border-radius: 5px; cursor: pointer; }

        /* Conteneurs Modèles */
        .car-group { background: #0c0c0c; border: 1px solid #222; border-radius: 8px; margin-bottom: 25px; overflow: hidden; }
        .car-group-header { background: #111; padding: 15px; border-bottom: 1px solid #222; display: flex; justify-content: space-between; align-items: center; }
        .car-group-header h2 { margin: 0; font-size: 18px; color: #fff; }
        .car-group-header .global-badge { background: #ff2828; color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: bold; }

        /* Cartes d'avis */
        .review-list { padding: 15px; display: flex; flex-direction: column; gap: 12px; }
        .review-row { background: #151515; border: 1px solid #222; padding: 15px; border-radius: 6px; display: flex; justify-content: space-between; align-items: flex-start; }
        .review-row.flagged { border-left: 4px solid #ffaa00; background: #1a1510; }
        .review-info h4 { margin: 0 0 5px 0; font-size: 14px; color: #aaa; }
        .review-info .stars { color: #ffaa00; margin-bottom: 8px; }
        .review-info p { margin: 0; font-size: 13px; color: #ddd; font-style: italic; }
        
        .review-actions { display: flex; gap: 10px; }
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; color: #fff; }
        .btn-flag { background: #ffaa00; }
        .btn-flag:disabled { background: #443311; color: #777; cursor: not-allowed; }
        .btn-delete { background: #ff2828; }
    </style>
</head>
<body>

    <div class="header">
        <h1><i class="fa-solid fa-gauge"></i> Scurderia Ferrari — Dashboard Modération</h1>
        <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Retour au site</a>
    </div>

    <div class="filter-section">
        <label><i class="fa-solid fa-filter"></i> Filtrer par note :</label>
        <select id="star-filter">
            <option value="all">Toutes les étoiles (1 à 5)</option>
            <option value="5">⭐⭐⭐⭐⭐ (5/5)</option>
            <option value="4">⭐⭐⭐⭐ (4/5)</option>
            <option value="3">⭐⭐⭐ (3/5)</option>
            <option value="2">⭐⭐ (2/5)</option>
            <option value="1">⭐ (1/5)</option>
        </select>
    </div>

    <div id="dashboard-container">
        <p style="text-align: center; opacity: 0.5;">Chargement des données de modération...</p>
    </div>

    <input type="hidden" id="admin-csrf" value="<?= $_SESSION['csrf_token'] ?>">

    <script src="../assets/js/admin.js"></script>
</body>
</html>