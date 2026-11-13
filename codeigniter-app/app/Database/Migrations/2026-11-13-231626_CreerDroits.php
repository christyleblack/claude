<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Module Utilisateurs et droits (lot 1, section 4 du cahier des charges).
 *
 * - Rôles et permissions (RBAC), affectations datées d'un rôle sur une entité (ABAC, RG-04),
 *   délégations temporaires (règle 4.2-5).
 * - Cloisonnement appliqué dans la base (règle 4.2-6) : sécurité au niveau des lignes
 *   (Row Level Security) sur les entités, sites et emplacements. L'application travaille
 *   sous le rôle PostgreSQL « gestion_app », soumis aux politiques ; le propriétaire des
 *   tables (migrations, commandes en ligne) n'y est pas soumis.
 * - Journal d'audit en insertion seule (ADM-03, section 5 « Traçabilité »), alimenté
 *   automatiquement par déclencheurs pour toute modification des tables protégées.
 *
 * Prérequis : le rôle gestion_app existe et le propriétaire des tables en est membre
 * (voir README, section « Base de données PostgreSQL »).
 */
class CreerDroits extends Migration
{
    private const TABLES_AUDITEES = [
        'type_entite', 'entite', 'force', 'site', 'emplacement',
        'role', 'role_permission', 'affectation', 'delegation',
    ];

    public function up()
    {
        $this->db->query(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'gestion_app') THEN
                    RAISE EXCEPTION 'Le rôle PostgreSQL gestion_app n''existe pas : le créer d''abord (voir README).';
                END IF;
            END
            $$
            SQL);

        // Permissions : codes utilisés par l'application, donc créés ici et non paramétrables
        $this->db->query(<<<'SQL'
            CREATE TABLE permission (
                code    varchar(60)  PRIMARY KEY,
                libelle varchar(200) NOT NULL
            )
            SQL);
        $this->db->query(<<<'SQL'
            INSERT INTO permission (code, libelle) VALUES
                ('organisation.gerer', 'Gérer l''organisation : entités, forces, types d''entités, sites, emplacements'),
                ('administration.utilisateurs', 'Gérer les affectations et les délégations des utilisateurs'),
                ('administration.roles', 'Paramétrer les rôles et leurs permissions'),
                ('audit.consulter', 'Consulter le journal d''audit')
            SQL);

        // Rôles : paramétrables sans développement (ADM-02)
        $this->db->query(<<<'SQL'
            CREATE TABLE role (
                id         serial PRIMARY KEY,
                code       varchar(40)  NOT NULL UNIQUE CHECK (code ~ '^[A-Z0-9_]+$'),
                libelle    varchar(150) NOT NULL,
                -- nationale : toute l'organisation ; entite : l'entité d'affectation et ses subordonnées
                portee     varchar(10)  NOT NULL CHECK (portee IN ('nationale', 'entite')),
                actif      boolean      NOT NULL DEFAULT true,
                created_at timestamp,
                updated_at timestamp
            )
            SQL);
        $this->db->query(<<<'SQL'
            CREATE TABLE role_permission (
                role_id    integer     NOT NULL REFERENCES role (id) ON DELETE CASCADE,
                permission varchar(60) NOT NULL REFERENCES permission (code),
                PRIMARY KEY (role_id, permission)
            )
            SQL);

        // Affectation : un rôle exercé sur une entité pendant une période (RG-04)
        $this->db->query(<<<'SQL'
            CREATE TABLE affectation (
                id             serial PRIMARY KEY,
                utilisateur_id integer NOT NULL REFERENCES users (id),
                role_id        integer NOT NULL REFERENCES role (id),
                entite_id      integer NOT NULL REFERENCES entite (id),
                date_debut     date    NOT NULL,
                date_fin       date,
                -- Révocation avant terme : l'affectation reste dans l'historique
                revoquee_le    timestamptz,
                revoquee_par   integer REFERENCES users (id),
                cree_par       integer REFERENCES users (id),
                created_at     timestamp,
                updated_at     timestamp,
                CHECK (date_fin IS NULL OR date_fin >= date_debut)
            )
            SQL);
        $this->db->query('CREATE INDEX affectation_utilisateur_idx ON affectation (utilisateur_id)');

        // Délégation : transfert temporaire d'une affectation, daté et tracé (règle 4.2-5)
        $this->db->query(<<<'SQL'
            CREATE TABLE delegation (
                id             serial PRIMARY KEY,
                affectation_id integer NOT NULL REFERENCES affectation (id),
                delegataire_id integer NOT NULL REFERENCES users (id),
                date_debut     date    NOT NULL,
                date_fin       date    NOT NULL,
                motif          text    NOT NULL CHECK (length(trim(motif)) > 0),
                revoquee_le    timestamptz,
                revoquee_par   integer REFERENCES users (id),
                cree_par       integer REFERENCES users (id),
                created_at     timestamp,
                updated_at     timestamp,
                CHECK (date_fin >= date_debut)
            )
            SQL);
        $this->db->query('CREATE INDEX delegation_delegataire_idx ON delegation (delegataire_id)');

        $this->db->query(<<<'SQL'
            CREATE FUNCTION delegation_verifier() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                a affectation%ROWTYPE;
            BEGIN
                SELECT * INTO a FROM affectation WHERE id = NEW.affectation_id;
                IF NEW.delegataire_id = a.utilisateur_id THEN
                    RAISE EXCEPTION 'Délégation refusée : on ne peut pas se déléguer ses propres droits'
                        USING ERRCODE = 'check_violation';
                END IF;
                IF NEW.date_debut < a.date_debut OR (a.date_fin IS NOT NULL AND NEW.date_fin > a.date_fin) THEN
                    RAISE EXCEPTION 'Délégation refusée : la période doit être comprise dans celle de l''affectation déléguée'
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER delegation_verifier BEFORE INSERT OR UPDATE ON delegation FOR EACH ROW EXECUTE FUNCTION delegation_verifier()');

        // Journal d'audit : insertion seule
        $this->db->query(<<<'SQL'
            CREATE TABLE journal_audit (
                id             bigserial PRIMARY KEY,
                horodatage     timestamptz NOT NULL DEFAULT now(),
                utilisateur_id integer,
                action         varchar(40) NOT NULL,
                objet_type     varchar(60),
                objet_id       bigint,
                details        jsonb,
                adresse_ip     varchar(45)
            )
            SQL);
        $this->db->query('CREATE INDEX journal_audit_horodatage_idx ON journal_audit (horodatage DESC)');
        $this->db->query(<<<'SQL'
            CREATE FUNCTION journal_audit_immuable() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'Le journal d''audit est immuable : aucune modification ni suppression n''est permise'
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER journal_audit_immuable BEFORE UPDATE OR DELETE ON journal_audit FOR EACH ROW EXECUTE FUNCTION journal_audit_immuable()');
        $this->db->query('CREATE TRIGGER journal_audit_immuable_truncate BEFORE TRUNCATE ON journal_audit FOR EACH STATEMENT EXECUTE FUNCTION journal_audit_immuable()');

        // Contexte transmis par l'application à chaque requête (filtre ContexteDroits)
        $this->db->query(<<<'SQL'
            CREATE FUNCTION droits_utilisateur() RETURNS integer LANGUAGE sql STABLE AS $$
                SELECT NULLIF(current_setting('app.utilisateur_id', true), '')::integer
            $$
            SQL);

        // Affectations actives d'un utilisateur, y compris celles reçues par délégation
        $this->db->query(<<<'SQL'
            CREATE FUNCTION droits_affectations_actives(uid integer)
            RETURNS TABLE (affectation_id integer, role_id integer, portee varchar, chemin ltree, par_delegation boolean)
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT a.id, a.role_id, r.portee, e.chemin_hierarchique, false
                FROM affectation a
                JOIN role r ON r.id = a.role_id AND r.actif
                JOIN entite e ON e.id = a.entite_id
                WHERE a.utilisateur_id = uid AND a.revoquee_le IS NULL
                  AND a.date_debut <= current_date AND (a.date_fin IS NULL OR a.date_fin >= current_date)
                UNION ALL
                SELECT a.id, a.role_id, r.portee, e.chemin_hierarchique, true
                FROM delegation d
                JOIN affectation a ON a.id = d.affectation_id
                JOIN role r ON r.id = a.role_id AND r.actif
                JOIN entite e ON e.id = a.entite_id
                WHERE d.delegataire_id = uid AND d.revoquee_le IS NULL AND a.revoquee_le IS NULL
                  AND d.date_debut <= current_date AND d.date_fin >= current_date
                  AND a.date_debut <= current_date AND (a.date_fin IS NULL OR a.date_fin >= current_date)
            $$
            SQL);

        // Périmètre (règle 4.2-3) : une entité est visible si une affectation active la couvre,
        // c'est-à-dire porte sur elle ou sur un de ses ancêtres hiérarchiques, ou est de portée nationale.
        $this->db->query(<<<'SQL'
            CREATE FUNCTION droits_chemin_visible(cible ltree) RETURNS boolean
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT EXISTS (
                    SELECT 1 FROM droits_affectations_actives(droits_utilisateur()) a
                    WHERE a.portee = 'nationale' OR cible <@ a.chemin
                )
            $$
            SQL);
        $this->db->query(<<<'SQL'
            CREATE FUNCTION droits_entite_visible(id_entite integer) RETURNS boolean
            LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT droits_chemin_visible((SELECT chemin_hierarchique FROM entite WHERE id = id_entite))
            $$
            SQL);

        // RBAC + ABAC en un seul point : l'utilisateur a-t-il la permission, sur cette entité
        // (ou, sans entité, quelque part ; ou, avec nationale, au niveau national) ?
        $this->db->query(<<<'SQL'
            CREATE FUNCTION droits_peut(code_permission varchar, id_entite integer DEFAULT NULL, exiger_nationale boolean DEFAULT false)
            RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT EXISTS (
                    SELECT 1
                    FROM droits_affectations_actives(droits_utilisateur()) a
                    JOIN role_permission rp ON rp.role_id = a.role_id AND rp.permission = code_permission
                    WHERE (a.portee = 'nationale')
                       OR (NOT exiger_nationale AND id_entite IS NULL)
                       OR (NOT exiger_nationale AND (SELECT chemin_hierarchique FROM entite WHERE id = id_entite) <@ a.chemin)
                )
            $$
            SQL);

        // Les déclencheurs de l'organisation lisent et propagent au-delà du périmètre de
        // l'utilisateur (parents, descendants) : ils s'exécutent avec les droits du propriétaire.
        foreach (['entite_calculer_chemins', 'entite_propager_chemins', 'force_recalculer_entites', 'emplacement_refuser_cycle'] as $fonction) {
            $this->db->query("ALTER FUNCTION {$fonction}() SECURITY DEFINER SET search_path = public");
        }

        // Cloisonnement : politiques de sécurité au niveau des lignes
        $this->db->query('ALTER TABLE entite ENABLE ROW LEVEL SECURITY');
        $this->db->query(<<<'SQL'
            CREATE POLICY perimetre ON entite
                USING (droits_chemin_visible(chemin_hierarchique))
                WITH CHECK (droits_chemin_visible(chemin_hierarchique))
            SQL);
        $this->db->query('ALTER TABLE site ENABLE ROW LEVEL SECURITY');
        $this->db->query(<<<'SQL'
            CREATE POLICY perimetre ON site
                USING (droits_entite_visible(entite_id))
                WITH CHECK (droits_entite_visible(entite_id))
            SQL);
        $this->db->query('ALTER TABLE emplacement ENABLE ROW LEVEL SECURITY');
        $this->db->query(<<<'SQL'
            CREATE POLICY perimetre ON emplacement
                USING (droits_entite_visible((SELECT entite_id FROM site WHERE id = site_id)))
                WITH CHECK (droits_entite_visible((SELECT entite_id FROM site WHERE id = site_id)))
            SQL);

        // Audit automatique de toute modification des tables protégées
        $this->db->query(<<<'SQL'
            CREATE FUNCTION audit_journaliser() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                avant jsonb := CASE WHEN TG_OP IN ('UPDATE', 'DELETE') THEN to_jsonb(OLD) END;
                apres jsonb := CASE WHEN TG_OP IN ('INSERT', 'UPDATE') THEN to_jsonb(NEW) END;
            BEGIN
                INSERT INTO journal_audit (utilisateur_id, action, objet_type, objet_id, details)
                VALUES (
                    droits_utilisateur(),
                    TG_OP,
                    TG_TABLE_NAME,
                    (COALESCE(apres, avant) ->> 'id')::bigint,
                    jsonb_strip_nulls(jsonb_build_object('avant', avant, 'apres', apres))
                );
                RETURN NULL;
            END;
            $$
            SQL);
        foreach (self::TABLES_AUDITEES as $table) {
            $this->db->query("CREATE TRIGGER audit AFTER INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION audit_journaliser()");
        }

        // Droits du rôle applicatif
        $this->db->query('GRANT USAGE ON SCHEMA public TO gestion_app');
        $this->db->query('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO gestion_app');
        $this->db->query('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO gestion_app');
        $this->db->query('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO gestion_app');
        $this->db->query('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT ON SEQUENCES TO gestion_app');
        // Rien ne se supprime dans le référentiel (désactivation), le journal est en insertion seule,
        // et l'application ne touche ni aux migrations ni aux permissions.
        $this->db->query('REVOKE DELETE ON type_entite, entite, force, site, emplacement, role, affectation, delegation FROM gestion_app');
        $this->db->query('REVOKE UPDATE, DELETE, TRUNCATE ON journal_audit FROM gestion_app');
        $this->db->query('REVOKE INSERT, UPDATE, DELETE ON migrations, permission FROM gestion_app');
    }

    public function down()
    {
        foreach (self::TABLES_AUDITEES as $table) {
            $this->db->query("DROP TRIGGER IF EXISTS audit ON {$table}");
        }
        foreach (['entite', 'site', 'emplacement'] as $table) {
            $this->db->query("DROP POLICY IF EXISTS perimetre ON {$table}");
            $this->db->query("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
        foreach (['entite_calculer_chemins', 'entite_propager_chemins', 'force_recalculer_entites', 'emplacement_refuser_cycle'] as $fonction) {
            $this->db->query("ALTER FUNCTION {$fonction}() SECURITY INVOKER RESET search_path");
        }

        $this->db->query('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE SELECT, INSERT, UPDATE, DELETE ON TABLES FROM gestion_app');
        $this->db->query('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE USAGE, SELECT ON SEQUENCES FROM gestion_app');
        $this->db->query('REVOKE ALL ON ALL TABLES IN SCHEMA public FROM gestion_app');
        $this->db->query('REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM gestion_app');
        $this->db->query('REVOKE USAGE ON SCHEMA public FROM gestion_app');

        $this->db->query('DROP TABLE IF EXISTS journal_audit, delegation, affectation, role_permission, role, permission CASCADE');
        $this->db->query('DROP FUNCTION IF EXISTS audit_journaliser(), droits_peut(varchar, integer, boolean), droits_entite_visible(integer), droits_chemin_visible(ltree), droits_affectations_actives(integer), droits_utilisateur(), journal_audit_immuable(), delegation_verifier()');
    }
}
