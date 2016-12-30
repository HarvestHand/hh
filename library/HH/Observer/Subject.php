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
 * @copyright $Date: 2011-09-21 22:27:35 -0300 (Wed, 21 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Observer
 */

/**
 * Description of Subject
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Subject.php 322 2011-09-22 01:27:35Z farmnik $
 * @package   HH_Observer
 * @copyright $Date: 2011-09-21 22:27:35 -0300 (Wed, 21 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
interface HH_Observer_Subject
{
    /**
	 * Attach an observer
     *
     * @param HH_Observer $observer  The observer to attach.
	 * @return void
	 */
    public function attach(HH_Observer $observer);

    /**
	 * Detach an observer
     *
	 * @param HH_Observer $observer The observer to detach.
	 * @return void
	 */
    public function detach(HH_Observer $observer);
    
    /**
	 * Detach an observer
     *
	 * @param HH_Observer|string $observer The observer to detach.
	 * @return void
	 */
    public function detachByType($observer);
    
    /**
	 * Detach an observer
     *
	 * @return void
	 */
    public function detachAll();
}