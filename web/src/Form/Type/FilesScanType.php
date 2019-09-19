<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

class FilesScanType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('updateExistingEntries', CheckboxType::class, [
                'required' => false,
            ])
            ->add('actions', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'attr' => [
                    'class' => 'collection',
                ],
                'help' => 'Available options: "meta", "cache", "geocode", "label" and "faces". ' .
                    'You can also use "geocode:force" instead of "geocode", ' .
                    'if you want to forcefully get new data from the API. ' .
                    'Same goes for "label" ("label:force") and "faces" ("faces:label").',
            ])
            ->add('folders', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                ],
                'allow_add' => true,
                'allow_delete' => true,
                'attr' => [
                    'class' => 'collection',
                ],
            ])
            ->add('execute', SubmitType::class)
        ;
    }
}
