<?php

namespace App\Modules\ApiToken\Services;

use App\Modules\ApiToken\DTOs\IssuedApiToken;
use App\Modules\ApiToken\Models\ApiToken;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApiTokenService
{
    /**
     * @return Collection<int, ApiToken>
     */
    public function list(): Collection
    {
        return ApiToken::query()->latest()->get();
    }

    /**
     * @param  list<string>|null  $permissions  null = acesso irrestrito
     */
    public function issue(string $name, ?Carbon $expiresAt = null, ?array $permissions = null): IssuedApiToken
    {
        $plainTextToken = ApiToken::PREFIX.Str::random(48);

        $apiToken = ApiToken::query()->create([
            'name' => $name,
            'token_hash' => ApiToken::hash($plainTextToken),
            'expires_at' => $expiresAt,
            'permissions' => $permissions,
        ]);

        return new IssuedApiToken($apiToken, $plainTextToken);
    }

    public function looksLikeApiToken(?string $plainTextToken): bool
    {
        return is_string($plainTextToken) && Str::startsWith($plainTextToken, ApiToken::PREFIX);
    }

    public function findValidByPlainToken(string $plainTextToken): ?ApiToken
    {
        $apiToken = ApiToken::query()
            ->withoutTenancy()
            ->where('token_hash', ApiToken::hash($plainTextToken))
            ->first();

        if ($apiToken === null || $apiToken->isExpired()) {
            return null;
        }

        return $apiToken;
    }

    public function markAsUsed(ApiToken $apiToken): void
    {
        $apiToken->forceFill(['last_used_at' => now()])->save();
    }

    public function revoke(ApiToken $apiToken): void
    {
        $apiToken->delete();
    }
}
