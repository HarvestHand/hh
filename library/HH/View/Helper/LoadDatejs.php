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
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_View
 */

/**
 * Load data JS based on locale
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: LoadDatejs.php 329 2011-09-27 01:14:40Z farmnik $
 * @package   HH_View
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_View_Helper_LoadDatejs extends Zend_View_Helper_Abstract
{
    protected $_locales = array(
        'af_ZA' => 'af-ZA',
        'ar_AE' => 'ar-AE',
        'ar_BH' => 'ar-BH',
        'ar_DZ' => 'ar-DZ',
        'ar_EG' => 'ar-EG',
        'ar_IQ' => 'ar-IQ',
        'ar_JO' => 'ar-JO',
        'ar_KW' => 'ar-KW',
        'ar_LB' => 'ar-LB',
        'ar_LY' => 'ar-LY',
        'ar_MA' => 'ar-MA',
        'ar_OM' => 'ar-OM',
        'ar_QA' => 'ar-QA',
        'ar_SA' => 'ar-SA',
        'ar_SY' => 'ar-SY',
        'ar_TN' => 'ar-TN',
        'ar_YE' => 'ar-YE',
        'az_AZ' => 'az-Latn-AZ',
        'be_BY' => 'be-BY',
        'bg_BG' => 'bg-BG',
        'bs_BA' => 'bs-Latn-BA',
        'ca_ES' => 'ca-ES',
        'cs_CZ' => 'cs-CZ',
        'cy_GB' => 'cy-GB',
        'da_DK' => 'da-DK',
        'de_AT' => 'de-AT',
        'de_CH' => 'de-CH',
        'de_DE' => 'de-DE',
        'de_LI' => 'de-LI',
        'de_LU' => 'de-LU',
        'dv_MV' => 'dv-MV',
        'el_GR' => 'el-GR',
        'en_029' => 'en-029',
        'en_AU' => 'en-AU',
        'en_BZ' => 'en-BZ',
        'en_CA' => 'en-CA',
        'en_GB' => 'en-GB',
        'en_IE' => 'en-IE',
        'en_JM' => 'en-JM',
        'en_NZ' => 'en-NZ',
        'en_PH' => 'en-PH',
        'en_TT' => 'en-TT',
        'en_US' => 'en-US',
        'en_ZA' => 'en-ZA',
        'en_ZW' => 'en-ZW',
        'es_AR' => 'es-AR',
        'es_BO' => 'es-BO',
        'es_CL' => 'es-CL',
        'es_CO' => 'es-CO',
        'es_CR' => 'es-CR',
        'es_DO' => 'es-DO',
        'es_EC' => 'es-EC',
        'es_ES' => 'es-ES',
        'es_GT' => 'es-GT',
        'es_HN' => 'es-HN',
        'es_MX' => 'es-MX',
        'es_NI' => 'es-NI',
        'es_PA' => 'es-PA',
        'es_PE' => 'es-PE',
        'es_PR' => 'es-PR',
        'es_PY' => 'es-PY',
        'es_SV' => 'es-SV',
        'es_UY' => 'es-UY',
        'es_VE' => 'es-VE',
        'et_EE' => 'et-EE',
        'eu_ES' => 'eu-ES',
        'fa_IR' => 'fa-IR',
        'fi_FI' => 'fi-FI',
        'fo_FO' => 'fo-FO',
        'fr_BE' => 'fr-BE',
        'fr_CA' => 'fr-CA',
        'fr_CH' => 'fr-CH',
        'fr_FR' => 'fr-FR',
        'fr_LU' => 'fr-LU',
        'fr_MC' => 'fr-MC',
        'gl_ES' => 'gl-ES',
        'gu_IN' => 'gu-IN',
        'he_IL' => 'he-IL',
        'hi_IN' => 'hi-IN',
        'hr_BA' => 'hr-BA',
        'hr_HR' => 'hr-HR',
        'hu_HU' => 'hu-HU',
        'hy_AM' => 'hy-AM',
        'id_ID' => 'id-ID',
        'is_IS' => 'is-IS',
        'it_CH' => 'it-CH',
        'it_IT' => 'it-IT',
        'ja_JP' => 'ja-JP',
        'ka_GE' => 'ka-GE',
        'kk_KZ' => 'kk-KZ',
        'kn_IN' => 'kn-IN',
        'kok_IN' => 'kok-IN',
        'ko_KR' => 'ko-KR',
        'ky_KG' => 'ky-KG',
        'lt_LT' => 'lt-LT',
        'lv_LV' => 'lv-LV',
        'mi_NZ' => 'mi-NZ',
        'mk_MK' => 'mk-MK',
        'mn_MN' => 'mn-MN',
        'mr_IN' => 'mr-IN',
        'ms_BN' => 'ms-BN',
        'ms_MY' => 'ms-MY',
        'mt_MT' => 'mt-MT',
        'nb_NO' => 'nb-NO',
        'nl_BE' => 'nl-BE',
        'nl_NL' => 'nl-NL',
        'nn_NO' => 'nn-NO',
        'ns_ZA' => 'ns-ZA',
        'pa_IN' => 'pa-IN',
        'pl_PL' => 'pl-PL',
        'pt_BR' => 'pt-BR',
        'pt_PT' => 'pt-PT',
        'quz_BO' => 'quz-BO',
        'quz_EC' => 'quz-EC',
        'quz_PE' => 'quz-PE',
        'ro_RO' => 'ro-RO',
        'ru_RU' => 'ru-RU',
        'sa_IN' => 'sa-IN',
        'se_FI' => 'se-FI',
        'se_NO' => 'se-NO',
        'se_SE' => 'se-SE',
        'sk_SK' => 'sk-SK',
        'sl_SI' => 'sl-SI',
        'sma_NO' => 'sma-NO',
        'sma_SE' => 'sma-SE',
        'smj_NO' => 'smj-NO',
        'smj_SE' => 'smj-SE',
        'smn_FI' => 'smn-FI',
        'sms_FI' => 'sms-FI',
        'sq_AL' => 'sq-AL',
        'sr_BA' => 'sr-Latn-BA',
        'sr_CS' => 'sr-Latn-CS',
        'sv_FI' => 'sv-FI',
        'sv_SE' => 'sv-SE',
        'sw_KE' => 'sw-KE',
        'syr_SY' => 'syr-SY',
        'ta_IN' => 'ta-IN',
        'te_IN' => 'te-IN',
        'th_TH' => 'th-TH',
        'tn_ZA' => 'tn-ZA',
        'tr_TR' => 'tr-TR',
        'tt_RU' => 'tt-RU',
        'uk_UA' => 'uk-UA',
        'ur_PK' => 'ur-PK',
        'uz_UZ' => 'uz-Latn-UZ',
        'vi_VN' => 'vi-VN',
        'xh_ZA' => 'xh-ZA',
        'zh_CN' => 'zh-CN',
        'zh_HK' => 'zh-HK',
        'zh_MO' => 'zh-MO',
        'zh_SG' => 'zh-SG',
        'zh_TW' => 'zh-TW',
        'zu_ZA' => 'zu-ZA'
    );

    /**
     * Load dateJs javascript library based on locale
     *
     * @param string|Zend_Locale $locale
     */
    public function loadDateJs($locale = null)
    {
        if (!$locale) {
            $this->view->loader()->append('datejs_date.js');
        } else {
            if ($locale instanceof Zend_Locale) {
                $locale = $locale->toString();
            }

            if (isset($this->_locales[$locale])) {
                $this->view->loader()->append('datejs_date-' . $this->_locales[$locale] . '.js');
            } else {
                $this->view->loader()->append('datejs_date.js');
            }
        }
    }
}