<?php

namespace App\Notifications;

use App\Models\Tache;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TacheAffectee extends Notification
{
    use Queueable;

    public function __construct(public Tache $tache) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'tache_affectee',
            'tache_id' => $this->tache->id,
            'projet_id' => $this->tache->projet_id,
            'message' => "Une tâche vous a été affectée : {$this->tache->titre}",
        ];
    }
}
