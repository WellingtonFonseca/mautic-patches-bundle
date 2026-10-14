<?php

declare(strict_types=1);

namespace MauticPlugin\MauticPatchesBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

/**
 * The campaign prefix table (0.5.0) for a plugin that was installed before it existed. Mautic 7 runs the plugin's
 * migrations on `mautic:plugins:reload` and does not call MauticPatchesBundle::onPluginUpdate. A fresh install already
 * has the table (created from the entity), so this does nothing there.
 */
class Version_0_5_0 extends AbstractMigration
{
    private string $table = 'campaign_prefix';

    protected function isApplicable(Schema $schema): bool
    {
        return !$schema->hasTable($this->concatPrefix($this->table));
    }

    protected function up(): void
    {
        $table = $this->concatPrefix($this->table);

        $this->addSql("CREATE TABLE `{$table}` (campaign_id INT NOT NULL, prefix VARCHAR(191) NOT NULL, INDEX campaign_prefix_prefix (prefix), PRIMARY KEY(campaign_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }
}
