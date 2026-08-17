<?php

namespace App\Domain\Finance\Http\Resources;

use App\Domain\Finance\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalEntry
 */
class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_number' => $this->entry_number,
            'entry_date' => $this->entry_date?->toDateString(),
            'status' => $this->status,
            'type' => $this->type,
            'description' => $this->description,
            'memo' => $this->memo,
            'source_type' => $this->source_type,
            'source_id' => $this->source_id,
            'void_reason' => $this->void_reason,
            'voided_at' => $this->voided_at,
            'posted_at' => $this->posted_at,
            'posted_by' => $this->whenLoaded('postedBy', fn () => $this->postedBy ? [
                'id' => $this->postedBy->id,
                'name' => $this->postedBy->name,
            ] : null),
            'reversal_of_id' => $this->reversal_of_id,
            'reversed_journal_entry_id' => $this->reversed_journal_entry_id,
            'total_debit' => $this->total_debit ?? $this->whenLoaded('lines', fn () => $this->lines->sum('debit')),
            'total_credit' => $this->total_credit ?? $this->whenLoaded('lines', fn () => $this->lines->sum('credit')),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'account' => [
                    'id' => $line->account->id,
                    'code' => $line->account->code,
                    'name' => $line->account->name,
                ],
                'debit' => $line->debit,
                'credit' => $line->credit,
                'description' => $line->description,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
