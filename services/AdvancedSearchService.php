<?php

/*
 * This file is part of the YesWiki Extension advancedsearch.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Advancedsearch\Service;

use YesWiki\Bazar\Controller\EntryController;
use YesWiki\Bazar\Service\EntryManager;
use YesWiki\Bazar\Service\FormManager;
use YesWiki\Bazar\Service\SearchManager;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\PageManager;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Tags\Service\TagsManager;
use YesWiki\Wiki;

class AdvancedSearchService
{
    public const MAXIMUM_RESULTS_BY_QUERY = 200 ;

    protected $aclService;
    protected $dbService;
    protected $entryController;
    protected $entryManager;
    protected $formManager;
    protected $pageManager;
    protected $searchManager;
    protected $tagsManager;
    protected $templateEngine;
    protected $wiki;

    public function __construct(
        AclService $aclService,
        DbService $dbService,
        EntryController $entryController,
        EntryManager $entryManager,
        FormManager $formManager,
        PageManager $pageManager,
        SearchManager $searchManager,
        TagsManager $tagsManager,
        TemplateEngine $templateEngine,
        Wiki $wiki
    ) {
        $this->aclService = $aclService;
        $this->dbService = $dbService;
        $this->entryController = $entryController;
        $this->entryManager = $entryManager;
        $this->formManager = $formManager;
        $this->pageManager = $pageManager;
        $this->searchManager = $searchManager;
        $this->tagsManager = $tagsManager;
        $this->templateEngine = $templateEngine;
        $this->wiki = $wiki;
    }

    public function getSearch(string $searchText, array $options = []): array
    {
        $options['displaytext'] = (isset($options['displaytext']) && is_bool($options['displaytext'])) ? $options['displaytext'] : false;
        $options['forceDisplay'] = (isset($options['forceDisplay']) && is_bool($options['forceDisplay'])) ? $options['forceDisplay'] : false;
        $options['limit'] = (isset($options['limit']) && is_int($options['limit']) && $options['limit'] > 0) ? $options['limit'] : 0;
        $options['limitByCat'] = (isset($options['limitByCat']) && is_bool($options['limitByCat'])) ? $options['limitByCat'] : false;
        $options['categories'] = (!empty($options['categories']) && is_string($options['categories'])) ? array_map('strval', explode(',', $options['categories'])) : [];
        $options['excludes'] = (!empty($options['excludes']) && is_string($options['excludes'])) ? explode(',', $options['excludes']) : [];
        $options['onlytags'] = (!empty($options['onlytags']) && is_string($options['onlytags'])) ? explode(',', $options['onlytags']) : [];
        $startTime = microtime(true);

        list('requestfull' => $sqlRequest, 'needles' => $needles) = $this->getSqlRequest($searchText);
        $data = [
            'results' => [],
            'extra' => []
        ];
        $sqlOptions = [
            'displaytext' => $options['displaytext'],
            'searchText' => $searchText,
            'needles' => $needles,
            'startTime' => $startTime,
            'limitByCat' => $options['limitByCat'] ? $options['limit'] : -1,
            'categories' => $options['categories'],
            'forceDisplay' => $options['forceDisplay'],
        ];
        $this->addExcludesTags($sqlRequest, $options['excludes']);
        $this->addOnlyTagsNames($sqlRequest, $options['onlytags']);
        if (empty($options['categories'])) {
            if ($options['limitByCat']) {
                // remove log pages (default)
                $sqlRequest = $this->removeLogPageFilter($sqlRequest);
                $limit = self::MAXIMUM_RESULTS_BY_QUERY+$options['limit'];
            } else {
                $limit = $options['limit'];
            }
            $this->searchSQL(
                $data,
                $sqlRequest,
                array_merge($sqlOptions, ['limit'=>$limit,'limitByCat' => -1])
            );
        } else {
            $onlyTags = $this->keepOnlyTagsCategories($options['categories']);
            $noTags = $this->removeTagsCategories($options['categories']);
            if (empty($noTags) && !empty($onlyTags)) {
                foreach ($onlyTags as $tagCat) {
                    $tag = $this->getTagCategory($tagCat);
                    $pagesOrEntries = $this->tagsManager->getPagesByTags($tag);
                    $selectedTags = array_map(function ($page) {
                        return $page['tag'];
                    }, $pagesOrEntries);
                    $this->searchSQL(
                        $data,
                        $this->addOnlyTags(
                            $sqlRequest,
                            $selectedTags
                        ),
                        array_merge($sqlOptions, ['limit'=>$options['limit'],'limitByCat' => -1])
                    );
                }
            } elseif (!empty($noTags)) {
                $forms = [];
                $withPage = false;
                $withLogPage = false;
                foreach ($noTags as $category) {
                    if (strval(intval($category)) == $category) {
                        if (intval($category) > 0 && !in_array($category, $forms)) {
                            $forms[] = $category;
                        }
                    } elseif ($category == "page") {
                        $withPage = true;
                    } elseif ($category == "logpage") {
                        $withLogPage = true;
                    }
                }
                if ($withPage || $withLogPage || !empty($forms)) {
                    if (empty($forms)) {
                        if ($withPage && $withLogPage) {
                            $sqlRequest = $this->removeEntriesFilter($sqlRequest);
                        } elseif ($withPage) {
                            $sqlRequest = $this->removeEntriesFilter($sqlRequest);
                            $sqlRequest = $this->removeLogPageFilter($sqlRequest);
                        } else {
                            $sqlRequest = $this->appendLogPageFilter($sqlRequest);
                        }
                    } elseif ($withPage && $withLogPage) {
                        $sqlRequest = $this->appendFormsFilter($sqlRequest, $forms, true);
                    } elseif ($withPage) {
                        $sqlRequest = $this->appendFormsFilter($sqlRequest, $forms, true);
                        $sqlRequest = $this->removeLogPageFilter($sqlRequest);
                    } elseif ($withLogPage) {
                        $sqlRequest = $this->appendFormsFilter($sqlRequest, $forms, true, true);
                    } else {
                        $sqlRequest = $this->appendFormsFilter($sqlRequest, $forms, false);
                    }
                    $limit = count($options['categories']) == 1
                        ? $options['limit']
                        : self::MAXIMUM_RESULTS_BY_QUERY+$options['limit'];
                    $this->searchSQL($data, $sqlRequest, array_merge($sqlOptions, ['limit'=>$limit]));
                }
            }
        }

        return $data;
    }

    public function getTitles(array $tags): array
    {
        $data = [
            'results' => [],
            'extra' => [],
        ];
        foreach ($tags as $tag) {
            $pageData = [
                'tag'=>$tag,
            ];
            $this->appendTitleIfNeeded($pageData);
            $data['results'][] = $pageData;
        }
        return $data;
    }

    public function getTags(array $tags): array
    {
        $data = [
            'results' => [],
            'extra' => [],
        ];
        $limits = [];
        foreach ($tags as $tag) {
            if ($this->aclService->hasAccess("read", $tag)) {
                $pageData = [
                    'tag'=>$tag,
                ];
                $this->appendTags($pageData);
                $data['results'][] = $pageData;
            }
        }
        return $data;
    }

    protected function isTagCategory(string $category): bool
    {
        return substr($category, 0, strlen('tag:')) == 'tag:' && strlen($category) > strlen('tag:');
    }

    protected function getTagCategory(string $category): string
    {
        return substr($category, strlen('tag:'));
    }

    protected function keepOnlyTagsCategories(array $categories): array
    {
        return array_filter($categories, function ($cat) {
            return $this->isTagCategory($cat);
        });
    }

    protected function removeTagsCategories(array $categories): array
    {
        return array_filter($categories, function ($cat) {
            return !$this->isTagCategory($cat);
        });
    }

    private function searchSQL(array &$dataCompacted, string $sqlRequest, array $options)
    {
        list(
            'displaytext'=>$displaytext,
            'searchText'=>$searchText,
            'needles'=>$needles,
            'limit'=>$limit,
            'limitByCat' => $limitByCat,
            'categories' => $categories,
            'startTime' => $startTime,
            'forceDisplay' => $forceDisplay
        ) = $options;
        $sqlRequest = $this->addSQLLimit($sqlRequest, $limit);
        $results = $this->dbService->loadAll($sqlRequest);
        if (!empty($results)) {
            $dataCompacted['extra']['overallLimitReached'] = (count($results) == $limit);
            $dataCompacted['extra']['timeLimitReached'] = false;
            $limitsReached = $this->prepareLimitsReached($categories, $limitByCat, $limit);
            $needAppendTags = (!empty($categories) && count(array_filter($categories, [$this,'isTagCategory'])) > 0);
            foreach ($results as $key => $page) {
                if ($this->aclService->hasAccess("read", $page["tag"])) {
                    $data = [
                        'tag' => $page["tag"],
                        'tags' => [],
                    ];

                    $saveData = $this->appendCategoryInfo($data, $limitsReached, $limitByCat);
                    $data['preRendered']= "";

                    if (!$dataCompacted['extra']['timeLimitReached']) {
                        if (microtime(true) - $startTime > 2.5) {
                            $dataCompacted['extra']['timeLimitReached'] = true;
                        } elseif ($saveData) {
                            if ($displaytext) {
                                $this->appendPreRendered($data, $searchText, $needles);
                            } elseif ($data['type'] != 'entry') {
                                $this->appendTitleIfNeeded($data);
                            }
                            if ($needAppendTags) {
                                $this->appendTags($data);
                            }
                        }
                    }
                    if ($saveData &&
                        $dataCompacted['extra']['timeLimitReached'] &&
                        $displaytext &&
                        $forceDisplay) {
                        $this->appendPreRendered($data, $searchText, $needles);
                    }

                    if ($saveData) {
                        $dataCompacted['results'][] = $data;
                    }

                    $this->updateLimitsReached($limitsReached);
                    if ($limitsReached['all']) {
                        break;
                    }
                }
            }
            $this->saveLimitsReachedInExtra($dataCompacted, $limitsReached);
        }
    }

    private function appendFormsFilter(string $sqlRequest, array $formsIds, bool $withPages, bool $onlyLogPages = false): string
    {
        $bodyCatch = implode(' OR ', array_map(function ($id) {
            return "`body` LIKE '%\"id_typeannonce\":\"$id\"%'";
        }, $formsIds));
        if ($withPages && !$onlyLogPages) {
            return <<<SQL
            $sqlRequest AND (
                (`tag` NOT IN (
                    SELECT `resource` FROM {$this->dbService->prefixTable('triples')} 
                        WHERE `value` = 'fiche_bazar' AND `property` = 'http://outils-reseaux.org/_vocabulary/type'
                    )
                ) OR (
                    `tag` IN (
                    SELECT `resource` FROM {$this->dbService->prefixTable('triples')} 
                        WHERE `value` = 'fiche_bazar' AND `property` = 'http://outils-reseaux.org/_vocabulary/type'
                    ) AND ($bodyCatch)
                )
            )
            SQL;
        } elseif ($onlyLogPages) {
            return <<<SQL
            $sqlRequest AND (
                (`tag` LIKE 'LogDesActionsAdministratives%') OR (
                    `tag` IN (
                    SELECT `resource` FROM {$this->dbService->prefixTable('triples')} 
                        WHERE `value` = 'fiche_bazar' AND `property` = 'http://outils-reseaux.org/_vocabulary/type'
                    ) AND ($bodyCatch)
                )
            )
            SQL;
        } else {
            return <<<SQL
            $sqlRequest AND
                (
                    `tag` IN (
                    SELECT `resource` FROM {$this->dbService->prefixTable('triples')} 
                        WHERE `value` = 'fiche_bazar' AND `property` = 'http://outils-reseaux.org/_vocabulary/type'
                    ) AND ($bodyCatch)
                )
            SQL;
        }
    }

    private function appendLogPageFilter(string $sqlRequest): string
    {
        return "$sqlRequest AND `tag` LIKE 'LogDesActionsAdministratives%'";
    }

    private function removeLogPageFilter(string $sqlRequest): string
    {
        return "$sqlRequest  AND `tag` NOT LIKE 'LogDesActionsAdministratives%'";
    }

    private function removeEntriesFilter(string $sqlRequest): string
    {
        return <<<SQL
        $sqlRequest AND `tag` NOT IN (
            SELECT `resource` FROM {$this->dbService->prefixTable('triples')} 
                WHERE `value` = 'fiche_bazar' AND `property` = 'http://outils-reseaux.org/_vocabulary/type'
            )
        SQL;
    }

    private function getSqlRequest(string $searchText): array
    {
        // extract needles with values in list
        // find in values for entries
        $forms = $this->formManager->getAll();
        $needles = $this->searchManager->searchWithLists($searchText, $forms);
        if (!empty($needles)) {
            $searches = [];
            // generate search
            foreach ($needles as $needle => $results) {
                $currentSearches = [];
                // add regexp standard search in page not entries
                $needleFormatted = $this->prepareNeedleForRegexpCaseInsensitive($needle);
                $search = str_replace('_', '\\_', $needleFormatted);
                $currentSearches[] = 'body REGEXP \''.$search.'\'';

                // add regexp standard search for entries
                $search = $this->convertToRawJSONStringForREGEXP($needleFormatted);
                $search = str_replace('_', '\\_', $search);
                $currentSearches[] = 'body REGEXP \''.$search.'\'';

                if (!empty($results)) {
                    // add search in list
                    // $results is an array not empty only if list
                    foreach ($results as $result) {
                        if (!$result['isCheckBox']) {
                            $currentSearches[] = ' body LIKE \'%"'.str_replace('_', '\\_', $result['propertyName']).'":"'.$result['key'].'"%\'';
                        } else {
                            $currentSearches[] = ' body REGEXP \'"'.str_replace('_', '\\_', $result['propertyName']).'":(' .
                                '"'.$result['key'] . '"'.
                                '|"[^"]*,' . $result['key'] . '"'.
                                '|"' . $result['key'] . ',[^"]*"'.
                                '|"[^"]*,' .$result['key'] . ',[^"]*"'.
                                ')\'';
                        }
                    }
                }

                $searches[] = '('.implode(' OR ', $currentSearches).')';
            }
            $requeteSQL = '('.implode(' AND ', $searches).')';
        }

        $requestfull = "SELECT DISTINCT tag FROM {$this->dbService->prefixTable('pages')} ".
            "WHERE latest = \"Y\" {$this->aclService->updateRequestWithACL()} ".
            "AND $requeteSQL";

        return compact('requestfull', 'needles');
    }

    private function displayNewSearchResult($string, $phrase, $needles = []): string
    {
        $string = strip_tags($string);
        $query = trim(str_replace(array("+","?","*"), array(" "," "," "), $phrase));
        $qt = explode(" ", $query);
        $num = count($qt);
        $cc = ceil(154 / $num);
        $string_re = '';
        foreach ($needles as $needle => $result) {
            if (preg_match('/'.$needle.'/i', $string, $matches)) {
                $tab = preg_split("/(".$matches[0].")/iu", $string, 2, PREG_SPLIT_DELIM_CAPTURE);
                if (count($tab)>1) {
                    $avant = strip_tags(mb_substr($tab[0], -$cc, $cc));
                    $apres = strip_tags(mb_substr($tab[2], 0, $cc));
                    $string_re .= $this->templateEngine->render('@core/_newtextsearch-display_search-text.twig', [
                        'before' => $avant,
                        'content' => $tab[1],
                        'after' => $apres,
                    ]);
                }
            }
        }
        if (empty($string_re)) {
            for ($i = 0; $i < $num; $i++) {
                $tab[$i] = preg_split("/($qt[$i])/iu", $string, 2, PREG_SPLIT_DELIM_CAPTURE);
                if (count($tab[$i])>1) {
                    $avant[$i] = strip_tags(mb_substr($tab[$i][0], -$cc, $cc));
                    $apres[$i] = strip_tags(mb_substr($tab[$i][2], 0, $cc));
                    $string_re .= $this->templateEngine->render('@core/_newtextsearch-display_search-text.twig', [
                        'before' => $avant[$i],
                        'content' => $tab[$i][1],
                        'after' => $apres[$i],
                    ]);
                }
            }
        }
        return $string_re;
    }

    private function addSQLLimit(string $sql, int $limit): string
    {
        if ($limit > 0) {
            return "$sql LIMIT {$limit}";
        } else {
            return $sql;
        }
    }

    private function addExcludesTags(string &$sqlRequest, array $excludes)
    {
        $filteredExcludes = array_filter($excludes, function ($tag) {
            return is_string($tag) && !empty(trim($tag));
        });
        if (!empty($filteredExcludes)) {
            $tags = implode(',', array_map(function ($tag) {
                return "'{$this->dbService->escape(trim($tag))}'";
            }, $filteredExcludes));
            $sqlRequest .= " AND `tag` NOT IN ($tags)";
        }
    }

    private function addOnlyTagsNames(string &$sqlRequest, array $tagsNames)
    {
        if (!empty($tagsNames)) {
            $pageTags = [];
            foreach ($tagsNames as $tag) {
                $pagesOrEntries = $this->tagsManager->getPagesByTags($tag);
                foreach ($pagesOrEntries as $page) {
                    if (!empty($page['tag']) && !in_array($page['tag'], $pageTags)) {
                        $pageTags[] = $page['tag'];
                    }
                }
            }
            $sqlRequest = $this->addOnlyTags($sqlRequest, $pageTags);
        }
    }

    private function addOnlyTags(string $sqlRequest, array $tags)
    {
        if (empty($tags)) {
            return "$sqlRequest AND FALSE";
        } else {
            $formattedTags = array_map(function ($tag) {
                return "'{$this->dbService->escape($tag)}'";
            }, $tags);
            $implodedTags = implode(',', $formattedTags);
            $sqlRequest .= " AND `tag` IN ($implodedTags)";
            return $sqlRequest;
        }
    }

        /**
     * prepare needle by removing accents and define string for regexp
     * @param string $needle
     * @return string
     */
    public function prepareNeedleForRegexp(string $needle): string
    {
        // be careful to ( and )
        $needle = str_replace(['(',')','/'], ['\\(','\\)','\\/'], $needle);

        // remove accents
        $needle = str_replace(
            ['à','á','â','ã','ä','ç','è','è','é','ê','ë','ì','í','î','ï','ñ','ò','ó','ô','õ','ö','ù','ú','û','ü','ý','ÿ','À','Á','Â','Ã','Ä','Ç','È','É','Ê','Ë','Ì','Í','Î','Ï','Ñ','Ò','Ó','Ô','Õ','Ö','Ù','Ú','Û','Ü','Ý'],
            ['a','a','a','a','a','c','e','e','e','e','e','i','i','i','i','n','o','o','o','o','o','u','u','u','u','y','y','a','a','a','a','a','c','e','e','e','e','i','i','i','i','n','o','o','o','o','o','u','u','u','u','y'],
            $needle
        );

        // add for regexp
        $needle = str_replace(
            [
                'a',
                'c',
                'e',
                'i',
                'n',
                'o',
                'u',
                'y',
            ],
            [
                '(a|à|á|â|ã|ä|A|À|Á|Â|Ã|Ä)',
                '(c|ç|C|Ç)',
                '(e|è|é|ê|ë|E|È|É|Ê|Ë)',
                '(i|ì|í|î|ï|I|Ì|Í|Î|Ï)',
                '(n|ñ|N|Ñ)',
                '(o|ò|ó|ô|õ|ö|O|Ò|Ó|Ô|Õ|Ö)',
                '(u|ù|ú|û|ü|U|Ù|Ú|Û|Ü)',
                '(y|ý|ÿ|Y|Ý)',
            ],
            $needle
        );

        return $needle;
    }

    /**
     * prepare needle by removing accents and define string for regexp
     * @param string $needle
     * @return string
     */
    public function prepareNeedleForRegexpCaseInsensitive(string $needle): string
    {
        // lowercase
        $needle = str_replace(
            ['B','C','D','F','G','H','J','K','L','M','N','P','Q','R','S','T','V','W','X','Z'],
            ['b','c','d','f','g','h','j','k','l','m','n','p','q','r','s','t','v','w','x','z'],
            $needle
        );

        // add for regexp
        $needle = str_replace(
            [
                'b','c','d','f','g',
                'h','j','k','l','m',
                'n','p','q','r','s',
                't','v','w','x','z'
            ],
            [
                '(b|B)','(c|C)','(d|D)','(f|F)','(g|G)',
                '(h|H)','(j|J)','(k|K)','(l|L)','(m|M)',
                '(n|N)','(p|P)','(q|Q)','(r|R)','(s|S)',
                '(t|T)','(v|V)','(w|W)','(x|X)','(z|Z)'
            ],
            $needle
        );

        return $needle;
    }

    /** format data as in sql
     * @param string $rawValue
     * @return string $formatedValue
     */
    private function convertToRawJSONStringForREGEXP(string $rawValue): string
    {
        $valueJSON = substr(json_encode($rawValue), 1, strlen(json_encode($rawValue))-2);
        $formattedValue = str_replace(['\\','\''], ['\\\\','\\\''], $valueJSON);
        return $this->dbService->escape($formattedValue);
    }

    private function prepareLimitsReached($categories, $limitByCat, $limit): array
    {
        $limitsReached = [];
        if ($limitByCat > 0) {
            $limitsReached = [
                'all' => false,
                'forms' => [],
                'autoFill' => empty($categories)
            ];
            if (!$limitsReached['autoFill']) {
                foreach ($categories as $category) {
                    if (strval(intval($category)) == $category) {
                        if (intval($category) > 0 && !in_array($category, array_keys($limitsReached['forms']))) {
                            $limitsReached['forms'][$category] = $limitByCat;
                        }
                    } elseif ($category == "page") {
                        $limitsReached['page'] = $limitByCat;
                    } elseif ($category == "logpage") {
                        $limitsReached['logpage'] = $limitByCat;
                    }
                }
            }
        } else {
            $limitsReached = [
                'all' => false,
                'total' => $limit,
                'autoFill' => false
            ];
        }
        return $limitsReached;
    }

    private function appendCategoryInfo(array &$data, array &$limitsReached, $limitByCat): bool
    {
        $saveData = true;
        if (
            (
                $limitsReached['autoFill'] ||
                (
                    !empty($limitsReached['forms']) &&
                    count(array_filter(
                        $limitsReached['forms'],
                        function ($i) {
                            return $i > 0;
                        }
                    )) > 0
                )
            ) &&
            $this->entryManager->isEntry($data['tag'])
        ) {
            $entry = $this->entryManager->getOne($data['tag']);
            if (!empty($entry['id_typeannonce'])) {
                $data['type'] = 'entry';
                $data['form'] =  strval(intval($entry['id_typeannonce']));
                if ($limitsReached['autoFill'] && !array_key_exists($data['form'], $limitsReached['forms'])) {
                    $limitsReached['forms'][$data['form']] = $limitByCat;
                }
                if (isset($limitsReached['forms'][$data['form']])) {
                    if ($limitsReached['forms'][$data['form']] > 0) {
                        $limitsReached['forms'][$data['form']] = $limitsReached['forms'][$data['form']] - 1;
                    } else {
                        $saveData = false;
                    }
                }
            } else {
                $saveData = false;
            }
            if (!empty($entry['bf_titre'])) {
                $data['title'] = $entry['bf_titre'];
            }
        } elseif (!(!$limitsReached['autoFill'] && isset($limitsReached['tags']) && !isset($limitsReached['forms']) &&
                !isset($limitsReached['total']) && !isset($limitsReached['page']) && !isset($limitsReached['logpage']))) {
            $data['type'] = (substr($data['tag'], 0, strlen('LogDesActionsAdministratives')) == 'LogDesActionsAdministratives')
                ? 'logpage'
                : 'page';
            if ($limitsReached['autoFill'] && !array_key_exists($data['type'], $limitsReached)) {
                $limitsReached[$data['type']] = $limitByCat;
            }
            if (isset($limitsReached[$data['type']]) && $limitsReached[$data['type']] > 0) {
                $limitsReached[$data['type']] = $limitsReached[$data['type']] - 1;
            } elseif (isset($limitsReached['total']) && $limitsReached['total'] > 0) {
                $limitsReached['total'] = $limitsReached['total'] - 1;
            } else {
                $saveData = false;
            }
        }

        return $saveData;
    }

    private function appendTags(array &$data)
    {
        $previousTag = $this->wiki->tag;
        $this->wiki->tag = $data["tag"];
        $tags = $this->tagsManager->getAll($data["tag"]);
        if (empty($tags)) {
            $data['tags'] = [];
        } else {
            $data['tags'] = array_values(array_map(function ($tag) {
                return $tag['value'];
            }, $tags));
        }
        $this->wiki->tag = $previousTag;
    }

    private function appendPreRendered(array &$data, $searchText, $needles)
    {
        if ($data['type'] == "entry") {
            $renderedEntry = $this->entryController->view($data["tag"], '', false); // without footer
            $data['preRendered'] = $this->displayNewSearchResult(
                $renderedEntry,
                $searchText,
                $needles
            );
        } else {
            $rawPage = $this->pageManager->getOne($data["tag"]);
            if (!empty($rawPage)) {
                $data['preRendered'] = $this->displayNewSearchResult(
                    $this->wiki->Format($rawPage, 'wakka', $data["tag"]),
                    $searchText,
                    $needles
                );
                $this->appendTitleIfNeeded($data, $rawPage);
            }
        }
    }

    private function appendTitleIfNeeded(array &$data, array $rawPage = [])
    {
        if (empty($data['title']) && function_exists('getTitleFromBody')) {
            if (empty($rawPage)) {
                $rawPage = $this->pageManager->getOne($data['tag']);
            }
            if (!empty($rawPage)) {
                $titleFormPage = getTitleFromBody($rawPage);
                if (!empty($titleFormPage)) {
                    $data['title'] = $titleFormPage;
                }
            }
        }
    }

    private function updateLimitsReached(array &$limitsReached)
    {
        if (!$limitsReached['all']) {
            $someNotReached = false;
            foreach ($limitsReached as $k => $v) {
                if ($someNotReached) {
                    break;
                }
                if (!in_array($k, ['all','autoFill','tags'], true)) {
                    if (is_array($v)) {
                        if (!empty($v)) {
                            foreach ($v as $subK => $subV) {
                                if ($subV > 0) {
                                    $someNotReached = true;
                                    break;
                                }
                            }
                        }
                    } elseif ($v > 0) {
                        $someNotReached = true;
                    }
                }
            }
            $limitsReached['all'] = !$someNotReached;
        }
    }

    private function saveLimitsReachedInExtra(array &$dataCompacted, array &$limitsReached)
    {
        $dataCompacted['extra']['limitsReached'] = [];
        foreach ($limitsReached as $k => $v) {
            if (!in_array($k, ['all','autoFill'], true)) {
                if (is_array($v)) {
                    if (!empty($v)) {
                        $dataCompacted['extra']['limitsReached'][$k] = [];
                        foreach ($v as $subK => $subV) {
                            $dataCompacted['extra']['limitsReached'][$k][strval($subK)] = ($subV < 1);
                        }
                    }
                } else {
                    $dataCompacted['extra']['limitsReached'][$k] = ($v < 1);
                }
            }
        }
    }
}
