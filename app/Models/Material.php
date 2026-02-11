<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = [
        'type',
        'title',
        'description',
        'cover_url',
        'ebook_url',
        'is_active',
        'is_free',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_free' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parts()
    {
        return $this->hasMany(MaterialPart::class)->orderBy('sort_order');
    }

    public function packages()
    {
        return $this->belongsToMany(Package::class, 'package_materials')
            ->withPivot(['sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }
}
