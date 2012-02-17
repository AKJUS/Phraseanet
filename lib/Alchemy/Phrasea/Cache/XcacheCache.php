<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2010 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\Cache;

use Doctrine\Common\Cache\XcacheCache as DoctrineXcache;

/**
 *
 * @package
 * @license     http://opensource.org/licenses/gpl-3.0 GPLv3
 * @link        www.phraseanet.com
 */
class XcacheCache extends DoctrineXcache implements Cache
{

  public function flushAll()
  {
    $this->_checkAuth();

    xcache_clear_cache(XC_TYPE_VAR, 0);

    return true;
  }

}