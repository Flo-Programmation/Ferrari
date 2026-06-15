/**
 * Scuderia Ferrari Exhibition - Main Application Script
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

let currentIndex = 0;
const loadedGroups = new Map();
let currentAudio = null; // Déplacé ici pour être accessible partout

// Fonctions utilitaires audio définies tôt pour éviter les erreurs de chargement
function stopCurrentVehicleSound() { 
    if (currentAudio) { 
        currentAudio.pause(); 
        currentAudio.currentTime = 0; 
    } 
}

// Sécurité anti-injection HTML
function escapeHtml(text) {
    if (!text) return '';
    return text
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// =========================================================================
// 2. INITIALISATION DE LA SCÈNE THREE.JS
// =========================================================================
const container = document.getElementById('webgl-canvas-container');
const scene = new THREE.Scene();

const camera = new THREE.PerspectiveCamera(35, container ? container.clientWidth / container.clientHeight : 1, 0.1, 100);
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
// 3. CHARGEMENT DES MODÈLES 3D
// =========================================================================
const loader = new GLTFLoader();

function loadVehicleModel(index) {
    if (loadedGroups.has(index)) {
        return Promise.resolve(loadedGroups.get(index));
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

            loadedGroups.set(index, group);
            resolve(group);
        });
    });
}

// =========================================================================
// 4. LOGIQUE DE CHANGEMENT DE VÉHICULE (SLIDER)
// =========================================================================
let currentActiveGroup = null;

async function changeVehicle(newIndex) {
    stopCurrentVehicleSound();
    currentIndex = newIndex;
    if (container) container.style.opacity = 0.1;

    const data = carData[currentIndex];
    
    const titleEl = document.getElementById('car-title');
    const subtitleEl = document.getElementById('car-subtitle');
    const badgeEl = document.getElementById('car-badge');
    const specsEl = document.getElementById('car-specs');
    const descEl = document.getElementById('car-desc');

    if (titleEl) titleEl.innerText = data.title;
    if (subtitleEl) subtitleEl.innerText = data.subtitle;
    if (badgeEl) badgeEl.innerText = data.badge;
    if (specsEl) specsEl.innerHTML = data.specs;
    if (descEl) descEl.innerText = data.desc;
    
    updateSidePanelContent(data);
    fetchCommentsForVehicle(currentIndex);

    document.querySelectorAll('.dots-navigation .dot').forEach((dot, idx) => {
        dot.classList.toggle('active', idx === currentIndex);
    });

    const nextGroup = await loadVehicleModel(currentIndex);
    if (currentActiveGroup) scene.remove(currentActiveGroup);
    currentActiveGroup = nextGroup;
    scene.add(currentActiveGroup);
    
    controls.reset();
    camera.position.set(0, 0.3, 5.0);
    if (container) container.style.opacity = 1;
}

const arrowNext = document.getElementById('arrow-next');
const arrowPrev = document.getElementById('arrow-prev');
if (arrowNext) arrowNext.addEventListener('click', () => changeVehicle((currentIndex + 1) % carData.length));
if (arrowPrev) arrowPrev.addEventListener('click', () => changeVehicle((currentIndex - 1 + carData.length) % carData.length));

// =========================================================================
// 5. ENVOI ET CHARGEMENT DES AVIS EN AJAX (BASE DE DONNÉES)
// =========================================================================
function fetchCommentsForVehicle(vehicleIndex) {
    const listContainer = document.getElementById('reviews-list-container');
    const sidePreview = document.getElementById('reviews-side-preview');
    const distribContainer = document.getElementById('stars-distribution-container');
    const globalStarsContainer = document.getElementById('global-stars-stars');
    const avgNumSpan = document.getElementById('average-rating-num');
    const totalCountSpan = document.getElementById('total-reviews-count');

    fetch(`api/main.php?action=get&vehicle_index=${vehicleIndex}`)
        .then(res => res.json())
        .then(data => {
            let totalComments = 0;
            let averageRating = 0.0;
            let starCounts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };

            if (data && data.success && data.comments && data.comments.length > 0) {
                totalComments = data.comments.length;
                let sum = 0;
                data.comments.forEach(c => {
                    const r = Math.round(parseFloat(c.rating));
                    sum += r;
                    if (starCounts[r] !== undefined) starCounts[r]++;
                });
                averageRating = (sum / totalComments).toFixed(1);
            }

            if (avgNumSpan) avgNumSpan.textContent = totalComments > 0 ? averageRating : "0.0";
            if (totalCountSpan) totalCountSpan.textContent = totalComments;

            if (globalStarsContainer) {
                let gStarsHTML = '';
                const roundedAvg = Math.round(averageRating);
                for (let i = 1; i <= 5; i++) {
                    gStarsHTML += i <= roundedAvg 
                        ? '<i class="fa-solid fa-star" style="margin-right:2px; color:#ffaa00;"></i>' 
                        : '<i class="fa-regular fa-star" style="margin-right:2px; color:#555;"></i>';
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
                if (listContainer) {
                    listContainer.innerHTML = `
                        <div style="text-align:center; padding:60px 10px; opacity:0.4;">
                            <i class="fa-regular fa-comments" style="font-size:32px; margin-bottom:12px; display:block; color:#ff2828;"></i>
                            <p style="font-size:13px; margin:0; font-style:italic; color:#fff;">il n'y a pas encore d'avis sur ce modèle</p>
                        </div>`;
                }
                if (sidePreview) {
                    sidePreview.innerHTML = '<p style="opacity:0.4; font-size:12px; font-style:italic; margin:0; color:#fff;">Aucun avis pour le moment.</p>';
                }
                return;
            }

            let listHTML = '';
            let previewHTML = '';

            data.comments.forEach((comment, index) => {
                let starsHTML = '';
                const ratingVal = parseInt(comment.rating) || 0;
                for (let i = 1; i <= 5; i++) {
                    starsHTML += i <= ratingVal ? '<i class="fa-solid fa-star" style="color:#ffaa00;"></i>' : '<i class="fa-regular fa-star" style="color:#555;"></i>';
                }
                
                const authorName = `${comment.prenom || 'Utilisateur'} ${comment.nom ? comment.nom.charAt(0) + '.' : ''}`;
                const avatar = comment.avatar_url || 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png';
                const commentText = escapeHtml(comment.comment);

                listHTML += `
                    <div class="review-card">
                        <img src="${avatar}" class="review-avatar">
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
            console.error("Erreur lors de la récupération des avis :", err);
        });
}

const leaveReviewForm = document.getElementById('leave-review-form');
if (leaveReviewForm) {
    leaveReviewForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const checkedStarInput = leaveReviewForm.querySelector('input[name="stars-qty"]:checked') || leaveReviewForm.querySelector('input[name="rating"]:checked');
        const commentTextarea = document.getElementById('review-textarea');

        if (!commentTextarea) return;

        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('vehicle_index', currentIndex);
        formData.append('rating', checkedStarInput ? checkedStarInput.value : 5);
        formData.append('comment', commentTextarea.value.trim());

        fetch('api/main.php?action=add', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                commentTextarea.value = '';
                fetchCommentsForVehicle(currentIndex);
                alert(data.message);
            } else {
                alert(data.message);
            }
        })
        .catch(() => {
            alert("Impossible de joindre le serveur de télémétrie.");
        });
    });
}

// =========================================================================
// 6. GESTION DES MODALES, ONGLETS ET PANNEAUX LATÉRAUX
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

if (profileTrigger && authModal) {
    profileTrigger.addEventListener('click', () => {
        authModal.classList.add('active');
    });
}

if (closeModalBtn && authModal) {
    closeModalBtn.addEventListener('click', () => {
        authModal.classList.remove('active');
    });
}

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
// 7. ROBUSTESSE DU MOT DE PASSE (VÉRIFICATION EN TEMPS RÉEL)
// =========================================================================
const registerPasswordInput = document.getElementById('register-password');
const strengthContainer = document.getElementById('password-strength-container');
const strengthBarFill = document.getElementById('strength-bar-fill');
const strengthText = document.getElementById('strength-text');

if (registerPasswordInput) {
    registerPasswordInput.addEventListener('input', () => {
        const password = registerPasswordInput.value;
        let score = 0;

        if (password.length >= 6) score++;
        if (password.length >= 10) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^A-Za-z0-9]/.test(password)) score++;

        if (password.length === 0) {
            if (strengthContainer) strengthContainer.style.display = 'none';
            if (strengthBarFill) { strengthBarFill.style.width = '0%'; strengthBarFill.style.backgroundColor = 'transparent'; }
            if (strengthText) strengthText.innerHTML = '';
        } else {
            if (strengthContainer) strengthContainer.style.display = 'block';

            if (score <= 2) {
                if (strengthBarFill) { strengthBarFill.style.width = '33%'; strengthBarFill.style.backgroundColor = '#ff4d4d'; }
                if (strengthText) strengthText.innerHTML = '<span style="color:#ff4d4d;">Sécurité : Faible / Insuffisante 🔴</span>';
            } else if (score <= 4) {
                if (strengthBarFill) { strengthBarFill.style.width = '66%'; strengthBarFill.style.backgroundColor = '#ffaa00'; }
                if (strengthText) strengthText.innerHTML = '<span style="color:#ffaa00;">Sécurité : Moyenne 🟡</span>';
            } else {
                if (strengthBarFill) { strengthBarFill.style.width = '100%'; strengthBarFill.style.backgroundColor = '#00cc66'; }
                if (strengthText) strengthText.innerHTML = '<span style="color:#00cc66;">Sécurité : Maximale / Forte 🟢</span>';
            }
        }
    });
}

function updateSidePanelContent(data) {
    const specsGrid = document.getElementById('panel-specs-grid');
    
    if (specsGrid) {
        specsGrid.innerHTML = `
            <div class="spec-item"><div><span>Moteur</span><p>${data.techDetails.moteur}</p></div></div>
            <div class="spec-item"><div><span>Vitesse max</span><p>${data.techDetails.vitesse}</p></div></div>
            <div class="spec-item"><div><span>Puissance</span><p>${data.techDetails.puissance}</p></div></div>
            <div class="spec-item"><div><span>0-100 km/h</span><p>${data.techDetails.chrono}</p></div></div>
        `;
    }
}

// =========================================================================
// 8. FORMULAIRES D'AUTHENTIFICATION AJAX (AVEC VALIDATION RECAPTCHA)
// =========================================================================
const loginForm = document.getElementById('login-form');
if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(loginForm);
        formData.append('action', 'login');
        fetch('api/main.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => data.success ? window.location.reload() : alert(data.message));
    });
}

const registerForm = document.getElementById('register-form');
if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();

        if (typeof grecaptcha !== 'undefined') {
            const recaptchaResponse = grecaptcha.getResponse();
            if (recaptchaResponse.length === 0) {
                alert("Veuillez cocher la case reCAPTCHA pour valider la protection anti-bot.");
                return;
            }
        }

        const formData = new FormData(registerForm);
        formData.append('action', 'register');
        fetch('api/main.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message);
                }
            });
    });
}

const logoutBtn = document.getElementById('logout-btn');
if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
        const formData = new FormData();
        formData.append('action', 'logout');
        fetch('api/main.php', { method: 'POST', body: formData }).then(() => window.location.reload());
    });
}

// Contrôle Audio
const playSoundBtn = document.getElementById('play-sound-btn');
if (playSoundBtn) {
    playSoundBtn.addEventListener('click', () => {
        if (currentAudio) { currentAudio.pause(); currentAudio.currentTime = 0; }
        currentAudio = new Audio(carData[currentIndex].sound);
        currentAudio.volume = 0.2;
        currentAudio.play();
    });
}

function animate() { 
    requestAnimationFrame(animate); 
    if (controls) controls.update(); 
    if (renderer && scene && camera) renderer.render(scene, camera); 
}

// Démarrage de l'application
changeVehicle(0).then(() => animate());