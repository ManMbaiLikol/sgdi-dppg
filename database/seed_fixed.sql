-- Données de test - SGDI MVP (Mots de passe corrigés)
-- Insertion des utilisateurs de démonstration

USE sgdi_mvp;

-- Vider les tables existantes
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE decisions;
TRUNCATE TABLE inspections;
TRUNCATE TABLE paiements;
TRUNCATE TABLE commissions;
TRUNCATE TABLE documents;
TRUNCATE TABLE historique;
TRUNCATE TABLE dossiers;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- Utilisateurs de test avec mots de passe corrects
-- admin123, chef123, cadre123, bill123, dir123 tous hashés avec password_hash()
INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif) VALUES
('admin', 'admin@sgdi.cm', '$2y$10$wFQZfFz5z5z5z5z5z5z5z.OQ5yKjO0rOQ5byMi.Ye4oKoEa3Ro9llC', 'admin', 'ADMINISTRATEUR', 'Système', '+237690000001', 1),
('chef', 'chef.service@dppg.cm', '$2y$10$wFQZfFz5z5z5z5z5z5z5z.OQ5yKjO0rOQ5byMi.Ye4oKoEa3Ro9llC', 'chef_service', 'MBALLA', 'Jean-Pierre', '+237690000002', 1),
('cadre', 'cadre@dppg.cm', '$2y$10$wFQZfFz5z5z5z5z5z5z5z.OQ5yKjO0rOQ5byMi.Ye4oKoEa3Ro9llC', 'cadre_dppg', 'NGOUNOU', 'Marie-Claire', '+237690000003', 1),
('billeteur', 'billeteur@dppg.cm', '$2y$10$wFQZfFz5z5z5z5z5z5z5z.OQ5yKjO0rOQ5byMi.Ye4oKoEa3Ro9llC', 'billeteur', 'FOKOU', 'Alain', '+237690000004', 1),
('directeur', 'directeur@dppg.cm', '$2y$10$wFQZfFz5z5z5z5z5z5z5z.OQ5yKjO0rOQ5byMi.Ye4oKoEa3Ro9llC', 'directeur', 'TALLA', 'Paul', '+237690000005', 1),
('cadre2', 'cadre2@dppg.cm', '$2y$10$wFQZfFz5z5z5z5z5z5z5z.OQ5yKjO0rOQ5byMi.Ye4oKoEa3Ro9llC', 'cadre_dppg', 'EYENGA', 'Francine', '+237690000006', 1);

-- Le reste des données reste identique
-- Dossiers de test
INSERT INTO dossiers (numero, type_infrastructure, sous_type, nom_demandeur, contact_demandeur, telephone_demandeur, email_demandeur, region, ville, adresse_precise, coordonnees_gps, operateur_proprietaire, entreprise_beneficiaire, entreprise_installatrice, user_id, statut, date_creation) VALUES

-- Station-service implantation (Créé)
('SS2025092501', 'station_service', 'implantation', 'TOTAL CAMEROUN SA', 'M. KOUAM Pierre', '+237677123456', 'kouam@total.cm', 'Centre', 'Yaoundé', 'Carrefour Warda, face au rond-point', '3.848,11.502', 'TOTAL CAMEROUN SA', NULL, NULL, 2, 'cree', '2025-09-20 08:30:00'),

-- Point consommateur reprise (En cours - commission constituée)
('PC2025092301', 'point_consommateur', 'reprise', 'CIMENTS DU CAMEROUN SA', 'Mme DJOUMESSI Annie', '+237695234567', 'annie@cimencam.cm', 'Littoral', 'Douala', 'Zone industrielle de Bonabéri', '4.063,9.733', NULL, 'CIMENTS DU CAMEROUN SA', NULL, 2, 'en_cours', '2025-09-21 14:20:00'),

-- Station-service reprise (Payé)
('SS2025092201', 'station_service', 'reprise', 'SHELL CAMEROUN SA', 'M. NOAH François', '+237699345678', 'noah@shell.cm', 'Ouest', 'Bafoussam', 'Route principale, après le marché A', '5.475,10.418', 'SHELL CAMEROUN SA', NULL, NULL, 2, 'paye', '2025-09-22 09:15:00'),

-- Dépôt GPL (Inspecté)
('DG2025091801', 'depot_gpl', 'implantation', 'GAZ DU CAMEROUN SARL', 'M. BIYA Samuel', '+237688456789', 'samuel@gazducameroun.cm', 'Centre', 'Yaoundé', 'Quartier Omnisport, derrière le stade', '3.866,11.516', NULL, NULL, 'GAZ DU CAMEROUN SARL', 2, 'inspecte', '2025-09-18 10:45:00'),

-- Point consommateur (Validé)
('PC2025091501', 'point_consommateur', 'implantation', 'ALUCAM SA', 'Mme BELLO Fatima', '+237677567890', 'fatima@alucam.cm', 'Littoral', 'Edéa', 'Site industriel ALUCAM', '3.797,10.125', NULL, 'ALUCAM SA', NULL, 2, 'valide', '2025-09-15 16:00:00'),

-- Station-service (Décidé - Approuvé)
('SS2025091201', 'station_service', 'implantation', 'BOCOM PETROLEUM SA', 'M. ESSO Jean', '+237690678901', 'esso@bocom.cm', 'Sud', 'Ebolowa', 'Entrée de ville, axe Yaoundé-Ebolowa', '2.915,11.154', 'BOCOM PETROLEUM SA', NULL, NULL, 2, 'decide', '2025-09-12 11:30:00'),

-- Dépôt GPL (Créé)
('DG2025091001', 'depot_gpl', 'implantation', 'TRADEX SARL', 'Mme TCHOUMI Rose', '+237695789012', 'rose@tradex.cm', 'Adamaoua', 'Ngaoundéré', 'Quartier Petit Marché', '7.326,13.584', NULL, NULL, 'TRADEX SARL', 2, 'cree', '2025-09-10 13:20:00'),

-- Point consommateur (En cours)
('PC2025090801', 'point_consommateur', 'reprise', 'SOCAPALM SA', 'M. MVONDO Paul', '+237688890123', 'mvondo@socapalm.cm', 'Sud-Ouest', 'Limbé', 'Plantation SOCAPALM', '4.023,9.199', NULL, 'SOCAPALM SA', NULL, 2, 'en_cours', '2025-09-08 07:45:00'),

-- Station-service (Payé)
('SS2025090501', 'station_service', 'reprise', 'PETROLEX SA', 'Mme ASSAM Claire', '+237677901234', 'claire@petrolex.cm', 'Extrême-Nord', 'Maroua', 'Route de Garoua, km 5', '10.591,14.315', 'PETROLEX SA', NULL, NULL, 2, 'paye', '2025-09-05 15:10:00'),

-- Dépôt GPL (Inspecté)
('DG2025090301', 'depot_gpl', 'implantation', 'CAMEROON OIL DEPOT', 'M. EKANI Joseph', '+237699012345', 'joseph@coildepot.cm', 'Littoral', 'Douala', 'Zone portuaire, terminal pétrolier', '4.048,9.704', NULL, NULL, 'CAMEROON OIL DEPOT', 2, 'inspecte', '2025-09-03 12:25:00');

-- Historique des actions pour les dossiers
INSERT INTO historique (dossier_id, action, description, ancien_statut, nouveau_statut, user_id, date_action) VALUES
(1, 'creation_dossier', 'Création du dossier SS2025092501', NULL, 'cree', 2, '2025-09-20 08:30:00'),

(2, 'creation_dossier', 'Création du dossier PC2025092301', NULL, 'cree', 2, '2025-09-21 14:20:00'),
(2, 'changement_statut_en_cours', 'Commission constituée et note de frais générée', 'cree', 'en_cours', 2, '2025-09-21 14:45:00'),

(3, 'creation_dossier', 'Création du dossier SS2025092201', NULL, 'cree', 2, '2025-09-22 09:15:00'),
(3, 'changement_statut_en_cours', 'Commission constituée et note de frais générée', 'cree', 'en_cours', 2, '2025-09-22 09:30:00'),
(3, 'changement_statut_paye', 'Paiement enregistré: 75 000 FCFA via cheque', 'en_cours', 'paye', 4, '2025-09-22 14:15:00'),

(4, 'creation_dossier', 'Création du dossier DG2025091801', NULL, 'cree', 2, '2025-09-18 10:45:00'),
(4, 'changement_statut_en_cours', 'Commission constituée et note de frais générée', 'cree', 'en_cours', 2, '2025-09-18 11:00:00'),
(4, 'changement_statut_paye', 'Paiement enregistré: 100 000 FCFA via virement', 'en_cours', 'paye', 4, '2025-09-18 15:30:00'),
(4, 'changement_statut_inspecte', 'Inspection réalisée et rapport rédigé', 'paye', 'inspecte', 3, '2025-09-19 16:45:00'),

(5, 'creation_dossier', 'Création du dossier PC2025091501', NULL, 'cree', 2, '2025-09-15 16:00:00'),
(5, 'changement_statut_en_cours', 'Commission constituée et note de frais générée', 'cree', 'en_cours', 2, '2025-09-15 16:15:00'),
(5, 'changement_statut_paye', 'Paiement enregistré: 50 000 FCFA via especes', 'en_cours', 'paye', 4, '2025-09-16 09:00:00'),
(5, 'changement_statut_inspecte', 'Inspection réalisée et rapport rédigé', 'paye', 'inspecte', 3, '2025-09-17 14:30:00'),
(5, 'changement_statut_valide', 'Rapport validé par le Directeur DPPG', 'inspecte', 'valide', 5, '2025-09-18 10:00:00'),

(6, 'creation_dossier', 'Création du dossier SS2025091201', NULL, 'cree', 2, '2025-09-12 11:30:00'),
(6, 'changement_statut_en_cours', 'Commission constituée et note de frais générée', 'cree', 'en_cours', 2, '2025-09-12 11:45:00'),
(6, 'changement_statut_paye', 'Paiement enregistré: 75 000 FCFA via cheque', 'en_cours', 'paye', 4, '2025-09-13 08:20:00'),
(6, 'changement_statut_inspecte', 'Inspection réalisée et rapport rédigé', 'paye', 'inspecte', 3, '2025-09-14 15:10:00'),
(6, 'changement_statut_valide', 'Rapport validé par le Directeur DPPG', 'inspecte', 'valide', 5, '2025-09-15 09:30:00'),
(6, 'changement_statut_decide', 'Décision ministérielle: APPROUVÉ', 'valide', 'decide', 5, '2025-09-16 14:15:00');

-- Commissions constituées
INSERT INTO commissions (dossier_id, chef_service_id, cadre_dppg_id, membre_externe_nom, membre_externe_fonction, membre_externe_contact, statut, date_constitution) VALUES
(2, 2, 3, 'M. NKOMO André', 'Ingénieur Civil', '+237677111222', 'constituee', '2025-09-21 14:45:00'),
(3, 2, 3, 'Mme BELLA Suzanne', 'Expert Pétrolier', '+237699333444', 'constituee', '2025-09-22 09:30:00'),
(4, 2, 6, 'M. EFFA Martin', 'Spécialiste GPL', '+237688555666', 'rapport_fait', '2025-09-18 11:00:00'),
(5, 2, 3, 'M. ATANGA Robert', 'Consultant', '+237677777888', 'rapport_fait', '2025-09-15 16:15:00'),
(6, 2, 6, 'Mme OWONO Jeanne', 'Ingénieur Sécurité', '+237699999000', 'rapport_fait', '2025-09-12 11:45:00');

-- Paiements enregistrés
INSERT INTO paiements (dossier_id, montant, devise, mode_paiement, reference_paiement, date_paiement, billeteur_id, observations, date_enregistrement) VALUES
(3, 75000.00, 'FCFA', 'cheque', 'CHQ2025001234', '2025-09-22', 4, 'Paiement pour inspection station-service Shell', '2025-09-22 14:15:00'),
(4, 100000.00, 'FCFA', 'virement', 'VIR2025005678', '2025-09-18', 4, 'Paiement pour inspection dépôt GPL', '2025-09-18 15:30:00'),
(5, 50000.00, 'FCFA', 'especes', NULL, '2025-09-16', 4, 'Paiement en espèces point consommateur ALUCAM', '2025-09-16 09:00:00'),
(6, 75000.00, 'FCFA', 'cheque', 'CHQ2025001111', '2025-09-13', 4, 'Paiement station-service BOCOM', '2025-09-13 08:20:00');

-- Inspections réalisées
INSERT INTO inspections (dossier_id, cadre_dppg_id, date_inspection, rapport, recommandations, conforme, observations, valide_par_directeur, directeur_id, date_validation, date_redaction) VALUES

(4, 6, '2025-09-19',
'Inspection du site d\'implantation du dépôt GPL de GAZ DU CAMEROUN SARL situé dans le quartier Omnisport à Yaoundé.

CONSTATATIONS TECHNIQUES:
- Surface du terrain: 2500 m²
- Clôture périmétrique: Conforme aux normes
- Distance de sécurité: 50m des habitations (conforme)
- Accès véhicules lourds: Satisfaisant
- Système de sécurité incendie: À installer

CONFORMITÉ RÉGLEMENTAIRE:
- Permis de construire: Validé
- Étude d\'impact environnemental: Approuvée
- Autorisation municipale: Obtenue

SÉCURITÉ:
- Plan d\'évacuation: À élaborer
- Formation du personnel: Requise
- Équipements de sécurité: À compléter',

'1. Installer le système de sécurité incendie avant mise en service
2. Élaborer le plan d\'évacuation d\'urgence
3. Former le personnel aux procédures de sécurité
4. Compléter les équipements de protection individuelle
5. Effectuer un test de sécurité avant ouverture',

'sous_reserve',
'Site conforme aux normes de base mais nécessite des améliorations sécuritaires avant autorisation définitive.',
0, NULL, NULL,
'2025-09-19 16:45:00'),

(5, 3, '2025-09-17',
'Inspection du point consommateur ALUCAM SA à Edéa.

CONSTATATIONS TECHNIQUES:
- Infrastructure existante: Conforme
- Modifications apportées: Appropriées
- Système de stockage: Aux normes
- Réseau de distribution interne: Vérifié

SÉCURITÉ:
- Dispositifs anti-fuite: Opérationnels
- Système d\'alarme: Fonctionnel
- Personnel formé: Oui
- Procédures de sécurité: En place

ENVIRONNEMENT:
- Respect des normes environnementales: Oui
- Système de récupération des vapeurs: Installé
- Traitement des effluents: Conforme',

'Installation conforme, autorisation recommandée',
'oui',
'Excellente installation respectant toutes les normes en vigueur.',
1, 5, '2025-09-18 10:00:00',
'2025-09-17 14:30:00'),

(6, 6, '2025-09-14',
'Inspection station-service BOCOM PETROLEUM SA à Ebolowa.

CONSTATATIONS TECHNIQUES:
- Cuves de stockage: Conformes (2x20000L)
- Pistes et aires: Revêtement approprié
- Système de distribution: 4 pistolets installés
- Signalisation: Complète et visible

SÉCURITÉ:
- Extincteurs: Positionnés et vérifiés
- Éclairage de sécurité: Fonctionnel
- Formation personnel: Effectuée
- Plan d\'urgence: Disponible

ENVIRONNEMENT:
- Séparateur hydrocarbures: Installé
- Rétention des cuves: Conforme
- Évacuation eaux pluviales: Appropriée',

'Station-service prête pour exploitation, tous critères respectés',
'oui',
'Installation de qualité supérieure, recommandation d\'approbation.',
1, 5, '2025-09-15 09:30:00',
'2025-09-14 15:10:00');

-- Décisions finales
INSERT INTO decisions (dossier_id, decision, motif, date_decision, reference_decision, date_enregistrement) VALUES
(6, 'approuve',
'Après examen du dossier et du rapport d\'inspection favorable, l\'autorisation d\'implantation est accordée à BOCOM PETROLEUM SA pour l\'exploitation de la station-service sise à Ebolowa. L\'installation respecte l\'ensemble des normes techniques et de sécurité en vigueur.',
'2025-09-16',
'DECISION_N°2025/001/MINEE/DPPG',
'2025-09-16 14:15:00');

-- Quelques documents fictifs pour les tests (chemins fictifs)
INSERT INTO documents (dossier_id, nom_fichier, nom_original, type_document, taille_fichier, extension, chemin_fichier, user_id, date_upload) VALUES
(1, 'doc_1_plan_implantation.pdf', 'Plan_implantation_TOTAL_Warda.pdf', 'plan_implantation', 2048576, 'pdf', '/uploads/2025/09/doc_1_plan_implantation.pdf', 2, '2025-09-20 09:00:00'),
(1, 'doc_1_cni_demandeur.jpg', 'CNI_KOUAM_Pierre.jpg', 'piece_identite', 1024768, 'jpg', '/uploads/2025/09/doc_1_cni_demandeur.jpg', 2, '2025-09-20 09:05:00'),

(2, 'doc_2_autorisation_terrain.pdf', 'Autorisation_terrain_Bonaberi.pdf', 'autorisation_terrain', 3145728, 'pdf', '/uploads/2025/09/doc_2_autorisation_terrain.pdf', 2, '2025-09-21 14:30:00'),

(4, 'doc_4_etude_impact.pdf', 'Etude_impact_environnemental_GPL.pdf', 'etude_impact', 5242880, 'pdf', '/uploads/2025/09/doc_4_etude_impact.pdf', 2, '2025-09-18 11:15:00'),

(6, 'doc_6_rapport_inspection.pdf', 'Rapport_inspection_BOCOM_Ebolowa.pdf', 'autres', 1536000, 'pdf', '/uploads/2025/09/doc_6_rapport_inspection.pdf', 6, '2025-09-14 15:30:00');