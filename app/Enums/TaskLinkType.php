<?php

namespace App\Enums;

use App\Models\Budget;
use App\Models\Contract;
use App\Models\Receipt;
use App\Models\Saving;
use Illuminate\Database\Eloquent\Model;

/**
 * The records a task can be linked to. Requests and the UI use these short
 * keys; the database stores the model class (no morph map, because media
 * library already stores Receipt's class name and a map would orphan it).
 */
enum TaskLinkType: string
{
    case Receipt = 'receipt';
    case Contract = 'contract';
    case Budget = 'budget';
    case Saving = 'saving';

    /**
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Receipt => Receipt::class,
            self::Contract => Contract::class,
            self::Budget => Budget::class,
            self::Saving => Saving::class,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Receipt => 'Receipt',
            self::Contract => 'Contract',
            self::Budget => 'Budget',
            self::Saving => 'Savings',
        };
    }

    /**
     * Named route of the page that shows the linked record.
     */
    public function routeName(): string
    {
        return match ($this) {
            self::Receipt => 'receipts.show',
            self::Contract => 'contracts.show',
            self::Budget => 'budgets.edit',
            self::Saving => 'savings.edit',
        };
    }

    /**
     * Short human name of a linked record, e.g. "REWE · 03.11.2026" or "Groceries budget (monthly)".
     */
    public function describe(Model $model): string
    {
        return match ($this) {
            self::Receipt => $this->withDate($model->getAttribute('vendor') ?: 'Receipt', $model->getAttribute('receipt_date')),
            self::Contract => (string) $model->getAttribute('name'),
            self::Budget => trim(($model->getRelationValue('category')?->name ?? 'Overall').' budget ('.$model->getAttribute('period')?->label().')'),
            self::Saving => $this->withDate($model->getAttribute('source') ?: 'Savings', $model->getAttribute('saved_on')),
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

    private function withDate(string $label, mixed $date): string
    {
        return $date instanceof \DateTimeInterface ? $label.' · '.$date->format('d.m.Y') : $label;
    }

    /**
     * The user's record of this type, or null when it does not exist or belongs to someone else.
     */
    public function findOwnedBy(int $userId, int $id): ?Model
    {
        return $this->modelClass()::query()->where('user_id', $userId)->find($id);
    }
}
