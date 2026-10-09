<?php

namespace Tests\Feature;

use App\Models\ArticleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * API JSON : lecture publique, création protégée par jeton d'accès Shield.
 *
 * @internal
 */
final class ApiArticlesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    private User $alice;

    protected function setUp(): void
    {
        // Repartir de services neufs : sinon l'utilisateur authentifié
        // dans un test précédent (session ou jeton) resterait connecté.
        $this->resetServices();

        parent::setUp();

        $this->alice = fake(UserModel::class, ['username' => 'alice', 'active' => 1]);
    }

    public function testLaListeEstPubliqueEtIndiqueLAuteur(): void
    {
        (new ArticleModel())->insert(['user_id' => $this->alice->id, 'titre' => 'Article API']);

        $result = $this->get('api/articles');

        $result->assertOK();
        $articles = json_decode($result->getJSON(), true);
        $this->assertCount(1, $articles);
        $this->assertSame('Article API', $articles[0]['titre']);
        $this->assertSame('alice', $articles[0]['auteur']);
    }

    public function testLaCreationSansJetonEstRefusee(): void
    {
        $result = $this->post('api/articles', ['titre' => 'Sans jeton']);

        $result->assertStatus(401);
        $this->dontSeeInDatabase('articles', ['titre' => 'Sans jeton']);
    }

    public function testLaCreationAvecUnFauxJetonEstRefusee(): void
    {
        $result = $this->withHeaders(['Authorization' => 'Bearer faux-jeton'])
            ->post('api/articles', ['titre' => 'Faux jeton']);

        $result->assertStatus(401);
    }

    public function testLaCreationAvecJetonEnregistreLAuteur(): void
    {
        $jeton = $this->alice->generateAccessToken('test')->raw_token;

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $jeton])
            ->post('api/articles', ['titre' => 'Créé par API', 'contenu' => 'ok']);

        $result->assertStatus(201);
        $result->assertJSONFragment(['titre' => 'Créé par API', 'auteur' => 'alice']);
        $this->seeInDatabase('articles', ['titre' => 'Créé par API', 'user_id' => $this->alice->id]);
    }

    public function testLaValidationSAppliqueAussiALAPI(): void
    {
        $jeton = $this->alice->generateAccessToken('test')->raw_token;

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $jeton])
            ->post('api/articles', ['titre' => 'ab']);

        $result->assertStatus(400);
        $result->assertJSONFragment(['erreurs' => ['titre' => 'Le titre doit contenir au moins 3 caractères.']]);
    }
}
