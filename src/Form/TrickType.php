<?php

namespace App\Form;

use App\Entity\Trick;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TrickType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la figure',
                'attr' => ['placeholder' => 'Nom de la figure']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 4, 'placeholder' => 'Décrivez la figure']
            ])
            ->add('group_name', TextType::class, [
                'label' => 'Groupe de la figure',
                'attr' => ['placeholder' => 'Ex: Grabs, Flips, etc.']
            ])
            ->add('images', FileType::class, [
                'label' => 'Illustrations',
                'mapped' => false,
                'required' => false,
                'multiple' => true
            ])
            ->add('videos', CollectionType::class, [
                'label' => 'Vidéos (balise embed)',
                'entry_type' => TextareaType::class, 
                'entry_options' => [
                    'attr' => [
                        'rows' => 3, 
                        'placeholder' => 'Collez ici le code embed de la vidéo (ex: YouTube, Dailymotion)',
                        'class' => 'form-control'
                    ]
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'prototype' => true,
                'by_reference' => false,
                'mapped' => false,  // Prevent automatic mapping to `Trick::$videos`
            ]);                      
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Trick::class,
        ]);
    }
}
