-- Ajout du compte Ministre pour Railway
-- Date: 7 novembre 2025
-- À exécuter sur la base de données Railway

-- Insérer le compte ministre si inexistant
-- Mot de passe: Ministre@2025
-- Hash généré avec password_hash('Ministre@2025', PASSWORD_DEFAULT)

INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif)
SELECT 'ministre', 'ministre@minee.cm', '$2y$10$mTQL2.kuw0g4eBPojVmMOehRxiD8t6OBBsX08XiU7H1NjHLR.yayW', 'ministre', 'CABINET', 'Ministre', '+237690000009', 1
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE username = 'ministre'
);

-- Vérifier l'insertion
SELECT
    CASE
        WHEN COUNT(*) > 0 THEN 'Compte ministre créé avec succès!'
        ELSE 'Le compte ministre existait déjà.'
    END as resultat,
    username, email, role, nom, prenom, actif
FROM users
WHERE username = 'ministre';
