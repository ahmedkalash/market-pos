<?php

namespace App\Enums;

enum Roles: string
{
    case SUPER_ADMIN = 'super_admin';
    case COMPANY_ADMIN = 'company_admin';
    case STORE_MANAGER = 'store_manager';
    case CASHIER = 'cashier';
    case STOCK_CLERK = 'stock_clerk';
    case ACCOUNTANT = 'accountant';
}
