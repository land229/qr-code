<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;


class RegisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstname',TextType::class,[
                //'constraints'=>new Length(2,8)
            ])
            ->add('lastname',TextType::class,[
                //'constraints'=>new Length(2,8)
            ])
            ->add('email',EmailType::class,[
                //'constraints'=>new Length(5,8)
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => "Les mots de passe doivent être identiques",
                'required' => true,
                'first_options' => [
                    'label' => "Mot de passe",
                    'label_attr' => ['class' => 'form-label'],
                    'attr' => [
                        'class' => 'form-control form-control-lg',
                        'placeholder' => 'Entrez votre mot de passe',
                        'data-target' => 'password-first', // Ajout d'un attribut pour le ciblage
                    ],
                ],
                'second_options' => [
                    'label' => "Confirmer votre mot de passe",
                    'label_attr' => ['class' => 'form-label'],
                    'attr' => [
                        'class' => 'form-control form-control-lg',
                        'placeholder' => 'Confirmez votre mot de passe',
                        'data-target' => 'password-second', // Ajout d'un attribut pour le ciblage
                    ],
                ],
            ])
            ->add('acceptTerms', CheckboxType::class, [
                'mapped' => true, // Ne pas enregistrer automatiquement dans l'entité
                'label_html' => true, // Permet de rendre le HTML dans le label
                'constraints' => [
                    new IsTrue([
                        'message' => 'Vous devez accepter la politique de confidentialité et les conditions.',
                    ]),
                ],
            ])
            ->add('submit',SubmitType::class,[
                "label"=>"s'inscrire",
                "attr"=>[
                    "class"=>'btn btn-lg btn-primary btn-block'
                ]
            ]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
