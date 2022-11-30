<?php

/*
 * This file is part of the YesWiki Extension advancedsearch.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Advancedsearch;

use YesWiki\Core\YesWikiAction;

class SearchInOtherPageAction extends YesWikiAction
{
    public function formatArguments($arg)
    {
        return [
            'page' => $arg['page'] ?? null,
        ];
    }

    public function run()
    {
        if (empty($this->arguments['page']) || !is_string($this->arguments['page'])) {
            return $this->render('@templates/alert-message.twig', [
                'type' => 'danger',
                'message' => _t('ADVANCEDSEARCH_SEARCH_IN_OTHER_PAGE_ERROR', ['class'=>get_class()]),
            ]);
        }

        return $this->render('@core/search-in-other-page.twig', [
            'linkedPage' => $this->arguments['page'] ?? null,
        ]);
    }
}
