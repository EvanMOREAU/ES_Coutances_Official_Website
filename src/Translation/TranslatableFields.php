<?php

namespace App\Translation;

/** Lecture des champs marqués #[Translatable] d'une entité. */
final class TranslatableFields
{
    /** @var array<class-string, array<string, bool>> */
    private static array $cache = [];

    /** @return array<string, bool> nom de propriété => contient du HTML */
    public static function of(object $entity): array
    {
        return self::ofClass($entity::class);
    }

    /**
     * @param class-string $class
     *
     * @return array<string, bool>
     */
    public static function ofClass(string $class): array
    {
        if (!isset(self::$cache[$class])) {
            $fields = [];
            foreach ((new \ReflectionClass($class))->getProperties() as $property) {
                foreach ($property->getAttributes(Translatable::class) as $attribute) {
                    $fields[$property->getName()] = $attribute->newInstance()->html;
                }
            }
            self::$cache[$class] = $fields;
        }

        return self::$cache[$class];
    }

    public static function read(object $entity, string $property): ?string
    {
        $value = (new \ReflectionProperty($entity, $property))->getValue($entity);

        return \is_string($value) ? $value : null;
    }
}
