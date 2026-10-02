<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Expresiones SQL que difieren entre MySQL (producción) y SQLite (local/tests).
 */
class Sql
{
    public static function hourBucket(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d %H:00', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m-%d %H:00')";
    }

    public static function dayBucket(string $column): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "strftime('%Y-%m-%d', {$column})"
            : "DATE_FORMAT({$column}, '%Y-%m-%d')";
    }
}
