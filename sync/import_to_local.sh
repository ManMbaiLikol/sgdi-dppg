#!/bin/bash
# Script d'import d'un dump SQL dans la base de données locale
# Usage: bash sync/import_to_local.sh [fichier.sql]

echo "═══════════════════════════════════════════════════"
echo "  IMPORT BASE DE DONNÉES DANS LOCAL"
echo "═══════════════════════════════════════════════════"
echo ""

# Vérifier qu'un fichier est fourni
BACKUP_FILE="${1:-sync/backups/latest.sql}"

if [ ! -f "$BACKUP_FILE" ]; then
    echo "❌ ERREUR: Fichier introuvable: $BACKUP_FILE"
    echo ""
    echo "Usage:"
    echo "  bash sync/import_to_local.sh [fichier.sql]"
    echo ""
    echo "Ou d'abord exporter depuis Railway:"
    echo "  bash sync/export_railway_db.sh"
    exit 1
fi

echo "1. Fichier à importer:"
echo "   $BACKUP_FILE"
SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo "   Taille: $SIZE"
echo ""

# Lire la configuration locale
if [ ! -f "config/database.php" ]; then
    echo "❌ ERREUR: config/database.php introuvable!"
    exit 1
fi

echo "2. Lecture de la configuration locale..."

# Extraire les credentials du fichier PHP (simple parsing)
DB_HOST=$(grep "DB_HOST" config/database.php | grep -oP "(?<=')[^']+(?=')" | head -1)
DB_NAME=$(grep "DB_NAME" config/database.php | grep -oP "(?<=')[^']+(?=')" | head -1)
DB_USER=$(grep "DB_USER" config/database.php | grep -oP "(?<=')[^']+(?=')" | head -1)
DB_PASS=$(grep "DB_PASS" config/database.php | grep -oP "(?<=')[^']+(?=')" | head -1)

# Valeurs par défaut si non trouvées
DB_HOST="${DB_HOST:-localhost}"
DB_NAME="${DB_NAME:-sgdi_mvp}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

echo "   Host: $DB_HOST"
echo "   Database: $DB_NAME"
echo "   User: $DB_USER"
echo ""

# Demander confirmation
read -p "⚠️  ATTENTION: Cela va REMPLACER toutes les données locales. Continuer? (oui/non): " CONFIRM

if [ "$CONFIRM" != "oui" ]; then
    echo "❌ Import annulé"
    exit 0
fi

echo ""
echo "3. Création d'un backup de sécurité de la base locale..."

BACKUP_LOCAL="sync/backups/local_backup_before_import_$(date +"%Y%m%d_%H%M%S").sql"
mkdir -p sync/backups

if [ -z "$DB_PASS" ]; then
    mysqldump -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$BACKUP_LOCAL" 2>&1
else
    mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$BACKUP_LOCAL" 2>&1
fi

if [ $? -eq 0 ]; then
    echo "✅ Backup local créé: $BACKUP_LOCAL"
else
    echo "⚠️  Impossible de créer le backup local (la base n'existe peut-être pas encore)"
fi

echo ""
echo "4. Suppression de la base de données locale..."

if [ -z "$DB_PASS" ]; then
    mysql -h "$DB_HOST" -u "$DB_USER" -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1
else
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "DROP DATABASE IF EXISTS $DB_NAME; CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1
fi

if [ $? -eq 0 ]; then
    echo "✅ Base recréée"
else
    echo "❌ ERREUR lors de la recréation de la base"
    exit 1
fi

echo ""
echo "5. Import du dump Railway dans la base locale..."
echo "   Cela peut prendre quelques secondes..."

if [ -z "$DB_PASS" ]; then
    mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" < "$BACKUP_FILE" 2>&1
else
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE" 2>&1
fi

if [ $? -eq 0 ]; then
    echo "✅ Import réussi!"
    echo ""

    # Statistiques
    echo "6. Statistiques de la base importée:"

    if [ -z "$DB_PASS" ]; then
        TABLES=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SHOW TABLES" | wc -l)
        USERS=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(*) FROM users" -s -N)
        DOSSIERS=$(mysql -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" -e "SELECT COUNT(*) FROM dossiers" -s -N)
    else
        TABLES=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES" | wc -l)
        USERS=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) FROM users" -s -N)
        DOSSIERS=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COUNT(*) FROM dossiers" -s -N)
    fi

    echo "   Tables: $TABLES"
    echo "   Utilisateurs: $USERS"
    echo "   Dossiers: $DOSSIERS"
    echo ""

    echo "✅ SYNCHRONISATION TERMINÉE AVEC SUCCÈS!"
    echo ""
    echo "Votre base locale contient maintenant les mêmes données que Railway."
    echo "Vous pouvez tester et débugger avec les vraies données des utilisateurs."
else
    echo "❌ ERREUR lors de l'import!"
    echo ""
    echo "Solutions possibles:"
    echo "1. Vérifiez que MySQL est démarré (WAMP)"
    echo "2. Vérifiez les credentials dans config/database.php"
    echo "3. Vérifiez que le fichier SQL n'est pas corrompu"
    exit 1
fi

echo "═══════════════════════════════════════════════════"
