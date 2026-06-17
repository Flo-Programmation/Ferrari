/**
 * Scuderia Ferrari - Script d'Administration de la Modération
 */

const carNames = {
    0: "Monza SP3 Evo",
    1: "SF100 Vision",
    2: "F42 Aperta"
};

let localCommentsCached = [];
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

/**
 * FONCTION NOUVELLE : Charge l'ensemble des messages de contact reçus
 */
function loadContactMessages() {
    const container = document.getElementById('contact-messages-container');
    if (!container) return;

    // Ajout explicite du jeton s'il est requis par ton fichier admin_process.php
    fetch('admin_process.php?action=getAllMessages')
        .then(response => {
            if (!response.ok) throw new Error('Erreur serveur');
            return response.json();
        })
        .then(data => {
            if (data.success && Array.isArray(data.messages)) {
                if (data.messages.length === 0) {
                    container.innerHTML = '<p style="opacity:0.5; font-style:italic; padding:15px 0;">Aucun message reçu pour le moment.</p>';
                    return;
                }

                let html = `
                    <table>
                        <thead>
                            <tr>
                                <th style="width:15%;">Nom</th>
                                <th style="width:20%;">Email</th>
                                <th style="width:15%;">Téléphone</th>
                                <th style="width:20%;">Sujet</th>
                                <th style="width:30%;">Message</th>
                            </tr>
                        </thead>
                        <tbody>
                `;

                data.messages.forEach(msg => {
                    const phoneDisplay = msg.telephone ? escapeHtml(msg.telephone) : '<span style="opacity:0.3; font-style:italic;">Non renseigné</span>';
                    const msgDisplay = msg.message ? escapeHtml(msg.message).replace(/\n/g, '<br>') : '';
                    
                    html += `
                        <tr>
                            <td><strong style="color:#fff;">${escapeHtml(msg.nom)}</strong></td>
                            <td><a href="mailto:${escapeHtml(msg.email)}" style="color:#aaa; text-decoration:underline;">${escapeHtml(msg.email)}</a></td>
                            <td>${phoneDisplay}</td>
                            <td style="color: #ffaa00; font-weight: bold;">${escapeHtml(msg.sujet)}</td>
                            <td style="color: #ccc; line-height: 1.4;">${msgDisplay}</td>
                        </tr>
                    `;
                });

                html += '</tbody></table>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `<p style="color:#ff2828; padding:15px 0;">${escapeHtml(data.message || 'Erreur lors de la récupération des messages.')}</p>`;
            }
        })
        .catch(err => {
            console.error('Erreur Messages Contact:', err);
            container.innerHTML = '<p style="color:#ff2828; padding:15px 0;">Impossible de joindre l\'API de messagerie.</p>';
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
        btn.replaceWith(btn.cloneNode(true)); // Nettoie les écouteurs précédents
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

document.addEventListener('DOMContentLoaded', () => {
    loadAdminDashboard();
    loadContactMessages(); // Lancement du chargement des messages de contact

    const starFilterSelector = document.getElementById('star-filter');
    if (starFilterSelector) {
        starFilterSelector.addEventListener('change', function() {
            renderDashboard(this.value);
        });
    }
});