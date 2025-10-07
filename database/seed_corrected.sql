-- Données de test CORRIGÉES - SGDI MVP
-- Mots de passe correctement hachés

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

-- Utilisateurs avec mots de passe CORRECTS
INSERT INTO users (username, email, password, role, nom, prenom, telephone, actif) VALUES
('admin', 'admin@sgdi.cm', '$2y$10$E4vKqSORTgQq5xKZeP4HEeRLq8dVZl1Q9TOpCEZtmOC5nEcKO7/lC', 'admin', 'ADMINISTRATEUR', 'Système', '+237690000001', 1),
('chef', 'chef.service@dppg.cm', '$2y$10$JtLqPLpPjKZYRUXmwhBQfOtAFLq8O8CFsLuJkA4WL/B1R.yJ2xXPy', 'chef_service', 'MBALLA', 'Jean-Pierre', '+237690000002', 1),
('cadre', 'cadre@dppg.cm', '$2y$10$vGsI4mGIrFMVY9/rU5VfSO4E8m1Qu0WJqKGZbgUWXFqP4N/cLWXI6', 'cadre_dppg', 'NGOUNOU', 'Marie-Claire', '+237690000003', 1),
('billeteur', 'billeteur@dppg.cm', '$2y$10$QJhKP5O.TCwcl5FgUBJOOe7WYLnf9xvHFqGpJIwkdkFH4E1G8EJzi', 'billeteur', 'FOKOU', 'Alain', '+237690000004', 1),
('directeur', 'directeur@dppg.cm', '$2y$10$H4pLmNbXQZjGi5W7DT2l6OmrFkLYQKcwOXBsJ7K.kVqNMLGE7tFJa', 'directeur', 'TALLA', 'Paul', '+237690000005', 1),
('cadre2', 'cadre2@dppg.cm', '$2y$10$vGsI4mGIrFMVY9/rU5VfSO4E8m1Qu0WJqKGZbgUWXFqP4N/cLWXI6', 'cadre_dppg', 'EYENGA', 'Francine', '+237690000006', 1);

-- Dossiers de test (identique à avant)
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

-- Plus de dossiers...
('DG2025091001', 'depot_gpl', 'implantation', 'TRADEX SARL', 'Mme TCHOUMI Rose', '+237695789012', 'rose@tradex.cm', 'Adamaoua', 'Ngaoundéré', 'Quartier Petit Marché', '7.326,13.584', NULL, NULL, 'TRADEX SARL', 2, 'cree', '2025-09-10 13:20:00'),

('PC2025090801', 'point_consommateur', 'reprise', 'SOCAPALM SA', 'M. MVONDO Paul', '+237688890123', 'mvondo@socapalm.cm', 'Sud-Ouest', 'Limbé', 'Plantation SOCAPALM', '4.023,9.199', NULL, 'SOCAPALM SA', NULL, 2, 'en_cours', '2025-09-08 07:45:00'),

('SS2025090501', 'station_service', 'reprise', 'PETROLEX SA', 'Mme ASSAM Claire', '+237677901234', 'claire@petrolex.cm', 'Extrême-Nord', 'Maroua', 'Route de Garoua, km 5', '10.591,14.315', 'PETROLEX SA', NULL, NULL, 2, 'paye', '2025-09-05 15:10:00'),

('DG2025090301', 'depot_gpl', 'implantation', 'CAMEROON OIL DEPOT', 'M. EKANI Joseph', '+237699012345', 'joseph@coildepot.cm', 'Littoral', 'Douala', 'Zone portuaire, terminal pétrolier', '4.048,9.704', NULL, NULL, 'CAMEROON OIL DEPOT', 2, 'inspecte', '2025-09-03 12:25:00');

-- Historique des actions
INSERT INTO historique (dossier_id, action, description, ancien_statut, nouveau_statut, user_id, date_action) VALUES
(1, 'creation_dossier', 'Création du dossier SS2025092501', NULL, 'cree', 2, '2025-09-20 08:30:00'),
(2, 'creation_dossier', 'Création du dossier PC2025092301', NULL, 'cree', 2, '2025-09-21 14:20:00'),
(2, 'changement_statut_en_cours', 'Commission constituée et note de frais générée', 'cree', 'en_cours', 2, '2025-09-21 14:45:00'),
(3, 'creation_dossier', 'Création du dossier SS2025092201', NULL, 'cree', 2, '2025-09-22 09:15:00'),
(3, 'changement_statut_en_cours', 'Commission constituée et note de frais générée', 'cree', 'en_cours', 2, '2025-09-22 09:30:00'),
(3, 'changement_statut_paye', 'Paiement enregistré: 75 000 FCFA via cheque', 'en_cours', 'paye', 4, '2025-09-22 14:15:00');

-- Commissions
INSERT INTO commissions (dossier_id, chef_service_id, cadre_dppg_id, membre_externe_nom, membre_externe_fonction, membre_externe_contact, statut, date_constitution) VALUES
(2, 2, 3, 'M. NKOMO André', 'Ingénieur Civil', '+237677111222', 'constituee', '2025-09-21 14:45:00'),
(3, 2, 3, 'Mme BELLA Suzanne', 'Expert Pétrolier', '+237699333444', 'constituee', '2025-09-22 09:30:00');

-- Paiements
INSERT INTO paiements (dossier_id, montant, devise, mode_paiement, reference_paiement, date_paiement, billeteur_id, observations, date_enregistrement) VALUES
(3, 75000.00, 'FCFA', 'cheque', 'CHQ2025001234', '2025-09-22', 4, 'Paiement pour inspection station-service Shell', '2025-09-22 14:15:00');

-- Documents de test
INSERT INTO documents (dossier_id, nom_fichier, nom_original, type_document, taille_fichier, extension, chemin_fichier, user_id, date_upload) VALUES
(1, 'doc_1_plan_implantation.pdf', 'Plan_implantation_TOTAL_Warda.pdf', 'plan_implantation', 2048576, 'pdf', '/uploads/2025/09/doc_1_plan_implantation.pdf', 2, '2025-09-20 09:00:00'),
(1, 'doc_1_cni_demandeur.jpg', 'CNI_KOUAM_Pierre.jpg', 'piece_identite', 1024768, 'jpg', '/uploads/2025/09/doc_1_cni_demandeur.jpg', 2, '2025-09-20 09:05:00');