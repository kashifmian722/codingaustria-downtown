<?php declare(strict_types=1);

namespace Shopware\Production\Merchants\Content\Merchant\Exception;

use Shopware\Core\Framework\ShopwareHttpException;

class CompanyDoesNotExistException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'MERCHANT_COMPANY_DOES_NOT_EXIST';
    }
}
