<?php

namespace App\Models;

use App\Observers\MessageObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([MessageObserver::class])]
class Message extends Model
{
    protected $fillable = ['projet_id', 'user_id', 'contenu'];

    public function projet(): BelongsTo
    {
        return $this->belongsTo(Projet::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
