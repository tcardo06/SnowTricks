<?php

namespace App\Controller;

use App\Entity\Token;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If the user is already logged in, redirect to home
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        // Get the login error, if any
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/mot-de-passe-oublie', name: 'forgot_password')]
    public function forgotPassword(Request $request, EntityManagerInterface $entityManager, MailerInterface $mailer): Response
    {
        if ($request->isMethod('POST')) {
            $username = $request->request->get('username');
    
            $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
    
            if (!$user) {
                $this->addFlash('danger', 'Aucun compte associé à ce nom d\'utilisateur.');
                return $this->redirectToRoute('forgot_password');
            }
    
            // Generate a reset token
            $token = new Token();
            $token->setUser($user)
                  ->setToken(bin2hex(random_bytes(32)))
                  ->setType('password_reset')
                  ->setExpiresAt((new \DateTime())->modify('+1 hour'));
    
            $entityManager->persist($token);
            $entityManager->flush();
    
            // Send email with the reset link
            $email = (new TemplatedEmail())
                ->from('no-reply@snowtricks.com')
                ->to($user->getEmail()) // Uses email from found user
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('emails/reset_password.html.twig')
                ->context([
                    'user' => $user,
                    'resetLink' => $this->generateUrl('reset_password', ['token' => $token->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
                ]);
    
            $mailer->send($email);
    
            $this->addFlash('success', 'Un e-mail de réinitialisation a été envoyé.');
            return $this->redirectToRoute('app_login');
        }
    
        return $this->render('security/forgot_password.html.twig');
    }    

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'reset_password')]
    public function resetPassword(string $token, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        $tokenEntity = $em->getRepository(Token::class)->findOneBy(['token' => $token, 'type' => 'password_reset']);

        if (!$tokenEntity || $tokenEntity->getExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'Le lien est invalide ou expiré.');
            return $this->redirectToRoute('forgot_password');
        }

        $user = $tokenEntity->getUser();

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);

            $em->remove($tokenEntity);
            $em->flush();

            $this->addFlash('success', 'Votre mot de passe a été mis à jour. Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
        ]);
    }
}
