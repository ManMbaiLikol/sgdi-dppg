-- ============================================================================
-- SCHEMA ADMINISTRATION - Tables de paramétrage système
-- ============================================================================

-- Table des paramètres système
CREATE TABLE IF NOT EXISTS parametres_systeme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(100) NOT NULL UNIQUE,
    valeur TEXT,
    type ENUM('string', 'number', 'boolean', 'json', 'date') DEFAULT 'string',
    categorie VARCHAR(50) NOT NULL,
    description TEXT,
    modifiable BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES users(id),
    INDEX idx_categorie (categorie),
    INDEX idx_cle (cle)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des délais configurables
CREATE TABLE IF NOT EXISTS delais_configuration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    libelle VARCHAR(255) NOT NULL,
    duree_jours INT NOT NULL DEFAULT 8,
    type_delai ENUM('huitaine', 'inspection', 'paiement', 'visa', 'autre') DEFAULT 'autre',
    notification_j_moins_2 BOOLEAN DEFAULT TRUE,
    notification_j_moins_1 BOOLEAN DEFAULT TRUE,
    notification_j BOOLEAN DEFAULT TRUE,
    action_expiration ENUM('aucune', 'notification', 'blocage', 'rejet_auto') DEFAULT 'notification',
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type_delai),
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de tarification
CREATE TABLE IF NOT EXISTS tarification (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    libelle VARCHAR(255) NOT NULL,
    type_infrastructure_id INT,
    montant_base DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    montant_min DECIMAL(15,2),
    montant_max DECIMAL(15,2),
    devise VARCHAR(3) DEFAULT 'XAF',
    calcul_auto BOOLEAN DEFAULT TRUE,
    formule_calcul TEXT,
    actif BOOLEAN DEFAULT TRUE,
    date_debut DATE,
    date_fin DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (type_infrastructure_id) REFERENCES types_infrastructure(id),
    INDEX idx_type_infra (type_infrastructure_id),
    INDEX idx_dates (date_debut, date_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des documents requis par type
CREATE TABLE IF NOT EXISTS documents_requis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_infrastructure_id INT NOT NULL,
    type_document_id INT NOT NULL,
    obligatoire BOOLEAN DEFAULT TRUE,
    ordre_affichage INT DEFAULT 0,
    description_aide TEXT,
    format_accepte VARCHAR(255) DEFAULT 'pdf,doc,docx,jpg,png',
    taille_max_mo INT DEFAULT 10,
    nombre_exemplaires INT DEFAULT 1,
    actif BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (type_infrastructure_id) REFERENCES types_infrastructure(id) ON DELETE CASCADE,
    FOREIGN KEY (type_document_id) REFERENCES types_document(id) ON DELETE CASCADE,
    UNIQUE KEY unique_doc_infra (type_infrastructure_id, type_document_id),
    INDEX idx_type_infra (type_infrastructure_id),
    INDEX idx_obligatoire (obligatoire)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des templates de documents
CREATE TABLE IF NOT EXISTS templates_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    nom VARCHAR(255) NOT NULL,
    type ENUM('note_frais', 'courrier', 'rapport', 'decision', 'recu', 'autre') NOT NULL,
    format ENUM('docx', 'pdf', 'html', 'odt') DEFAULT 'docx',
    fichier_template VARCHAR(255),
    contenu_html TEXT,
    variables_disponibles JSON,
    description TEXT,
    actif BOOLEAN DEFAULT TRUE,
    version VARCHAR(10) DEFAULT '1.0',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type (type),
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des backups
CREATE TABLE IF NOT EXISTS backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('manuel', 'automatique', 'avant_maj') NOT NULL DEFAULT 'automatique',
    nom_fichier VARCHAR(255) NOT NULL,
    chemin_fichier VARCHAR(500) NOT NULL,
    taille_mo DECIMAL(10,2),
    statut ENUM('en_cours', 'termine', 'erreur') DEFAULT 'en_cours',
    nb_tables INT,
    nb_lignes INT,
    duree_secondes INT,
    message_erreur TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type (type),
    INDEX idx_statut (statut),
    INDEX idx_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table d'archivage
CREATE TABLE IF NOT EXISTS archives_dossiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dossier_id INT NOT NULL,
    numero_dossier VARCHAR(50) NOT NULL,
    date_archivage TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    raison_archivage ENUM('anciennete', 'cloture', 'rejet', 'manuel', 'rgpd') NOT NULL,
    donnees_archivees JSON,
    documents_archives JSON,
    archiveur_id INT,
    lieu_archivage_physique VARCHAR(255),
    notes_archivage TEXT,
    date_destruction_prevue DATE,
    detruit BOOLEAN DEFAULT FALSE,
    date_destruction DATE,
    FOREIGN KEY (archiveur_id) REFERENCES users(id),
    INDEX idx_numero (numero_dossier),
    INDEX idx_date_archivage (date_archivage),
    INDEX idx_raison (raison_archivage),
    INDEX idx_destruction (date_destruction_prevue, detruit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de monitoring système
CREATE TABLE IF NOT EXISTS monitoring_systeme (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_metrique ENUM('cpu', 'memoire', 'disque', 'requetes', 'sessions', 'erreurs', 'autre') NOT NULL,
    nom_metrique VARCHAR(100) NOT NULL,
    valeur DECIMAL(15,2),
    unite VARCHAR(20),
    seuil_alerte DECIMAL(15,2),
    niveau ENUM('info', 'warning', 'critical') DEFAULT 'info',
    message TEXT,
    donnees_json JSON,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type (type_metrique),
    INDEX idx_timestamp (timestamp),
    INDEX idx_niveau (niveau)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des webhooks
CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    url VARCHAR(500) NOT NULL,
    evenement VARCHAR(100) NOT NULL,
    methode ENUM('GET', 'POST', 'PUT', 'DELETE') DEFAULT 'POST',
    headers JSON,
    secret VARCHAR(255),
    actif BOOLEAN DEFAULT TRUE,
    retry_max INT DEFAULT 3,
    timeout_secondes INT DEFAULT 30,
    derniere_execution TIMESTAMP NULL,
    dernier_statut ENUM('succes', 'erreur', 'timeout') NULL,
    nb_executions INT DEFAULT 0,
    nb_succes INT DEFAULT 0,
    nb_erreurs INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_evenement (evenement),
    INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table logs webhooks
CREATE TABLE IF NOT EXISTS webhooks_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    evenement VARCHAR(100),
    payload JSON,
    statut_http INT,
    reponse TEXT,
    duree_ms INT,
    erreur TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE,
    INDEX idx_webhook (webhook_id),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des API keys
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    cle VARCHAR(64) NOT NULL UNIQUE,
    secret VARCHAR(255) NOT NULL,
    user_id INT,
    permissions JSON,
    ip_autorisees TEXT,
    rate_limit_par_heure INT DEFAULT 1000,
    nb_requetes_total INT DEFAULT 0,
    derniere_utilisation TIMESTAMP NULL,
    actif BOOLEAN DEFAULT TRUE,
    expire_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_cle (cle),
    INDEX idx_actif (actif)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table logs API
CREATE TABLE IF NOT EXISTS api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key_id INT,
    endpoint VARCHAR(255) NOT NULL,
    methode VARCHAR(10) NOT NULL,
    ip_client VARCHAR(45),
    user_agent TEXT,
    params JSON,
    statut_http INT,
    reponse_json JSON,
    duree_ms INT,
    erreur TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE SET NULL,
    INDEX idx_api_key (api_key_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_endpoint (endpoint)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des exports comptables
CREATE TABLE IF NOT EXISTS exports_comptables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type_export ENUM('paiements', 'recettes', 'journal', 'balance') NOT NULL,
    format ENUM('csv', 'excel', 'sage', 'ciel', 'ebp') DEFAULT 'csv',
    date_debut DATE NOT NULL,
    date_fin DATE NOT NULL,
    nb_lignes INT DEFAULT 0,
    montant_total DECIMAL(15,2) DEFAULT 0.00,
    fichier_export VARCHAR(255),
    statut ENUM('en_cours', 'termine', 'erreur') DEFAULT 'en_cours',
    message_erreur TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_type (type_export),
    INDEX idx_dates (date_debut, date_fin),
    INDEX idx_statut (statut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERTION DES DONNÉES INITIALES
-- ============================================================================

-- Paramètres système par défaut
INSERT INTO parametres_systeme (cle, valeur, type, categorie, description) VALUES
('app_name', 'SGDI', 'string', 'general', 'Nom de l\'application'),
('app_version', '1.0.0', 'string', 'general', 'Version de l\'application'),
('maintenance_mode', 'false', 'boolean', 'general', 'Mode maintenance activé'),
('max_upload_size_mo', '10', 'number', 'fichiers', 'Taille maximale upload en Mo'),
('formats_acceptes', 'pdf,doc,docx,jpg,jpeg,png', 'string', 'fichiers', 'Formats de fichiers acceptés'),
('session_timeout_minutes', '120', 'number', 'securite', 'Durée de session en minutes'),
('password_min_length', '8', 'number', 'securite', 'Longueur minimale mot de passe'),
('backup_auto_enabled', 'true', 'boolean', 'backup', 'Backup automatique activé'),
('backup_auto_heure', '02:00', 'string', 'backup', 'Heure du backup automatique'),
('backup_retention_jours', '30', 'number', 'backup', 'Durée conservation backups en jours'),
('email_notifications_enabled', 'true', 'boolean', 'notifications', 'Notifications email activées'),
('smtp_host', 'smtp.gmail.com', 'string', 'email', 'Serveur SMTP'),
('smtp_port', '587', 'number', 'email', 'Port SMTP'),
('smtp_from_email', 'noreply@minee.gov.cm', 'string', 'email', 'Email expéditeur'),
('smtp_from_name', 'SGDI - MINEE/DPPG', 'string', 'email', 'Nom expéditeur'),
('archivage_auto_enabled', 'true', 'boolean', 'archivage', 'Archivage automatique activé'),
('archivage_delai_mois', '24', 'number', 'archivage', 'Délai avant archivage en mois'),
('rgpd_purge_enabled', 'true', 'boolean', 'rgpd', 'Purge RGPD activée'),
('rgpd_retention_annees', '5', 'number', 'rgpd', 'Durée conservation RGPD en années'),
('api_enabled', 'true', 'boolean', 'api', 'API REST activée'),
('api_rate_limit', '1000', 'number', 'api', 'Limite requêtes API par heure'),
('webhook_enabled', 'true', 'boolean', 'integrations', 'Webhooks activés'),
('monitoring_enabled', 'true', 'boolean', 'monitoring', 'Monitoring système activé'),
('monitoring_interval_minutes', '15', 'number', 'monitoring', 'Intervalle monitoring en minutes');

-- Délais par défaut
INSERT INTO delais_configuration (code, libelle, duree_jours, type_delai, action_expiration) VALUES
('huitaine_standard', 'Huitaine standard de régularisation', 8, 'huitaine', 'rejet_auto'),
('delai_inspection', 'Délai pour réaliser l\'inspection', 15, 'inspection', 'notification'),
('delai_paiement', 'Délai de paiement après note de frais', 30, 'paiement', 'notification'),
('delai_visa_chef', 'Délai visa Chef de Service', 5, 'visa', 'notification'),
('delai_visa_sous_directeur', 'Délai visa Sous-Directeur', 3, 'visa', 'notification'),
('delai_visa_directeur', 'Délai visa Directeur', 3, 'visa', 'notification'),
('delai_analyse_daj', 'Délai analyse juridique DAJ', 10, 'autre', 'notification'),
('delai_rapport_inspection', 'Délai rédaction rapport inspection', 7, 'inspection', 'notification');

-- Tarification par défaut
INSERT INTO tarification (code, libelle, montant_base, devise, calcul_auto) VALUES
('implantation_station', 'Frais implantation station-service', 500000.00, 'XAF', TRUE),
('reprise_station', 'Frais reprise station-service', 300000.00, 'XAF', TRUE),
('implantation_point_conso', 'Frais implantation point consommateur', 200000.00, 'XAF', TRUE),
('reprise_point_conso', 'Frais reprise point consommateur', 150000.00, 'XAF', TRUE),
('implantation_depot_gpl', 'Frais implantation dépôt GPL', 750000.00, 'XAF', TRUE),
('implantation_centre_emplisseur', 'Frais implantation centre emplisseur', 1000000.00, 'XAF', TRUE),
('frais_deplacement', 'Frais déplacement commission', 50000.00, 'XAF', FALSE),
('frais_expertise', 'Frais expertise technique', 100000.00, 'XAF', FALSE);

-- Templates de documents par défaut
INSERT INTO templates_documents (code, nom, type, format, variables_disponibles, description) VALUES
('note_frais_standard', 'Note de frais standard', 'note_frais', 'html',
 '["numero_dossier", "nom_demandeur", "type_infrastructure", "montant", "date_emission"]',
 'Template standard pour les notes de frais'),
('recu_paiement', 'Reçu de paiement', 'recu', 'html',
 '["numero_recu", "numero_dossier", "montant", "date_paiement", "mode_paiement", "nom_demandeur"]',
 'Reçu de paiement officiel'),
('courrier_convocation', 'Courrier de convocation commission', 'courrier', 'html',
 '["numero_dossier", "date_visite", "lieu_visite", "membres_commission"]',
 'Convocation des membres de la commission'),
('rapport_inspection_type', 'Rapport d\'inspection type', 'rapport', 'html',
 '["numero_dossier", "date_inspection", "lieu", "inspecteur", "observations", "conclusion"]',
 'Template de rapport d\'inspection'),
('decision_approbation', 'Décision d\'approbation', 'decision', 'html',
 '["numero_dossier", "nom_demandeur", "type_infrastructure", "date_decision", "reference"]',
 'Décision d\'approbation d\'implantation'),
('decision_rejet', 'Décision de rejet', 'decision', 'html',
 '["numero_dossier", "nom_demandeur", "motif_rejet", "date_decision"]',
 'Décision de rejet de demande');

-- ============================================================================
-- VUES UTILES
-- ============================================================================

-- Vue résumé des backups récents
CREATE OR REPLACE VIEW v_backups_recents AS
SELECT
    b.*,
    CONCAT(u.prenom, ' ', u.nom) as createur
FROM backups b
LEFT JOIN users u ON b.created_by = u.id
WHERE b.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY b.created_at DESC;

-- Vue statistiques API
CREATE OR REPLACE VIEW v_stats_api AS
SELECT
    ak.nom,
    ak.actif,
    COUNT(al.id) as nb_requetes_24h,
    AVG(al.duree_ms) as duree_moyenne_ms,
    SUM(CASE WHEN al.statut_http >= 200 AND al.statut_http < 300 THEN 1 ELSE 0 END) as nb_succes,
    SUM(CASE WHEN al.statut_http >= 400 THEN 1 ELSE 0 END) as nb_erreurs
FROM api_keys ak
LEFT JOIN api_logs al ON ak.id = al.api_key_id
    AND al.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY ak.id, ak.nom, ak.actif;

-- Vue monitoring alertes
CREATE OR REPLACE VIEW v_monitoring_alertes AS
SELECT
    type_metrique,
    nom_metrique,
    valeur,
    seuil_alerte,
    niveau,
    message,
    timestamp
FROM monitoring_systeme
WHERE niveau IN ('warning', 'critical')
    AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY
    CASE niveau
        WHEN 'critical' THEN 1
        WHEN 'warning' THEN 2
        ELSE 3
    END,
    timestamp DESC;
