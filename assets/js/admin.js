/**
 * Scuderia Ferrari - Script d'Administration de la Modération
 */

// Nom complet des véhicules calqué sur la structure globale de l'application
const carNames = {
    0: "Monza SP3 Evo",
    1: "SF100 Vision",
    2: "F42 Aperta"
};

// Variable pour conserver les données brutes des avis reçues du serveur
let localCommentsCached = [];
const csrfToken = document.getElementById('admin-csrf')?.value || null;

/**
 * Fonction utilitaire d'échappement HTML contre les failles XSS
 */
function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/**
 * Charge l'ensemble des commentaires depuis l'API d'administration
 */
function loadAdminDashboard() {
    const container = document.getElementById('dashboard-container');
    if (!container) return;

    fetch('admin_process.php?action=getAllComments')
        .then(response => {
            if (!response.ok) throw new Error('Erreur serveur');
            return response.json();
        })
        .then(data => {
            if (data.success && Array.isArray(data.comments)) {
                localCommentsCached = data.comments;
                renderDashboard('all'); // Rendu initial : aucun filtre appliqué
            } else {
                container.innerHTML = `<p style="text-align:center; color:#ff2828;">${escapeHtml(data.message || 'Erreur lors de la récupération des données.')}</p>`;
            }
        })
        .catch(err => {
            console.error('Erreur Modération:', err);
            container.innerHTML = '<p style="text-align:center; color:#ff2828;">Impossible de joindre l\'API d\'administration.</p>';
        });
}

/**
 * Génère le rendu graphique des statistiques, des groupes et des avis
 * @param {string|number} starFilterCriterion - 'all' ou une note de 1 à 5
 */
function renderDashboard(starFilterCriterion) {
    const container = document.getElementById('dashboard-container');
    if (!container) return;

    // 1. Filtrage initial des commentaires selon le choix de l'admin
    let filteredComments = localCommentsCached;
    if (starFilterCriterion !== 'all') {
        const targetStars = parseInt(starFilterCriterion);
        filteredComments = localCommentsCached.filter(c => parseInt(c.rating) === targetStars);
    }

    // 2. Calcul des métriques statistiques globales
    const totalAvisCount = localCommentsCached.length;
    const totalMasquesCount = localCommentsCached.filter(c => parseInt(c.is_deleted) === 1).length;
    
    let sumRatings = 0;
    localCommentsCached.forEach(c => sumRatings += parseInt(c.rating));
    const averageRating = totalAvisCount > 0 ? (sumRatings / totalAvisCount).toFixed(1) : '0.0';

    // Génération HTML de la zone des statistiques
    let htmlContent = `
        <div class="dashboard-meta">
            <div class="stat-card">
                <h4>Total Avis Publiés</h4>
                <div class="count">${totalAvisCount}</div>
            </div>
            <div class="stat-card">
                <h4>Avis Masqués / Signalés</h4>
                <div class="count" style="color: #ffaa00;">${totalMasquesCount}</div>
            </div>
            <div class="stat-card">
                <h4>Note Moyenne Globale</h4>
                <div class="count" style="color: #00cc66;">${averageRating} <span style="font-size:14px; color:#555;">/5</span></div>
            </div>
        </div>
    `;

    if (filteredComments.length === 0) {
        htmlContent += '<p style="text-align:center; opacity:0.4; padding: 40px 0;">Aucun avis ne correspond à ce critère de recherche.</p>';
        container.innerHTML = htmlContent;
        return;
    }

    // 3. Regroupement intelligent des avis par index de véhicule
    const groupedByVehicle = {};
    filteredComments.forEach(comment => {
        const vIndex = comment.vehicle_index;
        if (!groupedByVehicle[vIndex]) {
            groupedByVehicle[vIndex] = [];
        }
        groupedByVehicle[vIndex].push(comment);
    });

    // 4. Construction de l'arbre HTML par modèle de voiture
    Object.keys(groupedByVehicle).forEach(vIndex => {
        const commentsInGroup = groupedByVehicle[vIndex];
        const vehicleTitle = carNames[vIndex] || `Véhicule Prototype (#${vIndex})`;

        htmlContent += `
            <div class="car-group">
                <div class="car-group-header">
                    <h2><i class="fa-solid fa-car"></i> ${escapeHtml(vehicleTitle)}</h2>
                    <span class="global-badge">${commentsInGroup.length} avis</span>
                </div>
                <div class="review-list">
        `;

        commentsInGroup.forEach(c => {
            const isFlagged = parseInt(c.is_deleted) === 1;
            const starsPattern = '★'.repeat(c.rating) + '☆'.repeat(5 - c.rating);
            const authorFullName = `${escapeHtml(c.prenom)} ${escapeHtml(c.nom)}`;

            htmlContent += `
                <div class="review-row ${isFlagged ? 'flagged' : ''}">
                    <div class="review-info">
                        <h4>${authorFullName} ${isFlagged ? '<span style="color:#ffaa00; font-size:11px; margin-left:10px;"><i class="fa-solid fa-eye-slash"></i> Masqué du public</span>' : ''}</h4>
                        <div class="stars">${starsPattern}</div>
                        <p>" ${escapeHtml(c.comment)} "</p>
                    </div>
                    <div class="review-actions">
                        <button class="btn-action btn-flag" data-id="${c.id}" ${isFlagged ? 'disabled' : ''}>
                            <i class="fa-solid fa-eye-slash"></i> Masquer
                        </button>
                        <button class="btn-action btn-delete" data-id="${c.id}">
                            <i class="fa-solid fa-trash-can"></i> Supprimer
                        </button>
                    </div>
                </div>
            `;
        });

        htmlContent += `
                </div>
            </div>
        `;
    });

    container.innerHTML = htmlContent;

    // 5. Attachement dynamique des écouteurs d'événements sur les actions de modération
    bindModerationButtons();
}

/**
 * Attache les clics d'actions aux boutons Masquer et Supprimer
 */
function bindModerationButtons() {
    const container = document.getElementById('dashboard-container');
    if (!container) return;

    // Gestion de l'action : Masquer un avis (Soft delete)
    container.querySelectorAll('.btn-flag').forEach(btn => {
        btn.addEventListener('click', function() {
            const commentId = this.dataset.id;
            executeModerationAction('flagComment', commentId);
        });
    });

    // Gestion de l'action : Supprimer un avis (Hard delete de la base)
    container.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const commentId = this.dataset.id;
            if (confirm('ATTENTION : Voulez-vous définitivement supprimer cet avis de la base de données ? Cette action est irréversible.')) {
                executeModerationAction('deleteComment', commentId);
            }
        });
    });
}

/**
 * Envoie la requête POST sécurisée par CSRF au contrôleur PHP
 * @param {string} actionName - 'flagComment' ou 'deleteComment'
 * @param {number|string} id - Identifiant de l'avis cible
 */
function executeModerationAction(actionName, id) {
    if (!csrfToken) {
        alert('Jeton de sécurité CSRF manquant. Veuillez rafraîchir la page.');
        return;
    }

    const formData = new FormData();
    formData.append('action', actionName);
    formData.append('comment_id', id);
    formData.append('csrf_token', csrfToken);

    fetch('admin_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) throw new Error('Erreur réseau');
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert(data.message);
            // Rechargement immédiat des données pour garder le tableau de bord synchronisé
            loadAdminDashboard();
        } else {
            alert(data.message || 'Une erreur est survenue lors de l\'action.');
        }
    })
    .catch(err => {
        console.error('Erreur action admin:', err);
        alert('Impossible de communiquer avec le serveur pour valider la modération.');
    });
}

// -------------------------------------------------------------------------
// INITIALISATION ET GESTION DES FILTRES DU DOM
// -------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', () => {
    // Lancement du chargement initial
    loadAdminDashboard();

    // Écouteur sur le menu déroulant de filtrage par note
    const starFilterSelector = document.getElementById('star-filter');
    if (starFilterSelector) {
        starFilterSelector.addEventListener('change', function() {
            renderDashboard(this.value);
        });
    }
});