import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

// =========================================================================
// 1. BASE DE DONNÉES ET CONFIGURATION DES MODÈLES (ENRICHIE MAQUETTE)
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
            moteur: "V12 Atmosphérique 6.5L",
            vitesse: "> 340 km/h",
            puissance: "850 ch",
            couple: "692 Nm",
            chrono: "2.85 s",
            poids: "1 480 kg",
            boite: "DCT 7 rapports",
            transmission: "Propulsion"
        },
        tracks: {
            fiorano: "1'16\"30",
            monza: "1'41\"20"
        }
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
            moteur: "4 Moteurs Électriques",
            vitesse: "> 350 km/h",
            puissance: "1 200 ch",
            couple: "1 400 Nm",
            chrono: "1.95 s",
            poids: "1 650 kg",
            boite: "Direct Drive",
            transmission: "Intégrale (4WD)"
        },
        tracks: {
            fiorano: "1'12\"10 (Record)",
            monza: "1'35\"40"
        }
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
            moteur: "V8 Bi-turbo Hybride",
            vitesse: "> 340 km/h",
            puissance: "993 ch (Cumulée)",
            couple: "900 Nm",
            chrono: "2.9 s",
            poids: "1 485 kg",
            boite: "DCT 8 rapports",
            transmission: "Propulsion"
        },
        tracks: {
            fiorano: "1'17\"80",
            monza: "1'43\"15"
        }
    }
];

let currentIndex = 0;
const loadedGroups = new Map();

// =========================================================================
// 2. INITIALISATION DE LA SCÈNE ET DES COMPOSANTS GRAPHES
// =========================================================================
const container = document.getElementById('webgl-canvas-container');
const scene = new THREE.Scene();

const camera = new THREE.PerspectiveCamera(35, container.clientWidth / container.clientHeight, 0.1, 100);
camera.position.set(0, 0.3, 5.0);

const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setSize(container.clientWidth, container.clientHeight);
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.shadowMap.enabled = true;
container.appendChild(renderer.domElement);

scene.add(new THREE.AmbientLight(0xffffff, 1.0));
const lightTop = new THREE.DirectionalLight(0xffffff, 2.5);
lightTop.position.set(0, 8, 2);
scene.add(lightTop);

const lightFill = new THREE.DirectionalLight(0xffffff, 1.0);
lightFill.position.set(5, 2, -2);
scene.add(lightFill);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.05;
controls.enablePan = false; 
controls.enableZoom = true;
controls.minDistance = 3.5;
controls.maxDistance = 6.5;

controls.minAzimuthAngle = -Math.PI / 5;
controls.maxAzimuthAngle = Math.PI / 5;
controls.minPolarAngle = Math.PI / 2.5;
controls.maxPolarAngle = Math.PI / 1.95;

// =========================================================================
// 3. CHARGEMENT ET PRE-TRAITEMENT ASYNCHRONE
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
            const targetScale = 3.3 / maxDim;
            model.scale.set(targetScale, targetScale, targetScale);

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
// 4. LOGIQUE DU SLIDER ET TRANSITIONS EN FONDU
// =========================================================================
let currentActiveGroup = null;

async function changeVehicle(newIndex) {
    stopCurrentVehicleSound();
    currentIndex = newIndex;
    container.style.opacity = 0.1;

    const data = carData[currentIndex];
    document.getElementById('car-title').innerText = data.title;
    document.getElementById('car-subtitle').innerText = data.subtitle;
    document.getElementById('car-badge').innerText = data.badge;
    document.getElementById('car-specs').innerHTML = data.specs;
    document.getElementById('car-desc').innerText = data.desc;
    
    updateSidePanelContent(data);

    document.querySelectorAll('.dot').forEach((dot, idx) => {
        dot.classList.toggle('active', idx === currentIndex);
    });

    const nextGroup = await loadVehicleModel(currentIndex);

    if (currentActiveGroup) scene.remove(currentActiveGroup);
    currentActiveGroup = nextGroup;
    scene.add(currentActiveGroup);
    
    controls.reset();
    camera.position.set(0, 0.3, 5.0);
    controls.target.set(0, 0, 0);

    container.style.opacity = 1;
}

document.getElementById('arrow-next').addEventListener('click', () => {
    let nextIdx = (currentIndex + 1) % carData.length;
    changeVehicle(nextIdx);
});

document.getElementById('arrow-prev').addEventListener('click', () => {
    let prevIdx = (currentIndex - 1 + carData.length) % carData.length;
    changeVehicle(prevIdx);
});

document.querySelectorAll('.dot').forEach(dot => {
    dot.addEventListener('click', (e) => {
        const targetIdx = parseInt(e.target.getAttribute('data-index'));
        if(targetIdx !== currentIndex) changeVehicle(targetIdx);
    });
});

// =========================================================================
// 5. CONTRÔLE DU PANNEAU LATÉRAL COULISSANT & NAVIGATION ONGLETS
// =========================================================================
const sidePanel = document.getElementById('side-panel');
const openPanelBtn = document.getElementById('open-panel-btn');
const closePanelBtn = document.getElementById('close-panel-btn');

const tabBtns = document.querySelectorAll('.tab-btn');
const tabContents = document.querySelectorAll('.tab-pane');

tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        tabBtns.forEach(b => b.classList.remove('active'));
        tabContents.forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const targetTab = document.getElementById(btn.dataset.tab);
        if (targetTab) targetTab.classList.add('active');
    });
});

if (openPanelBtn) {
    openPanelBtn.addEventListener('click', () => {
        sidePanel.classList.add('open');
    });
}

if (closePanelBtn) {
    closePanelBtn.addEventListener('click', () => sidePanel.classList.remove('open'));
}

window.addEventListener('click', (e) => {
    if (sidePanel && !sidePanel.contains(e.target) && e.target !== openPanelBtn && sidePanel.classList.contains('open')) {
        sidePanel.classList.remove('open');
    }
});

function updateSidePanelContent(data) {
    const fioranoEl = document.getElementById('track-fiorano');
    const monzaEl = document.getElementById('track-monza');
    if (fioranoEl) fioranoEl.innerText = data.tracks.fiorano;
    if (monzaEl) monzaEl.innerText = data.tracks.monza;
    
    const specsGrid = document.getElementById('panel-specs-grid');
    if (specsGrid) {
        specsGrid.innerHTML = `
            <div class="spec-item"><i class="fa-solid fa-gauge-high"></i> <div><span>Moteur</span><p>${data.techDetails.moteur}</p></div></div>
            <div class="spec-item"><i class="fa-solid fa-bolt"></i> <div><span>Vitesse max</span><p>${data.techDetails.vitesse}</p></div></div>
            <div class="spec-item"><i class="fa-solid fa-horse"></i> <div><span>Puissance</span><p>${data.techDetails.puissance}</p></div></div>
            <div class="spec-item"><i class="fa-solid fa-circle-notch"></i> <div><span>Couple max</span><p>${data.techDetails.couple}</p></div></div>
            <div class="spec-item"><i class="fa-solid fa-stopwatch"></i> <div><span>0-100 km/h</span><p>${data.techDetails.chrono}</p></div></div>
            <div class="spec-item"><i class="fa-solid fa-weight-hanging"></i> <div><span>Poids</span><p>${data.techDetails.poids}</p></div></div>
            <div class="spec-item"><i class="fa-solid fa-sliders"></i> <div><span>Transmission</span><p>${data.techDetails.boite}</p></div></div>
            <div class="spec-item"><i class="fa-solid fa-road"></i> <div><span>Motricité</span><p>${data.techDetails.transmission}</p></div></div>
        `;
    }
}

// =========================================================================
// CONTRÔLE AUDIO DU MOTEUR
// =========================================================================
const playSoundBtn = document.getElementById('play-sound-btn');
let currentAudio = null;

if (playSoundBtn) {
    playSoundBtn.addEventListener('click', () => {
        if (currentAudio) {
            currentAudio.pause();
            currentAudio.currentTime = 0;
        }

        const soundPath = carData[currentIndex].sound;
        currentAudio = new Audio(soundPath);
        currentAudio.volume = 0.2; 
        currentAudio.play();

        playSoundBtn.classList.add('playing');
        playSoundBtn.innerHTML = `<i class="fa-solid fa-gauge-high"></i> Vroooam !`;

        currentAudio.onended = () => {
            playSoundBtn.classList.remove('playing');
            playSoundBtn.innerHTML = `<i class="fa-solid fa-volume-high"></i> Écouter le moteur`;
        };
    });
}

function stopCurrentVehicleSound() {
    if (currentAudio && playSoundBtn) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
        playSoundBtn.classList.remove('playing');
        playSoundBtn.innerHTML = `<i class="fa-solid fa-volume-high"></i> Écouter le moteur`;
    }
}

// =========================================================================
// 6. GESTION DE LA MODALE D'AUTHENTIFICATION & AJAX (CONNEXION / INSCRIPTION)
// =========================================================================
const authModal = document.getElementById('auth-modal');
const profileTrigger = document.getElementById('profile-trigger');
const closeModalBtn = document.getElementById('close-modal-btn');

if (profileTrigger && authModal) {
    profileTrigger.addEventListener('click', () => authModal.classList.add('active'));
}
if (closeModalBtn && authModal) {
    closeModalBtn.addEventListener('click', () => authModal.classList.remove('active'));
}
window.addEventListener('click', (e) => {
    if (e.target === authModal) {
        authModal.classList.remove('active');
    }
});

const modalTabBtns = document.querySelectorAll('#auth-modal .auth-tabs .tab-btn');
const modalFormContents = document.querySelectorAll('#auth-modal .auth-form-content');

modalTabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
        modalTabBtns.forEach(b => b.classList.remove('active'));
        modalFormContents.forEach(f => f.classList.remove('active'));
        
        btn.classList.add('active');
        const targetForm = document.getElementById(btn.dataset.tab);
        if (targetForm) targetForm.classList.add('active');
    });
});

// AJAX : Traitement de l'Inscription
const registerForm = document.getElementById('register-form');
const registerError = document.getElementById('register-error');
const registerPasswordInput = document.getElementById('register-password');

if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (registerError) registerError.textContent = "";

        // 1. Validation de l'adresse Email
        const emailInput = document.getElementById('register-email');
        const emailValue = emailInput ? emailInput.value.trim().toLowerCase() : "";
        
        const allowedDomains = [
            'gmail.com', 'outlook.com', 'outlook.fr', 
            'hotmail.com', 'hotmail.fr', 'yahoo.com', 
            'yahoo.fr', 'icloud.com', 'orange.fr', 
            'wanadoo.fr', 'sfr.fr', 'free.fr', 'live.fr'
        ];

        const emailParts = emailValue.split('@');
        const emailDomain = emailParts[1];
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

        if (!emailRegex.test(emailValue) || !allowedDomains.includes(emailDomain)) {
            if (registerError) {
                registerError.textContent = "Veuillez utiliser un e-mail connu (ex: @gmail.com, @outlook.fr).";
                registerError.style.color = "#ff4d4d";
            }
            if (emailInput) {
                emailInput.focus();
                emailInput.style.borderColor = "#ff4d4d";
            }
            return;
        } else {
            if (emailInput) emailInput.style.borderColor = "rgba(255,255,255,0.1)";
        }

        // 2. Sécurité : Vérification mot de passe
        const passwordValue = registerPasswordInput ? registerPasswordInput.value : "";
        if (passwordValue.length < 6) {
            if (registerError) registerError.textContent = "Le mot de passe doit contenir au moins 6 caractères.";
            return;
        }

        // 3. AJOUT : Validation du jeton Google reCAPTCHA
        const recaptchaResponse = grecaptcha.getResponse();
        if (recaptchaResponse.length === 0) {
            if (registerError) {
                registerError.textContent = "Veuillez valider le reCAPTCHA pour prouver que vous n'êtes pas un robot.";
                registerError.style.color = "#ff4d4d";
            }
            return;
        }

        const formData = new FormData(registerForm);
        formData.append('action', 'register');

        fetch('auth_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error("Erreur HTTP " + response.status);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert("Inscription réussie ! Connexion en cours...");
                window.location.reload(); 
            } else {
                if (registerError) registerError.textContent = data.message || "Erreur d'inscription.";
                // Réinitialise le reCAPTCHA en cas d'erreur serveur pour permettre un nouvel essai
                grecaptcha.reset();
            }
        })
        .catch(error => {
            console.error(error);
            if (registerError) registerError.textContent = "Erreur de communication avec le serveur.";
            grecaptcha.reset();
        });
    });
}

// INDICATEUR DE FORCE DU MOT DE PASSE EN TEMPS RÉEL
const strengthContainer = document.getElementById('password-strength-container');
const strengthBarFill = document.getElementById('strength-bar-fill');
const strengthText = document.getElementById('strength-text');

if (registerPasswordInput && strengthContainer && strengthBarFill && strengthText) {
    registerPasswordInput.addEventListener('input', function() {
        const val = registerPasswordInput.value;
        
        if (val.length === 0) {
            strengthContainer.style.display = "none";
            return;
        }

        strengthContainer.style.display = "block";
        let score = 0;
        
        if (val.length >= 6) score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;

        // Mise à jour de la taille et de la couleur proportionnellement
        if (score <= 2) {
            strengthBarFill.style.width = "33%";
            strengthBarFill.style.background = "#ff2828"; // Rouge
            strengthText.textContent = "Sécurité : Faible 🔴";
            strengthText.style.color = "#ff2828";
        } else if (score === 3 || score === 4) {
            strengthBarFill.style.width = "66%";
            strengthBarFill.style.background = "#ffaa00"; // Orange
            strengthText.textContent = "Sécurité : Moyen 🟡";
            strengthText.style.color = "#ffaa00";
        } else if (score >= 5) {
            strengthBarFill.style.width = "100%";
            strengthBarFill.style.background = "#00cc66"; // Vert
            strengthText.textContent = "Sécurité : Excellent / Fort 🟢";
            strengthText.style.color = "#00cc66";
        }
    });
}

// AJAX : Traitement de la Connexion
const loginForm = document.getElementById('login-form');
const loginError = document.getElementById('login-error');

if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();
        if (loginError) loginError.textContent = "";

        const formData = new FormData(loginForm);
        formData.append('action', 'login');

        fetch('auth_process.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error("Erreur HTTP " + response.status);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                if (loginError) loginError.textContent = data.message || "Identifiants invalides.";
            }
        })
        .catch(error => {
            console.error(error);
            if (loginError) loginError.textContent = "Erreur de communication avec le serveur.";
        });
    });
}

// Traitement de la Déconnexion
const logoutBtn = document.getElementById('logout-btn');
if (logoutBtn) {
    logoutBtn.addEventListener('click', () => {
        const formData = new FormData();
        formData.append('action', 'logout');

        fetch('auth_process.php', {
            method: 'POST',
            body: formData
        })
        .then(() => window.location.reload())
        .catch(error => console.error("Erreur de déconnexion:", error));
    });
}

// =========================================================================
// 7. RENDER LOOP ET ADAPTABILITÉ FENÊTRE
// =========================================================================
function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
}

window.addEventListener('resize', () => {
    camera.aspect = container.clientWidth / container.clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(container.clientWidth, container.clientHeight);
});

changeVehicle(0).then(() => animate());