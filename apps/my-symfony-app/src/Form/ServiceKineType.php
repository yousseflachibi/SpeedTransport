<?php

namespace App\Form;

use App\Entity\ServiceKine;
use App\Entity\CategorieServiceKine;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;

class ServiceKineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du service',
            ])
            ->add('categorie', EntityType::class, [
                'class' => CategorieServiceKine::class,
                'choice_label' => 'nom',
                'label' => 'Catégorie',
            ])
            ->add('price', TextType::class, [
                'label' => 'Prix',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ServiceKine::class,
        ]);
    }
}
