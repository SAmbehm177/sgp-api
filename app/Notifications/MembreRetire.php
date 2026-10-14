<?php

namespace App\Notifications;

use App\Models\Projet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MembreRetire extends Notification
{
    use Queueable;

    public function __construct(public Projet $projet)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'membre_retire',
            'projet_id' => $this->projet->id,
            'projet_titre' => $this->projet->titre,
            'message' => "Vous avez été retiré du projet « {$this->projet->titre} ».",
        ];
    }
}
