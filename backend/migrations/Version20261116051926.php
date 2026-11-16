<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261116051926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clé de traduction des catégories proposées par l\'application : category.label_key';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category ADD label_key VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE category DROP label_key');
    }
}
