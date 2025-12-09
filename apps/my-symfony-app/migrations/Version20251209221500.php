<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251209221500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create join table centre_kine_service';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE centre_kine_service (centre_id INT NOT NULL, service_id INT NOT NULL, INDEX IDX_CENTRE_SERVICE_CENTRE (centre_id), INDEX IDX_CENTRE_SERVICE_SERVICE (service_id), PRIMARY KEY(centre_id, service_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE centre_kine_service ADD CONSTRAINT FK_CENTRE_SERVICE_CENTRE FOREIGN KEY (centre_id) REFERENCES centre_kine (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE centre_kine_service ADD CONSTRAINT FK_CENTRE_SERVICE_SERVICE FOREIGN KEY (service_id) REFERENCES service_kine (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE centre_kine_service');
    }
}
