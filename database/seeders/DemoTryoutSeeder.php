<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Package;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Support\Facades\DB;

class DemoTryoutSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            $cat = Category::create([
                'name' => 'Demo Category',
                'parent_id' => null,
            ]);

            $package = Package::create([
                'name' => 'Demo Tryout 1',
                'type' => 'tryout',
                'category_id' => $cat->id,
                'duration_seconds' => 15 * 60, // 15 menit
                'is_active' => true,
            ]);

            // buat 3 soal
            for ($i = 1; $i <= 3; $i++) {
                $q = Question::create([
                    'category_id' => $cat->id,
                    'question' => "Soal demo nomor {$i}: 2 + 2 = ?",
                    'explanation' => "Penjelasan demo soal {$i}",
                ]);

                // opsi A-D nilai beda
                $opts = [
                    ['A', '3', 0],
                    ['B', '4', 5],
                    ['C', '5', 1],
                    ['D', '6', -1],
                    ['E', '7', 2],
                ];

                foreach ($opts as [$label, $text, $score]) {
                    QuestionOption::create([
                        'question_id' => $q->id,
                        'label' => $label,
                        'text' => $text,
                        'score_value' => $score,
                    ]);
                }
                
                // attach ke package_questions dengan order
                DB::table('package_questions')->insert([
                    'package_id' => $package->id,
                    'question_id' => $q->id,
                    'order_no' => $i,
                ]);
            }
        });
    }
}
