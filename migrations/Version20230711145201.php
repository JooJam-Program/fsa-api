<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20230711145201 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'adding batchId to sort uploaded files accordingly';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_object ADD batch_id VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_object DROP batch_id');
    }
}
