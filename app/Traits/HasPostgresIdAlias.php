<?php

namespace App\Traits;

trait HasPostgresIdAlias
{
    /**
     * Accessor virtual agar $model->_id tetap mengembalikan $model->id (kompatibilitas).
     */
    public function get_IdAttribute()
    {
        return $this->attributes['id'] ?? $this->id ?? null;
    }
}
