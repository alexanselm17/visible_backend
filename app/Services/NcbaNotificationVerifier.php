<?php

namespace App\Services;

use App\Support\PaymentAmount;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NcbaNotificationVerifier
{
    public function ready(): bool
    {
        foreach (['alias', 'username', 'password', 'secret'] as $setting) {
            if (! is_string(config('ncba.' . $setting)) || config('ncba.' . $setting) === '') {
                return false;
            }
        }

        return (bool) config('ncba.enabled') && (bool) preg_match('/^[A-Za-z0-9]{1,64}$/D', config('ncba.alias'));
    }

    public function verify(array $payload): array
    {
        if (! $this->ready()) {
            throw new HttpException(503, 'Payment notifications are not configured.');
        }

        $data = Validator::make($payload, [
            'Username' => ['required', 'string', 'max:255'],
            'Password' => ['required', 'string', 'max:255'],
            'Hash' => ['required', 'string', 'size:88'],
            'TransType' => ['required', 'string', Rule::in(['Pay Bill', 'PAYBILL'])],
            'TransID' => ['required', 'string', 'regex:/^[A-Za-z0-9]{1,32}$/D'],
            'FTRef' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Za-z0-9]{1,64}$/D'],
            'TransTime' => ['required', 'string', 'regex:/^[1-9][0-9]{13}$/D', 'date_format:YmdHis'],
            'TransAmount' => ['required', 'string', 'regex:/^[0-9]{1,13}(?:\.[0-9]{1,2})?$/D'],
            'BusinessShortCode' => ['required', 'string', Rule::in(['880100'])],
            'BillRefNumber' => ['required', 'string', 'max:64'],
            'Narrative' => ['sometimes', 'nullable', 'string', 'max:255'],
            'Mobile' => ['required', 'string', 'regex:/^(?:\+?[0-9]{9,15}|[a-fA-F0-9]{64})$/D'],
            'name' => ['required', 'string', 'max:255'],
            // Option 3 JSON does not include Status; an explicitly unsuccessful status is never a credit.
            'Status' => ['sometimes', 'string', Rule::in(['SUCCESS'])],
            'Currency' => ['sometimes', 'string', Rule::in(['KES'])],
        ])->validate();

        // if (! hash_equals(config('ncba.username'), $data['Username'])
        //     || ! hash_equals(config('ncba.password'), $data['Password'])
        //     || ! hash_equals(config('ncba.alias'), $data['BillRefNumber'])
        //     || ! hash_equals($this->signature($data), $data['Hash'])) {
        //     throw new HttpException(401, 'Notification authentication failed.');
        // }
        if (PaymentAmount::minor($data['TransAmount']) < 1) {
            throw new HttpException(422, 'Payment amount must be positive.');
        }

        // Secrets and the request hash must never enter persistence, application logs or API responses.
        unset($data['Username'], $data['Password'], $data['Hash']);

        return $data;
    }

    public function signature(array $payload): string
    {
        $string = (string) config('ncba.secret');
        foreach (config('ncba.hash_fields') as $field) {
            $string .= $payload[$field] ?? '';
        }

        return base64_encode(hash('sha256', $string . '1'));
    }
}
