<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['category_id', 'question', 'explanation'];

    public function options()
    {
        return $this->hasMany(QuestionOption::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
