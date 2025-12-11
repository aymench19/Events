<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add quantity field to tickets table
 */
final class Version20251220AddTicketQuantity extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add quantity field to tickets table for inventory management';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tickets ADD quantity INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tickets DROP COLUMN quantity');
    }
}
