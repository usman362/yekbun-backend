<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

/**
 * Editable copy for a public-app page (yekbun.app).
 *
 * One row per page (`id` field — slug like "landing", "login", "dashboard"). The
 * `fields` column is an array of `{key, label, value}` entries the admin edits in
 * the WebApp CMS. The public side reads them by key.
 */
class CmsPage extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $table = 'cms_pages';

    protected $guarded = [];

    protected $casts = [
        'fields' => 'array',
    ];
}
