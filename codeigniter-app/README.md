# CodeIgniter 4 Application Starter

## What is CodeIgniter?

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).

This repository holds a composer-installable app starter.
It has been built from the
[development repository](https://github.com/codeigniter4/CodeIgniter4).

More information about the plans for version 4 can be found in [CodeIgniter 4](https://forum.codeigniter.com/forumdisplay.php?fid=28) on the forums.

You can read the [user guide](https://codeigniter.com/user_guide/)
corresponding to the latest version of the framework.

## Installation & updates

`composer create-project codeigniter4/appstarter` then `composer update` whenever
there is a new release of the framework.

When updating, check the release notes to see if there are any changes you might need to apply
to your `app` folder. The affected files can be copied or merged from
`vendor/codeigniter4/framework/app`.

## Setup

Copy `env` to `.env` and tailor for your app, specifically the baseURL
and any database settings.

## Important Change with index.php

`index.php` is no longer in the root of the project! It has been moved inside the *public* folder,
for better security and separation of components.

This means that you should configure your web server to "point" to your project's *public* folder, and
not to the project root. A better practice would be to configure a virtual host to point there. A poor practice would be to point your web server to the project root and expect to enter *public/...*, as the rest of your logic and the
framework are exposed.

**Please** read the user guide for a better explanation of how CI4 works!

## Repository Management

We use GitHub issues, in our main repository, to track **BUGS** and to track approved **DEVELOPMENT** work packages.
We use our [forum](http://forum.codeigniter.com) to provide SUPPORT and to discuss
FEATURE REQUESTS.

This repository is a "distribution" one, built by our release preparation script.
Problems with it can be raised on our forum, or as issues in the main repository.

## Server Requirements

PHP version 8.2 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)

> [!WARNING]
> - The end of life date for PHP 7.4 was November 28, 2022.
> - The end of life date for PHP 8.0 was November 26, 2023.
> - The end of life date for PHP 8.1 was December 31, 2025.
> - If you are still using below PHP 8.2, you should upgrade immediately.
> - The end of life date for PHP 8.2 will be December 31, 2026.

Additionally, make sure that the following extensions are enabled in your PHP:

- json (enabled by default - don't turn it off)
- [pgsql](https://www.php.net/manual/en/pgsql.installation.php) for PostgreSQL (used by this application)
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library

## Base de données PostgreSQL

L'application utilise PostgreSQL (cahier des charges, section 6) : l'extension `ltree` sert aux arbres de l'organisation et la sécurité au niveau des lignes (*Row Level Security*) au cloisonnement des données. L'extension `ltree` est activée par une migration ; aucun droit de superutilisateur n'est nécessaire.

1. Créer l'utilisateur et les deux bases (développement et tests), en remplaçant `ci_password` par un mot de passe de votre choix :

```bash
sudo -u postgres psql
```

```sql
CREATE ROLE ci_user LOGIN PASSWORD 'ci_password';
CREATE DATABASE gestion_materiel OWNER ci_user ENCODING 'UTF8' TEMPLATE template0;
CREATE DATABASE gestion_materiel_tests OWNER ci_user ENCODING 'UTF8' TEMPLATE template0;
-- Rôle sous lequel l'application lit et écrit, soumis au cloisonnement (voir « Utilisateurs et droits »)
CREATE ROLE gestion_app NOLOGIN;
GRANT gestion_app TO ci_user;
\q
```

2. Copier `env` en `.env` et renseigner (le fichier `.env` n'est pas versionné) :

```
CI_ENVIRONMENT = development
database.default.hostname = localhost
database.default.database = gestion_materiel
database.default.username = ci_user
database.default.password = ci_password
database.default.port = 5432

database.tests.hostname = localhost
database.tests.database = gestion_materiel_tests
database.tests.username = ci_user
database.tests.password = ci_password
database.tests.port = 5432
```

La base `gestion_materiel_tests` est vidée à chaque exécution des tests : ne jamais la faire pointer vers la base de développement.

3. Lancer les migrations puis le serveur :

```bash
php spark migrate --all
php spark serve
```

**Données** : n'utiliser que des données fictives en développement et dans les tests, jamais de données réelles (cahier des charges, section 7.4).

Exemple fourni : table `articles` (`app/Database/Migrations`), `ArticleModel` (avec règles de validation), contrôleurs `Articles` (pages HTML) et `Api\Articles` (JSON).

Pages HTML (vues dans `app/Views/articles`, mise en page commune dans `app/Views/layouts/main.php`) :

| Adresse | Rôle |
|---------|------|
| `GET /articles` | liste des articles |
| `GET /articles/new` | formulaire de création |
| `POST /articles` | enregistrement (protégé par jeton CSRF) |
| `GET /articles/{id}` | affichage d'un article |
| `GET /articles/{id}/edit` | formulaire de modification |
| `POST /articles/{id}` | enregistrement des modifications (CSRF) |
| `POST /articles/{id}/delete` | suppression, après confirmation (CSRF) |

API JSON (sans CSRF) : `GET /api/articles` (public) et `POST /api/articles` (champs `titre`, `contenu`, jeton d'accès requis, voir plus bas).

## Module Organisation (lot 1)

Référentiel des entités, des forces, des sites et des emplacements de stockage (exigence REF-06, règles RG-01 à RG-03). Modèle de données : `docs/lot1-modele-de-donnees.md`, section 2.

| Adresse | Rôle |
|---------|------|
| `/organisation/entites` | arbre des entités, hiérarchique ou logistique (`?axe=logistique`) |
| `/organisation/entites/{id}` | fiche d'une entité : ses deux chaînes, ses subordonnées, ses sites |
| `/organisation/forces` | forces et paramètres d'autonomie de leur direction du matériel |
| `/organisation/types` | types d'entités (ajouter un niveau d'organisation sans développement) |
| `/organisation/sites/{id}` | site et ses emplacements de stockage |

- **Droits** : chacun ne voit que les entités de son périmètre ; créer ou modifier exige la permission `organisation.gerer` sur l'entité concernée (et au niveau national pour les forces, les types d'entités et les entités racines). Voir « Utilisateurs et droits ».
- **Garanties apportées par la base** (migration `CreerOrganisation`) : les chemins `ltree` des deux arbres et la force de chaque entité sont calculés par des déclencheurs PostgreSQL et ne peuvent pas être saisis ; un déplacement se propage à toute la branche ; les cycles sont refusés ; un emplacement ne peut être contenu que dans un emplacement du même site.
- **Force d'une entité** : celle de son ancêtre hiérarchique le plus proche qui est la racine d'une force. La DCMA et ses services n'appartiennent à aucune force (RG-02).
- **Pas de suppression** : une entité, un site ou un emplacement se désactive.

Charger le jeu de démonstration (**données fictives** : DCMA, ses six services, les sept forces, une unité et un dépôt par force) dans une base vide :

```bash
php spark db:seed OrganisationSeeder
```

Donner à un utilisateur le droit de modifier l'organisation : lui affecter un rôle qui a la permission `organisation.gerer`, par exemple :

```bash
php spark droits:affecter nom_utilisateur ADMIN_FONCTIONNEL DCMA
```

## Module Référentiel du matériel (lot 1)

Référentiel unique national (REF-01 à REF-05, RG-05 à RG-09). Modèle de données : `docs/lot1-modele-de-donnees.md`, section 4.

| Adresse | Rôle |
|---------|------|
| `/referentiel/articles` | recherche d'articles (désignation, codification, filière, catégorie, suivi, sensibilité) |
| `/referentiel/articles/{id}` | fiche d'un article et fiches propres à chaque force |
| `/referentiel/filieres` | filières et service gestionnaire |
| `/referentiel/filieres/{id}/categories` | arborescence des catégories d'une filière |

- **Filière** : gérée par un seul service central (RG-05) ; la filière d'un article découle de sa catégorie.
- **Mode de suivi** (RG-06, RG-07) : quantité, lot ou numéro de série. La base impose : armement par numéro de série, munitions par lot, numéro de série à l'unité ; une catégorie peut en plus imposer le mode de ses articles.
- **Fiche propre à une force** (REF-02, RG-09) : code et désignation locaux, attributs libres « nom = valeur » ; au plus une par force.
- **Unités de gestion** : unité, litre, mètre cube, kilogramme, tonne (fixées par migration).
- **Droits** : tout utilisateur connecté consulte le référentiel. Gérer les articles et les catégories exige `referentiel.gerer` au niveau national, éventuellement **limité à une filière** par l'affectation ; gérer les filières exige une affectation non limitée ; les fiches d'une force exigent `referentiel.fiche_force` sur cette force. Ces règles sont aussi appliquées par la base (*Row Level Security* sur l'écriture). Rien ne se supprime : un article se désactive.
- **Lots et matériels sérialisés** : ils seront créés par les mouvements d'entrée (module Stocks et mouvements).

```bash
php spark db:seed ReferentielSeeder   # trois filières du cahier des charges, catégories, articles d'exemple (fictifs)
```

## Module Stocks et mouvements (lot 1)

Exigences STK-01 à STK-05, règles RG-10 à RG-14. Modèle de données : `docs/lot1-modele-de-donnees.md`, section 5.

| Adresse | Rôle |
|---------|------|
| `/stocks` | stock par emplacement, par entité détentrice ou total, filtrable sur une entité et ses subordonnées (STK-01) |
| `/stocks/mouvements` | journal des mouvements (recherche par article, lot, n° de série, type) |
| `/stocks/mouvements/new` | saisie : entrée, sortie, transfert, perception, réintégration, perte, réforme (STK-02) |
| `/stocks/mouvements/{id}` | détail, empreinte, annulation par mouvement inverse |
| `/stocks/materiels/{id}` | matériel sérialisé : emplacement actuel, historique, état (STK-05) |
| `/stocks/bons` | bons émis : bon de perception, bon de sortie, bordereau de transfert (STK-06) |
| `/stocks/bons/{id}.pdf` | PDF d'un bon |

**Garanties apportées par la base** (migration `CreerStocks`) :

- **Journal immuable** (RG-12, STK-03) : aucun mouvement ne se modifie ni ne se supprime, même par le propriétaire des tables ; une erreur se corrige par un mouvement d'annulation, lié à l'original, une seule fois.
- **Stock calculé** (RG-10) : la table `stock` est tenue exclusivement par la base à chaque mouvement ; l'application ne peut que la lire.
- **Stock jamais négatif** (RG-13), y compris quand deux sorties arrivent en même temps : la ligne de stock est verrouillée pendant la mise à jour.
- **Suivi** (RG-06, RG-11, RG-14) : quantité, lot (créé à la première entrée, avec péremption) ou numéro de série (un mouvement par numéro ; un matériel n'est qu'à un seul endroit ; un matériel réformé ne bouge plus).
- **Propriétaire et détenteur** (STK-04) : le détenteur est l'entité de l'emplacement ; le propriétaire est porté par le mouvement (par défaut le détenteur).
- **Infalsifiabilité** (H-05) : chaque mouvement porte une empreinte SHA-256 chaînée au précédent. `php spark stocks:verifier` signale le premier mouvement altéré, même par un administrateur de la base ; à planifier régulièrement.
- **Droits** : on ne voit que les mouvements, le stock et les matériels de son périmètre. Saisir ou annuler exige `stocks.mouvementer` sur l'entité responsable : le destinataire pour une entrée ou une réintégration, l'expéditeur sinon.

Une saisie de plusieurs numéros de série forme une seule transaction : si un numéro est refusé, rien n'est enregistré. La régularisation est réservée aux inventaires (module suivant).

**Bons** (STK-06, migration `CreerBons`) : chaque saisie de perception, de sortie (ou perte, réforme) et de transfert (ou réintégration) émet automatiquement, dans la même transaction, un bon de perception, un bon de sortie ou un bordereau de transfert. Une saisie de plusieurs numéros de série donne un seul bon de plusieurs lignes. La numérotation est continue et sans trou, par type et par année (`BP-`, `BS-`, `BT-2026-000001`) ; une saisie refusée ne consomme pas de numéro. Un bon émis ne se modifie pas ; son PDF (Dompdf, licence LGPL 2.1) est produit à la demande, signale les mouvements annulés et reprend l'empreinte de chaque mouvement. Un bon n'est visible que si ses mouvements le sont.

```bash
php spark db:seed StocksSeeder   # mouvements fictifs dans le dépôt de l'Armée de Terre (exige un compte utilisateur)
php spark stocks:verifier        # intégrité du journal
```

## Module Inventaires (lot 1)

Exigences INV-01 à INV-03, règles RG-31 et RG-32. Modèle de données : `docs/lot1-modele-de-donnees.md`, section 6.

| Adresse | Rôle |
|---------|------|
| `/inventaires` | inventaires du périmètre, par statut |
| `/inventaires/new` | planification : complet, tournant ou contradictoire ; entité, emplacement facultatif, compteur, cédant et prenant (droit `inventaires.gerer`) |
| `/inventaires/{id}` | fiche : lancement, feuille de comptage, ajout d'un matériel trouvé, écarts, signatures, validation, annulation |

Déroulé : **planifié** → lancé (**en cours**) : les quantités théoriques sont figées et les emplacements gelés → **compté** par le compteur désigné → **validé** par une autre personne détenant `inventaires.valider` (RG-32), après signature du cédant et du prenant pour un inventaire contradictoire (INV-03). La validation crée un mouvement de régularisation par écart (RG-31) et lève le gel. Tous ces contrôles sont faits par la base, pas seulement par l'interface.

Rôles par défaut (`php spark db:seed DroitsSeeder`, à relancer pour ajouter les nouvelles permissions) : le gestionnaire de dépôt planifie et lance, le chef du service logistique de force valide.

## Module Demandes et validations (lot 2)

Exigences DEM-01 à DEM-04, règles RG-15 à RG-19, circuits paramétrables (ADM-02). Modèle et hypothèses : `docs/lot2-demandes.md`.

| Adresse | Rôle |
|---------|------|
| `/demandes` | mes demandes, à valider, à servir, tout mon périmètre ; filtre par statut |
| `/demandes/new` | nouvelle demande en brouillon : entité demandeuse, filière (une seule), objet, date de besoin, priorité |
| `/demandes/{id}` | lignes, soumission, circuit de validation, décision, service par perception, clôture, annulation, historique |
| `/demandes/circuits` | paramétrage des circuits de validation par filière (droit `demandes.circuits`) |
| `/notifications` | notifications de l'utilisateur ; leur nombre s'affiche dans le menu |

Déroulé : le demandeur saisit sa demande en **brouillon** puis la **soumet**. Le circuit de sa filière (par défaut : échelon logistique supérieur, puis service gestionnaire de la filière au-delà de 100 unités sur une ligne) est copié en étapes. Chaque étape est **validée** ou **refusée** par un valideur de l'entité concernée, jamais par le demandeur (RG-19). Une fois validée, la demande est **servie** par des perceptions depuis les dépôts de l'entité de service, avec bon de perception. Une demande partiellement servie se **clôture** ; tant que rien n'est servi, elle s'**annule**. Toutes ces règles sont appliquées par la base.

Rôles par défaut (`php spark db:seed DroitsSeeder`) :
- demandeur et gestionnaire de dépôt : `demandes.saisir` ;
- chef logistique de force, gestionnaire de filière et directeur DCMA : `demandes.valider` ;
- administrateur fonctionnel : `demandes.circuits` ;
- service : `stocks.mouvementer` sur l'entité de service ou l'un de ses dépôts.

## Utilisateurs et droits (lot 1, section 4)

CodeIgniter Shield gère les comptes et la connexion ; **les droits viennent des affectations** stockées en base :

- **Rôle** (RBAC) : un ensemble de permissions (`organisation.gerer`, `referentiel.gerer`, `referentiel.fiche_force`, `stocks.mouvementer`, `administration.utilisateurs`, `administration.roles`, `audit.consulter`) et une portée, *nationale* (toute l'organisation) ou *entité* (l'entité d'affectation et ses subordonnées hiérarchiques). Rôles et permissions se paramètrent dans Administration › Rôles (ADM-02).
- **Affectation** (ABAC, RG-04) : un rôle exercé sur une entité, du … au …, éventuellement limité à une filière (H-02) ; un utilisateur peut en avoir plusieurs. Une affectation se révoque, elle ne se supprime pas.
- **Délégation** (règle 4.2-5) : le titulaire d'une affectation la confie temporairement à un autre utilisateur (page « Mes droits »), avec un motif ; la période doit tenir dans celle de l'affectation, l'auto-délégation est refusée par la base.
- **Sans affectation active, un utilisateur ne voit aucune donnée.**

### Cloisonnement dans la base (règle 4.2-6)

À chaque requête HTTP, le filtre global `ContexteDroits` fait passer la connexion sous le rôle PostgreSQL **`gestion_app`** et transmet l'utilisateur connecté à la base. Des politiques de sécurité au niveau des lignes (*Row Level Security*) sur `entite`, `site` et `emplacement` ne laissent alors lire ou écrire que les lignes du périmètre de ses affectations actives : une requête PHP qui oublierait un filtre ne peut rien exposer. Le rôle `gestion_app` n'a pas non plus le droit de supprimer dans le référentiel ni de modifier le journal.

Le propriétaire des tables (migrations, commandes `spark`) n'est pas soumis au cloisonnement. **En production**, l'application doit se connecter directement avec un compte de connexion qui a uniquement le rôle `gestion_app`, et les migrations doivent être lancées avec un autre compte (réglage `droits.roleApplication` dans `app/Config/Droits.php`).

### Journal d'audit (ADM-03)

Toute modification des tables protégées est enregistrée par un déclencheur PostgreSQL (auteur, avant / après), ainsi que les connexions, déconnexions et échecs de connexion. Le journal est en insertion seule, y compris pour le propriétaire des tables. Consultation : Administration › Journal d'audit (permission `audit.consulter`, portée nationale).

### Sessions

Expiration après 30 minutes d'inactivité (`session.expiration` dans `.env`) ; « se souvenir de moi » est désactivé car il contournerait cette expiration.

### Double facteur pour les rôles sensibles (section 4.3)

Un rôle peut être déclaré **sensible** (case « connexion confirmée par un code » dans *Administration › Rôles*). Quiconque détient un rôle sensible, par affectation ou par délégation, confirme sa connexion par un code à six chiffres envoyé par courriel. Par défaut : administrateur système, administrateur fonctionnel, directeur DCMA, auditeur, armurier.

L'envoi des courriels se configure dans `.env` :

```ini
email.fromEmail = 'gestion-materiel@exemple.local'
email.fromName  = 'Gestion du matériel'
email.protocol  = 'smtp'
email.SMTPHost  = 'smtp.exemple.local'
email.SMTPPort  = 587
email.SMTPUser  = '...'
email.SMTPPass  = '...'
email.SMTPCrypto = 'tls'
```

**Poste de développement** : tant que `email.SMTPHost` est vide (et hors production), aucun courriel n'est envoyé. Le code est écrit dans le journal de l'application :

```bash
grep "Double facteur" writable/logs/log-$(date +%F).log | tail -1
```

En production, sans serveur SMTP, la connexion des rôles sensibles échoue : le code n'est jamais affiché.

### Matériel sensible (section 4.2-2)

Mouvementer un article d'armement, de munitions ou de matériel sensible exige, en plus de `stocks.mouvementer`, la permission **`stocks.sensibles`** sur l'entité responsable. La base l'impose par une politique de sécurité. Le rôle par défaut **ARMURIER** (gestionnaire de matériel sensible) la détient ; le gestionnaire de dépôt ne la détient pas. Les régularisations d'inventaire en sont exemptées : elles relèvent de `inventaires.valider` et de la séparation des tâches (RG-32).

### Journal des consultations (section 5)

L'affichage d'une fiche de matériel sérialisé, d'un mouvement ou d'un bon PDF qui porte sur du matériel sensible est inscrit au journal d'audit (action `CONSULTATION`), avec l'utilisateur, l'objet et l'adresse IP.

### Mise en route

```bash
php spark db:seed DroitsSeeder      # les profils de la section 4.1 et l'armurier ; relancé, complète leurs permissions par défaut
php spark droits:affecter <identifiant> ADMIN_SYSTEME DCMA       # premier administrateur
php spark droits:affecter <identifiant> ADMIN_FONCTIONNEL DCMA   # gestion de l'organisation
```

Les identifiants Shield n'acceptent que des lettres, des chiffres et des points.

## Utilisateurs (CodeIgniter Shield)

L'authentification repose sur [CodeIgniter Shield](https://shield.codeigniter.com/), le module officiel. Ses tables (`users`, `auth_identities`, etc.) sont créées par `php spark migrate --all` : l'option `--all` est indispensable, car les migrations de Shield ne sont pas dans le dossier `app/`.

- Lecture des articles : publique.
- Création, modification, suppression : réservées aux utilisateurs connectés (filtre `session` dans `app/Config/Routes.php`). Un visiteur est redirigé vers la page de connexion.
- Pages fournies par Shield : `/register` (inscription), `/login` (connexion), `/logout` (déconnexion). Elles sont en français (`defaultLocale = 'fr'` dans `app/Config/App.php`).
- Configuration : `app/Config/Auth.php` (redirections, règles de mot de passe, activation par e-mail désactivée par défaut).
- Les pages de connexion et d'inscription utilisent la mise en page de l'application : vues `app/Views/auth/login.php` et `app/Views/auth/register.php`, déclarées dans `$views` de `app/Config/Auth.php`.
- La connexion par lien magique (« mot de passe oublié ») est désactivée (`$allowMagicLinkLogins = false`), car elle envoie un e-mail et aucun envoi n'est configuré. Pour la réactiver, configurer d'abord `app/Config/Email.php`.

Créer un utilisateur en ligne de commande : `php spark shield:user create`.

### Auteur des articles et droits

Chaque article enregistre son auteur (colonne `articles.user_id`, clé étrangère vers `users`). L'auteur est affiché dans la liste et sur la page de l'article.

Un article ne peut être modifié ou supprimé que par son auteur, ou par un membre du groupe `admin` ou `superadmin`. La règle est définie dans `app/Helpers/article_helper.php` (`peut_modifier_article()`). Les articles créés avant l'ajout de cette colonne n'ont pas d'auteur (« auteur inconnu ») : seuls les administrateurs peuvent les modifier.

Si un utilisateur est supprimé, ses articles restent en ligne sans auteur (`ON DELETE SET NULL`).

Donner les droits d'administration à un utilisateur :

```bash
php spark shield:user addgroup -n nom_utilisateur -g admin
```

L'API `POST /api/articles` exige un jeton d'accès Shield, transmis dans l'en-tête `Authorization: Bearer <jeton>`. Un jeton se génère en PHP pour un utilisateur donné : `$user->generateAccessToken('nom')->raw_token` (le jeton brut n'est visible qu'au moment de sa création).
Changer le mot de passe `ci_password` avant toute utilisation hors développement.

## Installation sous Ubuntu

Le projet exige PHP 8.2 ou plus récent (Ubuntu 24.04 fournit PHP 8.3). Sur Ubuntu 22.04, la version par défaut est PHP 8.1 : il faut alors ajouter le dépôt PPA `ondrej/php`.

1. Installer les paquets :

```bash
sudo apt update
sudo apt install git composer postgresql php-cli php-pgsql php-intl php-mbstring php-xml php-curl unzip
php -v
```

2. Récupérer le projet et ses dépendances :

```bash
git clone https://github.com/christyleblack/claude
cd claude/codeigniter-app
composer install
cp env .env
```

3. Créer l'utilisateur et les bases PostgreSQL, puis renseigner `.env` (voir la section « Base de données PostgreSQL » ci-dessus).

4. Lancer les migrations et le serveur de développement :

```bash
php spark migrate --all
php spark serve
```

L'application est accessible sur http://localhost:8080.

Si l'application est consultée par une autre adresse que `localhost:8080` (aperçu dans un navigateur distant, mandataire), activer dans `.env` le réglage de développement `app.baseURLDepuisRequete = true` : les redirections reprennent alors l'adresse de la requête. Il est ignoré hors du mode `development`.

Mettre à jour le code : `git pull`. Envoyer ses modifications : `git push`.

## Tests automatisés

```bash
composer test
```

Les tests utilisent la base PostgreSQL `gestion_materiel_tests` (groupe `tests` dans `app/Config/Database.php`, identifiants dans `.env`), recréée pour chaque test. Ils ne touchent jamais à la base de développement. Sur GitHub Actions, un serveur PostgreSQL 16 est démarré pour l'occasion.

- `tests/feature/ArticlesTest.php` : pages HTML (lecture publique, connexion obligatoire pour écrire, protection CSRF, validation, droits de l'auteur et des administrateurs).
- `tests/feature/Organisation/ArbresTest.php` : garanties de la base (chemins des deux arbres, propagation des déplacements, refus des cycles, force déduite, emplacements).
- `tests/feature/Organisation/PagesTest.php` : pages du module Organisation (consultation, permission `organisation.gerer`, création, refus des cycles et des doublons, forces, sites, emplacements).
- `tests/feature/Referentiel/ReglesTest.php` : règles RG-05 à RG-09 garanties par la base.
- `tests/feature/Referentiel/PagesTest.php` : consultation, gestion par filière (interface et base), fiches propres aux forces, affectation limitée à une filière.
- `tests/feature/Stocks/MouvementsTest.php` : règles du journal et du stock (stock calculé et jamais négatif, série unique, lots, annulation, immuabilité, détection d'une falsification, réforme).
- `tests/feature/Stocks/BonsTest.php` : bons (émission, numérotation continue, une saisie refusée sans numéro consommé, immuabilité, PDF, cloisonnement).
- `tests/feature/Stocks/PagesTest.php` : consultation et saisie, cloisonnement par l'interface et en base, annulation, fiche et état d'un matériel.
- `tests/feature/Droits/CloisonnementTest.php` : critère de recette 7.3-2, prouvé par l'interface et directement en base (lecture, écriture, suppression, affectations terminées, à venir ou révoquées, délégations, journal immuable).
- `tests/feature/Droits/AdministrationTest.php` : affectations, révocation, paramétrage des rôles, journal des connexions, délégations.
- `tests/feature/AuthPagesTest.php` : pages de connexion et d'inscription (mise en page, inscription, mauvais mot de passe).
- `tests/feature/ApiArticlesTest.php` : API JSON (lecture publique, jeton d'accès obligatoire pour créer, auteur enregistré, validation).

Afficher chaque test avec son nom : `vendor/bin/phpunit --no-coverage --testdox`.

Les tests sont aussi lancés automatiquement par GitHub Actions à chaque push sur `main` et à chaque pull request (`.github/workflows/tests.yml`, onglet « Actions » du dépôt).

