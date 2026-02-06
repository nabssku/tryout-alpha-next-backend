<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionBulkController extends Controller
{
    public function store(Request $request)
    {
        // MAX ITEMS BULK CREATE
        $maxItems = 200;

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:' . $maxItems],

            'items.*.category_id' => ['required', 'integer', 'exists:categories,id'],
            'items.*.question' => ['required', 'string'],
            'items.*.explanation' => ['nullable', 'string'],

            'items.*.options' => ['required', 'array', 'min:2', 'max:10'],
            'items.*.options.*.label' => ['required', 'string', 'max:5'],
            'items.*.options.*.text' => ['required', 'string'],
            'items.*.options.*.score_value' => ['required', 'integer'],
        ]);

        // extra validation: label harus unik per question item
        foreach ($data['items'] as $idx => $item) {
            $labels = array_map(fn($o) => strtoupper(trim($o['label'])), $item['options']);
            if (count($labels) !== count(array_unique($labels))) {
                throw ValidationException::withMessages([
                    "items.$idx.options" => ["Label option harus unik per soal (contoh: A,B,C,D)."],
                ]);
            }
        }

        $result = DB::transaction(function () use ($data) {
            $created = [];

            foreach ($data['items'] as $i => $item) {
                /** @var \App\Models\Question $q */
                $q = Question::create([
                    'category_id' => $item['category_id'],
                    'question' => $item['question'],
                    'explanation' => $item['explanation'] ?? null,
                ]);

                $opts = [];
                $ts = now();
                foreach ($item['options'] as $opt) {
                    $opts[] = [
                        'question_id' => $q->id,
                        'label' => strtoupper(trim($opt['label'])),
                        'text' => $opt['text'],
                        'score_value' => (int) $opt['score_value'],
                        'created_at' => $ts,
                        'updated_at' => $ts,
                    ];
                }

                QuestionOption::insert($opts);

                $created[] = [
                    'index' => $i,
                    'question_id' => $q->id,
                ];
            }

            return $created;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'created_count' => count($result),
                'created' => $result,
            ],
        ], 201);
    }
}
