<?php

namespace Alchemy\Tests\Phrasea\Model\Repositories;

use Alchemy\Phrasea\Model\Entities\ApiApplication;

class ApiOauthTokenRepositoryTest extends \PhraseanetTestCase
{
    public function testFindDeveloperToken()
    {
        $tok = self::$DI['app']['EM']->getRepository('Phraseanet:ApiOauthToken')->findByAccount(self::$DI['oauth2-app-acc-user']);
        $this->assertNotNull($tok);
    }

    public function testFindOauthTokens()
    {
        $tokens = self::$DI['app']['EM']->getRepository('Phraseanet:ApiOauthToken')->findOauthTokens(self::$DI['oauth2-app-acc-user']);
        $this->assertGreaterThan(0,  count($tokens));
    }
}
