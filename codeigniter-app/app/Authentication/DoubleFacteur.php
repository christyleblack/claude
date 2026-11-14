<?php

namespace App\Authentication;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\Shield\Authentication\Actions\ConditionalActionInterface;
use CodeIgniter\Shield\Authentication\Actions\Email2FA;
use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Entities\UserIdentity;
use CodeIgniter\Shield\Exceptions\RuntimeException;
use CodeIgniter\Shield\Models\UserIdentityModel;

/**
 * Double facteur à la connexion pour les rôles sensibles (cahier des charges, section 4.3).
 *
 * Après le mot de passe, un code à six chiffres est envoyé par courriel ; il ne s'applique
 * qu'aux utilisateurs qui détiennent au moins un rôle marqué « double facteur », par
 * affectation ou par délégation active. La base fait ce calcul
 * (securite_double_facteur_requis) : le choix des rôles sensibles se paramètre sur chaque
 * rôle, sans développement (ADM-02).
 *
 * Hors production et sans serveur SMTP configuré (poste de développement), le code n'est
 * pas envoyé mais écrit dans le journal de l'application (writable/logs).
 */
class DoubleFacteur extends Email2FA implements ConditionalActionInterface
{
    public function appliesTo(User $user): bool
    {
        // PostgreSQL renvoie un booléen sous forme de texte 't' ou 'f' : (bool) 'f' vaudrait vrai
        $requis = db_connect()
            ->query('SELECT securite_double_facteur_requis(?)::int AS requis', [(int) $user->id])
            ->getRow()
            ->requis;

        return (int) $requis === 1;
    }

    /**
     * @return RedirectResponse|string
     */
    public function handle(IncomingRequest $request)
    {
        if (! self::envoiSimule()) {
            return parent::handle($request);
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();
        $user          = $authenticator->getPendingUser();

        if ($user === null) {
            throw new RuntimeException('Cannot get the pending login User.');
        }

        $email = $request->getPost('email');
        if (empty($email) || $email !== $user->email) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.invalidEmail', [$email]));
        }

        $identity = model(UserIdentityModel::class)->getIdentityByType($user, $this->getType());
        if (! $identity instanceof UserIdentity) {
            return redirect()->route('auth-action-show')->with('error', lang('Auth.need2FA'));
        }

        log_message('notice', 'Double facteur (développement, aucun courriel envoyé) : code {code} pour {email}', ['code' => $identity->secret, 'email' => $user->email]);

        return $this->view(setting('Auth.views')['action_email_2fa_verify'], ['envoiSimule' => true]);
    }

    /**
     * Vrai sur un poste de développement ou de test sans serveur SMTP : le courriel est simulé.
     */
    public static function envoiSimule(): bool
    {
        return ENVIRONMENT !== 'production' && config('Email')->SMTPHost === '';
    }
}
