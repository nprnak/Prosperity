<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NumberingSequence extends Model
{
    protected $fillable = ['type', 'scope', 'current_value'];
}
