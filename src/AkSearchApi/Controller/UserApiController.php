<?php
/**
 * User API controller.
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2019.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace AkSearchApi\Controller;

use SimpleXMLElement;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * User API controller.
 * 
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class UserApiController extends \VuFindApi\Controller\ApiController
    implements \VuFindApi\Controller\ApiInterface, \Zend\Log\LoggerAwareInterface
{
    use \AkSearchApi\Controller\ApiTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service manager
     */
    public function __construct(ServiceLocatorInterface $sm) {
        parent::__construct($sm);
    }

    /**
     * Get Swagger specification JSON fragment for services provided by the
     * controller
     *
     * @return string
     */
    public function getSwaggerSpecFragment()
    {
        // Don't publish details for the User API
        return '{}';
    }

    /**
     * Execute the request
     *
     * @param \Zend\Mvc\MvcEvent $e Event
     *
     * @return mixed
     * @throws Exception\DomainException
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        // Add CORS headers and handle OPTIONS requests. This is a simplistic
        // approach since we allow any origin. For more complete CORS handling
        // a module like zfr-cors could be used.
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Access-Control-Allow-Origin: *');
        $request = $this->getRequest();
        if ($request->getMethod() == 'OPTIONS') {
            // Disable session writes
            $this->disableSessionWrites();
            $headers->addHeaderLine(
                'Access-Control-Allow-Methods', 'GET, POST, OPTIONS'
            );
            $headers->addHeaderLine('Access-Control-Max-Age', '86400');

            return $this->output(null, 204);
        }
        if (null !== $request->getQuery('swagger')) {
            return $this->createSwaggerSpec();
        }
        return parent::onDispatch($e);
    }

    public function authAction() {
        if ($result = $this->isAccessDenied('access.api.AK.User.Auth')) {
            return $result;
        }

        /*$request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();*/
        $request = $this->getRequest()->getPost();

        if ($this->getAuthManager()->loginEnabled()) {
            $user = $this->getAuthManager()->login($request);
        }

        $mode = $request['mode'] ?? 'default';

        if ('apa' === strtolower($mode)) {
            $this->outputMode = 'xml';
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?>'
                . '<response/>');
            $xml->addChild('status');
            $xml->addChild('userid');
        }
        


        return $this->output($xml, self::STATUS_OK);
    }
}
?>