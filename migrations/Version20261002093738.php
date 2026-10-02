<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261002093738 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE mouvement_caisse (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(10) NOT NULL, montant NUMERIC(15, 2) NOT NULL, devise VARCHAR(3) NOT NULL, provenance VARCHAR(255) NOT NULL, reference VARCHAR(100) DEFAULT NULL, categorie VARCHAR(50) DEFAULT NULL, date_mouvement DATE NOT NULL, created_at DATETIME NOT NULL, devis_id INT DEFAULT NULL, effectue_par_id INT DEFAULT NULL, INDEX IDX_C8E3DDFE41DEFADA (devis_id), INDEX IDX_C8E3DDFEFAE26142 (effectue_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mouvement_caisse ADD CONSTRAINT FK_C8E3DDFE41DEFADA FOREIGN KEY (devis_id) REFERENCES devis (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE mouvement_caisse ADD CONSTRAINT FK_C8E3DDFEFAE26142 FOREIGN KEY (effectue_par_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mouvement_caisse DROP FOREIGN KEY FK_C8E3DDFE41DEFADA');
        $this->addSql('ALTER TABLE mouvement_caisse DROP FOREIGN KEY FK_C8E3DDFEFAE26142');
        $this->addSql('DROP TABLE mouvement_caisse');
    }
}
