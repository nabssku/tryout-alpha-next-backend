<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Package;
use Illuminate\Support\Facades\DB;

class AdminPackageQuestionController extends Controller
{
     // GET list soal di package + order
    public function index(Package $package)
    {
        $rows = DB::table('package_questions')
            ->join('questions', 'questions.id', '=', 'package_questions.question_id')
            ->where('package_questions.package_id', $package->id)
            ->orderBy('package_questions.order_no')
            ->get([
                'package_questions.question_id',
                'package_questions.order_no',
                'questions.question',
            ]);

        return response()->json(['success' => true, 'data' => $rows]);
    }

    // PUT sync soal + order (replace semua)
    public function sync(Request $request, Package $package)
    {
        $data = $request->validate([
            'items' => ['required','array','min:1'],
            'items.*.question_id' => ['required','integer','exists:questions,id'],
            'items.*.order_no' => ['required','integer','min:1'],
        ]);

        return DB::transaction(function () use ($package, $data) {
            DB::table('package_questions')->where('package_id', $package->id)->delete();

            // insert ulang
            $insert = [];
            foreach ($data['items'] as $it) {
                $insert[] = [
                    'package_id' => $package->id,
                    'question_id' => $it['question_id'],
                    'order_no' => $it['order_no'],
                ];
            }
            DB::table('package_questions')->insert($insert);

            return response()->json(['success' => true, 'message' => 'Synced']);
        });
    }
}
