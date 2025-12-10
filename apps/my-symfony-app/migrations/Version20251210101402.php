<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251210101402 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE demande_kine (id INT AUTO_INCREMENT NOT NULL, id_compte INT DEFAULT NULL, date_demande DATETIME NOT NULL, status INT NOT NULL, motif_kine LONGTEXT DEFAULT NULL, nombre_seance INT NOT NULL, adresse_rejete LONGTEXT DEFAULT NULL, traite_par_notre_cote INT DEFAULT NULL, id_ville INT DEFAULT NULL, id_zone INT DEFAULT NULL, nom_prenom VARCHAR(255) DEFAULT NULL, fonction VARCHAR(255) DEFAULT NULL, numero_tele VARCHAR(20) DEFAULT NULL, numero_tele_wtp VARCHAR(20) DEFAULT NULL, cin VARCHAR(50) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE centre_kine_service DROP FOREIGN KEY FK_CENTRE_SERVICE_SERVICE');
        $this->addSql('ALTER TABLE centre_kine_service DROP FOREIGN KEY FK_CENTRE_SERVICE_CENTRE');
        $this->addSql('ALTER TABLE centre_kine_service ADD CONSTRAINT FK_FB5F7023463CD7C3 FOREIGN KEY (centre_id) REFERENCES centre_kine (id)');
        $this->addSql('ALTER TABLE centre_kine_service ADD CONSTRAINT FK_FB5F7023ED5CA9E6 FOREIGN KEY (service_id) REFERENCES service_kine (id)');
        $this->addSql('ALTER TABLE centre_kine_service RENAME INDEX idx_centre_service_centre TO IDX_FB5F7023463CD7C3');
        $this->addSql('ALTER TABLE centre_kine_service RENAME INDEX idx_centre_service_service TO IDX_FB5F7023ED5CA9E6');
        $this->addSql('ALTER TABLE centre_kine_image RENAME INDEX idx_centre_image_centre TO IDX_4CF66788463CD7C3');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE demande_kine');
        $this->addSql('ALTER TABLE centre_kine_image RENAME INDEX idx_4cf66788463cd7c3 TO IDX_CENTRE_IMAGE_CENTRE');
        $this->addSql('ALTER TABLE centre_kine_service DROP FOREIGN KEY FK_FB5F7023463CD7C3');
        $this->addSql('ALTER TABLE centre_kine_service DROP FOREIGN KEY FK_FB5F7023ED5CA9E6');
        $this->addSql('ALTER TABLE centre_kine_service ADD CONSTRAINT FK_CENTRE_SERVICE_SERVICE FOREIGN KEY (service_id) REFERENCES service_kine (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE centre_kine_service ADD CONSTRAINT FK_CENTRE_SERVICE_CENTRE FOREIGN KEY (centre_id) REFERENCES centre_kine (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE centre_kine_service RENAME INDEX idx_fb5f7023ed5ca9e6 TO IDX_CENTRE_SERVICE_SERVICE');
        $this->addSql('ALTER TABLE centre_kine_service RENAME INDEX idx_fb5f7023463cd7c3 TO IDX_CENTRE_SERVICE_CENTRE');
    }
}
