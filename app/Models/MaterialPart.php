<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialPart extends Model
{
    protected $fillable = [
        'material_id',
        'title',
        'video_url',
        'duration_seconds',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'duration_seconds' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function material()
    {
        return $this->belongsTo(Material::class);
    }
}
