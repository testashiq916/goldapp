<?php

namespace Tests\Unit;

use App\Enums\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermissionMatchTest extends TestCase
{
    #[DataProvider('billingPathProvider')]
    public function test_it_matches_billing_permissions_for_screen_and_api_routes(string $path, string $expectedPermission): void
    {
        $this->assertSame($expectedPermission, Permission::matchPermissionForPath($path));
    }

    public static function billingPathProvider(): array
    {
        return [
            'sales bill screen' => ['sales-bill', 'MDI_SALES_BILL'],
            'sales bill api' => ['api/sales-bill/save', 'MDI_SALES_BILL'],
            'purchase bill api' => ['api/purchase-bill/save', 'MDI_PURCHASE_BILL'],
            'sales return api' => ['api/sales-return/save', 'MDI_SALES_RETURN'],
            'purchase return api' => ['api/purchase-return/save', 'MDI_PURCHASE_RETURN'],
            'diamond purchase return api' => ['api/diamond-purchase-return/save', 'MDI_DIAMOND_PURCHASE_RETURN'],
            'order bill api' => ['api/order-bill/save', 'MDI_ORDER_BILL'],
        ];
    }
}
