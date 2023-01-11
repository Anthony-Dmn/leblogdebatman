<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Recaptcha\RecaptchaValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationController extends AbstractController
{

    /**
     * Contrôleur de la page d'inscription
     */
    #[Route('/creer-un-compte/', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, RecaptchaValidator $recaptcha): Response
    {

        // Si l'utilisateur est déjà connecté, on le redirige de force sur la page d'accueil du site
        if ($this->getUser()) {
            return $this->redirectToRoute('main_home');
        }

        // Création d'un nouvel objet utilisateur
        $user = new User();

        // Création d'un nouveau formulaire de création de compte, "branché" sur $user (pour l'hydrater)
        $form = $this->createForm(RegistrationFormType::class, $user);

        // Remplissage du formulaire avec les données POST (qui sont $request)
        $form->handleRequest($request);

        // Si le formulaire a bien été envoyé
        if ($form->isSubmitted()) {

            // Récupération de la valeur du captcha ( $_POST['g-recaptcha-response'] )
            $captchaResponse = $request->request->get('g-recaptcha-response', null);

            // Récupération de l'adresse IP de l'utilisateur ( $_SERVER['REMOTE_ADDR'] )
            $ip = $request->server->get('REMOTE_ADDR');

            // Si le captcha est null ou s'il est invalide, on ajoute une erreur dans le formulaire
            if( $captchaResponse == null || !$recaptcha->verify($captchaResponse, $ip) ){

                $form->addError( new FormError('Veuillez remplir le captcha de sécurité') );

            }

            if($form->isValid()){

                // encode the plain password
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $form->get('plainPassword')->getData()
                    )
                );

                // Hydratation de la date d'inscription du nouvel utilisateur
                $user->setRegistrationDate( new \DateTime() );

                // Sauvegarde du nouvel utilisateur en BDD
                $entityManager->persist($user);
                $entityManager->flush();

                // Message flash de succès
                $this->addFlash('success', 'Votre compte a bien été créé avec succès !');

                // Redirection de l'utilisateur vers la page de connexion
                return $this->redirectToRoute('app_login');

            }

        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }
}
