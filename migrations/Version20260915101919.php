<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260915101919 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY `FK_B09C8CBB36AA666F`');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBB36AA666F FOREIGN KEY (responsable_delegue_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBB36AA666F');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT `FK_B09C8CBB36AA666F` FOREIGN KEY (responsable_delegue_id) REFERENCES user (id)');
    }
}
