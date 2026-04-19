<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260419165349 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Passwordless auth: drop users.password, replace users.email with users.email_hash (HMAC-SHA256 hex, 64 chars).';
    }

    public function up(Schema $schema): void
    {
        // Existing accounts reference email/password that are being dropped and
        // cannot be rehashed from scratch — the HMAC requires the cleartext
        // email which we no longer have. Users must re-login via magic link,
        // which will recreate the account transparently. Pantry items cascade.
        $this->addSql('TRUNCATE TABLE users RESTART IDENTITY CASCADE');
        $this->addSql('DROP INDEX uniq_users_email');
        $this->addSql('ALTER TABLE users ADD email_hash VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE users DROP email');
        $this->addSql('ALTER TABLE users DROP password');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_users_email_hash ON users (email_hash)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_users_email_hash');
        $this->addSql('ALTER TABLE users ADD email VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE users ADD password VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE users DROP email_hash');
        $this->addSql('CREATE UNIQUE INDEX uniq_users_email ON users (email)');
    }
}
