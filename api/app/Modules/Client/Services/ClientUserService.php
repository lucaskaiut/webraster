<?php

namespace App\Modules\Client\Services;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\ACL\Models\Role;
use App\Modules\Client\Models\Client;
use App\Modules\User\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class ClientUserService
{
    public function paginate(Client $client, int $perPage = 15, ?string $search = null): LengthAwarePaginator
    {
        return User::query()
            ->with('roles.permissions')
            ->where('client_id', $client->getKey())
            ->when(filled($search), function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(min(max($perPage, 1), 100));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Client $client, array $data): User
    {
        $roleIds = $data['role_ids'] ?? $this->defaultClientRoleIds();
        unset($data['role_ids']);

        if (blank($data['password'] ?? null)) {
            $data['password'] = $this->generateDefaultPassword($client);
        }

        $user = User::query()->create([
            ...Arr::only($data, ['name', 'email', 'phone', 'document', 'password']),
            'client_id' => $client->getKey(),
        ]);

        $user->syncRoles($roleIds);

        return $user->load('roles.permissions');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, User $user, array $data): User
    {
        $this->assertBelongsToClient($client, $user);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if (array_key_exists('role_ids', $data)) {
            $user->syncRoles($data['role_ids']);
            unset($data['role_ids']);
        }

        $user->fill(Arr::only($data, ['name', 'email', 'phone', 'document', 'password']));
        $user->save();

        return $user->refresh()->load('roles.permissions');
    }

    public function delete(Client $client, User $user): void
    {
        $this->assertBelongsToClient($client, $user);

        $user->tokens()->delete();
        $user->delete();
    }

    private function assertBelongsToClient(Client $client, User $user): void
    {
        if ((int) $user->client_id !== (int) $client->getKey()) {
            abort(404);
        }
    }

    private function generateDefaultPassword(Client $client): string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $client->document);

        if (strlen($digits) < 6) {
            throw ValidationException::withMessages([
                'password' => ['Não foi possível gerar a senha automática: o cliente não possui CPF/CNPJ válido.'],
            ]);
        }

        return substr($digits, 0, 6);
    }

    /**
     * @return list<int>
     */
    private function defaultClientRoleIds(): array
    {
        $role = Role::query()
            ->where('name', DefaultRole::CLIENT->value)
            ->first();

        if ($role === null) {
            throw ValidationException::withMessages([
                'role_ids' => ['Perfil Cliente não encontrado para este tenant.'],
            ]);
        }

        return [$role->getKey()];
    }
}
