<!doctype html>
<html lang="fr">
<head><meta charset="utf-8"><title>Code de connexion</title></head>
<body>
<p>Bonjour <?= esc($user->username ?? '') ?>,</p>
<p>Votre code de connexion à l'application de gestion du matériel est :</p>
<p style="font-size: 1.6em; font-weight: bold; letter-spacing: 0.2em"><?= esc($code) ?></p>
<p>Demande faite le <?= esc($date) ?> depuis l'adresse <?= esc($ipAddress) ?> (<?= esc($userAgent) ?>).</p>
<p>Si vous n'êtes pas à l'origine de cette connexion, ne communiquez ce code à personne et prévenez l'administrateur.</p>
</body>
</html>
