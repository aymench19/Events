<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251206PaymentTicket extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create payments and tickets tables';
    }

    public function up(Schema $schema): void
    {
        // Create payments table
        $this->addSql('CREATE TABLE payments (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            payment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            amount NUMERIC(10, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT \'USD\',
            status VARCHAR(50) NOT NULL DEFAULT \'PENDING\',
            paypal_transaction_id VARCHAR(255),
            card_brand VARCHAR(50),
            card_last_four VARCHAR(4),
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            completed_at DATETIME COMMENT \'(DC2Type:datetime_immutable)\',
            error_message LONGTEXT,
            UNIQUE INDEX UNIQ_PAYMENT_ID (payment_id),
            INDEX IDX_PAYMENTS_USER (user_id),
            PRIMARY KEY(id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        // Create tickets table
        $this->addSql('CREATE TABLE tickets (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            payment_id INT NOT NULL,
            ticket_key CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\',
            event_name VARCHAR(255),
            ticket_type VARCHAR(50) NOT NULL DEFAULT \'GENERAL\',
            price NUMERIC(10, 2) NOT NULL,
            issued_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            expires_at DATETIME COMMENT \'(DC2Type:datetime_immutable)\',
            status VARCHAR(50) NOT NULL DEFAULT \'ACTIVE\',
            qr_code LONGTEXT,
            UNIQUE INDEX UNIQ_TICKET_KEY (ticket_key),
            UNIQUE INDEX UNIQ_PAYMENT (payment_id),
            INDEX IDX_TICKETS_USER (user_id),
            PRIMARY KEY(id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tickets');
        $this->addSql('DROP TABLE IF EXISTS payments');
    }
}
