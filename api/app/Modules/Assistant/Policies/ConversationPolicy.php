<?php

namespace App\Modules\Assistant\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Assistant\Models\Conversation;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ASSISTANT_VIEW);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->ownedBy($user, $conversation)
            && $user->hasPermission(Permission::ASSISTANT_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ASSISTANT_VIEW);
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->ownedBy($user, $conversation)
            && $user->hasPermission(Permission::ASSISTANT_VIEW);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->ownedBy($user, $conversation)
            && $user->hasPermission(Permission::ASSISTANT_VIEW);
    }

    private function ownedBy(User $user, Conversation $conversation): bool
    {
        return (int) $conversation->user_id === (int) $user->getKey()
            && TenantAuthorization::matchesCurrentTenant((int) $conversation->tenant_id);
    }
}
