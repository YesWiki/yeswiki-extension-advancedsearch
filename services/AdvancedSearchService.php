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
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Tags\Service\TagsManager;
use YesWiki\Wiki;

class AdvancedSearchService
{
    protected $aclService;
    protected $dbService;
    protected $entryController;
    protected $entryManager;
    protected $formManager;
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
        $this->searchManager = $searchManager;
        $this->tagsManager = $tagsManager;
        $this->templateEngine = $templateEngine;
        $this->wiki = $wiki;
    }

    public function getSearch(string $searchText, array $options = []): array
    {
        $options['displaytext'] = (isset($options['displaytext']) && is_bool($options['displaytext'])) ? $options['displaytext'] : false;
        $options['limit'] = (isset($options['limit']) && is_int($options['limit']) && $options['limit'] > 0) ? $options['limit'] : 0;
        $options['limitByCat'] = (isset($options['limitByCat']) && is_bool($options['limitByCat'])) ? $options['limitByCat'] : false;
        $options['categories'] = (!empty($options['categories']) && is_string($options['categories'])) ? explode(',', $options['categories']) : [];
        $options['excludes'] = (!empty($options['excludes']) && is_string($options['excludes'])) ? explode(',', $options['excludes']) : [];
        $options['onlytags'] = (!empty($options['onlytags']) && is_string($options['onlytags'])) ? explode(',', $options['onlytags']) : [];

        list('requestfull' => $sqlRequest, 'needles' => $needles) = $this->getSqlRequest($searchText);
        $filteredResults = [];
        $this->addExcludesTags($sqlRequest, $options['excludes']);
        $this->addOnlyTagsNames($sqlRequest, $options['onlytags']);
        if (!$options['limitByCat'] && empty($options['categories'])) {
            $this->searchSQL($filteredResults, $this->addSQLLimit($sqlRequest, $options['limit']), $options['displaytext'], $searchText, $needles);
        } elseif (!empty($options['categories'])) {
            foreach ($options['categories'] as $category) {
                if (strval(intval($category)) == strval($category)) {
                    $this->searchSQL($filteredResults, $this->addSQLLimit($this->appendFormFilter($sqlRequest, intval($category)), $options['limit']), $options['displaytext'], $searchText, $needles);
                } elseif ($category == "page") {
                    $this->searchSQL($filteredResults, $this->addSQLLimit($this->appendPageFilter($sqlRequest), $options['limit']), $options['displaytext'], $searchText, $needles);
                } elseif ($category == "logpage") {
                    $this->searchSQL($filteredResults, $this->addSQLLimit($this->appendLogPageFilter($sqlRequest), $options['limit']), $options['displaytext'], $searchText, $needles);
                } elseif (substr($category, 0, strlen('tag:')) == 'tag:' && strlen($category) > strlen('tag:')) {
                    $tag = substr($category, strlen('tag:'));
                    $pagesOrEntries = $this->tagsManager->getPagesByTags($tag);
                    $this->searchSQL(
                        $filteredResults,
                        $this->addSQLLimit(
                            $this->addOnlyTags(
                                $sqlRequest,
                                array_map(function ($page) {
                                    return $page['tag'];
                                }, $pagesOrEntries)
                            ),
                            $options['limit']
                        ),
                        $options['displaytext'],
                        $searchText,
                        $needles
                    );
                }
            }
        } else {
            foreach ($this->formManager->getAll() as $form) {
                $this->searchSQL($filteredResults, $this->addSQLLimit($this->appendFormFilter($sqlRequest, intval($form['bn_id_nature'])), $options['limit']), $options['displaytext'], $searchText, $needles);
            }
            $this->searchSQL($filteredResults, $this->addSQLLimit($this->appendPageFilter($sqlRequest), $options['limit']), $options['displaytext'], $searchText, $needles);
        }

        return $filteredResults;
    }

    private function searchSQL(array &$filteredResults, string $sqlRequest, bool $displaytext, string $searchText, array $needles)
    {
        $results = $this->dbService->loadAll($sqlRequest);
        foreach ($results as $key => $page) {
            if ($this->aclService->hasAccess("read", $page["tag"])) {
                $data = $page;
                if ($this->entryManager->isEntry($page["tag"])) {
                    $entry = $this->entryManager->getOne($page["tag"]);
                    if (!empty($entry['id_typeannonce'])) {
                        $data['type'] = 'entry';
                        $data['form'] =  strval(intval($entry['id_typeannonce']));
                    }
                    if (!empty($entry['bf_titre'])) {
                        $data['title'] = $entry['bf_titre'];
                    }
                } elseif (substr($page["tag"], 0, strlen('LogDesActionsAdministratives')) == 'LogDesActionsAdministratives') {
                    $data['type'] =  'logpage';
                } else {
                    $data['type'] =  'page';
                }
                $data['preRendered']= "";
                if ($displaytext) {
                    if ($data['type'] == "entry") {
                        $renderedEntry = $this->entryController->view($page["tag"], '', false); // without footer
                        $data['preRendered'] = $this->displayNewSearchResult(
                            $renderedEntry,
                            $searchText,
                            $needles
                        );
                    }
                    if (empty($data['preRendered'])) {
                        $data['preRendered'] = $this->displayNewSearchResult(
                            $this->wiki->Format($page["body"], 'wakka', $page["tag"]),
                            $searchText,
                            $needles
                        );
                    }
                }
                if (function_exists('getTitleFromBody') && in_array($data['type'], ['logpage','page'])) {
                    $titleFormPage = getTitleFromBody($page);
                    if (!empty($titleFormPage)) {
                        $data['title'] = $titleFormPage;
                    }
                }
                $previousTag = $this->wiki->tag;
                $this->wiki->tag = $page["tag"];
                $tags = $this->tagsManager->getAll($page["tag"]);
                if (empty($tags)) {
                    $data['tags'] = [];
                } else {
                    $data['tags'] = array_values(array_map(function ($tag) {
                        return $tag['value'];
                    }, $tags));
                }
                $this->wiki->tag = $previousTag;
                // not needed after
                unset($data['body']);
                $filteredResults[] = $data;
            }
        }
    }

    private function appendFormFilter(string $sqlRequest, int $formId): string
    {
        if ($formId <= 0) {
            return "$sqlRequest AND FALSE";
        } else {
            return "$sqlRequest AND `body` LIKE '%\"id_typeannonce\":\"$formId\"%' AND `tag` IN (".
                "SELECT `resource` FROM {$this->dbService->prefixTable('triples')} ".
                "WHERE `value` = 'fiche_bazar' AND `property` = 'http://outils-reseaux.org/_vocabulary/type'".
                ")";
        }
    }

    private function appendPageFilter(string $sqlRequest): string
    {
        return "$sqlRequest AND `tag` NOT IN (".
            "SELECT `resource` FROM {$this->dbService->prefixTable('triples')} ".
            "WHERE `value` = 'fiche_bazar' AND `property` = 'http://outils-reseaux.org/_vocabulary/type'".
            ") AND `tag` NOT LIKE 'LogDesActionsAdministratives%'";
    }

    private function appendLogPageFilter(string $sqlRequest): string
    {
        return "$sqlRequest AND `tag` NOT IN (".
            "SELECT `resource` FROM {$this->dbService->prefixTable('triples')} ".
            "WHERE `value` = 'fiche_bazar' AND `property` = 'http://outils-reseaux.org/_vocabulary/type'".
            ") AND `tag` LIKE 'LogDesActionsAdministratives%'";
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

        // TODO retrouver la facon d'afficher les commentaires (AFFICHER_COMMENTAIRES ? '':'AND tag NOT LIKE "comment%"').
        $requestfull = "SELECT body, tag FROM {$this->dbService->prefixTable('pages')} ".
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
            return "$sql ORDER BY tag LIMIT {$limit}";
        } else {
            return $sql;
        }
    }

    private function addExcludesTags(string &$sqlRequest, array $excludes)
    {
        foreach ($excludes as $exclude) {
            if (!empty($exclude) && is_string($exclude)) {
                $sqlRequest .= " AND `tag` NOT LIKE '{$this->dbService->escape($exclude)}'";
            }
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
}
