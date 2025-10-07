/**
 * Fonctionnalités avancées pour tableaux DataTables
 * - Visibilité des colonnes personnalisable
 * - Sauvegarde des préférences utilisateur
 * - Export colonnes sélectionnées
 * - Filtres avancés inline
 */

(function() {
    'use strict';

    const STORAGE_KEY_PREFIX = 'sgdi_table_';

    // ========================================================================
    // CLASSE PRINCIPALE
    // ========================================================================

    class AdvancedTable {
        constructor(tableId, options = {}) {
            this.tableId = tableId;
            this.table = null;
            this.options = {
                savePreferences: true,
                exportOptions: ['excel', 'pdf', 'csv'],
                columnToggle: true,
                inlineFilters: false,
                ...options
            };

            this.storageKey = STORAGE_KEY_PREFIX + tableId;
            this.init();
        }

        /**
         * Initialisation
         */
        init() {
            const $table = $('#' + this.tableId);
            if ($table.length === 0) {
                console.warn('Table non trouvée:', this.tableId);
                return;
            }

            // Charger les préférences sauvegardées
            const preferences = this.loadPreferences();

            // Créer la configuration DataTable améliorée
            const config = this.buildDataTableConfig(preferences);

            // Initialiser DataTable
            this.table = $table.DataTable(config);

            // Ajouter les fonctionnalités avancées
            if (this.options.columnToggle) {
                this.createColumnToggle();
            }

            if (this.options.inlineFilters) {
                this.createInlineFilters();
            }

            // Attacher les événements
            this.attachEvents();

            console.log('✅ Table avancée initialisée:', this.tableId);
        }

        /**
         * Configuration DataTable
         */
        buildDataTableConfig(preferences) {
            const self = this;

            return {
                dom: 'Bfrtip',
                buttons: [
                    {
                        extend: 'colvis',
                        text: '<i class="fas fa-columns"></i> Colonnes',
                        className: 'btn-sm',
                        titleAttr: 'Gérer la visibilité des colonnes'
                    },
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fas fa-file-excel"></i> Excel',
                        className: 'btn-sm btn-success',
                        titleAttr: 'Exporter en Excel',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        text: '<i class="fas fa-file-pdf"></i> PDF',
                        className: 'btn-sm btn-danger',
                        titleAttr: 'Exporter en PDF',
                        exportOptions: {
                            columns: ':visible'
                        },
                        orientation: 'landscape',
                        pageSize: 'A4'
                    },
                    {
                        extend: 'csvHtml5',
                        text: '<i class="fas fa-file-csv"></i> CSV',
                        className: 'btn-sm btn-info',
                        titleAttr: 'Exporter en CSV',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'print',
                        text: '<i class="fas fa-print"></i> Imprimer',
                        className: 'btn-sm',
                        titleAttr: 'Imprimer le tableau',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        text: '<i class="fas fa-sync-alt"></i> Réinitialiser',
                        className: 'btn-sm btn-warning',
                        titleAttr: 'Réinitialiser les préférences',
                        action: function() {
                            self.resetPreferences();
                        }
                    }
                ],
                pageLength: preferences.pageLength || 25,
                order: preferences.order || [[0, 'asc']],
                columnDefs: preferences.columnDefs || [],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json',
                    buttons: {
                        colvis: 'Colonnes visibles',
                        colvisRestore: 'Restaurer'
                    }
                },
                responsive: true,
                stateSave: this.options.savePreferences,
                stateDuration: -1, // Sauvegarder indéfiniment
                initComplete: function(settings, json) {
                    // Restaurer la visibilité des colonnes
                    if (preferences.columnVisibility) {
                        preferences.columnVisibility.forEach((visible, idx) => {
                            self.table.column(idx).visible(visible);
                        });
                    }

                    // Annoncer aux lecteurs d'écran
                    if (window.accessibility) {
                        window.accessibility.announce('Tableau chargé avec ' + self.table.rows().count() + ' lignes');
                    }
                }
            };
        }

        /**
         * Créer le panneau de gestion des colonnes
         */
        createColumnToggle() {
            const self = this;
            const $table = $('#' + this.tableId);
            const $wrapper = $table.closest('.dataTables_wrapper');

            // Conteneur du toggle
            const $togglePanel = $('<div>', {
                class: 'column-toggle-panel card mt-3 mb-3',
                html: `
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-eye"></i> Personnaliser l'affichage des colonnes
                            <button class="btn btn-sm btn-link float-end collapse-toggle" type="button">
                                <i class="fas fa-chevron-down"></i>
                            </button>
                        </h6>
                    </div>
                    <div class="card-body collapse-content" style="display: none;">
                        <div class="row" id="${this.tableId}-column-toggles"></div>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-primary save-column-prefs">
                                <i class="fas fa-save"></i> Sauvegarder mes préférences
                            </button>
                            <button class="btn btn-sm btn-secondary toggle-all-columns">
                                <i class="fas fa-check-double"></i> Tout afficher
                            </button>
                        </div>
                    </div>
                `
            });

            $wrapper.prepend($togglePanel);

            // Générer les checkboxes pour chaque colonne
            const $container = $togglePanel.find('#' + this.tableId + '-column-toggles');

            this.table.columns().every(function(index) {
                const column = this;
                const title = $(column.header()).text();

                if (title.trim() === '' || title === 'Actions') {
                    return; // Skip empty headers or Actions column
                }

                const isVisible = column.visible();
                const $checkbox = $(`
                    <div class="col-md-3 col-sm-4 col-6 mb-2">
                        <div class="form-check">
                            <input class="form-check-input column-toggle-checkbox"
                                   type="checkbox"
                                   id="col-toggle-${self.tableId}-${index}"
                                   data-column="${index}"
                                   ${isVisible ? 'checked' : ''}>
                            <label class="form-check-label" for="col-toggle-${self.tableId}-${index}">
                                ${title}
                            </label>
                        </div>
                    </div>
                `);

                $container.append($checkbox);

                // Événement de changement
                $checkbox.find('input').on('change', function() {
                    const visible = $(this).prop('checked');
                    column.visible(visible);

                    if (window.accessibility) {
                        window.accessibility.announce(
                            `Colonne ${title} ${visible ? 'affichée' : 'masquée'}`
                        );
                    }
                });
            });

            // Toggle du panneau
            $togglePanel.find('.collapse-toggle').on('click', function() {
                const $content = $togglePanel.find('.collapse-content');
                const $icon = $(this).find('i');

                $content.slideToggle(300);
                $icon.toggleClass('fa-chevron-down fa-chevron-up');
            });

            // Sauvegarder les préférences
            $togglePanel.find('.save-column-prefs').on('click', function() {
                self.saveColumnPreferences();

                if (window.accessibility) {
                    window.accessibility.announce('Préférences sauvegardées');
                }

                // Notification visuelle
                $(this).html('<i class="fas fa-check"></i> Sauvegardé !');
                setTimeout(() => {
                    $(this).html('<i class="fas fa-save"></i> Sauvegarder mes préférences');
                }, 2000);
            });

            // Tout afficher
            $togglePanel.find('.toggle-all-columns').on('click', function() {
                $container.find('input[type="checkbox"]').prop('checked', true).trigger('change');
            });
        }

        /**
         * Créer des filtres inline
         */
        createInlineFilters() {
            const self = this;
            const $table = $('#' + this.tableId);

            // Ajouter une ligne de filtres sous les en-têtes
            const $thead = $table.find('thead');
            const $filterRow = $('<tr class="filters"></tr>');

            this.table.columns().every(function() {
                const column = this;
                const title = $(column.header()).text();

                const $th = $('<th></th>');

                if (title.trim() === '' || title === 'Actions') {
                    $th.html('');
                } else {
                    const $input = $('<input>', {
                        type: 'text',
                        class: 'form-control form-control-sm',
                        placeholder: 'Filtrer ' + title,
                        'aria-label': 'Filtrer ' + title
                    });

                    $input.on('keyup change', function() {
                        const val = $(this).val();
                        column.search(val).draw();
                    });

                    $th.append($input);
                }

                $filterRow.append($th);
            });

            $thead.append($filterRow);
        }

        /**
         * Sauvegarder les préférences des colonnes
         */
        saveColumnPreferences() {
            const columnVisibility = [];
            const columnOrder = this.table.order();

            this.table.columns().every(function(index) {
                columnVisibility[index] = this.visible();
            });

            const preferences = {
                columnVisibility: columnVisibility,
                order: columnOrder,
                pageLength: this.table.page.len(),
                timestamp: Date.now()
            };

            localStorage.setItem(this.storageKey, JSON.stringify(preferences));
            console.log('Préférences sauvegardées:', preferences);
        }

        /**
         * Charger les préférences
         */
        loadPreferences() {
            try {
                const stored = localStorage.getItem(this.storageKey);
                if (stored) {
                    return JSON.parse(stored);
                }
            } catch (e) {
                console.error('Erreur chargement préférences:', e);
            }
            return {};
        }

        /**
         * Réinitialiser les préférences
         */
        resetPreferences() {
            if (confirm('Voulez-vous vraiment réinitialiser toutes vos préférences pour ce tableau ?')) {
                localStorage.removeItem(this.storageKey);

                // Recharger la page
                window.location.reload();
            }
        }

        /**
         * Attacher les événements
         */
        attachEvents() {
            const self = this;

            // Sauvegarder automatiquement lors de changements
            if (this.options.savePreferences) {
                // Ordre des colonnes
                this.table.on('order.dt', function() {
                    self.saveColumnPreferences();
                });

                // Longueur de page
                this.table.on('length.dt', function() {
                    self.saveColumnPreferences();
                });

                // Visibilité des colonnes
                this.table.on('column-visibility.dt', function() {
                    self.saveColumnPreferences();
                });
            }
        }

        /**
         * API publique
         */
        getTable() {
            return this.table;
        }

        exportVisible(format) {
            const button = this.table.button(`.buttons-${format}`);
            if (button) {
                button.trigger();
            }
        }
    }

    // ========================================================================
    // INITIALISATION AUTOMATIQUE
    // ========================================================================

    $(document).ready(function() {
        // Initialiser automatiquement tous les tableaux avec la classe .advanced-table
        $('.advanced-table').each(function() {
            const tableId = $(this).attr('id');
            if (tableId) {
                window['advTable_' + tableId] = new AdvancedTable(tableId, {
                    savePreferences: true,
                    columnToggle: true,
                    inlineFilters: $(this).data('inline-filters') === true
                });
            }
        });

        console.log('%c📊 Advanced Tables chargé', 'color: #10b981; font-weight: bold;');
    });

    // ========================================================================
    // EXPORT GLOBAL
    // ========================================================================

    window.AdvancedTable = AdvancedTable;

    // Helper pour créer rapidement une table avancée
    window.createAdvancedTable = function(tableId, options) {
        return new AdvancedTable(tableId, options);
    };

})();
