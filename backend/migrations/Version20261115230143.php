<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261115230143 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Langue de l\'utilisateur (fr par défaut, en, de) : app_user.locale';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD locale VARCHAR(2) DEFAULT \'fr\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP locale');
    }
}
