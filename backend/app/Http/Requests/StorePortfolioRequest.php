<?php

namespace App\Http\Requests;

use App\Services\Crypto\CoinGeckoClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePortfolioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'crypto_id' => ['required', 'string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $cryptoId = $this->string('crypto_id')->toString();
            $coin = app(CoinGeckoClient::class)->findCoinById($cryptoId);

            if ($coin === null) {
                $validator->errors()->add('crypto_id', 'The selected crypto_id is invalid.');
            }
        });
    }

    public function resolvedCoin(): array
    {
        $coin = app(CoinGeckoClient::class)->findCoinById(
            $this->string('crypto_id')->toString(),
        );

        if ($coin === null) {
            abort(422, 'The selected crypto_id is invalid.');
        }

        return $coin;
    }
}
