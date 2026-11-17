<?php

namespace App\Controller\Admin;

use App\Entity\ContactMessage;
use App\Repository\ContactMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Messages reçus par le formulaire de la page de contact : boîte de réception, lecture détaillée, archivage, suppression. */
#[Route('/admin/messages-contact')]
class ContactMessageController extends AbstractController
{
    #[Route('', name: 'admin_message_contact_index', methods: ['GET'])]
    public function index(ContactMessageRepository $repository): Response
    {
        return $this->render('admin/contact_message/index.html.twig', [
            'messages' => $repository->findAllRecent(),
            'unread'   => $repository->countUnread(),
        ]);
    }

    /** Ouvrir un message le marque comme lu. */
    #[Route('/{id}', name: 'admin_message_contact_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(ContactMessage $message, EntityManagerInterface $em): Response
    {
        if (!$message->isRead()) {
            $message->markRead();
            $em->flush();
        }

        return $this->render('admin/contact_message/show.html.twig', ['message' => $message]);
    }

    /** Marquer comme lu / non lu, archiver / désarchiver (action = lu | non_lu | archiver | desarchiver). */
    #[Route('/{id}/{action}', name: 'admin_message_contact_traiter', requirements: ['id' => '\d+', 'action' => '(?!supprimer$)[a-z_]+'], methods: ['POST'])]
    public function process(Request $request, ContactMessage $message, string $action, EntityManagerInterface $em): Response
    {
        if (!in_array($action, ['lu', 'non_lu', 'archiver', 'desarchiver'], true)) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('message-contact-'.$message->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide, veuillez réessayer.');

            return $this->redirectToRoute('admin_message_contact_index');
        }

        match ($action) {
            'lu'         => $message->markRead(),
            'non_lu'     => $message->markUnread(),
            'archiver'   => $message->setArchived(true)->markRead(),
            'desarchiver' => $message->setArchived(false),
        };
        $em->flush();

        if ('non_lu' === $action || 'archiver' === $action || 'desarchiver' === $action) {
            $this->addFlash('success', match ($action) {
                'non_lu'     => 'Message marqué comme non lu.',
                'archiver'   => 'Message archivé.',
                'desarchiver' => 'Message remis dans la boîte de réception.',
            });
        }

        // Depuis la liste on y retourne ; depuis la fiche on y reste.
        return 'index' === $request->query->get('_back')
            ? $this->redirectToRoute('admin_message_contact_index')
            : $this->redirectToRoute('admin_message_contact_show', ['id' => $message->getId()]);
    }

    #[Route('/{id}/supprimer', name: 'admin_message_contact_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, ContactMessage $message, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('message-contact-'.$message->getId(), (string) $request->request->get('_token'))) {
            $em->remove($message);
            $em->flush();
            $this->addFlash('success', 'Message supprimé.');
        }

        return $this->redirectToRoute('admin_message_contact_index');
    }
}
