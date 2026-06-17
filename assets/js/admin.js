/**
 * Scuderia Ferrari - Script d'Administration de la Modération & Messagerie
 */

const carNames = {
    0: "Monza SP3 Evo",
    1: "SF100 Vision",
    2: "F42 Aperta"
};

let localCommentsCached = [];
let localMessagesCached = []; // Stockage local des messages pour le filtrage en temps réel
let currentMessageFilter = 'all'; // Filtre de messagerie par défaut ('all', 'unread', 'read')

const csrfToken = document.getElementById('admin-csrf')?.value || null;

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// =========================================================================
// SECTION A : MODÉRATION DES AVIS CLIENTS
// =========================================================================

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
                renderDashboard('all');
            } else {
                container.innerHTML = `<p style="text-align:center; color:#ff2828;">${escapeHtml(data.message || 'Erreur lors de la récupération des données.')}</p>`;
            }
        })
        .catch(err => {
            console.error('Erreur Modération:', err);
            container.innerHTML = '<p style="text-align:center; color:#ff2828;">Impossible de joindre l\'API d\'administration.</p>';
        });
}

function renderDashboard(starFilterCriterion) {
    const container = document.getElementById('dashboard-container');
    if (!container) return;

    let filteredComments = localCommentsCached;
    if (starFilterCriterion !== 'all') {
        const targetStars = parseInt(starFilterCriterion);
        filteredComments = localCommentsCached.filter(c => parseInt(c.rating) === targetStars);
    }

    const totalAvisCount = localCommentsCached.length;
    const totalMasquesCount = localCommentsCached.filter(c => parseInt(c.is_deleted) === 1).length;
    
    let sumRatings = 0;
    localCommentsCached.forEach(c => sumRatings += parseInt(c.rating));
    const averageRating = totalAvisCount > 0 ? (sumRatings / totalAvisCount).toFixed(1) : '0.0';

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

    const groupedByVehicle = {};
    filteredComments.forEach(comment => {
        const vIndex = comment.vehicle_index;
        if (!groupedByVehicle[vIndex]) {
            groupedByVehicle[vIndex] = [];
        }
        groupedByVehicle[vIndex].push(comment);
    });

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
    bindModerationButtons();
}

function bindModerationButtons() {
    const container = document.getElementById('dashboard-container');
    if (!container) return;

    container.querySelectorAll('.btn-flag').forEach(btn => {
        btn.replaceWith(btn.cloneNode(true));
    });

    container.querySelectorAll('.btn-delete').forEach(btn => {
        btn.replaceWith(btn.cloneNode(true));
    });

    container.querySelectorAll('.btn-flag').forEach(btn => {
        btn.addEventListener('click', function() {
            const commentId = this.dataset.id;
            executeModerationAction('flagComment', commentId);
        });
    });

    container.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const commentId = this.dataset.id;
            if (confirm('ATTENTION : Voulez-vous définitivement supprimer cet avis ? Cette action est irréversible.')) {
                executeModerationAction('deleteComment', commentId);
            }
        });
    });
}

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
            loadAdminDashboard();
        } else {
            alert(data.message || 'Une erreur est survenue lors de l\'action.');
        }
    })
    .catch(err => {
        console.error('Erreur action admin:', err);
        alert('Impossible de communiquer avec le serveur.');
    });
}


// =========================================================================
// SECTION B : SYSTEME DE MESSAGERIE (CONTACT)
// =========================================================================

/**
 * Charge l'ensemble des messages de contact reçus
 */
function loadContactMessages() {
    const container = document.getElementById('contact-messages-container');
    if (!container) return;

    fetch('admin_process.php?action=getAllMessages')
        .then(response => {
            if (!response.ok) throw new Error('Erreur serveur');
            return response.json();
        })
        .then(data => {
            if (data.success && Array.isArray(data.messages)) {
                localMessagesCached = data.messages;
                renderMessages();
            } else {
                container.innerHTML = `<p style="color:#ff2828; padding:15px 0;">${escapeHtml(data.message || 'Erreur lors de la récupération des messages.')}</p>`;
            }
        })
        .catch(err => {
            console.error('Erreur Messages Contact:', err);
            container.innerHTML = '<p style="color:#ff2828; padding:15px 0;">Impossible de joindre l\'API de messagerie.</p>';
        });
}

/**
 * Filtre et construit dynamiquement l'affichage type boîte de réception
 */
function renderMessages() {
    const container = document.getElementById('contact-messages-container');
    if (!container) return;

    // Application du filtre de lecture (all, unread, read)
    const filtered = localMessagesCached.filter(msg => {
        const isRead = parseInt(msg.is_read || 0) === 1;
        if (currentMessageFilter === 'unread') return !isRead;
        if (currentMessageFilter === 'read') return isRead;
        return true;
    });

    if (filtered.length === 0) {
        container.innerHTML = '<p style="opacity:0.5; font-style:italic; padding:25px 0; text-align:center;">Aucun message trouvé dans cette catégorie.</p>';
        return;
    }

    let html = "";
    filtered.forEach(msg => {
        const isRead = parseInt(msg.is_read || 0) === 1;
        const phoneDisplay = msg.telephone ? escapeHtml(msg.telephone) : 'Non renseigné';
        const msgDisplay = msg.message ? escapeHtml(msg.message).replace(/\n/g, '<br>') : '';
        
        // Structure optimisée en fiches de messagerie pour intégrer le design de lecture
        html += `
            <div class="review-row" style="border-left: 4px solid ${isRead ? '#333' : '#ff2828'}; opacity: ${isRead ? '0.65' : '1'}; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; background:#141414; padding:18px; border-radius:4px;">
                <div class="review-info" style="width: 80%;">
                    <h4 style="margin: 0 0 5px 0; font-size: 15px;">
                        <strong style="color:#fff;">${escapeHtml(msg.nom)}</strong> 
                        <span style="font-size:12px; color:#888; font-weight:normal; margin-left:8px;">(${escapeHtml(msg.email)} | Tél: ${phoneDisplay})</span>
                        ${!isRead ? '<span class="global-badge" style="background:#ff2828; margin-left:10px; font-size:10px; padding:2px 6px;">Nouveau</span>' : ''}
                    </h4>
                    <div style="font-size:12px; color:#ffaa00; font-weight:bold; margin-bottom:8px;">Sujet : ${escapeHtml(msg.sujet)}</div>
                    <p style="color: #ccc; font-style: normal; line-height:1.4; margin: 5px 0;">${msgDisplay}</p>
                    <small style="color: #555; font-size:11px;">Reçu le : ${msg.created_at || 'Date inconnue'}</small>
                </div>
                <div class="review-actions" style="margin-left: 20px;">
                    ${!isRead ? `
                        <button class="btn-action btn-edit" onclick="markMessageAsRead(${msg.id})">
                            <i class="fa-solid fa-envelope-open"></i> Lu
                        </button>
                    ` : `
                        <span style="color: #00cc66; font-size: 13px; font-weight: bold;"><i class="fa-solid fa-circle-check"></i> Traité</span>
                    `}
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

/**
 * Change le filtre actif de la messagerie et met à jour l'apparence des boutons
 */
function filterMessages(filterType) {
    currentMessageFilter = filterType;
    
    // Bascule visuelle des classes de boutons de filtres
    ['all', 'unread', 'read'].forEach(f => {
        const btn = document.getElementById(`btn-filter-${f}`);
        if (btn) {
            if (f === filterType) {
                btn.style.background = '#ff2828';
                btn.style.borderColor = '#ff2828';
                btn.style.color = '#fff';
            } else {
                btn.style.background = '#222';
                btn.style.borderColor = '#444';
                btn.style.color = '#fff';
            }
        }
    });

    renderMessages();
}

/**
 * Envoie une requête asynchrone pour marquer le message comme lu en Base de données
 */
function markMessageAsRead(messageId) {
    if (!csrfToken) {
        alert('Erreur de sécurité : Jeton CSRF absent.');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'markMessageAsRead');
    formData.append('message_id', messageId);
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
            // Mise à jour de l'état local pour rafraîchir l'interface sans recharger toute la page
            const messageObj = localMessagesCached.find(m => m.id == messageId);
            if (messageObj) {
                messageObj.is_read = 1;
            }
            renderMessages();
        } else {
            alert(data.message || 'Impossible de modifier le statut de lecture.');
        }
    })
    .catch(err => {
        console.error('Erreur lecture message:', err);
        alert('Erreur de connexion avec le serveur.');
    });
}

// Globalisation pour permettre l'appel par l'attribut inline onclick="markMessageAsRead()"
window.markMessageAsRead = markMessageAsRead;
window.filterMessages = filterMessages;


// =========================================================================
// INITIALISATION GLOBALE
// =========================================================================
document.addEventListener('DOMContentLoaded', () => {
    loadAdminDashboard();
    loadContactMessages(); // Initialisation de la boîte de messagerie

    const starFilterSelector = document.getElementById('star-filter');
    if (starFilterSelector) {
        starFilterSelector.addEventListener('change', function() {
            renderDashboard(this.value);
        });
    }
});