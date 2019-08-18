<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

final class FileAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper->add('id', TextType::class);
        $formMapper->add('hash', TextType::class);
        $formMapper->add('type', TextType::class);
        $formMapper->add('path', TextType::class);
        $formMapper->add('mime', TextType::class);
        $formMapper->add('extension', TextType::class);
        $formMapper->add('createdAt', DateTimeType::class);
        $formMapper->add('modifiedAt', DateTimeType::class);
        $formMapper->add('takenAt', DateTimeType::class);
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper->add('id');
        $datagridMapper->add('hash');
        $datagridMapper->add('type');
        $datagridMapper->add('path');
        $datagridMapper->add('mime');
        $datagridMapper->add('extension');
        $datagridMapper->add('createdAt');
        $datagridMapper->add('modifiedAt');
        $datagridMapper->add('takenAt');
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper->addIdentifier('id');
        $listMapper->add('hash');
        $listMapper->add('type');
        $listMapper->add('path');
        $listMapper->add('mime');
        $listMapper->add('extension');
        $listMapper->add('createdAt');
        $listMapper->add('modifiedAt');
        $listMapper->add('takenAt');
    }
}
