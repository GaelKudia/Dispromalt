<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925020306 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article ADD alerte_declenchee TINYINT DEFAULT 0 NOT NULL, ADD date_reapprovisionnement DATETIME DEFAULT NULL, ADD reapprovisionnement_en_cours TINYINT DEFAULT 0 NOT NULL, ADD date_signalement_alerte DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP alerte_declenchee, DROP date_reapprovisionnement, DROP reapprovisionnement_en_cours, DROP date_signalement_alerte');
    }
}
