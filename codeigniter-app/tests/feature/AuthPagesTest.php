<?php

namespace Tests\Feature;

use CodeIgniter\Shield\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Pages de connexion et d'inscription, intégrées à la mise en page de l'application.
 *
 * @internal
 */
final class AuthPagesTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = null;

    protected function setUp(): void
    {
        $this->resetServices();

        parent::setUp();
    }

    private function jeton(): array
    {
        return [csrf_token() => csrf_hash()];
    }

    public function testLaPageDeConnexionUtiliseLaMiseEnPageDeLApplication(): void
    {
        $result = $this->get('login');

        $result->assertOK();
        $result->assertSee('<title>Se connecter</title>');
        $result->assertSee('Mes articles');
        $result->assertSee('name="email"');
        $result->assertSee('name="password"');
        $result->assertSee('name="remember"');
        $result->assertDontSee('bootstrap');
        $result->assertDontSee('magic-link');
    }

    public function testLaPageDInscriptionUtiliseLaMiseEnPageDeLApplication(): void
    {
        $result = $this->get('register');

        $result->assertOK();
        $result->assertSee('Mes articles');
        $result->assertSee('name="username"');
        $result->assertSee('name="password_confirm"');
        $result->assertDontSee('bootstrap');
    }

    public function testLInscriptionCreeLUtilisateur(): void
    {
        $result = $this->post('register', $this->jeton() + [
            'email'            => 'christy@example.com',
            'username'         => 'christy',
            'password'         => 'Tournesol-Bleu-2026',
            'password_confirm' => 'Tournesol-Bleu-2026',
        ]);

        $result->assertRedirect();
        $this->assertNotNull(model(UserModel::class)->findByCredentials(['email' => 'christy@example.com']));
    }

    public function testUnMauvaisMotDePasseEstRefuse(): void
    {
        fake(UserModel::class, ['username' => 'alice', 'active' => 1])
            ->createEmailIdentity(['email' => 'alice@example.com', 'password' => 'Tournesol-Bleu-2026']);

        $result = $this->post('login', $this->jeton() + ['email' => 'alice@example.com', 'password' => 'mauvais-mot-de-passe']);

        $result->assertRedirectTo('/login');
        $result->assertSessionHas('error');
        $this->assertFalse(auth()->loggedIn());
    }
}
