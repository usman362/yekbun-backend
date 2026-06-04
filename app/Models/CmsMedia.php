<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Admin-managed media slot for the public app.
 *
 * Each row is a named "slot" (e.g. `landing.hero`, `global.logo`) that the public
 * app's media-resolution hook fetches by slot key. Admin uploads replace the slot's
 * URL — the public app immediately picks up the new image on next fetch.
 */
class CmsMedia extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'cms_media';

    protected $guarded = [];
}
