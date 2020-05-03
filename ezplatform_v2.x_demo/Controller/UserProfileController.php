<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\ProfilerBlockBundle\Controller;

use EzSystems\ProfilerBlockBundle\Entity\UserPreferences;
use EzSystems\ProfilerBlockBundle\Form\Type\UserPreferencesType;
use EzSystems\ProfilerBlockBundle\User\UserInterests;
use Netgen\TagsBundle\Core\Repository\TagsService;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserProfileController
{
    /** @var \Symfony\Bundle\FrameworkBundle\Templating\EngineInterface */
    private $templating;

    /** @var \Symfony\Component\Form\FormFactoryInterface */
    private $formFactory;

    /** @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface */
    private $tokenStorage;

    /** @var \Symfony\Component\Routing\RouterInterface */
    private $router;

    /** @var \Netgen\TagsBundle\Core\Repository\TagsService */
    private $tagsService;

    /** @var \EzSystems\ProfilerBlockBundle\User\UserInterests */
    private $userInterests;

    /**
     * UserProfileController constructor.
     * @param EngineInterface $templating
     * @param FormFactoryInterface $formFactory
     * @param TokenStorageInterface $tokenStorage
     * @param RouterInterface $router
     * @param TagsService $tagsService
     * @param UserInterests $userInterests
     */
    public function __construct(
        EngineInterface $templating,
        FormFactoryInterface $formFactory,
        TokenStorageInterface $tokenStorage,
        RouterInterface $router,
        TagsService $tagsService,
        UserInterests $userInterests
    ) {
        $this->templating = $templating;
        $this->formFactory = $formFactory;
        $this->tokenStorage = $tokenStorage;
        $this->router = $router;
        $this->tagsService = $tagsService;
        $this->userInterests = $userInterests;
    }

    /**
     * Shows user preferences form.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function showPreferencesAction(Request $request)
    {
        if (!$this->getUser()) {
            return new RedirectResponse($this->router->generate('login'));
        }

        $form = $this->formFactory->create(UserPreferencesType::class, $this->populateUserPreferencesEntity());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->userInterests->updateUserInterests($form->getData()->interests);

            $request->getSession()
                ->getFlashBag()
                ->add('success', 'Your preferences has been saved!');

            return new RedirectResponse($this->router->generate('ezsystems.user_preferences'));
        }

        return $this->templating->renderResponse('EzSystemsProfilerBlockBundle:user:preferences.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Populates UserPreferences Entity with logged user interests.
     *
     * @return UserPreferences
     */
    private function populateUserPreferencesEntity()
    {
        $entity = new UserPreferences();
        $entity->interests = $this->userInterests->getListForLoggedUser();

        return $entity;
    }

    /**
     * Get a user from the Security Token Storage.
     *
     * @return mixed
     */
    private function getUser()
    {
        $token = $this->tokenStorage->getToken();
        if (false == $token || !is_object($token->getUser())) {
            return false;
        }

        return $token->getUser();
    }
}