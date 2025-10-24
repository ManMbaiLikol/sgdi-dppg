# ✅ Solution: Constitution de Commission

## 🎯 Testez MAINTENANT avec l'affichage debug

### Étape 1: Rechargez la page
Appuyez sur **Ctrl+F5** pour vider le cache et recharger complètement la page de constitution de commission.

### Étape 2: Observez l'encadré bleu
Vous devriez voir un encadré bleu avec:
```
🔍 Debug: Valeur du champ chef_commission_role = 'vide'
```

### Étape 3: Sélectionnez un chef de commission
Quand vous sélectionnez un utilisateur dans le menu déroulant:

**✅ SI LE JAVASCRIPT FONCTIONNE:**
L'encadré devient:
```
🔍 Debug: Valeur du champ chef_commission_role = 'chef_commission'
```
(en VERT)

**❌ SI LE JAVASCRIPT NE FONCTIONNE PAS:**
L'encadré reste:
```
🔍 Debug: Valeur du champ chef_commission_role = 'vide'
```
(en ROUGE)

---

## 🚨 Diagnostics selon ce que vous voyez

### Cas A: La valeur reste en ROUGE "vide"

**Problème**: Le JavaScript ne s'exécute pas.

**Solutions:**

1. **Vérifiez qu'il n'y a pas d'erreur JavaScript**
   - Appuyez sur F12
   - Onglet "Console"
   - Regardez s'il y a des erreurs en rouge

2. **Le code HTML de l'option est peut-être incorrect**
   - Faites un clic droit sur la page → "Afficher le code source"
   - Cherchez `<option value=` dans le code
   - Vérifiez qu'il y a bien `data-role="chef_commission"` (ou autre)

3. **Conflit avec une autre librairie JavaScript**
   - Désactivez temporairement vos extensions de navigateur
   - Essayez dans un autre navigateur

### Cas B: La valeur passe en VERT mais l'erreur SQL persiste

**Problème**: L'ENUM en base de données n'est pas à jour.

**Solution: Exécutez ce script**
```bash
php fix_commission_enum_now.php
```

Le script:
- Vérifie l'état actuel de l'ENUM
- Applique la correction si nécessaire
- Teste que la correction a fonctionné

### Cas C: La valeur est en VERT avec un texte étrange

**Exemple**: `chef_commission_role = 'undefined'` ou `'null'` ou autre

**Problème**: L'attribut `data-role` n'existe pas ou est mal formé.

**Solution:**
1. Vérifiez le code source HTML (clic droit → "Afficher le code source")
2. Cherchez votre option sélectionnée
3. Elle DOIT ressembler à:
   ```html
   <option value="18" data-role="chef_commission">
       NOM Prénom (Chef de Commission)
   </option>
   ```

---

## 🛠️ Script de diagnostic rapide

Si vous voulez voir EXACTEMENT ce qui est envoyé au serveur, modifiez temporairement le formulaire:

### Dans `modules/dossiers/commission.php` ligne 179:

**Avant:**
```html
<form method="POST" id="commission-form">
```

**Après:**
```html
<form method="POST" id="commission-form" action="../../test_commission_post.php">
```

Puis soumettez le formulaire. Vous verrez une page avec TOUS les détails de ce qui a été envoyé.

**N'OUBLIEZ PAS de remettre comme avant après le test!**

---

## 📊 Checklist complète

- [ ] Page rechargée avec Ctrl+F5
- [ ] Encadré debug visible
- [ ] Sélection d'un chef → valeur passe en VERT
- [ ] Valeur affichée est valide (chef_service, chef_commission, sous_directeur, ou directeur)
- [ ] Formulaire soumis sans erreur

---

## 🎬 Vidéo du comportement attendu

**Comportement normal:**
1. Page chargée → Encadré bleu avec "vide" en rouge
2. Je clique sur le menu déroulant
3. Je sélectionne "LIDJA Francine (Chef de Commission)"
4. **IMMÉDIATEMENT** l'encadré devient: "chef_commission" en vert
5. Je remplis Cadre DPPG et Cadre DAJ
6. Je clique sur "Constituer la commission"
7. ✅ Succès!

---

## 💡 Si rien ne fonctionne

**Dernière solution: Forcer le rôle manuellement**

Modifiez temporairement `commission.php` ligne 217:

**Avant:**
```html
<input type="hidden" name="chef_commission_role" id="chef_commission_role" value="">
```

**Après:**
```html
<input type="hidden" name="chef_commission_role" id="chef_commission_role" value="chef_commission">
```

Puis sélectionnez UNIQUEMENT un utilisateur qui a le rôle "Chef de Commission" dans la liste.

**Attention**: Cette solution temporaire force toujours le même rôle, peu importe qui vous sélectionnez!

---

## 📞 Rapport de bug

Si après TOUT ça l'erreur persiste, fournissez ces informations:

1. **Capture d'écran** de l'encadré debug (avec la valeur visible)
2. **Code source HTML** d'une option du menu (clic droit → code source, cherchez `<option`)
3. **Console JavaScript** (F12 → Console, copiez tout)
4. **Logs PHP** de `C:\wamp64\logs\php_error.log` (dernières 50 lignes)
5. **Résultat** de `php fix_commission_enum_now.php`

---

## ✅ Versions corrigées déployées

- ✅ GitHub: commit `af65531`
- ✅ Railway: déploiement automatique en cours (2-3 min)

---

Date: 24 octobre 2025
Auteur: Claude Code
