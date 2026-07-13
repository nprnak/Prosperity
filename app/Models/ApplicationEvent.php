<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'share_application_id',
        'actor_id',
        'from_status',
        'to_status',
        'remarks',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function application()
    {
        return $this->belongsTo(ShareApplication::class, 'share_application_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
