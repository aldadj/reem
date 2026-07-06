<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPoint;
use App\Models\PointTransaction;
use Illuminate\Support\Facades\DB;

class PointService
{
    // Définition des constantes pour les types d'actions
    const ACTION_PUBLISH = 'content_publish';
    const ACTION_LIKE_RECEIVED = 'like_received';
    const ACTION_INVITE = 'invite_accepted';

    // Valeurs des points par action
    protected array $pointValues = [
        self::ACTION_PUBLISH => 10,       // +10 points pour une vidéo publiée
        self::ACTION_LIKE_RECEIVED => 2,  // +2 points quand quelqu'un aime votre vidéo
        self::ACTION_INVITE => 50,        // +50 points pour une invitation
    ];

    /**
     * Ajoute des points à un utilisateur et vérifie son niveau.
     */
    public function addPoints(User $user, string $action, ?string $description = null): void
    {
        $amount = $this->pointValues[$action] ?? 0;
        if ($amount === 0) return;

        DB::transaction(function () use ($user, $action, $amount, $description) {
            // 1. Récupérer ou créer le solde de l'utilisateur
            $userPoint = UserPoint::firstOrCreate(
                ['user_id' => $user->id],
                ['points' => 0, 'level' => 'Créateur Débutant']
            );

            // 2. Mettre à jour les points et le niveau
            $userPoint->points += $amount;
            $userPoint->level = $this->calculateLevel($userPoint->points);
            $userPoint->save();

            // 3. Enregistrer la transaction
            PointTransaction::create([
                'user_id' => $user->id,
                'type' => $action,
                'points_amount' => $amount,
                'description' => $description ?? $this->getDefaultDescription($action),
            ]);
        });
    }

    protected function calculateLevel(int $points): string
    {
        if ($points >= 2000) return 'Star REEM';
        if ($points >= 500) return 'Créateur Elite';
        if ($points >= 100) return 'Créateur Confirmé';
        return 'Créateur Débutant';
    }

    protected function getDefaultDescription(string $action): string
    {
        return match ($action) {
            self::ACTION_PUBLISH => 'Récompense pour publication de contenu',
            self::ACTION_LIKE_RECEIVED => 'Points reçus pour une interaction (Like)',
            self::ACTION_INVITE => 'Récompense pour parrainage',
            default => 'Gain de points REEM',
        };
    }
}
