<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Certificate;
use App\Models\Activity;
use App\Models\Company; // Assuming Company model exists based on permissions
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics based on user permissions.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $stats = [];

        // 1. Users Count (System Management)
        if ($user->can('users.read')) {
            $stats['total_users'] = User::count();
            $stats['new_users_last_month'] = User::where('created_at', '>=', now()->subMonth())->count();
        }

        // 2. Certificates Count (Certificates Management)
        if ($user->can('certificates.read')) {
            $stats['total_certificates'] = Certificate::count();
            $stats['certificates_issued_last_month'] = Certificate::where('created_at', '>=', now()->subMonth())->count();
        }

        // 3. Activities Count (Business Management)
        if ($user->can('activities.read')) {
            $stats['total_activities'] = Activity::count();
            $stats['active_activities'] = Activity::where('is_active', true)->count();
        }

        // 4. Companies Count (System Management)
        // Assuming Company model exists, if not we skip or use a placeholder if needed.
        // Based on permissions 'companies.read', it likely exists.
        if ($user->can('companies.read') && class_exists(\App\Models\Company::class)) {
             $stats['total_companies'] = \App\Models\Company::count();
        }

        // 5. My Certificates (For everyone, especially End Users)
        // Count certificates where the user is the recipient (user_id)
        $stats['my_certificates_count'] = Certificate::where('user_id', $user->id)->count();
        
        // 6. My Recent Certificates (Limit 5)
        $stats['my_recent_certificates'] = Certificate::with(['activity'])
            ->where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($cert) {
                return [
                    'id' => $cert->id,
                    'code' => $cert->code,
                    'activity_name' => $cert->activity ? $cert->activity->name : 'N/A',
                    'issue_date' => $cert->created_at->format('Y-m-d'),
                    'file_url' => $cert->file_url, // Assuming accessor or attribute
                ];
            });

        return response()->json([
            'success' => true,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'stats' => $stats,
        ]);
    }
}
