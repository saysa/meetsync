<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260313100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create rooms table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE rooms (
            id           VARCHAR(255) NOT NULL,
            capacity     INTEGER      NOT NULL,
            opening_time TIME         NOT NULL,
            closing_time TIME         NOT NULL,
            PRIMARY KEY (id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE rooms');
    }
}
