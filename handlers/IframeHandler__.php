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

use YesWiki\Advancedsearch\Controller\CommonHandlers;
use YesWiki\Core\YesWikiHandler;

class IframeHandler__ extends YesWikiHandler
{
    public function run()
    {
        $commonHandlers= $this->getService(CommonHandlers::class);
        $commonHandlers->run($this->output);
    }
}
