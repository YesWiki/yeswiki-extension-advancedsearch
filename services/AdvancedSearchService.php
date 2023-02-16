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

use Throwable;
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

    public function getSearch(
        string $text,
        int $limit,
        bool $limitByCat = false,
        array $neededByCat = [],
        bool $displayText = false,
        bool $forceDisplay = false,
        string $categoriesComaSeparated = '',
        string $excludesComaSeperated = '',
        string $onlytagsComaSeparated = '',
        bool $fastMode = false,
        array $keepOnlyTags = []): array
    {
        $limits = $this->sanitizeLimits($limit,$limitByCat,$neededByCat);
        
        $categories = empty($categoriesComaSeparated)
            ? []
            : array_map('strval', explode(',', $categoriesComaSeparated));
        $excludes = empty($excludesComaSeperated) ? [] : explode(',', $excludesComaSeperated);
        $onlytags = empty($onlytagsComaSeparated) ? [] : explode(',', $onlytagsComaSeparated);
        $startTime = microtime(true);

        list('requestfull' => $sqlRequest, 'needles' => $needles) = $this->getSqlRequest($text, !$fastMode);
        $data = [
            'results' => [],
            'extra' => []
        ];
        $sqlOptions = [
            'displaytext' => $displayText,
            'searchText' => $text,
            'needles' => $needles,
            'startTime' => $startTime,
            'limitByCat' => $limitByCat,
            'categories' => $categories,
            'forceDisplay' => $forceDisplay,
            'limits' => $limits,
            'onlyTags' => false
        ];
        $this->addExcludesTags($sqlRequest, $excludes);
        $this->addOnlyTagsNames($sqlRequest, $onlytags);
        if (!empty($keepOnlyTags)){
            $this->addKeepOnlyTags($sqlRequest, $keepOnlyTags);
            $this->searchSQL(
                $data,
                $sqlRequest,
                array_merge($sqlOptions, [
                    'limit'=>count($keepOnlyTags)+1,
                    'categories'=>'',
                    'limitByCat'=>false,
                    'limits' => [
                        'noCategory' => count($keepOnlyTags)+1
                    ]
                ])
            );
        } elseif (empty($categories)) {
            if ($limitByCat) {
                // remove log pages (default)
                $sqlRequest = $this->removeLogPageFilter($sqlRequest);
                $limitLocal = self::MAXIMUM_RESULTS_BY_QUERY+$limit;
            } else {
                $limitLocal = $limits['noCategory'];
            }
            $this->searchSQL(
                $data,
                $sqlRequest,
                array_merge($sqlOptions, ['limit'=>$limitLocal])
            );
        } else {
            $onlyTags = $this->keepOnlyTagsCategories($categories);
            $noTags = $this->removeTagsCategories($categories);
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
                        array_merge($sqlOptions, ['limit'=>$limits['tags']['default'],'limitByCat' => false,'onlyTags'=>true])
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
                    $limit = count($categories) <= 1
                        ? $limit
                        : (count($categories)+1)*max(5,$limit);
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

    private function searchSQL(array &$dataCompacted, string $sqlRequest, array $sqlOptions)
    {
        list(
            'displaytext'=>$displaytext,
            'searchText'=>$searchText,
            'needles'=>$needles,
            'limit'=>$limit,
            'limitByCat' => $limitByCat,
            'categories' => $categories,
            'startTime' => $startTime,
            'forceDisplay' => $forceDisplay,
            'limits' => $limits,
            'onlyTags' => $onlyTags
        ) = $sqlOptions;
        $sqlRequest = $this->addSQLLimit($sqlRequest, $limit+(($limit < self::MAXIMUM_RESULTS_BY_QUERY && $limit > 1)?$limit:1));
        $results = $this->dbService->loadAll($sqlRequest);
        if (!empty($results)) {
            $dataCompacted['extra']['timeLimitReached'] = false;
            $limitsReached = $this->prepareLimitsReached($categories, $limitByCat, $limit, $limits);
            $needAppendTags = (!empty($categories) && count(array_filter($categories, [$this,'isTagCategory'])) > 0);
            foreach ($results as $key => $page) {
                if ($this->aclService->hasAccess("read", $page["tag"])) {
                    $data = [
                        'tag' => $page["tag"],
                        'tags' => [],
                    ];

                    $saveData = $this->appendCategoryInfo($data, $limitsReached, $limits);
                    $data['preRendered']= "";
                    
                    if (!$dataCompacted['extra']['timeLimitReached']) {
                        if (microtime(true) - $startTime > 2.5) {
                            $dataCompacted['extra']['timeLimitReached'] = true;
                        }
                    }
                    if ($saveData) {
                        if ($onlyTags || $forceDisplay || !$dataCompacted['extra']['timeLimitReached']) {
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

                    if ($saveData || ($onlyTags && $forceDisplay)) {
                        $dataCompacted['results'][] = $data;
                    }

                    $this->updateLimitsReached($limitsReached);
                    if ($limitsReached['all']) {
                        break;
                    }
                }
            }
            $this->saveLimitsReachedInExtra($dataCompacted, $limitsReached,$limits);
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

    private function getSqlRequest(string $searchText, bool $searchInListInEntries = false): array
    {
        // extract needles with values in list
        // find in values for entries
        $forms = $searchInListInEntries ? $this->formManager->getAll() : [];
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

                if ($searchInListInEntries && !empty($results)) {
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

        $requestfull = <<<SQL
        SELECT DISTINCT `tag` FROM {$this->dbService->prefixTable('pages')}
            WHERE `latest` = "Y"
            AND $requeteSQL
        SQL;
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

    private function addKeepOnlyTags(string &$sqlRequest, array $tags)
    {
        $filtered = array_filter($tags, function ($tag) {
            return is_string($tag) && !empty(trim($tag));
        });
        if (!empty($filtered)) {
            $tagsSQL = implode(',', array_map(function ($tag) {
                return "'{$this->dbService->escape(trim($tag))}'";
            }, $filtered));
            $sqlRequest .= " AND `tag` IN ($tagsSQL)";
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

    private function prepareLimitsReached($categories, $limitByCat, $limit, $limits): array
    {
        $limitsReached = [];
        if ($limitByCat) {
            $limitsReached = [
                'all' => false,
                'forms' => [],
                'autoFill' => empty($categories)
            ];
            if (!$limitsReached['autoFill']) {
                $limitsReached['page'] = $limits['pages'];
                $limitsReached['logpage'] = $limits['logpages'];
                foreach ($categories as $category) {
                    if (strval(intval($category)) == $category) {
                        if (intval($category) > 0 && !in_array($category, array_keys($limitsReached['forms']))) {
                            $limitsReached['forms'][$category] = 
                                $limits['entries']['byForm'][strval($category)] ??
                                $limits['entries']['default'];
                        }
                    }
                }
                foreach($limits['entries']['byForm'] as $formId => $limit){
                    $limitsReached['forms'][strval($formId)] = $limit;
                }
            }
        } else {
            $limitsReached = [
                'all' => false,
                'noCategory' => $limits['noCategory'] ?? $limit,
                'autoFill' => false
            ];
        }
        return $limitsReached;
    }

    private function appendCategoryInfo(array &$data, array &$limitsReached, $limits): bool
    {
        if (
            (
                isset($limitsReached['noCategory']) &&
                $limitsReached['noCategory'] >= 0
            ) ||
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
        ) {
            // load entries only if needed
            if (!isset($limitsReached['noCategory']) && $this->isEntryThenAppendEntryData($data)) {
                return $this->updateLimitsForEntry($limitsReached, $data, $limits);
            } else {
                return $this->updateLimitsForPage($limitsReached, $data, $limits);
            }
        } elseif (!isset($limitsReached['noCategory']) && !$this->entryManager->isEntry($data['tag'])) {
            return $this->updateLimitsForPage($limitsReached, $data, $limits);
        }
        return false;
    }

    private function isEntryThenAppendEntryData(array &$data): bool
    {
        if (!$this->entryManager->isEntry($data['tag'])) {
            return false;
        }

        $entry = $this->entryManager->getOne($data['tag']);
        if (empty($entry['id_typeannonce']) ||
            !is_scalar($entry['id_typeannonce'])
            || empty(strval(intval($entry['id_typeannonce'])))) {
            return false;
        }


        $data['type'] = 'entry';
        $data['form'] =  strval(intval($entry['id_typeannonce']));

        if (!empty($entry['bf_titre'])) {
            $data['title'] = $entry['bf_titre'];
        }

        return true;
    }

    private function updateLimitsForEntry(array &$limitsReached, array $data, $limits): bool
    {
        if (!empty($data['form'])) {
            if ($limitsReached['autoFill'] && !array_key_exists($data['form'], $limitsReached['forms'])) {
                $limitsReached['forms'][$data['form']] = $limits['entries']['default'];
            }
            if (isset($limitsReached['forms'][$data['form']])) {
                $limitsReached['forms'][$data['form']] = $limitsReached['forms'][$data['form']] - 1;
                if ($limitsReached['forms'][$data['form']] < 0) {
                    return false;
                }
            }
            return true;
        } else {
            return false;
        }
    }

    private function updateLimitsForPage(array &$limitsReached, array &$data, $limits): bool
    {
        $data['type'] = (substr($data['tag'], 0, strlen('LogDesActionsAdministratives')) == 'LogDesActionsAdministratives')
            ? 'logpage'
            : 'page';
        if ($limitsReached['autoFill'] && !array_key_exists($data['type'], $limitsReached)) {
            $limitsReached[$data['type']] = $data['type'] == 'logpage' ? $limits['logpages'] : $limits['pages'];
        }
        if (isset($limitsReached[$data['type']])){
            $limitsReached[$data['type']] = $limitsReached[$data['type']] - 1;
            if ($limitsReached[$data['type']] < 0){
                return false;
            }
        } elseif (isset($limitsReached['noCategory'])) {
            $limitsReached['noCategory'] = $limitsReached['noCategory'] - 1;
            if ($limitsReached['noCategory'] < 0){
                return false;
            }
        } else {
            return false;
        }
        return true;
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
                try {
                    $renderedContent = $this->wiki->Format($rawPage['body'], 'wakka', $data["tag"]);
                    if (!empty($renderedContent)){
                        $data['preRendered'] = $this->displayNewSearchResult(
                            $this->wiki->Format($renderedContent, 'wakka', $data["tag"]),
                            $searchText,
                            $needles
                        );
                    }
                } catch (Throwable $th) {
                    // do nothing;
                }
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
                                if ($subV >= 0) {
                                    $someNotReached = true;
                                    break;
                                }
                            }
                        }
                    } elseif ($v >= 0) {
                        $someNotReached = true;
                    }
                }
            }
            $limitsReached['all'] = !$someNotReached;
        }
    }

    private function saveLimitsReachedInExtra(array &$dataCompacted, array &$limitsReached, array $limits)
    {
        $dataCompacted['extra']['limitsReached'] = [];
        foreach ($limitsReached as $k => $v) {
            if (!in_array($k, ['all','autoFill'], true)) {
                if (is_array($v)) {
                    if (!empty($v)) {
                        $dataCompacted['extra']['limitsReached'][$k] = [];
                        foreach ($v as $subK => $subV) {
                            $dataCompacted['extra']['limitsReached'][$k][strval($subK)] = ($subV > 1) ? 'no' :(
                                ($subV == 1 && $limits[$k][strval($subK)] != 1)
                                ? 'toConfirme'
                                : 'yes'
                            );
                        }
                    }
                } else {
                    $dataCompacted['extra']['limitsReached'][$k] = ($v > 1) ? 'no' :(
                        ($v == 1 && $limits[$k] != 1)
                        ? 'toConfirme'
                        : 'yes'
                    );
                }
            }
        }
    }

    protected function sanitizeLimits(int $limit, bool $limitByCat, array $neededByCat):array
    {
        if ($limit < 1){
            $limit = self::MAXIMUM_RESULTS_BY_QUERY;
        }
        $limits = [];

        if (!$limitByCat){
            $limits['noCategory'] = $this->extractSanitizedLimitFromNeeded('noCategory',$neededByCat,$limit);
        } else {
            $limits['pages'] = $this->extractSanitizedLimitFromNeeded('pages',$neededByCat,$limit);
            $limits['logpages'] = $this->extractSanitizedLimitFromNeeded('logpages',$neededByCat,$limit);
            
            $limits['entries'] = [];
            $limits['entries']['default'] = $limit;
            $limits['entries']['byForm'] = [];
            if (!empty($neededByCat['entries']) && is_array($neededByCat['entries'])){
                foreach($neededByCat['entries'] as $formId => $needed){
                    if (intval($formId) > 0 && is_scalar($needed) && intval($needed) >= 0){
                        $limits['entries']['byForm'][strval($formId)] = min(intval($needed),$limit);
                    }
                }
            }
            $limits['tags'] = [];
            $limits['tags']['default'] = $limit;
            $limits['tags']['byTag'] = [];
            if (!empty($neededByCat['tags']) && is_array($neededByCat['tags'])){
                foreach($neededByCat['tags'] as $tag => $needed){
                    if (!empty($tag) && is_scalar($needed) && intval($needed) >= 0){
                        $limits['tags']['byTag'][strval($tag)] = min(intval($needed),$limit);
                    }
                }
            }
        }

        return $limits;
    }

    protected function extractSanitizedLimitFromNeeded(string $key,array $neededByCat, int $limit): int
    {
        if (array_key_exists($key,$neededByCat)){
            $val = $neededByCat[$key];
            $val = (is_scalar($val) && intval($val) > 0)
                ? intval($val)
                : 0;
            return min($val,$limit);
        } else {
            return $limit;
        }
    }
}
