<?php

namespace App\Enums;

use App\Models\Contract;
use App\Models\Saving;
use Illuminate\Database\Eloquent\Model;

/**
 * The records a receipt can be attached to. Requests and the UI use these
 * short keys; receipt_links stores the model class, as tasks do.
 */
enum ReceiptLinkType: string
{
    case Contract = 'contract';
    case Saving = 'saving';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Contract => Contract::class,
            self::Saving => Saving::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Contract => 'Contract',
            self::Saving => 'Savings entry',
        };
    }

    /**
     * Named route of the page that shows the linked record.
     */
    public function routeName(): string
    {
        return match ($this) {
            self::Contract => 'contracts.show',
            self::Saving => 'savings.edit',
        };
    }

    /**
     * Short human name of a linked record, e.g. "Gym" or "Tax refund · 03.11.2026".
     */
    public function describe(Model $model): string
    {
        return match ($this) {
            self::Contract => (string) $model->getAttribute('name'),
            self::Saving => trim(($model->getAttribute('source') ?: 'Savings').($model->getAttribute('saved_on') instanceof \DateTimeInterface ? ' · '.$model->getAttribute('saved_on')->format('d.m.Y') : '')),
        };
    }

    public static function fromModel(Model $model): ?self
    {
        foreach (self::cases() as $type) {
            if ($model instanceof ($type->modelClass())) {
                return $type;
            }
        }

        return null;
    }

    /**
     * The user's record of this type, or null when it does not exist or belongs to someone else.
     */
    public function findOwnedBy(int $userId, int $id): ?Model
    {
        return $this->modelClass()::query()->where('user_id', $userId)->find($id);
    }
}
