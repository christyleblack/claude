<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

/*
 * Journal d'audit des connexions (cahier des charges, section 5 « Traçabilité »).
 * Les modifications de données sont journalisées par la base elle-même (déclencheurs).
 */
$journaliser = static function (string $action, ?int $utilisateurId, array $details = []): void {
    db_connect()->table('journal_audit')->insert([
        'utilisateur_id' => $utilisateurId,
        'action'         => $action,
        'objet_type'     => 'session',
        'details'        => $details === [] ? null : json_encode($details, JSON_UNESCAPED_UNICODE),
        'adresse_ip'     => service('request')->getIPAddress(),
    ]);
};
Events::on('login', static fn ($user) => $journaliser('CONNEXION', (int) $user->id));
Events::on('logout', static fn ($user) => $journaliser('DECONNEXION', (int) $user->id));
Events::on('failedLogin', static fn (array $identifiants) => $journaliser('ECHEC_CONNEXION', null, ['email' => $identifiants['email'] ?? null]));

