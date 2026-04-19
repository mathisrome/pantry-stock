<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\LoginRequestType;
use App\Repository\UserRepository;
use App\Security\EmailHasher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        EmailHasher $emailHasher,
        UserRepository $users,
        EntityManagerInterface $em,
        LoginLinkHandlerInterface $loginLinkHandler,
        MailerInterface $mailer,
    ): Response {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_pantry');
        }

        $form = $this->createForm(LoginRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();
            $hash = $emailHasher->hash($email);

            $user = $users->findOneByEmailHash($hash);
            if ($user === null) {
                $user = (new User())->setEmailHash($hash);
                $em->persist($user);
                $em->flush();
            }

            $loginLinkDetails = $loginLinkHandler->createLoginLink($user);
            $expiresInMinutes = max(1, (int) ceil(($loginLinkDetails->getExpiresAt()->getTimestamp() - time()) / 60));

            $mailer->send(
                (new TemplatedEmail())
                    ->to(new Address($email))
                    ->subject('Votre lien de connexion Pantry Stock')
                    ->htmlTemplate('emails/login_link.html.twig')
                    ->context([
                        'signed_url' => $loginLinkDetails->getUrl(),
                        'expires_in_minutes' => $expiresInMinutes,
                    ]),
            );

            $this->addFlash('success', 'Si un compte correspond à cet email, un lien de connexion vient d’être envoyé.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/login.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/login/check', name: 'app_login_check', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function check(): never
    {
        throw new \LogicException('This method is intercepted by the login_link authenticator on the firewall.');
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the logout key on the firewall.');
    }
}
