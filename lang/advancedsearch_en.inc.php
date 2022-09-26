<?php

/*
 * This file is part of the YesWiki Extension advancedsearch.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
  // actions/NexTextSearch.php
  'ADVANCEDSEARCH_PAGES' => 'Pages',
  'ADVANCEDSEARCH_LOG_PAGES' => 'Log pages',

   // templates/aceditor/actions-builder.tpl.html
   'AB_advanced_action_newtextsearch_template_label' => 'Template',
   'AB_advanced_action_newtextsearch_template_standard' => 'Standard',
   'AB_advanced_action_newtextsearch_template_by_form' => 'Classed by form',
   'AB_advanced_action_newtextsearch_displaytext_label' => 'Display found text',
   'AB_advanced_action_newtextsearch_displaytext_only_std' => 'only for template \'standard\'',
   'AB_advanced_action_newtextsearch_displayorder_label' => 'Custom display order',
   'AB_advanced_action_newtextsearch_displayorder_old_hint' => 'Give ids of forms to display, separated by coma ex: 2,4,pages (\'pages\' indicates pages, , \'logpages\' for logs, or a tagname)',
   'AB_advanced_action_newtextsearch_displayorder_titles' => 'Titles',
   'AB_advanced_action_newtextsearch_titles_hint' => 'Associated titles separated by coma (empty = default title) ; ex: "Calendar,,Page"',
   'AB_advanced_action_newtextsearch_limit_label' => 'Maximum number of results',
   'AB_advanced_action_newtextsearch_displayorder_type_pages' => 'Pages',
   'AB_advanced_action_newtextsearch_displayorder_type_logpages' => 'Log pages',
   'AB_advanced_action_newtextsearch_displayorder_type_form' => 'Form',
   'AB_advanced_action_newtextsearch_displayorder_type_tag' => 'Tag',
   'AB_advanced_action_newtextsearch_displayorder_type' => 'Type',
   'AB_advanced_action_newtextsearch_displayorder_title' => 'Title',
   'AB_advanced_action_newtextsearch_displayorder_value' => 'Value',
   'AB_advanced_action_textsearch_onlytags_label' => 'Only tags:',
   'AB_advanced_action_textsearch_onlytags_hint' => 'coma separated',
   'AB_advanced_action_textsearch_nbcols_label' => 'Number of coloumnes',
   'AB_advanced_action_textsearch_nbcols_hint' => '0 = automatic and variable number of columns according to content',
   'AB_advanced_action_textsearch_phrase_label_forced' => 'Search text forced by default',
   'AB_advanced_action_textsearch_phrase_hint' => 'If not empty, input form is not displayed.',
   'AB_advanced_action_textsearch_separator_hint' => 'This parameter is useful only for standard template.',
   'AB_advanced_action_textsearch_size_hint' => 'This parameter is active according to theme\'s adjustements',

   // templates/newtextsearch-by-category.twig
   'ADVANCEDSEARCH_SEE_MORE' => 'See more',
];
