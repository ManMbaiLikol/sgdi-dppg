# 🚀 Guide Rapide: Synchronisation Railway → Local

## ✅ Solution à votre problème

Vous avez dit:
> "Les erreurs détectées sont côté serveur Railway et proviennent des tests des utilisateurs en ligne. Les données ne sont pas en local, ce qui fausse les modifications."

**Solution créée**: Système complet de synchronisation de la base de données!

---

## 🎯 Usage Immédiat (30 secondes)

### Sur Windows (WAMP):

```batch
sync\sync_railway_to_local.bat
```

C'est TOUT! Le script fait automatiquement:
1. ✅ Exporte la base Railway
2. ✅ Sauvegarde votre base locale
3. ✅ Importe les données Railway en local

---

## 📋 Étapes en détail

### 1️⃣ Prérequis (une seule fois)

**Vérifiez que Railway CLI est lié:**
```bash
railway status
```

**Si erreur, liez le projet:**
```bash
cd C:\wamp64\www\dppg-implantation
railway link
```
Sélectionnez: `genuine-determination` → `sgdi-dppg`

### 2️⃣ Synchronisation

**Option A: Script Windows (RECOMMANDÉ)**
```batch
sync\sync_railway_to_local.bat
```

**Option B: PHP**
```bash
bash sync/export_railway_db.sh
php sync/import_to_local.php sync/backups/latest.sql
```

### 3️⃣ Vérification

Le script affiche:
```
Statistiques de la base importée:
   Tables: 25
   Utilisateurs: 46          ← Doit correspondre à Railway
   Dossiers: 14              ← Doit correspondre à Railway
   Rôles users: enum('admin','chef_service',...)
   Rôles commission: enum('chef_service','chef_commission',...)
```

---

## 💡 Cas d'usage: Débugger l'erreur de constitution commission

### Avant (problème):
- ❌ Bug signalé sur Railway
- ❌ Données différentes en local
- ❌ Impossible de reproduire le bug

### Maintenant (solution):

```bash
# 1. Synchroniser les données
sync\sync_railway_to_local.bat

# 2. Reproduire le bug EXACTEMENT comme sur Railway
# 3. Corriger le code
# 4. Tester que ça fonctionne avec les vraies données
# 5. Pusher

git add .
git commit -m "Fix: Bug constitution commission"
git push origin main
```

---

## 🔍 Testez MAINTENANT

### Étape 1: Synchronisez
```batch
sync\sync_railway_to_local.bat
```

### Étape 2: Vérifiez dans phpMyAdmin
- Ouvrez http://localhost/phpmyadmin
- Base `sgdi_mvp`
- Table `users` → Vérifiez le nombre de lignes
- Table `dossiers` → Vérifiez le nombre de lignes
- Table `commissions` → Regardez les données

### Étape 3: Testez l'erreur de constitution
- Allez sur http://localhost/dppg-implantation/
- Essayez de constituer une commission
- Vous travaillez maintenant avec les VRAIES données Railway!

---

## 📊 Bénéfices

### Développement plus efficace:
- ✅ Reproduire exactement les bugs signalés
- ✅ Tester avec les vraies données utilisateurs
- ✅ Vérifier que les migrations fonctionnent avant Railway
- ✅ Debug avec le contexte complet

### Workflow amélioré:
```
1. Bug signalé sur Railway
   ↓
2. Sync Railway → Local
   ↓
3. Reproduire le bug en local
   ↓
4. Corriger et tester
   ↓
5. Push vers Railway
   ↓
6. Vérifier que c'est corrigé
```

---

## ⚠️ Important

### Ce qui est synchronisé:
- ✅ Structure de la base (tables, colonnes, index, ENUMs)
- ✅ Données utilisateurs
- ✅ Données dossiers, commissions, etc.
- ✅ Tout sauf les mots de passe en clair (hashés)

### Ce qui n'est PAS synchronisé:
- ❌ Fichiers uploadés (`uploads/`)
- ❌ Configuration PHP (`config/`)
- ❌ Code source (déjà via git)

### Sécurité:
- ✅ Backups locaux uniquement (pas de cloud)
- ✅ Dossier `sync/backups/` ignoré par git
- ✅ Aucun mot de passe en clair

---

## 🛠️ Dépannage rapide

### Erreur: "Railway CLI non installé"
```bash
npm install -g @railway/cli
railway login
```

### Erreur: "Projet Railway non lié"
```bash
cd C:\wamp64\www\dppg-implantation
railway link
```

### Erreur: "mysql: command not found"
Ajoutez au PATH Windows:
```
C:\wamp64\bin\mysql\mysql8.0.x\bin
```

### Erreur: "Access denied"
Vérifiez `config/database.php`:
```php
define('DB_PASS', ''); // Souvent vide sur WAMP
```

---

## 📖 Documentation complète

Consultez `sync/README.md` pour:
- Installation détaillée
- Toutes les options de synchronisation
- Cas d'usage avancés
- Maintenance et automatisation
- 150+ lignes de documentation

---

## 🎬 Prochaines étapes

1. **MAINTENANT**: Testez la synchronisation
   ```batch
   sync\sync_railway_to_local.bat
   ```

2. **Ensuite**: Reproduisez l'erreur de constitution commission avec les vraies données

3. **Puis**: Corrigez l'erreur en sachant que vous travaillez avec les mêmes données que Railway

4. **Enfin**: Pushez et vérifiez sur Railway

---

## ✨ Résultat attendu

Après synchronisation, quand vous testez en local:
- ✅ Même nombre d'utilisateurs que Railway
- ✅ Même nombre de dossiers que Railway
- ✅ Mêmes ENUMs, mêmes contraintes
- ✅ Bugs reproductibles EXACTEMENT
- ✅ Corrections testables avant déploiement

**Votre développement est maintenant aligné avec la production!** 🎯

---

Date: 24 octobre 2025
Auteur: Claude Code
