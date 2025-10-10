#!/bin/bash
# Script d'export de la base de données pour Railway
# Auteur: SGDI Team

echo "========================================"
echo "Export Base de Données SGDI pour Railway"
echo "========================================"
echo ""

# Configuration
DB_NAME="sgdi_mvp"
DB_USER="root"
OUTPUT_FILE="database/sgdi_mvp_railway_export.sql"

echo "1. Vérification de mysqldump..."
if ! command -v mysqldump &> /dev/null; then
    echo "ERREUR: mysqldump n'est pas installé ou n'est pas dans le PATH"
    echo "Installation:"
    echo "  - Ubuntu/Debian: sudo apt-get install mysql-client"
    echo "  - macOS: brew install mysql-client"
    exit 1
fi

echo "mysqldump trouvé: $(which mysqldump)"
echo ""

echo "2. Export de la base de données ${DB_NAME}..."
echo ""

# Demander le mot de passe
read -sp "Mot de passe MySQL (vide si aucun): " DB_PASSWORD
echo ""

# Export avec options optimisées pour Railway
if [ -z "$DB_PASSWORD" ]; then
    # Sans mot de passe
    mysqldump \
        --user="${DB_USER}" \
        --host=localhost \
        --port=3306 \
        --single-transaction \
        --routines \
        --triggers \
        --add-drop-table \
        --complete-insert \
        --default-character-set=utf8mb4 \
        "${DB_NAME}" > "${OUTPUT_FILE}"
else
    # Avec mot de passe
    mysqldump \
        --user="${DB_USER}" \
        --password="${DB_PASSWORD}" \
        --host=localhost \
        --port=3306 \
        --single-transaction \
        --routines \
        --triggers \
        --add-drop-table \
        --complete-insert \
        --default-character-set=utf8mb4 \
        "${DB_NAME}" > "${OUTPUT_FILE}"
fi

if [ $? -eq 0 ]; then
    echo ""
    echo "========================================"
    echo "SUCCÈS!"
    echo "========================================"
    echo ""
    echo "Fichier créé: ${OUTPUT_FILE}"
    ls -lh "${OUTPUT_FILE}"
    echo ""
    echo "Prochaines étapes:"
    echo "1. Ouvrir HeidiSQL ou MySQL Workbench"
    echo "2. Se connecter à Railway (voir database/IMPORT_RAILWAY.md)"
    echo "3. Importer le fichier: ${OUTPUT_FILE}"
    echo ""
    echo "Guide complet: database/IMPORT_RAILWAY.md"
    echo "========================================"
else
    echo ""
    echo "ERREUR lors de l'export!"
    echo "Vérifiez:"
    echo "- Le mot de passe MySQL"
    echo "- Que MySQL est démarré"
    echo "- Que la base ${DB_NAME} existe"
    exit 1
fi
