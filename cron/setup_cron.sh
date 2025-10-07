#!/bin/bash
# Script de configuration des CRON jobs pour Linux/Unix
# Pour serveurs de production

echo "========================================"
echo "Configuration des CRON jobs SGDI"
echo "========================================"
echo ""

# Chemins (à ajuster selon votre installation)
PHP_PATH="/usr/bin/php"
PROJECT_PATH="/var/www/html/dppg-implantation"

# Vérifier que PHP existe
if [ ! -f "$PHP_PATH" ]; then
    echo "ERREUR: PHP non trouvé à $PHP_PATH"
    echo "Veuillez ajuster PHP_PATH dans ce script"
    exit 1
fi

echo "PHP trouvé: $PHP_PATH"
echo "Projet: $PROJECT_PATH"
echo ""

# Créer une sauvegarde du crontab actuel
echo "Sauvegarde du crontab actuel..."
crontab -l > /tmp/crontab_backup_$(date +%Y%m%d_%H%M%S).txt 2>/dev/null
echo "Sauvegarde créée dans /tmp/"
echo ""

# Préparer les nouvelles lignes cron
CRON_TEMP=$(mktemp)

# Copier le crontab existant (s'il existe)
crontab -l 2>/dev/null | grep -v "SGDI_" > "$CRON_TEMP"

# Ajouter les nouvelles tâches SGDI
cat >> "$CRON_TEMP" << EOF

# ========== SGDI - CRON JOBS ==========
# Vérification des huitaines (toutes les heures)
0 * * * * $PHP_PATH $PROJECT_PATH/cron/verifier_huitaines.php >> $PROJECT_PATH/logs/cron_huitaines.log 2>&1

# Nettoyage des logs (tous les jours à 3h)
0 3 * * * $PHP_PATH $PROJECT_PATH/cron/nettoyer_logs.php >> $PROJECT_PATH/logs/cron_nettoyage.log 2>&1

# Backup base de données (tous les jours à 2h)
0 2 * * * $PHP_PATH $PROJECT_PATH/cron/backup_database.php >> $PROJECT_PATH/logs/cron_backup.log 2>&1

# Statistiques quotidiennes (tous les jours à 8h)
0 8 * * * $PHP_PATH $PROJECT_PATH/cron/statistiques_quotidiennes.php >> $PROJECT_PATH/logs/cron_stats.log 2>&1

# Notifications quotidiennes (tous les jours à 9h)
0 9 * * * $PHP_PATH $PROJECT_PATH/cron/notifications_quotidiennes.php >> $PROJECT_PATH/logs/cron_notifications.log 2>&1

EOF

# Installer le nouveau crontab
crontab "$CRON_TEMP"
rm "$CRON_TEMP"

if [ $? -eq 0 ]; then
    echo "✓ CRON jobs installés avec succès!"
    echo ""
    echo "Tâches configurées:"
    echo "  - Vérification huitaines: Toutes les heures"
    echo "  - Nettoyage logs: Tous les jours à 3h"
    echo "  - Backup DB: Tous les jours à 2h"
    echo "  - Statistiques: Tous les jours à 8h"
    echo "  - Notifications: Tous les jours à 9h"
    echo ""
    echo "Pour voir le crontab:"
    echo "  crontab -l"
    echo ""
    echo "Pour éditer le crontab:"
    echo "  crontab -e"
    echo ""
else
    echo "✗ Erreur lors de l'installation des CRON jobs"
    exit 1
fi
