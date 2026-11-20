<?php

namespace App\Controller;

use App\Entity\ContactMessage;
use App\Form\ContactType;
use App\Repository\ContactSettingsRepository;
use App\Service\Notification\NotificationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    /**
     * Adresse utilisée tant qu'aucun email de contact n'a été configuré
     * depuis l'admin (Administration > Page de contact).
     */
    private const DEFAULT_CONTACT_EMAIL = 'evan.moreau@etik.com';

    /** Délai minimal, en secondes, entre deux messages envoyés depuis la même session. */
    private const COOLDOWN_SECONDS = 60;

    private const SESSION_LAST_SENT = 'contact_last_sent';

    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer, ContactSettingsRepository $contactSettingsRepo, LoggerInterface $logger, NotificationMailer $notifications, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        $contactSettings = $contactSettingsRepo->getSingleton();

        if ($form->isSubmitted() && $form->isValid()) {
            $session = $request->getSession();
            $elapsed = time() - (int) $session->get(self::SESSION_LAST_SENT, 0);
            if ($elapsed < self::COOLDOWN_SECONDS) {
                // Double clic, F5 ou envois en rafale : rien n'est enregistré, le formulaire garde sa saisie.
                $form->addError(new FormError(sprintf("Votre message vient d'être envoyé. Merci de patienter %d seconde(s) avant d'en envoyer un autre.", self::COOLDOWN_SECONDS - $elapsed)));

                return $this->render('contact/index.html.twig', [
                    'form'             => $form,
                    'success'          => false,
                    'contact_settings' => $contactSettings,
                ], new Response(status: Response::HTTP_TOO_MANY_REQUESTS));
            }
            $session->set(self::SESSION_LAST_SENT, time());

            $data = $form->getData();

            // Le message est d'abord conservé en base : il reste consultable dans l'admin même si l'e-mail ne part pas.
            $saved = (new ContactMessage())
                ->setNom((string) $data['nom'])
                ->setEmail((string) $data['email'])
                ->setTelephone($data['telephone'] ?? null)
                ->setType((string) $data['type'])
                ->setSujet((string) $data['sujet'])
                ->setMessage((string) $data['message']);
            $em->persist($saved);
            $em->flush();
            $notifications->contactReceived((string) $data['nom'], (string) $data['email'], (string) $data['sujet'], (string) $data['message']);

            $contactEmail = $contactSettings?->getEmail() ?: self::DEFAULT_CONTACT_EMAIL;

            $email = (new Email())
                ->from('noreply@escoutances.fr')
                ->to($contactEmail)
                ->replyTo($data['email'])
                ->subject('[Contact ESC] ' . $data['sujet'])
                ->html(sprintf(
                    '<h2>Nouveau message depuis le formulaire de contact</h2>
                    <p><strong>Nom :</strong> %s</p>
                    <p><strong>Email :</strong> %s</p>
                    <p><strong>Téléphone :</strong> %s</p>
                    <p><strong>Type de demande :</strong> %s</p>
                    <p><strong>Sujet :</strong> %s</p>
                    <hr>
                    <p><strong>Message :</strong><br>%s</p>',
                    htmlspecialchars($data['nom']),
                    htmlspecialchars($data['email']),
                    htmlspecialchars($data['telephone'] ?? 'Non renseigné'),
                    htmlspecialchars($data['type']),
                    htmlspecialchars($data['sujet']),
                    nl2br(htmlspecialchars($data['message'])),
                ));

            try {
                $mailer->send($email);
            } catch (TransportExceptionInterface $e) {
                // Panne du serveur d'e-mails : le message est déjà enregistré et visible dans l'admin, le visiteur n'a rien à refaire.
                $logger->error('E-mail du formulaire de contact non envoyé (message #{id} conservé en base) : {message}', ['id' => $saved->getId(), 'message' => $e->getMessage()]);
            }

            // Post/Redirect/Get : le formulaire revient vide et un rechargement de la page ne renvoie plus le message.
            $this->addFlash('contact_success', 'sent');

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/index.html.twig', [
            'form'             => $form,
            // On ne touche à la session que si le visiteur en a déjà une : une page vue sans cookie reste publique (cache).
            'success'          => $request->hasPreviousSession() && ($session = $request->getSession()) instanceof FlashBagAwareSessionInterface && [] !== $session->getFlashBag()->get('contact_success'),
            'contact_settings' => $contactSettings,
        ]);
    }
}
