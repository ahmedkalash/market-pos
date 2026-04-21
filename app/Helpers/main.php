<?php

if (! function_exists('is_standard_role')) {
    function is_standard_role(string $role): bool
    {
        return array_key_exists($role, config('company_standard_roles', [])['roles']);
    }
}
