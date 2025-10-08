# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the **Système de Gestion des Dossiers d'Implantation (SGDI)** - a comprehensive web application for managing petroleum infrastructure implementation files for the MINEE/DPPG in Cameroon.

## Technology Stack

- **Backend**: PHP 7.4+ only
- **Database**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 4/5
- **Server**: Apache/Nginx (XAMPP/WAMP environment)
- **Environment**: Local development required

## Architecture Overview

### User Role System (9 Roles)
1. **Chef de Service SDTD** - Central role managing all dossiers, commissions, and first-level visa
2. **Billeteur DPPG** - Payment recording and receipt generation
3. **Chef de Commission** - Coordinates visits and validates inspection reports
4. **Cadre DAJ** - Legal analysis and regulatory compliance validation
5. **Cadre DPPG (Inspecteur)** - Infrastructure inspections and report writing
6. **Sous-Directeur SDTD** - Second-level visa
7. **Directeur DPPG** - Third-level visa and ministerial transmission
8. **Cabinet/Secrétariat Ministre** - Final decision authority
9. **Admin Système** - Complete user and system management

**Note**: Le registre public est accessible sans authentification (pas de rôle "Lecteur" nécessaire)

### Infrastructure Types (6 Types)
1. **Implantation station-service** → Opérateur propriétaire
2. **Reprise station-service** → Opérateur propriétaire
3. **Implantation point consommateur** → Opérateur + Entreprise bénéficiaire + Contrat livraison
4. **Reprise point consommateur** → Opérateur + Entreprise bénéficiaire + Contrat livraison
5. **Implantation dépôt GPL** → Entreprise installatrice
6. **Implantation centre emplisseur** → Opérateur de gaz OU Entreprise constructrice

### 11-Step Workflow Process
1. Dossier creation by Chef Service + document upload
2. Commission constitution (3 mandatory members)
3. Automatic cost note generation
4. Payment recording by Billeteur → automatic notification
5. Legal analysis by DAJ
6. Completeness control by DPPG Inspector
7. Infrastructure inspection by DPPG + Report
8. Report validation by Commission Chief
9. Visa circuit: Chef Service → Sub-Director → Director
10. Ministerial decision (Approval/Refusal)
11. Automatic public registry publication

## Key System Features

### "Huitaine" Management
- Automatic 8-day countdown system
- Notifications at J-2, J-1, J (deadline)
- Automatic rejection if not regularized
- Regularization interface

### Commission System
- Chef Service nominates 3 members (cadre DPPG + cadre DAJ + chef commission)
- Visit planning management
- Report validation workflow

### Document Management
- Multiple file upload (PDF, DOC, JPG, PNG)
- Automatic versioning with integrity control (checksum)
- Simple electronic signature
- Type-specific required documents

### Notification System
- **Email**: PHPMailer for status changes and deadlines
- **In-app**: Dashboard counters and alerts
- **History**: All notifications archived

### Public Registry
- Public interface (no authentication required)
- Multi-criteria search
- Published decisions display
- Public data export

## Development Standards

### Security Requirements
- **Sessions**: Limited duration with ID regeneration
- **CSRF**: Tokens on all forms
- **XSS**: Systematic HTML escaping
- **SQL Injection**: Prepared statements exclusively
- **File Upload**: MIME type + extension validation
- **Audit Trail**: Complete logging system

### Database Structure
Key tables include:
- User management: `users`, `roles`, `user_roles`
- Workflow: `dossiers`, `statuts_dossier`, `historique_dossier`
- Infrastructure: `types_infrastructure`, `types_demandeurs`
- Commission: `commissions`, `membres_commission`, `types_membres`
- Documents: `documents`, `versions_document`, `types_document`
- Payment: `notes_frais`, `paiements`, `recus`
- Inspection: `inspections`, `rapports_inspection`, `grilles_evaluation`
- Notifications: `notifications`, `logs_activite`
- Public registry: `decisions_publiees`, `registre_public`

### Code Organization
Follow this structure:
```
sgdi/
├── config/         # Database, auth, constants
├── includes/       # Header, footer, sidebar templates
├── assets/         # CSS, JS, uploads
├── modules/        # Feature modules (auth, dossiers, commission, etc.)
├── database/       # Schema and seed files
└── docs/          # Documentation
```

## Development Phases

1. **Phase 1** (Weeks 1-6): Foundation - Database, auth, user management
2. **Phase 2** (Weeks 7-12): Dossiers - CRUD, infrastructure types, workflows
3. **Phase 3** (Weeks 13-18): Core workflows - inspection, payment, visa circuit
4. **Phase 4** (Weeks 19-22): Finalization - public registry, statistics, testing

## Critical Implementation Points

- Strict implementation of 10 user roles with exact permissions
- Absolute respect for 11-step workflow sequence
- Differentiated management of 6 infrastructure types
- Mandatory 3-member commission constitution
- Instant notification after payment recording
- Complete automation of "huitaine" countdown
- Infrastructure inspection exclusively by DPPG cadre
- Public registry accessible without authentication

## Development Environment

This is a local PHP project without package managers or build tools. Development should be done directly with:
- PHP files in the web server directory
- MySQL database setup via SQL scripts
- Direct file editing and testing via browser
- No npm, composer, or build processes required

## Testing and Validation

Test each functionality immediately after implementation:
- Role-based access control
- Workflow step transitions
- Document upload and versioning
- Notification delivery
- Database integrity
- Security measures (CSRF, SQL injection prevention)

The system must be production-ready upon delivery with all features fully functional.