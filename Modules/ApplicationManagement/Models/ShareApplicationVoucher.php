<?php

namespace Modules\ApplicationManagement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One bank deposit backing a share application: the slip and the transaction
 * code that identifies it, plus which bank it went to and for how much.
 */
class ShareApplicationVoucher extends Model
{
    protected $fillable = [
        'share_application_id', 'payment_type', 'deposited_bank', 'transaction_code', 'asba_reference', 'image_path', 'amount', 'payment_date',
    ];

    // The path is a private-disk location; the page links to a gated route instead.
    protected $hidden = ['image_path'];

    protected $appends = ['has_image'];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date:Y-m-d',
    ];

    public function application()
    {
        return $this->belongsTo(ShareApplication::class, 'share_application_id');
    }

    public function getHasImageAttribute(): bool
    {
        return $this->image_path !== null;
    }

    /** Removes the stored slip, used when a row is replaced or dropped. */
    public function deleteImage(): void
    {
        if ($this->image_path) {
            Storage::disk('private')->delete($this->image_path);
        }
    }
}
