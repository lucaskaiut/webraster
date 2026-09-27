<?php

namespace App\Modules\Chat\Policies;

use App\Modules\ACL\Enums\Permission;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Tenant\Support\TenantAuthorization;
use App\Modules\User\Models\User;

class ChatConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::CRM_CONVERSATION_VIEW);
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $this->sameTenant($conversation) && $user->hasPermission(Permission::CRM_CONVERSATION_VIEW);
    }

    public function update(User $user, Conversation $conversation): bool
    {
        return $this->sameTenant($conversation) && $user->hasPermission(Permission::CRM_CONVERSATION_REPLY);
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->update($user, $conversation);
    }

    private function sameTenant(Conversation $conversation): bool
    {
        return TenantAuthorization::matchesCurrentTenant((int) $conversation->tenant_id);
    }
}
