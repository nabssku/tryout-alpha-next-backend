<?php

namespace App\Jobs;

use App\Models\Attempt;
use App\Services\AttemptService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireAttemptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $attemptId) {}

    public function handle(AttemptService $attempts): void
    {
        $attempt = Attempt::find($this->attemptId);
        if (!$attempt) return;

        // kalau masih in_progress dan sudah lewat ends_at, finalize jadi expired
        $attempts->ensureNotExpired($attempt);
    }
}
