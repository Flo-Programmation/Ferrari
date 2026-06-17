import * as THREE from "three";
import { GLTFLoader } from "three/addons/loaders/GLTFLoader.js";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";

let engineActive = true;

// Variable globale contenant les données dynamiques synchronisées avec la BDD
let carData = [];

const AppState = {
  currentIndex: 0,
  loadedGroups: new Map(),
  currentAudio: null,
  currentActiveGroup: null,
  csrfToken: document.getElementById("global-csrf-token")?.value || null,
  isAuthenticated: false,
};

// --- CONFIGURATION DU LOADING MANAGER ---
const loadingManager = new THREE.LoadingManager();

// Dès que Three.js a fini de charger TOUTES les ressources initiales (3D, textures, etc.)
loadingManager.onLoad = function () {
  const loaderElement = document.getElementById("site-loader");
  if (loaderElement) {
    loaderElement.classList.add("loader-hidden");
  }
};

// --- OPTIMISATION : MISE EN CACHE DES ÉLÉMENTS DU DOM POUR LES HOTSPOTS ---
let cachedHotspots = null;
function getCachedHotspots() {
  if (cachedHotspots) return cachedHotspots;

  cachedHotspots = hotspotsData
    .map((hotspot) => {
      const el = document.getElementById(hotspot.id);
      if (!el) return null;
      return {
        ...hotspot,
        element: el,
        line: el.querySelector(".hotspot-line"),
        lineInner: el.querySelector(".hotspot-line line"),
        textBlock: el.querySelector(".hotspot-text"),
        isLeft: el.classList.contains("hotspot-left"),
      };
    })
    .filter((h) => h !== null);

  return cachedHotspots;
}

// Réutilisation d'un vecteur unique pour éviter les allocations mémoire dans la boucle (Garbage Collector)
const _vTarget = new THREE.Vector3();

function escapeHtml(text) {
  if (!text) return "";
  return text
    .toString()
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;")
    .replace(/\//g, "&#x2F;");
}

function escapeUrl(url) {
  if (!url) return "https://cdn-icons-png.flaticon.com/512/3135/3135715.png";
  const allowed = ["https://", "http://", "data:", "blob:"];
  const sanitized = escapeHtml(url.trim());
  for (const protocol of allowed) {
    if (sanitized.toLowerCase().startsWith(protocol)) return sanitized;
  }
  return "https://cdn-icons-png.flaticon.com/512/3135/3135715.png";
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());
}

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

function truncateText(text, maxLength) {
  if (!text) return "";
  text = text.trim();
  return text.length > maxLength ? text.substring(0, maxLength) : text;
}

async function secureFetch(url, options = {}) {
  try {
    const response = await fetch(url, options);
    if (!response.ok) throw new Error(`Erreur HTTP: ${response.status}`);
    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
      throw new Error("Réponse non-JSON reçue");
    }
    return await response.json();
  } catch (error) {
    console.error("Erreur réseau:", error);
    return { success: false, message: "Erreur de connexion au serveur." };
  }
}

async function fetchCsrfToken() {
  const data = await secureFetch("api/main.php?action=csrf");
  if (data.success && data.csrf_token) {
    AppState.csrfToken = data.csrf_token;
    const inputHtmlToken = document.getElementById("global-csrf-token");
    if (inputHtmlToken) inputHtmlToken.value = data.csrf_token;
    return true;
  }
  return false;
}

function addCsrfToken(formData) {
  if (!AppState.csrfToken) {
    AppState.csrfToken =
      document.getElementById("global-csrf-token")?.value || null;
  }
  if (AppState.csrfToken) {
    formData.append("csrf_token", AppState.csrfToken);
  }
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
  if (carData.length === 0 || !carData[AppState.currentIndex]) return;
  stopCurrentVehicleSound();
  const soundUrl = carData[AppState.currentIndex].sound;
  if (!soundUrl) return;

  const audio = new Audio(soundUrl);
  audio.volume = 0.2;
  audio.play().catch((err) => console.warn("Lecture audio impossible:", err));
  AppState.currentAudio = audio;
}

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
  renderer.toneMappingExposure = 1.0;
  container.appendChild(renderer.domElement);
}

const pmremGenerator = new THREE.PMREMGenerator(renderer);
pmremGenerator.compileEquirectangularShader();
const neutralEnv = pmremGenerator.fromScene(new THREE.Scene()).texture;
scene.environment = neutralEnv;
pmremGenerator.dispose();

scene.add(new THREE.AmbientLight(0xffffff, 0.6));

const lightTop = new THREE.DirectionalLight(0xffffff, 3.0);
lightTop.position.set(2, 8, 4);
scene.add(lightTop);

const lightFill = new THREE.DirectionalLight(0xffffff, 1.2);
lightFill.position.set(-4, 3, -2);
scene.add(lightFill);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.05;
controls.enablePan = false;
controls.minDistance = 3.5;
controls.maxDistance = 6.5;
controls.minPolarAngle = Math.PI / 4;
controls.maxPolarAngle = Math.PI / 2.1;

const loader = new GLTFLoader(loadingManager);

function loadVehicleModel(index) {
  if (AppState.loadedGroups.has(index)) {
    return Promise.resolve(AppState.loadedGroups.get(index));
  }
  return new Promise((resolve) => {
    if (!carData[index] || !carData[index].model) {
      resolve(new THREE.Group());
      return;
    }
    loader.load(
      carData[index].model,
      (gltf) => {
        const model = gltf.scene;

        model.traverse((child) => {
          if (child.isMesh) {
            child.castShadow = true;
            child.receiveShadow = true;
            if (child.material) {
              child.material.envMapIntensity = 1.5;
              child.material.needsUpdate = true;
            }
          }
        });

        const group = new THREE.Group();
        group.add(model);

        const box = new THREE.Box3().setFromObject(model);
        const size = new THREE.Vector3();
        box.getSize(size);
        const maxDim = Math.max(size.x, size.y, size.z);
        model.scale.setScalar(3.3 / maxDim);

        if (carData[index].model.includes("599obj.glb")) {
          model.rotation.x = -Math.PI / 2;
        }

        const correctedBox = new THREE.Box3().setFromObject(model);
        const center = correctedBox.getCenter(new THREE.Vector3());
        model.position.set(-center.x, -center.y + 0.1, -center.z);

        AppState.loadedGroups.set(index, group);
        resolve(group);
      },
      undefined,
      (error) => {
        console.error("Erreur lors du chargement du fichier 3D GLB:", error);
        resolve(new THREE.Group());
      },
    );
  });
}

// --- CONFIGURATION DU MOTEUR INTERACTIF ---
const engineContainer = document.getElementById("engine-canvas-container");
let engineScene, engineCamera, engineRenderer, engineModel;

const hotspotsData = [
  { id: "hotspot-culasse", targetPos: new THREE.Vector3(0.0, 0.4, 0.2) },
  { id: "hotspot-injection", targetPos: new THREE.Vector3(-0.3, 0.1, -0.2) },
  { id: "hotspot-bloc", targetPos: new THREE.Vector3(0.1, -0.2, 0.3) },
  { id: "hotspot-echappement", targetPos: new THREE.Vector3(0.4, -0.4, -0.1) },
];

if (engineContainer) {
  initEngineShowroom();
}

function initEngineShowroom() {
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

  const gltfLoader = new GLTFLoader(loadingManager);
  gltfLoader.load(
    "assets/models/porsche/engine.glb",
    (gltf) => {
      engineModel = gltf.scene;

      const pivotGroup = new THREE.Group();
      engineScene.add(pivotGroup);
      pivotGroup.add(engineModel);

      engineModel.scale.setScalar(0.1);
      engineModel.position.set(0.0, -0.2, 0.0);

      engineModel = pivotGroup;
      engineCamera.lookAt(0, 0, 0);

      animateEngine();
    },
    undefined,
    (err) => console.error("Erreur chargement moteur 3D :", err),
  );
}

function animateEngine() {
  requestAnimationFrame(animateEngine);

  if (!engineActive) return;

  if (engineRenderer && engineScene && engineCamera) {
    engineRenderer.render(engineScene, engineCamera);
  }

  if (engineModel) {
    engineModel.rotation.y += 0.003;

    const width = engineContainer.clientWidth;
    const height = engineContainer.clientHeight;
    const list = getCachedHotspots();

    for (let i = 0; i < list.length; i++) {
      const h = list[i];

      _vTarget.copy(h.targetPos);
      _vTarget.applyQuaternion(engineModel.quaternion);
      _vTarget.add(engineModel.position);
      _vTarget.project(engineCamera);

      const x = (_vTarget.x * 0.5 + 0.5) * width;
      const y = (_vTarget.y * -0.5 + 0.5) * height;

      h.element.style.display = "block";
      h.element.style.transform = `translate3d(${x}px, ${y}px, 0px)`;

      if (h.line && h.textBlock) {
        const lineLength = 400;

        if (!h.initialized) {
          h.line.setAttribute("width", (lineLength + 20).toString());
          h.line.setAttribute("height", "150");

          if (h.isLeft) {
            h.textBlock.style.transform = `translate3d(${-lineLength - 260}px, -60px, 0px)`;
            h.lineInner.setAttribute("x1", "4");
            h.lineInner.setAttribute("y1", "4");
            h.lineInner.setAttribute("x2", `${-lineLength}`);
            h.lineInner.setAttribute("y2", "-40");
          } else {
            h.textBlock.style.transform = `translate3d(${lineLength}px, -60px, 0px)`;
            h.lineInner.setAttribute("x1", "4");
            h.lineInner.setAttribute("y1", "4");
            h.lineInner.setAttribute("x2", `${lineLength}`);
            h.lineInner.setAttribute("y2", "-40");
          }

          if (x > 0 && y > 0) {
            h.initialized = true;
          }
        }
      }
    }
  }
}

window.addEventListener("resize", () => {
  if (engineContainer && engineRenderer) {
    const w = engineContainer.clientWidth;
    const h = engineContainer.clientHeight;
    engineRenderer.setSize(w, h);
    engineCamera.aspect = w / h;
    engineCamera.updateProjectionMatrix();
  }
});

async function changeVehicle(newIndex) {
  if (carData.length === 0) return;
  stopCurrentVehicleSound();
  AppState.currentIndex = newIndex;
  if (container) container.style.opacity = 0.1;

  const data = carData[AppState.currentIndex];

  const titleEl = document.getElementById("car-title");
  const subtitleEl = document.getElementById("car-subtitle");
  const badgeEl = document.getElementById("car-badge");
  const specsEl = document.getElementById("car-specs");
  const descEl = document.getElementById("car-desc");

  if (titleEl) titleEl.innerText = data.title;
  if (subtitleEl) subtitleEl.innerText = data.subtitle;
  if (badgeEl) badgeEl.innerText = data.badge;
  if (specsEl) specsEl.innerHTML = data.specs;
  if (descEl) descEl.innerText = data.desc;

  updateSidePanelContent(data);
  fetchCommentsForVehicle(data.id);

  const dots = document.querySelectorAll(".dots-navigation .dot");
  for (let i = 0; i < dots.length; i++) {
    dots[i].classList.toggle("active", i === AppState.currentIndex);
  }

  const nextGroup = await loadVehicleModel(AppState.currentIndex);
  if (AppState.currentActiveGroup) scene.remove(AppState.currentActiveGroup);
  AppState.currentActiveGroup = nextGroup;
  scene.add(AppState.currentActiveGroup);

  controls.reset();
  camera.position.set(0, 0.3, 5.0);
  if (container) container.style.opacity = 1;
}

const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      engineActive = entry.isIntersecting;
    });
  },
  { threshold: 0.1 },
);

const engineContainerElement =
  document.getElementById("engine-canvas-container") ||
  document.getElementById("engine-container");
if (engineContainerElement) {
  observer.observe(engineContainerElement);
} else {
  engineActive = true;
}

const arrowNext = document.getElementById("arrow-next");
const arrowPrev = document.getElementById("arrow-prev");
if (arrowNext)
  arrowNext.addEventListener("click", () => {
    if (carData.length > 0)
      changeVehicle((AppState.currentIndex + 1) % carData.length);
  });
if (arrowPrev)
  arrowPrev.addEventListener("click", () => {
    if (carData.length > 0)
      changeVehicle(
        (AppState.currentIndex - 1 + carData.length) % carData.length,
      );
  });

function fetchUserActivity() {
  const activityContainer = document.getElementById("user-reviews-container");
  if (!activityContainer) return;

  if (!AppState.isAuthenticated) {
    activityContainer.innerHTML =
      '<p style="opacity:0.5; font-size:12px; font-style:italic; text-align:center; margin:0; color:#fff;">Connectez-vous pour voir votre activité.</p>';
    return;
  }

  secureFetch("auth_process.php?action=getUserComments")
    .then((data) => {
      if (
        data &&
        data.success &&
        data.my_comments &&
        data.my_comments.length > 0
      ) {
        let html =
          '<div class="user-activity-list" style="padding-right: 5px;">';
        data.my_comments.forEach((c) => {
          let stars = "★".repeat(c.rating) + "☆".repeat(5 - c.rating);
          const matchingCar = carData.find((car) => car.id == c.voiture_id);
          const carName = matchingCar
            ? matchingCar.title
            : `Véhicule #${c.voiture_id}`;

          html += `
                        <div class="user-activity-item" id="user-comment-row-${c.id}" style="border-bottom: 1px solid #222; padding: 12px 0; font-size: 13px;">
                            <div style="display:flex; justify-content:space-between; color:#ff2828; font-weight:bold;">
                                <span>${escapeHtml(carName)}</span>
                                <span style="color:#ffaa00;">${stars}</span>
                            </div>
                            <p class="comment-text-content" style="margin: 6px 0; color:#ccc; font-style:italic;">"${escapeHtml(c.comment)}"</p>
                            <div style="display:flex; gap:15px; margin-top:5px; font-size:11px;">
                                <span class="btn-edit-my-comment" data-id="${c.id}" data-text="${escapeHtml(c.comment)}" data-rating="${c.rating}" style="color:#ffaa00; cursor:pointer; text-decoration:underline;"><i class="fa-solid fa-pen"></i> Modifier</span>
                                <span class="btn-delete-my-comment" data-id="${c.id}" style="color:#ff2828; cursor:pointer; text-decoration:underline;"><i class="fa-solid fa-trash"></i> Supprimer</span>
                            </div>
                        </div>`;
        });
        html += "</div>";
        activityContainer.innerHTML = html;

        activityContainer
          .querySelectorAll(".btn-delete-my-comment")
          .forEach((btn) => {
            btn.addEventListener("click", function () {
              const id = this.dataset.id;
              if (confirm("Voulez-vous vraiment supprimer cet avis ?")) {
                const formData = new FormData();
                formData.append("action", "delete");
                formData.append("comment_id", id);
                addCsrfToken(formData);

                secureFetch("auth_process.php", {
                  method: "POST",
                  body: formData,
                }).then((res) => {
                  if (res.success) {
                    alert(res.message);
                    fetchUserActivity();
                    if (carData[AppState.currentIndex]) {
                      fetchCommentsForVehicle(
                        carData[AppState.currentIndex].id,
                      );
                    }
                  } else {
                    alert(res.message);
                  }
                });
              }
            });
          });

        activityContainer
          .querySelectorAll(".btn-edit-my-comment")
          .forEach((btn) => {
            btn.addEventListener("click", function () {
              const id = this.dataset.id;
              const oldText = this.dataset.text;
              const oldRating = this.dataset.rating;

              const newText = prompt("Modifiez votre commentaire :", oldText);
              if (newText === null) return;
              if (newText.trim() === "") {
                alert("Le commentaire ne peut pas être vide.");
                return;
              }

              const newRatingStr = prompt("Nouvelle note (1 à 5) :", oldRating);
              if (newRatingStr === null) return;

              const newRating = Math.min(
                5,
                Math.max(1, parseInt(newRatingStr) || 5),
              );

              const formData = new FormData();
              formData.append("action", "edit");
              formData.append("comment_id", id);
              formData.append("comment", newText.trim());
              formData.append("rating", newRating);
              addCsrfToken(formData);

              secureFetch("auth_process.php", {
                method: "POST",
                body: formData,
              }).then((res) => {
                if (res.success) {
                  alert(res.message);
                  fetchUserActivity();
                  if (carData[AppState.currentIndex]) {
                    fetchCommentsForVehicle(carData[AppState.currentIndex].id);
                  }
                } else {
                  alert(res.message);
                }
              });
            });
          });
      } else {
        activityContainer.innerHTML =
          '<p style="opacity:0.4; font-size:12px; font-style:italic; text-align:center; margin:0; color:#fff;">Vous n\'avez publié aucun avis pour le moment.</p>';
      }
    })
    .catch((err) => {
      console.error("Erreur activité:", err);
      activityContainer.innerHTML =
        '<p style="color:#ff2828; font-size:12px; text-align:center; margin:0;">Impossible de charger votre activité.</p>';
    });
}

function fetchCommentsForVehicle(carDbId) {
  const listContainer = document.getElementById("reviews-list-container");
  const sidePreview = document.getElementById("reviews-side-preview");
  const distribContainer = document.getElementById(
    "stars-distribution-container",
  );
  const globalStarsContainer = document.getElementById("global-stars-stars");
  const avgNumSpan = document.getElementById("average-rating-num");
  const totalCountSpan = document.getElementById("total-reviews-count");

  secureFetch(`auth_process.php?action=get&voiture_id=${carDbId}`)
    .then((data) => {
      let totalComments = 0;
      let averageRating = 0.0;
      let starCounts = { 5: 0, 4: 0, 3: 0, 2: 0, 1: 0 };

      if (
        data &&
        data.success &&
        data.comments &&
        Array.isArray(data.comments) &&
        data.comments.length > 0
      ) {
        totalComments = data.comments.length;
        let sum = 0;

        data.comments.forEach((c) => {
          const rawRating = parseFloat(c.note || c.rating || 0);
          sum += rawRating;

          const bucket = Math.min(5, Math.max(1, Math.round(rawRating)));
          if (starCounts[bucket] !== undefined) {
            starCounts[bucket]++;
          }
        });

        averageRating = (sum / totalComments).toFixed(1);
      }

      if (avgNumSpan)
        avgNumSpan.textContent = totalComments > 0 ? averageRating : "0.0";
      if (totalCountSpan) totalCountSpan.textContent = totalComments;

      if (globalStarsContainer) {
        let gStarsHTML = "";
        const roundedAvg = Math.round(parseFloat(averageRating));
        for (let i = 1; i <= 5; i++) {
          gStarsHTML +=
            i <= roundedAvg
              ? '<i class="fa-solid fa-star" style="margin-right:2px; color:#ffaa00;"></i>'
              : '<i class="fa-regular fa-star" style="margin-right:2px; color:#555;"></i>';
        }
        globalStarsContainer.innerHTML = gStarsHTML;
      }

      if (distribContainer) {
        let distribHTML = "";
        for (let i = 5; i >= 1; i--) {
          const count = starCounts[i];
          const pct =
            totalComments > 0 ? Math.round((count / totalComments) * 100) : 0;
          distribHTML += `
                        <div class="distrib-row">
                            <span class="distrib-label">${i} <i class="fa-solid fa-star" style="font-size:9px; color:#ffaa00;"></i></span>
                            <div class="distrib-bar-bg">
                                <div class="distrib-bar-fill" style="width: ${pct}%;"></div>
                            </div>
                            <span class="distrib-percent">${pct}%</span>
                        </div>`;
        }
        distribContainer.innerHTML = distribHTML;
      }

      if (
        !data ||
        !data.success ||
        !data.comments ||
        data.comments.length === 0
      ) {
        const emptyMessage = `
                    <div style="text-align:center; padding:60px 10px; opacity:0.4;">
                        <i class="fa-regular fa-comments" style="font-size:32px; margin-bottom:12px; display:block; color:#ff2828;"></i>
                        <p style="font-size:13px; margin:0; font-style:italic; color:#fff;">il n'y a pas encore d'avis sur ce modèle</p>
                    </div>`;
        if (listContainer) listContainer.innerHTML = emptyMessage;
        if (sidePreview)
          sidePreview.innerHTML =
            '<p style="opacity:0.4; font-size:12px; font-style:italic; margin:0; color:#fff;">Aucun avis pour le moment.</p>';
        return;
      }

      let listHTML = "";
      let previewHTML = "";

      data.comments.forEach((comment, index) => {
        const commentText = escapeHtml(
          comment.commentaire || comment.comment || "",
        );
        const prenom = escapeHtml(comment.prenom || "Utilisateur");
        const nom = comment.nom ? escapeHtml(comment.nom.charAt(0) + ".") : "";
        const avatarUrl = escapeUrl(comment.avatar_url);
        const authorName = `${prenom} ${nom}`.trim();

        let starsHTML = "";
        const ratingVal = Math.min(
          5,
          Math.max(0, parseInt(comment.note || comment.rating) || 0),
        );
        for (let i = 1; i <= 5; i++) {
          starsHTML +=
            i <= ratingVal
              ? '<i class="fa-solid fa-star" style="color:#ffaa00;"></i>'
              : '<i class="fa-regular fa-star" style="color:#555;"></i>';
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
    .catch((err) =>
      console.error("Erreur lors de la récupération des avis :", err),
    );
}

const leaveReviewForm = document.getElementById("leave-review-form");
if (leaveReviewForm) {
  leaveReviewForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    if (!AppState.isAuthenticated) {
      alert("Vous devez être connecté pour laisser un avis.");
      if (typeof ouvrirModalConnexion === "function") ouvrirModalConnexion();
      return;
    }

    if (!AppState.csrfToken) {
      const loaded = await fetchCsrfToken();
      if (!loaded) {
        alert("Erreur de sécurité. Jeton CSRF introuvable, rechargez la page.");
        return;
      }
    }

    const checkedStarInput = leaveReviewForm.querySelector(
      'input[name="stars-qty"]:checked, input[name="rating"]:checked',
    );
    const commentTextarea = document.getElementById("review-textarea");
    if (!commentTextarea) return;

    const commentText = commentTextarea.value.trim();
    if (commentText.length === 0) {
      alert("Veuillez écrire un commentaire.");
      commentTextarea.focus();
      return;
    }

    if (commentText.length > 2000) {
      alert("Le commentaire est trop long. Maximum 2000 caractères.");
      commentTextarea.focus();
      return;
    }

    const safeComment = truncateText(commentText, 2000);
    const rating = checkedStarInput
      ? Math.min(5, Math.max(1, parseInt(checkedStarInput.value) || 5))
      : 5;

    if (!carData[AppState.currentIndex]) return;

    const formData = new FormData();
    formData.append("action", "add");
    formData.append("voiture_id", carData[AppState.currentIndex].id);
    formData.append("rating", rating);
    formData.append("comment", safeComment);
    addCsrfToken(formData);

    try {
      const data = await secureFetch("auth_process.php", {
        method: "POST",
        body: formData,
      });

      if (data.success) {
        commentTextarea.value = "";
        if (checkedStarInput) checkedStarInput.checked = false;
        fetchCommentsForVehicle(carData[AppState.currentIndex].id);
        fetchUserActivity();
        alert(data.message || "Votre avis a été enregistré !");
      } else {
        alert(data.message || "Erreur lors de l'envoi de l'avis.");
        if (data.message && data.message.toLowerCase().includes("csrf")) {
          window.location.reload();
        }
      }
    } catch (err) {
      console.error("Erreur:", err);
      alert("Impossible de joindre le serveur.");
    }
  });
}

const sidePanel = document.getElementById("side-panel");
const openPanelBtn =
  document.getElementById("open-panel-btn") ||
  document.querySelector(".open-panel-btn");
const closePanelBtn =
  document.getElementById("close-panel-btn") ||
  document.querySelector(".close-panel-btn");
const tabBtns = document.querySelectorAll(".tabs-navigation .tab-btn");
const tabContents = document.querySelectorAll(".side-panel-body .tab-pane");

if (openPanelBtn && sidePanel)
  openPanelBtn.addEventListener("click", () => sidePanel.classList.add("open"));
if (closePanelBtn && sidePanel)
  closePanelBtn.addEventListener("click", () =>
    sidePanel.classList.remove("open"),
  );

tabBtns.forEach((btn) => {
  btn.addEventListener("click", () => {
    tabBtns.forEach((b) => b.classList.remove("active"));
    tabContents.forEach((c) => c.classList.remove("active"));
    btn.classList.add("active");
    const targetPane = document.getElementById(btn.dataset.tab);
    if (targetPane) targetPane.classList.add("active");
  });
});

const authModal = document.getElementById("auth-modal");
const profileTrigger = document.getElementById("profile-trigger");
const closeModalBtn = document.getElementById("close-modal-btn");

function ouvrirModalConnexion() {
  if (authModal) authModal.classList.add("active");
}

if (profileTrigger && authModal) {
  profileTrigger.addEventListener("click", () => {
    authModal.classList.add("active");
    fetchUserActivity();
  });
}

if (closeModalBtn && authModal)
  closeModalBtn.addEventListener("click", () =>
    authModal.classList.remove("active"),
  );
if (authModal) {
  authModal.addEventListener("click", (e) => {
    if (e.target === authModal) authModal.classList.remove("active");
  });
}

const authFormTabs = document.querySelectorAll(
  "#auth-modal .auth-tabs .tab-btn",
);
const authFormContents = document.querySelectorAll(
  "#auth-modal .auth-form-content",
);

authFormTabs.forEach((tab) => {
  tab.addEventListener("click", () => {
    authFormTabs.forEach((t) => t.classList.remove("active"));
    authFormContents.forEach((c) => c.classList.remove("active"));
    tab.classList.add("active");
    const targetForm = document.getElementById(tab.dataset.tab);
    if (targetForm) targetForm.classList.add("active");
  });
});

const registerPasswordInput = document.getElementById("register-password");
const strengthContainer = document.getElementById(
  "password-strength-container",
);
const strengthBarFill = document.getElementById("strength-bar-fill");
const strengthText = document.getElementById("strength-text");

if (registerPasswordInput) {
  registerPasswordInput.addEventListener("input", () => {
    const password = registerPasswordInput.value;
    const score = getPasswordStrength(password);

    if (password.length === 0) {
      if (strengthContainer) strengthContainer.style.display = "none";
      if (strengthBarFill) {
        strengthBarFill.style.width = "0%";
        strengthBarFill.style.backgroundColor = "transparent";
      }
      if (strengthText) strengthText.innerHTML = "";
    } else {
      if (strengthContainer) strengthContainer.style.display = "block";
      if (score <= 2) {
        if (strengthBarFill) {
          strengthBarFill.style.width = "33%";
          strengthBarFill.style.backgroundColor = "#ff4d4d";
        }
        if (strengthText)
          strengthText.innerHTML =
            '<span style="color:#ff4d4d;">Sécurité : Faible</span>';
      } else if (score <= 4) {
        if (strengthBarFill) {
          strengthBarFill.style.width = "66%";
          strengthBarFill.style.backgroundColor = "#ffaa00";
        }
        if (strengthText)
          strengthText.innerHTML =
            '<span style="color:#ffaa00;">Sécurité : Moyenne</span>';
      } else {
        if (strengthBarFill) {
          strengthBarFill.style.width = "100%";
          strengthBarFill.style.backgroundColor = "#00cc66";
        }
        if (strengthText)
          strengthText.innerHTML =
            '<span style="color:#00cc66;">Sécurité : Forte</span>';
      }
    }
  });
}

function updateSidePanelContent(data) {
  const specsGrid = document.getElementById("panel-specs-grid");
  if (specsGrid && data && data.techDetails) {
    specsGrid.innerHTML = `
            <div class="spec-item">
                <div><span>Moteur</span><p>${escapeHtml(data.techDetails.moteur || "")}</p></div>
            </div>
            <div class="spec-item">
                <div><span>Vitesse max</span><p>${escapeHtml(data.techDetails.vitesse || "")}</p></div>
            </div>
            <div class="spec-item">
                <div><span>Puissance</span><p>${escapeHtml(data.techDetails.puissance || "")}</p></div>
            </div>
            <div class="spec-item">
                <div><span>Boîte de vitesses</span><p>${escapeHtml(data.techDetails.boite || "Séquentielle")}</p></div>
            </div>`;
  }
}

const loginForm = document.getElementById("login-form");
if (loginForm) {
  loginForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    const emailInput = loginForm.querySelector('input[name="email"]');
    const passwordInput = loginForm.querySelector('input[name="password"]');

    const email = emailInput ? emailInput.value.trim() : "";
    const password = passwordInput ? passwordInput.value : "";

    if (!email || !password) {
      alert("Veuillez remplir tous les champs.");
      return;
    }

    if (!isValidEmail(email)) {
      alert("Format d'email invalide.");
      return;
    }

    const formData = new FormData(loginForm);
    formData.append("action", "login");
    addCsrfToken(formData);

    try {
      const data = await secureFetch("auth_process.php", {
        method: "POST",
        body: formData,
      });

      if (data.success) {
        AppState.isAuthenticated = true;
        AppState.csrfToken = data.csrf_token;

        const inputHtmlToken = document.getElementById("global-csrf-token");
        if (inputHtmlToken) inputHtmlToken.value = data.csrf_token;

        if (authModal) authModal.classList.remove("active");

        if (data.role === "admin") {
          alert("Accès Administrateur validé. Redirection...");
          window.location.href = "admin/dashboard.php";
        } else {
          alert("Connexion réussie ! Bienvenue sur l'exposition.");
          window.location.reload(true);
        }
      } else {
        alert(data.message || "Identifiants incorrects.");
      }
    } catch (err) {
      console.error("Erreur:", err);
      alert("Erreur de connexion au serveur.");
    }
  });
}

const registerForm = document.getElementById("register-form");
if (registerForm) {
  registerForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    const prenomInput = registerForm.querySelector('input[name="prenom"]');
    const nomInput = registerForm.querySelector('input[name="nom"]');
    const emailInput = registerForm.querySelector('input[name="email"]');
    const passwordInput = registerForm.querySelector('input[name="password"]');

    const prenom = prenomInput ? prenomInput.value.trim() : "";
    const nom = nomInput ? nomInput.value.trim() : "";
    const email = emailInput ? emailInput.value.trim() : "";
    const password = passwordInput ? passwordInput.value : "";

    if (!prenom || !nom || !email || !password) {
      alert("Tous les champs sont requis.");
      return;
    }

    if (!isValidEmail(email)) {
      alert("Format d'email invalide.");
      return;
    }

    if (password.length < 8) {
      alert("Le mot de passe doit contenir au moins 8 caractères.");
      return;
    }

    const safePrenom = truncateText(prenom, 50);
    const safeNom = truncateText(nom, 50);

    const formData = new FormData(registerForm);
    formData.set("prenom", safePrenom);
    formData.set("nom", safeNom);
    formData.append("action", "register");
    addCsrfToken(formData);

    try {
      const data = await secureFetch("auth_process.php", {
        method: "POST",
        body: formData,
      });

      if (data.success) {
        if (data.csrf_token) {
          AppState.csrfToken = data.csrf_token;
          const inputHtmlToken = document.getElementById("global-csrf-token");
          if (inputHtmlToken) inputHtmlToken.value = data.csrf_token;
        }
        AppState.isAuthenticated = true;
        alert("Votre compte a été créé avec succès ! Bienvenue.");
        window.location.href = "index.php";
      } else {
        alert(data.message || "Erreur lors de la création du compte.");
      }
    } catch (err) {
      console.error("Erreur:", err);
      alert("Erreur de connexion au serveur.");
    }
  });
}

const logoutBtn = document.getElementById("logout-btn");
if (logoutBtn) {
  logoutBtn.addEventListener("click", async function () {
    const formData = new FormData();
    formData.append("action", "logout");
    addCsrfToken(formData);

    try {
      await secureFetch("api/main.php", {
        method: "POST",
        body: formData,
      });
      AppState.isAuthenticated = false;
      AppState.csrfToken = null;
      window.location.reload(true);
    } catch (err) {
      console.error("Erreur:", err);
      window.location.reload(true);
    }
  });
}

const playSoundBtn = document.getElementById("play-sound-btn");
if (playSoundBtn) {
  playSoundBtn.addEventListener("click", playVehicleSound);
}

window.addEventListener("resize", () => {
  if (container && renderer) {
    const width = container.clientWidth;
    const height = container.clientHeight;
    renderer.setSize(width, height);
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
  }
});

// --- BOUCLE D'ANIMATION PRINCIPALE (POUR LES VÉHICULES) ---
function animate() {
  requestAnimationFrame(animate);
  if (controls) controls.update();
  if (renderer && scene && camera) {
    renderer.render(scene, camera);
  }
}

async function initApp() {
  if (
    document.body.dataset.authenticated === "true" ||
    document.getElementById("profile-trigger") === null
  ) {
    AppState.isAuthenticated = true;
  }

  if (!AppState.csrfToken) {
    AppState.csrfToken =
      document.getElementById("global-csrf-token")?.value || null;
  }

  try {
    const response = await fetch("api/get_vehicules.php");
    const result = await response.json();

    if (result.success && result.cars && result.cars.length > 0) {
      carData = result.cars.map((car) => ({
        id: car.id,
        model: car.glb_url,
        title: car.modele,
        subtitle: `${car.moteur} | ${car.annee}`,
        badge:
          parseInt(car.puissance_ch) >= 900 ? "Supercar" : "Série Spéciale",
        specs: `<p><strong>Moteur :</strong> ${car.moteur}</p>
                        <p><strong>Puissance :</strong> ${car.puissance_ch} ch</p>
                        <p><strong>Vitesse Max :</strong> ${car.vitesse_max} km/h</p>`,
        desc:
          car.description || "Aucune description disponible pour le moment.",
        sound: car.sound_url || "assets/sounds/ferrariEngine.wav",
        techDetails: {
          moteur: car.moteur,
          vitesse: `${car.vitesse_max} km/h`,
          puissance: `${car.puissance_ch} ch`,
          boite: "Séquentielle F1",
          transmission: "Propulsion",
        },
      }));

      await changeVehicle(0);
    } else {
      console.error("Aucun modèle trouvé dans la base de données.");
    }
  } catch (error) {
    console.error(
      "Erreur lors de l'initialisation des données dynamiques:",
      error,
    );
  }

  animate();
}

// --- INTERCEPTION EN AJAX DU FORMULAIRE DE CONTACT ---
const contactForm = document.getElementById("contact-form");
if (contactForm) {
  contactForm.addEventListener("submit", async function (e) {
    e.preventDefault();

    if (!AppState.csrfToken) {
      AppState.csrfToken =
        document.getElementById("global-csrf-token")?.value || null;
      if (!AppState.csrfToken) {
        alert(
          "Erreur de sécurité : Jeton de sécurité introuvable. Rechargez la page.",
        );
        return;
      }
    }

    const btnSubmit = contactForm.querySelector(".btn-submit-form");
    const originalBtnText = btnSubmit
      ? btnSubmit.innerHTML
      : "Envoyer le message";

    if (btnSubmit) {
      btnSubmit.disabled = true;
      btnSubmit.innerHTML =
        'Envoi en cours... <i class="fa-solid fa-spinner fa-spin"></i>';
    }

    const formData = new FormData(contactForm);
    formData.append("action", "contact");
    addCsrfToken(formData);

    try {
      const data = await secureFetch("auth_process.php", {
        method: "POST",
        body: formData,
      });

      if (data.success) {
        alert(data.message || "Votre message a été envoyé avec succès !");
        contactForm.reset();
      } else {
        alert(data.message || "Une erreur est survenue lors de l'envoi.");
        if (data.message && data.message.toLowerCase().includes("csrf")) {
          window.location.reload();
        }
      }
    } catch (err) {
      console.error("Erreur contact:", err);
      alert("Impossible de contacter le serveur de messagerie.");
    } finally {
      if (btnSubmit) {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = originalBtnText;
      }
    }
  });
}

window.changeVehicle = changeVehicle;
window.getCarDataLength = () => carData.length;

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initApp);
} else {
  initApp();
}
