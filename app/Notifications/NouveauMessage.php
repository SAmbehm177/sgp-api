<?php

namespace App\Notifications;

use App\Models\Message;
use App\Models\Projet;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NouveauMessage extends Notification
{
    use Queueable;

    public function __construct(public Projet $projet, public Message $message, public int $nombre = 1) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return self::donnees($this->projet, $this->message, $this->nombre);
    }

    /**
     * Contenu de la notification. Une seule notification non lue existe par projet et par membre :
     * tant qu'elle n'est pas lue, les messages suivants la mettent à jour (compteur + dernier message)
     * au lieu d'en créer une nouvelle.
     */
    public static function donnees(Projet $projet, Message $message, int $nombre): array
    {
        $auteur = $message->auteur;
        $nom = $auteur->personnel
            ? trim($auteur->personnel->prenom.' '.$auteur->personnel->nom)
            : $auteur->name;

        return [
            'type' => 'nouveau_message',
            'projet_id' => $projet->id,
            'projet_titre' => $projet->titre,
            'message_id' => $message->id,
            'nombre' => $nombre,
            'message' => $nombre === 1
                ? "{$nom} a écrit dans « {$projet->titre} » : ".Str::limit(Str::squish($message->contenu), 80)
                : "{$nombre} nouveaux messages dans « {$projet->titre} »",
        ];
    }
}
