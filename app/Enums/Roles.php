<?php

namespace App\Enums;

enum Roles: string
{
    case SUPER_ADMIN = 'Super Admin';
    case TENANT_ADMIN = 'Tenant Admin';
    case STORE_MANAGER = 'Store Manager';
    case CASHIER = 'Cashier';
    case STOCK_CLERK = 'Stock Clerk';
    case ACCOUNTANT = 'Accountant';
}
