/**
 * Fonctions utilitaires pour les tests E2E SGDI
 */

/**
 * Données utilisateurs pour les tests
 */
export const testUsers = {
  admin: {
    email: 'admin@minee.cm',
    password: 'Admin@2025',
    role: 'admin_systeme',
    name: 'Administrateur Système'
  },
  chef_service: {
    email: 'chef.sdtd@minee.cm',
    password: 'Chef@2025',
    role: 'chef_service',
    name: 'Chef de Service SDTD'
  },
  billeteur: {
    email: 'billeteur@minee.cm',
    password: 'Billet@2025',
    role: 'billeteur',
    name: 'Billeteur DPPG'
  },
  christian_abanda: {
    email: 'christian.abanda@minee.cm',
    password: 'Christian@2025',
    role: 'cadre_dppg',
    name: 'Christian ABANDA',
    id: 27
  },
  salomon_mai: {
    email: 'salomon.mai@minee.cm',
    password: 'Salomon@2025',
    role: 'cadre_dppg',
    name: 'Salomon MAÏ',
    id: 16
  },
  chef_commission: {
    email: 'chef.commission@minee.cm',
    password: 'ChefCom@2025',
    role: 'chef_commission',
    name: 'Chef de Commission'
  },
  cadre_daj: {
    email: 'daj@minee.cm',
    password: 'DAJ@2025',
    role: 'cadre_daj',
    name: 'Cadre DAJ'
  },
  sous_directeur: {
    email: 'sous.directeur@minee.cm',
    password: 'SousDir@2025',
    role: 'sous_directeur',
    name: 'Sous-Directeur SDTD'
  },
  directeur: {
    email: 'directeur.dppg@minee.cm',
    password: 'Directeur@2025',
    role: 'directeur',
    name: 'Directeur DPPG'
  },
  ministre: {
    email: 'cabinet.ministre@minee.cm',
    password: 'Ministre@2025',
    role: 'ministre',
    name: 'Cabinet du Ministre'
  }
};

/**
 * Types d'infrastructure
 */
export const infrastructureTypes = {
  stationService: {
    implantation: 'implantation_station_service',
    reprise: 'reprise_station_service',
    label: 'Station-Service'
  },
  pointConsommateur: {
    implantation: 'implantation_point_consommateur',
    reprise: 'reprise_point_consommateur',
    label: 'Point Consommateur'
  },
  depotGPL: {
    implantation: 'implantation_depot_gpl',
    label: 'Dépôt GPL'
  },
  centreEmplisseur: {
    implantation: 'implantation_centre_emplisseur',
    label: 'Centre Emplisseur'
  }
};

/**
 * Statuts de dossier
 */
export const statuses = {
  brouillon: 'brouillon',
  enAttentePaiement: 'en_attente_paiement',
  enAnalyseDAJ: 'en_analyse_daj',
  enControleCompletude: 'en_controle_completude',
  enAttenteInspection: 'en_attente_inspection',
  enCours: 'en_cours',
  inspecte: 'inspecte',
  rapportValide: 'rapport_valide',
  visa1: 'visa_1',
  visa2: 'visa_2',
  visa3: 'visa_3',
  enAttenteDecision: 'en_attente_decision',
  approuve: 'approuve',
  refuse: 'refuse'
};

/**
 * Se connecter en tant qu'utilisateur
 * @param {import('@playwright/test').Page} page
 * @param {Object} user - Objet utilisateur depuis testUsers
 */
export async function login(page, user) {
  await page.goto('/login.php');

  // Remplir le formulaire
  await page.fill('input[name="email"]', user.email);
  await page.fill('input[name="password"]', user.password);

  // Soumettre
  await page.click('button[type="submit"]');

  // Attendre la redirection vers le dashboard
  await page.waitForURL('**/dashboard.php', { timeout: 10000 });

  // Vérifier que l'utilisateur est bien connecté
  await page.waitForSelector('.user-name, .user-info', { timeout: 5000 });
}

/**
 * Se déconnecter
 * @param {import('@playwright/test').Page} page
 */
export async function logout(page) {
  await page.goto('/logout.php');
  await page.waitForURL('**/login.php', { timeout: 5000 });
}

/**
 * Créer un dossier de test
 * @param {import('@playwright/test').Page} page
 * @param {Object} options - Options du dossier
 * @returns {Promise<string>} Numéro du dossier créé
 */
export async function createDossier(page, options = {}) {
  const defaults = {
    type: 'implantation_station_service',
    demandeur: 'Société Test',
    localisation: 'Yaoundé',
    commune: 'Yaoundé 1er',
    departement: 'Mfoundi',
    region: 'Centre',
    operateur: 'TOTAL Cameroun'
  };

  const data = { ...defaults, ...options };

  // Aller vers la page de création
  await page.goto('/modules/dossiers/create.php');

  // Remplir le formulaire
  await page.selectOption('select[name="type_infrastructure"]', data.type);
  await page.fill('input[name="nom_demandeur"]', data.demandeur);
  await page.fill('input[name="localisation"]', data.localisation);
  await page.selectOption('select[name="commune"]', data.commune);
  await page.selectOption('select[name="departement"]', data.departement);
  await page.selectOption('select[name="region"]', data.region);

  if (data.operateur) {
    await page.fill('input[name="operateur"]', data.operateur);
  }

  // Soumettre
  await page.click('button[type="submit"]');

  // Attendre la redirection
  await page.waitForURL('**/modules/dossiers/view.php?id=*', { timeout: 10000 });

  // Extraire le numéro de dossier
  const url = page.url();
  const dossierId = url.match(/id=(\d+)/)[1];

  return dossierId;
}

/**
 * Uploader un document
 * @param {import('@playwright/test').Page} page
 * @param {string} dossierId
 * @param {string} filePath
 * @param {string} typeDocument
 */
export async function uploadDocument(page, dossierId, filePath, typeDocument = 'autre') {
  await page.goto(`/modules/dossiers/upload_documents.php?dossier_id=${dossierId}`);

  // Sélectionner le type de document
  await page.selectOption('select[name="type_document"]', typeDocument);

  // Uploader le fichier
  const fileInput = await page.locator('input[type="file"]');
  await fileInput.setInputFiles(filePath);

  // Soumettre
  await page.click('button[type="submit"]');

  // Attendre confirmation
  await page.waitForSelector('.alert-success, .success-message', { timeout: 5000 });
}

/**
 * Constituer une commission
 * @param {import('@playwright/test').Page} page
 * @param {string} dossierId
 * @param {Object} membres - IDs des membres
 */
export async function constituerCommission(page, dossierId, membres = {}) {
  await page.goto(`/modules/commission/constituer.php?dossier_id=${dossierId}`);

  // Sélectionner les membres
  if (membres.inspecteur) {
    await page.selectOption('select[name="inspecteur_id"]', membres.inspecteur);
  }
  if (membres.daj) {
    await page.selectOption('select[name="daj_id"]', membres.daj);
  }
  if (membres.chef) {
    await page.selectOption('select[name="chef_commission_id"]', membres.chef);
  }

  // Soumettre
  await page.click('button[type="submit"]');

  // Attendre confirmation
  await page.waitForSelector('.alert-success, .success-message', { timeout: 5000 });
}

/**
 * Enregistrer un paiement
 * @param {import('@playwright/test').Page} page
 * @param {string} dossierId
 * @param {number} montant
 */
export async function enregistrerPaiement(page, dossierId, montant = 50000) {
  await page.goto(`/modules/paiements/enregistrer.php?dossier_id=${dossierId}`);

  // Remplir le formulaire
  await page.fill('input[name="montant"]', montant.toString());
  await page.fill('input[name="numero_recu"]', `REC${Date.now()}`);
  await page.fill('input[name="date_paiement"]', new Date().toISOString().split('T')[0]);

  // Soumettre
  await page.click('button[type="submit"]');

  // Attendre confirmation
  await page.waitForSelector('.alert-success, .success-message', { timeout: 5000 });
}

/**
 * Vérifier qu'un dossier est visible dans la liste
 * @param {import('@playwright/test').Page} page
 * @param {string} dossierId
 * @returns {Promise<boolean>}
 */
export async function isDossierVisible(page, dossierId) {
  await page.goto('/modules/dossiers/list.php');

  const dossierRow = await page.locator(`tr:has-text("${dossierId}")`);
  return await dossierRow.count() > 0;
}

/**
 * Tenter d'accéder à un dossier directement
 * @param {import('@playwright/test').Page} page
 * @param {string} dossierId
 * @returns {Promise<boolean>} True si accès autorisé, False si bloqué
 */
export async function canAccessDossier(page, dossierId) {
  const response = await page.goto(`/modules/dossiers/view.php?id=${dossierId}`);

  // Si 403 ou redirection vers erreur
  if (response.status() === 403) {
    return false;
  }

  // Vérifier s'il y a un message d'erreur
  const errorMsg = await page.locator('.alert-danger, .error-message').count();
  if (errorMsg > 0) {
    return false;
  }

  // Vérifier qu'on voit bien le contenu du dossier
  const dossierContent = await page.locator('.dossier-details, .dossier-info').count();
  return dossierContent > 0;
}

/**
 * Attendre qu'un email soit envoyé (en vérifiant les logs)
 * @param {import('@playwright/test').Page} page
 * @param {string} destinataire
 * @param {number} timeout
 */
export async function waitForEmail(page, destinataire, timeout = 30000) {
  // Aller vers les logs email (si accessibles)
  const startTime = Date.now();

  while (Date.now() - startTime < timeout) {
    await page.goto('/modules/admin/email_logs.php');

    const emailRow = await page.locator(`tr:has-text("${destinataire}")`);
    if (await emailRow.count() > 0) {
      return true;
    }

    await page.waitForTimeout(2000); // Attendre 2 secondes avant de réessayer
  }

  return false;
}

/**
 * Générer un numéro de dossier unique
 */
export function generateDossierNumber(type = 'SS') {
  const timestamp = Date.now();
  return `${type}${timestamp}`;
}

/**
 * Nettoyer la base de données de test
 * @param {import('@playwright/test').Page} page
 */
export async function cleanupTestData(page) {
  // Cette fonction peut être appelée après les tests pour nettoyer
  // Vous pouvez soit appeler un script PHP de cleanup
  // Ou utiliser une connexion MySQL directe
  await page.goto('/tests/utils/cleanup.php?token=test_cleanup_2025');
}

/**
 * Prendre une capture d'écran avec timestamp
 * @param {import('@playwright/test').Page} page
 * @param {string} name
 */
export async function screenshot(page, name) {
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
  await page.screenshot({
    path: `test-results/screenshots/${name}-${timestamp}.png`,
    fullPage: true
  });
}
