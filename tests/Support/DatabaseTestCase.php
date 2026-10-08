<?php

namespace App\Tests\Support;

use App\Entity\Article;
use App\Entity\ArticleVariante;
use App\Entity\Famille;
use App\Entity\Licencie;
use App\Entity\Saison;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Socle des tests fonctionnels : un seul client, une seule connexion, et tout ce qui est écrit
 * en base est annulé (rollback) à la fin de chaque test. La base « _test » doit exister
 * (voir `composer test:setup`).
 */
abstract class DatabaseTestCase extends WebTestCase
{
    public const PASSWORD = 'Test-Password-123!';

    protected KernelBrowser $client;
    protected EntityManagerInterface $em;
    private int $emailCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
        // Le kernel (et donc la connexion à la base) reste le même entre deux requêtes,
        // sinon la transaction de test serait perdue.
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        while ($connection->isTransactionActive()) {
            $connection->rollBack();
        }
        $this->em->clear();
        parent::tearDown();
    }

    /** @param list<string> $roles */
    protected function createUser(array $roles = [], ?string $email = null, string $password = self::PASSWORD): User
    {
        $user = (new User())
            ->setEmail($email ?? sprintf('user%d-%s@test.local', ++$this->emailCounter, bin2hex(random_bytes(3))))
            ->setNom('Testeur')
            ->setPrenom('Alice')
            ->setRoles($roles);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /** @param list<string> $roles */
    protected function loginAs(array $roles, ?User $user = null): User
    {
        $user ??= $this->createUser($roles);
        $this->client->loginUser($user, 'app_user_context');

        return $user;
    }

    /**
     * Article actif avec une variante par stock donné (une seule variante « Unique » si un seul stock).
     *
     * @param list<int> $stocks
     */
    protected function createArticle(string $nom = 'Maillot', int $prixCentimes = 3500, array $stocks = [10]): Article
    {
        $article = (new Article())
            ->setNom($nom)
            ->setSlug(strtolower((string) preg_replace('/\W+/', '-', $nom)).'-'.bin2hex(random_bytes(3)))
            ->setPrixCentimes($prixCentimes);
        foreach ($stocks as $i => $stock) {
            $article->addVariante((new ArticleVariante())->setLibelle(1 === \count($stocks) ? ArticleVariante::LIBELLE_UNIQUE : 'T'.($i + 1))->setStock($stock)->setOrdre($i));
        }
        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    protected function createSaison(string $libelle, string $debut, string $fin, bool $active = false): Saison
    {
        $saison = (new Saison())
            ->setLibelle($libelle)
            ->setDateDebut(new \DateTimeImmutable($debut))
            ->setDateFin(new \DateTimeImmutable($fin))
            ->setActive($active);
        $this->em->persist($saison);
        $this->em->flush();

        return $saison;
    }

    /** Un licencié avec son compte et sa famille. */
    protected function createLicencie(Saison $saison, string $nom = 'Martin', string $prenom = 'Lucas', string $naissance = '2014-04-12'): Licencie
    {
        $famille = (new Famille())->setNom($nom)->setUser($this->createUser(['ROLE_FAMILLE']));
        $licencie = (new Licencie())
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setDateNaissance(new \DateTimeImmutable($naissance))
            ->setFamille($famille)
            ->setUser($this->createUser(['ROLE_LICENCIE']))
            ->setSaison($saison);
        $this->em->persist($famille);
        $this->em->persist($licencie);
        $this->em->flush();

        return $licencie;
    }

    protected function loginAsDev(): User
    {
        return $this->loginAs(['ROLE_DEV']);
    }
}
