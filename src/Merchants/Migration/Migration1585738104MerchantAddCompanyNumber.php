<?php

declare(strict_types = 1);

namespace Shopware\Production\Merchants\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1585738104MerchantAddCompanyNumber extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1585738104;
    }

    public function update(Connection $connection): void
    {
        $connection->executeQuery('ALTER TABLE `merchant`
ADD `public_company_number` varchar(255) COLLATE \'utf8mb4_unicode_ci\' NULL;');
        $connection->executeQuery('UPDATE merchant SET public_company_number = "fnXXXX"');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

}
