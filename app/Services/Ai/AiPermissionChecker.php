<?php

namespace App\Services\Ai;

use App\Enums\AiIntent;
use App\Models\User;

class AiPermissionChecker
{
    public function canUseIntent(?User $user, AiIntent $intent): bool
    {
        if (! in_array($intent->value, config('ai.enabled_intents', []), true)) {
            return false;
        }

        if ($intent === AiIntent::NeedsClarification || $intent === AiIntent::Unknown) {
            return true;
        }

        if ($this->isAdminOnly($intent)) {
            return $this->isAdminOrDeveloper($user);
        }

        if (in_array($intent, [AiIntent::UpdateProfile, AiIntent::UpdateOrder, AiIntent::AdminReportQuery], true)) {
            return $user !== null;
        }

        if ($intent === AiIntent::PlatformDataQuery) {
            return true;
        }

        if (in_array($intent, [AiIntent::GenerateImage, AiIntent::FastGenerateImage], true)) {
            return $this->planAllows($user, 'image');
        }

        if ($intent === AiIntent::VisionAnalyze) {
            return $this->planAllows($user, 'vision');
        }

        if ($intent === AiIntent::ArtworkSimilarity) {
            return $this->planAllows($user, 'artwork_similarity');
        }

        return true;
    }

    public function getDeniedReason(?User $user, AiIntent $intent): string
    {
        if (! in_array($intent->value, config('ai.enabled_intents', []), true)) {
            return 'This AI feature is disabled.';
        }

        if ($this->isAdminOnly($intent)) {
            return 'This AI feature is available only for admins and developers.';
        }

        if ($user === null && in_array($intent, [AiIntent::UpdateProfile, AiIntent::UpdateOrder, AiIntent::AdminReportQuery], true)) {
            return 'Please sign in to use this AI feature.';
        }

        return 'AI feature not allowed for this subscription.';
    }

    public function canAccessTool(?User $user, string $tool): bool
    {
        return match ($tool) {
            'users_registered_last_24h' => $this->canViewUsers($user),
            'user_own_profile' => $user !== null,
            'platform_basic_stats' => $this->canAccessAdminDashboard($user),
            'site_content_search' => true,
            'blog_content_search' => true,
            'roles_list' => $this->canManageRoles($user),
            'users_search' => $this->canViewUsers($user),
            default => false,
        };
    }

    public function getToolDeniedReason(?User $user, string $tool): string
    {
        if ($user === null) {
            return 'Please sign in to access this data.';
        }

        return match ($tool) {
            'users_registered_last_24h' => 'You do not have permission to access user lists.',
            'platform_basic_stats' => 'You do not have permission to access platform statistics.',
            'roles_list' => 'You do not have permission to access role lists.',
            'users_search' => 'You do not have permission to search users.',
            default => 'This AI data tool is not allowed.',
        };
    }

    public function isAdminOrDeveloper(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(['super-admin', 'admin', 'developer', 'staff']);
    }

    public function canViewUsers(?User $user): bool
    {
        return $user !== null && ($user->hasAnyRole(['super-admin', 'admin']) || $user->can('users.viewAny') || $user->can('users.manage'));
    }

    public function canAccessAdminDashboard(?User $user): bool
    {
        return $user !== null && ($user->hasAnyRole(['super-admin', 'admin', 'staff', 'employee']) || $user->getAllPermissions()->isNotEmpty());
    }

    public function canManageRoles(?User $user): bool
    {
        return $user !== null && ($user->hasAnyRole(['super-admin', 'admin']) || $user->can('roles.viewAny') || $user->can('roles.manage') || $user->can('permissions.manage'));
    }

    private function isAdminOnly(AiIntent $intent): bool
    {
        return in_array($intent->value, config('ai.admin_only_intents', []), true);
    }

    private function planAllows(?User $user, string $feature): bool
    {
        if ($this->isAdminOrDeveloper($user)) {
            return true;
        }

        // Subscription integration placeholder. Defaults are intentionally permissive for public support chat.
        return true;
    }
}
