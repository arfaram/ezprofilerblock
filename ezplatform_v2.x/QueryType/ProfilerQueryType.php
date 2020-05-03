<?php

namespace EzSystems\ProfilerBlockBundle\QueryType;

use eZ\Publish\API\Repository\Values\Content\LocationQuery;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\Core\QueryType\QueryType;
use Netgen\TagsBundle\API\Repository\Values\Content\Query\Criterion\TagKeyword;

/**
 * Class ProfilerQueryType.
 */
class ProfilerQueryType implements QueryType
{
    /**
     * @param array $parameters
     * @return LocationQuery|Query
     */
    public function getQuery(array $parameters = [])
    {
        $criteria = [
            new Query\Criterion\Visibility(Query\Criterion\Visibility::VISIBLE),
        ];

        if (!empty($parameters['contentType'])) {
            $criteria[] = new Query\Criterion\ContentTypeIdentifier(
                    explode(',', $parameters['contentType'])
            );
        }

        if ($parameters['depth']) {
            $criteria[] = new Query\Criterion\Subtree($parameters['location']->pathString);
        } else {
            $criteria[] = new Query\Criterion\ParentLocationId($parameters['location']->id);
        }

        if (!empty($parameters['tags'])) {
            $tags = $this->fetchTags($parameters['tags']);
            $criteria[] = new TagKeyword(Query\Criterion\Operator::IN, $tags);
        }

        return new LocationQuery([
            'filter' => new Query\Criterion\LogicalAnd($criteria),
            'sortClauses' => array(new Query\SortClause\DatePublished(Query::SORT_DESC)),
        ]);
    }

    /**
     * @return array|void
     */
    public function getSupportedParameters()
    {
        return;
    }

    public static function getName()
    {
        return 'EventList';
    }

    /*  public function getTimeStamp($date)
        {
            $dateObject = new \DateTime($date);
            return $dateObject->getTimestamp();
        }
    */

    public function fetchTags($tags)
    {
        foreach ($tags as $key => $value) {
            $value = trim($value);
            $tagsArray[$key] = $value;

            if (null === $value || $value == '') {
                unset($tagsArray[$key]);
            }
        }

        return $tagsArray;
    }
}
