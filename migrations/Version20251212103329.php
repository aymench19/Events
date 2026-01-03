<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251212103329 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE login_attempts (id INT AUTO_INCREMENT NOT NULL, failed_attempts INT DEFAULT 0 NOT NULL, locked_until DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_9163C7FBA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE payments (id INT AUTO_INCREMENT NOT NULL, payment_id VARCHAR(36) NOT NULL, amount NUMERIC(10, 2) NOT NULL, currency VARCHAR(3) NOT NULL, status VARCHAR(50) NOT NULL, paypal_transaction_id VARCHAR(255) DEFAULT NULL, card_brand VARCHAR(50) DEFAULT NULL, card_last_four VARCHAR(4) DEFAULT NULL, created_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, error_message LONGTEXT DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_65D29B324C3A3BB (payment_id), INDEX IDX_65D29B32A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE tickets (id INT AUTO_INCREMENT NOT NULL, ticket_key VARCHAR(36) NOT NULL, event_name VARCHAR(255) DEFAULT NULL, ticket_type VARCHAR(50) NOT NULL, price NUMERIC(10, 2) NOT NULL, issued_at DATETIME NOT NULL, expires_at DATETIME DEFAULT NULL, status VARCHAR(50) NOT NULL, quantity INT DEFAULT 1 NOT NULL, qr_code LONGTEXT DEFAULT NULL, user_id INT NOT NULL, payment_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_54469DF489E97085 (ticket_key), INDEX IDX_54469DF4A76ED395 (user_id), UNIQUE INDEX UNIQ_54469DF44C3A3BB (payment_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE login_attempts ADD CONSTRAINT FK_9163C7FBA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_65D29B32A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE tickets ADD CONSTRAINT FK_54469DF4A76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE tickets ADD CONSTRAINT FK_54469DF44C3A3BB FOREIGN KEY (payment_id) REFERENCES payments (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE login_attempts DROP FOREIGN KEY FK_9163C7FBA76ED395');
        $this->addSql('ALTER TABLE payments DROP FOREIGN KEY FK_65D29B32A76ED395');
        $this->addSql('ALTER TABLE tickets DROP FOREIGN KEY FK_54469DF4A76ED395');
        $this->addSql('ALTER TABLE tickets DROP FOREIGN KEY FK_54469DF44C3A3BB');
        $this->addSql('DROP TABLE login_attempts');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE tickets');
        $this->addSql('DROP TABLE users');
    }
}
