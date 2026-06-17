/**
 * Module dédié à la gestion de la fenêtre catalogue des modèles
 */
document.addEventListener('DOMContentLoaded', () => {
    const triggers = document.querySelectorAll('.trigger-catalogue-modal');
    const modalOverlay = document.getElementById('catalogue-modal-overlay');
    const closeBtn = document.getElementById('close-catalogue-modal-btn');
    const carsGrid = document.getElementById('catalogue-cars-grid');

    // Ouverture de la modale
    triggers.forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            if (modalOverlay) {
                modalOverlay.classList.add('active');
                loadCarsFromDatabase();
            }
        });
    });

    // Fermeture de la modale
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            modalOverlay.classList.remove('active');
        });
    }

    if (modalOverlay) {
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) {
                modalOverlay.classList.remove('active');
            }
        });
    }

    // Requête AJAX pour récupérer les voitures de la base de données
    async function loadCarsFromDatabase() {
        if (!carsGrid) return;
        
        // Loader d'attente
        carsGrid.innerHTML = `
            <div style="grid-column: 1/-1; text-align: center; padding: 40px; color: rgba(255,255,255,0.5);">
                <i class="fa-solid fa-spinner fa-spin" style="margin-bottom:10px; font-size:1.5rem;"></i>
                <p>Chargement du catalogue Scuderia...</p>
            </div>
        `;

        try {
            // Appel à l'endpoint API PHP (à adapter selon votre contrôleur de véhicules existant)
            const response = await fetch('api/get_vehicules.php');
            if (!response.ok) throw new Error("Erreur lors de la récupération des données");
            
            const data = await response.json();
            
            if (!data.success || !data.cars || data.cars.length === 0) {
                carsGrid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 20px;">Aucun véhicule trouvé dans la vitrine.</div>`;
                return;
            }

            carsGrid.innerHTML = ''; // Nettoyer le loader

            // Génération des cartes de vignettes
            data.cars.forEach((car, index) => {
                const card = document.createElement('div');
                card.className = 'car-vignette-card';

                // Formatage propre du prix en monnaie locale (€)
                const formattedPrice = new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(car.prix);

                // Emplacement photo (laisse de la place si image_url est absent)
                let imageHtml = `<div class="car-vignette-image-placeholder">
                                    <span><i class="fa-solid fa-image" style="margin-right:5px;"></i> Photo disponible prochainement</span>
                                 </div>`;
                
                if (car.image_url && car.image_url.trim() !== "") {
                    imageHtml = `<div class="car-vignette-image-placeholder">
                                    <img src="${escapeHtml(car.image_url)}" alt="${escapeHtml(car.modele)}" onerror="this.parentNode.innerHTML='<span><i class=\"fa-solid fa-image\"></i> Photo manquante</span>';">
                                 </div>`;
                }

                card.innerHTML = `
                    ${imageHtml}
                    <div class="car-vignette-details">
                        <span class="car-vignette-year">${escapeHtml(car.annee)}</span>
                        <h3 class="car-vignette-title">${escapeHtml(car.modele)}</h3>
                        <p class="car-vignette-price">${formattedPrice}</p>
                        <button class="btn-vignette-tech" data-car-index="${index}" data-car-id="${car.id}">
                            Fiche Technique & Avis
                        </button>
                    </div>
                `;

                carsGrid.appendChild(card);
            });

            // Lier les clics sur les boutons des fiches techniques
            bindVignetteActions();

        } catch (error) {
            console.error(error);
            carsGrid.innerHTML = `<div style="grid-column: 1/-1; text-align: center; padding: 20px; color: var(--ferrari-red);">Erreur lors du chargement des modèles. Réessayez ultérieurement.</div>`;
        }
    }

    // Liaison avec votre application existante (main.js et structure d'avis)
    function bindVignetteActions() {
        const actionBtns = document.querySelectorAll('.btn-vignette-tech');
        actionBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const index = parseInt(btn.getAttribute('data-car-index'), 10);
                
                // 1. Fermer la modale actuelle
                modalOverlay.classList.remove('active');

                // 2. Aller vers la section showroom/3D
                window.location.href = '#showroom';

                // 3. Déclencher le changement de véhicule dans votre main.js (votre fonction globale)
                if (typeof window.changeVehicle === 'function') {
                    window.changeVehicle(index);
                }

                // 4. Ouvrir automatiquement la fiche technique existante (panneau de détails/avis)
                const sidePanel = document.getElementById('side-panel') || document.querySelector('.side-panel-info');
                if (sidePanel) {
                    sidePanel.classList.add('open');
                    sidePanel.classList.add('active');
                }
                
                // Optionnel : Forcer l'affichage de l'onglet Avis si vous possédez un système d'onglet sur la fiche technique
                const reviewTabTrigger = document.getElementById('tab-trigger-reviews');
                if (reviewTabTrigger) reviewTabTrigger.click();
            });
        });
    }

    function escapeHtml(string) {
        return String(string).replace(/[&<>"']/g, function (s) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[s];
        });
    }
});