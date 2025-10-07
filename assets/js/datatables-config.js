/**
 * Configuration globale pour DataTables
 * Tableaux interactifs avec export, tri, recherche, etc.
 */

(function() {
    'use strict';

    // Configuration par défaut pour tous les DataTables
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
        },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tous"]],
        responsive: true,
        autoWidth: false,
        stateSave: true,
        stateDuration: 60 * 60 * 24 * 7, // 7 jours
        dom: '<"row"<"col-sm-12 col-md-6"B><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        buttons: [
            {
                extend: 'copy',
                text: '<i class="fas fa-copy"></i> Copier',
                className: 'btn btn-sm btn-secondary',
                exportOptions: {
                    columns: ':visible:not(.no-export)'
                }
            },
            {
                extend: 'excel',
                text: '<i class="fas fa-file-excel"></i> Excel',
                className: 'btn btn-sm btn-success',
                exportOptions: {
                    columns: ':visible:not(.no-export)'
                },
                title: function() {
                    return 'SGDI_Export_' + new Date().toISOString().split('T')[0];
                }
            },
            {
                extend: 'pdf',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                className: 'btn btn-sm btn-danger',
                exportOptions: {
                    columns: ':visible:not(.no-export)'
                },
                orientation: 'landscape',
                pageSize: 'A4',
                title: function() {
                    return 'SGDI Export - ' + new Date().toLocaleDateString('fr-FR');
                },
                customize: function(doc) {
                    doc.defaultStyle.fontSize = 9;
                    doc.styles.tableHeader.fontSize = 10;
                    doc.styles.tableHeader.fillColor = '#0d6efd';
                }
            },
            {
                extend: 'print',
                text: '<i class="fas fa-print"></i> Imprimer',
                className: 'btn btn-sm btn-info',
                exportOptions: {
                    columns: ':visible:not(.no-export)'
                },
                title: function() {
                    return 'SGDI - ' + document.title;
                }
            },
            {
                extend: 'colvis',
                text: '<i class="fas fa-columns"></i> Colonnes',
                className: 'btn btn-sm btn-warning',
                columns: ':not(.no-toggle)'
            }
        ]
    });

    /**
     * Initialiser automatiquement les tableaux avec la classe .datatable
     */
    $(document).ready(function() {
        // Tableaux standards
        if ($('.datatable').length) {
            $('.datatable').DataTable();
        }

        // Tableaux sans pagination (pour petites listes)
        if ($('.datatable-simple').length) {
            $('.datatable-simple').DataTable({
                paging: false,
                info: false,
                searching: true,
                ordering: true
            });
        }

        // Tableaux sans export (pour données sensibles)
        if ($('.datatable-no-export').length) {
            $('.datatable-no-export').DataTable({
                buttons: [
                    {
                        extend: 'colvis',
                        text: '<i class="fas fa-columns"></i> Colonnes',
                        className: 'btn btn-sm btn-warning'
                    }
                ]
            });
        }
    });

    /**
     * Fonctions utilitaires exposées globalement
     */
    window.SGDI = window.SGDI || {};

    /**
     * Créer un DataTable avec configuration personnalisée
     * @param {string} selector - Sélecteur jQuery de la table
     * @param {object} options - Options DataTables supplémentaires
     * @returns {object} Instance DataTable
     */
    window.SGDI.initDataTable = function(selector, options) {
        options = options || {};
        return $(selector).DataTable(options);
    };

    /**
     * Recharger un DataTable
     * @param {string} selector - Sélecteur jQuery de la table
     */
    window.SGDI.reloadDataTable = function(selector) {
        const table = $(selector).DataTable();
        table.ajax.reload(null, false); // false = rester sur la page actuelle
    };

    /**
     * Ajouter une recherche personnalisée
     * @param {string} tableSelector - Sélecteur de la table
     * @param {string} inputSelector - Sélecteur de l'input de recherche
     * @param {number} columnIndex - Index de la colonne à rechercher
     */
    window.SGDI.addColumnSearch = function(tableSelector, inputSelector, columnIndex) {
        const table = $(tableSelector).DataTable();
        $(inputSelector).on('keyup change', function() {
            table.column(columnIndex).search(this.value).draw();
        });
    };

    /**
     * Configuration spéciale pour les tableaux de dossiers
     */
    window.SGDI.initDossiersTable = function(selector, ajaxUrl) {
        return $(selector).DataTable({
            ajax: {
                url: ajaxUrl,
                dataSrc: 'data'
            },
            columns: [
                { data: 'numero', title: 'Numéro' },
                { data: 'demandeur', title: 'Demandeur' },
                { data: 'type_infrastructure', title: 'Type' },
                { data: 'ville', title: 'Ville' },
                {
                    data: 'statut',
                    title: 'Statut',
                    render: function(data, type, row) {
                        const badges = {
                            'nouveau': 'primary',
                            'en_cours': 'info',
                            'approuve': 'success',
                            'rejete': 'danger',
                            'en_huitaine': 'warning'
                        };
                        const color = badges[data] || 'secondary';
                        return '<span class="badge bg-' + color + '">' + data.replace('_', ' ') + '</span>';
                    }
                },
                {
                    data: 'created_at',
                    title: 'Date création',
                    render: function(data) {
                        return new Date(data).toLocaleDateString('fr-FR');
                    }
                },
                {
                    data: 'id',
                    title: 'Actions',
                    orderable: false,
                    searchable: false,
                    className: 'no-export',
                    render: function(data) {
                        return '<a href="modules/dossiers/view.php?id=' + data + '" class="btn btn-sm btn-primary">' +
                               '<i class="fas fa-eye"></i> Voir</a>';
                    }
                }
            ],
            order: [[5, 'desc']] // Trier par date décroissante
        });
    };

    /**
     * Filtre rapide par statut pour tableaux de dossiers
     */
    window.SGDI.addStatutFilter = function(tableSelector) {
        // Créer les boutons de filtre
        const filterHtml = `
            <div class="btn-group mb-3" role="group" aria-label="Filtrer par statut">
                <button type="button" class="btn btn-sm btn-outline-secondary statut-filter" data-statut="">
                    <i class="fas fa-list"></i> Tous
                </button>
                <button type="button" class="btn btn-sm btn-outline-primary statut-filter" data-statut="nouveau">
                    Nouveaux
                </button>
                <button type="button" class="btn btn-sm btn-outline-info statut-filter" data-statut="en_cours">
                    En cours
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning statut-filter" data-statut="en_huitaine">
                    En huitaine
                </button>
                <button type="button" class="btn btn-sm btn-outline-success statut-filter" data-statut="approuve">
                    Approuvés
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger statut-filter" data-statut="rejete">
                    Rejetés
                </button>
            </div>
        `;

        // Insérer avant la table
        $(tableSelector).before(filterHtml);

        const table = $(tableSelector).DataTable();

        // Attacher les événements
        $('.statut-filter').on('click', function() {
            $('.statut-filter').removeClass('active');
            $(this).addClass('active');

            const statut = $(this).data('statut');

            if (statut === '') {
                table.column(4).search('').draw(); // 4 = colonne statut
            } else {
                table.column(4).search(statut).draw();
            }
        });

        // Activer le bouton "Tous" par défaut
        $('.statut-filter[data-statut=""]').addClass('active');
    };

    /**
     * Recherche inline dans les headers
     */
    window.SGDI.addHeaderSearch = function(tableSelector) {
        const table = $(tableSelector).DataTable();

        $(tableSelector + ' thead tr').clone(true).addClass('filters').appendTo(tableSelector + ' thead');

        $(tableSelector + ' thead tr.filters th').each(function(i) {
            const title = $(this).text();

            // Ne pas ajouter de recherche sur les colonnes d'actions
            if ($(this).hasClass('no-export')) {
                $(this).html('');
                return;
            }

            $(this).html('<input type="text" class="form-control form-control-sm" placeholder="' + title + '" />');

            $('input', this).on('keyup change', function() {
                if (table.column(i).search() !== this.value) {
                    table.column(i).search(this.value).draw();
                }
            });
        });
    };

})();
