/**
 * Script de nettoyage après les tests
 * Supprime les données de test créées pendant les tests E2E
 */

const mysql = require('mysql2/promise');
require('dotenv').config({ path: '.env.test' });

async function cleanup() {
  console.log('🧹 Nettoyage de la base de données de test...\n');

  const connection = await mysql.createConnection({
    host: process.env.DB_HOST || 'localhost',
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASSWORD || '',
    database: process.env.DB_NAME || 'sgdi_test'
  });

  try {
    // 1. Supprimer les dossiers de test
    console.log('📂 Suppression des dossiers de test...');

    const [dossiers] = await connection.execute(
      `SELECT id, numero_dossier FROM dossiers
       WHERE nom_demandeur LIKE '%Test%'
       OR nom_demandeur LIKE '%test%'
       OR nom_demandeur LIKE '%E2E%'
       OR nom_demandeur LIKE '%Société Pétrolière%'`
    );

    for (const dossier of dossiers) {
      // Supprimer les dépendances en cascade
      await connection.execute('DELETE FROM documents WHERE dossier_id = ?', [dossier.id]);
      await connection.execute('DELETE FROM commissions WHERE dossier_id = ?', [dossier.id]);
      await connection.execute('DELETE FROM paiements WHERE dossier_id = ?', [dossier.id]);
      await connection.execute('DELETE FROM inspections WHERE dossier_id = ?', [dossier.id]);
      await connection.execute('DELETE FROM visas WHERE dossier_id = ?', [dossier.id]);
      await connection.execute('DELETE FROM huitaines WHERE dossier_id = ?', [dossier.id]);
      await connection.execute('DELETE FROM historique_dossier WHERE dossier_id = ?', [dossier.id]);
      await connection.execute('DELETE FROM notifications WHERE dossier_id = ?', [dossier.id]);

      // Supprimer le dossier
      await connection.execute('DELETE FROM dossiers WHERE id = ?', [dossier.id]);

      console.log(`   ✅ Dossier ${dossier.numero_dossier} supprimé`);
    }

    console.log(`\n   Total: ${dossiers.length} dossier(s) supprimé(s)`);

    // 2. Supprimer les notifications de test
    console.log('\n📧 Suppression des notifications de test...');

    const [notifs] = await connection.execute(
      `DELETE FROM notifications WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)`
    );

    console.log(`   ✅ ${notifs.affectedRows} notification(s) récente(s) supprimée(s)`);

    // 3. Supprimer les logs de test récents
    console.log('\n📝 Suppression des logs récents...');

    const [logs] = await connection.execute(
      `DELETE FROM logs_activite WHERE created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)`
    );

    console.log(`   ✅ ${logs.affectedRows} log(s) récent(s) supprimé(s)`);

    // 4. Réinitialiser les compteurs
    console.log('\n🔢 Réinitialisation des compteurs...');

    // Optionnel: Réinitialiser auto_increment si nécessaire
    // await connection.execute('ALTER TABLE dossiers AUTO_INCREMENT = 1');

    console.log('   ✅ Compteurs OK');

    console.log('\n✅ Nettoyage terminé avec succès!\n');

  } catch (error) {
    console.error('❌ Erreur lors du nettoyage:', error);
    process.exit(1);
  } finally {
    await connection.end();
  }
}

// Exécuter si appelé directement
if (require.main === module) {
  cleanup()
    .then(() => process.exit(0))
    .catch(err => {
      console.error(err);
      process.exit(1);
    });
}

module.exports = { cleanup };
