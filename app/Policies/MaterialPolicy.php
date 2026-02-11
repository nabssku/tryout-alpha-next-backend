<?php

namespace App\Policies;

use App\Models\Material;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MaterialPolicy
{
    /**
     * User boleh melihat materi?
     */
    public function view(User $user, Material $material): bool
    {
        // ❌ materi non-aktif
        if (!$material->is_active) {
            return false;
        }

        // ✅ materi FREE
        if ($material->is_free) {
            return true;
        }

        // ✅ user punya paket aktif yg include materi
        return DB::table('package_materials as pm')
            ->join('user_packages as up', 'up.package_id', '=', 'pm.package_id')
            ->where('pm.material_id', $material->id)
            ->where('up.user_id', $user->id)
            ->where(function ($q) {
                $q->whereNull('up.ends_at')
                  ->orWhere('up.ends_at', '>', now());
            })
            ->exists();
    }
}
