-- Ajouter les colonnes pour Google OAuth

ALTER TABLE users
ADD COLUMN IF NOT EXISTS google_id VARCHAR(100) NULL UNIQUE AFTER email,
ADD COLUMN IF NOT EXISTS photo_url VARCHAR(500) NULL AFTER google_id;

-- Créer un index sur google_id
CREATE INDEX IF NOT EXISTS idx_google_id ON users(google_id);

-- Commentaires
ALTER TABLE users MODIFY COLUMN google_id VARCHAR(100) NULL COMMENT 'ID Google OAuth pour authentification';
ALTER TABLE users MODIFY COLUMN photo_url VARCHAR(500) NULL COMMENT 'URL photo de profil Google';
