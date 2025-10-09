# Configuration des Emails - SGDI

Ce guide explique comment configurer le système d'envoi d'emails automatiques pour le SGDI.

## 🎯 Vue d'ensemble

Le système envoie automatiquement des emails pour :
- ✅ **Paiement enregistré** - Notification au demandeur + cadres DPPG/DAJ
- 📝 **Visa accordé** - À chaque étape du circuit de validation
- ⚖️ **Décision ministérielle** - Décision finale (approuvée/refusée)
- ⚠️ **Alerte huitaine** - J-2, J-1, J (jour limite)
- 🔄 **Changement de statut** - À chaque transition importante

## 📋 Prérequis

Vous avez besoin d'un **serveur SMTP** pour envoyer des emails. Options recommandées :

### Option 1: Gmail (Simple pour débuter)
- Compte Gmail existant ou nouveau
- Authentification à 2 facteurs activée
- Mot de passe d'application généré

### Option 2: Services professionnels
- **SendGrid** - 100 emails/jour gratuits
- **Mailgun** - 5000 emails/mois gratuits les 3 premiers mois
- **AWS SES** - 62 000 emails/mois gratuits (si hébergé sur AWS)
- **Brevo** (ex-Sendinblue) - 300 emails/jour gratuits

## 🔧 Configuration sur Railway

### Étape 1: Accéder aux variables d'environnement

1. Ouvrez votre projet SGDI sur [railway.app](https://railway.app)
2. Allez dans l'onglet **"Variables"**
3. Cliquez sur **"New Variable"**

### Étape 2: Ajouter les variables

#### Variables OBLIGATOIRES :

```bash
# Activer l'envoi d'emails
EMAIL_ENABLED=true

# Configuration SMTP
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls

# Identifiants SMTP
SMTP_USERNAME=votre-email@gmail.com
SMTP_PASSWORD=votre-mot-de-passe-application

# Expéditeur des emails
EMAIL_FROM=noreply@dppg.cm
EMAIL_FROM_NAME=SGDI - MINEE/DPPG

# Email administrateur (notifications système)
ADMIN_EMAIL=admin@dppg.cm
```

#### Variables OPTIONNELLES :

```bash
# Mode debug (false en production)
EMAIL_DEBUG=false
```

### Étape 3: Redémarrer le service

Après avoir ajouté les variables, Railway redémarrera automatiquement votre application.

## 🔑 Configuration Gmail (Détaillée)

### 1. Activer l'authentification à 2 facteurs

1. Allez sur [myaccount.google.com/security](https://myaccount.google.com/security)
2. Cliquez sur "Validation en deux étapes"
3. Suivez les instructions pour l'activer

### 2. Créer un mot de passe d'application

1. Retournez sur [myaccount.google.com/security](https://myaccount.google.com/security)
2. Cherchez "Mots de passe des applications"
3. Créez un nouveau mot de passe pour "Autre (nom personnalisé)"
4. Nommez-le "SGDI"
5. **Copiez le mot de passe généré** (16 caractères, sans espaces)
6. Utilisez ce mot de passe dans `SMTP_PASSWORD`

### 3. Variables pour Gmail

```bash
EMAIL_ENABLED=true
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=votre-email@gmail.com
SMTP_PASSWORD=xxxx xxxx xxxx xxxx  # Mot de passe d'application
EMAIL_FROM=votre-email@gmail.com
EMAIL_FROM_NAME=SGDI - MINEE/DPPG
ADMIN_EMAIL=admin@dppg.cm
```

## 📧 Configuration SendGrid (Recommandé pour production)

SendGrid est **plus fiable** que Gmail pour les emails transactionnels.

### 1. Créer un compte

1. Allez sur [sendgrid.com](https://sendgrid.com)
2. Créez un compte gratuit (100 emails/jour)
3. Vérifiez votre email

### 2. Créer une clé API

1. Dans le dashboard SendGrid, allez dans **Settings > API Keys**
2. Cliquez sur **"Create API Key"**
3. Nommez-la "SGDI Production"
4. Sélectionnez **"Full Access"**
5. **Copiez la clé** (elle ne sera plus affichée)

### 3. Variables pour SendGrid

```bash
EMAIL_ENABLED=true
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USERNAME=apikey  # Toujours "apikey"
SMTP_PASSWORD=SG.xxxxxxxxxx  # Votre clé API
EMAIL_FROM=noreply@dppg.cm
EMAIL_FROM_NAME=SGDI - MINEE/DPPG
ADMIN_EMAIL=admin@dppg.cm
```

### 4. Authentification du domaine (Optionnel mais recommandé)

Pour utiliser `@dppg.cm` au lieu de `@sendgrid.net`, vous devez authentifier votre domaine :

1. Dans SendGrid : **Settings > Sender Authentication > Authenticate Your Domain**
2. Suivez les instructions pour ajouter des enregistrements DNS
3. Une fois validé, vous pouvez utiliser `noreply@dppg.cm`

## 🧪 Tester la configuration

### Option 1: Via l'interface admin

1. Connectez-vous en tant qu'admin
2. Allez dans **Admin > Test d'envoi d'email**
3. URL: `https://votre-app.railway.app/modules/admin/test_email.php`
4. Entrez votre email personnel
5. Choisissez un template
6. Cliquez sur "Envoyer l'email de test"

### Option 2: Via ligne de commande Railway

```bash
# Se connecter à Railway CLI
railway login

# Lancer un shell dans le conteneur
railway run bash

# Tester l'envoi d'email PHP
php -r "mail('test@example.com', 'Test SGDI', 'Test email');"
```

## 📊 Voir les logs d'envoi

1. Connectez-vous en tant qu'admin
2. Allez dans **Admin > Logs d'envoi d'email**
3. URL: `https://votre-app.railway.app/modules/admin/email_logs.php`
4. Vous verrez tous les emails envoyés avec leur statut

Les statuts possibles :
- 🟢 **sent** - Envoyé avec succès
- 🔴 **failed** - Échec d'envoi
- 🟡 **disabled** - Enregistré mais pas envoyé (EMAIL_ENABLED=false)

## ❌ Résolution de problèmes

### Problème: "Authentification SMTP échouée"

**Solutions:**
- Vérifiez `SMTP_USERNAME` et `SMTP_PASSWORD`
- Pour Gmail: utilisez un mot de passe d'application, pas votre mot de passe normal
- Vérifiez que l'authentification 2FA est activée sur Gmail

### Problème: "Connection timeout"

**Solutions:**
- Vérifiez `SMTP_HOST` et `SMTP_PORT`
- Assurez-vous que Railway autorise les connexions SMTP sortantes
- Essayez le port 465 avec `SMTP_SECURE=ssl` si 587 ne fonctionne pas

### Problème: Emails dans les spams

**Solutions:**
- Utilisez un service professionnel (SendGrid, Mailgun) au lieu de Gmail
- Authentifiez votre domaine avec SPF, DKIM, DMARC
- Utilisez une adresse email avec un vrai domaine (@dppg.cm) au lieu de Gmail

### Problème: Emails non envoyés mais loggés

**Solution:**
- Vérifiez que `EMAIL_ENABLED=true` dans les variables d'environnement Railway
- Vérifiez les logs PHP: `railway logs`

## 🔒 Sécurité

### ⚠️ NE JAMAIS :
- ❌ Commiter les mots de passe SMTP dans Git
- ❌ Partager les clés API publiquement
- ❌ Utiliser le même mot de passe que votre compte Gmail personnel

### ✅ TOUJOURS :
- ✅ Utiliser des variables d'environnement Railway
- ✅ Utiliser des mots de passe d'application Gmail (pas le mot de passe principal)
- ✅ Surveiller les logs d'envoi pour détecter les abus
- ✅ Changer les clés API régulièrement

## 📚 Templates d'emails disponibles

Le système inclut ces templates HTML professionnels :

1. **Paiement enregistré** - Fond vert, icône de succès
2. **Visa accordé** - Fond bleu, icône de validation
3. **Alerte huitaine** - Fond rouge, icône d'alerte
4. **Décision ministérielle** - Fond bleu/rouge selon décision
5. **Changement de statut** - Fond bleu, icône de notification

Tous les templates incluent :
- Logo/en-tête SGDI - MINEE/DPPG
- Contenu formaté en HTML responsive
- Bouton d'action vers le dossier
- Pied de page avec mentions légales

## 🎨 Personnalisation

Pour modifier les templates, éditez :
- `includes/email.php` - Templates inline (méthode actuelle)
- `includes/email_templates/` - Templates séparés (à créer)

## 🚀 Bonnes pratiques

1. **Testez d'abord en local** avec `EMAIL_ENABLED=false`
2. **Vérifiez les logs** après chaque envoi
3. **Surveillez les quotas** de votre fournisseur SMTP
4. **Utilisez SendGrid/Mailgun** pour la production (plus fiable que Gmail)
5. **Authentifiez votre domaine** pour éviter les spams
6. **Gardez EMAIL_DEBUG=false** en production

## 📞 Support

Si vous rencontrez des problèmes :

1. **Vérifiez les logs Railway** : `railway logs`
2. **Testez l'envoi** via la page de test admin
3. **Consultez les logs d'email** dans l'interface
4. **Vérifiez la configuration SMTP** auprès de votre fournisseur

---

**Documentation mise à jour:** 09/01/2025
**Version SGDI:** 1.0
