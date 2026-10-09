<?php

namespace Tests\Feature;

use App\Models\ArticleModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Parcours complets sur les pages HTML des articles : lecture publique,
 * connexion obligatoire pour écrire, droits réservés à l'auteur ou à un admin.
 *
 * @internal
 */
final class ArticlesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null; // migrations de l'application et de Shield

    private User $alice;
    private User $bob;
    private ArticleModel $articles;

    protected function setUp(): void
    {
        // Repartir de services neufs : sinon l'utilisateur authentifié
        // dans un test précédent (session ou jeton) resterait connecté.
        $this->resetServices();

        parent::setUp();

        $this->alice    = $this->creerUtilisateur('alice');
        $this->bob      = $this->creerUtilisateur('bob');
        $this->articles = new ArticleModel();
    }

    private function creerUtilisateur(string $nom): User
    {
        /** @var User $user */
        $user = fake(UserModel::class, ['username' => $nom, 'active' => 1]);
        $user->addGroup('user');

        return $user;
    }

    /**
     * Jeton CSRF valide, à joindre à chaque formulaire envoyé en POST.
     */
    private function jeton(): array
    {
        return [csrf_token() => csrf_hash()];
    }

    private function creerArticle(?User $auteur, string $titre = 'Un article'): int
    {
        return $this->articles->insert([
            'user_id' => $auteur?->id,
            'titre'   => $titre,
            'contenu' => 'Contenu de test',
        ]);
    }

    // Lecture publique

    public function testLaListeEstPubliqueEtAfficheLAuteur(): void
    {
        $this->creerArticle($this->alice, 'Bonjour le monde');
        $this->creerArticle(null, 'Ancien article');

        $result = $this->get('articles');

        $result->assertOK();
        $result->assertSee('Bonjour le monde');
        $result->assertSee('par alice');
        $result->assertSee('par auteur inconnu');
        $result->assertSee('Connexion');
        $result->assertDontSee('Nouvel article');
    }

    public function testUnArticleSAfficheSansConnexion(): void
    {
        $id = $this->creerArticle($this->alice, 'Article visible');

        $result = $this->get("articles/{$id}");

        $result->assertOK();
        $result->assertSee('Article visible');
        $result->assertDontSee('Supprimer</button>');
    }

    public function testUnArticleInexistantRenvoie404(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);

        $this->get('articles/9999');
    }

    public function testLeTitreEstEchappeALAffichage(): void
    {
        $id = $this->creerArticle($this->alice, '<script>alert(1)</script>');

        $result = $this->get("articles/{$id}");

        $result->assertDontSee('<script>alert(1)</script>');
        $result->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;');
    }

    // Connexion obligatoire pour écrire

    public function testUnVisiteurEstRedirigeVersLaConnexion(): void
    {
        $this->get('articles/new')->assertRedirectTo('/login');
    }

    public function testUnVisiteurNePeutPasCreerUnArticle(): void
    {
        $result = $this->post('articles', $this->jeton() + ['titre' => 'Intrus']);

        $result->assertRedirectTo('/login');
        $this->dontSeeInDatabase('articles', ['titre' => 'Intrus']);
    }

    public function testUnVisiteurNePeutPasSupprimerUnArticle(): void
    {
        $id = $this->creerArticle($this->alice);

        $this->post("articles/{$id}/delete", $this->jeton())->assertRedirectTo('/login');
        $this->seeInDatabase('articles', ['id' => $id]);
    }

    public function testUnFormulaireSansJetonCsrfEstRefuse(): void
    {
        $this->expectException(\CodeIgniter\Security\Exceptions\SecurityException::class);

        $this->actingAs($this->alice)->post('articles', ['titre' => 'Sans jeton']);
    }

    // Création

    public function testUnUtilisateurConnecteCreeUnArticleASonNom(): void
    {
        $result = $this->actingAs($this->alice)
            ->post('articles', $this->jeton() + ['titre' => 'Mon premier article', 'contenu' => 'Texte']);

        $this->seeInDatabase('articles', ['titre' => 'Mon premier article', 'user_id' => $this->alice->id]);
        $id = $this->articles->where('titre', 'Mon premier article')->first()['id'];
        $result->assertRedirectTo("/articles/{$id}");
        $result->assertSessionHas('message', 'Article enregistré.');
    }

    public function testUnTitreTropCourtEstRefuse(): void
    {
        $result = $this->actingAs($this->alice)->post('articles', $this->jeton() + ['titre' => 'ab']);

        $result->assertRedirect();
        $result->assertSessionHas('erreurs');
        $this->assertSame(0, $this->articles->countAllResults());
    }

    // Droits de modification et de suppression

    public function testLAuteurPeutModifierSonArticle(): void
    {
        $id = $this->creerArticle($this->alice, 'Avant');

        $this->actingAs($this->alice)->get("articles/{$id}/edit")->assertOK();
        $result = $this->actingAs($this->alice)
            ->post("articles/{$id}", $this->jeton() + ['titre' => 'Après', 'contenu' => 'Modifié']);

        $result->assertRedirectTo("/articles/{$id}");
        $this->seeInDatabase('articles', ['id' => $id, 'titre' => 'Après']);
    }

    public function testLAuteurPeutSupprimerSonArticle(): void
    {
        $id = $this->creerArticle($this->alice);

        $result = $this->actingAs($this->alice)->post("articles/{$id}/delete", $this->jeton());

        $result->assertRedirectTo('/articles');
        $this->dontSeeInDatabase('articles', ['id' => $id]);
    }

    public function testUnAutreUtilisateurNeVoitPasLesBoutons(): void
    {
        $id = $this->creerArticle($this->alice);

        $this->actingAs($this->bob)->get("articles/{$id}")->assertDontSee('Supprimer</button>');
        $this->actingAs($this->alice)->get("articles/{$id}")->assertSee('Supprimer</button>');
    }

    public function testUnAutreUtilisateurNePeutPasModifier(): void
    {
        $id = $this->creerArticle($this->alice, 'Original');

        $this->actingAs($this->bob)->get("articles/{$id}/edit")->assertRedirectTo("/articles/{$id}");

        $result = $this->actingAs($this->bob)->post("articles/{$id}", $this->jeton() + ['titre' => 'Piraté']);

        $result->assertRedirectTo("/articles/{$id}");
        $result->assertSessionHas('erreur');
        $this->seeInDatabase('articles', ['id' => $id, 'titre' => 'Original']);
    }

    public function testUnAutreUtilisateurNePeutPasSupprimer(): void
    {
        $id = $this->creerArticle($this->alice);

        $result = $this->actingAs($this->bob)->post("articles/{$id}/delete", $this->jeton());

        $result->assertSessionHas('erreur');
        $this->seeInDatabase('articles', ['id' => $id]);
    }

    public function testUnArticleSansAuteurNestModifiableQueParUnAdmin(): void
    {
        $id = $this->creerArticle(null, 'Sans auteur');

        $this->actingAs($this->alice)->get("articles/{$id}/edit")->assertRedirectTo("/articles/{$id}");

        $this->bob->addGroup('admin');
        $this->actingAs($this->bob)->get("articles/{$id}/edit")->assertOK();
    }

    public function testUnAdminPeutModifierLArticleDUnAutre(): void
    {
        $id = $this->creerArticle($this->alice, 'Original');
        $this->bob->addGroup('admin');

        $this->actingAs($this->bob)->post("articles/{$id}", $this->jeton() + ['titre' => 'Corrigé par un admin']);

        $this->seeInDatabase('articles', ['id' => $id, 'titre' => 'Corrigé par un admin']);
    }
}
