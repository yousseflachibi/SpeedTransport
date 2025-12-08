<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251208152213 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_kine ADD categorie_id INT NOT NULL');
        $this->addSql('ALTER TABLE service_kine ADD CONSTRAINT FK_828038A3BCF5E72D FOREIGN KEY (categorie_id) REFERENCES categorie_service_kine (id)');
        $this->addSql('CREATE INDEX IDX_828038A3BCF5E72D ON service_kine (categorie_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE service_kine DROP FOREIGN KEY FK_828038A3BCF5E72D');
        $this->addSql('DROP INDEX IDX_828038A3BCF5E72D ON service_kine');
        $this->addSql('ALTER TABLE service_kine DROP categorie_id');
    }
}
