<?php
// src/Form/QRCodeType.php

namespace App\Form;

use App\Entity\QrCode;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class QrCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Infos principales
            ->add('titre', TextType::class, [
                'label'       => 'Titre / Libellé',
                'attr'        => ['placeholder' => 'ex: Site web principal', 'class' => 'qr-input'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['min' => 2, 'max' => 255])],
            ])
            ->add('type', HiddenType::class)         // géré par les tabs JS
            ->add('contentData', HiddenType::class, [ // JSON des champs dynamiques
                'mapped' => false,
            ])

            // Design
            ->add('couleurPoints', HiddenType::class, [
                'mapped' => false,
                'data'   => '#000000',
            ])
            ->add('couleurFond', HiddenType::class, [
                'mapped' => false,
                'data'   => '#ffffff',
            ])
            ->add('taille', HiddenType::class, [
                'mapped' => false,
                'data'   => '300',
            ])
            ->add('errorCorrection', HiddenType::class, [
                'mapped' => false,
                'data'   => 'H',
            ])

            // Logo upload
            ->add('logoFile', FileType::class, [
                'label'      => false,
                'mapped'     => false,
                'required'   => false,
                'constraints' => [
                    new Assert\File([
                        'maxSize'   => '2M',
                        'mimeTypes' => ['image/png', 'image/jpeg', 'image/svg+xml'],
                    ]),
                ],
            ])

            // Options avancées
            ->add('quotaMaxScans', IntegerType::class, [
                'label'    => 'Limite de scans',
                'required' => false,
                'mapped'   => true,
                'attr'     => ['placeholder' => 'Illimité si vide', 'class' => 'qr-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => QRCode::class,
            'attr'       => ['novalidate' => 'novalidate'],
        ]);
    }
}