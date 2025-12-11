<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251210MakeTicketPaymentNullable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make tickets.payment_id nullable to allow creating tickets before payment';
    }

    public function up(Schema $schema): void
    {
        // Allow payment_id to be NULL so tickets can be created prior to a payment record.
        $this->addSql('ALTER TABLE tickets CHANGE payment_id payment_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Revert to NOT NULL (may fail if NULL values exist) — keep as cautious revert.
        $this->addSql('ALTER TABLE tickets CHANGE payment_id payment_id INT NOT NULL');
    }
}
