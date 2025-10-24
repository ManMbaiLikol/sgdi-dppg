#!/bin/bash
# Script d'export de la base de données Railway vers local
# Usage: bash sync/export_railway_db.sh

echo "═══════════════════════════════════════════════════"
echo "  EXPORT BASE DE DONNÉES RAILWAY → LOCAL"
echo "═══════════════════════════════════════════════════"
echo ""

# Vérifier que Railway CLI est installé
if ! command -v railway &> /dev/null; then
    echo "❌ ERREUR: Railway CLI n'est pas installé!"
    echo ""
    echo "Installation:"
    echo "  npm install -g @railway/cli"
    echo ""
    exit 1
fi

# Vérifier qu'on est dans le bon projet
echo "1. Vérification du projet Railway..."
railway status || {
    echo "❌ ERREUR: Pas de projet Railway lié!"
    echo "Exécutez: railway link"
    exit 1
}

echo "✅ Projet Railway détecté"
echo ""

# Créer le dossier de backup s'il n'existe pas
BACKUP_DIR="sync/backups"
mkdir -p "$BACKUP_DIR"

# Nom du fichier avec timestamp
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="$BACKUP_DIR/railway_backup_$TIMESTAMP.sql"

echo "2. Export de la base de données Railway..."
echo "   Fichier: $BACKUP_FILE"
echo ""

# Obtenir les variables d'environnement de Railway
echo "   Récupération des credentials..."

# Export via Railway CLI
# Note: Railway utilise MYSQL_HOST (avec underscore) et non MYSQLHOST
railway run bash -c "mysqldump -h \$MYSQL_HOST -P \$MYSQL_PORT -u \$MYSQL_USER -p\$MYSQL_PASSWORD \$MYSQL_DATABASE --single-transaction --routines --triggers --events" > "$BACKUP_FILE" 2>&1

if [ $? -eq 0 ]; then
    echo "✅ Export réussi!"
    echo ""

    # Afficher la taille du fichier
    SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    echo "   Taille: $SIZE"

    # Compter les tables
    TABLES=$(grep -c "CREATE TABLE" "$BACKUP_FILE")
    echo "   Tables: $TABLES"

    # Compter les INSERT
    INSERTS=$(grep -c "INSERT INTO" "$BACKUP_FILE")
    echo "   Insertions: $INSERTS"

    echo ""
    echo "3. Fichier prêt à être importé en local"
    echo "   Chemin: $BACKUP_FILE"
    echo ""
    echo "Pour importer en local, exécutez:"
    echo "   bash sync/import_to_local.sh $BACKUP_FILE"
    echo ""

    # Créer un lien symbolique vers le dernier backup
    ln -sf "$(basename "$BACKUP_FILE")" "$BACKUP_DIR/latest.sql"

    echo "✅ Export terminé avec succès!"
else
    echo "❌ ERREUR lors de l'export!"
    echo ""
    echo "Solutions possibles:"
    echo "1. Vérifiez que vous avez accès à la base Railway"
    echo "2. Vérifiez que MySQL est installé localement"
    echo "3. Essayez: railway login"
    exit 1
fi

echo ""
echo "═══════════════════════════════════════════════════"
