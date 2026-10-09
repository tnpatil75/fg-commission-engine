<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base for the small master tables (segments, regions, companies, sale types): a stable code and a label.
 */
abstract class MasterRecord extends Model
{
    protected $fillable = ['code', 'name'];

    /** @return array{code: string, name: string} */
    public function snapshot(): array
    {
        return ['code' => $this->code, 'name' => $this->name];
    }
}
