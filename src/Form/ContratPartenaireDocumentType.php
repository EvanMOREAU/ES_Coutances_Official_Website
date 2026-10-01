<?php

namespace App\Form;

use App\Entity\ContratPartenaireDocument;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Vich\UploaderBundle\Form\Type\VichFileType;

class ContratPartenaireDocumentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('fichierFile', VichFileType::class, [
            'label'       => 'Document',
            'required'    => true,
            'constraints' => [new File(
                maxSize: '10M',
                mimeTypes: ['application/pdf', 'image/jpeg', 'image/png', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                mimeTypesMessage: 'Formats acceptés : PDF, Word, image (max 10 Mo).',
            )],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ContratPartenaireDocument::class]);
    }
}
