# Configuration du CRON - Système de Huitaines

## 🪟 Configuration sous Windows

### Méthode 1 : Script automatique (Recommandé)

1. **Clic droit** sur `configurer_cron_windows.bat`
2. Sélectionner **"Exécuter en tant qu'administrateur"**
3. Suivre les instructions à l'écran

Le script créera automatiquement une tâche planifiée qui s'exécutera **toutes les heures**.

### Méthode 2 : Configuration manuelle

1. Ouvrir le **Planificateur de tâches** Windows
   - Touche Windows + R → `taskschd.msc` → Entrée

2. Dans le panneau de droite, cliquer sur **"Créer une tâche de base"**

3. **Nom** : `SGDI_Verifier_Huitaines`
   **Description** : Vérification automatique des huitaines et envoi des alertes

4. **Déclencheur** : Quotidien
   - Tous les jours à 00:00
   - Cocher "Répéter toutes les 1 heures"
   - Pendant : 24 heures

5. **Action** : Démarrer un programme
   - Programme : `C:\wamp64\bin\php\php8.1.0\php.exe`
   - Arguments : `"C:\wamp64\www\dppg-implantation\cron\verifier_huitaines.php"`
   - Dossier de démarrage : `C:\wamp64\www\dppg-implantation\cron`

6. Cocher **"Exécuter même si l'utilisateur n'est pas connecté"**

7. **Terminer**

### Vérifier la configuration

```cmd
# Lister les tâches planifiées
schtasks /query /tn SGDI_Verifier_Huitaines

# Exécuter manuellement
schtasks /run /tn SGDI_Verifier_Huitaines

# Supprimer la tâche
schtasks /delete /tn SGDI_Verifier_Huitaines /f
```

## 🐧 Configuration sous Linux/Mac

### Éditer le crontab

```bash
crontab -e
```

### Ajouter cette ligne

```bash
# Vérifier les huitaines toutes les heures
0 * * * * /usr/bin/php /path/to/dppg-implantation/cron/verifier_huitaines.php
```

### Vérifier le crontab

```bash
crontab -l
```

### Tester manuellement

```bash
php /path/to/dppg-implantation/cron/verifier_huitaines.php
```

## 📝 Logs

Les logs sont générés dans : `logs/huitaines_YYYY-MM.log`

### Consulter les logs en temps réel

**Windows :**
```cmd
type logs\huitaines_2025-10.log
```

**Linux/Mac :**
```bash
tail -f logs/huitaines_2025-10.log
```

## 🧪 Test manuel

Utilisez le script `tester_cron.bat` (Windows) pour exécuter manuellement le CRON et voir le résultat immédiatement.

## 🔧 Dépannage

### Le CRON ne s'exécute pas

1. **Vérifier que la tâche existe** :
   ```cmd
   schtasks /query /tn SGDI_Verifier_Huitaines
   ```

2. **Vérifier le chemin de PHP** :
   ```cmd
   where php
   ```

3. **Exécuter manuellement** pour voir les erreurs :
   ```cmd
   php cron\verifier_huitaines.php
   ```

### Erreurs dans les logs

Consultez `logs/huitaines_*.log` pour identifier les erreurs.

### Permissions

Assurez-vous que le dossier `logs/` a les permissions d'écriture :

**Windows :** Propriétés → Sécurité → Modifier → Autoriser "Écriture"

**Linux/Mac :**
```bash
chmod 755 logs/
```

## ⏰ Fréquence d'exécution

Par défaut : **Toutes les heures**

Pour modifier :
- **Windows** : Modifier la tâche planifiée
- **Linux/Mac** : Modifier le crontab

Exemples de crontab :
```bash
# Toutes les 30 minutes
*/30 * * * * php /path/to/verifier_huitaines.php

# Toutes les 2 heures
0 */2 * * * php /path/to/verifier_huitaines.php

# Toutes les heures de 8h à 18h
0 8-18 * * * php /path/to/verifier_huitaines.php
```

## 📧 Rapport quotidien

Le rapport quotidien est envoyé automatiquement à **8h00** aux admins et chefs de service.

Pour modifier l'heure :
```php
// Dans cron/verifier_huitaines.php, ligne ~41
if ($heure_actuelle == 8) { // Modifier cette valeur
```

## ✅ Vérification de bon fonctionnement

1. Exécuter manuellement une fois
2. Vérifier qu'un fichier de log est créé
3. Créer une huitaine de test
4. Attendre l'exécution automatique (1 heure max)
5. Vérifier les alertes dans les logs
