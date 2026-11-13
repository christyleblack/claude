<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Module Référentiel du matériel (lot 1, REF-01 à REF-05, RG-05 à RG-09).
 * Voir docs/lot1-modele-de-donnees.md, section 4.
 *
 * - Filières (gérées chacune par un service central), catégories, articles génériques
 *   nationaux, fiches propres à chaque force.
 * - Le référentiel est lisible par tous ; l'écriture est contrôlée par la base (RLS) :
 *   un article ou une catégorie ne s'écrit qu'avec la permission referentiel.gerer
 *   au niveau national, éventuellement restreinte à une filière (H-02) ; une fiche
 *   de force qu'avec referentiel.fiche_force sur cette force.
 * - Les lots et les matériels sérialisés seront créés par les mouvements d'entrée
 *   (module Stocks et mouvements).
 */
class CreerReferentiel extends Migration
{
    private const TABLES = ['filiere', 'categorie', 'article', 'article_force'];

    public function up()
    {
        $this->db->query(<<<'SQL'
            INSERT INTO permission (code, libelle) VALUES
                ('referentiel.gerer', 'Gérer le référentiel national du matériel (filières, catégories, articles), éventuellement limité à une filière'),
                ('referentiel.fiche_force', 'Gérer les fiches d''articles propres à sa force')
            SQL);

        // Unités de gestion : données de référence, fixées par migration
        $this->db->query(<<<'SQL'
            CREATE TABLE unite (
                code    varchar(10)  PRIMARY KEY,
                libelle varchar(60)  NOT NULL,
                nature  varchar(10)  NOT NULL CHECK (nature IN ('nombre', 'volume', 'masse'))
            )
            SQL);
        $this->db->query(<<<'SQL'
            INSERT INTO unite (code, libelle, nature) VALUES
                ('U', 'unité', 'nombre'),
                ('L', 'litre', 'volume'),
                ('M3', 'mètre cube', 'volume'),
                ('KG', 'kilogramme', 'masse'),
                ('T', 'tonne', 'masse')
            SQL);

        // Filière : famille de matériel gérée par un seul service central (RG-05)
        $this->db->query(<<<'SQL'
            CREATE TABLE filiere (
                id                      serial PRIMARY KEY,
                code                    varchar(30)  NOT NULL UNIQUE CHECK (code ~ '^[A-Z0-9_]+$'),
                libelle                 varchar(150) NOT NULL,
                service_gestionnaire_id integer      NOT NULL REFERENCES entite (id),
                actif                   boolean      NOT NULL DEFAULT true,
                created_at              timestamp,
                updated_at              timestamp
            )
            SQL);

        // Catégorie : arborescence propre à une filière ; peut imposer le mode de suivi (RG-07)
        $this->db->query(<<<'SQL'
            CREATE TABLE categorie (
                id                serial PRIMARY KEY,
                filiere_id        integer      NOT NULL REFERENCES filiere (id),
                parent_id         integer,
                code              varchar(30)  NOT NULL CHECK (code ~ '^[A-Z0-9_-]+$'),
                libelle           varchar(150) NOT NULL,
                mode_suivi_impose varchar(10)  CHECK (mode_suivi_impose IN ('quantite', 'lot', 'serie')),
                actif             boolean      NOT NULL DEFAULT true,
                created_at        timestamp,
                updated_at        timestamp,
                UNIQUE (filiere_id, code),
                UNIQUE (filiere_id, id),
                -- Une sous-catégorie appartient à la même filière que sa catégorie parente
                FOREIGN KEY (filiere_id, parent_id) REFERENCES categorie (filiere_id, id),
                CHECK (parent_id IS DISTINCT FROM id)
            )
            SQL);

        // Article générique national (REF-01, REF-03, REF-04, REF-05)
        $this->db->query(<<<'SQL'
            CREATE TABLE article (
                id            serial PRIMARY KEY,
                codification  varchar(40)  NOT NULL UNIQUE CHECK (codification ~ '^[A-Z0-9][A-Z0-9./-]*$'),
                designation   varchar(250) NOT NULL,
                unite_gestion varchar(10)  NOT NULL REFERENCES unite (code),
                filiere_id    integer      NOT NULL REFERENCES filiere (id),
                categorie_id  integer      NOT NULL,
                -- Un seul mode de suivi par article (RG-06)
                mode_suivi    varchar(10)  NOT NULL CHECK (mode_suivi IN ('quantite', 'lot', 'serie')),
                sensibilite   varchar(10)  NOT NULL CHECK (sensibilite IN ('courant', 'sensible', 'armement', 'munition')),
                actif         boolean      NOT NULL DEFAULT true,
                created_at    timestamp,
                updated_at    timestamp,
                -- La catégorie appartient à la filière de l'article (RG-05)
                FOREIGN KEY (filiere_id, categorie_id) REFERENCES categorie (filiere_id, id),
                -- RG-07 : armes suivies par numéro de série, munitions par lot
                CONSTRAINT armement_suivi_par_serie CHECK (sensibilite <> 'armement' OR mode_suivi = 'serie'),
                CONSTRAINT munition_suivie_par_lot CHECK (sensibilite <> 'munition' OR mode_suivi = 'lot'),
                -- Un matériel sérialisé se compte à l'unité
                CONSTRAINT serie_en_unites CHECK (mode_suivi <> 'serie' OR unite_gestion = 'U')
            )
            SQL);
        $this->db->query('CREATE INDEX article_filiere_idx ON article (filiere_id, categorie_id)');
        $this->db->query("CREATE INDEX article_designation_idx ON article USING gin (to_tsvector('french', designation))");

        // Fiche propre à une force : au plus une par couple article-force (REF-02, RG-09)
        $this->db->query(<<<'SQL'
            CREATE TABLE article_force (
                id                 serial PRIMARY KEY,
                article_id         integer      NOT NULL REFERENCES article (id),
                force_id           integer      NOT NULL REFERENCES force (id),
                code_local         varchar(40),
                designation_locale varchar(250),
                attributs          jsonb        NOT NULL DEFAULT '{}' CHECK (jsonb_typeof(attributs) = 'object'),
                actif              boolean      NOT NULL DEFAULT true,
                created_at         timestamp,
                updated_at         timestamp,
                UNIQUE (article_id, force_id)
            )
            SQL);

        // Mode de suivi imposé par la catégorie (RG-07), contrôlé dans les deux sens
        $this->db->query(<<<'SQL'
            CREATE FUNCTION article_verifier_mode_suivi() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            DECLARE
                impose varchar;
            BEGIN
                SELECT mode_suivi_impose INTO impose FROM categorie WHERE id = NEW.categorie_id;
                IF impose IS NOT NULL AND NEW.mode_suivi <> impose THEN
                    RAISE EXCEPTION 'Mode de suivi refusé : les articles de cette catégorie sont suivis en « % » (RG-07)', impose
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER article_verifier_mode_suivi BEFORE INSERT OR UPDATE OF mode_suivi, categorie_id ON article FOR EACH ROW EXECUTE FUNCTION article_verifier_mode_suivi()');

        $this->db->query(<<<'SQL'
            CREATE FUNCTION categorie_verifier() RETURNS trigger
            LANGUAGE plpgsql SECURITY DEFINER SET search_path = public AS $$
            BEGIN
                IF NEW.parent_id IS NOT NULL AND EXISTS (
                    WITH RECURSIVE ancetres AS (
                        SELECT id, parent_id FROM categorie WHERE id = NEW.parent_id
                        UNION
                        SELECT c.id, c.parent_id FROM categorie c JOIN ancetres a ON c.id = a.parent_id
                    )
                    SELECT 1 FROM ancetres WHERE id = NEW.id
                ) THEN
                    RAISE EXCEPTION 'Cycle refusé : une catégorie ne peut pas être rangée dans une de ses sous-catégories'
                        USING ERRCODE = 'check_violation';
                END IF;

                IF TG_OP = 'UPDATE' AND NEW.mode_suivi_impose IS NOT NULL AND EXISTS (
                    SELECT 1 FROM article WHERE categorie_id = NEW.id AND mode_suivi <> NEW.mode_suivi_impose
                ) THEN
                    RAISE EXCEPTION 'Mode de suivi refusé : des articles de cette catégorie ne sont pas suivis en « % » (RG-07)', NEW.mode_suivi_impose
                        USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$
            SQL);
        $this->db->query('CREATE TRIGGER categorie_verifier BEFORE INSERT OR UPDATE OF parent_id, mode_suivi_impose ON categorie FOR EACH ROW EXECUTE FUNCTION categorie_verifier()');

        // Restriction d'une affectation à une filière (H-02)
        $this->db->query('ALTER TABLE affectation ADD COLUMN filiere_id integer REFERENCES filiere (id)');

        // Droit sur une filière : permission au niveau national, par une affectation
        // non restreinte ou restreinte à cette filière. Sans filière : affectation non restreinte.
        $this->db->query(<<<'SQL'
            CREATE FUNCTION droits_peut_filiere(code_permission varchar, id_filiere integer)
            RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path = public AS $$
                SELECT EXISTS (
                    SELECT 1
                    FROM droits_affectations_actives(droits_utilisateur()) a
                    JOIN affectation af ON af.id = a.affectation_id
                    JOIN role_permission rp ON rp.role_id = a.role_id AND rp.permission = code_permission
                    WHERE a.portee = 'nationale'
                      AND (af.filiere_id IS NULL OR af.filiere_id = id_filiere)
                )
            $$
            SQL);

        // Écriture contrôlée par la base, lecture libre
        $regles = [
            'filiere'       => "droits_peut_filiere('referentiel.gerer', NULL)",
            'categorie'     => "droits_peut_filiere('referentiel.gerer', filiere_id)",
            'article'       => "droits_peut_filiere('referentiel.gerer', filiere_id)",
            'article_force' => "droits_peut('referentiel.fiche_force', (SELECT entite_racine_id FROM force WHERE force.id = force_id))",
        ];
        foreach ($regles as $table => $condition) {
            $this->db->query("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
            $this->db->query("CREATE POLICY lecture ON {$table} FOR SELECT USING (true)");
            $this->db->query("CREATE POLICY creation ON {$table} FOR INSERT WITH CHECK ({$condition})");
            $this->db->query("CREATE POLICY modification ON {$table} FOR UPDATE USING ({$condition}) WITH CHECK ({$condition})");
            $this->db->query("CREATE TRIGGER audit AFTER INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION audit_journaliser()");
        }

        // Rien ne se supprime (désactivation) ; les unités sont fixées par migration
        $this->db->query('REVOKE DELETE ON filiere, categorie, article, article_force FROM gestion_app');
        $this->db->query('REVOKE INSERT, UPDATE, DELETE ON unite FROM gestion_app');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE affectation DROP COLUMN IF EXISTS filiere_id');
        $this->db->query('DROP TABLE IF EXISTS article_force, article, categorie, filiere, unite CASCADE');
        $this->db->query('DROP FUNCTION IF EXISTS droits_peut_filiere(varchar, integer), categorie_verifier(), article_verifier_mode_suivi()');
        $this->db->query("DELETE FROM role_permission WHERE permission IN ('referentiel.gerer', 'referentiel.fiche_force')");
        $this->db->query("DELETE FROM permission WHERE code IN ('referentiel.gerer', 'referentiel.fiche_force')");
    }
}
