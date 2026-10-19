<?php

namespace App\Support;

final class RecordReference
{
    /**
     * Models other than invoices have no sequential number.
     * The suffix is the last six characters of the id, so the same record always shows the same code.
     */
    public static function fromId(string $prefix, string $id): string
    {
        return $prefix.'-'.strtoupper(substr($id, -6));
    }
}
