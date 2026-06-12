import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

// =========================================================================
// 1. BASE DE DONNÉES ET CONFIGURATION DES MODÈLES
// =========================================================================
const carData = [
    {
        model: "assets/models/ferrari_bleu.glb",
        title: "Monza SP3 Evo",
        subtitle: "L'Équilibre Absolu du V12",
        badge: "Édition Limitée",
        specs: "<p><strong>Moteur :</strong> V12 Atmosphérique | 6.5L</p><p><strong>Puissance :</strong> 850 ch à 9 200 tr/min</p><p><strong>0-100 km/h :</strong> 2.85 secondes</p>",
        desc: "Inspirée des mythiques barquettes de compétition des années 1960. Son profil aérodynamique virtuel unique canalise le flux d'air pour s'affranchir de pare-brise.",
        sound: "assets/src/sounds/ferrariEngine.wav" // Ton fichier audio
    },
    {
        model: "assets/models/599obj.glb",
        title: "SF100 Vision",
        subtitle: "Le Futur Hyper-Électrique",
        badge: "Concept Car",
        specs: "<p><strong>Motorisation :</strong> 4 Moteurs Électriques</p><p><strong>Puissance :</strong> 1 200 ch combinés</p><p><strong>Couple maximal :</strong> Vectorisation active</p>",
        desc: "Une vitrine technologique de la Scuderia dotée d'un empattement long. Conçue exclusivement pour briser les lois de la physique sur circuit.",
        sound: "assets/src/sounds/ferrariEngine.wav" // Ton fichier audio
    },
    {
        model: "assets/models/ferrari.glb",
        title: "F42 Aperta",
        subtitle: "La Pureté à Ciel Ouvert",
        badge: "Série Spéciale",
        specs: "<p><strong>Moteur :</strong> V8 Bi-turbo Hybride</p><p><strong>Puissance :</strong> 830 ch + 163 ch élec</p><p><strong>Châssis :</strong> Monocoque Carbone</p>",
        desc: "Alliant un toit amovible en fibre de carbone à la puissance brute du système hybride synchrone directement dérivé du savoir-faire F1.",
        sound: "assets/src/sounds/ferrariEngine.wav" // Ton fichier audio
    }
];

let currentIndex = 0;
const loadedGroups = new Map(); // Cache d'objets 3D optimisé

// =========================================================================
// 2. INITIALISATION DE LA SCÈNE ET DES COMPOSANTS GRAPHES
// =========================================================================
const container = document.getElementById('webgl-canvas-container');
const scene = new THREE.Scene();

// Caméra perspective optimisée pour affichage grand format de profil
const camera = new THREE.PerspectiveCamera(35, container.clientWidth / container.clientHeight, 0.1, 100);
camera.position.set(0, 0.3, 5.0); // Placement de profil initial propre

const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setSize(container.clientWidth, container.clientHeight);
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.shadowMap.enabled = true;
container.appendChild(renderer.domElement);

// ÉCLAIRAGE CONFIGURATEUR STUDIO
scene.add(new THREE.AmbientLight(0xffffff, 1.0));
const lightTop = new THREE.DirectionalLight(0xffffff, 2.5);
lightTop.position.set(0, 8, 2);
scene.add(lightTop);

const lightFill = new THREE.DirectionalLight(0xffffff, 1.0);
lightFill.position.set(5, 2, -2);
scene.add(lightFill);

// CONFIGURATION DES CONTRÔLES (Restriction stricte demandée)
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.05;
controls.enablePan = false; 
controls.enableZoom = true;
controls.minDistance = 3.5;
controls.maxDistance = 6.5;

// Verrouillage angulaire horizontal pour ne voir qu'un peu l'avant et l'arrière
controls.minAzimuthAngle = -Math.PI / 5; // Rotation limitée vers l'avant
controls.maxAzimuthAngle = Math.PI / 5;  // Rotation limitée vers l'arrière
controls.minPolarAngle = Math.PI / 2.5;  // Plafond vertical de plongée
controls.maxPolarAngle = Math.PI / 1.95; // Sol technique

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

            // Normalisation de l'échelle et centrage automatique
            const box = new THREE.Box3().setFromObject(model);
            const size = new THREE.Vector3();
            box.getSize(size);
            const maxDim = Math.max(size.x, size.y, size.z);
            const targetScale = 3.3 / maxDim; // Calibrage taille de la voiture
            model.scale.set(targetScale, targetScale, targetScale);

            // Correction spécifique de rotation pour le modèle 599obj
            if (carData[index].model.includes('599obj.glb')) {
                model.rotation.x = -Math.PI / 2;
            }

            // Réajustement du point de pivot au centre géométrique
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
    // NOUVEAU : On coupe le son en cours avant de changer de modèle
    stopCurrentVehicleSound();

    currentIndex = newIndex;
    
    // Effet d'atténuation optique (Fade-out)
    container.style.opacity = 0.1;

    // Mise à jour de l'interface textuelle synchrone
    const data = carData[currentIndex];
    document.getElementById('car-title').innerText = data.title;
    document.getElementById('car-subtitle').innerText = data.subtitle;
    document.getElementById('car-badge').innerText = data.badge;
    document.getElementById('car-specs').innerHTML = data.specs;
    document.getElementById('car-desc').innerText = data.desc;
    
    // Actualisation de l'état des puces de navigation
    document.querySelectorAll('.dot').forEach((dot, idx) => {
        dot.classList.toggle('active', idx === currentIndex);
    });

    // Chargement de l'entité 3D correspondante
    const nextGroup = await loadVehicleModel(currentIndex);

    // Retrait sélectif de l'ancien modèle du graphe de scène
    if (currentActiveGroup) scene.remove(currentActiveGroup);
    
    // Injection du nouveau modèle et réinitialisation de la perspective de profil
    currentActiveGroup = nextGroup;
    scene.add(currentActiveGroup);
    
    controls.reset();
    camera.position.set(0, 0.3, 5.0);
    controls.target.set(0, 0, 0);

    // Rétablissement de la visibilité (Fade-in)
    container.style.opacity = 1;
}

// INTERRUPTEURS ET ÉCOUTEURS D'ÉVÉNEMENTS
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
// 5. CONTRÔLE DU PANNEAU LATÉRAL COULISSANT
// =========================================================================
const sidePanel = document.getElementById('side-panel');
const openPanelBtn = document.getElementById('open-panel-btn');
const closePanelBtn = document.getElementById('close-panel-btn');

openPanelBtn.addEventListener('click', () => {
    document.getElementById('panel-car-title').innerText = carData[currentIndex].title;
    sidePanel.classList.add('open');
});

closePanelBtn.addEventListener('click', () => sidePanel.classList.remove('open'));

// Fermeture si clic à l'extérieur du volet
window.addEventListener('click', (e) => {
    if (!sidePanel.contains(e.target) && e.target !== openPanelBtn && sidePanel.classList.contains('open')) {
        sidePanel.classList.remove('open');
    }
});

// =========================================================================
// CONTRÔLE AUDIO DU MOTEUR
// =========================================================================
const playSoundBtn = document.getElementById('play-sound-btn');
let currentAudio = null;

playSoundBtn.addEventListener('click', () => {
    // Si un son est déjà en train de jouer, on l'arrête pour pouvoir le relancer
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
    }

    // On récupère le fichier son associé à la voiture actuellement affichée
    const soundPath = carData[currentIndex].sound;
    
    // Création et lancement de l'objet audio
    currentAudio = new Audio(soundPath);
    currentAudio.volume = 0.2; // Volume à 70% pour ne pas détruire les oreilles
    currentAudio.play();

    // Effet visuel optionnel sur le bouton pendant la lecture
    playSoundBtn.classList.add('playing');
    playSoundBtn.innerHTML = `<i class="fa-solid fa-gauge-high"></i> Vroooam !`;

    // Remettre le bouton à l'état initial quand le son se termine
    currentAudio.onended = () => {
        playSoundBtn.classList.remove('playing');
        playSoundBtn.innerHTML = `<i class="fa-solid fa-volume-high"></i> Écouter le moteur`;
    };
});

// SÉCURITÉ : Si l'utilisateur clique sur "Suivant" ou "Précédent", on coupe immédiatement le son
function stopCurrentVehicleSound() {
    if (currentAudio) {
        currentAudio.pause();
        currentAudio.currentTime = 0;
        playSoundBtn.classList.remove('playing');
        playSoundBtn.innerHTML = `<i class="fa-solid fa-volume-high"></i> Écouter le moteur`;
    }
}

// =========================================================================
// 6. RENDER LOOP ET ADAPTABILITÉ FENÊTRE
// =========================================================================
function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
}

// Lancement initial de l'application
window.addEventListener('resize', () => {
    camera.aspect = container.clientWidth / container.clientHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(container.clientWidth, container.clientHeight);
});

// Premier chargement de la voiture à l'index 0
changeVehicle(0).then(() => animate());
