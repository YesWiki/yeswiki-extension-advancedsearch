<?php

/*
 * This file is part of the YesWiki Extension advancedsearch.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Advancedsearch\Controller;

use Symfony\Component\Routing\Annotation\Route;
use YesWiki\Advancedsearch\Service\AdvancedSearchService;
use YesWiki\Core\ApiResponse;
use YesWiki\Core\YesWikiController;

class ApiController extends YesWikiController
{
    /**
     * @Route("/api/search/getTags/",methods={"POST"}, options={"acl":{"public"}},priority=5)
     */
    public function getTags()
    {
        $data = [
            'results' => [],
            'extra' => [],
        ];
        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
            $tags = array_filter($_POST['tags'], function ($tag) {
                return is_string($tag) && !empty(trim($tag));
            });
            $data = $this->getService(AdvancedSearchService::class)->getTags($tags);
        }
        return new ApiResponse($data);
    }

    /**
     * @Route("/api/search/getTitles/",methods={"POST"}, options={"acl":{"public"}},priority=5)
     */
    public function getTitles()
    {
        $data = [
            'results' => [],
            'extra' => [],
        ];
        if (isset($_POST['tags']) && is_array($_POST['tags'])) {
            $tags = array_filter($_POST['tags'], function ($tag) {
                return is_string($tag) && !empty(trim($tag));
            });
            $data = $this->getService(AdvancedSearchService::class)->getTitles($tags);
        }
        return new ApiResponse($data);
    }

    /**
     * @Route("/api/search/{text}",methods={"GET"}, options={"acl":{"public"}},priority=5)
     */
    public function search($text = '')
    {
        if (empty($text)) {
            $results = [];
        } else {
            $advancedSearchService = $this->getService(AdvancedSearchService::class);
            $results = $advancedSearchService->getSearch(
                $text,
                $this->filterInput(INPUT_GET, 'limit', FILTER_VALIDATE_INT, [
                    'default' => 0,
                    'min_range' => 0
                ]),
                $this->filterInput(INPUT_GET, 'limitByCat', FILTER_VALIDATE_BOOL, [
                    'default' => false
                ]),
                isset($_GET['neededByCat']) && is_array($_GET['neededByCat']) ? $_GET['neededByCat'] : [],
                $this->filterInput(INPUT_GET, 'displaytext', FILTER_VALIDATE_BOOL, [
                    'default' => false
                ]),
                $this->filterInput(INPUT_GET, 'forceDisplay', FILTER_VALIDATE_BOOL, [
                    'default' => false
                ]),
                strval($this->filterInput(INPUT_GET, 'categories', FILTER_UNSAFE_RAW, [
                    'default' => ''
                ])),
                strval($this->filterInput(INPUT_GET, 'excludes', FILTER_UNSAFE_RAW, [
                    'default' => ''
                ])),
                strval($this->filterInput(INPUT_GET, 'onlytags', FILTER_UNSAFE_RAW, [
                    'default' => ''
                ])),
                $this->filterInput(INPUT_GET, 'fast', FILTER_VALIDATE_BOOL, [
                    'default' => false
                ]),
                isset($_GET['keepOnlyTags']) && is_array($_GET['keepOnlyTags']) ? $_GET['keepOnlyTags'] : []
            );
        }
        return new ApiResponse($results);
    }

    protected function filterInput(
        int $type,
        string $var_name,
        int $filter,
        array $options = []
    )
    {
        $value = filter_input($type, $var_name, $filter, $options);
        if (in_array($value,[false,null],true) && array_key_exists('default',$options)){
            $value = $options['default'];
        }
        return $value;
    }
}
