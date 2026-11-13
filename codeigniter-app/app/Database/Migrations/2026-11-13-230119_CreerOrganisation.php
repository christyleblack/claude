<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Module Organisation (lot 1) : types d'entités, entités et leurs deux arbres
 * (hiérarchique et logistique), forces, sites, emplacements.
 *
 * Voir docs/lot1-modele-de-donnees.md, section 2.
 *
 * Les chemins ltree et la force d'une entité sont calculés par la base
 * (déclencheurs) : l'application ne peut pas les saisir, ce qui garantit
 * la cohérence des arbres (RG-01) et de l'appartenance à une force (RG-02).
 */
class CreerOrganisation extends Migration
{
    public function up()
    {
        $this->db->query(<<<'SQL'
            CREATE TABLE type_entite (
                id           serial PRIMARY KEY,
                code         varchar(30)  NOT NULL UNIQUE CHECK (code ~ '^[A-Z0-9_]+$'),
                libelle      varchar(150) NOT NULL,
                est_stockeur boolean      NOT NULL DEFAULT false,
                created_at   timestamp,
                updated_at   timestamp
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE entite (
                id                         serial PRIMARY KEY,
                code                       varchar(30)  NOT NULL UNIQUE,
                nom                        varchar(200) NOT NULL,
                type_entite_id             integer      NOT NULL REFERENCES type_entite (id),
                -- Calculé : force de l'ancêtre hiérarchique le plus proche qui est la racine d'une force (RG-02)
                force_id                   integer,
                parent_hierarchique_id     integer REFERENCES entite (id),
                parent_logistique_id       integer REFERENCES entite (id),
                -- Calculés : chemins d'identifiants depuis la racine de chaque arbre (RG-01)
                chemin_hierarchique        ltree        NOT NULL,
                chemin_logistique          ltree        NOT NULL,
                -- Régime de responsabilité, paramétrable par entité (section 2.4)
                signature_nominative       boolean      NOT NULL DEFAULT false,
                inventaire_contradictoire  boolean      NOT NULL DEFAULT false,
                restitution_fin_engagement boolean      NOT NULL DEFAULT false,
                actif                      boolean      NOT NULL DEFAULT true,
                created_at                 timestamp,
                updated_at                 timestamp,
                CHECK (parent_hierarchique_id IS DISTINCT FROM id),
                CHECK (parent_logistique_id IS DISTINCT FROM id)
            )
            SQL);
        $this->db->query('CREATE INDEX entite_chemin_hierarchique_idx ON entite USING gist (chemin_hierarchique)');
        $this->db->query('CREATE INDEX entite_chemin_logistique_idx ON entite USING gist (chemin_logistique)');

        $this->db->query(<<<'SQL'
            CREATE TABLE force (
                id                       serial PRIMARY KEY,
                code                     varchar(30)  NOT NULL UNIQUE,
                nom                      varchar(200) NOT NULL,
                entite_racine_id         integer      NOT NULL UNIQUE REFERENCES entite (id),
                -- Autonomie de la direction du matériel de la force (section 2.5)
                validation_finale_locale boolean      NOT NULL DEFAULT false,
                achats_propres           boolean      NOT NULL DEFAULT false,
                seuil_quantite_dcma      numeric(14, 3) CHECK (seuil_quantite_dcma >= 0),
                seuil_montant_dcma       numeric(16, 2) CHECK (seuil_montant_dcma >= 0),
                created_at               timestamp,
                updated_at               timestamp
            )
            SQL);
        $this->db->query('ALTER TABLE entite ADD CONSTRAINT entite_force_fk FOREIGN KEY (force_id) REFERENCES force (id)');

        // Calcul des chemins et de la force, refus des cycles
        $this->db->query(<<<'SQL'
            CREATE FUNCTION entite_calculer_chemins() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                chemin_parent ltree;
            BEGIN
                -- Arbre hiérarchique : recalculé à la création ou quand le parent change.
                -- Sinon, une modification directe du chemin est ignorée, sauf lors de la
                -- propagation aux descendants (déclencheur imbriqué, profondeur > 1).
                IF TG_OP = 'INSERT' OR NEW.parent_hierarchique_id IS DISTINCT FROM OLD.parent_hierarchique_id THEN
                    IF NEW.parent_hierarchique_id IS NULL THEN
                        NEW.chemin_hierarchique := text2ltree(NEW.id::text);
                    ELSE
                        SELECT chemin_hierarchique INTO chemin_parent FROM entite WHERE id = NEW.parent_hierarchique_id;
                        IF TG_OP = 'UPDATE' AND chemin_parent <@ OLD.chemin_hierarchique THEN
                            RAISE EXCEPTION 'Cycle refusé : une entité ne peut pas dépendre hiérarchiquement d''une de ses subordonnées'
                                USING ERRCODE = 'check_violation';
                        END IF;
                        NEW.chemin_hierarchique := chemin_parent || text2ltree(NEW.id::text);
                    END IF;
                ELSIF pg_trigger_depth() = 1 THEN
                    NEW.chemin_hierarchique := OLD.chemin_hierarchique;
                END IF;

                -- Arbre logistique : même principe, indépendant de l'arbre hiérarchique.
                IF TG_OP = 'INSERT' OR NEW.parent_logistique_id IS DISTINCT FROM OLD.parent_logistique_id THEN
                    IF NEW.parent_logistique_id IS NULL THEN
                        NEW.chemin_logistique := text2ltree(NEW.id::text);
                    ELSE
                        SELECT chemin_logistique INTO chemin_parent FROM entite WHERE id = NEW.parent_logistique_id;
                        IF TG_OP = 'UPDATE' AND chemin_parent <@ OLD.chemin_logistique THEN
                            RAISE EXCEPTION 'Cycle refusé : une entité ne peut pas être ravitaillée par une de ses subordonnées logistiques'
                                USING ERRCODE = 'check_violation';
                        END IF;
                        NEW.chemin_logistique := chemin_parent || text2ltree(NEW.id::text);
                    END IF;
                ELSIF pg_trigger_depth() = 1 THEN
                    NEW.chemin_logistique := OLD.chemin_logistique;
                END IF;

                -- Force : celle dont l'entité est la racine, sinon celle de l'ancêtre
                -- hiérarchique le plus proche qui est racine d'une force ; NULL au niveau national.
                SELECT f.id INTO NEW.force_id
                FROM force f
                JOIN entite r ON r.id = f.entite_racine_id
                WHERE f.entite_racine_id = NEW.id
                   OR NEW.chemin_hierarchique <@ r.chemin_hierarchique
                ORDER BY (f.entite_racine_id = NEW.id) DESC, nlevel(r.chemin_hierarchique) DESC
                LIMIT 1;

                RETURN NEW;
            END;
            $$
            SQL);
        $this->db->query(<<<'SQL'
            CREATE TRIGGER entite_calculer_chemins
                BEFORE INSERT OR UPDATE ON entite
                FOR EACH ROW EXECUTE FUNCTION entite_calculer_chemins()
            SQL);

        // Propagation d'un déplacement aux descendants
        $this->db->query(<<<'SQL'
            CREATE FUNCTION entite_propager_chemins() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.chemin_hierarchique IS DISTINCT FROM OLD.chemin_hierarchique THEN
                    UPDATE entite
                    SET chemin_hierarchique = NEW.chemin_hierarchique || subpath(chemin_hierarchique, nlevel(OLD.chemin_hierarchique))
                    WHERE chemin_hierarchique <@ OLD.chemin_hierarchique AND id <> NEW.id;
                END IF;

                IF NEW.chemin_logistique IS DISTINCT FROM OLD.chemin_logistique THEN
                    UPDATE entite
                    SET chemin_logistique = NEW.chemin_logistique || subpath(chemin_logistique, nlevel(OLD.chemin_logistique))
                    WHERE chemin_logistique <@ OLD.chemin_logistique AND id <> NEW.id;
                END IF;

                RETURN NULL;
            END;
            $$
            SQL);
        $this->db->query(<<<'SQL'
            CREATE TRIGGER entite_propager_chemins
                AFTER UPDATE ON entite
                FOR EACH ROW EXECUTE FUNCTION entite_propager_chemins()
            SQL);

        // Création ou changement de racine d'une force : recalcul de la force des entités concernées
        $this->db->query(<<<'SQL'
            CREATE FUNCTION force_recalculer_entites() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                -- Mise à jour sans changement : le déclencheur de l'entité recalcule force_id.
                UPDATE entite SET id = id
                WHERE chemin_hierarchique <@ (SELECT chemin_hierarchique FROM entite WHERE id = NEW.entite_racine_id)
                   OR (TG_OP = 'UPDATE' AND chemin_hierarchique <@ (SELECT chemin_hierarchique FROM entite WHERE id = OLD.entite_racine_id));
                RETURN NULL;
            END;
            $$
            SQL);
        $this->db->query(<<<'SQL'
            CREATE TRIGGER force_recalculer_entites
                AFTER INSERT OR UPDATE OF entite_racine_id ON force
                FOR EACH ROW EXECUTE FUNCTION force_recalculer_entites()
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE site (
                id         serial PRIMARY KEY,
                code       varchar(30)  NOT NULL UNIQUE,
                nom        varchar(200) NOT NULL,
                adresse    text,
                entite_id  integer      NOT NULL REFERENCES entite (id),
                actif      boolean      NOT NULL DEFAULT true,
                created_at timestamp,
                updated_at timestamp
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE TABLE emplacement (
                id         serial PRIMARY KEY,
                site_id    integer      NOT NULL REFERENCES site (id),
                parent_id  integer,
                code       varchar(30)  NOT NULL,
                libelle    varchar(200) NOT NULL,
                actif      boolean      NOT NULL DEFAULT true,
                created_at timestamp,
                updated_at timestamp,
                UNIQUE (site_id, code),
                UNIQUE (site_id, id),
                -- Un emplacement ne peut être contenu que dans un emplacement du même site
                FOREIGN KEY (site_id, parent_id) REFERENCES emplacement (site_id, id),
                CHECK (parent_id IS DISTINCT FROM id)
            )
            SQL);

        $this->db->query(<<<'SQL'
            CREATE FUNCTION emplacement_refuser_cycle() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
                IF NEW.parent_id IS NOT NULL AND EXISTS (
                    WITH RECURSIVE ancetres AS (
                        SELECT id, parent_id FROM emplacement WHERE id = NEW.parent_id
                        UNION
                        SELECT e.id, e.parent_id FROM emplacement e JOIN ancetres a ON e.id = a.parent_id
                    )
                    SELECT 1 FROM ancetres WHERE id = NEW.id
                ) THEN
                    RAISE EXCEPTION 'Cycle refusé : un emplacement ne peut pas être contenu dans un de ses sous-emplacements'
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->db->query(<<<'SQL'
            CREATE TRIGGER emplacement_refuser_cycle
                BEFORE INSERT OR UPDATE OF parent_id ON emplacement
                FOR EACH ROW EXECUTE FUNCTION emplacement_refuser_cycle()
            SQL);
    }

    public function down()
    {
        $this->db->query('DROP TABLE IF EXISTS emplacement, site CASCADE');
        $this->db->query('ALTER TABLE IF EXISTS entite DROP CONSTRAINT IF EXISTS entite_force_fk');
        $this->db->query('DROP TABLE IF EXISTS force, entite, type_entite CASCADE');
        $this->db->query('DROP FUNCTION IF EXISTS emplacement_refuser_cycle(), force_recalculer_entites(), entite_propager_chemins(), entite_calculer_chemins()');
    }
}
