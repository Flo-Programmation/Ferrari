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
    <title>Scuderia Ferrari Exhibition</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script type="importmap">
        {
            "imports": {
                "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
                "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
            }
        }
    </script>
    <style>
        /* =========================================================================
           CORRECTIONS DES CONFLITS VISUELS ET COMPORTEMENTAUX (Z-INDEX & BACKGROUNDS)
           ========================================================================= */
        
        /* Sécurité de base pour isoler les sections principales */
        .section-modeles, .section-contact, .site-footer {
            position: relative;
            z-index: 1;
        }

        /* Correctif pour le panneau latéral : arrière-plan étanche et isolation */
        .side-panel {
            background: #111111 !important; /* Force un fond opaque pour bloquer la transparence des cartes derrière */
            box-shadow: -5px 0 30px rgba(0, 0, 0, 0.8);
            z-index: 9999 !important; /* S'assure de passer au-dessus des grilles de modèles */
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .side-panel-body {
            flex: 1;
            overflow-y: auto !important; /* Permet de scroller uniquement dans le panneau sans faire bouger le fond */
            padding: 20px;
        }

        /* CSS Intégré pour la Modale d'Authentification / Profil */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            display: flex; justify-content: center; align-items: center;
            z-index: 10000; opacity: 0; pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active { opacity: 1; pointer-events: auto; }
        
        .modal-container {
            background: #141414; border: 1px solid rgba(255, 255, 255, 0.1);
            padding: 35px; border-radius: 12px; width: 90%; max-width: 550px;
            position: relative; color: #fff; box-shadow: 0 10px 40px rgba(0,0,0,0.8);
        }
        
        .close-btn {
            position: absolute; top: 15px; right: 20px; background: none;
            border: none; color: #fff; font-size: 32px; cursor: pointer; opacity: 0.6; line-height: 1;
        }
        .close-btn:hover { opacity: 1; color: #ff2828; }
        
        .auth-tabs { display: flex; margin-bottom: 25px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .auth-tabs .tab-btn {
            flex: 1; background: none; border: none; color: rgba(255,255,255,0.4);
            padding: 12px; cursor: pointer; font-weight: bold; font-size: 15px; transition: all 0.2s;
        }
        .auth-tabs .tab-btn.active { color: #fff; border-bottom: 3px solid #ff2828; }
        
        .auth-form-content { display: none; }
        .auth-form-content.active { display: block; }
        .auth-form-content h3 { margin-bottom: 20px; font-weight: 500; font-size: 20px; text-align: center; }
        
        .input-group { margin-bottom: 18px; display: flex; flex-direction: column; }
        .input-group-row { display: flex; gap: 15px; }
        .input-group-row .input-group { flex: 1; }
        .input-group label { font-size: 11px; text-transform: uppercase; margin-bottom: 6px; opacity: 0.6; letter-spacing: 0.5px; }
        .input-group input {
            padding: 12px; background: #222; border: 1px solid #333; color: #fff; border-radius: 6px; outline: none; font-size: 14px;
        }
        .input-group input:focus { border-color: #ff2828; background: #282828; }
        
        .btn-submit {
            width: 100%; padding: 14px; background: #ff2828; border: none; color: #fff;
            font-weight: bold; border-radius: 6px; cursor: pointer; text-transform: uppercase; font-size: 14px; margin-top: 10px; transition: background 0.2s;
        }
        .btn-submit:hover { background: #cc1b1b; }
        .auth-error { color: #ff4d4d; font-size: 13px; text-align: center; margin-top: 12px; font-weight: 500; }
        
        /* Profil */
        .profile-card { text-align: center; }
        .profile-large-avatar { width: 85px; height: 85px; border-radius: 50%; border: 2px solid #ff2828; object-fit: cover; margin-bottom: 15px; }
        .profile-card h2 { font-size: 22px; margin-bottom: 5px; }
        .profile-email, .profile-role { margin: 5px 0; opacity: 0.8; font-size: 14px; }
        .profile-role span { font-weight: bold; color: #ff2828; }
        .btn-logout { width: 100%; padding: 12px; background: transparent; border: 1px solid #ff4d4d; color: #ff4d4d; border-radius: 6px; cursor: pointer; font-weight: bold; margin-top: 20px; transition: all 0.2s; }
        .btn-logout:hover { background: #ff4d4d; color: #fff; }
    </style>
</head>
<body>

    <video src="assets/videos/video.mp4" autoplay loop muted playsinline id="bg-video"></video>

    <nav class="navbar">
        <div class="nav-left">
            <a href="#showroom" style="display: flex; align-items: center; gap: 15px; text-decoration: none; color: inherit;">
                <img src="https://upload.wikimedia.org/wikipedia/fr/thumb/c/c0/Scuderia_Ferrari_Logo.svg/500px-Scuderia_Ferrari_Logo.svg.png?_=20250316140006" alt="Ferrari Logo" class="brand-logo">
                <span class="brand-name">Ferrari</span>
            </a>
        </div>
        <div class="nav-right" style="display: flex; align-items: center;">
            <a href="#modeles" title="Modèles"><i class="fa-solid fa-car"></i></a>
            <a href="#contact" title="Contact"><i class="fa-solid fa-envelope"></i></a>
            
            <div id="profile-trigger" style="cursor: pointer; display: inline-flex; align-items: center; justify-content: center; width: 35px; height: 35px; border-radius: 50%; margin-left: 15px; background: rgba(255,255,255,0.1);">
                <?php if (isset($_SESSION['user'])): ?>
                    <img src="<?php echo htmlspecialchars($_SESSION['user']['avatar_url']); ?>" alt="Avatar" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
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
                
                <h3 class="telemetry-title">Télémétrie</h3>
                <div class="telemetry-box">
                    <p>Fiorano : <strong id="track-fiorano">--</strong></p>
                    <p>Monza : <strong id="track-monza">--</strong></p>
                </div>

                <h3 class="telemetry-title" style="margin-top: 25px;">Aperçu des Avis</h3>
                <div class="reviews-preview-box">
                    <div class="review-mini-card">
                        <div class="review-mini-header">
                            <strong>Alessandro M.</strong>
                            <div class="review-stars">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                            </div>
                        </div>
                        <p class="review-mini-text">Une expérience de conduite pure, le V12 atmosphérique est une œuvre d'art.</p>
                    </div>
                    <div class="review-mini-card">
                        <div class="review-mini-header">
                            <strong>Pierre G.</strong>
                            <div class="review-stars">
                                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-regular fa-star"></i>
                            </div>
                        </div>
                        <p class="review-mini-text">Un design sculptural à couper le souffle, digne héritière des barquettes de course.</p>
                    </div>
                </div>
            </div>

            <div id="tab-reviews" class="tab-pane">
                <div class="global-rating">
                    <div class="rating-left">
                        <span id="rating-grade">0.0</span>/5
                        <div id="global-stars" class="stars"></div>
                        <p id="rating-count">Basé sur 0 avis</p>
                    </div>
                    <div id="reviews-distribution" class="reviews-distribution"></div>
                </div>

                <div id="reviews-list-container" class="reviews-list"></div>

                <div class="add-review-section">
                    <h3 class="telemetry-title">Laisser un avis</h3>
                    <form id="leave-review-form" class="custom-review-form" onsubmit="event.preventDefault();">
                        <div class="form-row-split">
                            <div class="review-input-box">
                                <i class="fa-solid fa-user"></i>
                                <input type="text" placeholder="Votre nom" required autocomplete="off">
                            </div>
                            <div class="star-rating-input-container">
                                <span class="rating-label">Note :</span>
                                <div class="rating-stars-select">
                                    <input type="radio" id="star-5" name="stars-qty" value="5" checked><label for="star-5"><i class="fa-solid fa-star"></i></label>
                                    <input type="radio" id="star-4" name="stars-qty" value="4"><label for="star-4"><i class="fa-solid fa-star"></i></label>
                                    <input type="radio" id="star-3" name="stars-qty" value="3"><label for="star-3"><i class="fa-solid fa-star"></i></label>
                                    <input type="radio" id="star-2" name="stars-qty" value="2"><label for="star-2"><i class="fa-solid fa-star"></i></label>
                                    <input type="radio" id="star-1" name="stars-qty" value="1"><label for="star-1"><i class="fa-solid fa-star"></i></label>
                                </div>
                            </div>
                        </div>
                        <div class="review-input-box textarea-box">
                            <i class="fa-solid fa-pen"></i>
                            <textarea id="review-textarea" placeholder="Partagez votre retour d'expérience sur ce modèle..." rows="3" required></textarea>
                        </div>
                        <button type="submit" class="btn-submit-review">
                            Soumettre l'avis <i class="fa-solid fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="auth-modal" class="modal-overlay">
        <div class="modal-container">
            <button id="close-modal-btn" class="close-btn">&times;</button>
            
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
                        <input type="password" id="register-password" name="password" required placeholder="••••••••">
                        
                        <div id="password-strength-container" style="margin-top: 8px; display: none;">
                            <div class="strength-meter-bar" style="height: 6px; width: 100%; background: #333; border-radius: 3px; overflow: hidden; position: relative;">
                                <div id="strength-bar-fill" style="height: 100%; width: 0%; transition: all 0.3s ease;"></div>
                            </div>
                            <small id="strength-text" style="font-size: 11px; margin-top: 4px; display: block; font-weight: 500;"></small>
                        </div>
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
                        <input type="password" name="password" required placeholder="••••••••">
                    </div>
                    <button type="submit" class="btn-submit">Créer le compte</button>
                    <div class="auth-error" id="register-error"></div>
                </form>
            <?php else: ?>
                <div id="profile-info" class="profile-card">
                    <img src="<?php echo htmlspecialchars($_SESSION['user']['avatar_url']); ?>" alt="Avatar" class="profile-large-avatar">
                    <h2><?php echo htmlspecialchars($_SESSION['user']['prenom'] . ' ' . $_SESSION['user']['nom']); ?></h2>
                    <p class="profile-email"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($_SESSION['user']['email']); ?></p>
                    <p class="profile-role"><i class="fa-solid fa-shield-halved"></i> Statut : <span><?php echo htmlspecialchars($_SESSION['user']['role'] ?? 'Membre'); ?></span></p>
                    <button id="logout-btn" class="btn-logout">Déconnexion</button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <section id="modeles" class="section-modeles">
        <div class="section-title-container">
            <div class="line"></div>
            <h2>Découvrez notre gamme exclusive</h2>
            <div class="line"></div>
        </div>
        <p class="section-subtitle">L'excellence Ferrari sous toutes ses formes</p>

        <div class="modeles-grid">
            <div class="model-card">
                <div class="card-badge-type supercar">Supercar</div>
                <div class="card-img-wrapper">
                    <img src="https://images.unsplash.com/photo-1583121274602-3e2820c69888?auto=format&fit=crop&q=80&w=600" alt="Ferrari SF90">
                </div>
                <div class="card-body-content">
                    <h3>Ferrari SF90 Stradale</h3>
                    <p>L'innovation hybride au service de la performance.</p>
                    <div class="card-price-container">
                        <span>A partir de</span>
                        <div class="price">430 000 €</div>
                    </div>
                    <button class="btn-card-discover">Découvrir</button>
                </div>
            </div>

            <div class="model-card">
                <div class="card-badge-type moto">Moto</div>
                <div class="card-img-wrapper">
                    <img src="https://images.unsplash.com/photo-1568772585407-9361f9bf3a87?auto=format&fit=crop&q=80&w=600" alt="Ducati Panigale">
                </div>
                <div class="card-body-content">
                    <h3>Ducati Panigale V4 S</h3>
                    <p>Technologie de pointe, adrénaline pure.</p>
                    <div class="card-price-container">
                        <span>A partir de</span>
                        <div class="price">430 000 €</div>
                    </div>
                    <button class="btn-card-discover">Découvrir</button>
                </div>
            </div>

            <div class="model-card">
                <div class="card-badge-type suv">Suv</div>
                <div class="card-img-wrapper">
                    <img src="https://images.unsplash.com/photo-1614162692292-7ac56d7f7f1e?auto=format&fit=crop&q=80&w=600" alt="Ferrari Purosangue">
                </div>
                <div class="card-body-content">
                    <h3>Ferrari Purosangue</h3>
                    <p>Le premier SUV Ferrari. Polyvalence et prestige</p>
                    <div class="card-price-container">
                        <span>A partir de</span>
                        <div class="price">430 000 €</div>
                    </div>
                    <button class="btn-card-discover">Découvrir</button>
                </div>
            </div>
        </div>

        <div class="global-action-container">
            <button class="btn-view-all-range">Voir toute la gamme</button>
        </div>
    </section>

    <section id="contact" class="section-contact">
        <div class="contact-panel-container">
            <div class="contact-form-side">
                <span class="contact-mini-tag">Contact</span>
                <h2>Une question ?</h2>
                <p class="contact-desc-text">Notre équipe vous répond sous 24h.</p>
                
                <form action="#" method="POST" class="custom-contact-form">
                    <div class="custom-input-box">
                        <i class="fa-solid fa-user"></i>
                        <input type="text" placeholder="Nom complet" required>
                    </div>
                    <div class="custom-input-box">
                        <i class="fa-solid fa-envelope"></i>
                        <input type="email" placeholder="Adresse e-mail" required>
                    </div>
                    <div class="custom-input-box">
                        <i class="fa-solid fa-folder"></i>
                        <input type="text" placeholder="Sujet" required>
                    </div>
                    <div class="custom-input-box">
                        <i class="fa-solid fa-pen"></i>
                        <textarea placeholder="Votre message" rows="4" required></textarea>
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
                    <div class="f-badge">F</div>
                    <h3>Ferrari Future</h3>
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
            <p>&copy; 2026 Ferrari Future. Tous droits réservés.</p>
            <p>Conçu avec passion pour l'excellence automobile</p>
        </div>
    </footer>

    <script type="module" src="assets/js/main.js"></script>
</body>
</html>