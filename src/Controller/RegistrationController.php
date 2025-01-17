<?php

namespace App\Controller;

use App\Entity\Token;
use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/inscription', name: 'registration')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        MailerInterface $mailer
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $hashedPassword = $passwordHasher->hashPassword($user, $user->getPassword());
            $user->setPassword($hashedPassword);

            // Set the user as inactive
            $user->setIsActive(false);

            try {
                $em->persist($user);
                $em->flush();

                // Generate the token
                $token = new Token();
                $token->setUser($user)
                    ->setToken(bin2hex(random_bytes(32)))
                    ->setType('email_verification')
                    ->setExpiresAt((new \DateTime())->modify('+1 day'));
                $em->persist($token);
                $em->flush();

                // Send verification email
                $email = (new TemplatedEmail())
                    ->from('no-reply@snowtricks.com')
                    ->to($user->getEmail())
                    ->subject('Confirmez votre inscription')
                    ->htmlTemplate('emails/registration.html.twig')
                    ->context([
                        'user' => $user,
                        'validationLink' => $this->generateUrl('verify_email', ['token' => $token->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
                    ]);
                $mailer->send($email);

                $this->addFlash('success', 'Un e-mail de confirmation vous a été envoyé.');
                return $this->redirectToRoute('home');
            } catch (UniqueConstraintViolationException $e) {
                // Handle duplicate email or username errors
                $this->addFlash('error', 'Nom d\'utilisateur ou e-mail déjà utilisé.');
            }
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/verifier-email/{token}', name: 'verify_email')]
    public function verifyEmail(string $token, EntityManagerInterface $em): Response
    {
        $tokenRepository = $em->getRepository(Token::class);
        $tokenEntity = $tokenRepository->findOneBy(['token' => $token, 'type' => 'email_verification']);

        if (!$tokenEntity || $tokenEntity->getExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'Le token est invalide ou expiré.');
            return $this->redirectToRoute('home');
        }

        $user = $tokenEntity->getUser();
        $user->setIsActive(true);
        $em->remove($tokenEntity); // Delete the token after use
        $em->flush();

        $this->addFlash('success', 'Votre compte a été activé avec succès !');
        return $this->redirectToRoute('home');
    }
}
