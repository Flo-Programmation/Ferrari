import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";

let engineActive = true,
  carData = [],
  cachedHotspots = null,
  engineScene,
  engineCamera,
  engineRenderer,
  engineModel;

const AppState = {
  currentIndex: 0,
  loadedGroups: new Map(),
  currentAudio: null,
  currentActiveGroup: null,
  csrfToken: document.getElementById("global-csrf-token")?.value || null,
  isAuthenticated: false,
};

// --- LOADING MANAGER & DOM CACHE ---
const loadingManager = new THREE.LoadingManager();
loadingManager.onLoad = () =>
  document.getElementById("site-loader")?.classList.add("loader-hidden");

const hotspotsData = [
  { id: "hotspot-culasse", targetPos: new THREE.Vector3(0.0, 0.4, 0.2) },
  { id: "hotspot-injection", targetPos: new THREE.Vector3(-0.3, 0.1, -0.2) },
  { id: "hotspot-bloc", targetPos: new THREE.Vector3(0.1, -0.2, 0.3) },
  { id: "hotspot-echappement", targetPos: new THREE.Vector3(0.4, -0.4, -0.1) },
];

function getCachedHotspots() {
  if (!cachedHotspots) {
    cachedHotspots = hotspotsData
      .map((h) => {
        const el = document.getElementById(h.id);
        return el
          ? {
              ...h,
              element: el,
              line: el.querySelector(".hotspot-line"),
              lineInner: el.querySelector(".hotspot-line line"),
              textBlock: el.querySelector(".hotspot-text"),
              isLeft: el.classList.contains("hotspot-left"),
            }
          : null;
      })
      .filter(Boolean);
  }
  return cachedHotspots;
}

const _vTarget = new THREE.Vector3();
const escapeHtml = (t) =>
  t
    ? t
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;")
        .replace(/\//g, "&#x2F;")
    : "";
const escapeUrl = (u) =>
  u &&
  ["https://", "http://", "data:", "blob:"].some((p) =>
    u.trim().toLowerCase().startsWith(p),
  )
    ? escapeHtml(u.trim())
    : "https://cdn-icons-png.flaticon.com/512/3135/3135715.png";
const isValidEmail = (e) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e.trim());
const truncateText = (t, m) => (t ? t.trim().substring(0, m) : "");

function getPasswordStrength(p) {
  let s = 0;
  if (p.length >= 8) s++;
  if (p.length >= 12) s++;
  if (/[A-Z]/.test(p)) s++;
  if (/[a-z]/.test(p)) s++;
  if (/[0-9]/.test(p)) s++;
  if (/[^A-Za-z0-9]/.test(p)) s++;
  return s;
}

async function secureFetch(url, options = {}) {
  try {
    const res = await fetch(url, options);
    if (
      !res.ok ||
      !res.headers.get("content-type")?.includes("application/json")
    )
      throw new Error();
    return await res.json();
  } catch {
    return { success: false, message: "Erreur de connexion au serveur." };
  }
}

function addCsrfToken(formData) {
  const tok =
    AppState.csrfToken || document.getElementById("global-csrf-token")?.value;
  if (tok) formData.append("csrf_token", tok);
  return formData;
}

function stopCurrentVehicleSound() {
  if (AppState.currentAudio) {
    AppState.currentAudio.pause();
    AppState.currentAudio.currentTime = 0;
    AppState.currentAudio = null;
  }
}

function playVehicleSound() {
  if (!carData[AppState.currentIndex]?.sound) return;
  stopCurrentVehicleSound();
  AppState.currentAudio = new Audio(carData[AppState.currentIndex].sound);
  AppState.currentAudio.volume = 0.2;
  AppState.currentAudio.play().catch((e) => console.warn(e));
}

// --- INITIALISATION SCÈNE PRINCIPALE (THREE.JS) ---
const container = document.getElementById("webgl-canvas-container");
const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(
  35,
  container ? container.clientWidth / container.clientHeight : 1,
  0.1,
  100,
);
camera.position.set(0, 0.3, 5.0);

const renderer = new THREE.WebGLRenderer({
  antialias: true,
  alpha: true,
  powerPreference: "high-performance",
});
if (container) {
  renderer.setSize(container.clientWidth, container.clientHeight);
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.shadowMap.enabled = true;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  container.appendChild(renderer.domElement);
}

const pmrem = new THREE.PMREMGenerator(renderer);
scene.environment = pmrem.fromScene(new THREE.Scene()).texture;
pmrem.dispose();

scene.add(new THREE.AmbientLight(0xffffff, 0.6));
const lightTop = new THREE.DirectionalLight(0xffffff, 3.0);
lightTop.position.set(2, 8, 4);
scene.add(lightTop);
const lightFill = new THREE.DirectionalLight(0xffffff, 1.2);
lightFill.position.set(-4, 3, -2);
scene.add(lightFill);

const controls = new OrbitControls(camera, renderer.domElement);
Object.assign(controls, {
  enableDamping: true,
  dampingFactor: 0.05,
  enablePan: false,
  minDistance: 3.5,
  maxDistance: 6.5,
  minPolarAngle: Math.PI / 4,
  maxPolarAngle: Math.PI / 2.1,
});

const loader = new GLTFLoader(loadingManager);

function loadVehicleModel(index) {
  if (AppState.loadedGroups.has(index))
    return Promise.resolve(AppState.loadedGroups.get(index));
  return new Promise((resolve) => {
    if (!carData[index]?.model) return resolve(new THREE.Group());
    loader.load(
      carData[index].model,
      (gltf) => {
        const model = gltf.scene;
        model.traverse((c) => {
          if (c.isMesh) {
            c.castShadow = c.receiveShadow = true;
            if (c.material) c.material.envMapIntensity = 1.5;
          }
        });
        const group = new THREE.Group().add(model);
        const box = new THREE.Box3().setFromObject(model);
        const size = box.getSize(new THREE.Vector3());
        model.scale.setScalar(3.3 / Math.max(size.x, size.y, size.z));
        if (carData[index].model.includes("599obj.glb"))
          model.rotation.x = -Math.PI / 2;

        const center = new THREE.Box3()
          .setFromObject(model)
          .getCenter(new THREE.Vector3());
        model.position.set(-center.x, -center.y + 0.1, -center.z);
        AppState.loadedGroups.set(index, group);
        resolve(group);
      },
      undefined,
      () => resolve(new THREE.Group()),
    );
  });
}

// --- CONFIGURATION DU MOTEUR 3D ---
const engineContainer = document.getElementById("engine-canvas-container");
if (engineContainer) {
  engineScene = new THREE.Scene();
  engineCamera = new THREE.PerspectiveCamera(
    40,
    engineContainer.clientWidth / engineContainer.clientHeight,
    0.1,
    100,
  );
  engineCamera.position.set(0, 0.2, 4.5);
  engineRenderer = new THREE.WebGLRenderer({
    antialias: true,
    alpha: true,
    powerPreference: "high-performance",
  });
  engineRenderer.setSize(
    engineContainer.clientWidth,
    engineContainer.clientHeight,
  );
  engineRenderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  engineContainer.appendChild(engineRenderer.domElement);

  engineScene.add(new THREE.AmbientLight(0xffffff, 0.7));
  const topLight = new THREE.DirectionalLight(0xffffff, 2.5);
  topLight.position.set(3, 6, 3);
  engineScene.add(topLight);

  new GLTFLoader(loadingManager).load(
    "assets/models/porsche/engine.glb",
    (gltf) => {
      const pivot = new THREE.Group().add(gltf.scene);
      engineScene.add(pivot);
      gltf.scene.scale.setScalar(0.1);
      gltf.scene.position.set(0, -0.2, 0);
      engineModel = pivot;
      animateEngine();
    },
  );
}

function animateEngine() {
  requestAnimationFrame(animateEngine);
  if (!engineActive) return;
  engineRenderer?.render(engineScene, engineCamera);
  
  if (engineModel) {
    engineModel.rotation.y += 0.003;
    const w = engineContainer.clientWidth,
          h = engineContainer.clientHeight,
          list = getCachedHotspots();
          
    list.forEach((hItem) => {
      _vTarget
        .copy(hItem.targetPos)
        .applyQuaternion(engineModel.quaternion)
        .add(engineModel.position)
        .project(engineCamera);
        
      const x = (_vTarget.x * 0.5 + 0.5) * w,
            y = (_vTarget.y * -0.5 + 0.5) * h;
            
      // 1. On applique le positionnement 3D
      hItem.element.style.transform = `translate3d(${x}px, ${y}px, 0px)`;
      
      // 2. CORRECTION : Si le point est bien dans l'écran, on force son affichage !
      if (x > 0 && y > 0 && x < w && y < h) {
        hItem.element.style.display = "block"; 
      } else {
        hItem.element.style.display = "none"; // Optionnel : masque si le point sort de l'écran
      }

      if (!hItem.initialized && hItem.line && hItem.textBlock) {
  hItem.line.setAttribute("width", "420");
  hItem.line.setAttribute("height", "150");
  
  // On réduit le décalage à droite de 400px à 250px pour qu'il reste visible à l'écran
  hItem.textBlock.style.transform = hItem.isLeft
    ? "translate3d(-660px, -60px, 0px)"
    : "translate3d(250px, -60px, 0px)"; 
    
  hItem.lineInner.setAttribute("x1", "4");
  hItem.lineInner.setAttribute("y1", "4");
  hItem.lineInner.setAttribute("x2", hItem.isLeft ? "-400" : "250");
  hItem.lineInner.setAttribute("y2", "-40");
  
  if (x > 0 && y > 0) hItem.initialized = true;
}
    });
  }
}

// --- NAVIGATION ET INTERACTION ---
async function changeVehicle(newIndex) {
  if (carData.length === 0) return;
  stopCurrentVehicleSound();
  AppState.currentIndex = newIndex;
  if (container) container.style.opacity = 0.1;

  const data = carData[AppState.currentIndex];
  const binds = {
    "car-title": data.title,
    "car-subtitle": data.subtitle,
    "car-badge": data.badge,
    "car-desc": data.desc,
  };
  Object.entries(binds).forEach(([id, val]) => {
    const el = document.getElementById(id);
    if (el) el.innerText = val;
  });
  const specsEl = document.getElementById("car-specs");
  if (specsEl) specsEl.innerHTML = data.specs;

  updateSidePanelContent(data);
  fetchCommentsForVehicle(data.id); // CORRECTION : Utilise le vrai ID unique de la BDD

  document
    .querySelectorAll(".dots-navigation .dot")
    .forEach((d, i) =>
      d.classList.toggle("active", i === AppState.currentIndex),
    );

  const nextGroup = await loadVehicleModel(AppState.currentIndex);
  if (AppState.currentActiveGroup) scene.remove(AppState.currentActiveGroup);
  scene.add((AppState.currentActiveGroup = nextGroup));

  controls.reset();
  camera.position.set(0, 0.3, 5.0);
  if (container) container.style.opacity = 1;
}

const observer = new IntersectionObserver(
  (entries) => entries.forEach((e) => (engineActive = e.isIntersecting)),
  { threshold: 0.1 },
);
const eCont =
  document.getElementById("engine-canvas-container") ||
  document.getElementById("engine-container");
if (eCont) observer.observe(eCont);

document
  .getElementById("arrow-next")
  ?.addEventListener(
    "click",
    () =>
      carData.length &&
      changeVehicle((AppState.currentIndex + 1) % carData.length),
  );
document
  .getElementById("arrow-prev")
  ?.addEventListener(
    "click",
    () =>
      carData.length &&
      changeVehicle(
        (AppState.currentIndex - 1 + carData.length) % carData.length,
      ),
  );
document
  .getElementById("play-sound-btn")
  ?.addEventListener("click", playVehicleSound);

// --- INTERFACES & MODALES PANELS ---
const sidePanel = document.getElementById("side-panel");
(
  document.getElementById("open-panel-btn") ||
  document.querySelector(".open-panel-btn")
)?.addEventListener("click", () => sidePanel?.classList.add("open"));
(
  document.getElementById("close-panel-btn") ||
  document.querySelector(".close-panel-btn")
)?.addEventListener("click", () => sidePanel?.classList.remove("open"));

const setupTabs = (btnsSelector, panesSelector) => {
  const btns = document.querySelectorAll(btnsSelector),
    panes = document.querySelectorAll(panesSelector);
  btns.forEach((b) =>
    b.addEventListener("click", () => {
      btns.forEach((x) => x.classList.remove("active"));
      panes.forEach((x) => x.classList.remove("active"));
      b.classList.add("active");
      document.getElementById(b.dataset.tab)?.classList.add("active");
    }),
  );
};
setupTabs(".tabs-navigation .tab-btn", ".side-panel-body .tab-pane");
setupTabs("#auth-modal .auth-tabs .tab-btn", "#auth-modal .auth-form-content");

const authModal = document.getElementById("auth-modal");
document.getElementById("profile-trigger")?.addEventListener("click", () => {
  authModal?.classList.add("active");
  fetchUserActivity();
});
document
  .getElementById("close-modal-btn")
  ?.addEventListener("click", () => authModal?.classList.remove("active"));
authModal?.addEventListener(
  "click",
  (e) => e.target === authModal && authModal.classList.remove("active"),
);

// --- MOT DE PASSE FORCE ---
const registerPasswordInput = document.getElementById("register-password");
const strengthBarFill = document.getElementById("strength-bar-fill"),
  strengthText = document.getElementById("strength-text");
registerPasswordInput?.addEventListener("input", () => {
  const p = registerPasswordInput.value,
    s = getPasswordStrength(p),
    c = document.getElementById("password-strength-container");
  if (!p) {
    if (c) c.style.display = "none";
    if (strengthBarFill) {
      strengthBarFill.style.width = "0%";
      strengthBarFill.style.backgroundColor = "transparent";
    }
    if (strengthText) strengthText.innerHTML = "";
  } else {
    if (c) c.style.display = "block";
    const cfg =
      s <= 2
        ? { w: "33%", c: "#ff4d4d", t: "Faible" }
        : s <= 4
          ? { w: "66%", c: "#ffaa00", t: "Moyenne" }
          : { w: "100%", c: "#00cc66", t: "Forte" };
    if (strengthBarFill) {
      strengthBarFill.style.width = cfg.w;
      strengthBarFill.style.backgroundColor = cfg.c;
    }
    if (strengthText)
      strengthText.innerHTML = `<span style="color:${cfg.c};">Sécurité : ${cfg.t}</span>`;
  }
});

function updateSidePanelContent(data) {
  const grid = document.getElementById("panel-specs-grid");
  if (grid && data?.techDetails) {
    grid.innerHTML = ["moteur", "vitesse", "puissance", "boite"]
      .map(
        (k) => `
      <div class="spec-item"><div><span>${k === "boite" ? "Boîte de vitesses" : k === "vitesse" ? "Vitesse max" : k.charAt(0).toUpperCase() + k.slice(1)}</span>
      <p>${escapeHtml(data.techDetails[k] || (k === "boite" ? "Séquentielle" : ""))}</p></div></div>`,
      )
      .join("");
  }
}

// --- AUTHENTIFICATION ---
document
  .getElementById("login-form")
  ?.addEventListener("submit", async function (e) {
    e.preventDefault();
    const em = this.querySelector('input[name="email"]')?.value.trim(),
      pw = this.querySelector('input[name="password"]')?.value;
    if (!em || !pw) return alert("Veuillez remplir tous les champs.");
    if (!isValidEmail(em)) return alert("Format d'email invalide.");

    const fd = addCsrfToken(new FormData(this));
    fd.append("action", "login");
    const d = await secureFetch("auth_process.php", {
      method: "POST",
      body: fd,
    });
    if (d.success) {
      AppState.isAuthenticated = true;
      AppState.csrfToken = d.csrf_token;
      if (document.getElementById("global-csrf-token"))
        document.getElementById("global-csrf-token").value = d.csrf_token;
      authModal?.classList.remove("active");
      if (d.role === "admin") {
        alert("Accès Admin. Redirection...");
        window.location.href = "admin/dashboard.php";
      } else {
        alert("Connexion réussie !");
        window.location.reload(true);
      }
    } else alert(d.message || "Identifiants incorrects.");
  });

document
  .getElementById("register-form")
  ?.addEventListener("submit", async function (e) {
    e.preventDefault();
    const pr = this.querySelector('input[name="prenom"]')?.value.trim(),
      nm = this.querySelector('input[name="nom"]')?.value.trim();
    const em = this.querySelector('input[name="email"]')?.value.trim(),
      pw = this.querySelector('input[name="password"]')?.value;
    if (!pr || !nm || !em || !pw) return alert("Tous les champs sont requis.");
    if (!isValidEmail(em)) return alert("Format d'email invalide.");
    if (pw.length < 8)
      return alert("Le mot de passe doit contenir au moins 8 caractères.");

    const fd = addCsrfToken(new FormData(this));
    fd.set("prenom", truncateText(pr, 50));
    fd.set("nom", truncateText(nm, 50));
    fd.append("action", "register");
    const d = await secureFetch("auth_process.php", {
      method: "POST",
      body: fd,
    });
    if (d.success) {
      if (d.csrf_token && document.getElementById("global-csrf-token"))
        document.getElementById("global-csrf-token").value =
          AppState.csrfToken = d.csrf_token;
      AppState.isAuthenticated = true;
      alert("Compte créé avec succès !");
      window.location.href = "index.php";
    } else alert(d.message || "Erreur lors de la création du compte.");
  });

document.getElementById("logout-btn")?.addEventListener("click", async () => {
  try {
    const fd = addCsrfToken(new FormData());
    fd.append("action", "logout");
    await secureFetch("api/main.php", { method: "POST", body: fd });
  } finally {
    AppState.isAuthenticated = false;
    AppState.csrfToken = null;
    window.location.reload(true);
  }
});

// --- IMPLÉMENTATION DES COMMMMENTAIRES ---
function fetchCommentsForVehicle(carDbId) {
  const lCont = document.getElementById("reviews-list-container"),
    sPrev = document.getElementById("reviews-side-preview");
  const dCont = document.getElementById("stars-distribution-container"),
    gStars = document.getElementById("global-stars-stars");
  const avgN = document.getElementById("average-rating-num"),
    totC = document.getElementById("total-reviews-count");

  secureFetch(`auth_process.php?action=get&vehicle_index=${carDbId}`).then((d) => {
    let tot = 0,
      avg = "0.0",
      counts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };
    if (d?.success && Array.isArray(d.comments) && d.comments.length > 0) {
      tot = d.comments.length;
      let sum = d.comments.reduce((acc, c) => {
        const r = parseFloat(c.note || c.rating || 0);
        counts[Math.min(5, Math.max(1, Math.round(r)))]++;
        return acc + r;
      }, 0);
      avg = (sum / tot).toFixed(1);
    }
    if (avgN) avgN.textContent = avg;
    if (totC) totC.textContent = tot;

    if (gStars) {
      const rAvg = Math.round(parseFloat(avg));
      gStars.innerHTML = Array.from(
        { length: 5 },
        (_, i) =>
          `<i class="${i < rAvg ? "fa-solid" : "fa-regular"} fa-star" style="margin-right:2px; color:${i < rAvg ? "#ffaa00" : "#555"};"></i>`,
      ).join("");
    }

    if (dCont) {
      dCont.innerHTML = [5, 4, 3, 2, 1]
        .map((i) => {
          const pct = tot > 0 ? Math.round((counts[i] / tot) * 100) : 0;
          return `<div class="distrib-row"><span class="distrib-label">${i} <i class="fa-solid fa-star" style="font-size:9px; color:#ffaa00;"></i></span>
                <div class="distrib-bar-bg"><div class="distrib-bar-fill" style="width: ${pct}%;"></div></div><span class="distrib-percent">${pct}%</span></div>`;
        })
        .join("");
    }

    if (!tot) {
      if (lCont)
        lCont.innerHTML = `<div style="text-align:center; padding:60px 10px; opacity:0.4;"><i class="fa-regular fa-comments" style="font-size:32px; margin-bottom:12px; display:block; color:#ff2828;"></i><p style="font-size:13px; margin:0; font-style:italic; color:#fff;">Il n'y a pas encore d'avis sur ce modèle</p></div>`;
      if (sPrev)
        sPrev.innerHTML =
          '<p style="opacity:0.4; font-size:12px; font-style:italic; margin:0; color:#fff;">Aucun avis pour le moment.</p>';
      return;
    }

    let lHtml = "",
      pHtml = "";
    d.comments.forEach((c, idx) => {
      const txt = escapeHtml(c.commentaire || c.comment || ""),
        pr = escapeHtml(c.prenom || "Utilisateur"),
        nm = c.nom ? escapeHtml(c.nom.charAt(0) + ".") : "";
      const auth = `${pr} ${nm}`.trim(),
        av = escapeUrl(c.avatar_url),
        r = Math.min(5, Math.max(0, parseInt(c.note || c.rating) || 0));
      const sHtml = Array.from(
        { length: 5 },
        (_, i) =>
          `<i class="${i < r ? "fa-solid" : "fa-regular"} fa-star" style="color:${i < r ? "#ffaa00" : "#555"};"></i>`,
      ).join("");

      const card = `<div class="review-card"><img src="${av}" class="review-avatar" alt="${auth}" loading="lazy"><div class="review-content"><div class="review-header"><span class="review-author">${auth}</span><span class="review-stars">${sHtml}</span></div><p class="review-text">${txt}</p></div></div>`;
      lHtml += card;
      if (idx < 2)
        pHtml += `<div class="preview-review-card"><div class="preview-review-header"><span class="preview-review-author">${auth}</span><span class="preview-review-stars">${sHtml}</span></div><p class="preview-review-text">${txt}</p></div>`;
    });
    if (lCont) lCont.innerHTML = lHtml;
    if (sPrev) sPrev.innerHTML = pHtml;
  });
}

// --- SOUMISSION DE L'AVIS ---
const reviewForm = document.getElementById("leave-review-form");
reviewForm?.addEventListener("submit", async function (e) {
  e.preventDefault();
  e.stopImmediatePropagation();
  const btn = this.querySelector(".btn-submit-review"),
    orig = btn ? btn.innerHTML : "Soumettre";
  if (btn && btn.disabled) return;
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = 'Envoi... <i class="fa-solid fa-spinner fa-spin"></i>';
  }

  const fd = new FormData(this);
  fd.set("action", "add");
  const cur = carData[AppState.currentIndex];
  if (cur?.id) fd.set("vehicle_index", cur.id); // CORRECTION : Utilise l'ID BDD au lieu du simple index

  addCsrfToken(fd);
  try {
    const d = await secureFetch("auth_process.php", {
      method: "POST",
      body: fd,
    });
    if (d.success) {
      alert(d.message || "Avis publié !");
      this.reset();
      fetchCommentsForVehicle(cur.id);
      if (typeof fetchUserActivity === "function") fetchUserActivity();
    } else alert(d.message || "Une erreur est survenue.");
  } catch {
    alert("Impossible de joindre le serveur.");
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = orig;
    }
  }
});

window.addEventListener("resize", () => {
  if (container && renderer) {
    renderer.setSize(container.clientWidth, container.clientHeight);
    camera.aspect = container.clientWidth / container.clientHeight;
    camera.updateProjectionMatrix();
  }
  if (engineContainer && engineRenderer) {
    engineRenderer.setSize(
      engineContainer.clientWidth,
      engineContainer.clientHeight,
    );
    engineCamera.aspect =
      engineContainer.clientWidth / engineContainer.clientHeight;
    engineCamera.updateProjectionMatrix();
  }
});

function animate() {
  requestAnimationFrame(animate);
  controls?.update();
  renderer?.render(scene, camera);
}

async function initApp() {
  if (
    document.body.dataset.authenticated === "true" ||
    !document.getElementById("profile-trigger")
  )
    AppState.isAuthenticated = true;
  if (!AppState.csrfToken)
    AppState.csrfToken =
      document.getElementById("global-csrf-token")?.value || null;

  try {
    const res = await fetch("api/get_vehicules.php");
    const r = await res.json();
    if (r.success && r.cars?.length > 0) {
      carData = r.cars.map((c) => ({
        id: c.id,
        model: c.glb_url,
        title: c.modele,
        subtitle: `${c.moteur} | ${c.annee}`,
        badge: parseInt(c.puissance_ch) >= 900 ? "Supercar" : "Série Spéciale",
        specs: `<p><strong>Moteur :</strong> ${c.moteur}</p><p><strong>Puissance :</strong> ${c.puissance_ch} ch</p><p><strong>Vitesse Max :</strong> ${c.vitesse_max} km/h</p>`,
        desc: c.description || "Aucune description disponible.",
        sound: c.sound_url || "assets/sounds/ferrariEngine.wav",
        techDetails: {
          moteur: c.moteur,
          vitesse: `${c.vitesse_max} km/h`,
          puissance: `${c.puissance_ch} ch`,
          boite: "Séquentielle F1",
          transmission: "Propulsion",
        },
      }));
      await changeVehicle(0);
    }
  } catch (e) {
    console.error(e);
  }
  animate();
}

window.changeVehicle = changeVehicle;
window.getCarDataLength = () => carData.length;
if (document.readyState === "loading")
  document.addEventListener("DOMContentLoaded", initApp);
else initApp();
