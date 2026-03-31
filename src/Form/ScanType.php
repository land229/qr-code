<?php

namespace App\Form;

use App\Entity\QrCode;
use App\Entity\Scan;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ScanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('dateHeure')
            ->add('ipAdresse')
            ->add('ville')
            ->add('pays')
            ->add('appareil')
            ->add('navigateur')
            ->add('qrCode', EntityType::class, [
                'class' => QrCode::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Scan::class,
        ]);
    }
}
