<?php
// src/Form/PlanTarifaireType.php

namespace App\Form;

use App\Entity\PlanTarifaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PlanTarifaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label'       => 'Nom du plan',
                'attr'        => ['placeholder' => 'ex: Starter, Pro, Business…', 'class' => 'qr-input'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['min' => 2, 'max' => 255])],
            ])
            ->add('prix', MoneyType::class, [
                'label'       => 'Prix mensuel',
                'currency'    => 'XOF',
                'attr'        => ['placeholder' => '0', 'class' => 'qr-input'],
                'constraints' => [new Assert\NotBlank(), new Assert\PositiveOrZero()],
            ])
            ->add('limiteQR', IntegerType::class, [
                'label'       => 'Limite de QR codes',
                'attr'        => ['placeholder' => 'ex: 10, 50, -1 pour illimité', 'class' => 'qr-input'],
                'constraints' => [new Assert\NotBlank(), new Assert\GreaterThanOrEqual(-1)],
            ])
            ->add('accesAvance', CheckboxType::class, [
                'label'    => 'Accès aux fonctionnalités avancées',
                'required' => false,
                'attr'     => ['class' => 'custom-control-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => PlanTarifaire::class,
            'attr'               => ['novalidate' => 'novalidate'],
        ]);
    }
}