/**
 * MINISTÈRE DE LA FONCTION PUBLIQUE - SCRIPT JS PRINCIPAL (2026)
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialisation du gestionnaire Toast
    initToastContainer();

    // 2. Bascule visibilité mot de passe (Toggle Password)
    initPasswordToggles();

    // 3. Animation automatique des compteurs numériques au scroll / chargement
    initNumberCounters();

    // 4. Filtrage dynamique universel (pour cartes et tableaux)
    initLiveSearch();
});

/**
 * Créer le conteneur de notifications s'il n'existe pas encore
 */
function initToastContainer() {
    if (!document.getElementById('toast-container')) {
        const container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }
}

/**
 * Afficher une notification Toast élégante
 * @param {string} message 
 * @param {'success'|'error'|'warning'|'info'} type 
 */
function showToast(message, type = 'info') {
    initToastContainer();
    const container = document.getElementById('toast-container');

    const icons = {
        success: 'fa-circle-check',
        error: 'fa-circle-xmark',
        warning: 'fa-triangle-exclamation',
        info: 'fa-circle-info'
    };

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fa-solid ${icons[type] || icons.info}" style="font-size: 1.2rem;"></i>
        <span>${message}</span>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'toastSlideIn 0.3s reverse ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

/**
 * Bouton afficher/masquer le mot de passe
 */
function initPasswordToggles() {
    const toggleBtns = document.querySelectorAll('[data-toggle-password]');
    toggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.getAttribute('data-toggle-password');
            const input = document.getElementById(targetId) || btn.previousElementSibling;
            if (input && input.type) {
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                const icon = btn.querySelector('i') || btn;
                if (icon) {
                    icon.classList.toggle('fa-eye', !isPassword);
                    icon.classList.toggle('fa-eye-slash', isPassword);
                }
            }
        });
    });
}

/**
 * Animation fluide des valeurs numériques
 */
function initNumberCounters() {
    const counters = document.querySelectorAll('[data-counter-target]');
    counters.forEach(counter => {
        const target = parseInt(counter.getAttribute('data-counter-target'), 10) || 0;
        let current = 0;
        const step = Math.max(1, Math.ceil(target / 40));
        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                counter.textContent = target.toLocaleString('fr-FR');
                clearInterval(timer);
            } else {
                counter.textContent = current.toLocaleString('fr-FR');
            }
        }, 25);
    });
}

/**
 * Filtrage dynamique en direct sur les attributs [data-searchable]
 */
function initLiveSearch() {
    const searchInputs = document.querySelectorAll('[data-live-search]');
    searchInputs.forEach(input => {
        const targetContainerId = input.getAttribute('data-live-search');
        const container = document.getElementById(targetContainerId);

        if (!container) return;

        input.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const items = container.querySelectorAll('[data-searchable-item]');

            let visibleCount = 0;
            items.forEach(item => {
                const text = item.textContent.toLowerCase();
                if (text.includes(query)) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            // Gérer le message "Aucun résultat" s'il existe
            const emptyMsg = document.getElementById('search-empty-message');
            if (emptyMsg) {
                emptyMsg.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        });
    });
}
