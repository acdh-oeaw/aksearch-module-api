<?php
/**
 * AK: Extended additional functionality for API controllers.
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

/**
 * AK: Extending additional functionality for API controllers.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait ApiTrait
{
    use \VuFindApi\Controller\ApiTrait;

    /**
     * Send output data and exit.
     * AK: Add XML as possible return type.
     *     Must be set with ... in controller.
     *
     * @param mixed  $data     The response data
     * @param string $status   Status of the request
     * @param int    $httpCode A custom HTTP Status Code
     * @param string $message  Status message
     *
     * @return \Zend\Http\Response
     * @throws \Exception
     */
    protected function output($data, $status, $httpCode = null, $message = '')
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        if ($httpCode !== null) {
            $response->setStatusCode($httpCode);
        }
        if (null === $data) {
            return $response;
        }
        $output = $data;
        if (!isset($output['status'])) {
            $output['status'] = $status;
        }
        if ($message && !isset($output['statusMessage'])) {
            $output['statusMessage'] = $message;
        }
        $jsonOptions = $this->jsonPrettyPrint ? JSON_PRETTY_PRINT : 0;
        if ($this->outputMode == 'json') {
            $headers->addHeaderLine('Content-type', 'application/json');
            $response->setContent(json_encode($output, $jsonOptions));
            return $response;
        } elseif ($this->outputMode == 'jsonp') {
            $headers->addHeaderLine('Content-type', 'application/javascript');
            $response->setContent(
                $this->jsonpCallback . '(' . json_encode($output, $jsonOptions)
                . ');'
            );
            return $response;
        } elseif ($this->outputMode == 'xml') {
            // AK: Adding XML output
            $headers->addHeaderLine('Content-type', 'application/xml');
            if ($data instanceof \SimpleXMLElement) {
                $response->setContent($data->asXML());
            } else {
                throw new \Exception('Instance of SimpleXMLElement must be used');
            }
            return $response;
        } else {
            throw new \Exception('Invalid output mode');
        }
    }
}
