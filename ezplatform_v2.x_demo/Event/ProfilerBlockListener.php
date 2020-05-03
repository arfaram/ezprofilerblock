<?php

namespace EzSystems\ProfilerBlockBundle\Event;

use eZ\Publish\API\Repository\LocationService;
use eZ\Publish\API\Repository\UserService;
use EzSystems\EzPlatformPageFieldType\Event\BlockResponseEvent;
use EzSystems\EzPlatformPageFieldType\Event\BlockResponseEvents;
use EzSystems\EzPlatformPageFieldType\FieldType\Page\Block\Renderer\Twig\TwigRenderRequest;
use EzSystems\PlatformHttpCacheBundle\Handler\TagHandler;
use Netgen\TagsBundle\Core\Repository\TagsService;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use EzSystems\EzPlatformPageFieldType\FieldType\Page\Block\Renderer\BlockRenderEvents;
use EzSystems\EzPlatformPageFieldType\FieldType\Page\Block\Renderer\Event\PreRenderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use eZ\Publish\API\Repository\SearchService;
use eZ\Publish\Core\QueryType\QueryTypeRegistry;
use eZ\Publish\API\Repository\ContentService;

/**
 * Class ProfilerBlockListener.
 */
class ProfilerBlockListener implements EventSubscriberInterface
{
    /**
     * @var SearchService
     */
    private $searchService;
    /**
     * @var QueryTypeRegistry
     */
    private $queryTypeRegistry;
    /**
     * @var ContentService
     */
    private $contentService;
    /**
     * @var UserService
     */
    private $userService;

    /*
     * @var TokenStorageInterface
     */
    private $tokenStorage;
    /**
     * @var TagsService
     */
    private $tagsService;
    /**
     * @var
     */
    private $languages;
    /**
     * @var LocationService
     */
    private $locationService;
    /**
     * @var TagHandler
     */
    private $tagHandler;

    /**
     * ProfilerBlockListener constructor.
     * @param ContentService $contentService
     * @param LocationService $locationService
     * @param SearchService $searchService
     * @param QueryTypeRegistry $queryTypeRegistry
     * @param UserService $userService
     * @param TokenStorageInterface $tokenStorage
     * @param TagsService $tagsService
     * @param TagHandler $tagHandler
     * @param $languages
     */
    public function __construct(
        ContentService $contentService,
        LocationService $locationService,
        SearchService $searchService,
        QueryTypeRegistry $queryTypeRegistry,
        UserService $userService,
        TokenStorageInterface $tokenStorage,
        TagsService $tagsService,
        TagHandler $tagHandler,
        $languages
    ) {
        $this->searchService = $searchService;
        $this->queryTypeRegistry = $queryTypeRegistry;
        $this->contentService = $contentService;
        $this->userService = $userService;
        $this->tokenStorage = $tokenStorage;
        $this->tagsService = $tagsService;
        $this->languages = $languages;
        $this->locationService = $locationService;
        $this->tagHandler = $tagHandler;
    }

    /**
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            BlockRenderEvents::getBlockPreRenderEventName('profiler') => 'onBlockPreRender',
            BlockResponseEvents::getBlockResponseEventName('profiler') => ['onBlockResponse', -200],
            //BlockResponseEvents::BLOCK_RESPONSE => ['onBlockResponse', -200],
        ];
    }

    /**
     * @param PreRenderEvent $event
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function onBlockPreRender(PreRenderEvent $event)
    {
        $blockValue = $event->getBlockValue();

        $renderRequest = $event->getRenderRequest();
        $parameters = $renderRequest->getParameters();
        $contentType = $blockValue->getAttribute('contentType')->getValue();
        $contentId = $blockValue->getAttribute('contentId')->getValue();
        $depth = $blockValue->getAttribute('depth')->getValue();

        $contentInfo = $this->contentService->loadContentInfo($contentId);
        $location = $this->locationService->loadLocation($contentInfo->mainLocationId);

        $queryType = $this->queryTypeRegistry->getQueryType('EventList');

        $login = $this->tokenStorage->getToken()->getUsername();

        $userContent = $this->userService->loadUserByLogin($login);
        $userInterestsFieldValue = $userContent->getFieldValue('interests');

        $siteaccessLang = $this->languages[0];

        $userInterests = [];

        foreach ($userInterestsFieldValue->tags as $tagValue) {
            $userInterests[] = $tagValue->keywords[$siteaccessLang] ?? $tagValue->keywords[$tagValue->mainLanguageCode] ;
            //$tag = $this->tagsService->loadTag($tagContent->id);
        }

        $blockTagsValue = $blockValue->getAttribute('tags')->getValue();

        if ($blockTagsValue === null ) {
            $parameters['results'] = '';
            /** @var TwigRenderRequest $renderRequest */
            $renderRequest->setParameters($parameters);
            return;
        }
        $tagsValue = json_decode($blockTagsValue, true);
        $keywords = [];

        foreach ($tagsValue as $value){
            $mainLanguage = $value['main_language_code'];
            $keywords[] = $value['keywords'][$mainLanguage];
        }


        // user doesn't have any interests
        if (\count($userInterests) < 0) {
            $parameters['results'] = '';
            $renderRequest->setParameters($parameters);

            return;
        }

        $commonTags = array_intersect(array_map('strtolower', array_map('trim', $keywords)), array_map('strtolower', $userInterests));

        if (\count($commonTags) < 1) {
            $parameters['results'] = '';
            /** @var TwigRenderRequest $renderRequest */
            $renderRequest->setParameters($parameters);

            return;
        }

        $query = $queryType->getQuery(
            [
                'location' => $location,
                'depth' => $depth,
                'contentType' => $contentType,
                'tags' => $commonTags,
            ]
        );

        $results = $this->searchService->findLocations($query)->searchHits;

        $contentArray = [];
        foreach ($results as $key => $searchHit) {
            $location = $searchHit->valueObject;
            $content = $location->getContent();
            $contentArray[$key]['content'] = $content;
            $contentArray[$key]['location'] = $location;
        }

        $parameters['results'] = $contentArray;
        /** @var TwigRenderRequest $renderRequest */
        $renderRequest->setParameters($parameters);
    }

    /**
     * @param \EzSystems\EzPlatformPageFieldType\Event\BlockResponseEvent $event
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function onBlockResponse(BlockResponseEvent $event): void
    {
        $response = $event->getResponse();
        $response->setPrivate();
    }
}
