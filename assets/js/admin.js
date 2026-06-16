// Stockage global des avis chargés pour appliquer les filtres sans réinterroger l'API
let allReviews = [];

document.addEventListener('DOMContentLoaded', () => {
    loadDashboardReviews();

    // Écouteur sur le filtre d'étoiles
    const starFilter = document.getElementById('star-filter');
    if (starFilter) {
        starFilter.addEventListener('change', () => {
            renderReviews(starFilter.value);
        });
    }
});

// Chargement initial des avis depuis l'API PHP
function loadDashboardReviews() {
    fetch('../api/main.php?action=getAdminReviews')
        .then(res => res.json())
        .then(data => {
            if (data && data.success) {
                allReviews = data.reviews || [];
                renderReviews('all');
            } else {
                document.getElementById('dashboard-container').innerHTML = 
                    `<p style="color:#ff2828; text-align:center;">Erreur : ${data.message || 'Impossible de charger les avis.'}</p>`;
            }
        })
        .catch(err => {
            console.error(err);
            document.getElementById('dashboard-container').innerHTML = 
                '<p style="color:#ff2828; text-align:center;">Erreur de communication avec l\'API.</p>';
        });
}

// Fonction de rendu avec regroupement par modèle et filtrage par note
function renderReviews(starCriterion) {
    const container = document.getElementById('dashboard-container');
    if (!container) return;

    if (allReviews.length === 0) {
        container.innerHTML = '<p style="text-align:center; opacity:0.5;">Aucun avis enregistré dans la base de données.</p>';
        return;
    }

    // 1. Filtrer les avis selon le critère d'étoiles sélectionné
    const filteredReviews = allReviews.filter(r => {
        if (starCriterion === 'all') return true;
        return parseInt(r.note) === parseInt(starCriterion);
    });

    if (filteredReviews.length === 0) {
        container.innerHTML = '<p style="text-align:center; opacity:0.5; padding: 40px;">Aucun avis ne correspond à ce filtre d\'étoiles.</p>';
        return;
    }

    // 2. Grouper les avis filtrés par Modèle de Voiture
    const groupedByCar = {};
    filteredReviews.forEach(review => {
        const modelName = review.voiture_modele;
        if (!groupedByCar[modelName]) {
            groupedByCar[modelName] = [];
        }
        groupedByCar[modelName].push(review);
    });

    // 3. Générer le code HTML propre
    let html = '';

    for (const [carModel, reviewsList] of Object.entries(groupedByCar)) {
        // "Voir le nombre d'avis global de chaque modèle" -> calculé via reviewsList.length
        const totalCount = reviewsList.length;

        html += `
            <div class="car-group">
                <div class="car-group-header">
                    <h2><i class="fa-solid fa-car"></i> ${carModel}</h2>
                    <span class="global-badge">${totalCount} ${totalCount > 1 ? 'avis' : 'avis'}</span>
                </div>
                <div class="review-list">
        `;

        reviewsList.forEach(rev => {
            let stars = '★'.repeat(rev.note) + '☆'.repeat(5 - rev.note);
            const isFlaggedClass = parseInt(rev.is_flagged) === 1 ? 'flagged' : '';
            const isFlaggedBtnDisabled = parseInt(rev.is_flagged) === 1 ? 'disabled' : '';

            html += `
                <div class="review-row ${isFlaggedClass}" id="review-row-${rev.id}">
                    <div class="review-info">
                        <h4>Par ${rev.prenom} ${rev.nom.charAt(0)}. (${rev.email})</h4>
                        <div class="stars">${stars}</div>
                        <p>" ${rev.commentaire} "</p>
                    </div>
                    <div class="review-actions">
                        <button class="btn-action btn-flag" ${isFlaggedBtnDisabled} onclick="flagReview(${rev.id})">
                            <i class="fa-solid fa-triangle-exclamation"></i> ${parseInt(rev.is_flagged) === 1 ? 'Signalé' : 'Signaler'}
                        </button>
                        <button class="btn-action btn-delete" onclick="deleteReview(${rev.id})">
                            <i class="fa-solid fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;
    }

    container.innerHTML = html;
}

// Action : Supprimer un avis
function deleteReview(reviewId) {
    if (!confirm('Voulez-vous vraiment supprimer définitivement cet avis ?')) return;

    const csrfToken = document.getElementById('admin-csrf').value;
    const formData = new FormData();
    formData.append('action', 'deleteReview');
    formData.append('id', reviewId);
    formData.append('csrf_token', csrfToken);

    fetch('../api/main.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.success) {
            // Mise à jour de notre tableau local et réaffichage fluide
            allReviews = allReviews.filter(r => r.id !== reviewId);
            const currentFilter = document.getElementById('star-filter').value;
            renderReviews(currentFilter);
        } else {
            alert(data.message || 'Erreur lors de la suppression.');
        }
    })
    .catch(err => console.error(err));
}

// Action : Signaler un avis + Notification
function flagReview(reviewId) {
    const reason = prompt(
        "Spécifiez un motif d'avertissement pour l'utilisateur (il recevra ce texte sous forme de notification) :", 
        "Votre avis a été signalé par l'équipe de modération pour non-respect des règles de notre vitrine."
    );
    
    if (reason === null) return; // Annulation

    const csrfToken = document.getElementById('admin-csrf').value;
    const formData = new FormData();
    formData.append('action', 'flagReview');
    formData.append('id', reviewId);
    formData.append('message', reason);
    formData.append('csrf_token', csrfToken);

    fetch('../api/main.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.success) {
            alert('Avis signalé ! L\'utilisateur a été averti sur son espace personnel.');
            // Mettre à jour l'état de l'avis localement pour changer le visuel à l'écran
            const revIdx = allReviews.findIndex(r => r.id === reviewId);
            if (revIdx !== -1) allReviews[revIdx].is_flagged = 1;
            
            const currentFilter = document.getElementById('star-filter').value;
            renderReviews(currentFilter);
        } else {
            alert(data.message || 'Erreur lors du signalement.');
        }
    })
    .catch(err => console.error(err));
}