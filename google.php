<?php
/**
 * @package         IdentityProof
 * @subpackage      Plugins
 * @author          Todor Iliev
 * @copyright       Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license         http://www.gnu.org/licenses/gpl-3.0.en.html GNU/GPLv3
 */

// no direct access
defined('_JEXEC') or die;

jimport('Prism.init');
jimport('Identityproof.init');

/**
 * Proof of Identity - Google Plugin
 *
 * @package        IdentityProof
 * @subpackage     Plugins
 */
class plgIdentityproofGoogle extends JPlugin
{
    protected $autoloadLanguage = true;

    /**
     * @var JApplicationSite
     */
    protected $app;

    /**
     * This method prepares a code that will be included to step "Extras" on project wizard.
     *
     * @param string                   $context This string gives information about that where it has been executed the trigger.
     * @param stdClass                 $item    User data.
     * @param Joomla\Registry\Registry $params  The parameters of the component
     *
     * @return null|string
     */
    public function onDisplayVerification($context, &$item, &$params)
    {
        if (strcmp('com_identityproof.proof', $context) !== 0) {
            return null;
        }

        if ($this->app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp('html', $docType) !== 0) {
            return null;
        }

        if (!isset($item->id) or !$item->id) {
            return null;
        }

        $profile = new Identityproof\Profile\Google(JFactory::getDbo());
        $profile->load(array('user_id' => $item->id));

        $loginUrl = '#';
        if (!$profile->getId()) {
            $client = $client = $this->getClient();
            $client->setScopes(array('https://www.googleapis.com/auth/plus.me'));

            $loginUrl = $client->createAuthUrl();
        }

        // Get the path for the layout file
        $path = JPath::clean(JPluginHelper::getLayoutPath('identityproof', 'google'));

        // Render the login form.
        ob_start();
        include $path;
        $html = ob_get_clean();

        return $html;
    }

    /**
     * This method prepares a code that will be included to step "Extras" on project wizard.
     *
     * @param string                   $context This string gives information about that where it has been executed the trigger.
     * @param Joomla\Registry\Registry $params  The parameters of the component
     *
     * @return null|string
     */
    public function onVerify($context, &$params)
    {
        if (strcmp('com_identityproof.service.google', $context) !== 0) {
            return null;
        }

        if ($this->app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp('html', $docType) !== 0) {
            return null;
        }

        $output = array(
            'redirect_url' => JRoute::_(IdentityproofHelperRoute::getProofRoute()),
            'message' => ''
        );

        $userId  = JFactory::getUser()->get('id');
        if (!$userId) {
            $output['message'] = JText::_('PLG_IDENTITYPROOF_GOOGLE_INVALID_USER');
            return $output;
        }

        $code        = $this->app->input->get->get('code', null, 'raw');
        if (!$code) {
            $output['message'] = JText::_('PLG_IDENTITYPROOF_GOOGLE_INVALID_REQUEST');
            return $output;
        }

        try {
            $client = $this->getClient();
            $client->authenticate($code);

            $plus = new Google_Service_Plus($client);

            $userNode  = $plus->people->get('me', array('fields' => 'currentLocation,displayName,gender,id,image/url,name,placesLived,url,urls,verified'));
//            $client->revokeToken();

        } catch (Exception $e) {
            $output['message'] = $e->getMessage();
            return $output;
        }

        $profile = new Identityproof\Profile\Google(JFactory::getDbo());
        $profile->load(array('user_id' => $userId));

        // Get a link to user's website.
        $urls = $userNode->getUrls();
        $website = '';
        if (is_array($urls) and count($urls) > 0) {
            foreach ($urls as $url) {
                if ($url['type'] === 'other') {
                    $website = $url['value'];
                    break;
                }
            }
        }

        // Get picture.
        $image = $userNode->getImage();
        $picture = '';
        if (isset($image->url)) {
            $picture = $image->url;
        }

        // Get location.
        $placeLived = $userNode->getPlacesLived();
        $location = '';
        if (is_array($placeLived) and count($placeLived) > 0) {
            foreach ($placeLived as $place) {
                if ($place['value'] !== '') {
                    $location = $place['value'];
                    break;
                }
            }
        }

        $data = array(
            'google_id' => $userNode->getId(),
            'name' => $userNode->getDisplayName(),
            'link' => $userNode->getUrl(),
            'gender' => $userNode->getGender(),
            'website' =>  $website,
            'location' =>  $location,
            'verified' => (int)$userNode->getVerified(),
            'picture' => $picture
        );

        if (!$profile->getId()) {
            $data['user_id'] = $userId;
        }

        $profile->bind($data);
        $profile->store();

        return $output;
    }

    /**
     * This method prepares a code that will be included to step "Extras" on project wizard.
     *
     * @param string                   $context This string gives information about that where it has been executed the trigger.
     * @param Joomla\Registry\Registry $params  The parameters of the component
     *
     * @return null|string
     */
    public function onRemove($context, &$params)
    {
        if (strcmp('com_identityproof.service.google', $context) !== 0) {
            return null;
        }

        if ($this->app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp('html', $docType) !== 0) {
            return null;
        }

        $output = array(
            'redirect_url' => JRoute::_(IdentityproofHelperRoute::getProofRoute()),
            'message' => ''
        );

        $userId  = JFactory::getUser()->get('id');
        if (!$userId) {
            $output['message'] = JText::_('PLG_IDENTITYPROOF_GOOGLE_INVALID_USER');
            return $output;
        }

        $profile = new Identityproof\Profile\Google(JFactory::getDbo());
        $profile->load(array('user_id' => $userId));

        if (!$profile->getId()) {
            $output['message'] = JText::_('PLG_IDENTITYPROOF_GOOGLE_INVALID_PROFILE');
            return $output;
        }

        $profile->remove();
        $output['message'] = JText::_('PLG_IDENTITYPROOF_GOOGLE_RECORD_REMOVED_SUCCESSFULLY');

        return $output;
    }

    protected function getClient()
    {
        $filter = JFilterInput::getInstance();

        // Get URI
        $uri         = JUri::getInstance();
        $redirectUrl = $filter->clean($uri->getScheme() . '://' . $uri->getHost()) . '/index.php?option=com_identityproof&task=service.verify&service=google';

        jimport('Prism.libs.GuzzleHttp.init');
        $client = new Google_Client(array(
            'application_name' => $this->params->get('app_name'),
            'client_id' => $this->params->get('client_id'),
            'client_secret' => $this->params->get('client_secret'),
            'redirect_uri' => $redirectUrl
        ));

        return $client;
    }
}
