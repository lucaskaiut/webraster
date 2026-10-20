<?php

namespace Tests\Feature\Auth;

use App\Modules\ACL\Enums\DefaultRole;
use App\Modules\Client\Models\Client;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Services\ServiceOrderPdfService;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Enums\UserType;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\Concerns\InteractsWithTenants;
use Tests\TestCase;

class UserSignatureTest extends TestCase
{
    use InteractsWithTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_technician_uploads_replaces_and_removes_personal_signature(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $technician = $this->createTechnician($tenant);

        Sanctum::actingAs($technician);

        $this->post('/api/auth/signature', [
            'image' => UploadedFile::fake()->image('assinatura.png', 240, 90),
        ])->assertOk()->assertJsonPath('data.signature_url', fn ($url) => is_string($url));

        $firstPath = $technician->refresh()->signature_path;

        $this->assertNotNull($firstPath);
        Storage::disk('local')->assertExists($firstPath);

        $this->get('/api/auth/signature/image')->assertOk();

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.user.signature_url', fn ($url) => is_string($url));

        $this->post('/api/auth/signature', [
            'image' => UploadedFile::fake()->image('assinatura-2.png', 240, 90),
        ])->assertOk();

        $secondPath = $technician->refresh()->signature_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);

        $this->deleteJson('/api/auth/signature')->assertOk();

        $this->assertNull($technician->refresh()->signature_path);
        Storage::disk('local')->assertMissing($secondPath);
        $this->get('/api/auth/signature/image')->assertNotFound();
    }

    public function test_signature_upload_requires_an_image_and_authentication(): void
    {
        $this->postJson('/api/auth/signature', [])->assertUnauthorized();

        [, $tenant] = $this->createOperationalChild();
        Sanctum::actingAs($this->createTechnician($tenant));

        $this->post('/api/auth/signature', [])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_service_order_pdf_embeds_technician_signature(): void
    {
        [, $tenant] = $this->createOperationalChild();
        $technician = $this->createTechnician($tenant);
        $client = Client::factory()->for($tenant)->create();

        $signature = UploadedFile::fake()->image('assinatura.png', 200, 70);
        $path = "users/{$technician->uuid}/signature.png";
        Storage::disk('local')->put($path, $signature->getContent());

        $technician->forceFill(['signature_path' => $path])->save();

        $order = ServiceOrder::factory()->forClient($client)->create([
            'technician_id' => $technician->getKey(),
        ]);
        $order->load('technician');

        $service = app(ServiceOrderPdfService::class);
        $method = new ReflectionMethod($service, 'html');
        $method->setAccessible(true);
        $html = (string) $method->invoke($service, $order);

        $this->assertStringContainsString('signature-image', $html);
        $this->assertStringContainsString('data:image/png;base64,', $html);
    }

    private function createTechnician(Tenant $tenant): User
    {
        $technician = User::factory()->for($tenant)->create([
            'type' => UserType::TECHNICIAN->value,
        ]);

        $technician->assignRole($this->roleFor($tenant, DefaultRole::TECHNICIAN));

        return $technician;
    }
}
