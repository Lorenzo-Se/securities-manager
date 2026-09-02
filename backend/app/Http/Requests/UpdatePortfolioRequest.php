<?php

namespace App\Http\Requests;

use App\Models\Portfolio;
use App\Services\Blockchain\ChainRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePortfolioRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'wallet_source' => ['sometimes', 'required', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! $this->has('wallet_source')) {
                return;
            }

            /** @var Portfolio $portfolio */
            $portfolio = $this->route('portfolio');

            $walletError = app(ChainRegistry::class)->validateWalletSource(
                $portfolio->crypto_id,
                $this->string('wallet_source')->toString(),
            );

            if ($walletError !== null) {
                $validator->errors()->add('wallet_source', $walletError);
            }
        });
    }
}
