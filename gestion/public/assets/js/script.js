// public/assets/js/script.js

document.addEventListener('DOMContentLoaded', function() {
    // Gestion du menu mobile
    const menuToggle = document.getElementById('menu-toggle');
    const mobileMenu = document.getElementById('mobile-menu');

    if (menuToggle && mobileMenu) {
        menuToggle.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
    }

    // Gestion des modals (générique)
    // Cette fonction sera réutilisée par chaque module pour ouvrir/fermer ses modals
    window.setupModal = function(modalId, openButtonSelector, closeButtonSelector) {
        const modal = document.getElementById(modalId);
        const openButtons = document.querySelectorAll(openButtonSelector);
        const closeButton = modal ? modal.querySelector(closeButtonSelector) : null;

        if (!modal) {
            console.warn(`Modal with ID '${modalId}' not found.`);
            return;
        }

        // Ouvre le modal
        openButtons.forEach(button => {
            button.addEventListener('click', function() {
                modal.classList.remove('hidden');
            });
        });

        // Ferme le modal via le bouton de fermeture
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                modal.classList.add('hidden');
            });
        }

        // Ferme le modal si l'utilisateur clique en dehors du contenu
        window.addEventListener('click', function(event) {
            if (event.target == modal) {
                modal.classList.add('hidden');
            }
        });
    };

    // Exemple d'utilisation pour un modal de message (si vous en avez un global)
    // window.setupModal('messageModal', '.open-message-modal', '.close-message-modal');

    // Fonction pour afficher des messages (succès/erreur)
    window.showMessage = function(type, message) {
        const messageContainer = document.getElementById('message-container');
        if (messageContainer) {
            messageContainer.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            messageContainer.classList.remove('hidden');
            setTimeout(() => {
                messageContainer.classList.add('hidden');
                messageContainer.innerHTML = '';
            }, 5000); // Cache le message après 5 secondes
        } else {
            console.log(`Message (${type}): ${message}`);
        }
    };
});
