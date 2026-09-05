<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260815115627 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL');
        $this->addSql('ALTER TABLE visite ADD responsable_delegue_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE visite ADD CONSTRAINT FK_B09C8CBB36AA666F FOREIGN KEY (responsable_delegue_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_B09C8CBB36AA666F ON visite (responsable_delegue_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
        $this->addSql('ALTER TABLE visite DROP FOREIGN KEY FK_B09C8CBB36AA666F');
        $this->addSql('DROP INDEX IDX_B09C8CBB36AA666F ON visite');
        $this->addSql('ALTER TABLE visite DROP responsable_delegue_id');
    }
}
