<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\QuestionOption;
use App\Models\Question;

class AdminQuestionOptionController extends Controller
{
     public function store(Request $request, Question $question)
    {
        $data = $request->validate([
            'label' => ['required','string','max:5'],
            'text' => ['required','string'],
            'score_value' => ['required','integer'],
        ]);

        $opt = $question->options()->create($data);

        return response()->json(['success' => true, 'data' => $opt], 201);
    }

    public function update(Request $request, QuestionOption $option)
    {
        $data = $request->validate([
            'label' => ['sometimes','required','string','max:5'],
            'text' => ['sometimes','required','string'],
            'score_value' => ['sometimes','required','integer'],
        ]);

        $option->update($data);

        return response()->json(['success' => true, 'data' => $option->fresh()]);
    }

    public function destroy(QuestionOption $option)
    {
        $option->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}
