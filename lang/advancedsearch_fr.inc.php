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
   // actions/documentation.yaml
   'AB_ADVANCEDSEARCH_SEARCHINOTHERPAGE_LABEL' => 'Rechercher dans une autre page',
   'AB_ADVANCEDSEARCH_SEARCHINOTHERPAGE_PAGE' => 'Page où basculer la recherche',

   // actions/NexTextSearch.php
   'ADVANCEDSEARCH_PAGES' => 'Pages',
   'ADVANCEDSEARCH_LOG_PAGES' => 'Pages de log',

   // actions/SearchInOtherPageAction.php
   'ADVANCEDSEARCH_SEARCH_IN_OTHER_PAGE_ERROR' => 'Le paramètre \'page\' est requis pour %{class}!',

   // templates/aceditor/actions-builder.tpl.html
   'AB_advanced_action_newtextsearch_template_label' => 'Template',
   'AB_advanced_action_newtextsearch_template_standard' => 'Standard',
   'AB_advanced_action_newtextsearch_template_by_form' => 'Classé par formulaire',
   'AB_advanced_action_newtextsearch_displaytext_label' => 'Afficher le texte trouvé',
   'AB_advanced_action_newtextsearch_displaytext_only_std' => 'Seulement pour le template \'standard\'',
   'AB_advanced_action_newtextsearch_displayorder_label' => 'Ordre d\'affichage personnalisé',
   'AB_advanced_action_newtextsearch_displayorder_old_hint' => 'Indiquer les numéros de formulaires à afficher séparés par des virgules ex: 2,4,pages (\'pages\' est pour indiquer les pages, \'logpages\' pour les logs, ou le nom d\'un tag)',
   'AB_advanced_action_newtextsearch_displayorder_titles' => 'Titres',
   'AB_advanced_action_newtextsearch_titles_hint' => 'Titres associés séparés par des virgules (vide = titre par défaut) ; ex: "Agenda,,Page"',
   'AB_advanced_action_newtextsearch_limit_label' => 'Nombre de résultats maximum',
   'AB_advanced_action_newtextsearch_displayorder_type_pages' => 'Pages',
   'AB_advanced_action_newtextsearch_displayorder_type_logpages' => 'Pages de log',
   'AB_advanced_action_newtextsearch_displayorder_type_form' => 'Formulaire',
   'AB_advanced_action_newtextsearch_displayorder_type_tag' => 'Tag',
   'AB_advanced_action_newtextsearch_displayorder_type' => 'Type',
   'AB_advanced_action_newtextsearch_displayorder_title' => 'Titre',
   'AB_advanced_action_newtextsearch_displayorder_value' => 'Valeur',
   'AB_advanced_action_newtextsearch_viewtype_label' => 'Type d\'affichage',
   'AB_advanced_action_newtextsearch_viewtype_modal' => 'Fenêtre modale',
   'AB_advanced_action_newtextsearch_viewtype_link' => 'Onglet courant',
   'AB_advanced_action_newtextsearch_viewtype_newtab' => 'Nouvel onglet',
   'AB_advanced_action_textsearch_onlytags_label' => 'Seulement les tags :',
   'AB_advanced_action_textsearch_onlytags_hint' => 'séparés par des virgules',
   'AB_advanced_action_textsearch_nbcols_label' => 'Nombre de colonnes',
   'AB_advanced_action_textsearch_nbcols_hint' => '0 = mise en colonne automatique et variable en fonction du contenu',
   'AB_advanced_action_textsearch_phrase_label_forced' => 'Texte de recherche forcé par défaut',
   'AB_advanced_action_textsearch_phrase_hint' => 'Si non vide, la saisie n\'est plus affichée.',
   'AB_advanced_action_textsearch_separator_hint' => 'Ce paramètre n\'est utile que pour le template standard.',
   'AB_advanced_action_textsearch_size_hint' => 'Ce paramètre est actif en fonction des réglages du thème',


   // templates/newtextsearch-by-category.twig
   'ADVANCEDSEARCH_SEE_MORE' => 'Voir plus',
   'ADVANCEDSEARCH_TO_UPDATE' => 'En cours de mise à jour',
];
