<?php
// On inclut le fichier de configuration qui démarre la session et crée la fonction
require_once 'config/database.php';

// On s'assure d'avoir la session ouverte pour lire l'état de connexion
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

$isAuthenticated = isset($_SESSION['user']) ? 'true' : 'false';
$user_prenom = $_SESSION['user']['prenom'] ?? '';
$user_nom = $_SESSION['user']['nom'] ?? '';
$user_email = $_SESSION['user']['email'] ?? '';

// Génération dynamique et sécurisée de l'avatar unique de l'utilisateur connecté via l'API DiceBear
$user_avatar = "https://api.dicebear.com/7.x/lorelei/svg?seed=" . urlencode($user_prenom . ' ' . $user_nom);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Porsche Exhibition</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/icon-site.png">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <input type="hidden" id="global-csrf-token" value="<?php echo generate_csrf_token(); ?>">
    <script type="importmap">
    {
        "imports": {
            "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
            "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
        }
    }
    </script>
    
    <style>
        .section-modeles, .section-contact, .site-footer { position: relative; z-index: 1; }
        .side-panel { background: #111111 !important; box-shadow: -5px 0 30px rgba(0, 0, 0, 0.8); z-index: 9999 !important; display: flex; flex-direction: column; overflow: hidden; }
        .side-panel-body { flex: 1; overflow-y: auto !important; padding: 20px; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(10px); display: flex; justify-content: center; align-items: center; z-index: 10000; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }
        .modal-container { background: #141414; border: 1px solid rgba(255, 255, 255, 0.1); padding: 35px; border-radius: 12px; width: 90%; max-width: 550px; position: relative; color: #fff; box-shadow: 0 10px 40px rgba(0,0,0,0.8); }
        .close-btn { background: none; border: none; color: #fff; font-size: 32px; cursor: pointer; opacity: 0.6; line-height: 1; }
        .close-btn:hover { opacity: 1; color: #ff2828; }
        .auth-tabs { display: flex; margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .auth-tabs .tab-btn { flex: 1; background: none; border: none; color: rgba(255,255,255,0.4); padding: 12px; cursor: pointer; font-weight: bold; font-size: 15px; transition: all 0.2s; }
        .auth-tabs .tab-btn.active { color: #fff; border-bottom: 3px solid #ff2828; }
        .auth-form-content { display: none; }
        .auth-form-content.active { display: block; }
        .auth-form-content h3 { margin-bottom: 20px; font-weight: 500; font-size: 20px; text-align: center; }
        .input-group { margin-bottom: 18px; display: flex; flex-direction: column; }
        .input-group-row { display: flex; gap: 15px; }
        .input-group-row .input-group { flex: 1; }
        .input-group label { font-size: 11px; text-transform: uppercase; margin-bottom: 6px; opacity: 0.6; letter-spacing: 0.5px; }
        .input-group input { padding: 12px; background: #222; border: 1px solid #333; color: #fff; border-radius: 6px; outline: none; font-size: 14px; }
        .input-group input:focus { border-color: #ff2828; background: #282828; }
        .btn-submit { width: 100%; padding: 14px; background: #ff2828; border: none; color: #fff; font-weight: bold; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 14px; margin-top: 10px; transition: background 0.2s; }
        .btn-submit:hover { background: #cc1b1b; }
        .auth-error { color: #ff4d4d; font-size: 13px; text-align: center; margin-top: 12px; font-weight: 500; }
        
        /* Profil de l'utilisateur */
        .profile-card { text-align: center; }
        .profile-large-avatar { width: 85px; height: 85px; border-radius: 50%; border: 2px solid #ff2828; object-fit: cover; margin-bottom: 15px; background: rgba(255,255,255,0.1); }
        .profile-card h2 { font-size: 22px; margin-bottom: 5px; }
        .profile-email, .profile-role { margin: 5px 0; opacity: 0.8; font-size: 14px; }
        .profile-role span { font-weight: bold; color: #ff2828; }
        .btn-logout { width: 100%; padding: 12px; background: transparent; border: 1px solid #ff4d4d; color: #ff4d4d; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 20px; transition: all 0.2s; }
        .btn-logout:hover { background: #ff4d4d; color: #fff; }

        /* Zone d'activité et d'historique des avis dans le profil */
        .user-activity-section { margin-top: 25px; border-top: 1px solid rgba(255, 255, 255, 0.1); padding-top: 15px; text-align: left; }
        .user-activity-section h3 { font-size: 13px; color: #ff2828; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 8px; }
        .user-history-list { max-height: 200px; overflow-y: auto; background: #0c0c0c; border: 1px solid #222; border-radius: 8px; padding: 10px; display: flex; flex-direction: column; gap: 10px; }
        .user-history-item { background: #161616; padding: 10px; border-radius: 6px; border-left: 3px solid #ff2828; }
        .history-item-header { display: flex; justify-content: space-between; font-size: 12px; font-weight: bold; margin-bottom: 4px; }
        .history-car-target { color: #fff; text-transform: uppercase; }
        .history-stars { color: #ffaa00; }
        .history-comment-text { font-size: 12px; color: #ccc; margin: 4px 0 8px 0; line-height: 1.4; word-break: break-word; }
        .history-item-actions { display: flex; justify-content: space-between; align-items: center; font-size: 11px; }
        .history-date { color: #555; }
        .btn-action-delete-small { background: none; border: none; color: #ff4d4d; cursor: pointer; font-size: 11px; font-weight: 500; padding: 0; }
        .btn-action-delete-small:hover { text-decoration: underline; }

        /* Styles de structure des commentaires */
        .review-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); padding: 15px; border-radius: 8px; margin-bottom: 15px; display: flex; gap: 15px; position: relative; }
        .review-avatar { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 1px solid rgba(255,255,255,0.2); }
        .review-content { flex: 1; }
        .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .review-author { font-weight: bold; color: #fff; font-size: 14px; }
        .review-stars { color: #ffaa00; font-size: 12px; }
        .review-text { color: rgba(255,255,255,0.7); font-size: 13px; line-height: 1.4; }
        .review-date { font-size: 11px; color: rgba(255,255,255,0.3); margin-top: 5px; display: block; }
        
        /* Boutons d'actions Modifier / Supprimer sur les avis du modèle */
        .comment-actions { display: flex; gap: 12px; margin-top: 10px; justify-content: flex-end; border-top: 1px solid rgba(255,255,255,0.02); padding-top: 8px; }
        .btn-action-edit, .btn-action-delete { background: none; border: none; font-size: 11px; cursor: pointer; display: flex; align-items: center; gap: 4px; font-weight: 500; transition: color 0.2s; color: #888; }
        .btn-action-edit:hover { color: #00cc66; }
        .btn-action-delete:hover { color: #ff2828; }

        /* Formulaire d'édition dynamique intégré dans la carte d'avis */
        .edit-comment-box { background: #111; padding: 10px; border-radius: 6px; margin-top: 8px; border: 1px solid #333; }
        .edit-textarea { width: 100%; height: 60px; background: #222; color: #fff; border: 1px solid #444; border-radius: 4px; padding: 8px; font-size: 13px; resize: none; outline: none; box-sizing: border-box; }
        .edit-textarea:focus { border-color: #ff2828; }
        .edit-rating-selection { margin: 8px 0; font-size: 12px; display: flex; align-items: center; gap: 8px; }
        .edit-select { background: #222; color: #fff; border: 1px solid #444; border-radius: 4px; padding: 3px 6px; }
        .edit-box-buttons { display: flex; justify-content: flex-end; gap: 8px; }
        .edit-box-buttons button { padding: 5px 12px; font-size: 12px; border-radius: 4px; border: none; cursor: pointer; font-weight: bold; }
        .btn-save { background: #ff2828; color: #fff; }
        .btn-save:hover { background: #cc1b1b; }
        .btn-cancel { background: #333; color: #ccc; }
        .btn-cancel:hover { background: #444; }

        .auth-notice-box { text-align: center; background: rgba(255,255,255,0.02); border: 1px dashed rgba(255,255,255,0.1); padding: 25px 15px; border-radius: 8px; margin-top: 20px; }
        .auth-notice-box p { font-size: 13px; opacity: 0.6; margin-bottom: 12px; }
        .btn-trigger-login-view { background: transparent; border: 1px solid #ff2828; color: #ff2828; padding: 8px 16px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 12px; text-transform: uppercase; transition: all 0.2s; }
        .btn-trigger-login-view:hover { background: #ff2828; color: #fff; }
        
        #reviews-list-container { width: 100%; box-sizing: border-box; }
        .review-card { max-width: 100%; box-sizing: border-box; }
        .review-card p { white-space: normal; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; }
        
        .reviews-preview-box { display: flex; flex-direction: column; gap: 12px; margin-top: 15px; }
        .preview-review-card { background: rgba(255, 255, 255, 0.03); border-left: 3px solid #ff2828; border-top: 1px solid rgba(255, 255, 255, 0.05); border-right: 1px solid rgba(255, 255, 255, 0.05); border-bottom: 1px solid rgba(255, 255, 255, 0.05); padding: 12px 14px; border-radius: 0 8px 8px 0; transition: background 0.2s ease; }
        .preview-review-card:hover { background: rgba(255, 255, 255, 0.06); }
        .preview-review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .preview-review-author { font-size: 13px; font-weight: 700; color: #ffffff; text-transform: uppercase; letter-spacing: 0.5px; }
        .preview-review-stars { color: #ffaa00; font-size: 10px; }
        .preview-review-text { font-size: 12px; color: rgba(255, 255, 255, 0.7); line-height: 1.4; white-space: normal; overflow-wrap: break-word; word-wrap: break-word; word-break: break-word; }

        .distrib-row { display: flex; align-items: center; gap: 8px; font-size: 12px; margin-bottom: 5px; }
        .distrib-label { width: 22px; display: flex; align-items: center; gap: 3px; opacity: 0.7; color: #fff; }
        .distrib-bar-bg { flex: 1; height: 5px; background: rgba(255, 255, 255, 0.08); border-radius: 3px; overflow: hidden; }
        .distrib-bar-fill { height: 100%; background: #ff2828; width: 0%; transition: width 0.5s ease; }
        .distrib-percent { width: 32px; text-align: right; opacity: 0.5; font-size: 11px; color: #fff; }
    </style>
</head>
<body data-authenticated="<?php echo $isAuthenticated; ?>">
<div id="site-loader" style="
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background-color: #0b0b0b;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    transition: opacity 0.6s cubic-bezier(0.25, 1, 0.5, 1), visibility 0.6s;
">
    <div class="block-loader-container" style="
        display: flex;
        gap: 8px;
        height: 40px;
        align-items: center;
        margin-bottom: 25px;
    ">
        <div class="loader-block" style="animation-delay: 0s;"></div>
        <div class="loader-block" style="animation-delay: 0.15s;"></div>
        <div class="loader-block" style="animation-delay: 0.3s;"></div>
    </div>
    
    <p style="color: #fff; font-family: sans-serif; font-size: 12px; letter-spacing: 3px; opacity: 0.8; font-weight: 300;">CHARGEMENT DU SHOWROOM</p>
</div>

<style>
    /* Style individuel de chaque bloc */
    .loader-block {
        width: 12px;
        height: 30px;
        background-color: #ff2828; /* Ta couleur rouge d'accentuation */
        border-radius: 2px;
        animation: blockWave 1.2s ease-in-out infinite;
    }

    /* L'animation : le bloc grandit (scaleY), devient opaque, puis rétrécit et s'estompe */
    @keyframes blockWave {
        0%, 100% {
            transform: scaleY(0.4);
            opacity: 0.2;
        }
        50% {
            transform: scaleY(1.2);
            opacity: 1;
            background-color: #ff5555; /* Légère variation lumineuse au sommet de l'animation */
        }
    }

    /* Classe JavaScript pour masquer proprement le loader */
    .loader-hidden {
        opacity: 0 !important;
        visibility: hidden !important;
    }
</style>

    <video src="assets/videos/4K-Cinematic.mp4" autoplay loop muted playsinline id="bg-video"></video>

    <nav class="navbar">
        <div class="nav-left">
            <a href="#showroom" style="display: flex; align-items: center; gap: 15px; text-decoration: none; color: inherit;">
                <img src="assets/images/logo.png" alt="Ferrari Logo" class="brand-logo">
                <span class="brand-name">Porsche</span>
            </a>
        </div>
        <div class="nav-right" style="display: flex; align-items: center;">
            <a href="#modeles" title="Modèles"><i class="fa-solid fa-car"></i></a>
            <a href="#contact" title="Contact"><i class="fa-solid fa-envelope"></i></a>

            <div id="profile-trigger" style="cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 35px; height: 35px; border-radius: 50%; margin-left: 15px; background: rgba(255,255,255,0.1); overflow: hidden;">
                <?php if (isset($_SESSION['user'])): ?>
                    <img src="<?php echo htmlspecialchars($user_avatar); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <i class="fa-solid fa-user" style="color: #fff;"></i>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main id="showroom" class="showroom-container">
        <div id="webgl-canvas-container"></div>

        <div class="ui-panel top-left-panel">
            <h1 id="car-title">Monza SP3 Evo</h1>
            <h2 id="car-subtitle">L'Équilibre Absolu du V12</h2>
            <button id="car-badge" class="rounded-btn">Édition Limitée</button>
        </div>

        <button id="arrow-prev" class="nav-arrow prev"><i class="fa-solid fa-chevron-left"></i></button>
        <button id="arrow-next" class="nav-arrow next"><i class="fa-solid fa-chevron-right"></i></button>

        <div class="dots-navigation">
            <span class="dot active" data-index="0"></span>
            <span class="dot" data-index="1"></span>
            <span class="dot" data-index="2"></span>
        </div>

        <div class="ui-panel bottom-left-panel">
            <div id="car-specs" class="specs-box"></div>
        </div>

        <div class="ui-panel bottom-right-panel">
            <h3 class="panel-title">Fiche Technique</h3>
            <p id="car-desc">Inspirée des mythiques barquettes de compétition des années 1960.</p>

            <button id="play-sound-btn" class="rounded-btn sound-btn">
                <i class="fa-solid fa-volume-high"></i> Écouter le moteur
            </button>

            <button id="open-panel-btn" class="rounded-btn primary-btn">Ouvrir les Détails</button>
        </div>
    </main>

    <div id="side-panel" class="side-panel">
        <div class="side-panel-header">
            <div class="close-btn-container">
                <button id="close-panel-btn" class="close-btn">&times;</button>
            </div>
            <div class="tabs-navigation">
                <button class="tab-btn active" data-tab="tab-specs">Fiche Technique</button>
                <button class="tab-btn" data-tab="tab-reviews">Avis Clients</button>
            </div>
        </div>

        <div class="side-panel-body">
            <div id="tab-specs" class="tab-pane active">
                <div id="panel-specs-grid" class="specs-grid"></div>
                <h3 class="telemetry-title">Évaluation Globale</h3>
                
                <div class="rating-overview-wrapper" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); padding: 18px; border-radius: 8px; display: flex; gap: 20px; align-items: center; margin-bottom: 25px;">
                    <div class="rating-global-box" style="flex: 1; text-align: center; border-right: 1px solid rgba(255,255,255,0.08); padding-right: 10px;">
                        <h1 style="font-size: 46px; margin: 0; font-weight: 800; line-height: 1;"><span id="average-rating-num" style="color: #fff;">0.0</span><span style="font-size: 18px; opacity: 0.5; font-weight: normal;">/5</span></h1>
                        <div id="global-stars-stars" style="color: #ffaa00; margin: 8px 0 4px 0; font-size: 11px;"></div>
                        <p style="font-size: 11px; opacity: 0.5; margin: 0;">Basé sur <span id="total-reviews-count">0</span> avis</p>
                    </div>
                    <div class="rating-stars-distribution" id="stars-distribution-container" style="flex: 1.4; display: flex; flex-direction: column; gap: 2px;"></div>
                </div>

                <h3 class="telemetry-title">Aperçu des Avis</h3>
                <div id="reviews-side-preview" class="reviews-preview-box"></div>
            </div>

            <div id="tab-reviews" class="tab-pane" style="display: none;">
                <div id="reviews-list-container" class="reviews-list"></div>

                <div class="add-review-section" style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.08); padding-top: 25px;">
                    <h3 class="telemetry-title">Laisser un avis</h3>

                    <?php if (isset($_SESSION['user'])): ?>
                    <form id="leave-review-form" class="custom-review-form">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" id="review-vehicle-index" name="vehicle_index" value="0">

                        <div class="form-row-split">
                            <div class="star-rating-input-container">
                                <span class="rating-label">Note :</span>
                                <div class="rating-stars-select">
                                    <input type="radio" id="star-5" name="rating" value="5" checked><label for="star-5"><i class="fa-solid fa-star"></i></label>
                                    <input type="radio" id="star-4" name="rating" value="4"><label for="star-4"><i class="fa-solid fa-star"></i></label>
                                    <input type="radio" id="star-3" name="rating" value="3"><label for="star-3"><i class="fa-solid fa-star"></i></label>
                                    <input type="radio" id="star-2" name="rating" value="2"><label for="star-2"><i class="fa-solid fa-star"></i></label>
                                    <input type="radio" id="star-1" name="rating" value="1"><label for="star-1"><i class="fa-solid fa-star"></i></label>
                                </div>
                            </div>
                        </div>
                        <div class="review-input-box textarea-box">
                            <i class="fa-solid fa-pen"></i>
                            <textarea id="review-textarea" name="comment" placeholder="Partagez votre retour d'expérience sur ce modèle..." rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn-submit-review">
                            Soumettre l'avis <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="auth-notice-box">
                        <p>Vous devez être membre de la Scuderia pour publier un avis technique sur ce modèle.</p>
                        <button id="trigger-login-from-reviews" class="btn-trigger-login-view">Se connecter / S'inscrire</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="auth-modal" class="modal-overlay">
        <div class="modal-container">
            <button id="close-modal-btn" class="close-btn" style="position: absolute; top: 15px; right: 20px;">&times;</button>

            <?php if (!isset($_SESSION['user'])): ?>
                <div class="auth-tabs">
                    <button class="tab-btn active" data-tab="login-form">Connexion</button>
                    <button class="tab-btn" data-tab="register-form">Inscription</button>
                </div>

                <form id="login-form" class="auth-form-content active">
                    <h3>Connexion Pilote</h3>
                    <div class="input-group">
                        <label>Email</label>
                        <input type="email" name="email" required placeholder="nom@example.com">
                    </div>
                    <div class="input-group">
                        <label>Mot de passe</label>
                        <input type="password" name="password" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn-submit">Faire vrombir le moteur</button>
                    <div class="auth-error" id="login-error"></div>
                </form>

                <form id="register-form" class="auth-form-content">
                    <h3>Rejoindre la Scuderia</h3>
                    <div class="input-group-row">
                        <div class="input-group">
                            <label>Prénom</label>
                            <input type="text" name="prenom" required placeholder="Enzo">
                        </div>
                        <div class="input-group">
                            <label>Nom</label>
                            <input type="text" name="nom" required placeholder="Ferrari">
                        </div>
                    </div>
                    <div class="input-group">
                        <label>Email</label>
                        <input type="email" name="email" required placeholder="enzo@ferrari.com">
                    </div>
                    <div class="input-group">
                        <label>Mot de passe</label>
                        <input type="password" id="register-password" name="password" required placeholder="••••••••">
                        <div id="password-strength-container" style="margin-top: 8px; display: none;">
                            <div class="strength-meter-bar" style="height: 6px; width: 100%; background: #333; border-radius: 3px; overflow: hidden; position: relative;">
                                <div id="strength-bar-fill" style="height: 100%; width: 0%; transition: all 0.3s ease;"></div>
                            </div>
                            <small id="strength-text" style="font-size: 11px; margin-top: 4px; display: block; font-weight: 500;"></small>
                        </div>
                    </div>
                    <div class="input-group re-captcha-container" style="display: flex; justify-content: center; margin: 15px 0;">
                        <div class="g-recaptcha" data-sitekey="6LeZCSAtAAAAAKk9ITa_lNnsUz5W_R1WTum9jPag" data-theme="dark"></div>
                    </div>
                    <button type="submit" class="btn-submit">Créer le compte</button>
                    <div class="auth-error" id="register-error"></div>
                </form>
            <?php else: ?>
                <div id="profile-info" class="profile-card">
                    <img src="<?php echo htmlspecialchars($user_avatar, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar" class="profile-large-avatar">
                    <h2><?php echo htmlspecialchars($user_prenom . ' ' . $user_nom); ?></h2>
                    <p class="profile-email"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($user_email); ?></p>
                    <p class="profile-role">
                        <i class="fa-solid fa-shield-halved"></i> Statut : 
                        <span style="font-weight: bold; color: <?php echo (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') ? '#ff2828' : '#ffffff'; ?>;">
                            <?php 
                                if (isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin') {
                                    echo 'Administrateur';
                                } else {
                                    echo 'Membre';
                                }
                            ?>
                        </span>
                    </p>
                    
                    <div class="user-activity-section">
                        <h3><i class="fa-regular fa-comments"></i> Mes avis publiés</h3>
                        <div class="profile-reviews-list" id="user-reviews-container" style="max-height: 200px; overflow-y: auto; background: #0c0c0c; border: 1px solid #222; border-radius: 8px; padding: 10px;">
                            <p style="font-size:12px; font-style:italic; text-align:center; opacity:0.5; color:#fff;">Chargement de votre activité...</p>
                        </div>
                    </div>

                    <div style="margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px; text-align: left;">
                        <h4 style="color:#fff; font-size:12px; margin-bottom:8px;">Sécurité du compte</h4>
                        <form id="update-password-form" style="display:flex; flex-direction:column; gap:6px;">
                            <input type="password" name="current_password" placeholder="Mot de passe actuel" style="background:rgba(0,0,0,0.4); border:1px solid #444; color:#fff; padding:6px; font-size:11px; border-radius:4px;" required>
                            <input type="password" name="new_password" placeholder="Nouveau mot de passe" style="background:rgba(0,0,0,0.4); border:1px solid #444; color:#fff; padding:6px; font-size:11px; border-radius:4px;" required>
                            <button type="submit" style="background:#ff2828; color:#fff; border:none; padding:6px; font-size:11px; border-radius:4px; cursor:pointer; font-weight:bold;">Changer mon mot de passe</button>
                        </form>
                    </div>

                    <button id="logout-btn" class="btn-logout">Déconnexion</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <section id="engine-showroom-section" style="position: relative; width: 100%; height: 85vh; background: radial-gradient(circle at center, #1c1f22 0%, #0a0b0c 100%); overflow: hidden; border-bottom: 1px solid #1a1c1e;">
    
    <div style="position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px); background-size: 40px 40px; pointer-events: none; z-index: 1;"></div>

    <div class="engine-title-container" style="position: absolute; top: 40px; left: 50%; transform: translateX(-50%); z-index: 10; text-align: center;">
        <h2 style="color: #fff; font-family: 'Inter', sans-serif; font-weight: 700; letter-spacing: 4px; margin: 0; font-size: 22px; text-transform: uppercase;">Porsche Engineering</h2>
        <p style="color: #626971; font-size: 11px; letter-spacing: 1px; margin-top: 6px; text-transform: uppercase;">Groupe Motopropulseur Haute Performance</p>
    </div>

    <div id="engine-canvas-container" style="width: 100%; height: 100%; position: relative; z-index: 2;"></div>

    <div id="engine-hotspots-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 5;">
    
    <div class="hotspot-card hotspot-left" id="hotspot-culasse" style="position: absolute; display: none; pointer-events: auto;">
        <div class="hotspot-pointer" style="width: 8px; height: 8px; background: #fff; border-radius: 50%; box-shadow: 0 0 0 6px rgba(255,255,255,0.15);"></div>
        <svg class="hotspot-line" style="position: absolute; overflow: visible; pointer-events: none;"><line x1="0" y1="0" x2="0" y2="0" stroke="rgba(255,255,255,0.3)" stroke-width="1" stroke-dasharray="3 3"/></svg>
        <div class="hotspot-text" style="background: rgba(235, 237, 240, 0.96); border-right: 4px solid #d5001c; padding: 16px; width: 260px; border-radius: 2px; color: #1c1f22; font-size: 12px; font-family: sans-serif; box-shadow: 0 15px 35px rgba(0,0,0,0.4); backdrop-filter: blur(4px); line-height: 1.5; text-align: right;">
            <strong style="color: #1c1f22; display:block; margin-bottom:6px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; font-size: 13px;">01 / Distribution VarioCam</strong>
            Contrôle millimétrique des arbres à cames d'admission et d'échappement. Adapte l'ouverture des soupapes en continu pour optimiser le couple.
        </div>
    </div>

    <div class="hotspot-card hotspot-left" id="hotspot-bloc" style="position: absolute; display: none; pointer-events: auto;">
        <div class="hotspot-pointer" style="width: 8px; height: 8px; background: #fff; border-radius: 50%; box-shadow: 0 0 0 6px rgba(255,255,255,0.15);"></div>
        <svg class="hotspot-line" style="position: absolute; overflow: visible; pointer-events: none;"><line x1="0" y1="0" x2="0" y2="0" stroke="rgba(255,255,255,0.3)" stroke-width="1" stroke-dasharray="3 3"/></svg>
        <div class="hotspot-text" style="background: rgba(235, 237, 240, 0.96); border-right: 4px solid #d5001c; padding: 16px; width: 260px; border-radius: 2px; color: #1c1f22; font-size: 12px; font-family: sans-serif; box-shadow: 0 15px 35px rgba(0,0,0,0.4); backdrop-filter: blur(4px); line-height: 1.5; text-align: right;">
            <strong style="color: #1c1f22; display:block; margin-bottom:6px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; font-size: 13px;">03 / Bloc Cylindres Allégé</strong>
            Alliage d'aluminium et de silicium à haute résistance. Parois de cylindres traitées anti-friction pour endurer de hautes contraintes.
        </div>
    </div>


    <div class="hotspot-card hotspot-right" id="hotspot-injection" style="position: absolute; display: none; pointer-events: auto;">
        <div class="hotspot-pointer" style="width: 8px; height: 8px; background: #fff; border-radius: 50%; box-shadow: 0 0 0 6px rgba(255,255,255,0.15);"></div>
        <svg class="hotspot-line" style="position: absolute; overflow: visible; pointer-events: none;"><line x1="0" y1="0" x2="0" y2="0" stroke="rgba(255,255,255,0.3)" stroke-width="1" stroke-dasharray="3 3"/></svg>
        <div class="hotspot-text" style="background: rgba(235, 237, 240, 0.96); border-left: 4px solid #d5001c; padding: 16px; width: 260px; border-radius: 2px; color: #1c1f22; font-size: 12px; font-family: sans-serif; box-shadow: 0 15px 35px rgba(0,0,0,0.4); backdrop-filter: blur(4px); line-height: 1.5;">
            <strong style="color: #1c1f22; display:block; margin-bottom:6px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; font-size: 13px;">02 / Injection Directe DFI</strong>
            Injecteurs piézo-électriques pulvérisant le carburant à 350 bars directement au centre de la chambre pour une combustion complète.
        </div>
    </div>

    <div class="hotspot-card hotspot-right" id="hotspot-echappement" style="position: absolute; display: none; pointer-events: auto;">
        <div class="hotspot-pointer" style="width: 8px; height: 8px; background: #fff; border-radius: 50%; box-shadow: 0 0 0 6px rgba(255,255,255,0.15);"></div>
        <svg class="hotspot-line" style="position: absolute; overflow: visible; pointer-events: none;"><line x1="0" y1="0" x2="0" y2="0" stroke="rgba(255,255,255,0.3)" stroke-width="1" stroke-dasharray="3 3"/></svg>
        <div class="hotspot-text" style="background: rgba(235, 237, 240, 0.96); border-left: 4px solid #d5001c; padding: 16px; width: 260px; border-radius: 2px; color: #1c1f22; font-size: 12px; font-family: sans-serif; box-shadow: 0 15px 35px rgba(0,0,0,0.4); backdrop-filter: blur(4px); line-height: 1.5;">
            <strong style="color: #1c1f22; display:block; margin-bottom:6px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; font-size: 13px;">04 / Échappement Dynamique</strong>
            Lignes hydroformées à contre-pression optimisée qui égalisent le flux des gaz, libérant la puissance et la signature sonore fine.
        </div>
    </div>

</div>
</section>

    <section id="modeles" class="section-modeles">
    <div class="section-title-container">
        <div class="line"></div>
        <h2>Découvrez notre gamme exclusive</h2>
        <div class="line"></div>
    </div>
    <p class="section-subtitle">L'excellence Ferrari sous toutes ses formes</p>

    <div class="modeles-grid">
        <div class="model-card">
            <div class="card-badge-type supercar">Édition Limitée</div>
            <div class="card-img-wrapper">
                <img src="https://images.unsplash.com/photo-1583121274602-3e2820c69888?auto=format&fit=crop&q=80&w=600" alt="Monza SP3 Evo">
            </div>
            <div class="card-body-content">
                <h3>Monza SP3 Evo</h3>
                <p>L'Équilibre Absolu du V12 atmosphérique.</p>
                <div class="card-price-container">
                    <span>A partir de</span>
                    <div class="price">2 000 000 €</div>
                </div>
                <button class="btn-card-discover trigger-catalogue-modal" data-target-car="Monza SP3 EVO">Découvrir</button>
            </div>
        </div>

        <div class="model-card">
            <div class="card-badge-type supercar">Concept Car</div>
            <div class="card-img-wrapper">
                <img src="https://images.unsplash.com/photo-1614162692292-7ac56d7f7f1e?auto=format&fit=crop&q=80&w=600" alt="SF100 Vision">
            </div>
            <div class="card-body-content">
                <h3>SF100 Vision</h3>
                <p>Le Futur Hyper-Électrique sur circuit.</p>
                <div class="card-price-container">
                    <span>Prototype</span>
                    <div class="price">Unique</div>
                </div>
                <button class="btn-card-discover trigger-catalogue-modal" data-target-car="SF90 Stradale">Découvrir</button>
            </div>
        </div>

        <div class="model-card">
            <div class="card-badge-type supercar">Série Spéciale</div>
            <div class="card-img-wrapper">
                <img src="https://images.unsplash.com/photo-1568772585407-9361f9bf3a87?auto=format&fit=crop&q=80&w=600" alt="F42 Aperta">
            </div>
            <div class="card-body-content">
                <h3>F42 Aperta</h3>
                <p>La Pureté à Ciel Ouvert combinée au V8 hybride.</p>
                <div class="card-price-container">
                    <span>A partir de</span>
                    <div class="price">1 200 000 €</div>
                </div>
                <button class="btn-card-discover trigger-catalogue-modal" data-target-car="296 GTB">Découvrir</button>
            </div>
        </div>
    </div>

    <div class="global-action-container">
        <button class="btn-view-all-range trigger-catalogue-modal" data-target-car="all">Voir toute la gamme</button>
    </div>
</section>

    <section id="contact" class="section-contact">
    <div class="contact-panel-container">
        <div class="contact-form-side">
            <span class="contact-mini-tag">Contact</span>
            <h2>Une question ?</h2>
            <p class="contact-desc-text">Notre équipe d'ingénieurs vous répond sous 24h.</p>

            <form action="#" method="POST" class="custom-contact-form" id="contact-form">
                
                <div class="custom-input-box">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" name="nom" placeholder="Nom complet" 
                           value="<?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']) : ''; ?>" 
                           <?php echo isset($_SESSION['user']) ? 'readonly style="opacity: 0.7; cursor: not-allowed;"' : ''; ?> required>
                </div>
                
                <div class="custom-input-box">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" placeholder="Adresse e-mail" 
                           value="<?php echo isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']['email']) : ''; ?>" 
                           <?php echo isset($_SESSION['user']) ? 'readonly style="opacity: 0.7; cursor: not-allowed;"' : ''; ?> required>
                </div>

                <div class="custom-input-box">
                    <i class="fa-solid fa-phone"></i>
                    <input type="tel" name="telephone" placeholder="Numéro de téléphone (ex: 0612345678)">
                </div>
                
                <div class="custom-input-box">
                    <i class="fa-solid fa-folder"></i>
                    <input type="text" name="sujet" placeholder="Sujet" required>
                </div>
                
                <div class="custom-input-box box-textarea">
                    <i class="fa-solid fa-pen"></i>
                    <textarea name="message" placeholder="Votre message" rows="4" required></textarea>
                </div>
                
                <button type="submit" class="btn-submit-form">
                    Envoyer le message <i class="fa-solid fa-chevron-right"></i>
                </button>
            </form>
        </div>
    </div>
</section>

    <footer class="site-footer">
        <div class="footer-top-grid">
            <div class="footer-about-block">
                <div class="footer-logo-layout">
                    <div class="f-badge">P</div>
                    <h3>Porsche Future</h3>
                </div>
                <p>Repoussant les limites de la performance automobile depuis 1947. L'avenir du luxe sportif commence ici.</p>
                <div class="footer-social-medias">
                    <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#"><i class="fa-brands fa-twitter"></i></a>
                    <a href="#"><i class="fa-brands fa-youtube"></i></a>
                </div>
            </div>

            <div class="footer-links-block">
                <h4>Navigation Rapide</h4>
                <ul>
                    <li><a href="#showroom">Modèles</a></li>
                    <li><a href="#">Performances</a></li>
                    <li><a href="#">Innovation</a></li>
                    <li><a href="#">À propos</a></li>
                </ul>
            </div>

            <div class="footer-links-block">
                <h4>Informations Légales</h4>
                <ul>
                    <li><a href="#">Mentions légales</a></li>
                    <li><a href="#">Politique de confidentialité</a></li>
                    <li><a href="#">Conditions d'utilisation</a></li>
                    <li><a href="#">Cookies</a></li>
                </ul>
            </div>

            <div class="footer-links-block contact-details-block">
                <h4>Contact</h4>
                <ul>
                    <li><i class="fa-solid fa-location-dot"></i> Via Abetone Inferiore 4, 41053 Maranello, Italie</li>
                    <li><i class="fa-solid fa-phone"></i> +39 0536 949 111</li>
                    <li><i class="fa-solid fa-envelope"></i> contact@ferrarifuture.com</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom-bar">
            <p>&copy; 2026 Porsche Future. Tous droits réservés.</p>
            <p>Conçu avec passion pour l'excellence automobile</p>
        </div>
    </footer>
    
    <div class="modal-overlay" id="catalogue-modal-overlay">
    <div class="modal-container catalogue-modal-container">
        <div class="catalogue-modal-header">
            <h2><i class="fa-solid fa-car-side"></i> Notre Catalogue Scuderia</h2>
            <button class="close-btn" id="close-catalogue-modal-btn">&times;</button>
        </div>
        
        <div class="catalogue-cars-grid" id="catalogue-cars-grid">
            </div>
        </div>
    </div>

    <script src="assets/js/catalogue.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Gestion des Onglets du Side Panel (Fiche technique vs Avis)
            const tabButtons = document.querySelectorAll('#side-panel .tabs-navigation .tab-btn');
            const tabPanes = document.querySelectorAll('#side-panel .tab-pane');
            
            tabButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    tabButtons.forEach(b => b.classList.remove('active'));
                    tabPanes.forEach(p => {
                        p.classList.remove('active');
                        p.style.display = 'none';
                    });
                    
                    btn.classList.add('active');
                    const targetPane = document.getElementById(btn.getAttribute('data-tab'));
                    if (targetPane) {
                        targetPane.classList.add('active');
                        targetPane.style.display = 'block';
                    }
                });
            });

            // Gestion de l'ouverture / fermeture de la modale de Connexion / Profil
            const profileTrigger = document.getElementById('profile-trigger');
            const authModal = document.getElementById('auth-modal');
            const closeModalBtn = document.getElementById('close-modal-btn');
            const triggerFromReviews = document.getElementById('trigger-login-from-reviews');

            const openModal = () => authModal.classList.add('active');
            const closeModal = () => authModal.classList.remove('active');

            if (profileTrigger) profileTrigger.addEventListener('click', openModal);
            if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
            if (triggerFromReviews) triggerFromReviews.addEventListener('click', openModal);

            // Gestion du basculement Connexion / Inscription dans la modale
            const modalTabs = document.querySelectorAll('.modal-container .auth-tabs .tab-btn');
            const authForms = document.querySelectorAll('.modal-container .auth-form-content');

            modalTabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    modalTabs.forEach(t => t.classList.remove('active'));
                    authForms.forEach(f => f.classList.remove('active'));
                    
                    tab.classList.add('active');
                    const targetForm = document.getElementById(tab.getAttribute('data-tab'));
                    if (targetForm) targetForm.classList.add('active');
                });
            });
        });
    </script>
    <script type="module" src="assets/js/main.js"></script>
</body>
</html>