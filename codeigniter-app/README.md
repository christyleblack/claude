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
php spark migrate
php spark serve
```

Exemple fourni : table `articles` (`app/Database/Migrations`), `ArticleModel`, contrôleur `Articles`.
Routes : `GET /articles` (liste) et `POST /articles` (champs `titre`, `contenu`).
Changer le mot de passe `ci_password` avant toute utilisation hors développement.

## Installation sous Ubuntu

Le projet exige PHP 8.2 ou plus récent (Ubuntu 24.04 fournit PHP 8.3). Sur Ubuntu 22.04, la version par défaut est PHP 8.1 : il faut alors ajouter le dépôt PPA `ondrej/php`.

1. Installer les paquets :

```bash
sudo apt update
sudo apt install git composer mysql-server php-cli php-mysql php-intl php-mbstring php-xml php-curl unzip
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
php spark migrate
php spark serve
```

L'application est accessible sur http://localhost:8080 et `http://localhost:8080/articles` renvoie la liste des articles au format JSON.

Mettre à jour le code : `git pull`. Envoyer ses modifications : `git push`.
