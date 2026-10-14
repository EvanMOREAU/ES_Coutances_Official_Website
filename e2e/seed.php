<?php

// Données minimales pour les tests de bout en bout : un article en stock et un administrateur.
// Lance le noyau en environnement « test » (base *_test) ; affiche un JSON sur la dernière ligne.
use App\Entity\Article;
use App\Entity\ArticleVariante;
use App\Entity\User;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

require dirname(__DIR__).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
/** @var EntityManagerInterface $em */
$em = $container->get(EntityManagerInterface::class);

$slug = 'e2e-maillot';
$article = $em->getRepository(Article::class)->findOneBy(['slug' => $slug]);
if (null === $article) {
    $article = (new Article())->setNom('Maillot E2E')->setSlug($slug)->setPrixCentimes(3500)->setDescription('Article des tests de bout en bout.');
    $article->addVariante((new ArticleVariante())->setLibelle(ArticleVariante::LIBELLE_UNIQUE)->setStock(50)->setOrdre(0));
    $em->persist($article);
}

$email = 'e2e-admin@test.local';
$password = 'E2e-Password-123!';
$admin = $em->getRepository(User::class)->findOneBy(['email' => $email]);
if (null === $admin) {
    $admin = (new User())->setEmail($email)->setNom('E2E')->setPrenom('Admin')->setRoles(['ROLE_ADMIN']);
    $admin->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($admin, $password));
    $em->persist($admin);
}

$clientEmail = 'e2e-client@test.local';
if (null === $em->getRepository(User::class)->findOneBy(['email' => $clientEmail])) {
    $client = (new User())->setEmail($clientEmail)->setNom('Durand')->setPrenom('Camille')->setRoles([]);
    $client->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($client, $password));
    $em->persist($client);
}
$em->flush();

echo json_encode(['article' => $slug, 'admin' => $email, 'client' => $clientEmail, 'password' => $password]), "\n";
