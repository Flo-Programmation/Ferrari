<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scuderia Ferrari Vitrine</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
                "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
            }
        }
    </script>
</head>
<body>

    <nav class="navbar">
        <div class="nav-left">
            <a href="index.php" style="display: flex; align-items: center; gap: 15px; text-decoration: none; color: inherit;">
                <img src="https://upload.wikimedia.org/wikipedia/fr/thumb/c/c0/Scuderia_Ferrari_Logo.svg/500px-Scuderia_Ferrari_Logo.svg.png" style="height: 40px;" alt="Ferrari">
                <span style="font-weight: bold; letter-spacing: 1px;">FERRARI</span>
            </a>
        </div>
        
        <div class="nav-right" style="display: flex; align-items: center; gap: 20px;">
            <div id="profile-trigger" class="profile-icon-container" style="cursor: pointer;">
                <?php if (isset($_SESSION['user'])): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['user']['avatar_url']); ?>" alt="Avatar" class="user-avatar-nav">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div id="auth-modal" class="modal-overlay">
        <div class="modal-container">
            <button id="close-modal-btn" class="close-btn">&times;</button>
            
            <?php if (!isset($_SESSION['user'])): ?>
                <div id="auth-forms">
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
                                <div class="strength-meter-bar" style="height: 6px; width: 100%; background: #222; border: 1px solid #333; border-radius: 3px; overflow: hidden; position: relative;">
                                    <div id="strength-bar-fill" style="height: 100%; width: 0%; transition: all 0.3s ease; background: #ff2828;"></div>
                                </div>
                                <small id="strength-text" style="font-size: 11px; margin-top: 4px; display: block; font-weight: 500;"></small>
                            </div>
                        </div>

                        <div class="input-group re-captcha-container" style="display: flex; justify-content: center; margin: 15px 0;">
                            <div class="g-recaptcha" data-sitekey="VOTRE_CLE_PUBLIQUE_RECAPTCHA" data-theme="dark"></div>
                        </div>
                    
                        <button type="submit" class="btn-submit">Créer le compte</button>
                        <div class="auth-error" id="register-error"></div>
                    </form>
                </div>
            <?php else: ?>
                <div id="profile-info" class="profile-card">
                    <img src="<?php echo htmlspecialchars($_SESSION['user']['avatar_url']); ?>" alt="Avatar" class="profile-large-avatar">
                    <h2><?php echo htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']); ?></h2>
                    <p class="profile-email"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($_SESSION['user']['email']); ?></p>
                    <p class="profile-role"><i class="fa-solid fa-shield-halved"></i> Statut : <span><?php echo htmlspecialchars($_SESSION['user']['role']); ?></span></p>
                    
                    <hr class="profile-hr">
                    
                    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                        <a href="admin/index.php" class="btn-admin-panel"><i class="fa-solid fa-gauge-high"></i> Panel Administration</a>
                    <?php endif; ?>
                    
                    <button id="logout-btn" class="btn-logout"><i class="fa-solid fa-power-off"></i> Déconnexion</button>
                </div>
            <?php endif; ?>
        </div>
    </div>