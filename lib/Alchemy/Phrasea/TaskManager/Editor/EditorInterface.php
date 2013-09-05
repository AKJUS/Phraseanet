<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2013 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Phrasea\TaskManager\Editor;

use Alchemy\Phrasea\Core\Configuration\ConfigurationInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

interface EditorInterface
{
    const FORM_TYPE_STRING = 'string';
    const FORM_TYPE_BOOLEAN = 'boolean';
    const FORM_TYPE_INTEGER = 'integer';

    /**
     * Receives a request containing the XML task setting and the value of
     * a form.
     *
     * This method validates and merge form data in the XML.
     *
     * @param Request $request
     *
     * @return Response The updated XML wrapped in a response
     *
     * @throws BadRequestHttpException In case the XML is invalid.
     */
    public function updateXMLWithRequest(Request $request);

    /**
     * Treats a facility request.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function facility(Request $request);

    /**
     * Returns the job default settings.
     *
     * Configuration is used to populate the default configuration with
     * configuration values.
     *
     * @param Configuration $config
     *
     * @return string An XML string
     */
    public function getDefaultSettings(ConfigurationInterface $config = null);

    /**
     * Returns the default period of the job.
     * The job will be run every period.
     *
     * @return float
     */
    public function getDefaultPeriod();

    /**
     * Returns the path to the template used to edit task settings.
     *
     * @return string
     */
    public function getTemplatePath();
}
