# 🚀 Démarrage rapide - Workflow Huitaine

## ✅ Étape 1 : Base de données (2 minutes)

### Via phpMyAdmin
1. Ouvrir phpMyAdmin : `http://localhost/phpmyadmin`
2. Sélectionner la base `sgdi_mvp`
3. Cliquer sur "Importer"
4. Choisir le fichier : `database/add_huitaine_workflow.sql`
5. Cliquer sur "Exécuter"

### Via ligne de commande
```bash
mysql -u root -p sgdi_mvp < database/add_huitaine_workflow.sql
```

### Vérification
```sql
SHOW TABLES LIKE '%huitaine%';
-- Doit afficher : huitaine, historique_huitaine, alertes_huitaine
```

---

## ✅ Étape 2 : Configuration CRON (1 minute)

### Méthode automatique (Windows)
1. **Clic droit** sur `cron\configurer_cron_windows.bat`
2. Sélectionner **"Exécuter en tant qu'administrateur"**
3. Attendre le message "SUCCÈS!"

✅ **C'est tout !** La tâche est configurée pour s'exécuter toutes les heures.

### Vérification
```cmd
schtasks /query /tn SGDI_Verifier_Huitaines
```

### Test manuel
```cmd
cd cron
tester_cron.bat
```

---

## ✅ Étape 3 : Test fonctionnel (3 minutes)

### 1. Se connecter
- Utilisateur : `chef_service` ou `admin`
- Mot de passe : celui configuré

### 2. Créer une huitaine de test
```
1. Menu → Dossiers
2. Choisir un dossier existant
3. Actions → "Créer une huitaine"
4. Type : Document manquant
5. Description : Test de création
6. Valider
```

✅ **Résultat attendu** : "Huitaine créée avec succès. Délai : 8 jours ouvrables"

### 3. Vérifier l'affichage
```
1. Retourner sur le dossier
2. Observer l'alerte orange en haut
3. Vérifier le compte à rebours
```

✅ **Résultat attendu** : Alerte visible avec nombre de jours restants

### 4. Consulter la liste
```
1. Menu → Huitaines
2. Observer les statistiques
3. Voir la huitaine de test
```

✅ **Résultat attendu** : Liste avec 1 huitaine active

### 5. Régulariser
```
1. Cliquer sur "Régulariser"
2. Commentaire : "Test terminé"
3. Valider
```

✅ **Résultat attendu** : "Huitaine régularisée avec succès"

---

## 🎉 C'est terminé !

Votre système de workflow Huitaine est **opérationnel** !

---

## 📊 Utilisation quotidienne

### Pour créer une huitaine

**Quand ?** Lorsqu'une irrégularité est constatée dans un dossier

**Comment ?**
1. Ouvrir le dossier concerné
2. Menu Actions → "Créer une huitaine"
3. Sélectionner le type d'irrégularité
4. Décrire précisément le problème
5. Valider

**Résultat :** Le demandeur dispose de 8 jours ouvrables pour régulariser

### Pour suivre les huitaines

**Menu → Huitaines** affiche :
- ✅ Statistiques globales
- ⚠️ Huitaines urgentes (≤ 2 jours)
- 🔴 Huitaines expirées
- 📊 Historique complet

### Pour régulariser

1. Menu → Huitaines
2. Trouver la huitaine concernée
3. Cliquer "Régulariser"
4. Saisir un commentaire explicatif
5. Valider

**Effet :** Le dossier reprend son traitement normal

---

## 🔔 Alertes automatiques

Le système envoie automatiquement :

| Moment | Alerte |
|--------|--------|
| **J-2** | ⚠️ Première alerte au demandeur |
| **J-1** | 🚨 Alerte urgente |
| **J** | ⛔ Alerte finale (dernier jour) |
| **Après J** | ❌ Rejet automatique du dossier |

**Rapport quotidien** à 8h00 pour les admins et chefs de service

---

## 🔧 Dépannage express

### Le menu "Huitaines" n'apparaît pas
**Solution** : Vérifier que vous êtes connecté avec un rôle autorisé (chef_service, admin, cadre_dppg, cadre_daj)

### Erreur "Table huitaine doesn't exist"
**Solution** : Exécuter `database/add_huitaine_workflow.sql`

### Le CRON ne fonctionne pas
**Test** :
```cmd
cd cron
tester_cron.bat
```
**Vérifier** : Le fichier `logs/huitaines_2025-10.log` doit être créé

### Pas de logs générés
**Normal** : Si aucune huitaine n'existe, le script ne génère pas de log
**Test** : Créer une huitaine de test pour générer de l'activité

---

## 📱 Accès rapide

| Action | Chemin |
|--------|--------|
| Créer | Dossier → Actions → Créer huitaine |
| Liste | Menu → Huitaines |
| Urgentes | Menu → Huitaines → Filtrer : Urgentes |
| Stats | Dashboard (alerte si urgences) |

---

## 🎯 Indicateurs clés

Dans **Menu → Huitaines**, surveillez :

- 🟡 **Urgents** : Nécessite action rapide
- 🔴 **Expirés** : Rejet imminent
- ⏱️ **Durée moyenne** : Performance du système

---

## 💡 Bonnes pratiques

1. **Consulter quotidiennement** la liste des huitaines urgentes
2. **Régulariser rapidement** pour éviter les rejets automatiques
3. **Être précis** dans la description de l'irrégularité
4. **Documenter** la régularisation avec un commentaire détaillé

---

## 📞 Support

### Documentation complète
- `GUIDE_HUITAINE.md` - Guide utilisateur détaillé
- `INSTALLATION_COMPLETE.md` - Installation complète
- `cron/README_CRON.md` - Configuration CRON

### Logs
Consultez `logs/huitaines_YYYY-MM.log` en cas de problème

### Commandes SQL utiles
```sql
-- Voir toutes les huitaines actives
SELECT * FROM huitaines_actives;

-- Statistiques
SELECT * FROM statistiques_huitaine;

-- Historique d'une huitaine
SELECT * FROM historique_huitaine WHERE huitaine_id = X;
```

---

**Version** : 1.0
**Date** : Octobre 2025
**Statut** : ✅ Production ready
