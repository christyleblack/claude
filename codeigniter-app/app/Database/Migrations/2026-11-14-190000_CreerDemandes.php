<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Module Demandes et validations (lot 2, DEM-01 à DEM-04, RG-15 à RG-19, ADM-02).
 *
 * - Une demande est émise par une entité, porte sur une seule filière (RG-16) et contient
 *   des lignes d'un seul article chacune (RG-15).
 * - Routage (DEM-02) : à la soumission, le circuit de la filière (ou le circuit par défaut)
 *   est instancié en étapes concrètes, chacune confiée à une entité : l'échelon de la
 *   chaîne logistique du demandeur, le service gestionnaire de la filière ou une entité
 *   fixée. Une étape peut ne s'appliquer qu'au-delà d'un seuil de quantité (DEM-03).
 *   L'entité de service est le parent logistique du demandeur (à défaut, le service
 *   gestionnaire de la filière).
 * - Validation : à chaque étape, un détenteur de demandes.valider sur l'entité de l'étape
 *   (une affectation de portée nationale couvre toutes les étapes), qui n'est pas le
 *   demandeur (RG-19, règle 4.2-4). Les délégations s'appliquent.
 * - Service (RG-17) : chaque perception depuis l'entité de service vers l'entité demandeuse
 *   est rattachée à une ligne ; la demande est servie quand toutes ses lignes le sont,
 *   sinon elle reste en service jusqu'à sa clôture. L'annulation d'une perception
 *   rattachée réduit la quantité servie.
 * - États (RG-18) : brouillon, soumise, en_validation, validee, refusee, en_service,
 *   servie, cloturee, annulee ; annulation possible tant que rien n'a été servi.
 * - Suivi (DEM-04) : historique de chaque demande et notifications aux acteurs concernés.
 *
 * Toutes les transitions sont des fonctions de la base : l'application ne peut ni changer
 * un statut, ni écrire une étape, un service ou une notification directement.
 */
class CreerDemandes extends Migration
{
    private const FONCTIONS = [
        'entite_libelle(integer)', 'demande_emplacements_livraison(integer)', 'demande_preparer()', 'ligne_demande_verifier()', 'demande_visible(integer)', 'demande_service_visible(integer)', 'demande_modifiable(integer)',
        'demande_droit(integer, varchar, integer, integer)', 'demande_detenteurs(varchar, integer, integer)',
        'demande_notifier(integer, integer[], text)', 'demande_evenement(integer, varchar, text)',
        'demande_soumettre(integer)', 'demande_decider(integer, boolean, text)', 'demande_servir(integer, bigint)',
        'demande_service_annule()', 'demande_cloturer(integer, text)', 'demande_annuler(integer, text)',
    ];

    public function up()
    {
        $this->db->transException(true)->transStart();

        $this->db->query(<<<'SQL'
            INSERT INTO permission (code, libelle) VALUES
                ('demandes.saisir', 'Saisir, soumettre, clôturer et annuler les demandes de son entité'),
                ('demandes.valider', 'Valider ou refuser une étape de demande'),
                ('demandes.circuits', 'Paramétrer les circuits de validation des demandes')
            SQL);

        // Circuits de validation paramétrables (DEM-03, ADM-02)
        $this->db->query(<<<'SQL'
            CREATE TABLE circuit_validation (
                id         serial PRIMARY KEY,
                libelle    varchar(150) NOT NULL,
                filiere_id integer REFERENCES filiere (id),
                actif      boolean NOT NULL DEFAULT true,
                created_at timestamp,
                updated_at timestamp,
                -- un circuit par filière et un seul circuit par défaut (sans filière)
                CONSTRAINT circuit_unique_par_filiere UNIQUE NULLS NOT DISTINCT (filiere_id)
            )
            SQL);
        $this->db->query(<<<'SQL'
            CREATE TABLE circuit_etape (
                id             serial PRIMARY KEY,
                circuit_id     integer NOT NULL REFERENCES circuit_validation (id) ON DELETE CASCADE,
                ordre          smallint NOT NULL CHECK (ordre > 0),
                libelle        varchar(150) NOT NULL,
                mode           varchar(12) NOT NULL CHECK (mode IN ('logistique', 'filiere', 'entite')),
                niveau         smallint CHECK (niveau > 0),
                entite_id      integer REFERENCES entite (id),
                seuil_quantite numeric(14, 3) CHECK (seuil_quantite > 0),
                created_at     timestamp,
                updated_at     timestamp,
                CONSTRAINT etape_ordre_unique UNIQUE (circuit_id, ordre),
                CONSTRAINT parametres_du_mode CHECK (
                    (mode = 'logistique') = (niveau IS NOT NULL) AND (mode = 'entite') = (entite_id IS NOT NULL)
                )
            )
            SQL);

        $this->db->query('CREATE TABLE demande_compteur (annee integer PRIMARY KEY, dernier integer NOT NULL)');

        $this->db->query(<<<'SQL'
            CREATE TABLE demande (
                id                serial PRIMARY KEY,
                numero            varchar(20) NOT NULL UNIQUE,
                entite_id         integer NOT NULL REFERENCES entite (id),
                filiere_id        integer NOT NULL REFERENCES filiere (id),
                entite_service_id integer REFERENCES entite (id),
                demandeur_id      integer NOT NULL REFERENCES users (id),
                objet             varchar(200) NOT NULL,
                date_besoin       date,
                priorite          varchar(8) NOT NULL DEFAULT 'normale' CHECK (priorite IN ('normale', 'urgente')),
                statut            varchar(14) NOT NULL DEFAULT 'brouillon' CHECK (statut IN (
                                      'brouillon', 'soumise', 'en_validation', 'validee', 'refusee',
                                      'en_service', 'servie', 'cloturee', 'annulee')),
                circuit_id        integer REFERENCES circuit_validation (id),
                soumise_le        timestamptz,
                validee_le        timestamptz,
                terminee_le       timestamptz,
                created_at        timestamp,
                updated_at        timestamp
            )
            SQL);
        $this->db->query('CREATE INDEX demande_statut_idx ON demande (statut)');

        $this->db->query(<<<'SQL'
            CREATE TABLE ligne_demande (
                id              serial PRIMARY KEY,
                demande_id      integer NOT NULL REFERENCES demande (id),
                article_id      integer NOT NULL REFERENCES article (id),
                quantite        numeric(14, 3) NOT NULL CHECK (quantite > 0),
                quantite_servie numeric(14, 3) NOT NULL DEFAULT 0 CHECK (quantite_servie >= 0),
                observation     varchar(200),
                CONSTRAINT ligne_article_unique UNIQUE (demande_id, article_id),
                CONSTRAINT service_dans_la_limite CHECK (quantite_servie <= quantite)
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE etape_demande (
                id          serial PRIMARY KEY,
                demande_id  integer NOT NULL REFERENCES demande (id),
                ordre       smallint NOT NULL,
                libelle     varchar(150) NOT NULL,
                entite_id   integer NOT NULL REFERENCES entite (id),
                statut      varchar(10) NOT NULL DEFAULT 'en_attente' CHECK (statut IN ('en_attente', 'validee', 'refusee')),
                decideur_id integer REFERENCES users (id),
                decision_le timestamptz,
                commentaire text,
                CONSTRAINT etape_demande_unique UNIQUE (demande_id, ordre)
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE service_demande (
                mouvement_id     bigint PRIMARY KEY REFERENCES mouvement (id),
                ligne_demande_id integer NOT NULL REFERENCES ligne_demande (id),
                quantite         numeric(14, 3) NOT NULL,
                servi_par        integer REFERENCES users (id),
                servi_le         timestamptz NOT NULL DEFAULT now(),
                annulation_id    bigint REFERENCES mouvement (id)
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE evenement_demande (
                id          bigserial PRIMARY KEY,
                demande_id  integer NOT NULL REFERENCES demande (id),
                horodatage  timestamptz NOT NULL DEFAULT now(),
                auteur_id   integer REFERENCES users (id),
                action      varchar(30) NOT NULL,
                commentaire text
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE notification (
                id             bigserial PRIMARY KEY,
                utilisateur_id integer NOT NULL REFERENCES users (id) ON DELETE CASCADE,
                demande_id     integer REFERENCES demande (id),
                message        varchar(300) NOT NULL,
                cree_le        timestamptz NOT NULL DEFAULT now(),
                lue_le         timestamptz
            )
            SQL);
        $this->db->query('CREATE INDEX notification_non_lue_idx ON notification (utilisateur_id) WHERE lue_le IS NULL');

        // Numérotation continue par année, demandeur et statut imposés à la création
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_preparer() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                an integer := extract(year FROM now())::integer;
                rang integer;
            BEGIN
                INSERT INTO demande_compteur (annee, dernier) VALUES (an, 1)
                ON CONFLICT (annee) DO UPDATE SET dernier = demande_compteur.dernier + 1
                RETURNING dernier INTO rang;

                NEW.numero            := 'DEM-' || an || '-' || lpad(rang::text, 6, '0');
                NEW.demandeur_id      := COALESCE(droits_utilisateur(), NEW.demandeur_id);
                NEW.statut            := 'brouillon';
                NEW.entite_service_id := NULL;
                NEW.circuit_id        := NULL;
                NEW.soumise_le        := NULL;
                NEW.validee_le        := NULL;
                NEW.terminee_le       := NULL;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER demande_preparer BEFORE INSERT ON demande FOR EACH ROW EXECUTE FUNCTION demande_preparer()');

        // RG-16 : une seule filière ; lignes modifiables seulement en brouillon
        $this->db->query(<<<'SQL'
            CREATE FUNCTION ligne_demande_verifier() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                d demande;
                ligne ligne_demande := CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            BEGIN
                SELECT * INTO d FROM demande WHERE id = ligne.demande_id;

                IF TG_OP = 'UPDATE' AND NEW.quantite = OLD.quantite AND NEW.article_id = OLD.article_id
                   AND NEW.demande_id = OLD.demande_id THEN
                    RETURN NEW;  -- quantité servie ou observation : géré par les fonctions de service
                END IF;
                IF d.statut <> 'brouillon' THEN
                    RAISE EXCEPTION 'Demande refusée : les lignes ne se modifient qu''en brouillon' USING ERRCODE = 'check_violation';
                END IF;
                IF TG_OP <> 'DELETE' AND (SELECT filiere_id FROM article WHERE id = NEW.article_id) <> d.filiere_id THEN
                    RAISE EXCEPTION 'Demande refusée : l''article n''appartient pas à la filière de la demande (RG-16)' USING ERRCODE = 'check_violation';
                END IF;
                RETURN ligne;
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER ligne_demande_verifier BEFORE INSERT OR UPDATE OR DELETE ON ligne_demande FOR EACH ROW EXECUTE FUNCTION ligne_demande_verifier()');

        // Visibilité : périmètre de l'entité demandeuse, de l'entité de service ou d'une étape
        // Une demande circule hors du périmètre de l'entité demandeuse (valideurs, entité de
        // service) : son libellé reste lisible, sans ouvrir le reste de l'organisation
        $this->db->query(<<<'SQL'
            CREATE FUNCTION entite_libelle(id_entite integer) RETURNS varchar
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT code || ' – ' || nom FROM entite WHERE id = id_entite
            $$
            SQL);
        // Entité de service vue d'en dessous : le dépôt qui sert la demande dépend de l'entité de service
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_service_visible(id_entite integer) RETURNS boolean
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT id_entite IS NOT NULL AND (
                    droits_entite_visible(id_entite)
                    OR EXISTS (
                        SELECT 1 FROM droits_affectations_actives(droits_utilisateur()) a
                        WHERE a.chemin <@ (SELECT chemin_hierarchique FROM entite WHERE id = id_entite)
                    )
                )
            $$
            SQL);
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_visible(id_demande integer) RETURNS boolean
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT EXISTS (
                    SELECT 1 FROM demande d
                    WHERE d.id = id_demande AND (
                        d.demandeur_id = droits_utilisateur()
                        OR droits_entite_visible(d.entite_id)
                        OR demande_service_visible(d.entite_service_id)
                        OR EXISTS (SELECT 1 FROM etape_demande e WHERE e.demande_id = d.id AND droits_entite_visible(e.entite_id))
                    )
                )
            $$
            SQL);
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_modifiable(id_demande integer) RETURNS boolean
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT EXISTS (
                    SELECT 1 FROM demande d
                    WHERE d.id = id_demande AND d.statut = 'brouillon' AND droits_peut('demandes.saisir', d.entite_id)
                )
            $$
            SQL);

        // Un utilisateur détient-il une permission sur une entité, en respectant la restriction de filière ?
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_droit(uid integer, code_permission varchar, id_entite integer, id_filiere integer) RETURNS boolean
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT EXISTS (
                    SELECT 1
                    FROM droits_affectations_actives(uid) a
                    JOIN affectation af ON af.id = a.affectation_id
                    JOIN role_permission rp ON rp.role_id = a.role_id AND rp.permission = code_permission
                    WHERE (af.filiere_id IS NULL OR af.filiere_id = id_filiere)
                      AND (a.portee = 'nationale' OR (SELECT chemin_hierarchique FROM entite WHERE id = id_entite) <@ a.chemin)
                )
            $$
            SQL);
        // Détenteurs d'une permission sur une entité ou l'une de ses subordonnées (destinataires des notifications)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_detenteurs(code_permission varchar, id_entite integer, id_filiere integer) RETURNS integer[]
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT coalesce(array_agg(DISTINCT u.id), '{}')
                FROM users u
                CROSS JOIN LATERAL droits_affectations_actives(u.id) a
                JOIN affectation af ON af.id = a.affectation_id
                JOIN role_permission rp ON rp.role_id = a.role_id AND rp.permission = code_permission
                WHERE u.active = 1 AND u.deleted_at IS NULL
                  AND (af.filiere_id IS NULL OR af.filiere_id = id_filiere)
                  AND (a.portee = 'nationale'
                       OR (SELECT chemin_hierarchique FROM entite WHERE id = id_entite) <@ a.chemin
                       OR a.chemin <@ (SELECT chemin_hierarchique FROM entite WHERE id = id_entite))
            $$
            SQL);

        // Emplacements de livraison (entité demandeuse et subordonnées), pour qui peut servir la demande
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_emplacements_livraison(id_demande integer) RETURNS TABLE (id integer, libelle text)
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT em.id, ent.nom || ' › ' || em.code || ' – ' || em.libelle
                FROM demande d
                JOIN entite dem ON dem.id = d.entite_id
                JOIN entite ent ON ent.chemin_hierarchique <@ dem.chemin_hierarchique
                JOIN site si ON si.entite_id = ent.id AND si.actif
                JOIN emplacement em ON em.site_id = si.id AND em.actif
                WHERE d.id = id_demande
                  AND droits_utilisateur() = ANY (demande_detenteurs('stocks.mouvementer', d.entite_service_id, d.filiere_id))
                ORDER BY ent.nom, em.code
            $$
            SQL);
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_notifier(id_demande integer, destinataires integer[], texte text) RETURNS void
            LANGUAGE sql SECURITY DEFINER SET search_path = public AS $$
                INSERT INTO notification (utilisateur_id, demande_id, message)
                SELECT DISTINCT u, id_demande, left(texte, 300)
                FROM unnest(destinataires) AS u
                WHERE u IS NOT NULL AND u IS DISTINCT FROM droits_utilisateur()
            $$
            SQL);
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_evenement(id_demande integer, quoi varchar, texte text) RETURNS void
            LANGUAGE sql SECURITY DEFINER SET search_path = public AS $$
                INSERT INTO evenement_demande (demande_id, auteur_id, action, commentaire)
                VALUES (id_demande, droits_utilisateur(), quoi, nullif(trim(texte), ''))
            $$
            SQL);

        // Soumission : routage et instanciation du circuit (DEM-02, DEM-03)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_soumettre(id_demande integer) RETURNS varchar
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                d demande;
                circuit integer;
                chemin ltree;
                etape circuit_etape;
                cible integer;
                rang smallint := 0;
                service integer;
                gestionnaire integer;
            BEGIN
                SELECT * INTO d FROM demande WHERE id = id_demande FOR UPDATE;
                IF NOT FOUND OR NOT droits_peut('demandes.saisir', d.entite_id) THEN
                    RAISE EXCEPTION 'Demande refusée : vous ne pouvez pas soumettre cette demande' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF d.statut <> 'brouillon' THEN
                    RAISE EXCEPTION 'Demande refusée : seule une demande en brouillon se soumet' USING ERRCODE = 'check_violation';
                END IF;
                IF NOT EXISTS (SELECT 1 FROM ligne_demande WHERE demande_id = d.id) THEN
                    RAISE EXCEPTION 'Demande refusée : ajoutez au moins une ligne' USING ERRCODE = 'check_violation';
                END IF;

                SELECT chemin_logistique INTO chemin FROM entite WHERE id = d.entite_id;
                SELECT service_gestionnaire_id INTO gestionnaire FROM filiere WHERE id = d.filiere_id;

                -- Circuit de la filière, sinon circuit par défaut
                SELECT id INTO circuit FROM circuit_validation
                WHERE actif AND (filiere_id = d.filiere_id OR filiere_id IS NULL)
                ORDER BY filiere_id NULLS LAST LIMIT 1;

                FOR etape IN SELECT * FROM circuit_etape WHERE circuit_id = circuit ORDER BY ordre LOOP
                    -- Étape soumise à un seuil : seulement si une ligne l'atteint
                    CONTINUE WHEN etape.seuil_quantite IS NOT NULL
                        AND NOT EXISTS (SELECT 1 FROM ligne_demande WHERE demande_id = d.id AND quantite >= etape.seuil_quantite);

                    cible := CASE etape.mode
                        WHEN 'logistique' THEN CASE WHEN nlevel(chemin) > etape.niveau
                                                    THEN subpath(chemin, -etape.niveau - 1, 1)::text::integer END
                        WHEN 'filiere' THEN gestionnaire
                        ELSE etape.entite_id
                    END;
                    -- Échelon absent (entité sans parent logistique à ce niveau) : étape sans objet
                    CONTINUE WHEN cible IS NULL;

                    rang := rang + 1;
                    INSERT INTO etape_demande (demande_id, ordre, libelle, entite_id) VALUES (d.id, rang, etape.libelle, cible);
                END LOOP;

                -- Entité de service : le ravitailleur (parent logistique), à défaut le service de la filière
                service := COALESCE(CASE WHEN nlevel(chemin) > 1 THEN subpath(chemin, -2, 1)::text::integer END, gestionnaire);

                UPDATE demande SET
                    statut = CASE WHEN rang > 0 THEN 'soumise' ELSE 'validee' END,
                    entite_service_id = service, circuit_id = circuit, soumise_le = now(),
                    validee_le = CASE WHEN rang > 0 THEN NULL ELSE now() END, updated_at = now()
                WHERE id = d.id;

                PERFORM demande_evenement(d.id, 'soumission', NULL);
                IF rang > 0 THEN
                    SELECT entite_id INTO cible FROM etape_demande WHERE demande_id = d.id AND ordre = 1;
                    PERFORM demande_notifier(d.id,
                        array_remove(demande_detenteurs('demandes.valider', cible, d.filiere_id), d.demandeur_id),
                        'Demande ' || d.numero || ' à valider : ' || d.objet);
                    RETURN 'soumise';
                END IF;

                PERFORM demande_notifier(d.id, demande_detenteurs('stocks.mouvementer', service, d.filiere_id),
                    'Demande ' || d.numero || ' validée, à servir : ' || d.objet);
                RETURN 'validee';
            END;
            $$
            SQL);

        // Décision sur l'étape en cours (RG-19, règle 4.2-4)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_decider(id_demande integer, valider boolean, texte text) RETURNS varchar
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                d demande;
                e etape_demande;
                suivante etape_demande;
                uid integer := droits_utilisateur();
            BEGIN
                SELECT * INTO d FROM demande WHERE id = id_demande FOR UPDATE;
                IF NOT FOUND OR d.statut NOT IN ('soumise', 'en_validation') THEN
                    RAISE EXCEPTION 'Demande refusée : aucune validation en attente' USING ERRCODE = 'check_violation';
                END IF;
                SELECT * INTO e FROM etape_demande WHERE demande_id = d.id AND statut = 'en_attente' ORDER BY ordre LIMIT 1;

                IF uid = d.demandeur_id THEN
                    RAISE EXCEPTION 'Demande refusée : le demandeur ne peut pas valider sa propre demande (RG-19)' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF uid IS NULL OR NOT demande_droit(uid, 'demandes.valider', e.entite_id, d.filiere_id) THEN
                    RAISE EXCEPTION 'Demande refusée : cette étape relève d''un valideur de l''entité %', (SELECT nom FROM entite WHERE id = e.entite_id)
                        USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF NOT valider AND nullif(trim(texte), '') IS NULL THEN
                    RAISE EXCEPTION 'Demande refusée : indiquez le motif du refus' USING ERRCODE = 'check_violation';
                END IF;

                UPDATE etape_demande SET statut = CASE WHEN valider THEN 'validee' ELSE 'refusee' END,
                    decideur_id = uid, decision_le = now(), commentaire = nullif(trim(texte), '')
                WHERE id = e.id;

                IF NOT valider THEN
                    UPDATE demande SET statut = 'refusee', terminee_le = now(), updated_at = now() WHERE id = d.id;
                    PERFORM demande_evenement(d.id, 'refus', e.libelle || ' : ' || texte);
                    PERFORM demande_notifier(d.id, ARRAY[d.demandeur_id], 'Demande ' || d.numero || ' refusée : ' || texte);
                    RETURN 'refusee';
                END IF;

                PERFORM demande_evenement(d.id, 'validation', e.libelle || coalesce(' : ' || nullif(trim(texte), ''), ''));
                SELECT * INTO suivante FROM etape_demande WHERE demande_id = d.id AND statut = 'en_attente' ORDER BY ordre LIMIT 1;

                IF FOUND THEN
                    UPDATE demande SET statut = 'en_validation', updated_at = now() WHERE id = d.id;
                    PERFORM demande_notifier(d.id,
                        array_remove(demande_detenteurs('demandes.valider', suivante.entite_id, d.filiere_id), d.demandeur_id),
                        'Demande ' || d.numero || ' à valider : ' || d.objet);
                    PERFORM demande_notifier(d.id, ARRAY[d.demandeur_id], 'Demande ' || d.numero || ' : ' || e.libelle || ' validée');
                    RETURN 'en_validation';
                END IF;

                UPDATE demande SET statut = 'validee', validee_le = now(), updated_at = now() WHERE id = d.id;
                PERFORM demande_notifier(d.id, ARRAY[d.demandeur_id], 'Demande ' || d.numero || ' validée');
                PERFORM demande_notifier(d.id, demande_detenteurs('stocks.mouvementer', d.entite_service_id, d.filiere_id),
                    'Demande ' || d.numero || ' validée, à servir : ' || d.objet);
                RETURN 'validee';
            END;
            $$
            SQL);

        // Rattachement d'une perception à une ligne (RG-17)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_servir(id_ligne integer, id_mouvement bigint) RETURNS varchar
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                l ligne_demande;
                d demande;
                m mouvement;
                nouveau varchar;
            BEGIN
                SELECT * INTO l FROM ligne_demande WHERE id = id_ligne FOR UPDATE;
                SELECT * INTO d FROM demande WHERE id = l.demande_id FOR UPDATE;
                SELECT * INTO m FROM mouvement WHERE id = id_mouvement;

                IF d.statut NOT IN ('validee', 'en_service') THEN
                    RAISE EXCEPTION 'Demande refusée : seule une demande validée se sert' USING ERRCODE = 'check_violation';
                END IF;
                IF m.id IS NULL OR m.auteur_id IS DISTINCT FROM droits_utilisateur() OR m.type <> 'perception'
                   OR m.article_id <> l.article_id OR EXISTS (SELECT 1 FROM service_demande WHERE mouvement_id = m.id) THEN
                    RAISE EXCEPTION 'Demande refusée : le service doit être une perception de l''article demandé, saisie par vous' USING ERRCODE = 'check_violation';
                END IF;
                IF NOT (SELECT chemin_hierarchique FROM entite WHERE id = emplacement_entite(m.emplacement_origine_id))
                       <@ (SELECT chemin_hierarchique FROM entite WHERE id = d.entite_service_id) THEN
                    RAISE EXCEPTION 'Demande refusée : le matériel doit provenir de l''entité de service ou de ses dépôts' USING ERRCODE = 'check_violation';
                END IF;
                IF NOT (SELECT chemin_hierarchique FROM entite WHERE id = emplacement_entite(m.emplacement_destination_id))
                       <@ (SELECT chemin_hierarchique FROM entite WHERE id = d.entite_id) THEN
                    RAISE EXCEPTION 'Demande refusée : le matériel doit être livré à l''entité demandeuse' USING ERRCODE = 'check_violation';
                END IF;
                IF l.quantite_servie + m.quantite > l.quantite THEN
                    RAISE EXCEPTION 'Demande refusée : quantité supérieure au reste à servir (%)', trim_scale(l.quantite - l.quantite_servie)
                        USING ERRCODE = 'check_violation';
                END IF;

                INSERT INTO service_demande (mouvement_id, ligne_demande_id, quantite, servi_par) VALUES (m.id, l.id, m.quantite, droits_utilisateur());
                UPDATE ligne_demande SET quantite_servie = quantite_servie + m.quantite WHERE id = l.id;

                nouveau := CASE WHEN EXISTS (SELECT 1 FROM ligne_demande WHERE demande_id = d.id AND quantite_servie < quantite)
                                THEN 'en_service' ELSE 'servie' END;
                UPDATE demande SET statut = nouveau, updated_at = now(),
                    terminee_le = CASE WHEN nouveau = 'servie' THEN now() END
                WHERE id = d.id;

                PERFORM demande_evenement(d.id, 'service',
                    trim_scale(m.quantite) || ' ' || (SELECT codification FROM article WHERE id = l.article_id) || ', mouvement n° ' || m.id);
                PERFORM demande_notifier(d.id, ARRAY[d.demandeur_id],
                    'Demande ' || d.numero || CASE WHEN nouveau = 'servie' THEN ' entièrement servie' ELSE ' partiellement servie' END);
                RETURN nouveau;
            END;
            $$
            SQL);

        // Annulation d'une perception rattachée : la quantité servie diminue
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_service_annule() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                s service_demande;
                id_demande integer;
            BEGIN
                SELECT * INTO s FROM service_demande WHERE mouvement_id = NEW.mouvement_annule_id AND annulation_id IS NULL;
                IF NOT FOUND THEN
                    RETURN NULL;
                END IF;

                UPDATE service_demande SET annulation_id = NEW.id WHERE mouvement_id = s.mouvement_id;
                UPDATE ligne_demande SET quantite_servie = quantite_servie - s.quantite WHERE id = s.ligne_demande_id
                RETURNING demande_id INTO id_demande;
                UPDATE demande SET statut = 'en_service', terminee_le = NULL, updated_at = now()
                WHERE id = id_demande AND statut = 'servie';
                PERFORM demande_evenement(id_demande, 'service_annule', 'mouvement n° ' || s.mouvement_id || ' annulé par le n° ' || NEW.id);
                RETURN NULL;
            END;
            $$
            SQL);
        $this->db->query("CREATE TRIGGER demande_service_annule AFTER INSERT ON mouvement FOR EACH ROW WHEN (NEW.type = 'annulation') EXECUTE FUNCTION demande_service_annule()");

        // Clôture d'une demande validée ou partiellement servie (RG-17)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_cloturer(id_demande integer, texte text) RETURNS varchar
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                d demande;
            BEGIN
                SELECT * INTO d FROM demande WHERE id = id_demande FOR UPDATE;
                IF NOT FOUND OR d.statut NOT IN ('validee', 'en_service') THEN
                    RAISE EXCEPTION 'Demande refusée : seule une demande validée ou en cours de service se clôture' USING ERRCODE = 'check_violation';
                END IF;
                IF NOT (droits_peut('demandes.saisir', d.entite_id)
                        OR droits_utilisateur() = ANY (demande_detenteurs('stocks.mouvementer', d.entite_service_id, d.filiere_id))) THEN
                    RAISE EXCEPTION 'Demande refusée : vous ne pouvez pas clôturer cette demande' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF nullif(trim(texte), '') IS NULL THEN
                    RAISE EXCEPTION 'Demande refusée : indiquez le motif de la clôture' USING ERRCODE = 'check_violation';
                END IF;

                UPDATE demande SET statut = 'cloturee', terminee_le = now(), updated_at = now() WHERE id = d.id;
                PERFORM demande_evenement(d.id, 'cloture', texte);
                PERFORM demande_notifier(d.id, ARRAY[d.demandeur_id], 'Demande ' || d.numero || ' clôturée : ' || texte);
                RETURN 'cloturee';
            END;
            $$
            SQL);

        // Annulation tant que rien n'a été servi (RG-18)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION demande_annuler(id_demande integer, texte text) RETURNS varchar
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                d demande;
            BEGIN
                SELECT * INTO d FROM demande WHERE id = id_demande FOR UPDATE;
                IF NOT FOUND OR NOT droits_peut('demandes.saisir', d.entite_id) THEN
                    RAISE EXCEPTION 'Demande refusée : vous ne pouvez pas annuler cette demande' USING ERRCODE = 'insufficient_privilege';
                END IF;
                IF d.statut NOT IN ('brouillon', 'soumise', 'en_validation', 'validee') THEN
                    RAISE EXCEPTION 'Demande refusée : une demande déjà servie, même en partie, se clôture au lieu de s''annuler' USING ERRCODE = 'check_violation';
                END IF;
                IF d.statut <> 'brouillon' AND nullif(trim(texte), '') IS NULL THEN
                    RAISE EXCEPTION 'Demande refusée : indiquez le motif de l''annulation' USING ERRCODE = 'check_violation';
                END IF;

                UPDATE demande SET statut = 'annulee', terminee_le = now(), updated_at = now() WHERE id = d.id;
                PERFORM demande_evenement(d.id, 'annulation', texte);
                PERFORM demande_notifier(d.id, ARRAY[d.demandeur_id], 'Demande ' || d.numero || ' annulée');
                RETURN 'annulee';
            END;
            $$
            SQL);

        // Droits de l'application : aucune écriture directe hors brouillon
        $this->db->query('REVOKE ALL ON demande, ligne_demande, etape_demande, service_demande, evenement_demande, notification, demande_compteur FROM gestion_app');
        $this->db->query('GRANT SELECT, INSERT ON demande TO gestion_app');
        $this->db->query('GRANT UPDATE (objet, date_besoin, priorite, updated_at) ON demande TO gestion_app');
        $this->db->query('GRANT SELECT, INSERT, DELETE ON ligne_demande TO gestion_app');
        $this->db->query('GRANT UPDATE (quantite, observation) ON ligne_demande TO gestion_app');
        $this->db->query('GRANT SELECT ON etape_demande, service_demande, evenement_demande TO gestion_app');
        $this->db->query('GRANT SELECT, UPDATE (lue_le) ON notification TO gestion_app');
        $this->db->query('GRANT USAGE ON SEQUENCE demande_id_seq, ligne_demande_id_seq TO gestion_app');

        foreach (['demande', 'ligne_demande', 'etape_demande', 'service_demande', 'evenement_demande', 'notification', 'circuit_validation', 'circuit_etape'] as $table) {
            $this->db->query("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        }
        // Évaluée sur les colonnes de la ligne (et non par relecture de la table) pour valoir aussi
        // sur une ligne en cours d'insertion (INSERT … RETURNING)
        $this->db->query(<<<'SQL'
            CREATE POLICY lecture ON demande FOR SELECT USING (
                demandeur_id = droits_utilisateur()
                OR droits_entite_visible(entite_id)
                OR demande_service_visible(entite_service_id)
                OR demande_visible(id)
            )
            SQL);
        $this->db->query("CREATE POLICY creation ON demande FOR INSERT WITH CHECK (droits_peut('demandes.saisir', entite_id))");
        $this->db->query('CREATE POLICY modification ON demande FOR UPDATE USING (demande_modifiable(id)) WITH CHECK (demande_modifiable(id))');
        $this->db->query('CREATE POLICY lecture ON ligne_demande FOR SELECT USING (demande_visible(demande_id))');
        $this->db->query('CREATE POLICY saisie ON ligne_demande FOR INSERT WITH CHECK (demande_modifiable(demande_id))');
        $this->db->query('CREATE POLICY modification ON ligne_demande FOR UPDATE USING (demande_modifiable(demande_id)) WITH CHECK (demande_modifiable(demande_id))');
        $this->db->query('CREATE POLICY suppression ON ligne_demande FOR DELETE USING (demande_modifiable(demande_id))');
        foreach (['etape_demande', 'service_demande', 'evenement_demande'] as $table) {
            $colonne = $table === 'service_demande' ? '(SELECT demande_id FROM ligne_demande WHERE id = ligne_demande_id)' : 'demande_id';
            $this->db->query("CREATE POLICY lecture ON {$table} FOR SELECT USING (demande_visible({$colonne}))");
        }
        $this->db->query('CREATE POLICY personnelle ON notification FOR ALL USING (utilisateur_id = droits_utilisateur())');
        foreach (['circuit_validation', 'circuit_etape'] as $table) {
            $this->db->query("CREATE POLICY lecture ON {$table} FOR SELECT USING (true)");
            $this->db->query("CREATE POLICY parametrage ON {$table} FOR ALL USING (droits_peut('demandes.circuits', NULL, true)) WITH CHECK (droits_peut('demandes.circuits', NULL, true))");
        }

        foreach (['demande', 'ligne_demande', 'etape_demande', 'service_demande', 'circuit_validation', 'circuit_etape'] as $table) {
            $this->db->query("CREATE TRIGGER audit AFTER INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION audit_journaliser()");
        }

        // Circuit par défaut : échelon logistique supérieur, puis service de la filière au-delà d'un seuil
        $this->db->query("INSERT INTO circuit_validation (libelle, created_at) VALUES ('Circuit par défaut', now())");
        $this->db->query(<<<'SQL'
            INSERT INTO circuit_etape (circuit_id, ordre, libelle, mode, niveau, seuil_quantite, created_at)
            SELECT id, 1, 'Validation par l''échelon logistique supérieur', 'logistique', 1, NULL, now() FROM circuit_validation
            UNION ALL
            SELECT id, 2, 'Validation par le service gestionnaire de la filière', 'filiere', NULL, 100, now() FROM circuit_validation
            SQL);

        $this->db->transComplete();
        $this->db->transException(false);
    }

    public function down()
    {
        $this->db->query('DROP TRIGGER IF EXISTS demande_service_annule ON mouvement');
        $this->db->query('DROP TABLE IF EXISTS notification, evenement_demande, service_demande, etape_demande, ligne_demande, demande, demande_compteur, circuit_etape, circuit_validation CASCADE');
        $this->db->query('DROP FUNCTION IF EXISTS ' . implode(', ', self::FONCTIONS));
        $this->db->query("DELETE FROM role_permission WHERE permission LIKE 'demandes.%'");
        $this->db->query("DELETE FROM permission WHERE code LIKE 'demandes.%'");
    }
}
