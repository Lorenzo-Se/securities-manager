<?php

namespace App\Http\Requests;

use App\Enums\PortfolioShareStatus;
use App\Enums\PortfolioShareType;
use App\Models\PortfolioShare;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePortfolioShareRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'share_type' => ['required', Rule::enum(PortfolioShareType::class)],
            'share_amount' => ['nullable', 'required_if:share_type,amount', 'numeric', 'gt:0'],
            'share_percentage' => ['nullable', 'required_if:share_type,percentage', 'numeric', 'gt:0', 'lte:100'],
            'show_total_balance' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $email = strtolower($this->string('email')->toString());
            $userEmail = strtolower($this->user()->email);

            if ($email === $userEmail) {
                $validator->errors()->add('email', 'Du kannst dich nicht selbst einladen.');

                return;
            }

            $portfolio = $this->route('portfolio');

            $existingShare = PortfolioShare::query()
                ->where('portfolio_id', $portfolio->id)
                ->where('invited_email', $email)
                ->whereIn('status', [
                    PortfolioShareStatus::Pending,
                    PortfolioShareStatus::Accepted,
                ])
                ->exists();

            if ($existingShare) {
                $validator->errors()->add('email', 'Für diese E-Mail existiert bereits eine aktive Einladung.');
            }
        });
    }

    public function normalizedEmail(): string
    {
        return strtolower($this->string('email')->toString());
    }

    public function shareType(): PortfolioShareType
    {
        return PortfolioShareType::from($this->string('share_type')->toString());
    }
}
