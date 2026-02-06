<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Package;
use App\Models\QuestionOption;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Jobs\ExpireAttemptJob;
use Illuminate\Support\Facades\Cache;

class AttemptService
{
    public function startAttempt(int $userId, Package $package): Attempt
    {
        return DB::transaction(function () use ($userId, $package) {
            $now = now();

            // (Optional) kalau mau 1 attempt aktif per package:
            // Attempt::where('user_id',$userId)->where('package_id',$package->id)
            //     ->where('status','in_progress')->update(['status'=>'expired']);

            $attempt = Attempt::create([
                'user_id' => $userId,
                'package_id' => $package->id,
                'status' => 'in_progress',
                'started_at' => $now,
                'ends_at' => $now->copy()->addSeconds($package->duration_seconds),
                'total_score' => 0,
            ]);

            ExpireAttemptJob::dispatch($attempt->id)->delay($attempt->ends_at);

            // pre-create attempt_answers sesuai urutan package_questions
            $questionIds = $package->questions()
                ->orderBy('package_questions.order_no')
                ->pluck('questions.id')
                ->toArray();

            $rows = [];
            $ts = now();
            foreach ($questionIds as $qid) {
                $rows[] = [
                    'attempt_id' => $attempt->id,
                    'question_id' => $qid,
                    'selected_option_id' => null,
                    'score_awarded' => 0,
                    'answered_at' => null,
                    'is_marked' => false,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }

            if (!empty($rows)) {
                AttemptAnswer::insert($rows);
            }

            return $attempt->fresh();
        });
    }

    public function ensureNotExpired(Attempt $attempt): Attempt
    {
        if ($attempt->status !== 'in_progress') return $attempt;

        if (now()->greaterThan($attempt->ends_at)) {
            // auto-expire + submit score
            return $this->finalizeAttempt($attempt, 'expired');
        }

        return $attempt;
    }

    public function answer(Attempt $attempt, int $questionId, int $optionId): AttemptAnswer
    {
        $this->ensureNotExpired($attempt);

        if ($attempt->status !== 'in_progress') {
            abort(422, 'Attempt sudah selesai.');
        }

        // pastikan option itu milik question yang sama
        $opt = QuestionOption::where('id', $optionId)
            ->where('question_id', $questionId)
            ->firstOrFail();

        // update row jawaban (harus sudah ada karena kita precreate)
        $ans = AttemptAnswer::where('attempt_id', $attempt->id)
            ->where('question_id', $questionId)
            ->firstOrFail();

        $ans->update([
            'selected_option_id' => $opt->id,
            'score_awarded' => (int) $opt->score_value,
            'answered_at' => now(),
        ]);

        return $ans->fresh();
    }

    public function toggleMark(Attempt $attempt, int $questionId): AttemptAnswer
    {
        $this->ensureNotExpired($attempt);

        $ans = AttemptAnswer::where('attempt_id', $attempt->id)
            ->where('question_id', $questionId)
            ->firstOrFail();

        $ans->update(['is_marked' => !$ans->is_marked]);

        return $ans->fresh();
    }

    public function submit(Attempt $attempt): Attempt
    {
        $this->ensureNotExpired($attempt);

        if ($attempt->status !== 'in_progress') {
            return $attempt;
        }

        return $this->finalizeAttempt($attempt, 'submitted');
    }

    private function finalizeAttempt(Attempt $attempt, string $finalStatus): Attempt
    {
        return DB::transaction(function () use ($attempt, $finalStatus) {
            $attempt = Attempt::lockForUpdate()->findOrFail($attempt->id);

            if ($attempt->status !== 'in_progress') {
                return $attempt;
            }

            $total = (int) AttemptAnswer::where('attempt_id', $attempt->id)->sum('score_awarded');

            $attempt->update([
                'status' => $finalStatus,
                'submitted_at' => now(),
                'total_score' => $total,
            ]);

            // invalidate cache statistik dashboard user (submitted & expired sama-sama lewat sini)
            Cache::forget("dash:stats:user:{$attempt->user_id}");
            Cache::forget("rank:user:{$attempt->user_id}:pkg:{$attempt->package_id}");
            Cache::forget("rank:pkg:{$attempt->package_id}:limit:100");
            Cache::forget("rank:pkg:{$attempt->package_id}:limit:200");
            Cache::forget("rank:pkg:{$attempt->package_id}:limit:500");
            Cache::forget("rank:pkg:{$attempt->package_id}:total_users");
            Cache::forget("rank:pkg:{$attempt->package_id}:user:{$attempt->user_id}");
            Cache::forget("rank:pkg:{$attempt->package_id}:page:1:per:50");

            return $attempt->fresh();
        });
    }


    public function remainingSeconds(Attempt $attempt): int
    {
        $now = now();
        if ($attempt->status !== 'in_progress') return 0;
        return max(0, $now->diffInSeconds($attempt->ends_at, false));
    }
}
