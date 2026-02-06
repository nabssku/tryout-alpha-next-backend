<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    // GET /api/packages/{package}/ranking?page=1&per_page=50
    public function perPackage(Request $request, Package $package)
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 50);

        $page = max(1, $page);
        $perPage = max(1, min($perPage, 100));

        $offset = ($page - 1) * $perPage;

        // Ranking hanya meaningful untuk tryout / akbar
        if (!in_array($package->type, ['tryout', 'akbar'])) {
            return response()->json([
                'success' => true,
                'data' => [
                    'package_id' => $package->id,
                    'package_name' => $package->name,
                    'items' => [],
                    'meta' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => 0,
                        'total_pages' => 0,
                    ],
                    'my_rank' => null,
                    'my_score' => null,
                    'my_submitted_at' => null,
                    'my_in_top' => false,
                    'message' => 'Ranking hanya tersedia untuk Tryout dan Akbar',
                ],
            ]);
        }

        // =========================
        // Total distinct users (cached)
        // =========================
        $totalKey = "rank:pkg:{$package->id}:total_users";
        $totalTtl = 60;

        $totalUsers = (int) Cache::remember($totalKey, $totalTtl, function () use ($package) {
            $row = DB::selectOne("
                SELECT COUNT(DISTINCT user_id) AS total
                FROM attempts
                WHERE package_id = ?
                  AND status IN ('submitted','expired')
                  AND submitted_at IS NOT NULL
            ", [$package->id]);

            return (int) ($row->total ?? 0);
        });

        $totalPages = $perPage > 0 ? (int) ceil($totalUsers / $perPage) : 0;

        // =========================
        // Page items (cached)
        // =========================
        $itemsKey = "rank:pkg:{$package->id}:page:{$page}:per:{$perPage}";
        $itemsTtl = 60;

        $items = Cache::remember($itemsKey, $itemsTtl, function () use ($package, $perPage, $offset) {
            $rows = DB::select("
                WITH best_attempts AS (
                    SELECT
                        a.user_id,
                        a.total_score,
                        a.submitted_at,
                        ROW_NUMBER() OVER (
                            PARTITION BY a.user_id
                            ORDER BY a.total_score DESC, a.submitted_at ASC, a.id ASC
                        ) AS rn
                    FROM attempts a
                    WHERE a.package_id = ?
                      AND a.status IN ('submitted','expired')
                      AND a.submitted_at IS NOT NULL
                )
                SELECT
                    b.user_id,
                    u.name AS user_name,
                    b.total_score,
                    b.submitted_at
                FROM best_attempts b
                JOIN users u ON u.id = b.user_id
                WHERE b.rn = 1
                ORDER BY b.total_score DESC, b.submitted_at ASC
                LIMIT ? OFFSET ?
            ", [$package->id, $perPage, $offset]);

            return collect($rows)->values()->map(function ($r, $i) use ($offset) {
                return [
                    'rank' => $offset + $i + 1,
                    'user' => [
                        'id' => (int) $r->user_id,
                        'name' => $r->user_name,
                    ],
                    'score' => (int) $r->total_score,
                    'submitted_at' => $r->submitted_at,
                ];
            })->values();
        });

        // =========================
        // My rank (global) (cached 30s)
        // =========================
        $myRank = null;
        $myScore = null;
        $mySubmittedAt = null;
        $inTop = null;

        if ($request->user()) {
            $uid = (int) $request->user()->id;

            // cek apakah user ada di page items ini
            $mine = $items->firstWhere('user.id', $uid);
            if ($mine) {
                $myRank = (int) $mine['rank'];
                $myScore = (int) $mine['score'];
                $mySubmittedAt = $mine['submitted_at'] ?? null;
                $inTop = true;
            } else {
                $my = Cache::remember("rank:pkg:{$package->id}:user:{$uid}", 30, function () use ($uid, $package) {
                    return $this->getMyRankForPackage($uid, (int) $package->id);
                });

                if ($my) {
                    $myRank = (int) $my['rank'];
                    $myScore = (int) $my['score'];
                    $mySubmittedAt = $my['submitted_at'] ?? null;
                    $inTop = false;
                } else {
                    $myRank = null;
                    $inTop = false;
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'items' => $items,
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $totalUsers,
                    'total_pages' => $totalPages,
                ],
                'my_rank' => $myRank,
                'my_score' => $myScore,
                'my_submitted_at' => $mySubmittedAt,
                'my_in_top' => $inTop,
            ],
        ]);
    }

    /**
     * Hitung rank user untuk package, walau di luar page/per_page.
     * - best attempt per user
     * - tie-breaker: submitted_at lebih cepat menang
     */
    private function getMyRankForPackage(int $userId, int $packageId): ?array
    {
        $myBest = DB::selectOne("
            WITH best AS (
                SELECT
                    a.user_id,
                    a.total_score,
                    a.submitted_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY a.user_id
                        ORDER BY a.total_score DESC, a.submitted_at ASC, a.id ASC
                    ) AS rn
                FROM attempts a
                WHERE a.package_id = ?
                  AND a.status IN ('submitted','expired')
                  AND a.submitted_at IS NOT NULL
            )
            SELECT total_score, submitted_at
            FROM best
            WHERE rn = 1 AND user_id = ?
            LIMIT 1
        ", [$packageId, $userId]);

        if (!$myBest) return null;

        $better = DB::selectOne("
            WITH best AS (
                SELECT
                    a.user_id,
                    a.total_score,
                    a.submitted_at,
                    ROW_NUMBER() OVER (
                        PARTITION BY a.user_id
                        ORDER BY a.total_score DESC, a.submitted_at ASC, a.id ASC
                    ) AS rn
                FROM attempts a
                WHERE a.package_id = ?
                  AND a.status IN ('submitted','expired')
                  AND a.submitted_at IS NOT NULL
            )
            SELECT COUNT(*) AS cnt
            FROM best
            WHERE rn = 1
              AND (
                total_score > ?
                OR (total_score = ? AND submitted_at < ?)
              )
        ", [$packageId, $myBest->total_score, $myBest->total_score, $myBest->submitted_at]);

        return [
            'rank' => 1 + (int) ($better->cnt ?? 0),
            'score' => (int) $myBest->total_score,
            'submitted_at' => $myBest->submitted_at,
        ];
    }
}
