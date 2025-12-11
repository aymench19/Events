<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251211MakePaymentIdNullable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make tickets.payment_id nullable to allow creating tickets before payment';
    }

    public function up(Schema $schema): void
    {
        // Drop existing foreign key and constraint, then recreate with nullable
        $this->addSql('ALTER TABLE tickets DROP FOREIGN KEY `tickets_ibfk_2`');
        $this->addSql('ALTER TABLE tickets MODIFY payment_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tickets ADD CONSTRAINT FK_54469DF44C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id)');
    }

    public function down(Schema $schema): void
    {
        // Revert to NOT NULL
        $this->addSql('ALTER TABLE tickets DROP FOREIGN KEY FK_54469DF44C3A3BB');
        $this->addSql('ALTER TABLE tickets MODIFY payment_id INT NOT NULL');
        $this->addSql('ALTER TABLE tickets ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (payment_id) REFERENCES payments (id)');
    }
}
