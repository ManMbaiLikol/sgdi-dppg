/**
 * Script de configuration de la base de données de test
 * Crée les utilisateurs de test et les données initiales
 */

const mysql = require('mysql2/promise');
require('dotenv').config({ path: '.env.test' });

async function setupTestDatabase() {
  console.log('🔧 Configuration de la base de données de test...\n');

  const connection = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'sgdi_test'
  });

  try {
    // Vérifier la connexion
    console.log('✅ Connexion à la base de données établie');

    // 1. Créer les utilisateurs de test
    console.log('\n👥 Création des utilisateurs de test...');

    const users = [
      {
        nom: 'Administrateur',
        prenom: 'Système',
        email: 'admin@minee.cm',
        password: '$2y$10$YourHashedPasswordHere', // À hasher avec bcrypt
        role: 'admin_systeme',
        telephone: '+237 600 000 001'
      },
      {
        nom: 'Chef',
        prenom: 'Service SDTD',
        email: 'chef.sdtd@minee.cm',
        password: '$2y$10$YourHashedPasswordHere',
        role: 'chef_service',
        telephone: '+237 600 000 002'
      },
      {
        nom: 'Billeteur',
        prenom: 'DPPG',
        email: 'billeteur@minee.cm',
        password: '$2y$10$YourHashedPasswordHere',
        role: 'billeteur',
        telephone: '+237 600 000 003'
      },
      {
        nom: 'ABANDA',
        prenom: 'Christian',
        email: 'christian.abanda@minee.cm',
        password: '$2y$10$YourHashedPasswordHere',
        role: 'cadre_dppg',
        telephone: '+237 600 000 027'
      },
      {
        nom: 'MAÏ',
        prenom: 'Salomon',
        email: 'salomon.mai@minee.cm',
        password: '$2y$10$YourHashedPasswordHere',
        role: 'cadre_dppg',
        telephone: '+237 600 000 016'
      },
      {
        nom: 'Chef',
        prenom: 'Commission',
        email: 'chef.commission@minee.cm',
        password: '$2y$10$YourHashedPasswordHere',
        role: 'chef_commission',
        telephone: '+237 600 000 004'
      },
      {
        nom: 'Cadre',
        prenom: 'DAJ',
        email: 'daj@minee.cm',
        password: '$2y$10$YourHashedPasswordHere',
        role: 'cadre_daj',
        telephone: '+237 600 000 005'
      },
      {
        nom: 'Sous-Directeur',
        prenom: 'SDTD',
        email: 'sous.directeur@minee.cm',
        password: '$2y$10$YourHashedPasswordHere',
        role: 'sous_directeur',
        telephone: '+237 600 000 006'
      },
      {
        nom: 'Directeur',
        prenom: 'DPPG',
        email: 'directeur.dppg@minee.cm',
        password: '$2y$10$YourHashedPasswordHere',
        role: 'directeur',
        telephone: '+237 600 000 007'
      },
      {
        nom: 'Cabinet',
        prenom: 'Ministre',
        email: 'cabinet.ministre@minee.cm',
        password: '$2y$10$YourHashedPasswordHere',
        role: 'ministre',
        telephone: '+237 600 000 008'
      }
    ];

    for (const user of users) {
      const [existing] = await connection.execute(
        'SELECT id FROM users WHERE email = ?',
        [user.email]
      );

      if (existing.length === 0) {
        await connection.execute(
          `INSERT INTO users (nom, prenom, email, password, role, telephone, actif, created_at)
           VALUES (?, ?, ?, ?, ?, ?, 1, NOW())`,
          [user.nom, user.prenom, user.email, user.password, user.role, user.telephone]
        );
        console.log(`   ✅ ${user.prenom} ${user.nom} (${user.role})`);
      } else {
        console.log(`   ⏭️  ${user.prenom} ${user.nom} existe déjà`);
      }
    }

    // 2. Créer des données de référence
    console.log('\n📋 Création des données de référence...');

    // Régions du Cameroun
    const regions = [
      'Adamaoua', 'Centre', 'Est', 'Extrême-Nord', 'Littoral',
      'Nord', 'Nord-Ouest', 'Ouest', 'Sud', 'Sud-Ouest'
    ];

    for (const region of regions) {
      const [existing] = await connection.execute(
        'SELECT id FROM regions WHERE nom = ?',
        [region]
      );

      if (existing.length === 0) {
        await connection.execute(
          'INSERT INTO regions (nom) VALUES (?)',
          [region]
        );
      }
    }

    console.log('   ✅ Régions créées');

    // Types d'infrastructure
    const typesInfra = [
      { code: 'implantation_station_service', libelle: 'Implantation Station-Service' },
      { code: 'reprise_station_service', libelle: 'Reprise Station-Service' },
      { code: 'implantation_point_consommateur', libelle: 'Implantation Point Consommateur' },
      { code: 'reprise_point_consommateur', libelle: 'Reprise Point Consommateur' },
      { code: 'implantation_depot_gpl', libelle: 'Implantation Dépôt GPL' },
      { code: 'implantation_centre_emplisseur', libelle: 'Implantation Centre Emplisseur' }
    ];

    for (const type of typesInfra) {
      const [existing] = await connection.execute(
        'SELECT id FROM types_infrastructure WHERE code = ?',
        [type.code]
      );

      if (existing.length === 0) {
        await connection.execute(
          'INSERT INTO types_infrastructure (code, libelle) VALUES (?, ?)',
          [type.code, type.libelle]
        );
      }
    }

    console.log('   ✅ Types d\'infrastructure créés');

    // 3. Nettoyer les anciennes données de test
    console.log('\n🧹 Nettoyage des données de test...');

    await connection.execute(
      `DELETE FROM dossiers WHERE nom_demandeur LIKE '%Test%'
       OR nom_demandeur LIKE '%test%'
       OR nom_demandeur LIKE '%E2E%'`
    );

    console.log('   ✅ Anciennes données de test supprimées');

    console.log('\n🎉 Configuration terminée avec succès!');
    console.log('\nVous pouvez maintenant exécuter les tests avec:');
    console.log('   npm test\n');

  } catch (error) {
    console.error('❌ Erreur lors de la configuration:', error);
    process.exit(1);
  } finally {
    await connection.end();
  }
}

// Exécuter si appelé directement
if (require.main === module) {
  setupTestDatabase()
    .then(() => process.exit(0))
    .catch(err => {
      console.error(err);
      process.exit(1);
    });
}

module.exports = { setupTestDatabase };
