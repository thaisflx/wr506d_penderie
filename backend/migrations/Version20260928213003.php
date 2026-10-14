<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260928213003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les colonnes temps et difficulte a la table item';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item ADD temps INT NOT NULL');
        $this->addSql('ALTER TABLE item ADD difficulte INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item DROP temps');
        $this->addSql('ALTER TABLE item DROP difficulte');
    }
}
