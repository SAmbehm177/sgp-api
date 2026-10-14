<?php

namespace App\Notifications;

use App\Models\Projet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MembreAjoute extends Notification
{
    use Queueable;

    public function __construct(public Projet $projet, public string $fonction)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'membre_ajoute',
            'projet_id' => $this->projet->id,
            'projet_titre' => $this->projet->titre,
            'fonction' => $this->fonction,
            'message' => "Vous avez été ajouté au projet « {$this->projet->titre} » avec la fonction « {$this->fonction} ».",
        ];
    }
}