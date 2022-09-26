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
     * @Route("/api/search/{text}",methods={"GET"}, options={"acl":{"public"}},priority=5)
     */
    public function search($text = '')
    {
        if (empty($text)){
            $results = [];
        } else {
            $advancedSearchService = $this->getService(AdvancedSearchService::class);
            $results = $advancedSearchService->getSearch($text,[
                'displaytext' => filter_input(INPUT_GET,'displaytext',FILTER_VALIDATE_BOOL),
                'limitByCat' => filter_input(INPUT_GET,'limitByCat',FILTER_VALIDATE_BOOL),
                'limit' => filter_input(INPUT_GET,'limit',FILTER_VALIDATE_INT,[
                    'min_range' => 0,
                    'default' => 0
                ]),
                'categories' => filter_input(INPUT_GET,'categories',FILTER_UNSAFE_RAW),
                'excludes' => filter_input(INPUT_GET,'excludes',FILTER_UNSAFE_RAW),
                'onlytags' => filter_input(INPUT_GET,'onlytags',FILTER_UNSAFE_RAW)
            ]);
        }
        return new ApiResponse($results);
    }
}