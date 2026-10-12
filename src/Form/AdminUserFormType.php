<?php

namespace App\Form;

use App\Entity\User;
use App\Validator\StrongPassword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['edit'];

        // En modification, le mot de passe est facultatif : on retire NotBlank (1re règle)
        $rules = StrongPassword::rules();
        if ($isEdit) {
            $rules = array_slice($rules, 1);
        }

        $builder
            ->add('email')
            ->add('nom')
            ->add('prenom', null, ['label' => 'Prénom'])
            ->add('adresse')
            ->add('ville')
            ->add('codePostal', null, ['label' => 'Code postal'])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => [
                    'Utilisateur' => 'ROLE_USER',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
            ])
            ->add('suspended', CheckboxType::class, [
                'label' => 'Compte suspendu',
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => !$isEdit,
                'first_options' => [
                    'label' => $isEdit ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe',
                ],
                'second_options' => ['label' => 'Confirmation'],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => $rules,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'edit' => false,
        ]);
    }
}