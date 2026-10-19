<?php

namespace App\Modules\ServiceOrder\Services;

use App\Modules\ServiceOrder\Enums\ServiceOrderStatus;
use App\Modules\ServiceOrder\Models\ServiceOrder;
use App\Modules\ServiceOrder\Models\ServiceOrderAttachment;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Anexos da OS em disco privado: o download passa sempre pela API autenticada.
 */
class ServiceOrderAttachmentService
{
    public const DISK = 'local';

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'doc', 'docx', 'xls', 'xlsx', 'txt'];

    public const MAX_KILOBYTES = 10240;

    public function store(ServiceOrder $order, UploadedFile $file, User $actor): ServiceOrderAttachment
    {
        $this->assertMutable($order);

        $path = $file->store("service-orders/{$order->uuid}", self::DISK);

        if ($path === false) {
            throw ValidationException::withMessages(['file' => ['Não foi possível salvar o arquivo.']]);
        }

        return $order->attachments()->create([
            'uploaded_by' => $actor->getKey(),
            'disk' => self::DISK,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName() ?: basename($path), 0, 255),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
        ])->load('uploader');
    }

    public function download(ServiceOrder $order, ServiceOrderAttachment $attachment): StreamedResponse
    {
        $this->assertBelongs($order, $attachment);

        $disk = Storage::disk($attachment->disk);

        if (! $disk->exists($attachment->path)) {
            throw (new ModelNotFoundException)->setModel(ServiceOrderAttachment::class, [$attachment->uuid]);
        }

        return $disk->download($attachment->path, $attachment->original_name, array_filter([
            'Content-Type' => $attachment->mime_type,
        ]));
    }

    public function delete(ServiceOrder $order, ServiceOrderAttachment $attachment): void
    {
        $this->assertBelongs($order, $attachment);
        $this->assertMutable($order);

        Storage::disk($attachment->disk)->delete($attachment->path);
        $attachment->delete();
    }

    private function assertBelongs(ServiceOrder $order, ServiceOrderAttachment $attachment): void
    {
        if ((int) $attachment->service_order_id !== (int) $order->getKey()) {
            throw (new ModelNotFoundException)->setModel(ServiceOrderAttachment::class, [$attachment->uuid]);
        }
    }

    private function assertMutable(ServiceOrder $order): void
    {
        if ($order->status === ServiceOrderStatus::CANCELLED) {
            throw ValidationException::withMessages([
                'status' => ['Não é possível alterar os anexos de uma OS cancelada.'],
            ]);
        }
    }
}
