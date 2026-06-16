document.addEventListener('DOMContentLoaded', () => {
    const addCarForm = document.getElementById('add-car-form');

    if (addCarForm) {
        addCarForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            // Récupération automatique de tous les champs du formulaire
            const formData = new FormData(addCarForm);

            try {
                // Remplace 'api/add_car.php' par le chemin réel de ton script de traitement PHP
                const response = await fetch('api/add_car.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('Le modèle a été ajouté avec succès en base de données !');
                    addCarForm.reset(); // Vide le formulaire
                } else {
                    alert('Erreur lors de l\'ajout : ' + result.message);
                }
            } catch (error) {
                console.error('Erreur système :', error);
                alert('Impossible de joindre le serveur.');
            }
        });
    }
});

// Afficher / masquer le champ de notification d'avertissement
function toggleWarningForm(reviewId) {
    const form = document.getElementById(`warn-form-${reviewId}`);
    if (form) {
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
    }
}

// Envoyer la notification de signalement
async function sendNotification(reviewId, userEmail) {
    const messageInput = document.getElementById(`msg-${reviewId}`);
    const message = messageInput.value.trim();

    if (!message) {
        alert('Veuillez saisir un motif pour l\'avertissement.');
        return;
    }

    try {
        const response = await fetch('api/send_notification.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                review_id: reviewId,
                email: userEmail,
                message: message
            })
        });

        const result = await response.json();
        if (result.success) {
            alert('L\'utilisateur a été notifié.');
            toggleWarningForm(reviewId);
            messageInput.value = '';
        }
    } catch (error) {
        alert('Erreur lors de l\'envoi de la notification.');
    }
}