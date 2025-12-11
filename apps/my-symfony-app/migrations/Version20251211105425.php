<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251211105425 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE centre_kine ADD zone_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE centre_kine ADD CONSTRAINT FK_DDCE1F1F9F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone_kine (id)');
        $this->addSql('CREATE INDEX IDX_DDCE1F1F9F2C3FAB ON centre_kine (zone_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE centre_kine DROP FOREIGN KEY FK_DDCE1F1F9F2C3FAB');
        $this->addSql('DROP INDEX IDX_DDCE1F1F9F2C3FAB ON centre_kine');
        $this->addSql('ALTER TABLE centre_kine DROP zone_id');
    }
}
