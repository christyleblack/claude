<?php

namespace Tests\Feature\Stocks;

use App\Libraries\SaisieMouvements;
use App\Models\Stocks\BonModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use DomainException;
use Tests\Support\Stocks\JeuDeStocks;
use Tests\Support\TestCase;

/**
 * Bons de mouvement (STK-06) : émission à la saisie, numérotation continue,
 * immuabilité, PDF et cloisonnement.
 *
 * @internal
 */
final class BonsTest extends TestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;
    use JeuDeStocks;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    private SaisieMouvements $saisie;
    private string $annee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creerJeuDeStocks();
        $this->annee = date('Y');
        $this->db->query("SELECT set_config('app.utilisateur_id', ?, false)", [(string) $this->magTerre->id]);
        $this->saisie = new SaisieMouvements($this->db);
        $this->saisie->enregistrer(['type' => 'entree', 'codification' => 'GASOIL', 'quantite' => '1000', 'destination' => (string) $this->id['T-R1']]);
        $this->saisie->enregistrer(['type' => 'entree', 'codification' => 'VEH', 'numeros_serie' => "V-1\nV-2", 'destination' => (string) $this->id['T-R1']]);
    }

    private function sortie(string $quantite): array
    {
        return $this->saisie->enregistrer(['type' => 'sortie', 'codification' => 'GASOIL', 'quantite' => $quantite, 'origine' => (string) $this->id['T-R1']]);
    }

    private function numeros(): array
    {
        return array_column($this->db->table('bon')->orderBy('id')->get()->getResultArray(), 'numero');
    }

    public function testUneEntreeNEmetPasDeBon(): void
    {
        $this->assertSame([], $this->numeros());
    }

    public function testLaNumerotationEstContinueParTypeEtParAnnee(): void
    {
        $this->sortie('10');
        $this->sortie('10');
        $this->saisie->enregistrer(['type' => 'transfert', 'codification' => 'GASOIL', 'quantite' => '5', 'origine' => (string) $this->id['T-R1'], 'destination' => (string) $this->id['T-R2']]);
        $this->saisie->enregistrer(['type' => 'perte', 'codification' => 'GASOIL', 'quantite' => '1', 'origine' => (string) $this->id['T-R1']]);

        $this->assertSame(["BS-{$this->annee}-000001", "BS-{$this->annee}-000002", "BT-{$this->annee}-000001", "BS-{$this->annee}-000003"], $this->numeros());
    }

    public function testUneSaisieRefuseeNeConsommePasDeNumero(): void
    {
        $this->sortie('10');

        try {
            $this->sortie('999999');
            $this->fail('La sortie aurait dû être refusée.');
        } catch (DomainException) {
        }
        $this->sortie('10');

        $this->assertSame(["BS-{$this->annee}-000001", "BS-{$this->annee}-000002"], $this->numeros());
    }

    public function testPlusieursNumerosDeSerieDonnentUnSeulBon(): void
    {
        $ids = $this->saisie->enregistrer(['type' => 'transfert', 'codification' => 'VEH', 'numeros_serie' => "V-1\nV-2", 'origine' => (string) $this->id['T-R1'], 'destination' => (string) $this->id['T-R2']]);

        $bon = (new BonModel())->duMouvement($ids[0]);
        $this->assertSame($bon['id'], (new BonModel())->duMouvement($ids[1])['id']);
        $this->assertCount(2, (new BonModel())->lignes((int) $bon['id']));
    }

    public function testUnBonEmisNeSeModifiePas(): void
    {
        $this->sortie('10');

        foreach (["UPDATE bon SET numero = 'FAUX'", 'DELETE FROM bon_mouvement', 'DELETE FROM bon'] as $sql) {
            try {
                $this->db->query($sql);
                $this->fail("Autorisé à tort : {$sql}");
            } catch (DatabaseException $e) {
                $this->assertStringContainsString('ne se modifie pas', $e->getMessage());
            }
        }
    }

    public function testUnMouvementNeFigurePasSurUnBonDuMauvaisType(): void
    {
        $entree = (int) $this->db->table('mouvement')->where('type', 'entree')->get()->getRow()->id;
        $this->sortie('10');
        $bon = (int) $this->db->table('bon')->get()->getRow()->id;

        $this->expectException(DatabaseException::class);
        $this->expectExceptionMessage('Bon refusé');

        $this->db->table('bon_mouvement')->insert(['bon_id' => $bon, 'mouvement_id' => $entree]);
    }

    public function testLePdfEstProduitPourQuiVoitLeBon(): void
    {
        $this->sortie('120');
        $bon = $this->db->table('bon')->get()->getRowArray();
        $this->redevenirProprietaire();

        $result = $this->actingAs($this->magTerre)->get('stocks/bons/' . $bon['id'] . '.pdf');

        $result->assertOK();
        $this->assertStringStartsWith('application/pdf', $result->response()->getHeaderLine('Content-Type'));
        $this->assertStringStartsWith('%PDF', $result->response()->getBody());
        $this->assertStringContainsString($bon['numero'], $result->response()->getHeaderLine('Content-Disposition'));

        $this->actingAs($this->magTerre)->get('stocks/bons')->assertSee($bon['numero']);
    }

    public function testLeContenuDuBonReprendSesMouvements(): void
    {
        $ids = $this->saisie->enregistrer(['type' => 'transfert', 'codification' => 'VEH', 'numeros_serie' => "V-1\nV-2", 'origine' => (string) $this->id['T-R1'], 'destination' => (string) $this->id['T-R2'], 'motif' => 'Motif fictif']);
        $model = new BonModel();
        $bon   = $model->avecAuteur()->where('bon.id', (int) $model->duMouvement($ids[0])['id'])->first();

        $html = view('stocks/bons/pdf', ['bon' => $bon, 'lignes' => $model->lignes((int) $bon['id'])]);

        foreach ([$bon['numero'], 'Bordereau de transfert', 'V-1', 'V-2', 'Rayon Terre 1', 'Rayon Terre 2', 'Motif fictif', 'mag.terre'] as $attendu) {
            $this->assertStringContainsString($attendu, $html);
        }
    }

    public function testUnBonHorsPerimetreEstIntrouvable(): void
    {
        $this->sortie('10');
        $bon = (int) $this->db->table('bon')->get()->getRow()->id;
        $this->redevenirProprietaire();

        $this->actingAs($this->magAir)->get('stocks/bons')->assertDontSee('BS-');

        $this->expectException(PageNotFoundException::class);
        $this->actingAs($this->magAir)->get('stocks/bons/' . $bon . '.pdf');
    }
}
