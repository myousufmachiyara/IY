<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    protected $fillable = [
        'vehicle_id', 'type', 'title', 'file_path',
        'is_final_clearance', 'visible_to_customer', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_final_clearance'  => 'boolean',
            'visible_to_customer' => 'boolean',
        ];
    }

    public function vehicle(): BelongsTo  { return $this->belongsTo(Vehicle::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}