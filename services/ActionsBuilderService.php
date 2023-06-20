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

use YesWiki\Aceditor\Service\ActionsBuilderService as AceditorActionsBuilderService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Wiki;

trait ActionsBuilderServiceCommon
{
    protected $previousData;
    protected $data;
    protected $parentActionsBuilderService;
    protected $renderer;
    protected $wiki;

    public function __construct(TemplateEngine $renderer, Wiki $wiki, $parentActionsBuilderService)
    {
        $this->data = null;
        $this->previousData = null;
        $this->parentActionsBuilderService = $parentActionsBuilderService;
        $this->renderer = $renderer;
        $this->wiki = $wiki;
    }

    public function setPreviousData(?array $data)
    {
        if (is_null($this->previousData)) {
            $this->previousData = is_array($data) ? $data : [];
            if ($this->parentActionsBuilderService && method_exists($this->parentActionsBuilderService, 'setPreviousData')) {
                $this->parentActionsBuilderService->setPreviousData($data);
            }
        }
    }

    // ---------------------
    // Data for the template
    // ---------------------
    public function getData()
    {
        if (is_null($this->data)) {
            if (!empty($this->parentActionsBuilderService)) {
                $this->data = $this->parentActionsBuilderService->getData();
            } else {
                $this->data = $this->previousData;
            }

            if (isset($this->data['action_groups']['advanced-actions']['actions'])) {
                $this->data['action_groups']['advanced-actions']['actions']['searchinotherpage'] = [
                    'label' => _t('AB_ADVANCEDSEARCH_SEARCHINOTHERPAGE_LABEL'),
                    'properties' => [
                        'page' => [
                            'label' => _t('AB_ADVANCEDSEARCH_SEARCHINOTHERPAGE_PAGE'),
                            'type' => 'page-list',
                            'required' => true,
                            'value' => 'RechercheTexte'
                        ]
                    ],
                ];
            }

            if (isset($this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties'])) {
                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['label'] = [
                    'label' => _t('AB_advanced_action_textsearch_label_label'),
                    'type' => "text",
                    'default' => _t('WHAT_YOU_SEARCH')." : ",
                ];
                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['size'] = [
                    'label' => _t('AB_advanced_action_textsearch_size_label'),
                    'type' => "number",
                    'default' => "40",
                    'min' => '1',
                ];
                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['template'] = [
                    'label' => _t('AB_advanced_action_newtextsearch_template_label'),
                    'type' => "list",
                    'default' => "newtextsearch-by-category.twig",
                    'options' => [
                        "newtextsearch.twig" => _t('AB_advanced_action_newtextsearch_template_standard'),
                        "newtextsearch-by-category.twig" => _t('AB_advanced_action_newtextsearch_template_by_form'),
                    ],
                ];
                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['viewtype'] = [
                    'label' => _t('AB_advanced_action_newtextsearch_viewtype_label'),
                    'type' => "list",
                    'default' => "modal",
                    'options' => [
                        "modal" => _t('AB_advanced_action_newtextsearch_viewtype_modal'),
                        "link" => _t('AB_advanced_action_newtextsearch_viewtype_link'),
                        "newtab" => _t('AB_advanced_action_newtextsearch_viewtype_newtab'),
                    ]
                ];

                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['displayorder'] = [
                    'btn-label-add' => _t('AB_advanced_action_newtextsearch_displayorder_label'),
                    'type' => "display-order",
                    'showif' => [
                        'template' => "^$|^newtextsearch-by-category\.twig$",
                    ],
                    //'default' => "",
                    'subproperties' => [
                    'type' => [
                        'label' => _t('AB_advanced_action_newtextsearch_displayorder_type'),
                        'type' => 'list',
                        'options' => [
                            'pages' =>  _t('AB_advanced_action_newtextsearch_displayorder_type_pages'),
                            'logpages' =>  _t('AB_advanced_action_newtextsearch_displayorder_type_logpages'),
                            'form' =>  _t('AB_advanced_action_newtextsearch_displayorder_type_form'),
                            'tag' =>  _t('AB_advanced_action_newtextsearch_displayorder_type_tag')
                        ]
                    ],
                    'value' => [
                        'label' => _t('AB_advanced_action_newtextsearch_displayorder_value'),
                        'type' => "value-for-display-order",
                        'default' => "",
                    ],
                    'title' => [
                        'label' => _t('AB_advanced_action_newtextsearch_displayorder_title'),
                        'type' => "text",
                        'default' => "",
                    ],
                ]
                ];

                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['phrase'] = [
                    'label' => _t('AB_advanced_action_textsearch_phrase_label'),
                    'type' => "text",
                    'default' => "",
                    'advanced' => true,
                ];
                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['separator'] = [
                    'label' => _t('AB_advanced_action_textsearch_separator_label'),
                    'type' => "text",
                    'default' => "",
                    'advanced' => true,
                ];
                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['displaytext'] = [
                    'label' => _t('AB_advanced_action_newtextsearch_displaytext_label'),
                    'type' => "list",
                    'default' => "",
                    'advanced' => true,
                    "options" => [
                        "" => _t('AB_advanced_action_newtextsearch_displaytext_only_std'),
                        "true" => _t('YES'),
                        "false" => _t('NO'),
                    ]
                ];
                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['limit'] = [
                    'label' => _t('AB_advanced_action_newtextsearch_limit_label'),
                    'type' => "number",
                    'default' => 10,
                    'advanced' => true,
                    'min' => 1,
                ];
                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['onlytags'] = [
                    'label' => _t('AB_advanced_action_textsearch_onlytags_label'),
                    'hint' => _t('AB_advanced_action_textsearch_onlytags_hint'),
                    'type' => "text",
                    'default' => "",
                    'advanced' => true,
                ];
                $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['nbcols'] = [
                    'label' => _t('AB_advanced_action_textsearch_nbcols_label'),
                    'hint' => _t('AB_advanced_action_textsearch_nbcols_hint'),
                    'type' => "number",
                    'default' => 2,
                    'min' => 0,
                    'max' => 3,
                    'advanced' => true,
                    'showif' => [
                        'template' => "^$|^newtextsearch-by-category\.twig$",
                    ],
                ];
                if (isset($this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['phrase']['label'])) {
                    $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['phrase']['label'] =
                        _t('AB_advanced_action_textsearch_phrase_label_forced');
                    $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['phrase']['hint'] =
                        _t('AB_advanced_action_textsearch_phrase_hint');
                }
                if (isset($this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['separator'])) {
                    $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['separator']['showif'] =
                        [
                            'template' => "^$|^newtextsearch\.twig$",
                        ];
                    $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['separator']['hint'] =
                        _t('AB_advanced_action_textsearch_separator_hint') ;
                }
                if (isset($this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['size'])) {
                    $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['size']['hint'] =
                        _t('AB_advanced_action_textsearch_size_hint') ;
                    $this->data['action_groups']['advanced-actions']['actions']['newtextsearch']['properties']['size']['advanced'] =
                        true ;
                }
            }
        }
        return $this->data;
    }
}

if (class_exists(AceditorActionsBuilderService::class, false)) {
    class ActionsBuilderService extends AceditorActionsBuilderService
    {
        use ActionsBuilderServiceCommon;
    }
} else {
    class ActionsBuilderService
    {
        use ActionsBuilderServiceCommon;
    }
}
