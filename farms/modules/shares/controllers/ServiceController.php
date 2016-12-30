<?php
/**
 * HarvestHand
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License Version 3
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to farmnik@harvesthand.com so we can send you a copy immediately.
 *
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of AdminController
 *
 * @package
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class Shares_ServiceController extends HHF_Controller_Action
{
    public function init()
    {
        parent::init();

        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        if (!empty($_SERVER['HTTP_REFERER']) && ($urlParts = parse_url($_SERVER['HTTP_REFERER'])) !== false) {

            // Allow CORS
            $matchedHost = false;

            if (!empty($urlParts['host'])) {
                list ($farm, $url) = explode('.', $urlParts['host'], 2);

                if (strcasecmp(Bootstrap::$rootDomain, $url) === 0) {
                    $matchedHost = Bootstrap::$rootDomain;
                }
            }

            if ($matchedHost) {
                $this->getResponse()
                    ->setHeader(
                        'Access-Control-Allow-Credentials',
                        'true'
                    );

                $this->getResponse()
                    ->setHeader(
                        'Access-Control-Allow-Origin',
                        $urlParts['scheme'] . '://' . $farm . '.' . $matchedHost
                    );
            }
        }
    }

    public function __call($action, $params)
    {
        return $this->_response->setBody(json_encode('you\'re nuts'));
    }

    public function addonCategoriesAction()
    {
        $categories = HHF_Domain_Addon_Category::fetchAllForm(
            $this->farm
        );

        return $this->_response->setBody(json_encode($categories));
    }

    public function locationsAction()
    {
        $locations = HHF_Domain_Location::fetchLocations(
            $this->farm,
            array(
                'fetch' => HHF_Domain_Location::FETCH_ENABLED
            )
        );

        $response = array();

        foreach ($locations as $location) {
            $response[] = $location->toArray();
        }

        return $this->_response->setBody(json_encode($response));
    }

    public function certificationsAction()
    {
        $certifications = HHF_Domain_Certification::getSelectOptions();

        $response = array();
        $allowed = array(
            '',
            HHF_Domain_Certification::BIODYNAMIC,
            HHF_Domain_Certification::CERTIFIED_NATURAL,
            HHF_Domain_Certification::ORGANIC,
            HHF_Domain_Certification::FAIR_TRADE,
            HHF_Domain_Certification::FREE_RANGE
        );

        foreach ($certifications as $key => $certification) {
            if (!in_array($key, $allowed)) {
                continue;
            }


            $response[$key] = $certification;
        }

        return $this->_response->setBody(json_encode($response));
    }

    public function addonAction()
    {
        $id = (int) $this->_request->getParam('id');
        $externalId = (int) $this->_request->getParam('externalId');
        $vendorId = (int) $this->_request->getParam('vendorId');

        $addon = false;

        if (!empty($id)) {
            $addon = new HHF_Domain_Addon($this->farm, $id);
        } else if (!empty($externalId)) {
            $addon = HHF_Domain_Addon::fetchOne(
                $this->farm,
                array(
                    'where' => array(
                        'externalId' => $externalId,
                        'vendorId' => $vendorId
                    )
                )
            );
        }

        if ($addon === false || $addon->isEmpty()) {
            $response = false;
        } else {
            $response = $addon->toArray();

            $response['locations'] = array();

            foreach ($addon->getLocations() as $location) {
                $response['locations'][] = $location['locationId'];
            }
        }

        return $this->_response->setBody(json_encode($response));
    }
} 
