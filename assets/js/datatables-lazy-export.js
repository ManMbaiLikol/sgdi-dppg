/**
 * Chargement à la demande des bibliothèques d'export DataTables
 *
 * JSZip (Excel) et pdfmake (PDF) pèsent environ 2 Mo : au lieu de les charger
 * sur chaque page, on les télécharge au premier clic sur le bouton d'export.
 * Doit être chargé après buttons.html5 et avant toute initialisation de tableau.
 */
(function () {
    'use strict';

    if (!window.jQuery || !$.fn.dataTable || !$.fn.dataTable.ext.buttons.excelHtml5) {
        return;
    }

    var LIBS = {
        excel: ['https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js'],
        pdf: [
            'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js',
            'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js' // dépend de pdfmake
        ]
    };

    var loaded = {};

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = src;
            script.onload = resolve;
            script.onerror = function () { reject(new Error('Échec du chargement : ' + src)); };
            document.head.appendChild(script);
        });
    }

    // Charge les scripts dans l'ordre, une seule fois par type d'export
    function loadLibs(type) {
        if (!loaded[type]) {
            loaded[type] = LIBS[type].reduce(function (chain, src) {
                return chain.then(function () { return loadScript(src); });
            }, Promise.resolve());
            loaded[type].catch(function () { delete loaded[type]; }); // permettre un nouvel essai
        }
        return loaded[type];
    }

    function makeLazy(buttonName, type, isReady) {
        var button = $.fn.dataTable.ext.buttons[buttonName];
        var originalAction = button.action;

        // Toujours proposer le bouton, même avant le chargement de la bibliothèque
        button.available = function () {
            return window.FileReader !== undefined;
        };

        button.action = function (e, dt, node, config, cb) {
            var that = this;
            var args = arguments;

            if (isReady()) {
                return originalAction.apply(that, args);
            }

            that.processing(true);
            loadLibs(type).then(function () {
                that.processing(false);
                originalAction.apply(that, args);
            }).catch(function (err) {
                that.processing(false);
                console.error(err);
                alert("L'export n'a pas pu être préparé. Vérifiez votre connexion Internet puis réessayez.");
            });
        };
    }

    makeLazy('excelHtml5', 'excel', function () { return !!window.JSZip; });
    makeLazy('pdfHtml5', 'pdf', function () { return !!(window.pdfMake && window.pdfMake.vfs); });
})();
