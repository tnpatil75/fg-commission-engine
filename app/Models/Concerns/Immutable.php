<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Append-only records: once inserted they cannot be updated or deleted through Eloquent.
 * Foreign keys (ON DELETE RESTRICT) additionally stop referenced rows from being deleted in the database.
 */
trait Immutable
{
    protected static function bootImmutable(): void
    {
        static::updating(function ($model) {
            throw new LogicException(class_basename($model).' records are immutable and cannot be changed.');
        });

        static::deleting(function ($model) {
            throw new LogicException(class_basename($model).' records are immutable and cannot be deleted.');
        });
    }
}
