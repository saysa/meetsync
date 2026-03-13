<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reservations table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reservations (
            id           VARCHAR(36)              NOT NULL,
            room_id      VARCHAR(255)             NOT NULL,
            organizer_id VARCHAR(255)             NOT NULL,
            status       VARCHAR(50)              NOT NULL,
            start_at     TIMESTAMP WITH TIME ZONE NOT NULL,
            end_at       TIMESTAMP WITH TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reservations');
    }
}
