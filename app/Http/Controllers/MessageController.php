<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\Projet;
use App\Notifications\NouveauMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    private const PAR_PAGE = 30;

    // Relations chargées avec chaque message (l'e-mail de l'auteur n'est volontairement pas exposé)
    private const AUTEUR = ['auteur:id,name', 'auteur.personnel:id,user_id,nom,prenom'];

    private const DESTINATAIRE = ['destinataire:id,name', 'destinataire.personnel:id,user_id,nom,prenom'];

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
            'apres_suppression_id' => ['nullable', 'integer', 'min:0'],
        ]);

        // Ouvrir la discussion vaut lecture : les notifications de nouveaux messages du projet sont marquées lues
        $request->user()->unreadNotifications()
            ->where('type', NouveauMessage::class)
            ->where('data->projet_id', $projet->id)
            ->update(['read_at' => now()]);

        $query = $projet->messages()->with([...self::AUTEUR, ...self::DESTINATAIRE]);
        $suppressions = DB::table('message_suppressions')->where('projet_id', $projet->id);
        $dernierIdSuppression = (clone $suppressions)->max('id') ?? 0;
        $suppressionsDepuis = $request->filled('apres_suppression_id')
            ? $suppressions
                ->where('id', '>', $request->integer('apres_suppression_id'))
                ->orderBy('id')
                ->get(['id', 'message_id'])
            : collect();

        if ($request->filled('apres_id')) {
            return [
                'data' => $query->where('id', '>', $request->integer('apres_id'))->orderBy('id')->get(),
                'a_plus_ancien' => false,
                'suppressions' => $suppressionsDepuis,
                'dernier_id_suppression' => $dernierIdSuppression,
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
            'suppressions' => $suppressionsDepuis,
            'dernier_id_suppression' => $dernierIdSuppression,
        ];
    }

    // POST /projets/{projet}/messages : tout membre actif du projet (et l'admin) peut écrire
    public function store(Request $request, Projet $projet)
    {
        Gate::authorize('voir', $projet);

        $data = $request->validate([
            'contenu' => ['required', 'string', 'max:2000'],
            'destinataire_id' => ['nullable', 'integer'],
        ]);

        // Destinataire facultatif : un autre membre actif du projet (sans destinataire = tout le projet)
        $destinataireId = isset($data['destinataire_id']) ? (int) $data['destinataire_id'] : null;

        if ($destinataireId !== null) {
            $estMembreActif = $projet->membresActifs()->where('personnels.user_id', $destinataireId)->exists();

            if ($destinataireId === $request->user()->id || ! $estMembreActif) {
                throw ValidationException::withMessages([
                    'destinataire_id' => ['Le destinataire doit être un autre membre actif du projet.'],
                ]);
            }
        }

        $message = $projet->messages()->create([
            'user_id' => $request->user()->id,
            'destinataire_id' => $destinataireId,
            'contenu' => $data['contenu'],
        ]);

        return response()->json($message->load([...self::AUTEUR, ...self::DESTINATAIRE]), 201);
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

        DB::transaction(function () use ($message, $projet): void {
            DB::table('message_suppressions')->insert([
                'projet_id' => $projet->id,
                'message_id' => $message->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $message->delete();
        });

        return response()->json(['message' => 'Message supprimé.']);
    }
}
