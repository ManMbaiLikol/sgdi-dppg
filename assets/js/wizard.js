/**
 * Wizard de création de dossier
 * Gestion de la navigation, validation temps réel, et sauvegarde automatique
 */

(function() {
    'use strict';

    let currentStep = 1;
    const totalSteps = 5;
    let draftId = document.getElementById('draftId')?.value || null;
    let autoSaveTimer = null;

    /**
     * Initialisation
     */
    document.addEventListener('DOMContentLoaded', function() {
        initWizard();
        initValidation();
        initAutoSave();
        initTypeInfrastructure();
    });

    /**
     * Initialiser le wizard
     */
    function initWizard() {
        document.getElementById('nextBtn').addEventListener('click', nextStep);
        document.getElementById('prevBtn').addEventListener('click', prevStep);

        // Restaurer les données du brouillon
        const draftData = document.getElementById('draftId')?.dataset.draft;
        if (draftData) {
            restoreDraft(JSON.parse(draftData));
        }

        updateProgress();
    }

    /**
     * Passer à l'étape suivante
     */
    function nextStep() {
        if (!validateCurrentStep()) {
            return;
        }

        if (currentStep < totalSteps) {
            // Marquer l'étape comme complétée
            document.querySelector(`.wizard-step[data-step="${currentStep}"]`).classList.add('completed');

            currentStep++;
            showStep(currentStep);
            updateProgress();
            saveDraft();
        }
    }

    /**
     * Revenir à l'étape précédente
     */
    function prevStep() {
        if (currentStep > 1) {
            currentStep--;
            showStep(currentStep);
            updateProgress();
        }
    }

    /**
     * Afficher une étape spécifique
     */
    function showStep(step) {
        // Masquer tous les panneaux
        document.querySelectorAll('.wizard-panel').forEach(panel => {
            panel.classList.remove('active');
        });

        // Afficher le panneau actuel
        document.querySelector(`.wizard-panel[data-panel="${step}"]`).classList.add('active');

        // Mettre à jour les indicateurs d'étapes
        document.querySelectorAll('.wizard-step').forEach((stepEl, index) => {
            if (index + 1 < step) {
                stepEl.classList.add('completed');
                stepEl.classList.remove('active');
            } else if (index + 1 === step) {
                stepEl.classList.add('active');
                stepEl.classList.remove('completed');
            } else {
                stepEl.classList.remove('active', 'completed');
            }
        });

        // Gérer les boutons
        document.getElementById('prevBtn').style.display = step === 1 ? 'none' : 'inline-block';
        document.getElementById('nextBtn').style.display = step === totalSteps ? 'none' : 'inline-block';
        document.getElementById('submitBtn').style.display = step === totalSteps ? 'inline-block' : 'none';

        // Si dernière étape, générer le résumé
        if (step === totalSteps) {
            generateReviewSummary();
        }

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /**
     * Mettre à jour la barre de progression
     */
    function updateProgress() {
        const progress = (currentStep / totalSteps) * 100;
        document.getElementById('progressBar').style.width = progress + '%';
        document.getElementById('progressText').textContent = `Étape ${currentStep} sur ${totalSteps}`;
        document.getElementById('completionText').textContent = Math.round(progress) + '% complété';
    }

    /**
     * Valider l'étape actuelle
     */
    function validateCurrentStep() {
        const panel = document.querySelector(`.wizard-panel[data-panel="${currentStep}"]`);
        const requiredFields = panel.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                showValidationError(field, 'Ce champ est requis');
                isValid = false;
            } else {
                clearValidationError(field);
            }
        });

        return isValid;
    }

    /**
     * Initialiser la validation en temps réel
     */
    function initValidation() {
        // Email validation
        const emailField = document.getElementById('emailDemandeur');
        if (emailField) {
            emailField.addEventListener('blur', function() {
                if (this.value && !isValidEmail(this.value)) {
                    showValidationError(this, 'Format d\'email invalide');
                } else {
                    clearValidationError(this);
                }
            });
        }

        // Téléphone validation
        const phoneField = document.getElementById('telephoneDemandeur');
        if (phoneField) {
            phoneField.addEventListener('blur', function() {
                if (this.value && !isValidPhone(this.value)) {
                    showValidationError(this, 'Format de téléphone invalide (ex: 6XXXXXXXX)');
                } else {
                    clearValidationError(this);
                }
            });
        }

        // Validation générique sur tous les champs requis
        document.querySelectorAll('[required]').forEach(field => {
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    showValidationError(this, 'Ce champ est requis');
                } else {
                    clearValidationError(this);
                }
            });

            field.addEventListener('input', function() {
                if (this.value.trim()) {
                    clearValidationError(this);
                }
            });
        });
    }

    /**
     * Afficher une erreur de validation
     */
    function showValidationError(field, message) {
        field.classList.add('is-invalid');
        const messageEl = document.querySelector(`[data-field="${field.name}"]`);
        if (messageEl) {
            messageEl.textContent = message;
            messageEl.className = 'validation-message error';
        }
    }

    /**
     * Effacer une erreur de validation
     */
    function clearValidationError(field) {
        field.classList.remove('is-invalid');
        const messageEl = document.querySelector(`[data-field="${field.name}"]`);
        if (messageEl) {
            messageEl.className = 'validation-message';
            messageEl.textContent = '';
        }
    }

    /**
     * Valider un email
     */
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    /**
     * Valider un téléphone camerounais
     */
    function isValidPhone(phone) {
        return /^6[0-9]{8}$/.test(phone.replace(/\s+/g, ''));
    }

    /**
     * Initialiser la sauvegarde automatique
     */
    function initAutoSave() {
        const form = document.getElementById('wizardForm');

        form.addEventListener('input', function() {
            // Réinitialiser le timer
            clearTimeout(autoSaveTimer);

            // Sauvegarder après 2 secondes d'inactivité
            autoSaveTimer = setTimeout(saveDraft, 2000);
        });
    }

    /**
     * Sauvegarder le brouillon
     */
    function saveDraft() {
        const formData = new FormData(document.getElementById('wizardForm'));
        const data = {};

        formData.forEach((value, key) => {
            if (key !== 'csrf_token' && key !== 'action' && key !== 'draft_id') {
                data[key] = value;
            }
        });

        // Envoyer via AJAX
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'save_draft',
                data: JSON.stringify(data),
                draft_id: draftId || ''
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                draftId = result.draft_id;
                document.getElementById('draftId').value = draftId;
                showAutoSaveIndicator();
            }
        })
        .catch(error => {
            console.error('Erreur de sauvegarde:', error);
        });
    }

    /**
     * Afficher l'indicateur de sauvegarde automatique
     */
    function showAutoSaveIndicator() {
        const indicator = document.getElementById('autoSaveIndicator');
        indicator.style.display = 'block';

        setTimeout(() => {
            indicator.style.display = 'none';
        }, 2000);
    }

    /**
     * Restaurer les données du brouillon
     */
    function restoreDraft(data) {
        Object.keys(data).forEach(key => {
            const field = document.querySelector(`[name="${key}"]`);
            if (field) {
                field.value = data[key];
            }
        });
    }

    /**
     * Initialiser la gestion du type d'infrastructure
     */
    function initTypeInfrastructure() {
        const typeSelect = document.getElementById('typeInfrastructure');
        const sousTypeSelect = document.getElementById('sousType');
        const typeInfo = document.getElementById('typeInfo');
        const typeInfoText = document.getElementById('typeInfoText');

        const sousTypes = {
            station_service: [
                { value: 'implantation', label: 'Implantation' },
                { value: 'reprise', label: 'Reprise' },
                { value: 'remodelage', label: 'Remodelage' }
            ],
            point_consommateur: [
                { value: 'implantation', label: 'Implantation' },
                { value: 'reprise', label: 'Reprise' }
            ],
            depot_gpl: [
                { value: 'implantation', label: 'Implantation' }
            ],
            centre_emplisseur: [
                { value: 'implantation', label: 'Implantation' }
            ]
        };

        const typeDescriptions = {
            station_service: 'Infrastructure pour la vente au détail de carburants et produits pétroliers',
            point_consommateur: 'Point de livraison de produits pétroliers pour un consommateur spécifique',
            depot_gpl: 'Installation de stockage et distribution de GPL',
            centre_emplisseur: 'Centre de remplissage de bouteilles de gaz'
        };

        typeSelect.addEventListener('change', function() {
            const type = this.value;

            // Vider et remplir les sous-types
            sousTypeSelect.innerHTML = '<option value="">-- Sélectionner --</option>';

            if (type && sousTypes[type]) {
                sousTypes[type].forEach(st => {
                    const option = document.createElement('option');
                    option.value = st.value;
                    option.textContent = st.label;
                    sousTypeSelect.appendChild(option);
                });
                sousTypeSelect.disabled = false;
            } else {
                sousTypeSelect.disabled = true;
            }

            // Afficher la description
            if (type && typeDescriptions[type]) {
                typeInfoText.textContent = typeDescriptions[type];
                typeInfo.style.display = 'block';
            } else {
                typeInfo.style.display = 'none';
            }

            // Mettre à jour les champs spécifiques
            updateSpecificFields(type);
        });

        // Trigger si déjà sélectionné (brouillon)
        if (typeSelect.value) {
            typeSelect.dispatchEvent(new Event('change'));
        }
    }

    /**
     * Mettre à jour les champs spécifiques selon le type
     */
    function updateSpecificFields(type) {
        const container = document.getElementById('specificFields');

        let html = '';

        if (type === 'station_service') {
            html = `
                <div class="mb-3">
                    <label class="form-label field-required">Opérateur propriétaire</label>
                    <input type="text" name="operateur_proprietaire" class="form-control" required>
                </div>
            `;
        } else if (type === 'point_consommateur') {
            html = `
                <div class="mb-3">
                    <label class="form-label field-required">Entreprise bénéficiaire</label>
                    <input type="text" name="entreprise_beneficiaire" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Référence contrat de livraison</label>
                    <input type="text" name="contrat_livraison" class="form-control">
                </div>
            `;
        } else if (type === 'depot_gpl') {
            html = `
                <div class="mb-3">
                    <label class="form-label field-required">Entreprise installatrice</label>
                    <input type="text" name="entreprise_installatrice" class="form-control" required>
                </div>
            `;
        } else if (type === 'centre_emplisseur') {
            html = `
                <div class="mb-3">
                    <label class="form-label">Opérateur de gaz</label>
                    <input type="text" name="operateur_gaz" class="form-control">
                    <small class="text-muted">Requis si entreprise constructrice non fournie</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Entreprise constructrice</label>
                    <input type="text" name="entreprise_constructrice" class="form-control">
                    <small class="text-muted">Requis si opérateur de gaz non fourni</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Capacité d'enfûtage (kg/jour)</label>
                    <input type="number" name="capacite_enfutage" class="form-control" min="0">
                </div>
            `;
        }

        container.innerHTML = html || '<p class="text-muted">Aucun champ spécifique requis pour ce type.</p>';

        // Réinitialiser la validation sur les nouveaux champs
        initValidation();
    }

    /**
     * Générer le résumé pour vérification finale
     */
    function generateReviewSummary() {
        const formData = new FormData(document.getElementById('wizardForm'));
        const reviewContainer = document.getElementById('reviewSummary').querySelector('.card-body');

        const sections = [
            {
                title: 'Type d\'infrastructure',
                fields: ['type_infrastructure', 'sous_type']
            },
            {
                title: 'Demandeur',
                fields: ['nom_demandeur', 'contact_demandeur', 'telephone_demandeur', 'email_demandeur']
            },
            {
                title: 'Localisation',
                fields: ['region', 'departement', 'ville', 'arrondissement', 'quartier', 'lieu_dit', 'coordonnees_gps']
            },
            {
                title: 'Détails spécifiques',
                fields: ['operateur_proprietaire', 'entreprise_beneficiaire', 'contrat_livraison',
                         'entreprise_installatrice', 'operateur_gaz', 'entreprise_constructrice', 'capacite_enfutage']
            }
        ];

        const fieldLabels = {
            type_infrastructure: 'Type',
            sous_type: 'Sous-type',
            nom_demandeur: 'Nom',
            contact_demandeur: 'Contact',
            telephone_demandeur: 'Téléphone',
            email_demandeur: 'Email',
            region: 'Région',
            departement: 'Département',
            ville: 'Ville',
            arrondissement: 'Arrondissement',
            quartier: 'Quartier',
            lieu_dit: 'Lieu-dit',
            coordonnees_gps: 'GPS',
            operateur_proprietaire: 'Opérateur propriétaire',
            entreprise_beneficiaire: 'Entreprise bénéficiaire',
            contrat_livraison: 'Contrat de livraison',
            entreprise_installatrice: 'Entreprise installatrice',
            operateur_gaz: 'Opérateur de gaz',
            entreprise_constructrice: 'Entreprise constructrice',
            capacite_enfutage: 'Capacité d\'enfûtage'
        };

        let html = '';

        sections.forEach(section => {
            const sectionFields = section.fields
                .map(field => {
                    const value = formData.get(field);
                    if (value) {
                        return `
                            <tr>
                                <td class="text-muted">${fieldLabels[field]}</td>
                                <td><strong>${value}</strong></td>
                            </tr>
                        `;
                    }
                    return '';
                })
                .join('');

            if (sectionFields) {
                html += `
                    <h6 class="mt-3 mb-2">${section.title}</h6>
                    <table class="table table-sm">
                        ${sectionFields}
                    </table>
                `;
            }
        });

        reviewContainer.innerHTML = html || '<p class="text-muted">Aucune donnée saisie</p>';
    }

})();
