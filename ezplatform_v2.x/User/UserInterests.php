<?php
/**
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace EzSystems\ProfilerBlockBundle\User;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\PermissionResolver;
use eZ\Publish\API\Repository\UserService;
use Netgen\TagsBundle\Core\Repository\TagsService;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserInterests
{
    /** @var \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface */
    private $tokenStorage;

    /** @var \eZ\Publish\API\Repository\ContentService */
    private $contentService;

    /** @var \eZ\Publish\API\Repository\ContentTypeService */
    private $contentTypeService;

    /** @var \eZ\Publish\API\Repository\UserService */
    private $userService;

    /** @var \eZ\Publish\API\Repository\PermissionResolver */
    private $permissionResolver;

    /** @var TagsService */
    private $tagsService;

    /**
     * @param \Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface $tokenStorage
     * @param \eZ\Publish\API\Repository\ContentService $contentService
     * @param \eZ\Publish\API\Repository\ContentTypeService $contentTypeService
     * @param \eZ\Publish\API\Repository\UserService $userService
     * @param \eZ\Publish\API\Repository\PermissionResolver $permissionResolver
     */
    public function __construct(
        TokenStorageInterface $tokenStorage,
        ContentService $contentService,
        ContentTypeService $contentTypeService,
        UserService $userService,
        PermissionResolver $permissionResolver,
        TagsService $tagsService
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->contentService = $contentService;
        $this->contentTypeService = $contentTypeService;
        $this->userService = $userService;
        $this->permissionResolver = $permissionResolver;
        $this->tagsService = $tagsService;
    }

    /**
     * Returns logged user interests.
     *
     * @return array
     */
    public function getListForLoggedUser()
    {
        if (!$apiUserFieldValue = $this->getFieldValueForLoggedUser()) {
            return [];
        }

        return array_map(function ($tag) { return $tag->id; }, $apiUserFieldValue->tags);
    }

    /**
     * Gets available choices for `interests` field.
     *
     * @return array
     */
    public function getAvailableChoices()
    {
        $subTreeLimit = $this->contentTypeService
            ->loadContentTypeByIdentifier('user')
            ->getFieldDefinition('interests')
            ->validatorConfiguration['TagsValueValidator']['subTreeLimit'];

        $userInterestsTags = $this->tagsService->loadTagChildren(
            $this->tagsService->loadTag($subTreeLimit)
        );

        $userInterestsList = [];

        foreach ($userInterestsTags as $tag) {
            $userInterestsList[$tag->keyword] = $tag->id;
        }

        return $userInterestsList;
    }

    /**
     * Updates user Interests by tags id list.
     *
     * @param array $tagsIdsList
     */
    public function updateUserInterests($tagsIdsList)
    {
        $this->permissionResolver->setCurrentUserReference(
            $this->userService->loadUserByLogin('admin')
        );

        $updatedTags = $this->getFieldValueForLoggedUser();
        $updatedTags->tags = $this->loadTagsByTagsIds($tagsIdsList);

        $contentUpdateStruct = $this->contentService->newContentUpdateStruct();
        $contentUpdateStruct->setField('interests', $updatedTags);

        $userUpdateStruct = $this->userService->newUserUpdateStruct();
        $userUpdateStruct->contentUpdateStruct = $contentUpdateStruct;

        $this->userService->updateUser($this->getCurrentApiUser(), $userUpdateStruct);
    }

    /**
     * Loads tags by tags id list.
     *
     * @param array $tagsIdsList
     *
     * @return \Netgen\TagsBundle\API\Repository\Values\Tags\Tag[]
     */
    private function loadTagsByTagsIds($tagsIdsList)
    {
        return array_map(function ($tagId) { return $this->tagsService->loadTag($tagId); }, $tagsIdsList);
    }

    /**
     * Gets value of `interests` field for logged user.
     *
     * @return \Netgen\TagsBundle\Core\FieldType\Tags\Value
     *
     * @throws AccessDeniedException If user is not logged in.
     */
    private function getFieldValueForLoggedUser()
    {
        if (!$currentUser = $this->getCurrentApiUser()) {
            throw new AccessDeniedException();
        }

        return $currentUser->content->getField('interests')->value;
    }

    /**
     * Get a Api user from the Security Token Storage.
     *
     * @return mixed
     */
    private function getCurrentApiUser()
    {
        $token = $this->tokenStorage->getToken();
        if (false == $token || !is_object($token->getUser())) {
            return false;
        }

        return $token->getUser()->getAPIUser();
    }
}
