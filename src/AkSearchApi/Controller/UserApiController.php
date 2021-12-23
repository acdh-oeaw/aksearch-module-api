<?php
/**
 * AK: User API controller.
 *
 * PHP version 7
 *
 * Copyright (C) AK Bibliothek Wien 2020.
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
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Cache\Manager as CacheManager;

/**
 * AK: User API controller.
 * 
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class UserApiController extends \VuFindApi\Controller\ApiController
    implements \VuFindApi\Controller\ApiInterface, \Laminas\Log\LoggerAwareInterface
{
    use \AkSearchApi\Controller\ApiTrait;
    use \AkSearch\ILS\Driver\AlmaTrait;
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFind\ILS\Driver\CacheTrait;


    /**
	 * AK: Cache manager
	 *
	 * @var CacheManager
	 */
    protected $cache;
    
    /**
     * AK: Constructor
     *
     * @param ServiceLocatorInterface $sm Service manager
     */
    public function __construct(ServiceLocatorInterface $sm, CacheManager $cache) {
        parent::__construct($sm);
        $this->cache = $cache;
    }

    /**
     * AK: Get Swagger specification JSON fragment for services provided by the
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
     * AK: Checking for swagger in request
     *
     * @param \Laminas\Mvc\MvcEvent $e Event
     *
     * @return mixed
     * @throws Exception\DomainException
     */
    public function onDispatch(\Laminas\Mvc\MvcEvent $e)
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

    /**
     * AK: Authentication action for AKsearch user with Alma
     *
     * @return \Laminas\Http\Response
     */
    public function authAction() {
        // Check permission
        if ($result = $this->isAccessDenied('access.api.AK.User.Auth')) {
            return $result;
        }

        // Check if login is enabled
        if (!$this->getAuthManager()->loginEnabled()) {
            return $this->output([], self::STATUS_ERROR, 423, 'Login not enabled');
        }

        // Get HTTP request object
        $request = $this->getRequest();

        // Get request method (GET, POST, ...)
        $requestMethod = $request->getMethod();
        
        // Check if we got a POST request
		if ($requestMethod != 'POST') {
            return $this->output([], self::STATUS_ERROR, 405,
                'Only POST requests are allowed');
        }
        
        // Initialize variables for the return value
        // U = Unknown (Default: we don't know yet if the credentials are valid)
        $isValid = 'U';
        // U = Unknown (Default: we don't know yet if the user exists)
        $userExists = 'U';
        // U = Unknown (Default: we don't know yet if the user account is expired)
		$isExpired = 'U';
		$expired = null;
		$expireDateTS = null;
        $expiryDateFormatted = null;
        // U = Unknown (Default: We don't know if the user has blocks)
		$isBlocked = 'U'; 
		$blocks = null;
		$hasError = null;
		$errorMsg = null;
		$userGroupCode = null;
        $userGroupDesc = null;
        // Default HTTP status code for output
        $httpStatusCode = 200;
        // Default status for output
        $status = self::STATUS_OK;
        
        // Get GET params
        $getParams = $request->getQuery()->toArray();

        // Get authentication mode
        $authMode = $getParams['mode'] ?? 'default';

        // HTTP status codes are dependent from auth mode. If mode "apa" is used, we
        // always have to return status code "200".
        $unauthorized401 = ($authMode == 'apa') ? 200 : 401;
		$forbidden403 = ($authMode == 'apa') ? 200 : 403;
        
        // Get POST params
        $postParams = $this->getRequest()->getPost();

        // Set CSRF hash to post params so that we can use login() method
        $postParams['csrf'] = $this->getAuthManager()->getCsrfHash();

        // Get post JSON body and add username and password parameters to the request
        // for passing it on to the login function
        $postBodyJson = $request->getContent();
        $postBodyArr = json_decode($postBodyJson, true);
        $username = $postBodyArr['username'] ?? null;
        $postParams['username'] = $username;
        $postParams['password'] = $postBodyArr['password'] ?? null;

        // Try to authenticate and process the result
        try {
            // Try to login and get user object
            $user = $this->getAuthManager()->login($this->getRequest());

            // If we got this far without an exception thrown in the login function,
            // the user with the given credentials exists.
            $userExists = 'Y';

            // On successful login, some user information from Alma is written
            // to the object cache (see Alma ILS driver). We get the object cache
            // here and use it to query the user information for further
            // processing.
            $cache = $this->cache->getCache('object');

            // This is necessary as the cache keys created in Alma.php are also
            // cleaned. We need the same key as created in Alma.php to get the
            // cached values. This is especially important for our usernames that
            // start with "$" as this character is not allowed in cache keys.
            $cleanUsername = $this->getCleanCacheKey($username);
            
            // Get user group code from cache. The value is set to the cache when
            // calling the "login" method above, which in turn calls the
            // "authenticate" function which finally calls the "getMyProfile"
            // function from the ILS driver that actually sets the cache value.
            $userGroupCode = $cache->getItem(
                'Alma_User_' . $cleanUsername . '_GroupCode'
            );

            // Get user group description from cache. See above for information about
            // how the value was set to the cache.
            $userGroupDesc = $cache->getItem(
                'Alma_User_' . $cleanUsername . '_GroupDesc'
            );

            // Get user expiry date from cache. See above for information about how
            // the value was set to the cache.
            $expiryDateFormatted = $cache->getItem(
                'Alma_User_' . $cleanUsername . '_ExpiryDate'
            );

            // Check for account blocks
            try {
                $patron = ['id' => $username];
                if ($ilsBlocks = $this->getILS()->getAccountBlocks($patron)) {
                    $blocks = [];
                    foreach ($ilsBlocks as $key => $ilsBlock) {
                        // We don't get a block code from 'getAccountBlocks'
                        $blocks[$key]['code'] = 'none';
                        $blocks[$key]['note'] = $ilsBlock;
                    }
                    $isBlocked = 'Y';
                } else {
                    $isBlocked = 'N';
                }
            } catch (\Exception $e) {
                // We are not able to determine if the user has blocks so we assume
                // he has no blocks.
                $isBlocked = 'N';
            }

            // Check if user account is expired
            try {
                // Get date format from config
                $dateFormat = $this->getConfig()->Site->displayDateFormat ?? 'Y-m-d';
                // Get DateTime object for expirty date. We use 'UTC' time for being
                // able to do better time comparison below.
                $expiryDateObj = \DateTime::createFromFormat(
                    $dateFormat,
                    $expiryDateFormatted,
                    new \DateTimeZone('UTC')
                );
                // Set time of expiry to 23:59:59 o'clock
                $expiryDateObj->setTime(23, 59, 59);
                // Get the timestamp of the exiry date
                $expireDateTS = $expiryDateObj->getTimestamp();
                // Get "now" timestamp
                $nowTs = time();
                // Compare UTC "now timestamp" with UTC "expiry timestamp"
                if ($expireDateTS < $nowTs) {
                    $isExpired = 'Y';
                    $expired['timestamp'] = $expireDateTS;
                    $expired['formatted'] = $expiryDateFormatted;
                } else {
                    $isExpired = 'N';
                }
            } catch (\Exception $e) {
                // We are not able to determine if the user account is expired so we
                // assume it is not expired.
                $isExpired = 'N';
            }

            // Check for general account validity
            if ($isBlocked == 'Y' || $isExpired == 'Y') {
                $isValid = 'N';
                $status = self::STATUS_ERROR;
                $httpStatusCode = $forbidden403;
            } else {
                $isValid = 'Y';
                $httpStatusCode = 200;
            }
        } catch (\Exception $e) {
            // User was not found in VuFind database and/or Alma with the given
            // credentials.
            $userExists = 'N';
            $isValid = 'N';
            $hasError = 'Y';
            $status = self::STATUS_ERROR;
            $errorMsg = $this->translate('Invalid Patron Login');
            $httpStatusCode = $unauthorized401;
        }

        // Initialize content variable
        $content = null;

        // Switch between modes (mainly APA and default)
        switch (strtolower($authMode)) {
            case 'apa':
                // APA authentication requires XML output format
                $this->outputMode = 'xml';
                $content = new SimpleXMLElement(
                    '<?xml version="1.0" encoding="UTF-8"?><response/>'
                );

                // Default status: Username or password wrong
                $status = '-1';

                // Set status based on values we got above
                if ($isValid === 'Y') {
                    $status = 3; // User is allowed to authenticate
                } else if ($isBlocked === 'Y' || $isExpired === 'Y') {
                    $status = 1; // User blocked from access
                }

                $content->addChild('status', $status);
                $content->addChild('userid', $username);
                break;
            default:
                // Default output is in JSON format
                $this->outputMode = 'json';

                // Initialize return variable
				$content = [];
		
				// Create the return array
				$content['user']['isValid'] = $isValid;
				$content['user']['exists'] = $userExists;
                if ($userGroupDesc) {
                    $content['user']['group']['desc'] = $userGroupDesc;
                };
				if ($userGroupCode) {
                    $content['user']['group']['code'] = $userGroupCode;
                };
				$content['expired']['isExpired'] = $isExpired;
				if ($expired) {
                    $content['expired']['date'] = $expired;
                }
				$content['blocks']['isBlocked'] = $isBlocked;
				if ($blocks) {
                    $content['blocks']['reasons'] = $blocks;
                }
				if ($hasError) {
                    $content['request']['hasError'] = $hasError;
                }
				if ($errorMsg) {
                    // The error message from the database or ILS
                    $content['request']['errorMsg'] = $errorMsg;
                }

                // Remove "null" values from array
                $content = array_filter($content);
                break;
        }

        return $this->output($content, $status, $httpStatusCode);
    }
}
?>