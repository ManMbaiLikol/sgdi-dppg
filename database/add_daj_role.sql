-- Ajout du rôle Cadre DAJ manquant - SGDI MVP
-- Le Cadre DAJ effectue l'analyse juridique et réglementaire à l'étape 5 du workflow

USE sgdi_mvp;

-- Ajouter l'utilisateur Cadre DAJ
INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif) VALUES
('daj', 'daj@dppg.cm', '$2y$10$wFQZfFz5z5z5z5z5z5z5z.OQ5yKjO0rOQ5byMi.Ye4oKoEa3Ro9llC', 'cadre_daj', 'MBONGO', 'Celestine', '+237690000007', 1);

-- Ajouter une table pour les analyses juridiques DAJ
CREATE TABLE IF NOT EXISTS analyses_daj (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dossier_id INT NOT NULL,
    daj_user_id INT NOT NULL,
    statut_analyse ENUM('en_cours', 'conforme', 'non_conforme', 'conforme_avec_reserves') DEFAULT 'en_cours',
    observations TEXT,
    documents_manquants TEXT,
    recommandations TEXT,
    date_analyse DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_finalisation DATETIME NULL,

    FOREIGN KEY (dossier_id) REFERENCES dossiers(id) ON DELETE CASCADE,
    FOREIGN KEY (daj_user_id) REFERENCES users(id) ON DELETE CASCADE,

    INDEX idx_dossier_daj (dossier_id),
    INDEX idx_statut_analyse (statut_analyse)
);

-- Ajouter un nouveau statut pour l'analyse DAJ dans le workflow
-- Le statut 'analyse_daj' vient après 'paye' et avant 'controle_completude'