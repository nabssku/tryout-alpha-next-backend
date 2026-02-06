<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttemptAnswer extends Model
{
    protected $fillable = [
        'attempt_id','question_id','selected_option_id',
        'score_awarded','answered_at','is_marked',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'is_marked' => 'boolean',
    ];

    public function attempt()
    {
        return $this->belongsTo(Attempt::class);
    }

    public function question()
    {
        return $this->belongsTo(Question::class);
    }
    
}
