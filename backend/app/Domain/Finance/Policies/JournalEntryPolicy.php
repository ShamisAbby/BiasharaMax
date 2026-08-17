<?php

namespace App\Domain\Finance\Policies;

use App\Domain\Authentication\Models\User;
use App\Domain\Finance\Models\JournalEntry;

class JournalEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('finance.view');
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $user->business_id === $entry->business_id && $user->hasPermission('finance.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('finance.journal.manage');
    }

    public function void(User $user, JournalEntry $entry): bool
    {
        return $user->business_id === $entry->business_id && $user->hasPermission('finance.journal.manage');
    }

    public function post(User $user, JournalEntry $entry): bool
    {
        return $user->business_id === $entry->business_id && $user->hasPermission('finance.journal.post');
    }

    public function reverse(User $user, JournalEntry $entry): bool
    {
        return $user->business_id === $entry->business_id && $user->hasPermission('finance.journal.post');
    }
}
