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

// --- RÉCUPÉRATION DES STATISTIQUES & LISTES ---
$vehiculesList = [];
$usersList = [];
try {
    // Statistiques globales
    $countContact = $bdd->query("SELECT COUNT(*) FROM contact_requests")->fetchColumn();
    $countUnreadContact = $bdd->query("SELECT COUNT(*) FROM contact_requests WHERE is_read = 0")->fetchColumn();
    $countComments = $bdd->query("SELECT COUNT(*) FROM comments WHERE is_deleted = 0")->fetchColumn();
    $countVehicules = $bdd->query("SELECT COUNT(*) FROM voiture")->fetchColumn();
    $countUsers = $bdd->query("SELECT COUNT(*) FROM users")->fetchColumn(); // Table au pluriel

    // Récupération de la liste des véhicules
    $stmtVehicules = $bdd->query("SELECT * FROM voiture ORDER BY id DESC");
    $vehiculesList = $stmtVehicules->fetchAll(PDO::FETCH_ASSOC);

    // Récupération de la liste complète des utilisateurs
    $stmtUsers = $bdd->query("SELECT id, nom, prenom, email, telephone, avatar_url, role, created_at FROM users ORDER BY id DESC");
    $usersList = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

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
        
        .sidebar { width: 250px; background: #141414; height: 100vh; position: fixed; border-right: 1px solid #222; padding: 20px; box-sizing: border-box; z-index: 10; }
        .sidebar h2 { color: #ff2828; font-size: 20px; margin-bottom: 30px; text-transform: uppercase; letter-spacing: 1px; }
        .sidebar a { display: block; color: #aaa; padding: 12px 15px; text-decoration: none; border-radius: 5px; margin-bottom: 5px; font-size: 15px; transition: 0.3s; }
        .sidebar a:hover, .sidebar a.active { background: #ff2828; color: #fff; font-weight: bold; }
        
        .main-content { margin-left: 250px; padding: 40px; width: calc(100% - 250px); max-width: 1100px; box-sizing: border-box; }
        
        .header-dash { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #222; padding-bottom: 15px; margin-bottom: 30px; }
        .header-dash h1 { margin: 0; font-size: 28px; }
        .btn-back { background: #222; color: #fff; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-size: 14px; transition: 0.3s; }
        .btn-back:hover { background: #ff2828; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 40px; }
        .stat-card { background: #141414; padding: 20px; border-radius: 6px; border-left: 4px solid #ff2828; display: flex; align-items: center; justify-content: space-between; }
        .stat-card i { font-size: 32px; color: #333; }
        .stat-info h3 { margin: 0; font-size: 12px; color: #aaa; text-transform: uppercase; }
        .stat-info p { margin: 5px 0 0 0; font-size: 24px; font-weight: bold; }
        
        .data-section { background: #141414; padding: 25px; border-radius: 6px; border: 1px solid #222; margin-bottom: 30px; scroll-margin-top: 20px; }
        .data-section h2 { margin-top: 0; font-size: 18px; color: #ffaa00; border-bottom: 1px solid #222; padding-bottom: 10px; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; margin-top: 10px; }
        th { padding: 12px; color: #aaa; border-bottom: 2px solid #222; text-transform: uppercase; font-size: 12px; }
        td { padding: 12px; border-bottom: 1px solid #1f1f1f; color: #ccc; vertical-align: middle; }
        tr:hover td { background: #1a1a1a; }
        
        .badge-role { background: #333; color: #fff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .badge-role.admin { background: #ff2828; }
        
        .review-actions { display: flex; gap: 10px; }
        
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 5px; transition: 0.2s; text-decoration: none; }
        .btn-delete { background: #ff2828; color: #fff; }
        .btn-delete:hover:not(:disabled) { background: #cc1f1f; }
        .btn-delete:disabled { background: #222; color: #555; cursor: not-allowed; }
        .btn-edit { background: #00cc66; color: #fff; }
        .btn-edit:hover { background: #00a352; }

        .btn-add-main { background: #ff2828; color: #fff; border: none; padding: 10px 18px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; transition: 0.2s; }
        .btn-add-main:hover { background: #cc1f1f; }

        /* Fenêtres modales */
        .admin-modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.85); z-index: 9999; display: flex; justify-content: center; align-items: center; }
        .admin-modal-content { background: #141414; border: 1px solid #333; border-radius: 6px; width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; padding: 30px; box-sizing: border-box; }
        .admin-modal-content h3 { margin-top: 0; font-size: 20px; color: #ff2828; border-bottom: 1px solid #222; padding-bottom: 10px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { font-size: 13px; color: #aaa; margin-bottom: 5px; }
        .form-group input, .form-group textarea, .form-group select { background: #222; border: 1px solid #444; color: #fff; padding: 10px; border-radius: 4px; outline: none; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: #ff2828; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }

        /* Boîte de messagerie flottante */
        .chat-widget-container { position: fixed; bottom: 25px; right: 25px; z-index: 999; font-family: inherit; }
        .chat-trigger-btn { background: #ff2828; color: #fff; width: 60px; height: 60px; border-radius: 50%; display: flex; justify-content: center; align-items: center; font-size: 24px; cursor: pointer; box-shadow: 0 8px 24px rgba(255, 40, 40, 0.3); border: none; transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; }
        .chat-trigger-btn:hover { transform: scale(1.08); background: #cc1f1f; }
        .chat-notif-badge { position: absolute; top: -2px; right: -2px; background: #fff; color: #ff2828; font-size: 12px; font-weight: bold; min-width: 20px; height: 20px; border-radius: 10px; display: flex; justify-content: center; align-items: center; padding: 0 4px; box-sizing: border-box; border: 2px solid #ff2828; box-shadow: 0 2px 5px rgba(0,0,0,0.3); animation: pulseNotif 2s infinite; }
        @keyframes pulseNotif { 0% { transform: scale(1); } 50% { transform: scale(1.15); } 100% { transform: scale(1); } }
        .chat-window { position: absolute; bottom: 75px; right: 0; width: 420px; height: 550px; background: #111; border: 1px solid #262626; border-radius: 12px; box-shadow: 0 12px 36px rgba(0,0,0,0.7); display: flex; flex-direction: column; overflow: hidden; transform-origin: bottom right; transition: transform 0.3s ease, opacity 0.3s ease; opacity: 0; transform: scale(0.8); pointer-events: none; }
        .chat-widget-container.open .chat-window { opacity: 1; transform: scale(1); pointer-events: auto; }
        .chat-header { background: #161616; padding: 15px 20px; border-bottom: 1px solid #262626; display: flex; justify-content: space-between; align-items: center; }
        .chat-header h3 { margin: 0; font-size: 15px; color: #fff; display: flex; align-items: center; gap: 8px; }
        .chat-header h3 i { color: #ff2828; }
        .chat-close-btn { background: transparent; border: none; color: #888; font-size: 16px; cursor: pointer; transition: 0.2s; }
        .chat-close-btn:hover { color: #fff; }
        .chat-filters { display: flex; background: #1a1a1a; padding: 8px 15px; gap: 8px; border-bottom: 1px solid #262626; }
        .chat-filters .btn-filter-chat { flex: 1; background: #262626; border: 1px solid #333; color: #aaa; padding: 5px 0; font-size: 11px; border-radius: 4px; cursor: pointer; font-weight: bold; transition: 0.2s; }
        .chat-filters .btn-filter-chat.active { background: #ff2828; border-color: #ff2828; color: #fff; }
        .chat-body { flex: 1; padding: 15px; overflow-y: auto; background: #0c0c0c; display: flex; flex-direction: column; gap: 12px; }

        /* Styles pour les éléments générés des avis et messages */
        .msg-item, .comment-item { background: #161616; border: 1px solid #222; padding: 15px; border-radius: 6px; position: relative; }
        .msg-item.unread { border-left: 3px solid #ff2828; background: #1c1313; }
        .msg-meta { font-size: 12px; color: #888; margin-bottom: 5px; display: flex; justify-content: space-between; }
        .msg-title { font-weight: bold; color: #fff; margin-bottom: 5px; font-size: 14px; }
        .msg-text { font-size: 13px; color: #ccc; line-height: 1.4; word-break: break-word; }
        .comment-stars { color: #ffaa00; margin-bottom: 5px; }
        .comment-author { font-size: 13px; color: #ff2828; font-weight: bold; margin-bottom: 5px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2><i class="fa-solid fa-gauge"></i> Dashboard</h2>
        <a href="#garage" class="active"><i class="fa-solid fa-car"></i> Gestion Garage</a>
        <a href="#utilisateurs"><i class="fa-solid fa-users"></i> Comptes Utilisateurs</a>
        <a href="#avis"><i class="fa-solid fa-comments"></i> Modération Avis</a>
        <a href="javascript:void(0);" onclick="toggleChatWidget()"><i class="fa-solid fa-envelope"></i> Messagerie</a>
    </div>

    <div class="main-content">
        <div class="header-dash">
            <h1>Tableau de bord</h1>
            <a href="../index.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Retour au site</a>
        </div>

        <?php if (isset($error)): ?>
            <div style="background: #ff2828; color: #fff; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <strong>Erreur technique :</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card" style="border-left-color: #ff2828;">
                <div class="stat-info">
                    <h3>Messages Reçus</h3>
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
            <div class="stat-card" style="border-left-color: #0088ff;">
                <div class="stat-info">
                    <h3>Utilisateurs</h3>
                    <p><?php echo $countUsers; ?></p>
                </div>
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        <input type="hidden" id="admin-csrf" value="<?php echo $_SESSION['csrf_token']; ?>">

        <div class="data-section" id="garage">
            <h2><i class="fa-solid fa-car"></i> Gestion du Garage (Modèles 3D)</h2>
            <button class="btn-add-main" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Ajouter un modèle
            </button>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Modèle</th>
                        <th>Moteur</th>
                        <th>Puissance</th>
                        <th>Vitesse max</th>
                        <th>Année</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($vehiculesList)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; opacity: 0.5; padding: 30px;">Aucun véhicule disponible.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($vehiculesList as $v): ?>
                            <tr>
                                <td><strong>#<?php echo $v['id']; ?></strong></td>
                                <td style="color: #fff; font-weight: bold;"><?php echo htmlspecialchars($v['modele']); ?></td>
                                <td><?php echo htmlspecialchars($v['moteur']); ?></td>
                                <td><?php echo (int)$v['puissance_ch']; ?> ch</td>
                                <td><?php echo (int)$v['vitesse_max']; ?> km/h</td>
                                <td><?php echo (int)$v['annee']; ?></td>
                                <td>
                                    <div class="review-actions" style="justify-content: flex-end;">
                                        <button class="btn-action btn-edit" onclick='openEditModal(<?php echo json_encode($v, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="fa-solid fa-pen"></i> Modifier
                                        </button>
                                        <button class="btn-action btn-delete" onclick="deleteVehicle(<?php echo $v['id']; ?>)">
                                            <i class="fa-solid fa-trash"></i> Supprimer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="data-section" id="utilisateurs">
            <h2><i class="fa-solid fa-users"></i> Liste des Comptes Utilisateurs</h2>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Avatar</th>
                        <th>Nom Complet</th>
                        <th>Email</th>
                        <th>Téléphone</th>
                        <th>Rôle</th>
                        <th>Inscription</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usersList)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; opacity: 0.5; padding: 30px;">Aucun utilisateur enregistré.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usersList as $u): ?>
                            <tr>
                                <td><strong>#<?php echo $u['id']; ?></strong></td>
                                <td>
                                    <?php if (!empty($u['avatar_url'])): ?>
                                        <img src="../<?php echo htmlspecialchars($u['avatar_url']); ?>" alt="Avatar" style="width:32px; height:32px; border-radius:50%; object-fit:cover; border:1px solid #333;">
                                    <?php else: ?>
                                        <i class="fa-solid fa-circle-user" style="font-size:32px; color:#333;"></i>
                                    <?php endif; ?>
                                </td>
                                <td style="color: #fff; font-weight: bold;"><?php echo htmlspecialchars($u['prenom'] . ' ' . $u['nom']); ?></td>
                                <td><?php echo htmlspecialchars($u['email']); ?></td>
                                <td><?php echo htmlspecialchars($u['telephone'] ?: 'Non renseigné'); ?></td>
                                <td>
                                    <span class="badge-role <?php echo (strtolower(trim($u['role'])) === 'admin') ? 'admin' : ''; ?>">
                                        <?php echo htmlspecialchars($u['role']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <div class="review-actions" style="justify-content: flex-end;">
                                        <button class="btn-action btn-edit" onclick='openEditUserModal(<?php echo json_encode($u, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                            <i class="fa-solid fa-user-gear"></i> Gérer
                                        </button>
                                        <button class="btn-action btn-delete" onclick="deleteUser(<?php echo $u['id']; ?>)" <?php echo ($_SESSION['user']['id'] == $u['id']) ? 'disabled title="Vous êtes actuellement connecté sur ce compte admin"' : ''; ?>>
                                            <i class="fa-solid fa-user-minus"></i> Supprimer
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
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
            <div id="dashboard-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <p style="opacity:0.5; font-style:italic;">Chargement des avis en cours...</p>
            </div>
        </div>
    </div>

    <div class="chat-widget-container" id="chat-widget">
        <button class="chat-trigger-btn" onclick="toggleChatWidget()">
            <i class="fa-solid fa-envelope"></i>
            <?php if ($countUnreadContact > 0): ?>
                <span class="chat-notif-badge" id="chat-badge-counter"><?php echo $countUnreadContact; ?></span>
            <?php endif; ?>
        </button>
        <div class="chat-window">
            <div class="chat-header">
                <h3><i class="fa-solid fa-message"></i> Demandes de Contact</h3>
                <button class="chat-close-btn" onclick="toggleChatWidget()"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="chat-filters">
                <button class="btn-filter-chat active" id="btn-filter-all" onclick="filterMessages('all')">Tous</button>
                <button class="btn-filter-chat" id="btn-filter-unread" onclick="filterMessages('unread')">Non lus</button>
                <button class="btn-filter-chat" id="btn-filter-read" onclick="filterMessages('read')">Lus</button>
            </div>
            <div class="chat-body" id="contact-messages-container">
                <p style="opacity:0.5; font-style:italic; text-align:center; padding-top:20px;">Chargement...</p>
            </div>
        </div>
    </div>

    <div id="vehicle-modal-overlay" class="admin-modal-overlay" style="display: none;">
        <div class="admin-modal-content">
            <h3 id="modal-title">Ajouter un modèle</h3>
            <form id="vehicle-admin-form">
                <input type="hidden" name="id" id="form-vehicle-id" value="">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nom du modèle *</label>
                        <input type="text" name="modele" id="form-vehicle-modele" required>
                    </div>
                    <div class="form-group">
                        <label>Motorisation *</label>
                        <input type="text" name="moteur" id="form-vehicle-moteur" required>
                    </div>
                    <div class="form-group">
                        <label>Puissance (ch) *</label>
                        <input type="number" name="puissance_ch" id="form-vehicle-puissance" required>
                    </div>
                    <div class="form-group">
                        <label>Vitesse Max (km/h) *</label>
                        <input type="number" name="vitesse_max" id="form-vehicle-vitesse" required>
                    </div>
                    <div class="form-group">
                        <label>Année de production *</label>
                        <input type="number" name="annee" id="form-vehicle-annee" required>
                    </div>
                </div>
                <div class="form-group full-width">
                    <label>Description détaillée</label>
                    <textarea name="description" id="form-vehicle-description" rows="4"></textarea>
                </div>
                <div class="form-group full-width">
                    <label>Lien / Chemin du modèle 3D (.glb) *</label>
                    <input type="text" name="glb_url" id="form-vehicle-glb" required>
                </div>
                <div class="form-group full-width">
                    <label>Lien / Chemin d'accès du fichier Audio</label>
                    <input type="text" name="sound_url" id="form-vehicle-sound">
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-action" style="background:#444; color:#fff;" onclick="closeVehicleModal()">Annuler</button>
                    <button type="submit" class="btn-action btn-edit" id="btn-submit-vehicle">Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>

    <div id="user-modal-overlay" class="admin-modal-overlay" style="display: none;">
        <div class="admin-modal-content">
            <h3>Modifier le compte Utilisateur</h3>
            <form id="user-admin-form">
                <input type="hidden" name="id" id="form-user-id" value="">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Prénom</label>
                        <input type="text" name="prenom" id="form-user-prenom" required>
                    </div>
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" name="nom" id="form-user-nom" required>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Adresse Email (Sert d'identifiant)</label>
                        <input type="email" name="email" id="form-user-email" required>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="text" name="telephone" id="form-user-telephone">
                    </div>
                </div>
                <div class="form-group full-width">
                    <label>Rôle du compte</label>
                    <select name="role" id="form-user-role">
                        <option value="user">User (Standard)</option>
                        <option value="admin">Admin (Accès total)</option>
                    </select>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn-action" style="background:#444; color:#fff;" onclick="closeUserModal()">Annuler</button>
                    <button type="submit" class="btn-action btn-edit" id="btn-submit-user">Appliquer les modifications</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modalOverlay = document.getElementById("vehicle-modal-overlay");
        const userModalOverlay = document.getElementById("user-modal-overlay");
        const vehicleForm = document.getElementById("vehicle-admin-form");
        const userForm = document.getElementById("user-admin-form");
        const csrfTokenVal = document.getElementById("admin-csrf")?.value;

        let allMessagesCache = [];
        let allCommentsCache = [];

        // Au chargement initial
        document.addEventListener("DOMContentLoaded", () => {
            loadMessages();
            loadComments();
            
            // Écouteur pour le filtre d'avis
            document.getElementById("star-filter").addEventListener("change", (e) => {
                renderComments(e.target.value);
            });
        });

        function toggleChatWidget() {
            document.getElementById("chat-widget").classList.toggle("open");
        }

        /* --- FONCTIONS : MESSAGERIE CONTACT --- */
        async function loadMessages() {
            const container = document.getElementById("contact-messages-container");
            try {
                const res = await fetch("admin_process.php?action=getAllMessages");
                const r = await res.json();
                if (r.success) {
                    allMessagesCache = r.messages;
                    filterMessages('all');
                } else {
                    container.innerHTML = `<p style="color:#ff2828; font-size:12px;">${r.message}</p>`;
                }
            } catch(e) {
                container.innerHTML = `<p style="color:#ff2828; font-size:12px;">Erreur réseau messagerie.</p>`;
            }
        }

        function filterMessages(type) {
            // Changement classe active boutons
            document.querySelectorAll(".chat-filters .btn-filter-chat").forEach(b => b.classList.remove("active"));
            if(type === 'all') document.getElementById("btn-filter-all").classList.add("active");
            if(type === 'unread') document.getElementById("btn-filter-unread").classList.add("active");
            if(type === 'read') document.getElementById("btn-filter-read").classList.add("active");

            const container = document.getElementById("contact-messages-container");
            container.innerHTML = "";

            let filtered = allMessagesCache;
            if (type === 'unread') filtered = allMessagesCache.filter(m => parseInt(m.is_read) === 0);
            if (type === 'read') filtered = allMessagesCache.filter(m => parseInt(m.is_read) === 1);

            if (filtered.length === 0) {
                container.innerHTML = `<p style="opacity:0.4; text-align:center; font-style:italic; padding-top:20px;">Aucun message.</p>`;
                return;
            }

            filtered.forEach(m => {
                const isUnread = parseInt(m.is_read) === 0;
                const div = document.createElement("div");
                div.className = `msg-item ${isUnread ? 'unread' : ''}`;
                
                let btnRead = isUnread ? `<button class="btn-action btn-edit" style="padding:3px 8px; font-size:11px; margin-top:8px;" onclick="markAsRead(${m.id})"><i class="fa-solid fa-check"></i> Marquer lu</button>` : '';

                div.innerHTML = `
                    <div class="msg-meta">
                        <span><strong>${escapeHtml(m.nom)}</strong> (${escapeHtml(m.email)})</span>
                        <span>${m.created_at}</span>
                    </div>
                    <div class="msg-title">Sujet : ${escapeHtml(m.sujet || 'Non renseigné')}</div>
                    ${m.telephone ? `<div style="font-size:11px; color:#aaa; margin-bottom:4px;"><i class="fa-solid fa-phone"></i> ${escapeHtml(m.telephone)}</div>` : ''}
                    <div class="msg-text">${escapeHtml(m.message)}</div>
                    ${btnRead}
                `;
                container.appendChild(div);
            });
        }

        async function markAsRead(id) {
            const formData = new FormData();
            formData.append("action", "markMessageAsRead");
            formData.append("message_id", id);
            formData.append("csrf_token", csrfTokenVal);

            try {
                const res = await fetch("admin_process.php", { method: "POST", body: formData });
                const r = await res.json();
                if (r.success) {
                    // Mettre à jour le cache local
                    allMessagesCache = allMessagesCache.map(m => m.id == id ? {...m, is_read: 1} : m);
                    // Décrémenter ou recalculer le badge
                    const unreadCount = allMessagesCache.filter(m => parseInt(m.is_read) === 0).length;
                    const badge = document.getElementById("chat-badge-counter");
                    if(unreadCount > 0) {
                        if(badge) badge.innerText = unreadCount;
                    } else if(badge) {
                        badge.remove();
                    }
                    filterMessages(document.querySelector(".chat-filters .btn-filter-chat.active").id.replace("btn-filter-", ""));
                } else { alert(r.message); }
            } catch(e) { alert("Erreur réseau action."); }
        }


        /* --- FONCTIONS : MODÉRATION DES AVIS --- */
        async function loadComments() {
            const container = document.getElementById("dashboard-container");
            try {
                const res = await fetch("admin_process.php?action=getAllComments");
                const r = await res.json();
                if (r.success) {
                    allCommentsCache = r.comments;
                    renderComments("all");
                } else {
                    container.innerHTML = `<p style="color:#ff2828;">${r.message}</p>`;
                }
            } catch(e) {
                container.innerHTML = `<p style="color:#ff2828;">Erreur réseau lors du chargement des avis.</p>`;
            }
        }

        function renderComments(ratingFilter) {
            const container = document.getElementById("dashboard-container");
            container.innerHTML = "";

            let filtered = allCommentsCache;
            if (ratingFilter !== "all") {
                filtered = allCommentsCache.filter(c => parseInt(c.rating) === parseInt(ratingFilter));
            }

            if (filtered.length === 0) {
                container.innerHTML = `<p style="opacity:0.5; font-style:italic; grid-column: span 2;">Aucun avis ne correspond à ce critère.</p>`;
                return;
            }

            filtered.forEach(c => {
                const isDeleted = parseInt(c.is_deleted) === 1;
                const div = document.createElement("div");
                div.className = "comment-item";
                if(isDeleted) div.style.opacity = "0.45";

                let stars = "";
                for(let i=1; i<=5; i++) {
                    stars += i <= parseInt(c.rating) ? `<i class="fa-solid fa-star"></i>` : `<i class="fa-regular fa-star"></i>`;
                }

                div.innerHTML = `
                    <div class="comment-stars">${stars}</div>
                    <div class="comment-author">${escapeHtml(c.prenom)} ${escapeHtml(c.nom)} <span style="color:#666; font-weight:normal;">(Index Véhicule: #${c.vehicle_index})</span></div>
                    <p class="msg-text" style="margin:8px 0 15px 0;">"${escapeHtml(c.comment)}"</p>
                    <div class="review-actions">
                        ${!isDeleted ? `<button class="btn-action" style="background:#ffaa00; color:#000;" onclick="flagComment(${c.id})"><i class="fa-solid fa-eye-slash"></i> Masquer</button>` : '<span style="font-size:12px; color:#ff2828; display:flex; align-items:center;"><i class="fa-solid fa-ban"></i> Masqué du Showroom</span>'}
                        <button class="btn-action btn-delete" onclick="deleteComment(${c.id})"><i class="fa-solid fa-trash"></i> Supprimer</button>
                    </div>
                `;
                container.appendChild(div);
            });
        }

        async function flagComment(id) {
            if(!confirm("Masquer cet avis du showroom public ?")) return;
            const formData = new FormData();
            formData.append("action", "flagComment");
            formData.append("comment_id", id);
            formData.append("csrf_token", csrfTokenVal);
            try {
                const res = await fetch("admin_process.php", { method: "POST", body: formData });
                const r = await res.json();
                if(r.success) {
                    allCommentsCache = allCommentsCache.map(c => c.id == id ? {...c, is_deleted: 1} : c);
                    renderComments(document.getElementById("star-filter").value);
                } else { alert(r.message); }
            } catch(e) { alert("Erreur."); }
        }

        async function deleteComment(id) {
            if(!confirm("Supprimer définitivement cet avis de la base de données ?")) return;
            const formData = new FormData();
            formData.append("action", "deleteComment");
            formData.append("comment_id", id);
            formData.append("csrf_token", csrfTokenVal);
            try {
                const res = await fetch("admin_process.php", { method: "POST", body: formData });
                const r = await res.json();
                if(r.success) {
                    allCommentsCache = allCommentsCache.filter(c => c.id != id);
                    renderComments(document.getElementById("star-filter").value);
                } else { alert(r.message); }
            } catch(e) { alert("Erreur."); }
        }


        /* --- FONCTIONS : SÉCURITÉ & MODALES GÉNÉRIQUES --- */
        function openAddModal() {
            document.getElementById("modal-title").innerText = "Ajouter un nouveau modèle";
            vehicleForm.reset();
            document.getElementById("form-vehicle-id").value = "";
            modalOverlay.style.display = "flex";
        }
        function openEditModal(data) {
            document.getElementById("modal-title").innerText = "Modifier le modèle : " + data.modele;
            document.getElementById("form-vehicle-id").value = data.id;
            document.getElementById("form-vehicle-modele").value = data.modele;
            document.getElementById("form-vehicle-moteur").value = data.moteur;
            document.getElementById("form-vehicle-puissance").value = data.puissance_ch;
            document.getElementById("form-vehicle-vitesse").value = data.vitesse_max;
            document.getElementById("form-vehicle-annee").value = data.annee;
            document.getElementById("form-vehicle-description").value = data.description || "";
            document.getElementById("form-vehicle-glb").value = data.glb_url;
            document.getElementById("form-vehicle-sound").value = data.sound_url || "";
            modalOverlay.style.display = "flex";
        }
        function closeVehicleModal() { modalOverlay.style.display = "none"; }

        function openEditUserModal(data) {
            document.getElementById("form-user-id").value = data.id;
            document.getElementById("form-user-prenom").value = data.prenom;
            document.getElementById("form-user-nom").value = data.nom;
            document.getElementById("form-user-email").value = data.email;
            document.getElementById("form-user-telephone").value = data.telephone || "";
            document.getElementById("form-user-role").value = data.role.toLowerCase().trim();
            userModalOverlay.style.display = "flex";
        }
        function closeUserModal() { userModalOverlay.style.display = "none"; }

        // Formulaires soumission POST standard
        vehicleForm?.addEventListener("submit", async function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById("btn-submit-vehicle");
            const idVal = document.getElementById("form-vehicle-id").value;
            const formData = new FormData(this);
            formData.append("action", idVal ? "editVehicle" : "addVehicle");
            formData.append("csrf_token", csrfTokenVal);

            try {
                submitBtn.disabled = true;
                const res = await fetch("admin_process.php", { method: "POST", body: formData });
                const r = await res.json();
                if (r.success) { window.location.reload(); } else { alert(r.message); }
            } catch(e) { alert("Erreur réseau."); }
            finally { submitBtn.disabled = false; }
        });

        userForm?.addEventListener("submit", async function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById("btn-submit-user");
            const formData = new FormData(this);
            formData.append("action", "editUser");
            formData.append("csrf_token", csrfTokenVal);

            try {
                submitBtn.disabled = true;
                const res = await fetch("admin_process.php", { method: "POST", body: formData });
                const r = await res.json();
                if (r.success) {
                    alert("Compte utilisateur mis à jour !");
                    window.location.reload();
                } else { alert(r.message); }
            } catch(e) { alert("Erreur réseau."); }
            finally { submitBtn.disabled = false; }
        });

        async function deleteVehicle(id) {
            if (!confirm("Supprimer ce modèle 3D du Showroom ?")) return;
            const formData = new FormData();
            formData.append("action", "deleteVehicle");
            formData.append("id", id);
            formData.append("csrf_token", csrfTokenVal);
            try {
                const res = await fetch("admin_process.php", { method: "POST", body: formData });
                const r = await res.json();
                if (r.success) { window.location.reload(); }
            } catch(e) { alert("Erreur."); }
        }

        async function deleteUser(id) {
            if (!confirm("ATTENTION : Supprimer définitivement ce compte ?")) return;
            const formData = new FormData();
            formData.append("action", "deleteUser");
            formData.append("user_id", id);
            formData.append("csrf_token", csrfTokenVal);
            try {
                const res = await fetch("admin_process.php", { method: "POST", body: formData });
                const r = await res.json();
                if (r.success) { window.location.reload(); } else { alert(r.message); }
            } catch(e) { alert("Erreur."); }
        }

        // Utilitaire anti-XSS
        function escapeHtml(text) {
            if(!text) return "";
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    </script>
</body>
</html>