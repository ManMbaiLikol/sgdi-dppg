// SGDI MVP - JavaScript principal

document.addEventListener('DOMContentLoaded', function() {

    // Initialisation des tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialisation des popovers
    var popoverTriggerList = [].slice.call(document.querySelectorList('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function(popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });

    // Auto-dismiss des alertes après 5 secondes
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert.alert-dismissible');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Confirmation des actions de suppression
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-danger') ||
            e.target.closest('.btn-danger')) {

            var target = e.target.classList.contains('btn-danger') ? e.target : e.target.closest('.btn-danger');
            var action = target.textContent.trim().toLowerCase();

            if (action.includes('supprimer') || action.includes('delete') ||
                action.includes('annuler') || action.includes('rejeter')) {

                if (!confirm('Êtes-vous sûr de vouloir effectuer cette action ? Cette opération ne peut pas être annulée.')) {
                    e.preventDefault();
                    return false;
                }
            }
        }
    });

    // Validation en temps réel des formulaires
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // Formatage automatique des montants
    var montantInputs = document.querySelectorAll('input[name*="montant"], input[type="number"][step*="0."]');
    montantInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            var value = parseFloat(this.value);
            if (!isNaN(value) && value > 0) {
                var formatted = new Intl.NumberFormat('fr-FR').format(value);

                // Afficher le montant formaté
                var display = this.parentNode.querySelector('.montant-display');
                if (!display) {
                    display = document.createElement('small');
                    display.className = 'montant-display text-muted ms-2';
                    this.parentNode.appendChild(display);
                }
                display.textContent = formatted + ' FCFA';
            }
        });
    });

    // Recherche en temps réel dans les tableaux
    var searchInputs = document.querySelectorAll('input[name="search"]');
    searchInputs.forEach(function(input) {
        var debounceTimer;
        input.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                var form = input.closest('form');
                if (form && input.value.length >= 2) {
                    // Auto-submit après 500ms de pause dans la saisie
                    form.submit();
                }
            }, 500);
        });
    });

    // Gestion des uploads de fichiers avec preview
    var fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            var file = this.files[0];
            if (file) {
                var fileName = file.name;
                var fileSize = (file.size / 1024 / 1024).toFixed(2);
                var fileType = file.type;

                // Afficher les informations du fichier
                var info = this.parentNode.querySelector('.file-info');
                if (!info) {
                    info = document.createElement('div');
                    info.className = 'file-info mt-2 p-2 bg-light rounded';
                    this.parentNode.appendChild(info);
                }

                var icon = 'fas fa-file';
                if (fileType.includes('pdf')) icon = 'fas fa-file-pdf text-danger';
                else if (fileType.includes('image')) icon = 'fas fa-file-image text-success';
                else if (fileType.includes('word')) icon = 'fas fa-file-word text-primary';

                info.innerHTML = `
                    <small>
                        <i class="${icon}"></i> <strong>${fileName}</strong><br>
                        <i class="fas fa-weight"></i> Taille: ${fileSize} MB<br>
                        <i class="fas fa-info-circle"></i> Type: ${fileType}
                    </small>
                `;

                // Validation de la taille
                if (file.size > 5242880) { // 5MB
                    info.innerHTML += '<br><small class="text-danger"><i class="fas fa-exclamation-triangle"></i> Fichier trop volumineux (max 5MB)</small>';
                    this.value = '';
                }
            }
        });
    });

    // Navigation avec sauvegarde du scroll
    window.addEventListener('beforeunload', function() {
        sessionStorage.setItem('scrollPosition', window.scrollY);
    });

    window.addEventListener('load', function() {
        var scrollPosition = sessionStorage.getItem('scrollPosition');
        if (scrollPosition) {
            window.scrollTo(0, parseInt(scrollPosition));
            sessionStorage.removeItem('scrollPosition');
        }
    });

    // Mise à jour automatique des timestamps
    function updateRelativeTimes() {
        var timeElements = document.querySelectorAll('[data-timestamp]');
        timeElements.forEach(function(element) {
            var timestamp = parseInt(element.getAttribute('data-timestamp'));
            var now = Math.floor(Date.now() / 1000);
            var diff = now - timestamp;

            var relativeTime = '';
            if (diff < 60) {
                relativeTime = 'À l\'instant';
            } else if (diff < 3600) {
                relativeTime = Math.floor(diff / 60) + ' min';
            } else if (diff < 86400) {
                relativeTime = Math.floor(diff / 3600) + 'h';
            } else {
                relativeTime = Math.floor(diff / 86400) + 'j';
            }

            element.textContent = relativeTime;
        });
    }

    // Mettre à jour les timestamps toutes les minutes
    updateRelativeTimes();
    setInterval(updateRelativeTimes, 60000);

    // Gestion des raccourcis clavier
    document.addEventListener('keydown', function(e) {
        // Ctrl+S pour sauvegarder les formulaires
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            var form = document.querySelector('form');
            if (form) {
                form.submit();
            }
        }

        // Échap pour fermer les modales
        if (e.key === 'Escape') {
            var modals = document.querySelectorAll('.modal.show');
            modals.forEach(function(modal) {
                var bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            });
        }
    });

    // Amélioration de l'accessibilité
    var focusableElements = document.querySelectorAll('a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])');
    focusableElements.forEach(function(element) {
        element.addEventListener('focus', function() {
            this.style.outline = '2px solid #667eea';
            this.style.outlineOffset = '2px';
        });

        element.addEventListener('blur', function() {
            this.style.outline = '';
            this.style.outlineOffset = '';
        });
    });

    // Sauvegarde automatique des brouillons (formulaires longs)
    var longForms = document.querySelectorAll('form[data-autosave="true"]');
    longForms.forEach(function(form) {
        var formId = form.id || 'form_' + Date.now();
        var saveKey = 'draft_' + formId;

        // Charger le brouillon sauvegardé
        var savedData = localStorage.getItem(saveKey);
        if (savedData) {
            try {
                var data = JSON.parse(savedData);
                Object.keys(data).forEach(function(name) {
                    var field = form.querySelector('[name="' + name + '"]');
                    if (field && field.type !== 'password') {
                        field.value = data[name];
                    }
                });
            } catch (e) {
                console.log('Erreur lors du chargement du brouillon:', e);
            }
        }

        // Sauvegarder automatiquement
        var saveTimeout;
        form.addEventListener('input', function() {
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(function() {
                var formData = new FormData(form);
                var data = {};
                for (var pair of formData.entries()) {
                    if (pair[0] !== 'csrf_token' && pair[0] !== 'password') {
                        data[pair[0]] = pair[1];
                    }
                }
                localStorage.setItem(saveKey, JSON.stringify(data));
            }, 2000); // Sauvegarder après 2s d'inactivité
        });

        // Nettoyer le brouillon après soumission réussie
        form.addEventListener('submit', function() {
            localStorage.removeItem(saveKey);
        });
    });

    // Amélioration des sélecteurs multiples
    var multiSelects = document.querySelectorAll('select[multiple]');
    multiSelects.forEach(function(select) {
        select.addEventListener('change', function() {
            var selectedCount = this.selectedOptions.length;
            var totalCount = this.options.length;

            var info = this.parentNode.querySelector('.select-info');
            if (!info) {
                info = document.createElement('small');
                info.className = 'select-info text-muted';
                this.parentNode.appendChild(info);
            }

            info.textContent = selectedCount + ' / ' + totalCount + ' sélectionné(s)';
        });
    });

});

// Fonctions utilitaires globales

// Afficher une notification toast
function showToast(message, type = 'success') {
    // Créer le conteneur toast s'il n'existe pas
    var toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    // Créer le toast
    var toast = document.createElement('div');
    toast.className = 'toast align-items-center text-bg-' + type + ' border-0';
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    toastContainer.appendChild(toast);
    var bsToast = new bootstrap.Toast(toast);
    bsToast.show();

    // Supprimer le toast après fermeture
    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

// Confirmer une action
function confirmAction(message, callback) {
    if (confirm(message || 'Êtes-vous sûr de vouloir effectuer cette action ?')) {
        if (typeof callback === 'function') {
            callback();
        }
        return true;
    }
    return false;
}

// Formater un montant
function formatMontant(montant, devise = 'FCFA') {
    if (!montant || isNaN(montant)) return '0 ' + devise;
    return new Intl.NumberFormat('fr-FR').format(montant) + ' ' + devise;
}

// Copier dans le presse-papier
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('Copié dans le presse-papier', 'success');
    }).catch(function(err) {
        console.error('Erreur de copie:', err);
        showToast('Erreur lors de la copie', 'danger');
    });
}