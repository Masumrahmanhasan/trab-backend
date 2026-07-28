<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class ActivityLogger
{
    public function log(
        string $action,
        ?User $user = null,
        ?int $storeId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null
    ): ActivityLog {
        $request = $request ?? request();

        return ActivityLog::create([
            'user_id' => $user?->id,
            'store_id' => $storeId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function logRoleAssignment(
        User $user,
        string $roleKey,
        ?int $storeId = null,
        ?Request $request = null
    ): ActivityLog {
        return $this->log(
            'role_assigned',
            $user,
            $storeId,
            User::class,
            $user->id,
            null,
            ['role' => $roleKey, 'team_id' => $storeId],
            $request
        );
    }

    public function logRoleRemoval(
        User $user,
        string $roleKey,
        ?int $storeId = null,
        ?Request $request = null
    ): ActivityLog {
        return $this->log(
            'role_removed',
            $user,
            $storeId,
            User::class,
            $user->id,
            ['role' => $roleKey, 'team_id' => $storeId],
            null,
            $request
        );
    }

    public function logPermissionGrant(
        User $user,
        string $permissionKey,
        ?int $storeId = null,
        ?Request $request = null
    ): ActivityLog {
        return $this->log(
            'permission_granted',
            $user,
            $storeId,
            User::class,
            $user->id,
            null,
            ['permission' => $permissionKey, 'team_id' => $storeId],
            $request
        );
    }

    public function logPermissionRevocation(
        User $user,
        string $permissionKey,
        ?int $storeId = null,
        ?Request $request = null
    ): ActivityLog {
        return $this->log(
            'permission_revoked',
            $user,
            $storeId,
            User::class,
            $user->id,
            ['permission' => $permissionKey, 'team_id' => $storeId],
            null,
            $request
        );
    }

    public function logStaffAssignment(
        User $owner,
        User $staff,
        int $storeId,
        array $data,
        ?Request $request = null
    ): ActivityLog {
        return $this->log(
            'staff_assigned',
            $owner,
            $storeId,
            User::class,
            $staff->id,
            null,
            ['staff_id' => $staff->id, 'data' => $data],
            $request
        );
    }

    public function logStaffRemoval(
        User $owner,
        User $staff,
        int $storeId,
        ?Request $request = null
    ): ActivityLog {
        return $this->log(
            'staff_removed',
            $owner,
            $storeId,
            User::class,
            $staff->id,
            ['staff_id' => $staff->id],
            null,
            $request
        );
    }

    public function logSubscriptionChange(
        User $user,
        int $subscriptionId,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null
    ): ActivityLog {
        return $this->log(
            "subscription_{$action}",
            $user,
            null,
            'Subscription',
            $subscriptionId,
            $oldValues,
            $newValues,
            $request
        );
    }
}
