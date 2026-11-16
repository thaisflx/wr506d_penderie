<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116000430 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Date de dernière connexion de l\'utilisateur : app_user.last_login_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP last_login_at');
    }
}
