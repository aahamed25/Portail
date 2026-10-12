<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;

class StrongPassword
{
    public static function rules(): array
    {
        return [
            new Assert\NotBlank(),
            new Assert\Length(min: 12, max: 4096, minMessage: '12 caractères minimum.'),
            new Assert\Regex('/[a-z]/', message: 'Au moins une minuscule.'),
            new Assert\Regex('/[A-Z]/', message: 'Au moins une majuscule.'),
            new Assert\Regex('/\d/', message: 'Au moins un chiffre.'),
            new Assert\Regex('/[^\w\s]/', message: 'Au moins un caractère spécial.'),
            new Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_STRONG),
            new Assert\NotCompromisedPassword(message: 'Ce mot de passe a fuité dans une base publique.'),
        ];
    }
}