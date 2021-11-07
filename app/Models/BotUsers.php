<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotUsers extends Model
{
    protected $fillable = [
        'user_id',
        'lang',
        'age',
        'sex',
        'country',
        'city',
        'whom_find',
        'name'
    ];
}
