<?php

/**
 * API Controller
 *
 * PHP version 8
 *
 * Copyright (C) AK Bibliothek Wien 2024.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner-Tröger <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace AkSearchApi\Controller;

use function in_array;

/**
 * API Controller
 *
 * Controls the API functionality
 *
 * @category AKsearch
 * @package  Controller
 * @author   Michael Birkner-Tröger <michael.birkner@akwien.at>
 * @license  https://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class ApiController extends \VuFindApi\Controller\ApiController
{
    use ApiTrait;

    /**
     * Get API specification JSON fragment for the root nodes
     * 
     * AK: Change method from "protected" to "public" to avoid the
     * error "Access level to [this method] must be public (as in
     * class VuFindApi\Controller\ApiInterface)"
     *
     * @return string
     */
    public function getApiSpecFragment()
    {
        $config = $this->getConfig();
        $params = [
            'config' => $config,
            'version' => \VuFind\Config\Version::getBuildVersion(),
        ];
        return $this->getViewRenderer()->render('api/openapi', $params);
    }

}
