<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Projet;
use App\Notifications\NouveauMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    private const PAR_PAGE = 30;

    // Relations chargées avec chaque message (l'e-mail de l'auteur n'est volontairement pas exposé)
    private const AUTEUR = ['auteur:id,name', 'auteur.personnel:id,user_id,nom,prenom'];

    /**
     * GET /projets/{projet}/messages
     *  - sans paramètre : les 30 derniers messages
     *  - ?avant_id=X    : les 30 messages précédant X (bouton « messages plus anciens »)
     *  - ?apres_id=X    : les messages postérieurs à X (sondage régulier du front)
     * Les messages sont toujours renvoyés du plus ancien au plus récent.
     */
    public function index(Request $request, Projet $projet)
    {
        Gate::authorize('voir', $projet);

        $request->validate([
            'avant_id' => ['nullable', 'integer', 'min:0'],
            'apres_id' => ['nullable', 'integer', 'min:0'],
        ]);

        // Ouvrir la discussion vaut lecture : les notifications de nouveaux messages du projet sont marquées lues
        $request->user()->unreadNotifications()
            ->where('type', NouveauMessage::class)
            ->get()
            ->filter(fn ($n) => (int) ($n->data['projet_id'] ?? 0) === $projet->id)
            ->each->markAsRead();

        $query = $projet->messages()->with(self::AUTEUR);

        if ($request->filled('apres_id')) {
            return [
                'data' => $query->where('id', '>', $request->integer('apres_id'))->orderBy('id')->get(),
                'a_plus_ancien' => false,
            ];
        }

        if ($request->filled('avant_id')) {
            $query->where('id', '<', $request->integer('avant_id'));
        }

        // On lit un message de trop pour savoir s'il en reste d'autres avant
        $messages = $query->orderByDesc('id')->limit(self::PAR_PAGE + 1)->get();

        return [
            'data' => $messages->take(self::PAR_PAGE)->reverse()->values(),
            'a_plus_ancien' => $messages->count() > self::PAR_PAGE,
        ];
    }

    // POST /projets/{projet}/messages : tout membre actif du projet (et l'admin) peut écrire
    public function store(Request $request, Projet $projet)
    {
        Gate::authorize('voir', $projet);

        $data = $request->validate([
            'contenu' => ['required', 'string', 'max:2000'],
        ]);

        $message = $projet->messages()->create([
            'user_id' => $request->user()->id,
            'contenu' => $data['contenu'],
        ]);

        return response()->json($message->load(self::AUTEUR), 201);
    }

    // DELETE /messages/{message} : l'auteur, un chef de projet du projet ou l'admin
    public function destroy(Request $request, Message $message)
    {
        $projet = $message->projet;

        Gate::authorize('voir', $projet);

        abort_unless(
            $message->user_id === $request->user()->id || Gate::allows('gerer', $projet),
            403
        );

        $message->delete();

        return response()->json(['message' => 'Message supprimé.']);
    }
}
