<?php

namespace App\Services;

use App\Models\User;

class UserSuggestionService
{
    // Weightage distribution (you can adjust)
    protected $weights = [
        'origin'   => 30,
        'location' => 25,
        'city'     => 20,
        'country'  => 15,
        'province' => 10,
    ];

    /**
     * Calculate score between two users
     */
    public function calculateMatchScore(User $userA, User $userB): int
    {
        $score = 0;

        foreach ($this->weights as $field => $weight) {
            if (
                isset($userA->$field, $userB->$field) &&
                strtolower($userA->$field) === strtolower($userB->$field)
            ) {
                $score += $weight;
            }
        }

        return $score;
    }

    /**
     * Get suggestions for a user
     */
    public function getSuggestions(User $currentUser, $limit = 10)
    {
        $users = User::where('_id', '!=', $currentUser->id)
        ->whereDoesntHave('relations')
        ->get();


        $suggestions = $users->map(function ($user) use ($currentUser) {
            $user->suggestion_score = $this->calculateMatchScore($currentUser, $user);
            return $user;
        });

        return $suggestions->sortByDesc('suggestion_score')->take($limit)->values();
    }
}
