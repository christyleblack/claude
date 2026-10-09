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
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) if you plan to use MySQL
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library

## Base de données MySQL

1. Créer la base et l'utilisateur :

```sql
CREATE DATABASE ci_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ci_user'@'localhost' IDENTIFIED BY 'ci_password';
GRANT ALL ON ci_app.* TO 'ci_user'@'localhost';
```

2. Copier `env` en `.env` et renseigner (le fichier `.env` n'est pas versionné) :

```
CI_ENVIRONMENT = development
database.default.hostname = localhost
database.default.database = ci_app
database.default.username = ci_user
database.default.password = ci_password
database.default.DBDriver = MySQLi
database.default.port = 3306
```

3. Lancer les migrations puis le serveur :

```bash
php spark migrate --all
php spark serve
```

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

## Utilisateurs (CodeIgniter Shield)

L'authentification repose sur [CodeIgniter Shield](https://shield.codeigniter.com/), le module officiel. Ses tables (`users`, `auth_identities`, etc.) sont créées par `php spark migrate --all` : l'option `--all` est indispensable, car les migrations de Shield ne sont pas dans le dossier `app/`.

- Lecture des articles : publique.
- Création, modification, suppression : réservées aux utilisateurs connectés (filtre `session` dans `app/Config/Routes.php`). Un visiteur est redirigé vers la page de connexion.
- Pages fournies par Shield : `/register` (inscription), `/login` (connexion), `/logout` (déconnexion). Elles sont en français (`defaultLocale = 'fr'` dans `app/Config/App.php`).
- Configuration : `app/Config/Auth.php` (redirections, règles de mot de passe, activation par e-mail désactivée par défaut).

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
sudo apt install git composer mysql-server php-cli php-mysql php-sqlite3 php-intl php-mbstring php-xml php-curl unzip
php -v
```

2. Récupérer le projet et ses dépendances :

```bash
git clone https://github.com/christyleblack/claude
cd claude/codeigniter-app
composer install
cp env .env
```

3. Créer la base (voir la section « Base de données MySQL » ci-dessus) avec `sudo mysql`, puis renseigner `.env`.

4. Lancer les migrations et le serveur de développement :

```bash
php spark migrate --all
php spark serve
```

L'application est accessible sur http://localhost:8080 et `http://localhost:8080/articles` renvoie la liste des articles au format JSON.

Mettre à jour le code : `git pull`. Envoyer ses modifications : `git push`.

## Tests automatisés

```bash
composer test
```

Les tests utilisent une base SQLite en mémoire, recréée pour chaque test (configuration `tests` dans `app/Config/Database.php`). Ils ne nécessitent pas MySQL et ne touchent jamais à la base de développement. L'extension PHP `sqlite3` est requise (`sudo apt install php-sqlite3`).

- `tests/feature/ArticlesTest.php` : pages HTML (lecture publique, connexion obligatoire pour écrire, protection CSRF, validation, droits de l'auteur et des administrateurs).
- `tests/feature/ApiArticlesTest.php` : API JSON (lecture publique, jeton d'accès obligatoire pour créer, auteur enregistré, validation).

Afficher chaque test avec son nom : `vendor/bin/phpunit --no-coverage --testdox`.

Les tests sont aussi lancés automatiquement par GitHub Actions à chaque push sur `main` et à chaque pull request (`.github/workflows/tests.yml`, onglet « Actions » du dépôt).

