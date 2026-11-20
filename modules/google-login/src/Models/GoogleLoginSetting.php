<?php

namespace Modules\GoogleLogin\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleLoginSetting extends Model
{
    protected $table = 'google_login_settings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'allow_registration' => 'boolean',
        ];
    }
}
