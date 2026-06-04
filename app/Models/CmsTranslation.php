<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Admin-managed translation overrides for the public app's i18n keys.
 *
 * The bundled i18n catalogue (in /src/i18n/*.json) provides defaults; this collection
 * holds per-language overrides for any key the admin decides to customize. Stored as
 * a single document keyed by translation key for fast `db.cms_translations.findOne({key:...})`.
 */
class CmsTranslation extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'cms_translations';

    protected $guarded = [];

    protected $casts = [
        'values' => 'array', // { en: "Hello", de: "Hallo", ku: "Silav", ar: "..." }
    ];
}
