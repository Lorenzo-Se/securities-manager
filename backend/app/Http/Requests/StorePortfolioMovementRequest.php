<?php

namespace App\Http\Requests;

use App\Models\Portfolio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePortfolioMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['deposit', 'withdrawal'])],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,16}(\.\d{1,8})?$/'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->string('type')->toString() !== 'withdrawal') {
                return;
            }

            /** @var Portfolio $portfolio */
            $portfolio = $this->route('portfolio');
            $amount = (float) $this->input('amount');
            $occurredAt = $this->date('occurred_at');
            $balance = (float) $portfolio->balanceAt($occurredAt);

            if ($amount > $balance) {
                $validator->errors()->add('amount', 'Nicht genügend Bestand.');
            }
        });
    }
}
