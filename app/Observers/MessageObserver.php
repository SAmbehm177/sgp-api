<?php

namespace App\Observers;

use App\Models\Message;
use App\Models\Projet;
use App\Models\User;
use App\Notifications\NouveauMessage;

class MessageObserver
{
    // Chaque membre actif du projet (sauf l'auteur) est notifié d'un nouveau message
    public function created(Message $message): void
    {
        $projet = $message->projet;
        $message->loadMissing('auteur.personnel');

        $projet->membresActifs()->with('user')->get()
            ->pluck('user')
            ->filter(fn (?User $user) => $user && $user->estActif() && $user->id !== $message->user_id)
            ->each(fn (User $user) => $this->notifier($user, $projet, $message));
    }

    private function notifier(User $user, Projet $projet, Message $message): void
    {
        $existante = $user->unreadNotifications()
            ->where('type', NouveauMessage::class)
            ->get()
            ->first(fn ($n) => (int) ($n->data['projet_id'] ?? 0) === $projet->id);

        if (! $existante) {
            $user->notify(new NouveauMessage($projet, $message));

            return;
        }

        // Regroupement : même notification, compteur incrémenté, remontée en tête de liste
        $existante->update([
            'data' => NouveauMessage::donnees($projet, $message, ((int) ($existante->data['nombre'] ?? 1)) + 1),
            'created_at' => now(),
        ]);
    }
}
