# Matrice de Permissions - SGDI

> Documentation complète des permissions et contrôles d'accès du système

## Vue d'ensemble

Le système utilise **9 rôles utilisateur** avec des permissions spécifiques pour chaque module. Tous les fichiers PHP accessibles via HTTP sont protégés par des contrôles d'authentification et d'autorisation.

---

## Système de contrôle d'accès

### Fonctions de sécurité (includes/auth.php)

| Fonction | Description | Usage |
|----------|-------------|-------|
| `isLoggedIn()` | Vérifie si l'utilisateur est connecté | Condition dans les templates |
| `requireLogin()` | Force la connexion (toutes pages privées) | Début de fichier PHP |
| `hasRole($role)` | Vérifie un rôle spécifique | Condition dans les templates |
| `requireRole($role)` | Force un rôle spécifique | Début de fichier PHP |
| `hasAnyRole($roles)` | Vérifie plusieurs rôles possibles | Condition dans les templates |
| `requireAnyRole($roles)` | Force l'un des rôles listés | Début de fichier PHP |

---

## Les 9 Rôles du système

| Code | Libellé | Description |
|------|---------|-------------|
| `admin` | Admin Système | Gestion complète du système |
| `chef_service` | Chef de Service SDTD | Création dossiers, nomination commissions, visa 1er niveau |
| `billeteur` | Billeteur DPPG | Enregistrement paiements et édition reçus |
| `cadre_daj` | Cadre DAJ | Analyse juridique et conformité réglementaire |
| `cadre_dppg` | Inspecteur DPPG | Inspections infrastructures et rapports |
| `chef_commission` | Chef de Commission | Coordination visites et validation rapports |
| `sous_directeur` | Sous-Directeur SDTD | Visa 2ème niveau |
| `directeur` | Directeur DPPG | Visa 3ème niveau et transmission ministre |
| `ministre` | Cabinet/Secrétariat Ministre | Décision finale (autorisation/refus) |

---

## Matrice de Permissions par Module

### 1. Module Authentification (modules/auth/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `login.php` | Public | Tous | Page de connexion |
| `logout.php` | `requireLogin()` | Tous connectés | Déconnexion |
| `register.php` | N/A | N/A | Désactivé (création par admin uniquement) |

### 2. Module Utilisateurs (modules/users/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `list.php` | `requireRole('admin')` | Admin | Liste utilisateurs |
| `create.php` | `requireRole('admin')` | Admin | Création utilisateur |
| `edit.php` | `requireRole('admin')` | Admin | Modification utilisateur |
| `delete.php` | `requireRole('admin')` | Admin | Suppression utilisateur |
| `profile.php` | `requireLogin()` | Tous connectés | Mon profil |
| `change_password.php` | `requireLogin()` | Tous connectés | Changer mot de passe |
| `functions.php` | N/A | N/A | Bibliothèque (pas de contrôle direct) |

### 3. Module Dossiers (modules/dossiers/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `create.php` | `requireRole('chef_service')` | Chef Service | Création dossier |
| `create_wizard.php` | `requireRole('chef_service')` | Chef Service | Assistant création |
| `edit.php` | `requireLogin()` | Tous connectés | Modification selon statut |
| `view.php` | `requireLogin()` | Tous connectés | Visualisation dossier |
| `list.php` | `requireLogin()` | Tous connectés | Liste filtrée par rôle |
| `delete.php` | `requireRole('admin')` | Admin | Suppression dossier |
| `commission.php` | `requireRole('chef_service')` | Chef Service | Nomination commission |
| `paiement.php` | `requireRole('billeteur')` | Billeteur | Enregistrer paiement |
| `analyse_daj.php` | `requireLogin()` | Cadre DAJ | Analyse juridique |
| `inspection.php` | `requireRole('cadre_dppg')` | Inspecteur DPPG | Inspection terrain |
| `apposer_visa.php` | `requireRole('chef_service')` | Chef Service | Visa 1er niveau |
| `apposer_visa_sous_directeur.php` | `requireRole('sous_directeur')` | Sous-Directeur | Visa 2ème niveau |
| `apposer_visa_directeur.php` | `requireRole('directeur')` | Directeur | Visa 3ème niveau |
| `viser_inspections.php` | `requireRole('chef_service')` | Chef Service | Viser inspection |
| `viser_sous_directeur.php` | `requireRole('sous_directeur')` | Sous-Directeur | Viser inspection SD |
| `viser_directeur.php` | `requireRole('directeur')` | Directeur | Viser inspection DIR |
| `decision.php` | `requireRole('directeur')` | Directeur | Transmission ministre |
| `decision_ministre.php` | `requireRole('ministre')` | Ministre | Interface décision |
| `prendre_decision.php` | `requireRole('ministre')` | Ministre | Décision finale |
| `localisation.php` | `requireLogin()` | Tous connectés | Carte localisation |
| `upload_documents.php` | `requireLogin()` | Tous connectés | Upload documents |
| `marquer_autorise.php` | `requireLogin()` | Admin/Chef Service | Marqueur autorisation |
| `validation_geospatiale.php` | `requireLogin()` | Admin/Chef Service | Validation GPS |
| `gestion_operationnelle.php` | `requireLogin()` | Admin/Chef Service | Gestion opérationnelle |
| `functions.php` | N/A | N/A | Bibliothèque |

### 4. Module Commission (modules/chef_commission/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `dashboard.php` | `requireRole('chef_commission')` | Chef Commission | Tableau de bord |
| `list.php` | `requireRole('chef_commission')` | Chef Commission | Mes dossiers |
| `view.php` | `requireRole('chef_commission')` | Chef Commission | Voir dossier |
| `valider_inspection.php` | `requireRole('chef_commission')` | Chef Commission | Valider rapport |
| `valider_inspections.php` | `requireLogin()` | Chef Commission | Valider plusieurs |
| `valider_fiche.php` | `requireLogin()` | Chef Commission | Valider fiche |
| `functions.php` | N/A | N/A | Bibliothèque |

### 5. Module DAJ (modules/daj/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `list.php` | `requireLogin()` | Cadre DAJ | Mes analyses |
| `analyse.php` | `requireLogin()` | Cadre DAJ | Faire analyse |
| `functions.php` | N/A | N/A | Bibliothèque |

### 6. Module Directeur (modules/directeur/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `dashboard.php` | `requireRole('directeur')` | Directeur | Tableau de bord |
| `viser.php` | `requireRole('directeur')` | Directeur | Apposer visa |

### 7. Module Chef Service (modules/chef_service/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `dashboard_avance.php` | `requireRole('chef_service')` | Chef Service | Tableau de bord avancé |

### 8. Module Admin (modules/admin/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `dashboard_avance.php` | `requireRole('admin')` | Admin | Tableau de bord admin |
| `email_logs.php` | `requireRole('admin')` | Admin | Logs emails |
| `test_email.php` | `requireRole('admin')` | Admin | Test envoi email |
| `ajax/get_email_body.php` | `requireRole('admin')` | Admin | AJAX email |

### 9. Module Documents (modules/documents/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `upload.php` | `requireLogin()` | Tous connectés | Upload document |
| `download.php` | `requireLogin()` | Tous connectés | Télécharger document |

### 10. Module Paiements (modules/paiements/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `view.php` | `requireLogin()` | Billeteur/Admin | Voir paiement |
| `functions.php` | N/A | N/A | Bibliothèque |

### 11. Module Notes de Frais (modules/notes_frais/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `list.php` | `requireLogin()` | Admin/Chef Service | Liste notes frais |
| `edit.php` | `requireLogin()` | Admin/Chef Service | Modifier note frais |
| `debug.php` | N/A | N/A | Debug (à sécuriser/supprimer) |
| `debug2.php` | N/A | N/A | Debug (à sécuriser/supprimer) |
| `functions.php` | N/A | N/A | Bibliothèque |

### 12. Module Huitaine (modules/huitaine/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `list.php` | `requireLogin()` | Admin/Chef/Cadre | Liste huitaines |
| `creer.php` | `requireLogin()` | Admin/Chef Service | Créer huitaine |
| `regulariser.php` | `requireLogin()` | Tous connectés | Régulariser huitaine |

### 13. Module Fiche Inspection (modules/fiche_inspection/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `list_dossiers.php` | `requireLogin()` | Inspecteur/Chef | Liste dossiers |
| `edit.php` | `requireLogin()` | Inspecteur DPPG | Éditer fiche |
| `print_blank.php` | `requireLogin()` | Tous connectés | Imprimer fiche vierge |
| `print_prefilled.php` | `requireLogin()` | Inspecteur DPPG | Imprimer pré-remplie |
| `print_filled.php` | `requireLogin()` | Inspecteur DPPG | Imprimer remplie |
| `functions.php` | N/A | N/A | Bibliothèque |

### 14. Module Import Historique (modules/import_historique/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `index.php` | `requireRole('admin')` | Admin | Interface import |
| `upload.php` | `requireRole('admin')` | Admin | Upload CSV |
| `preview.php` | `requireRole('admin')` | Admin | Prévisualisation |
| `import.php` | `requireRole('admin')` | Admin | Importer données |
| `check_structure.php` | N/A | N/A | Vérification structure |
| `functions.php` | N/A | N/A | Bibliothèque |

### 15. Module OSM Extraction (modules/osm_extraction/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `index.php` | `requireLogin()` | Admin/Chef Service | Interface OSM |
| `guide_import.php` | `requireLogin()` | Admin/Chef Service | Guide import |
| `extract_osm_stations.php` | `requireAnyRole(['admin','chef_service'])` | Admin/Chef Service | Extraction OSM |
| `filter_osm_stations.php` | `requireAnyRole(['admin','chef_service'])` | Admin/Chef Service | Filtrage stations |
| `match_minee_osm.php` | `requireAnyRole(['admin','chef_service'])` | Admin/Chef Service | Matching MINEE/OSM |
| `convert_for_import.php` | `requireAnyRole(['admin','chef_service'])` | Admin/Chef Service | Conversion CSV |

### 16. Module Admin GPS (modules/admin_gps/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `index.php` | `requireLogin()` | Admin/Chef Service | Gestion GPS |
| `edit_gps.php` | `requireLogin()` | Admin/Chef Service | Modifier GPS |

### 17. Module Carte (modules/carte/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `index.php` | `requireLogin()` | Tous connectés | Carte infrastructures |

### 18. Module Rapports (modules/rapports/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `export_excel.php` | `requireLogin()` | Admin/Chef/Directeur | Export Excel |

### 19. Module Registre Public (modules/registre_public/)

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `index.php` | **PUBLIC** | Aucune | Page d'accueil registre |
| `detail.php` | **PUBLIC** | Aucune | Détail dossier public |
| `carte.php` | **PUBLIC** | Aucune | Carte publique |
| `statistiques.php` | **PUBLIC** | Aucune | Statistiques publiques |
| `export.php` | **PUBLIC** | Aucune | Export données publiques |
| `suivi.php` | **PUBLIC** | Aucune | Suivi dossier public |

---

## Pages racine

| Fichier | Permission | Rôles autorisés | Remarque |
|---------|------------|-----------------|----------|
| `index.php` | Public | Tous | Page de connexion |
| `dashboard.php` | `requireLogin()` | Tous connectés | Tableau de bord |
| `logout.php` | `requireLogin()` | Tous connectés | Déconnexion |

---

## Règles de sécurité

### 1. Tous les fichiers PHP accessibles doivent avoir un contrôle

✅ **BON** :
```php
<?php
require_once '../../includes/auth.php';
requireRole('admin');
// ... reste du code
```

❌ **MAUVAIS** :
```php
<?php
// Pas de contrôle d'accès
// Fichier vulnérable !
```

### 2. Les fichiers functions.php n'ont pas de contrôle direct

Les fichiers `functions.php` sont des bibliothèques appelées par d'autres fichiers déjà sécurisés. Ils ne doivent **jamais** être appelés directement via HTTP.

### 3. Le registre public est une exception

Le module `registre_public/` est **volontairement public** selon les spécifications CLAUDE.md :
> "Public registry accessible without authentication"

### 4. Hiérarchie des contrôles

1. `requireLogin()` : Minimum pour toute page privée
2. `requireRole($role)` : Pour un rôle spécifique unique
3. `requireAnyRole([$roles])` : Pour plusieurs rôles autorisés

### 5. Fichiers de debug

⚠️ **ATTENTION** : Les fichiers `debug.php` et `debug2.php` dans `modules/notes_frais/` doivent être :
- Sécurisés avec `requireRole('admin')` OU
- Supprimés avant la mise en production

---

## Audit de sécurité

### Statut actuel : ✅ SÉCURISÉ

- **Total fichiers PHP** : 126
- **Fichiers avec contrôle** : 108
- **Fichiers publics justifiés** : 6 (registre_public)
- **Fichiers bibliothèques** : 7 (functions.php)
- **Fichiers à sécuriser** : 2 (debug.php)
- **Scripts utilitaires** : 3 (check_structure.php, etc.)

### Recommandations

1. ✅ Sécuriser ou supprimer `modules/notes_frais/debug.php` et `debug2.php`
2. ✅ Vérifier que `check_structure.php` n'est pas accessible via HTTP
3. ✅ Ajouter un fichier `.htaccess` pour bloquer l'accès direct aux `functions.php` :

```apache
# .htaccess dans /modules/
# IMPORTANT : Utiliser la syntaxe Apache 2.4+ (Require all denied)
<FilesMatch "functions\.php$">
    Require all denied
</FilesMatch>
```

**Note** : La syntaxe `Order Allow,Deny` est obsolète (Apache 2.2). Utilisez `Require all denied` pour Apache 2.4+.

---

## Matrice de navigation par rôle

### Admin

Peut accéder à **TOUT** sauf le registre public (pas nécessaire car admin).

### Chef de Service SDTD

- ✅ Création dossiers
- ✅ Nomination commissions
- ✅ Visa 1er niveau
- ✅ Gestion GPS
- ✅ Huitaines
- ✅ Carte infrastructures
- ✅ Import OSM

### Billeteur DPPG

- ✅ Enregistrement paiements
- ✅ Édition reçus
- ✅ Liste dossiers (filtrée : paiements en attente)

### Cadre DAJ

- ✅ Analyses juridiques
- ✅ Huitaines
- ✅ Liste dossiers (filtrée : analyse_daj)

### Inspecteur DPPG

- ✅ Inspections terrain
- ✅ Rapports d'inspection
- ✅ Fiches d'inspection
- ✅ Huitaines
- ✅ Carte infrastructures

### Chef de Commission

- ✅ Coordination visites
- ✅ Validation rapports
- ✅ Liste dossiers commission

### Sous-Directeur SDTD

- ✅ Visa 2ème niveau
- ✅ Liste dossiers (filtrée : validation_sd)

### Directeur DPPG

- ✅ Visa 3ème niveau
- ✅ Transmission ministre
- ✅ Rapports Excel
- ✅ Liste dossiers (filtrée : validation_directeur)

### Ministre / Cabinet

- ✅ Décision finale
- ✅ Liste dossiers (filtrée : decision_ministre)

---

## Tests de sécurité

### Test 1 : Accès non authentifié

```bash
# Doit rediriger vers index.php
curl -L http://localhost/dppg-implantation/modules/dossiers/create.php
```

### Test 2 : Accès avec mauvais rôle

```bash
# Connecté comme billeteur, tenter d'accéder à create.php
# Doit rediriger vers dashboard avec message erreur
```

### Test 3 : Registre public accessible

```bash
# Doit fonctionner sans authentification
curl http://localhost/dppg-implantation/modules/registre_public/index.php
```

---

## Historique des modifications

| Date | Action | Fichiers concernés |
|------|--------|-------------------|
| 2025-11-01 | Ajout contrôle `requireLogin()` | `modules/fiche_inspection/print_blank.php` |
| 2025-11-01 | Ajout contrôle `requireAnyRole(['admin','chef_service'])` | 4 fichiers OSM extraction |
| 2025-11-01 | Documentation matrice permissions | `docs/MATRICE_PERMISSIONS.md` |

---

**Document maintenu par** : Admin Système
**Dernière mise à jour** : 2025-11-01
**Version** : 1.0
