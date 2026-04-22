<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use App\Traits\UsesLegacyId;

class LanguageKeyword extends Model
{
    use UsesLegacyId;
    protected $connection = 'mongodb';
    protected $collection = 'language_keywords';

    protected $fillable = [
        'language_id', 'alert', 'upgrade', 'premium', 'vip', 'monthly', 'feeds',
        'text_comments', 'music_player', 'video_playlist', 'discount',
        'stories', 'voice_comments', 'live_stream', 'fanpage', 'gift_free',
        'show_me_the_gift', 'congratulations_educated', 'congratulations_academic',
        'premium_description', 'go_back_home', 'your_activation_code_mail',
        'your_password_code_mail', 'your_fanpage_activation_code', 'one_time_code',
        'follow_steps_on_your_device', 'welcome',
    ];

    public function language() { return $this->belongsTo(Language::class); }
}
