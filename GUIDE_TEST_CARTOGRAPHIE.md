# 🗺️ Guide de test - Fonctionnalités de cartographie

## ✅ Étape 1 : Vérifier l'installation

La migration SQL a été appliquée avec succès ! Vous pouvez maintenant tester.

## 🧪 Tests à effectuer

### Test 1 : Carte interactive interne (Authentifié)

1. **Connectez-vous** avec un compte `chef_service` ou `admin`
2. **Menu** → Dossiers → **Carte des infrastructures**
   - URL : `http://localhost/dppg-implantation/modules/carte/index.php`
3. **Vérifications** :
   - ✓ La carte s'affiche avec le Cameroun centré
   - ✓ Les filtres fonctionnent (Type, Statut, Région)
   - ✓ Les statistiques s'affichent
   - ✓ Cliquer sur un marqueur affiche les détails

### Test 2 : Ajouter une localisation GPS

1. **Ouvrez un dossier existant**
   - Menu → Dossiers → Liste des dossiers → Cliquez sur un dossier
2. **Dans le menu Actions**, cliquez sur **"Localisation GPS"**
   - URL : `http://localhost/dppg-implantation/modules/dossiers/localisation.php?id=X`
3. **Testez différentes méthodes** :

   **Méthode A : Clic sur la carte**
   - Cliquez n'importe où sur la carte
   - Les coordonnées se remplissent automatiquement
   - Le marqueur est placé

   **Méthode B : Saisie manuelle**
   - Entrez les coordonnées de test :
     - Yaoundé : `3.8667, 11.5167`
     - Douala : `4.0511, 9.7679`
     - Garoua : `9.3014, 13.3964`
   - Le marqueur se place automatiquement

   **Méthode C : Drag & Drop**
   - Cliquez sur le marqueur et déplacez-le
   - Les coordonnées se mettent à jour

4. **Cliquez sur "Enregistrer"**
5. **Vérifiez** :
   - ✓ Message de succès
   - ✓ Si des infrastructures sont à proximité (< 5 km), elles s'affichent avec la distance

### Test 3 : Voir la localisation dans un dossier

1. **Retournez au dossier** (après avoir ajouté des coordonnées)
2. **Vérifications** :
   - ✓ Les coordonnées s'affichent en format décimal
   - ✓ Les coordonnées s'affichent en format DMS (Degrees Minutes Seconds)
   - ✓ Bouton "Voir sur Google Maps" fonctionne
   - ✓ Bouton "Modifier" redirige vers la page de localisation

### Test 4 : Carte publique (Sans authentification)

1. **Déconnectez-vous** ou ouvrez un **navigateur privé**
2. **Accédez à** : `http://localhost/dppg-implantation/public_map.php`
3. **Vérifications** :
   - ✓ La carte s'affiche sans authentification
   - ✓ Seules les infrastructures **autorisées** sont visibles
   - ✓ Les statistiques publiques s'affichent
   - ✓ Les filtres fonctionnent
   - ✓ Clic sur un marqueur affiche les infos publiques
   - ✓ Design professionnel

### Test 5 : Détection de proximité

1. **Créez/modifiez 2 dossiers** avec des coordonnées proches :
   - Dossier A : `3.8667, 11.5167` (Yaoundé centre)
   - Dossier B : `3.8700, 11.5200` (Yaoundé proche - ~400m)
2. **Éditez le dossier B** et allez à "Localisation GPS"
3. **Vérifications** :
   - ✓ Le dossier A apparaît dans "Infrastructures à proximité"
   - ✓ La distance est affichée (< 1 km)
   - ✓ Alerte visuelle en orange/jaune

### Test 6 : Validation des coordonnées

1. **Essayez d'entrer des coordonnées invalides** :
   - `100, 200` (hors limites)
   - `50.5, 3.2` (pas au Cameroun)
   - `abc, xyz` (non numériques)
2. **Vérifications** :
   - ✓ Messages d'erreur appropriés
   - ✓ Le système refuse les coordonnées invalides

### Test 7 : Formats GPS multiples

**Testez ces différents formats dans la page de localisation** :

```
Format décimal :
3.8667, 11.5167

Format DMS :
3°52'0"N 11°31'0"E

Format mixte :
N 3.8667 E 11.5167
```

**Vérifications** :
- ✓ Tous les formats sont acceptés
- ✓ Conversion automatique en format décimal
- ✓ Affichage correct sur la carte

## 📊 Résultats attendus

### Carte interne
- Affiche toutes les infrastructures géolocalisées
- Clustering automatique si beaucoup de marqueurs
- Popup détaillé avec lien vers le dossier

### Carte publique
- Uniquement les infrastructures **autorisées**
- Pas d'informations sensibles
- Design responsive

### Détection de proximité
- Alert automatique si infrastructure < 5 km
- Distance calculée précisément (formule de Haversine)
- Liste complète des infrastructures proches

## 🎯 Coordonnées de test pour le Cameroun

```
Yaoundé (Capitale) : 3.8667, 11.5167
Douala (Port) : 4.0511, 9.7679
Garoua (Nord) : 9.3014, 13.3964
Bafoussam (Ouest) : 5.4781, 10.4179
Bamenda (Nord-Ouest) : 5.9597, 10.1453
Maroua (Extrême-Nord) : 10.5910, 14.3163
Ngaoundéré (Adamaoua) : 7.3167, 13.5833
```

## 🐛 Problèmes courants et solutions

### La carte ne s'affiche pas
**Solution** : Vérifiez votre connexion Internet (Leaflet charge depuis CDN)

### Les coordonnées ne se sauvegardent pas
**Solution** : Vérifiez que la colonne `coordonnees_gps` existe :
```sql
SHOW COLUMNS FROM dossiers LIKE 'coordonnees_gps';
```

### Erreur "table doesn't exist"
**Solution** : Exécutez la migration SQL complète

### Les infrastructures à proximité ne s'affichent pas
**Solution** : Assurez-vous que plusieurs dossiers ont des coordonnées GPS

## ✨ Fonctionnalités avancées à tester

### Clustering
- Ajoutez 20+ dossiers avec coordonnées
- Zoomez/dézoomez sur la carte
- Les marqueurs se regroupent automatiquement

### Responsive
- Testez sur mobile/tablette
- La carte s'adapte automatiquement

### Performance
- Ajoutez 100+ infrastructures
- La carte reste fluide grâce au clustering

## 📝 Checklist finale

- [ ] Carte interne accessible et fonctionnelle
- [ ] Ajout de localisation GPS fonctionne (3 méthodes)
- [ ] Coordonnées affichées dans la vue du dossier
- [ ] Google Maps link fonctionne
- [ ] Carte publique accessible sans auth
- [ ] Détection de proximité fonctionne
- [ ] Validation des coordonnées fonctionne
- [ ] Formats multiples acceptés
- [ ] Statistiques correctes

## 🎉 Félicitations !

Si tous les tests passent, votre système de cartographie est **opérationnel** !

## 📞 Besoin d'aide ?

Si un test échoue :
1. Vérifiez les logs PHP (`error_log`)
2. Vérifiez la console JavaScript (F12)
3. Vérifiez que la migration SQL est complète
4. Vérifiez la connexion Internet (pour les cartes)

---

**Prochaine étape** : Ajouter des coordonnées GPS à tous vos dossiers existants ! 🗺️
