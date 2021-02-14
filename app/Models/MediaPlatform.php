<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MediaPlatform extends Model
{
    use HasFactory;

    public function getUserPlatformRelation(){
        return $this->hasOne('App\Models\UserPlatform', 'media_platform_id', 'id');
    }
}
