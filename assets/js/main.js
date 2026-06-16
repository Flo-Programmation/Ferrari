/**
 * Scuderia Ferrari Exhibition - Main Application Script
 * Version sécurisée avec CSRF, XSS protection, validation client et CSP
 */
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

// =========================================================================
// 1. BASE DE DONNÉES DES VÉHICULES ET CONFIGURATIONS
// =========================================================================
const carData = [
    {
        model: "assets/models/ferrari_bleu.glb",
        title: "Monza SP3 Evo",
        subtitle: "L'Équilibre Absolu du V12",
        badge: "Édition Limitée",
        specs: "<p><strong>Moteur :</strong> V12 Atmosphérique | 6.5L</p><p><strong>Puissance :</strong> 850 ch à 9 200 tr/min</p><p><strong>0-100 km/h :</strong> 2.85 secondes</p>",
        desc: "Inspirée des mythiques barquettes de compétition des années 1960. Son profil aérodynamique virtuel unique canalise le flux d'air pour s'affranchir de pare-brise.",
        sound: "assets/src/sounds/ferrariEngine.wav",
        techDetails: {
            moteur: "V12 Atmosphérique 6.5L", vitesse: "> 340 km/h", puissance: "850 ch", couple: "692 Nm",
            chrono: "2.85 s", poids: "1 480 kg", boite: "DCT 7 rapports", transmission: "Propulsion"
        },
        tracks: { fiorano: "1'16\"30", monza: "1'41\"20" }
    },
    {
        model: "assets/models/599obj.glb",
        title: "SF100 Vision",
        subtitle: "Le Futur Hyper-Électrique",
        badge: "Concept Car",
        specs: "<p><strong>Motorisation :</strong> 4 Moteurs Électriques</p><p><strong>Puissance :</strong> 1 200 ch combinés</p><p><strong>Couple maximal :</strong> Vectorisation active</p>",
        desc: "Une vitrine technologique de la Scuderia dotée d'un empattement long. Conçue exclusivement pour briser les lois de la physique sur circuit.",
        sound: "assets/src/sounds/ferrariEngine.wav",
        techDetails: {
            moteur: "4 Moteurs Électriques", vitesse: "> 350 km/h", puissance: "1 200 ch", couple: "1 400 Nm",
            chrono: "1.95 s", poids: "1 650 kg", boite: "Direct Drive", transmission: "Intégrale (4WD)"
        },
        tracks: { fiorano: "1'12\"10 (Record)", monza: "1'35\"40" }
    },
    {
        model: "assets/models/ferrari.glb",
        title: "F42 Aperta",
        subtitle: "La Pureté à Ciel Ouvert",
        badge: "Série Spéciale",
        specs: "<p><strong>Moteur :</strong> V8 Bi-turbo Hybride</p><p><strong>Puissance :</strong> 830 ch + 163 ch élec</p><p><strong>Châssis :</strong> Monocoque Carbone</p>",
        desc: "Alliant un toit amovible en fibre de carbone à la puissance brute du système hybride synchrone directement dérivé du savoir-faire F1.",
        sound: "assets/src/sounds/ferrariEngine.wav",
        techDetails: {
            moteur: "V8 Bi-turbo Hybride", vitesse: "> 340 km/h", puissance: "993 ch (Cumulée)", couple: "900 Nm",
            chrono: "2.9 s", poids: "1 485 kg", boite: "DCT 8 rapports", transmission: "Propulsion"
        },
        tracks: { fiorano: "1'17\"80", monza: "1'43\"15" }
    }
];

// =========================================================================
// 2. ÉTAT GLOBAL DE L'APPLICATION (encapsulé dans un objet pour limiter l'exposition)
// =========================================================================
const AppState = {
    currentIndex: 0,
    loadedGroups: new Map(),
    currentAudio: null,
    currentActiveGroup: null,
    csrfToken: null,
    isAuthenticated: false
};

// =========================================================================
// 3. FONCTIONS UTILITAIRES DE SÉCURITÉ
// =========================================================================

/**
 * Échappe le HTML pour prévenir les XSS.
 * Utilisé pour TOUT affichage de données venant de l'extérieur (API, utilisateur).
 */
function escapeHtml(text) {
    if (!text) return '';
    return text
        .toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/\//g, '&#x2F;');
}

/**
 * Échappe une URL pour éviter les injections dans les attributs src/href
 */
function escapeUrl(url) {
    if (!url) return '';
    // Ne garder que les protocoles autorisés
    const allowedProtocols = ['https://', 'http://', 'data:', 'blob:'];
    const sanitized = escapeHtml(url.trim());
    for (const protocol of allowedProtocols) {
        if (sanitized.toLowerCase().startsWith(protocol)) {
            return sanitized;
        }
    }
    // Si aucun protocole autorisé, retourner une URL par défaut
    return 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
}

/**
 * Valide un email côté client avant envoi
 */
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email.trim());
}

/**
 * Vérifie la force du mot de passe côté client
 */
function getPasswordStrength(password) {
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    return score;
}

/**
 * Limite la longueur d'une chaîne
 */
function truncateText(text, maxLength) {
    if (!text) return '';
    text = text.trim();
    if (text.length > maxLength) {
        return text.substring(0, maxLength);
    }
    return text;
}

/**
 * Gère les réponses API de manière sécurisée
 */
async function secureFetch(url, options = {}) {
    try {
        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error(`Erreur HTTP: ${response.status}`);
        }
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Réponse non-JSON reçue');
        }
        return await response.json();
    } catch (error) {
        console.error('Erreur réseau:', error);
        return { success: false, message: 'Erreur de connexion au serveur.' };
    }
}

// =========================================================================
// 4. GESTION DU TOKEN CSRF
// =========================================================================

/**
 * Récupère le token CSRF depuis le serveur
 */
async function fetchCsrfToken() {
    const data = await secureFetch('api/main.php?action=csrf');
    if (data.success && data.csrf_token) {
        AppState.csrfToken = data.csrf_token;
        return true;
    }
    return false;
}

/**
 * Ajoute le token CSRF à un FormData
 */
function addCsrfToken(formData) {
    if (AppState.csrfToken) {
        formData.append('csrf_token', AppState.csrfToken);
    }
    return formData;
}

// =========================================================================
// 5. GESTION AUDIO
// =========================================================================

function stopCurrentVehicleSound() {
    if (AppState.currentAudio) {
        AppState.currentAudio.pause();
        AppState.currentAudio.currentTime = 0;
        AppState.currentAudio = null;
    }
}

function playVehicleSound() {
    stopCurrentVehicleSound();
    const audio = new Audio(carData[AppState.currentIndex].sound);
    audio.volume = 0.2;
    audio.play().catch(err => {
        console.warn('Lecture audio impossible:', err);
    });
    AppState.currentAudio = audio;
}

// =========================================================================
// 6. INITIALISATION DE LA SCÈNE THREE.JS
// =========================================================================

const container = document.getElementById('webgl-canvas-container');
const scene = new THREE.Scene();

const camera = new THREE.PerspectiveCamera(
    35,
    container ? container.clientWidth / container.clientHeight : 1,
    0.1,
    100
);
camera.position.set(0, 0.3, 5.0);

const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
if (container) {
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    container.appendChild(renderer.domElement);
}

scene.add(new THREE.AmbientLight(0xffffff, 1.0));
const lightTop = new THREE.DirectionalLight(0xffffff, 2.5);
lightTop.position.set(0, 8, 2);
scene.add(lightTop);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.05;
controls.enablePan = false;
controls.minDistance = 3.5;
controls.maxDistance = 6.5;
controls.minAzimuthAngle = -Math.PI / 5;
controls.maxAzimuthAngle = Math.PI / 5;
controls.minPolarAngle = Math.PI / 2.5;
controls.maxPolarAngle = Math.PI / 1.95;

// =========================================================================
// 7. CHARGEMENT DES MODÈLES 3D
// =========================================================================

const loader = new GLTFLoader();

function loadVehicleModel(index) {
    if (AppState.loadedGroups.has(index)) {
        return Promise.resolve(AppState.loadedGroups.get(index));
    }
    return new Promise((resolve) => {
        loader.load(carData[index].model, (gltf) => {
            const model = gltf.scene;
            const group = new THREE.Group();
            group.add(model);

            const box = new THREE.Box3().setFromObject(model);
            const size = new THREE.Vector3();
            box.getSize(size);
            const maxDim = Math.max(size.x, size.y, size.z);
            model.scale.setScalar(3.3 / maxDim);

            if (carData[index].model.includes('599obj.glb')) {
                model.rotation.x = -Math.PI / 2;
            }

            const correctedBox = new THREE.Box3().setFromObject(model);
            const center = correctedBox.getCenter(new THREE.Vector3());
            model.position.set(-center.x, -center.y + 0.1, -center.z);

            AppState.loadedGroups.set(index, group);
            resolve(group);
        });
    });
}

// =========================================================================
// 8. LOGIQUE DE CHANGEMENT DE VÉHICULE (SLIDER)
// =========================================================================

async function changeVehicle(newIndex) {
    stopCurrentVehicleSound();
    AppState.currentIndex = newIndex;
    if (container) container.style.opacity = 0.1;

    const data = carData[AppState.currentIndex];
    
    const titleEl = document.getElementById('car-title');
    const subtitleEl = document.getElementById('car-subtitle');
    const badgeEl = document.getElementById('car-badge');
    const specsEl = document.getElementById('car-specs');
    const descEl = document.getElementById('car-desc');

    if (titleEl) titleEl.innerText = data.title;
    if (subtitleEl) subtitleEl.innerText = data.subtitle;
    if (badgeEl) badgeEl.innerText = data.badge;
    // specs vient du code source (pas de l'API) -> innerHTML acceptable
    if (specsEl) specsEl.innerHTML = data.specs;
    if (descEl) descEl.innerText = data.desc;
    
    updateSidePanelContent(data);
    fetchCommentsForVehicle(AppState.currentIndex);

    document.querySelectorAll('.dots-navigation .dot').forEach((dot, idx) => {
        dot.classList.toggle('active', idx === AppState.currentIndex);
    });

    const nextGroup = await loadVehicleModel(AppState.currentIndex);
    if (AppState.currentActiveGroup) scene.remove(AppState.currentActiveGroup);
    AppState.currentActiveGroup = nextGroup;
    scene.add(AppState.currentActiveGroup);
    
    controls.reset();
    camera.position.set(0, 0.3, 5.0);
    if (container) container.style.opacity = 1;
}

const arrowNext = document.getElementById('arrow-next');
const arrowPrev = document.getElementById('arrow-prev');
if (arrowNext) {
    arrowNext.addEventListener('click', () => {
        changeVehicle((AppState.currentIndex + 1) % carData.length);
    });
}
if (arrowPrev) {
    arrowPrev.addEventListener('click', () => {
        changeVehicle((AppState.currentIndex - 1 + carData.length) % carData.length);
    });
}

// =========================================================================
// 9. ENVOI ET CHARGEMENT DES AVIS (AJAX SÉCURISÉ)
// =========================================================================

function fetchUserActivity() {
    // On cible précisément l'ID de ton conteneur existant : 'user-reviews-container'
    const activityContainer = document.getElementById('user-reviews-container'); 
    
    if (!activityContainer) return;

    // Si pas connecté, on remplace le chargement par un message d'invitation
    if (!AppState.isAuthenticated) {
        activityContainer.innerHTML = '<p style="opacity:0.5; font-size:12px; font-style:italic; text-align:center; margin:0; color:#fff;">Connectez-vous pour voir votre activité.</p>';
        return;
    }

    secureFetch('api/main.php?action=getUserComments')
        .then(data => {
            if (data && data.success && data.my_comments && data.my_comments.length > 0) {
                let html = '<div class="user-activity-list" style="padding-right: 5px;">';
                
                data.my_comments.forEach(c => {
                    let stars = '';
                    for (let i = 1; i <= 5; i++) {
                        stars += i <= c.rating ? '★' : '☆';
                    }
                    
                    // Récupération du titre du véhicule depuis carData
                    const carName = carData[c.vehicle_index] ? carData[c.vehicle_index].title : `Véhicule #${c.vehicle_index}`;

                    html += `
                        <div class="user-activity-item" style="border-bottom: 1px solid #222; padding: 8px 0; font-size: 13px;">
                            <div style="display:flex; justify-content:space-between; color:#ff2828; font-weight:bold;">
                                <span>${escapeHtml(carName)}</span>
                                <span style="color:#ffaa00;">${stars}</span>
                            </div>
                            <p style="margin: 4px 0 0 0; color:#ccc; font-style:italic;">"${escapeHtml(c.comment)}"</p>
                        </div>
                    `;
                });
                
                html += '</div>';
                // On injecte le HTML directement dedans (ce qui nettoie le "Chargement de votre activité...")
                activityContainer.innerHTML = html; 
            } else {
                activityContainer.innerHTML = '<p style="opacity:0.4; font-size:12px; font-style:italic; text-align:center; margin:0; color:#fff;">Vous n\'avez publié aucun avis pour le moment.</p>';
            }
        })
        .catch(err => {
            console.error('Erreur activité:', err);
            activityContainer.innerHTML = '<p style="color:#ff2828; font-size:12px; text-align:center; margin:0;">Impossible de charger votre activité.</p>';
        });
}

function fetchCommentsForVehicle(vehicleIndex) {
    const listContainer = document.getElementById('reviews-list-container');
    const sidePreview = document.getElementById('reviews-side-preview');
    const distribContainer = document.getElementById('stars-distribution-container');
    const globalStarsContainer = document.getElementById('global-stars-stars');
    const avgNumSpan = document.getElementById('average-rating-num');
    const totalCountSpan = document.getElementById('total-reviews-count');

    // Validation côté client du paramètre
    const sanitizedIndex = Math.max(0, parseInt(vehicleIndex) || 0);

    secureFetch(`api/main.php?action=get&vehicle_index=${sanitizedIndex}`)
        .then(data => {
            let totalComments = 0;
            let averageRating = 0.0;
            let starCounts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };

            if (data && data.success && data.comments && Array.isArray(data.comments) && data.comments.length > 0) {
                totalComments = data.comments.length;
                let sum = 0;
                data.comments.forEach(c => {
                    const r = Math.min(5, Math.max(1, Math.round(parseFloat(c.rating) || 0)));
                    sum += r;
                    if (starCounts[r] !== undefined) starCounts[r]++;
                });
                averageRating = totalComments > 0 ? (sum / totalComments).toFixed(1) : '0.0';
            }

            if (avgNumSpan) avgNumSpan.textContent = totalComments > 0 ? averageRating : '0.0';
            if (totalCountSpan) totalCountSpan.textContent = totalComments;

            if (globalStarsContainer) {
                let gStarsHTML = '';
                const roundedAvg = Math.round(parseFloat(averageRating));
                for (let i = 1; i <= 5; i++) {
                    if (i <= roundedAvg) {
                        gStarsHTML += '<i class="fa-solid fa-star" style="margin-right:2px; color:#ffaa00;"></i>';
                    } else {
                        gStarsHTML += '<i class="fa-regular fa-star" style="margin-right:2px; color:#555;"></i>';
                    }
                }
                globalStarsContainer.innerHTML = gStarsHTML;
            }

            if (distribContainer) {
                let distribHTML = '';
                for (let i = 5; i >= 1; i--) {
                    const count = starCounts[i];
                    const pct = totalComments > 0 ? Math.round((count / totalComments) * 100) : 0;
                    distribHTML += `
                        <div class="distrib-row">
                            <span class="distrib-label">${i} <i class="fa-solid fa-star" style="font-size:9px; color:#ffaa00;"></i></span>
                            <div class="distrib-bar-bg">
                                <div class="distrib-bar-fill" style="width: ${pct}%;"></div>
                            </div>
                            <span class="distrib-percent">${pct}%</span>
                        </div>
                    `;
                }
                distribContainer.innerHTML = distribHTML;
            }

            if (!data || !data.success || !data.comments || data.comments.length === 0) {
                const emptyMessage = `
                    <div style="text-align:center; padding:60px 10px; opacity:0.4;">
                        <i class="fa-regular fa-comments" style="font-size:32px; margin-bottom:12px; display:block; color:#ff2828;"></i>
                        <p style="font-size:13px; margin:0; font-style:italic; color:#fff;">il n'y a pas encore d'avis sur ce modèle</p>
                    </div>`;
                if (listContainer) listContainer.innerHTML = emptyMessage;
                if (sidePreview) {
                    sidePreview.innerHTML = '<p style="opacity:0.4; font-size:12px; font-style:italic; margin:0; color:#fff;">Aucun avis pour le moment.</p>';
                }
                return;
            }

            let listHTML = '';
            let previewHTML = '';

            data.comments.forEach((comment, index) => {
                // Sécurité : échappement systématique de TOUT ce qui vient de l'API
                const commentText = escapeHtml(comment.comment || '');
                const prenom = escapeHtml(comment.prenom || 'Utilisateur');
                const nom = comment.nom ? escapeHtml(comment.nom.charAt(0) + '.') : '';
                const avatarUrl = escapeUrl(comment.avatar_url);
                const authorName = `${prenom} ${nom}`.trim();

                let starsHTML = '';
                const ratingVal = Math.min(5, Math.max(0, parseInt(comment.rating) || 0));
                for (let i = 1; i <= 5; i++) {
                    if (i <= ratingVal) {
                        starsHTML += '<i class="fa-solid fa-star" style="color:#ffaa00;"></i>';
                    } else {
                        starsHTML += '<i class="fa-regular fa-star" style="color:#555;"></i>';
                    }
                }

                listHTML += `
                    <div class="review-card">
                        <img src="${avatarUrl}" class="review-avatar" alt="Avatar de ${authorName}" loading="lazy">
                        <div class="review-content">
                            <div class="review-header">
                                <span class="review-author">${authorName}</span>
                                <span class="review-stars">${starsHTML}</span>
                            </div>
                            <p class="review-text">${commentText}</p>
                        </div>
                    </div>`;

                if (index < 2) {
                    previewHTML += `
                        <div class="preview-review-card">
                            <div class="preview-review-header">
                                <span class="preview-review-author">${authorName}</span>
                                <span class="preview-review-stars">${starsHTML}</span>
                            </div>
                            <p class="preview-review-text">${commentText}</p>
                        </div>`;
                }
            });

            if (listContainer) listContainer.innerHTML = listHTML;
            if (sidePreview) sidePreview.innerHTML = previewHTML;
        })
        .catch(err => {
            console.error('Erreur lors de la récupération des avis :', err);
            if (listContainer) {
                listContainer.innerHTML = `
                    <div style="text-align:center; padding:60px 10px; opacity:0.4;">
                        <p style="font-size:13px; margin:0; color:#ff2828;">Impossible de charger les avis.</p>
                    </div>`;
            }
        });
}

// =========================================================================
// 10. FORMULAIRE D'AJOUT D'AVIS (AVEC CSRF ET VALIDATION)
// =========================================================================

const leaveReviewForm = document.getElementById('leave-review-form');
if (leaveReviewForm) {
    leaveReviewForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Vérifier que l'utilisateur est connecté
        if (!AppState.isAuthenticated) {
            alert('Vous devez être connecté pour laisser un avis.');
            ouvrirModalConnexion();
            return;
        }

        // Vérifier que le token CSRF est disponible
        if (!AppState.csrfToken) {
            const loaded = await fetchCsrfToken();
            if (!loaded) {
                alert('Erreur de sécurité. Rechargez la page.');
                return;
            }
        }

        const checkedStarInput = leaveReviewForm.querySelector(
            'input[name="stars-qty"]:checked, input[name="rating"]:checked'
        );
        const commentTextarea = document.getElementById('review-textarea');
        if (!commentTextarea) return;

        // Validation côté client
        const commentText = commentTextarea.value.trim();
        
        if (commentText.length === 0) {
            alert('Veuillez écrire un commentaire.');
            commentTextarea.focus();
            return;
        }
        
        if (commentText.length > 2000) {
            alert('Le commentaire est trop long. Maximum 2000 caractères.');
            commentTextarea.focus();
            return;
        }

        // Sécurisation : limiter la taille du commentaire envoyé
        const safeComment = truncateText(commentText, 2000);
        const rating = checkedStarInput ? Math.min(5, Math.max(1, parseInt(checkedStarInput.value) || 5)) : 5;

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('vehicle_index', AppState.currentIndex);
        formData.append('rating', rating);
        formData.append('comment', safeComment);
        addCsrfToken(formData);

        try {
            const data = await secureFetch('api/main.php', {
                method: 'POST',
                body: formData
            });

            if (data.success) {
                commentTextarea.value = '';
                fetchCommentsForVehicle(AppState.currentIndex);
                fetchUserActivity(); // 👈 Met à jour l'activité du profil en tâche de fond
                alert(data.message || 'Votre avis a été enregistré !');
            } else {
                alert(data.message || 'Erreur lors de l\'envoi de l\'avis.');
                // Si le token CSRF est invalide, en récupérer un nouveau
                if (data.message && data.message.toLowerCase().includes('csrf')) {
                    await fetchCsrfToken();
                }
            }
        } catch (err) {
            console.error('Erreur:', err);
            alert('Impossible de joindre le serveur.');
        }
    });
}

// =========================================================================
// 11. GESTION DES MODALES, ONGLETS ET PANNEAUX LATÉRAUX
// =========================================================================

const sidePanel = document.getElementById('side-panel');
const openPanelBtn = document.getElementById('open-panel-btn') || document.querySelector('.open-panel-btn');
const closePanelBtn = document.getElementById('close-panel-btn') || document.querySelector('.close-panel-btn');
const tabBtns = document.querySelectorAll('.tabs-navigation .tab-btn');
const tabContents = document.querySelectorAll('.side-panel-body .tab-pane');

if (openPanelBtn && sidePanel) {
    openPanelBtn.addEventListener('click', () => sidePanel.classList.add('open'));
}
if (closePanelBtn && sidePanel) {
    closePanelBtn.addEventListener('click', () => sidePanel.classList.remove('open'));
}

tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        tabBtns.forEach(b => b.classList.remove('active'));
        tabContents.forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const targetPane = document.getElementById(btn.dataset.tab);
        if (targetPane) targetPane.classList.add('active');
    });
});

const authModal = document.getElementById('auth-modal');
const profileTrigger = document.getElementById('profile-trigger');
const closeModalBtn = document.getElementById('close-modal-btn');

function ouvrirModalConnexion() {
    if (authModal) authModal.classList.add('active');
}

if (profileTrigger && authModal) {
    profileTrigger.addEventListener('click', () => {
        authModal.classList.add('active');
        fetchUserActivity(); // 👈 Charge l'activité quand la modale s'ouvre
    });
}

if (closeModalBtn && authModal) {
    closeModalBtn.addEventListener('click', () => {
        authModal.classList.remove('active');
    });
}

// Fermeture de la modale en cliquant en dehors
if (authModal) {
    authModal.addEventListener('click', (e) => {
        if (e.target === authModal) {
            authModal.classList.remove('active');
        }
    });
}

// Gestion des onglets dans la modale d'auth
const authFormTabs = document.querySelectorAll('#auth-modal .auth-tabs .tab-btn');
const authFormContents = document.querySelectorAll('#auth-modal .auth-form-content');

authFormTabs.forEach(tab => {
    tab.addEventListener('click', () => {
        authFormTabs.forEach(t => t.classList.remove('active'));
        authFormContents.forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        const targetForm = document.getElementById(tab.dataset.tab);
        if (targetForm) targetForm.classList.add('active');
    });
});

// =========================================================================
// 12. ROBUSTESSE DU MOT DE PASSE (VÉRIFICATION EN TEMPS RÉEL)
// =========================================================================

const registerPasswordInput = document.getElementById('register-password');
const strengthContainer = document.getElementById('password-strength-container');
const strengthBarFill = document.getElementById('strength-bar-fill');
const strengthText = document.getElementById('strength-text');

if (registerPasswordInput) {
    registerPasswordInput.addEventListener('input', () => {
        const password = registerPasswordInput.value;
        const score = getPasswordStrength(password);

        if (password.length === 0) {
            if (strengthContainer) strengthContainer.style.display = 'none';
            if (strengthBarFill) {
                strengthBarFill.style.width = '0%';
                strengthBarFill.style.backgroundColor = 'transparent';
            }
            if (strengthText) strengthText.innerHTML = '';
        } else {
            if (strengthContainer) strengthContainer.style.display = 'block';

            if (score <= 2) {
                if (strengthBarFill) {
                    strengthBarFill.style.width = '33%';
                    strengthBarFill.style.backgroundColor = '#ff4d4d';
                }
                if (strengthText) {
                    strengthText.innerHTML = '<span style="color:#ff4d4d;">Sécurité : Faible</span>';
                }
            } else if (score <= 4) {
                if (strengthBarFill) {
                    strengthBarFill.style.width = '66%';
                    strengthBarFill.style.backgroundColor = '#ffaa00';
                }
                if (strengthText) {
                    strengthText.innerHTML = '<span style="color:#ffaa00;">Sécurité : Moyenne</span>';
                }
            } else {
                if (strengthBarFill) {
                    strengthBarFill.style.width = '100%';
                    strengthBarFill.style.backgroundColor = '#00cc66';
                }
                if (strengthText) {
                    strengthText.innerHTML = '<span style="color:#00cc66;">Sécurité : Forte</span>';
                }
            }
        }
    });
}

// =========================================================================
// 13. MISE À JOUR DU PANNEAU LATÉRAL
// =========================================================================

function updateSidePanelContent(data) {
    const specsGrid = document.getElementById('panel-specs-grid');
    
    if (specsGrid && data && data.techDetails) {
        // Les données viennent du tableau carData (source interne) -> innerHTML acceptable
        specsGrid.innerHTML = `
            <div class="spec-item">
                <div><span>Moteur</span><p>${escapeHtml(data.techDetails.moteur || '')}</p></div>
            </div>
            <div class="spec-item">
                <div><span>Vitesse max</span><p>${escapeHtml(data.techDetails.vitesse || '')}</p></div>
            </div>
            <div class="spec-item">
                <div><span>Puissance</span><p>${escapeHtml(data.techDetails.puissance || '')}</p></div>
            </div>
            <div class="spec-item">
                <div><span>0-100 km/h</span><p>${escapeHtml(data.techDetails.chrono || '')}</p></div>
            </div>
        `;
    }
}

// =========================================================================
// 14. FORMULAIRES D'AUTHENTIFICATION (AVEC CSRF ET VALIDATION)
// =========================================================================

// --- CONNEXION ---
const loginForm = document.getElementById('login-form');
if (loginForm) {
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const emailInput = loginForm.querySelector('input[name="email"]');
        const passwordInput = loginForm.querySelector('input[name="password"]');

        // Validation côté client
        const email = emailInput ? emailInput.value.trim() : '';
        const password = passwordInput ? passwordInput.value : '';

        if (!email || !password) {
            alert('Veuillez remplir tous les champs.');
            return;
        }

        if (!isValidEmail(email)) {
            alert('Format d\'email invalide.');
            return;
        }

        // Récupérer le token CSRF si nécessaire
        if (!AppState.csrfToken) {
            await fetchCsrfToken();
        }

        const formData = new FormData(loginForm);
        formData.append('action', 'login');
        addCsrfToken(formData);

        try {
            const data = await secureFetch('api/main.php', {
                method: 'POST',
                body: formData
            });

            if (data.success) {
                AppState.isAuthenticated = true;
                AppState.csrfToken = data.csrf_token;
                
                if (authModal) authModal.classList.remove('active');

                // Vérification du rôle reçu depuis la base de données
                if (data.role === 'admin') {
                    // Redirection instantanée vers le tableau de bord de l'administrateur
                    window.location.href = 'admin/dashboard.php'; 
                } else {
                    // Comportement normal pour un membre ou visiteur classique
                    alert('Connexion réussie ! Bienvenue sur l\'exposition.');
                    location.reload(); 
                }
            } else {
                alert(data.message || 'Identifiants incorrects.');
            }
        } catch (err) {
            console.error('Erreur:', err);
            alert('Erreur de connexion au serveur.');
        }
    });
}

// --- INSCRIPTION ---
const registerForm = document.getElementById('register-form');
if (registerForm) {
    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const prenomInput = registerForm.querySelector('input[name="prenom"]');
        const nomInput = registerForm.querySelector('input[name="nom"]');
        const emailInput = registerForm.querySelector('input[name="email"]');
        const passwordInput = registerForm.querySelector('input[name="password"]');

        // Validation côté client
        const prenom = prenomInput ? prenomInput.value.trim() : '';
        const nom = nomInput ? nomInput.value.trim() : '';
        const email = emailInput ? emailInput.value.trim() : '';
        const password = passwordInput ? passwordInput.value : '';

        if (!prenom || !nom || !email || !password) {
            alert('Tous les champs sont requis.');
            return;
        }

        if (prenom.length > 50) {
            alert('Le prénom ne peut pas dépasser 50 caractères.');
            return;
        }

        if (nom.length > 50) {
            alert('Le nom ne peut pas dépasser 50 caractères.');
            return;
        }

        if (!isValidEmail(email)) {
            alert('Format d\'email invalide.');
            return;
        }

        if (password.length < 8) {
            alert('Le mot de passe doit contenir au moins 8 caractères.');
            return;
        }

        // Vérification reCAPTCHA
        if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.getResponse === 'function') {
            const recaptchaResponse = grecaptcha.getResponse();
            if (!recaptchaResponse || recaptchaResponse.length === 0) {
                alert('Veuillez cocher la case reCAPTCHA pour valider la protection anti-bot.');
                return;
            }
        }

        // Récupérer le token CSRF si nécessaire
        if (!AppState.csrfToken) {
            await fetchCsrfToken();
        }

        // Sécuriser les champs texte (limiter la taille)
        const safePrenom = truncateText(prenom, 50);
        const safeNom = truncateText(nom, 50);

        const formData = new FormData(registerForm);
        formData.set('prenom', safePrenom);
        formData.set('nom', safeNom);
        formData.append('action', 'register');
        addCsrfToken(formData);

        try {
            const data = await secureFetch('api/main.php', {
                method: 'POST',
                body: formData
            });

            if (data.success) {
                if (data.csrf_token) {
                    AppState.csrfToken = data.csrf_token;
                }
                AppState.isAuthenticated = true;
                window.location.reload();
            } else {
                alert(data.message || 'Erreur lors de la création du compte.');
            }
        } catch (err) {
            console.error('Erreur:', err);
            alert('Erreur de connexion au serveur.');
        }
    });
}

// --- DÉCONNEXION ---
const logoutBtn = document.getElementById('logout-btn');
if (logoutBtn) {
    logoutBtn.addEventListener('click', async function() {
        const formData = new FormData();
        formData.append('action', 'logout');
        addCsrfToken(formData);

        try {
            await secureFetch('api/main.php', {
                method: 'POST',
                body: formData
            });
            AppState.isAuthenticated = false;
            AppState.csrfToken = null;
            window.location.reload();
        } catch (err) {
            console.error('Erreur:', err);
            window.location.reload();
        }
    });
}

// =========================================================================
// 15. CONTRÔLE AUDIO
// =========================================================================

const playSoundBtn = document.getElementById('play-sound-btn');
if (playSoundBtn) {
    playSoundBtn.addEventListener('click', playVehicleSound);
}

// =========================================================================
// 16. GESTION DU REDIMENSIONNEMENT
// =========================================================================

window.addEventListener('resize', () => {
    if (container && renderer) {
        const width = container.clientWidth;
        const height = container.clientHeight;
        renderer.setSize(width, height);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
    }
});

// =========================================================================
// 17. ANIMATION THREE.JS
// =========================================================================

function animate() {
    requestAnimationFrame(animate);
    if (controls) controls.update();
    if (renderer && scene && camera) renderer.render(scene, camera);
}

// =========================================================================
// 18. INITIALISATION DE L'APPLICATION
// =========================================================================

async function initApp() {
    // Récupérer le token CSRF au démarrage
    await fetchCsrfToken();

    // Vérifier si l'utilisateur est connecté (vérification via un appel API ou état initial)
    if (document.body.dataset.authenticated === 'true') {
        AppState.isAuthenticated = true;
    }

    // Charger le premier véhicule et démarrer l'animation
    await changeVehicle(0);
    animate();
}

// Démarrer l'application quand le DOM est prêt
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}