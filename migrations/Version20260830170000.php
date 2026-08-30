<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260830170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store cl100k_base token counts for corpus works.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bard_work ADD token_count INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bard_work DROP token_count');
    }
}
