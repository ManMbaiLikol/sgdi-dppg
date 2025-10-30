# Ajout des champs Contrat d'Approvisionnement

## Date : 2025-10-25

## Nouveaux champs ajoutés

Dans la section **3. INFORMATIONS TECHNIQUES** pour les **Points Consommateurs**, deux nouveaux champs ont été ajoutés :

1. **Numéro du contrat d'approvisionnement** (texte)
2. **Nom de la société contractante** (texte)

---

## 🚀 Installation

### Étape unique : Appliquer la migration SQL

```bash
mysql -u root sgdi_mvp < database/migrations/2025_10_25_add_contrat_approvisionnement_fields.sql
```

**OU via phpMyAdmin** :
1. Ouvrir phpMyAdmin
2. Sélectionner la base `sgdi_mvp`
3. Onglet SQL
4. Copier/coller le contenu du fichier `database/migrations/2025_10_25_add_contrat_approvisionnement_fields.sql`
5. Exécuter

---

## ✅ Vérification

Pour vérifier que les champs ont été ajoutés :

```sql
DESCRIBE fiches_inspection;
```

Vous devriez voir les nouveaux champs :
- `numero_contrat_approvisionnement`
- `societe_contractante`

---

## 📋 Modifications apportées

### 1. Base de données
**Fichier** : `database/migrations/2025_10_25_add_contrat_approvisionnement_fields.sql`

Ajout de 2 nouveaux champs :
- `numero_contrat_approvisionnement` (VARCHAR 100)
- `societe_contractante` (VARCHAR 200)

### 2. Formulaire
**Fichier** : `modules/fiche_inspection/edit.php`

Les deux nouveaux champs apparaissent **en premier** dans la section 3, avant les autres champs pour les points consommateurs.

### 3. Backend
**Fichier** : `modules/fiche_inspection/functions.php`

Fonction `mettreAJourFicheInspection()` mise à jour pour gérer les deux nouveaux champs.

---

## 🎯 Ordre des champs - Section 3 (Point Consommateur)

1. ⭐ **Numéro du contrat d'approvisionnement** (nouveau)
2. ⭐ **Nom de la société contractante** (nouveau)
3. Besoins moyens mensuels en produits pétroliers (litres)
4. Nombre de personnels employés
5. Superficie du site (m²)
6. Système de récupération des huiles usées
7. Parc d'engin de la société
8. Bâtiments du site
9. Infrastructures d'approvisionnement (Eau, Électricité)
10. Réseaux de télécommunication (CAMTEL, MTN, ORANGE, NEXTTEL)

---

## 📸 Aperçu

```
┌─────────────────────────────────────────────────────┐
│ Section 3 - Point Consommateur                      │
├─────────────────────────────────────────────────────┤
│ Numéro du contrat d'approvisionnement               │
│ [_______________________________] (Ex: CTR-2025-001)│
│                                                     │
│ Nom de la société contractante                      │
│ [_______________________________] (Nom de la société)│
│                                                     │
│ Besoins moyens mensuels en produits pétroliers     │
│ [_____________________] litres                      │
│                                                     │
│ ... (autres champs)                                 │
└─────────────────────────────────────────────────────┘
```

---

## 🔄 Compatibilité

✅ **Rétrocompatibilité totale** :
- Les champs sont optionnels (NULL)
- Les fiches existantes continuent de fonctionner
- Pas de perte de données

✅ **Uniquement pour Points Consommateurs** :
- Ces champs n'apparaissent PAS pour les stations-services
- Le formulaire s'adapte automatiquement selon le type d'infrastructure

---

## 📦 Total des champs Points Consommateurs

Avec cette mise à jour, la section 3 pour les points consommateurs contient maintenant **16 champs** :

- 2 champs contrat (nouveaux)
- 6 champs quantitatifs
- 2 champs infrastructures
- 4 champs réseaux
- 2 champs textes longs

---

**Développé par** : Claude Code
**Date** : 2025-10-25
