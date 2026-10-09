<?php

if (! function_exists('peut_modifier_article')) {
    /**
     * L'utilisateur connecté peut modifier ou supprimer l'article
     * s'il en est l'auteur ou s'il appartient au groupe admin ou superadmin.
     */
    function peut_modifier_article(array $article): bool
    {
        if (! auth()->loggedIn()) {
            return false;
        }

        $user = auth()->user();

        return ($article['user_id'] !== null && (int) $article['user_id'] === (int) $user->id)
            || $user->inGroup('admin', 'superadmin');
    }
}
