<?php

namespace Tests\Feature\Stocks;

use App\Libraries\SaisieMouvements;
use App\Models\Referentiel\ArticleModel;
use App\Models\Stocks\MaterielModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\DatabaseTestTrait;
use DomainException;
use Tests\Support\Stocks\JeuDeStocks;
use Tests\Support\TestCase;

/**
 * Règles du journal et du stock (RG-10 à RG-14, STK-02 à STK-05, H-05), jouées sur la base.
 * Saisies en propriétaire (sans cloisonnement), avec l'auteur transmis comme le fait l'application.
 *
 * @internal
 */
final class MouvementsTest extends TestCase
{
    use DatabaseTestTrait;
    use JeuDeStocks;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    private SaisieMouvements $saisie;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creerJeuDeStocks();
        $this->db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) $this->magTerre->id]);
        $this->saisie = new SaisieMouvements($this->db);
    }

    private function saisir(array $saisie): array
    {
        return $this->saisie->enregistrer($saisie);
    }

    private function entreeGasoil(float $quantite, string $emplacement = 'T-R1'): int
    {
        return $this->saisir(['type' => 'entree', 'codification' => 'GASOIL', 'quantite' => (string) $quantite, 'destination' => (string) $this->id[$emplacement]])[0];
    }

    private function nombreDeMouvements(): int
    {
        return $this->db->table('mouvement')->countAllResults();
    }

    // Stock calculé (RG-10, RG-13)

    public function testLeStockSuitLesMouvements(): void
    {
        $this->entreeGasoil(5000);
        $this->saisir(['type' => 'transfert', 'codification' => 'GASOIL', 'quantite' => '1200,5', 'origine' => (string) $this->id['T-R1'], 'destination' => (string) $this->id['T-R2']]);
        $this->saisir(['type' => 'sortie', 'codification' => 'GASOIL', 'quantite' => '300', 'origine' => (string) $this->id['T-R1']]);

        $this->assertSame(3499.5, $this->stock('GASOIL', 'T-R1'));
        $this->assertSame(1200.5, $this->stock('GASOIL', 'T-R2'));
    }

    public function testLeStockEstExactementLaSommeDesMouvements(): void
    {
        $this->entreeGasoil(800);
        $this->saisir(['type' => 'transfert', 'codification' => 'GASOIL', 'quantite' => '100', 'origine' => (string) $this->id['T-R1'], 'destination' => (string) $this->id['T-R2']]);
        $this->saisir(['type' => 'perte', 'codification' => 'GASOIL', 'quantite' => '50', 'origine' => (string) $this->id['T-R2']]);

        // Recalcul indépendant à partir du seul journal
        $ecarts = $this->db->query(
            'WITH flux AS (
                SELECT article_id, emplacement_destination_id AS emplacement, quantite FROM mouvement WHERE emplacement_destination_id IS NOT NULL
                UNION ALL
                SELECT article_id, emplacement_origine_id, -quantite FROM mouvement WHERE emplacement_origine_id IS NOT NULL
             ), calcule AS (SELECT article_id, emplacement, sum(quantite) AS q FROM flux GROUP BY 1, 2 HAVING sum(quantite) <> 0)
             SELECT count(*) AS n FROM calcule c FULL JOIN stock s ON s.article_id = c.article_id AND s.emplacement_id = c.emplacement
             WHERE c.q IS DISTINCT FROM s.quantite',
        )->getRow()->n;

        $this->assertSame(0, (int) $ecarts);
    }

    public function testUnStockNePeutPasDevenirNegatif(): void
    {
        $this->entreeGasoil(100);
        $avant = $this->nombreDeMouvements();

        try {
            $this->saisir(['type' => 'sortie', 'codification' => 'GASOIL', 'quantite' => '150', 'origine' => (string) $this->id['T-R1']]);
            $this->fail('La sortie aurait dû être refusée.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Stock insuffisant', $e->getMessage());
            $this->assertStringContainsString('RG-13', $e->getMessage());
        }

        $this->assertSame($avant, $this->nombreDeMouvements());
        $this->assertSame(100.0, $this->stock('GASOIL', 'T-R1'));
    }

    // Suivi par numéro de série et par lot (RG-06, RG-11, RG-14)

    public function testUnePlusieursNumerosDeSerieDonnentUnMouvementChacun(): void
    {
        $ids = $this->saisir(['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => "V-001\nV-002\n\nV-003", 'destination' => (string) $this->id['T-R1']]);

        $this->assertCount(3, $ids);
        $this->assertSame(3.0, $this->stock('VEH', 'T-R1'));
        $this->assertSame($this->id['TERRE-D1'], (new MaterielModel())->where('numero_serie', 'V-002')->first()['proprietaire_entite_id']);
    }

    public function testUnMaterielNeSeTrouveQuAUnSeulEndroit(): void
    {
        $this->saisir(['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => 'V-001', 'destination' => (string) $this->id['T-R1']]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('RG-14');

        $this->saisir(['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => 'V-001', 'destination' => (string) $this->id['T-R2']]);
    }

    public function testUneSaisieMultipleEstEntierementAnnuleeSiUnNumeroEstRefuse(): void
    {
        $this->saisir(['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => 'V-002', 'destination' => (string) $this->id['T-R1']]);
        $avant = $this->nombreDeMouvements();

        try {
            $this->saisir(['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => "V-001\nV-002", 'destination' => (string) $this->id['T-R2']]);
            $this->fail('La saisie aurait dû être refusée.');
        } catch (DomainException) {
        }

        $this->assertSame($avant, $this->nombreDeMouvements(), 'aucun mouvement de la saisie refusée n\'est enregistré');
        $this->assertNull((new MaterielModel())->where('numero_serie', 'V-001')->first(), 'le matériel V-001 n\'est pas créé non plus');
    }

    public function testUnArticleSuiviParLotExigeUnLotConnuEnSortie(): void
    {
        $this->saisir(['type' => 'entree', 'codification' => 'MUN', 'numero_lot' => 'L-1', 'date_peremption' => '2030-01-01', 'quantite' => '500', 'destination' => (string) $this->id['T-R1']]);
        $this->assertSame(500.0, $this->stock('MUN', 'T-R1'));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Lot inconnu');

        $this->saisir(['type' => 'sortie', 'codification' => 'MUN', 'numero_lot' => 'L-INCONNU', 'quantite' => '1', 'origine' => (string) $this->id['T-R1']]);
    }

    public function testUneSaisieRefuseeNeComprometPasLesTransactionsSuivantes(): void
    {
        try {
            $this->saisir(['type' => 'sortie', 'codification' => 'GASOIL', 'quantite' => '1', 'origine' => (string) $this->id['T-R1']]);
            $this->fail('La sortie aurait dû être refusée.');
        } catch (DomainException) {
        }

        // CodeIgniter gardait l'échec en mémoire : cette transaction était annulée en silence
        $roles = new \App\Models\Droits\RoleModel();
        $role  = (int) $roles->insert(['code' => 'APRES', 'libelle' => 'Après échec', 'portee' => 'entite']);
        $roles->definirPermissions($role, ['stocks.mouvementer']);

        $this->assertSame(['stocks.mouvementer'], $roles->permissions($role));
    }

    // Annulation (RG-12)

    public function testUneAnnulationInverseLeMouvement(): void
    {
        $this->entreeGasoil(1000);
        $transfert = $this->saisir(['type' => 'transfert', 'codification' => 'GASOIL', 'quantite' => '400', 'origine' => (string) $this->id['T-R1'], 'destination' => (string) $this->id['T-R2']])[0];

        $annulation = $this->saisie->annuler($transfert, 'Erreur de rayon');

        $this->assertSame(1000.0, $this->stock('GASOIL', 'T-R1'));
        $this->assertSame(0.0, $this->stock('GASOIL', 'T-R2'));
        $ligne = $this->db->table('mouvement')->where('id', $annulation)->get()->getRowArray();
        $this->assertSame((string) $this->id['T-R2'], (string) $ligne['emplacement_origine_id']);
        $this->assertSame((string) $transfert, (string) $ligne['mouvement_annule_id']);
    }

    public function testUnMouvementNeSAnnuleQuUneFois(): void
    {
        $entree = $this->entreeGasoil(10);
        $this->saisie->annuler($entree, 'Erreur');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('déjà été annulé');

        $this->saisie->annuler($entree, 'Encore');
    }

    public function testUneAnnulationNeSAnnulePas(): void
    {
        $annulation = $this->saisie->annuler($this->entreeGasoil(10), 'Erreur');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('une annulation ne s\'annule pas');

        $this->saisie->annuler($annulation, 'Erreur');
    }

    public function testOnNAnnulePasUneEntreeDejaConsommee(): void
    {
        $entree = $this->entreeGasoil(100);
        $this->saisir(['type' => 'sortie', 'codification' => 'GASOIL', 'quantite' => '80', 'origine' => (string) $this->id['T-R1']]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stock insuffisant');

        $this->saisie->annuler($entree, 'Erreur');
    }

    // Immuabilité et intégrité (STK-03, H-05)

    public function testLeJournalEstImmuableMemePourLeProprietaire(): void
    {
        $this->entreeGasoil(10);

        // CASCADE : contourne la clé étrangère des bons, c'est bien l'immuabilité qui doit refuser
        foreach (['UPDATE mouvement SET quantite = 99', 'DELETE FROM mouvement', 'TRUNCATE mouvement CASCADE'] as $sql) {
            try {
                $this->db->query($sql);
                $this->fail("Autorisé à tort : {$sql}");
            } catch (DatabaseException $e) {
                $this->assertStringContainsString('immuable', $e->getMessage());
            }
        }
    }

    public function testUneFalsificationDuJournalEstDetectee(): void
    {
        $premier = $this->entreeGasoil(100);
        $this->entreeGasoil(50, 'T-R2');
        $this->assertNull($this->db->query('SELECT stocks_verifier_chaine() AS id')->getRow()->id, 'chaîne intacte');

        // Un administrateur de la base contourne la protection pour modifier une quantité
        $this->db->query('ALTER TABLE mouvement DISABLE TRIGGER mouvement_immuable');
        $this->db->query('UPDATE mouvement SET quantite = 1000 WHERE id = ?', [$premier]);
        $this->db->query('ALTER TABLE mouvement ENABLE TRIGGER mouvement_immuable');

        $this->assertSame((string) $premier, (string) $this->db->query('SELECT stocks_verifier_chaine() AS id')->getRow()->id);
    }

    public function testChaqueMouvementEnregistreSonAuteur(): void
    {
        $id = $this->entreeGasoil(10);

        $this->assertSame((string) $this->magTerre->id, (string) $this->db->table('mouvement')->where('id', $id)->get()->getRow()->auteur_id);
    }

    // État des matériels (STK-05) et contrôles divers

    public function testUneReformeSortLeMaterielEtBloqueSesMouvements(): void
    {
        $this->saisir(['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => 'V-001', 'destination' => (string) $this->id['T-R1']]);
        $reforme = $this->saisir(['type' => 'reforme', 'codification' => 'VEH', 'numeros_serie' => 'V-001', 'origine' => (string) $this->id['T-R1']])[0];
        $materiels = new MaterielModel();

        $this->assertSame('reforme', $materiels->where('numero_serie', 'V-001')->first()['etat']);
        $this->assertSame(0.0, $this->stock('VEH', 'T-R1'));

        try {
            $this->saisir(['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => 'V-001', 'destination' => (string) $this->id['T-R1']]);
            $this->fail('Un matériel réformé ne doit plus bouger.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('réformé', $e->getMessage());
        }

        $this->saisie->annuler($reforme, 'Réforme saisie par erreur');
        $this->assertSame('en_service', $materiels->where('numero_serie', 'V-001')->first()['etat']);
        $this->assertSame(1.0, $this->stock('VEH', 'T-R1'));
    }

    public function testUneDateFutureEstRefusee(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('futur');

        $this->saisir(['type' => 'entree', 'codification' => 'GASOIL', 'quantite' => '1', 'destination' => (string) $this->id['T-R1'], 'date_mouvement' => date('Y-m-d\TH:i', strtotime('+2 days'))]);
    }

    public function testOnNEntrePasUnArticleDesactive(): void
    {
        (new ArticleModel())->update($this->id['GASOIL'], ['actif' => false]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('désactivé');

        $this->entreeGasoil(1);
    }
}
