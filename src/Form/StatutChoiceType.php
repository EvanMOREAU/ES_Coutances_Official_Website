<?php

namespace App\Form;

use App\Entity\Concern\StatutTrait;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Choix du statut d'un enregistrement : actif, brouillon ou archivé. */
class StatutChoiceType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label'   => 'Statut',
            'choices' => array_flip(self::labels()),
            'help'    => "Un brouillon ou un enregistrement archivé est conservé mais n'est plus considéré comme actif.",
        ]);
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    /** @return array<string, string> */
    private static function labels(): array
    {
        // Le trait n'expose ses constantes que via une classe qui l'utilise.
        return \App\Entity\Equipe::STATUTS;
    }
}
