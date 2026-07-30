<?php

namespace App\Controller;

use App\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        $success = false;

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $email = (new Email())
                ->from('noreply@escoutances.fr')
                ->to('evan.moreau@etik.com')
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
                    nl2br(htmlspecialchars($data['message']))
                ));

            $mailer->send($email);
            $success = true;
        }

        return $this->render('contact/index.html.twig', [
            'form'    => $form,
            'success' => $success,
        ]);
    }
}