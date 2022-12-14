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

use YesWiki\Advancedsearch\Service\AdvancedSearchService;
use YesWiki\Core\YesWikiController;

class CommonHandlers extends YesWikiController
{
    public const GET_PARAMETERS_NAME = "searchAnchor";
    public const ANCHOR_ID = "searchAnchor";

    private const ALL_CHARS = "[\w\W\p{Z}\\h\\v]";
    private const ALL_WORD_CHARS_AND_RETURN_EXCEPT_LT = "[\w\p{Z}\\h\\v^>]";

    protected $advancedSearchService;

    public function __construct(
        AdvancedSearchService $advancedSearchService
    ) {
        $this->advancedSearchService = $advancedSearchService;
    }

    public function run(string &$output)
    {
        if (!empty($_GET[self::GET_PARAMETERS_NAME])) {
            $searchText = filter_input(INPUT_GET, self::GET_PARAMETERS_NAME, FILTER_UNSAFE_RAW);
            $searchText = in_array($searchText, [null,false], true) ? "" : htmlspecialchars($searchText);
            if (!empty($searchText)) {
                $formattedSearchText = $this->advancedSearchService->prepareNeedleForRegexp(preg_quote($searchText, '/'));
                $allChars = self::ALL_CHARS;
                $ltNotFollowedByLt = ">".self::ALL_WORD_CHARS_AND_RETURN_EXCEPT_LT;
                if (preg_match("/(?:<div class=\"(?:yeswiki-page-widget page-widget )?page\"$allChars*)($ltNotFollowedByLt*$formattedSearchText)/iu", $output, $matches)) {
                    $textToReplace = $matches[1];
                    if (preg_match("/^($ltNotFollowedByLt*)($formattedSearchText)/iu", $textToReplace, $matches2)) {
                        $newOutput = str_replace(
                            $matches2[0],
                            "{$matches2[1]}<span id=\"".self::ANCHOR_ID."\"></span><b>{$matches2[2]}</b>",
                            $output
                        );
                        if (!empty($newOutput)) {
                            $output = $newOutput;
                        }
                    }
                } else {
                    $splittedSearchText = array_filter(explode(' ', $searchText));
                    if (count($splittedSearchText)>1) {
                        $splittedSearchText = array_map(
                            function ($value) {
                                return $this->advancedSearchService->prepareNeedleForRegexp(preg_quote($value, '/'));
                            },
                            $splittedSearchText
                        );
                        $formattedSearchText = implode('.*', $splittedSearchText);
                        if (preg_match("/(?:<div class=\"(?:yeswiki-page-widget page-widget )?page\"$allChars*)($ltNotFollowedByLt*$formattedSearchText)/iu", $output, $matches)) {
                            $textToReplace = $matches[1];
                            if (preg_match("/^($ltNotFollowedByLt*)($formattedSearchText)/iu", $textToReplace, $matches2)) {
                                $newOutput = str_replace(
                                    $matches2[0],
                                    "{$matches2[1]}<span id=\"".self::ANCHOR_ID."\"></span><b>{$matches2[2]}</b>",
                                    $output
                                );
                                if (!empty($newOutput)) {
                                    $output = $newOutput;
                                }
                            }
                        }
                        foreach ($splittedSearchText as $formattedSearchText) {
                            if (preg_match("/(?:<div class=\"(?:yeswiki-page-widget page-widget )?page\"$allChars*)($ltNotFollowedByLt*$formattedSearchText)/iu", $output, $matches)) {
                                $textToReplace = $matches[1];
                                if (preg_match("/^($ltNotFollowedByLt*)($formattedSearchText)/iu", $textToReplace, $matches2)) {
                                    $newOutput = str_replace(
                                        $matches2[0],
                                        "{$matches2[1]}<span id=\"".self::ANCHOR_ID."\"></span><b>{$matches2[2]}</b>",
                                        $output
                                    );
                                    if (!empty($newOutput)) {
                                        $output = $newOutput;
                                    }
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }
    }
}
