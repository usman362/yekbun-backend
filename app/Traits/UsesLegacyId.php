<?php

namespace App\Traits;

/**
 * Preserves `_id` key in JSON serialization instead of converting to `id`.
 *
 * New mongodb/laravel-mongodb package outputs `id` by default, but the
 * old jenssegers/mongodb output `_id`. Mobile apps and clients expect `_id`,
 * so this trait restores that behavior.
 *
 * Usage: `use UsesLegacyId;` inside any MongoDB model.
 */
trait UsesLegacyId
{
    public function toArray()
    {
        $array = parent::toArray();

        if (array_key_exists('id', $array) && !array_key_exists('_id', $array)) {
            $array = ['_id' => $array['id']] + $array;
            unset($array['id']);
        }

        return $array;
    }
}
