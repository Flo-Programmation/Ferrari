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

// --- RÉCUPÉRATION DES STATISTIQUES & LISTE DES VÉHICULES ---
$vehiculesList = [];
try {
    // Nombre de messages de contact reçus
    $countContact = $bdd->query("SELECT COUNT(*) FROM contact_requests")->fetchColumn();
    
    // Nombre de commentaires (avis) totaux non supprimés
    $countComments = $bdd->query("SELECT COUNT(*) FROM comments WHERE is_deleted = 0")->fetchColumn();
    
    // CORRECTION : Table renommée en 'vehicules' pour correspondre à ta structure
    $countVehicules = $bdd->query("SELECT COUNT(*) FROM voiture")->fetchColumn();

    // Récupération de la liste des véhicules pour le tableau de gestion
    $stmtVehicules = $bdd->query("SELECT * FROM voiture ORDER BY id DESC");
    $vehiculesList = $stmtVehicules->fetchAll(PDO::FETCH_ASSOC);
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
        
        .data-section { background: #141414; padding: 25px; border-radius: 6px; border: 1px solid #222; margin-bottom: 30px; scroll-margin-top: 20px; }
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
        
        .btn-action { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 5px; transition: 0.2s; text-decoration: none; }
        .btn-flag { background: #ffaa00; color: #000; }
        .btn-flag:hover:not(:disabled) { background: #e09500; }
        .btn-flag:disabled { background: #333; color: #666; cursor: not-allowed; }
        .btn-delete { background: #ff2828; color: #fff; }
        .btn-delete:hover { background: #cc1f1f; }
        .btn-edit { background: #00cc66; color: #fff; }
        .btn-edit:hover { background: #00a352; }

        .btn-add-main { background: #ff2828; color: #fff; border: none; padding: 10px 18px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 14px; display: inline-flex; align-items: center; gap: 8px; margin-bottom: 20px; transition: 0.2s; }
        .btn-add-main:hover { background: #cc1f1f; }

        /* Style des fenêtres modales de gestion */
        .admin-modal-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(0,0,0,0.85); z-index: 9999; display: flex; justify-content: center; align-items: center; }
        .admin-modal-content { background: #141414; border: 1px solid #333; border-radius: 6px; width: 600px; max-width: 90%; max-height: 90vh; overflow-y: auto; padding: 30px; box-sizing: border-box; }
        .admin-modal-content h3 { margin-top: 0; font-size: 20px; color: #ff2828; border-bottom: 1px solid #222; padding-bottom: 10px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 15px; }
        .form-group.full-width { grid-column: span 2; }
        .form-group label { font-size: 13px; color: #aaa; margin-bottom: 5px; }
        .form-group input, .form-group textarea { background: #222; border: 1px solid #444; color: #fff; padding: 10px; border-radius: 4px; outline: none; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { border-color: #ff2828; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2><i class="fa-solid fa-gauge"></i> Scuderia Admin</h2>
        <a href="#messages"><i class="fa-solid fa-envelope"></i> Messages reçus</a>
        <a href="#avis"><i class="fa-solid fa-comments"></i> Modération Avis</a>
        <a href="#garage" class="active"><i class="fa-solid fa-car"></i> Gestion Garage</a>
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

        <div class="data-section" id="garage">
            <h2><i class="fa-solid fa-car"></i> Gestion du Garage (Modèles 3D)</h2>
            
            <button class="btn-add-main" onclick="openAddModal()">
                <i class="fa-solid fa-plus"></i> Ajouter un nouveau modèle
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
                            <td colspan="7" style="text-align: center; opacity: 0.5; padding: 30px;">Aucun véhicule disponible dans la base de données.</td>
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

    <div id="vehicle-modal-overlay" class="admin-modal-overlay" style="display: none;">
        <div class="admin-modal-content">
            <h3 id="modal-title">Ajouter un modèle</h3>
            <form id="vehicle-admin-form">
                <input type="hidden" name="id" id="form-vehicle-id" value="">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nom du modèle *</label>
                        <input type="text" name="modele" id="form-vehicle-modele" required placeholder="Ex: Ferrari 458 Italia">
                    </div>
                    <div class="form-group">
                        <label>Motorisation *</label>
                        <input type="text" name="moteur" id="form-vehicle-moteur" required placeholder="Ex: V8 4.5L">
                    </div>
                    <div class="form-group">
                        <label>Puissance (ch) *</label>
                        <input type="number" name="puissance_ch" id="form-vehicle-puissance" required placeholder="570">
                    </div>
                    <div class="form-group">
                        <label>Vitesse Max (km/h) *</label>
                        <input type="number" name="vitesse_max" id="form-vehicle-vitesse" required placeholder="325">
                    </div>
                    <div class="form-group">
                        <label>Année de production *</label>
                        <input type="number" name="annee" id="form-vehicle-annee" required placeholder="2012">
                    </div>
                </div>

                <div class="form-group full-width">
                    <label>Description détaillée</label>
                    <textarea name="description" id="form-vehicle-description" rows="4" placeholder="Histoire ou caractéristiques du modèle..."></textarea>
                </div>

                <div class="form-group full-width">
                    <label>Lien / Chemin d'accès du modèle 3D (.glb) *</label>
                    <input type="text" name="glb_url" id="form-vehicle-glb" required placeholder="assets/models/ferrari/458.glb">
                </div>

                <div class="form-group full-width">
                    <label>Lien / Chemin d'accès du fichier Audio (.wav / .mp3)</label>
                    <input type="text" name="sound_url" id="form-vehicle-sound" placeholder="assets/sounds/458engine.wav">
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn-action" style="background:#444; color:#fff;" onclick="closeVehicleModal()">Annuler</button>
                    <button type="submit" class="btn-action btn-edit" id="btn-submit-vehicle">Sauvegarder</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/admin.js" defer></script>

    <script>
        const modalOverlay = document.getElementById("vehicle-modal-overlay");
        const vehicleForm = document.getElementById("vehicle-admin-form");
        const csrfTokenVal = document.getElementById("admin-csrf")?.value;

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

        function closeVehicleModal() {
            modalOverlay.style.display = "none";
        }

        // Action d'envoi du formulaire (Ajout / Modification)
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
                
                if (r.success) {
                    alert(r.message || "Opération réussie !");
                    window.location.reload();
                } else {
                    alert(r.message || "Une erreur est survenue.");
                }
            } catch(e) {
                alert("Erreur réseau ou serveur inaccessible.");
            } finally {
                submitBtn.disabled = false;
            }
        });

        // Action de suppression directe
        async function deleteVehicle(id) {
            if (!confirm("Attention ! Supprimer ce modèle retirera sa visualisation 3D du Showroom. Confirmer ?")) return;
            
            const formData = new FormData();
            formData.append("action", "deleteVehicle");
            formData.append("id", id);
            formData.append("csrf_token", csrfTokenVal);

            try {
                const res = await fetch("admin_process.php", { method: "POST", body: formData });
                const r = await res.json();
                if (r.success) {
                    alert(r.message || "Modèle supprimé.");
                    window.location.reload();
                } else {
                    alert(r.message);
                }
            } catch(e) {
                alert("Impossible d'exécuter la suppression.");
            }
        }
    </script>
</body>
</html>