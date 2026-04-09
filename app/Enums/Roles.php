<?php

namespace App\Enums;

enum Roles: string
{
    case SUPER_ADMIN = 'super_admin';
    case TENANT_ADMIN = 'tenant_admin';
    case STORE_MANAGER = 'store_manager';
    case CASHIER = 'cashier';
    case STOCK_CLERK = 'stock_clerk';
    case ACCOUNTANT = 'accountant';
}
