<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260813085847 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE article (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, quantite INT NOT NULL, seuil_alerte INT NOT NULL, category_id INT DEFAULT NULL, sub_category_id INT DEFAULT NULL, machine_id INT DEFAULT NULL, INDEX IDX_23A0E6612469DE2 (category_id), INDEX IDX_23A0E66F7BFE87C (sub_category_id), INDEX IDX_23A0E66F6B75B26 (machine_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE article_attribut (id INT AUTO_INCREMENT NOT NULL, dimension VARCHAR(255) DEFAULT NULL, grammage VARCHAR(255) DEFAULT NULL, couleur VARCHAR(255) DEFAULT NULL, longueur VARCHAR(255) DEFAULT NULL, kilogramme VARCHAR(255) DEFAULT NULL, article_id INT NOT NULL, INDEX IDX_2F501C367294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE devis (montant_initial NUMERIC(10, 2) DEFAULT NULL, id INT AUTO_INCREMENT NOT NULL, description VARCHAR(255) NOT NULL, montant DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, created_by VARCHAR(255) NOT NULL, entete VARCHAR(255) NOT NULL, statut VARCHAR(50) NOT NULL, devise VARCHAR(3) NOT NULL, date_decaissement DATETIME DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE machine (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, category_id INT NOT NULL, sub_category_id INT NOT NULL, INDEX IDX_1505DF8412469DE2 (category_id), INDEX IDX_1505DF84F7BFE87C (sub_category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE sub_category (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, category_id INT NOT NULL, INDEX IDX_BCE3F79812469DE2 (category_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, localisation VARCHAR(255) DEFAULT NULL, actif TINYINT NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE visite (id INT AUTO_INCREMENT NOT NULL, nom_visiteur VARCHAR(255) NOT NULL, departement VARCHAR(255) NOT NULL, motif VARCHAR(255) NOT NULL, statut VARCHAR(50) NOT NULL, date_arrivee DATETIME DEFAULT NULL, telephone VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, piece_identite VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E6612469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66F7BFE87C FOREIGN KEY (sub_category_id) REFERENCES sub_category (id)');
        $this->addSql('ALTER TABLE article ADD CONSTRAINT FK_23A0E66F6B75B26 FOREIGN KEY (machine_id) REFERENCES machine (id)');
        $this->addSql('ALTER TABLE article_attribut ADD CONSTRAINT FK_2F501C367294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF8412469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE machine ADD CONSTRAINT FK_1505DF84F7BFE87C FOREIGN KEY (sub_category_id) REFERENCES sub_category (id)');
        $this->addSql('ALTER TABLE sub_category ADD CONSTRAINT FK_BCE3F79812469DE2 FOREIGN KEY (category_id) REFERENCES category (id)');
        $this->addSql('ALTER TABLE demande ADD destination VARCHAR(255) DEFAULT NULL, ADD created_at DATETIME NOT NULL, ADD localisation VARCHAR(255) DEFAULT NULL, ADD article_id INT DEFAULT NULL, ADD demandeur_id INT DEFAULT NULL, ADD attribut_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE demande ADD CONSTRAINT FK_2694D7A57294869C FOREIGN KEY (article_id) REFERENCES article (id)');
        $this->addSql('ALTER TABLE demande ADD CONSTRAINT FK_2694D7A595A6EE59 FOREIGN KEY (demandeur_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE demande ADD CONSTRAINT FK_2694D7A551383AF3 FOREIGN KEY (attribut_id) REFERENCES article_attribut (id)');
        $this->addSql('CREATE INDEX IDX_2694D7A57294869C ON demande (article_id)');
        $this->addSql('CREATE INDEX IDX_2694D7A595A6EE59 ON demande (demandeur_id)');
        $this->addSql('CREATE INDEX IDX_2694D7A551383AF3 ON demande (attribut_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E6612469DE2');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66F7BFE87C');
        $this->addSql('ALTER TABLE article DROP FOREIGN KEY FK_23A0E66F6B75B26');
        $this->addSql('ALTER TABLE article_attribut DROP FOREIGN KEY FK_2F501C367294869C');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_1505DF8412469DE2');
        $this->addSql('ALTER TABLE machine DROP FOREIGN KEY FK_1505DF84F7BFE87C');
        $this->addSql('ALTER TABLE sub_category DROP FOREIGN KEY FK_BCE3F79812469DE2');
        $this->addSql('DROP TABLE article');
        $this->addSql('DROP TABLE article_attribut');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE devis');
        $this->addSql('DROP TABLE machine');
        $this->addSql('DROP TABLE sub_category');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE visite');
        $this->addSql('ALTER TABLE demande DROP FOREIGN KEY FK_2694D7A57294869C');
        $this->addSql('ALTER TABLE demande DROP FOREIGN KEY FK_2694D7A595A6EE59');
        $this->addSql('ALTER TABLE demande DROP FOREIGN KEY FK_2694D7A551383AF3');
        $this->addSql('DROP INDEX IDX_2694D7A57294869C ON demande');
        $this->addSql('DROP INDEX IDX_2694D7A595A6EE59 ON demande');
        $this->addSql('DROP INDEX IDX_2694D7A551383AF3 ON demande');
        $this->addSql('ALTER TABLE demande DROP destination, DROP created_at, DROP localisation, DROP article_id, DROP demandeur_id, DROP attribut_id');
    }
}
