USE sgdi_mvp;

DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS permissions;

CREATE TABLE IF NOT EXISTS permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(100) UNIQUE NOT NULL,
    module VARCHAR(50) NOT NULL,
    nom VARCHAR(150) NOT NULL,
    description TEXT,
    date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_permissions_module (module),
    INDEX idx_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    permission_id INT NOT NULL,
    accordee_par INT NOT NULL,
    date_attribution TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_permission (user_id, permission_id),
    INDEX idx_user_permissions_user (user_id),
    INDEX idx_user_permissions_permission (permission_id),
    INDEX idx_user_permissions_accordee (accordee_par)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('dossiers.create', 'dossiers', 'Créer un dossier', 'Permet de créer de nouveaux dossiers d''implantation'),
('dossiers.view', 'dossiers', 'Voir les dossiers', 'Permet de consulter les dossiers'),
('dossiers.edit', 'dossiers', 'Modifier un dossier', 'Permet de modifier les informations d''un dossier'),
('dossiers.delete', 'dossiers', 'Supprimer un dossier', 'Permet de supprimer un dossier (admin uniquement)'),
('dossiers.list', 'dossiers', 'Lister les dossiers', 'Permet d''accéder à la liste des dossiers'),
('dossiers.view_all', 'dossiers', 'Voir tous les dossiers', 'Permet de voir tous les dossiers sans filtre de rôle'),
('dossiers.export', 'dossiers', 'Exporter les dossiers', 'Permet d''exporter les données des dossiers'),
('dossiers.localisation', 'dossiers', 'Gérer la localisation', 'Permet de gérer les coordonnées GPS et la carte');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('commission.create', 'commission', 'Constituer une commission', 'Permet de nommer les membres d''une commission'),
('commission.view', 'commission', 'Voir les commissions', 'Permet de consulter les commissions'),
('commission.edit', 'commission', 'Modifier une commission', 'Permet de modifier la composition d''une commission'),
('commission.validate', 'commission', 'Valider une inspection', 'Permet de valider un rapport d''inspection');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('paiements.view', 'paiements', 'Voir les paiements', 'Permet de consulter les paiements'),
('paiements.create', 'paiements', 'Enregistrer un paiement', 'Permet d''enregistrer un nouveau paiement'),
('paiements.edit', 'paiements', 'Modifier un paiement', 'Permet de modifier un paiement existant'),
('paiements.receipt', 'paiements', 'Générer un reçu', 'Permet de générer et imprimer un reçu de paiement');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('daj.view', 'daj', 'Voir les analyses DAJ', 'Permet de consulter les analyses juridiques'),
('daj.create', 'daj', 'Faire une analyse DAJ', 'Permet de réaliser une analyse juridique'),
('daj.edit', 'daj', 'Modifier une analyse DAJ', 'Permet de modifier une analyse juridique'),
('daj.validate', 'daj', 'Valider une analyse DAJ', 'Permet de valider une analyse juridique');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('inspections.view', 'inspections', 'Voir les inspections', 'Permet de consulter les inspections'),
('inspections.create', 'inspections', 'Faire une inspection', 'Permet de réaliser une inspection terrain'),
('inspections.edit', 'inspections', 'Modifier une inspection', 'Permet de modifier un rapport d''inspection'),
('inspections.validate', 'inspections', 'Valider une inspection', 'Permet de valider une inspection'),
('inspections.print', 'inspections', 'Imprimer les fiches', 'Permet d''imprimer les fiches d''inspection');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('visa.chef_service', 'visa', 'Visa Chef Service', 'Permet d''apposer le visa niveau 1 (Chef Service)'),
('visa.sous_directeur', 'visa', 'Visa Sous-Directeur', 'Permet d''apposer le visa niveau 2 (Sous-Directeur)'),
('visa.directeur', 'visa', 'Visa Directeur', 'Permet d''apposer le visa niveau 3 (Directeur)'),
('visa.view', 'visa', 'Voir les visas', 'Permet de consulter les visas apposés');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('decisions.view', 'decisions', 'Voir les décisions', 'Permet de consulter les décisions ministérielles'),
('decisions.create', 'decisions', 'Prendre une décision', 'Permet de prendre une décision finale (autorisation/refus)'),
('decisions.transmit', 'decisions', 'Transmettre au ministre', 'Permet de transmettre un dossier au ministre');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('documents.view', 'documents', 'Voir les documents', 'Permet de consulter les documents'),
('documents.upload', 'documents', 'Uploader des documents', 'Permet d''uploader de nouveaux documents'),
('documents.download', 'documents', 'Télécharger des documents', 'Permet de télécharger les documents'),
('documents.delete', 'documents', 'Supprimer des documents', 'Permet de supprimer des documents');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('users.view', 'users', 'Voir les utilisateurs', 'Permet de consulter la liste des utilisateurs'),
('users.create', 'users', 'Créer un utilisateur', 'Permet de créer de nouveaux utilisateurs'),
('users.edit', 'users', 'Modifier un utilisateur', 'Permet de modifier les informations d''un utilisateur'),
('users.delete', 'users', 'Supprimer un utilisateur', 'Permet de supprimer un utilisateur'),
('users.toggle_status', 'users', 'Activer/Désactiver utilisateur', 'Permet d''activer ou désactiver un compte utilisateur'),
('users.manage_permissions', 'users', 'Gérer les permissions', 'Permet d''attribuer des permissions aux utilisateurs');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('huitaine.view', 'huitaine', 'Voir les huitaines', 'Permet de consulter les huitaines en cours'),
('huitaine.create', 'huitaine', 'Créer une huitaine', 'Permet de déclencher une huitaine'),
('huitaine.regularize', 'huitaine', 'Régulariser une huitaine', 'Permet de régulariser un dossier en huitaine');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('gps.view', 'gps', 'Voir les données GPS', 'Permet de consulter les données GPS'),
('gps.edit', 'gps', 'Modifier les données GPS', 'Permet de modifier les coordonnées GPS'),
('gps.import', 'gps', 'Importer des données GPS', 'Permet d''importer des données GPS (OSM, CSV)'),
('gps.validate', 'gps', 'Valider les coordonnées GPS', 'Permet de valider la cohérence géographique');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('rapports.view', 'rapports', 'Voir les rapports', 'Permet de consulter les rapports'),
('rapports.export_excel', 'rapports', 'Exporter en Excel', 'Permet d''exporter des rapports Excel'),
('rapports.export_pdf', 'rapports', 'Exporter en PDF', 'Permet d''exporter des rapports PDF'),
('rapports.statistics', 'rapports', 'Voir les statistiques', 'Permet d''accéder aux statistiques avancées');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('registre_public.manage', 'registre_public', 'Gérer le registre public', 'Permet de gérer les publications au registre public');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('carte.view', 'carte', 'Voir la carte', 'Permet d''accéder à la carte des infrastructures'),
('carte.export', 'carte', 'Exporter la carte', 'Permet d''exporter les données cartographiques');

INSERT IGNORE INTO permissions (code, module, nom, description) VALUES
('admin.dashboard', 'admin', 'Dashboard admin', 'Permet d''accéder au tableau de bord administrateur'),
('admin.email_logs', 'admin', 'Voir les logs emails', 'Permet de consulter les logs d''emails'),
('admin.test_email', 'admin', 'Tester les emails', 'Permet de tester l''envoi d''emails'),
('admin.system_settings', 'admin', 'Paramètres système', 'Permet de modifier les paramètres du système');

SELECT 'Installation terminee avec succes!' as Message, COUNT(*) as 'Permissions creees' FROM permissions;

SELECT module, COUNT(*) as 'Nombre de permissions' FROM permissions GROUP BY module ORDER BY module;
