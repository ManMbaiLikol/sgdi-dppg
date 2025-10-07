-- Mise à jour de la localisation des dossiers - SGDI MVP
-- Ajout des champs Arrondissement, Quartier et remplacement d'adresse_precise par lieu_dit

USE sgdi_mvp;

-- Ajouter les nouveaux champs de localisation
ALTER TABLE dossiers
ADD COLUMN arrondissement VARCHAR(100) AFTER ville,
ADD COLUMN quartier VARCHAR(100) AFTER arrondissement;

-- Renommer adresse_precise en lieu_dit
ALTER TABLE dossiers
CHANGE COLUMN adresse_precise lieu_dit TEXT;

-- Mise à jour de la documentation
-- Structure finale de localisation :
-- - region (existant)
-- - ville (existant)
-- - arrondissement (nouveau)
-- - quartier (nouveau)
-- - lieu_dit (renommé depuis adresse_precise)
-- - coordonnees_gps (existant)