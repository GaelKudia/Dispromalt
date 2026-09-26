<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925031354 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE historique_reapprovisionnement (id INT AUTO_INCREMENT NOT NULL, type_action VARCHAR(50) NOT NULL, commentaire LONGTEXT DEFAULT NULL, date_action DATETIME NOT NULL, ancienne_date_reappro DATETIME DEFAULT NULL, nouvelle_date_reappro DATETIME DEFAULT NULL, quantite_au_moment INT DEFAULT NULL, seuil_au_moment INT DEFAULT NULL, article_id INT NOT NULL, effectue_par_id INT DEFAULT NULL, INDEX IDX_AC88B6DF7294869C (article_id), INDEX IDX_AC88B6DFFAE26142 (effectue_par_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE historique_reapprovisionnement ADD CONSTRAINT FK_AC88B6DF7294869C FOREIGN KEY (article_id) REFERENCES article (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE historique_reapprovisionnement ADD CONSTRAINT FK_AC88B6DFFAE26142 FOREIGN KEY (effectue_par_id) REFERENCES user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE historique_reapprovisionnement DROP FOREIGN KEY FK_AC88B6DF7294869C');
        $this->addSql('ALTER TABLE historique_reapprovisionnement DROP FOREIGN KEY FK_AC88B6DFFAE26142');
        $this->addSql('DROP TABLE historique_reapprovisionnement');
    }
}
