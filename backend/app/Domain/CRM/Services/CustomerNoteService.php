<?php

namespace App\Domain\CRM\Services;

use App\Domain\CRM\Models\CustomerNote;

class CustomerNoteService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CustomerNote
    {
        return CustomerNote::create($data);
    }

    public function delete(CustomerNote $note): void
    {
        $note->delete();
    }
}
