<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class OrganizationRules
{
    public static function exists(string $table, bool $softDeletes = false): Exists
    {
        $rule = Rule::exists($table, 'id')->where('organization_id', OrganizationContext::id());

        if ($softDeletes) {
            $rule->whereNull('deleted_at');
        }

        return $rule;
    }

    public static function member(): Exists
    {
        return Rule::exists('organization_user', 'user_id')
            ->where('organization_id', OrganizationContext::id());
    }
}
