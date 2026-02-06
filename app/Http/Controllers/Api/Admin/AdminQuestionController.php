<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Question;

class AdminQuestionController extends Controller
{
    public function index(Request $request)
    {
        $q = Question::query()
            ->with('category:id,name')
            ->withCount('options')
            ->when($request->category_id, fn($qq) => $qq->where('category_id', $request->category_id))
            ->when($request->search, fn($qq) => $qq->where('question', 'like', "%{$request->search}%"))
            ->orderBy('id','desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required','integer','exists:categories,id'],
            'question' => ['required','string'],
            'explanation' => ['nullable','string'],
        ]);

        $q = Question::create($data);

        return response()->json(['success' => true, 'data' => $q], 201);
    }

    public function show(Question $question)
    {
        $question->load('category:id,name', 'options:id,question_id,label,text,score_value');

        return response()->json(['success' => true, 'data' => $question]);
    }

    public function update(Request $request, Question $question)
    {
        $data = $request->validate([
            'category_id' => ['sometimes','required','integer','exists:categories,id'],
            'question' => ['sometimes','required','string'],
            'explanation' => ['nullable','string'],
        ]);

        $question->update($data);

        return response()->json(['success' => true, 'data' => $question->fresh()]);
    }

    public function destroy(Question $question)
    {
        $question->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}
