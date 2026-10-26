<?php

namespace App\Support;

use Filament\Notifications\Notification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AdminWorkflow
{
    /** Run the same validation and business rules as the API, using the panel session. */
    public static function run(string $controller, string $method, array $data = [], array $parameters = [], array $files = []): array
    {
        // Livewire files are already uploaded. Adapt them before API code calls move().
        $files = array_map(fn ($file) => $file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile
            ? new \Illuminate\Http\UploadedFile($file->getRealPath(), $file->getClientOriginalName(), $file->getMimeType(), null, true)
            : $file, $files);
        $reflection = new \ReflectionMethod($controller, $method);
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (! $type instanceof \ReflectionNamedType || ! is_a($type->getName(), Request::class, true)) {
                continue;
            }

            $requestClass = $type->getName();
            $request = $requestClass::create('/', 'POST', $data, [], $files);
            $request->setUserResolver(fn () => auth()->user());
            if ($request instanceof FormRequest) {
                $request->setContainer(app())->setRedirector(app('redirect'));
                abort_unless($request->authorize(), 403);
                $validator = Validator::make($data + $files, $request->rules());
                $request->setValidator($validator);
                $validator->validate();
            }
            $parameters[$parameter->getName()] = $request;
        }

        $response = app()->call([app($controller), $method], $parameters);
        if (! $response instanceof JsonResponse) {
            throw new \RuntimeException('The operation did not return a result.');
        }
        $payload = $response->getData(true);
        if ($response->getStatusCode() >= 500) {
            \Illuminate\Support\Facades\Log::error('Admin workflow failed', [
                'operation' => $controller.'@'.$method, 'error' => $payload['error'] ?? $payload['message'] ?? null,
            ]);
        }
        if ($response->getStatusCode() >= 400 || ($payload['ok'] ?? true) === false) {
            throw ValidationException::withMessages($payload['errors'] ?? [
                'operation' => $payload['message'] ?? 'The operation could not be completed.',
            ]);
        }

        Notification::make()->title($payload['message'] ?? 'Operation completed')->success()->send();

        return $payload;
    }
}
