<?php

namespace App\Form;

use App\Entity\DemandeDevis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DemandeDevisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Prénom & Nom',
                'attr' => ['placeholder' => 'Jean Dupont']
            ])
            ->add('societe', TextType::class, [
                'label' => 'Société',
                'attr' => ['placeholder' => 'Mon Entreprise SAS']
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'attr' => ['placeholder' => '06 XX XX XX XX']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['placeholder' => 'contact@societe.fr']
            ])
            ->add('villeDepart', TextType::class, [
                'label' => 'Ville de départ',
                'attr' => ['placeholder' => 'Paris (75)']
            ])
            ->add('destination', TextType::class, [
                'label' => 'Destination',
                'attr' => ['placeholder' => 'Ville ou pays...']
            ])
            ->add('typePrestation', ChoiceType::class, [
                'label' => 'Type de prestation',
                'choices' => [
                    'Sélectionnez...' => '',
                    'Transport express H+2 / H+4' => 'Transport express H+2 / H+4',
                    'Préparation de commandes' => 'Préparation de commandes',
                    'Stockage & gestion de stock' => 'Stockage & gestion de stock',
                    'Transport international Afrique – Europe' => 'Transport international Afrique – Europe',
                    'Tournée régulière B2B' => 'Tournée régulière B2B',
                    'Solution logistique complète' => 'Solution logistique complète',
                    'Autre / Sur mesure' => 'Autre / Sur mesure',
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description de votre besoin',
                'required' => false,
                'attr' => ['placeholder' => 'Type de marchandise, volume, fréquence, délai souhaité, destination (internationale ?)...']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DemandeDevis::class,
        ]);
    }
}
