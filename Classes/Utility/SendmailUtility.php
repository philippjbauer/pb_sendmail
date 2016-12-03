<?php
namespace PhilippBauer\PbSendmail\Utility;

/***
 *
 * This file is part of the "PB Sendmail" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2016 Philipp Bauer <hello@philippbauer.org>, Philipp Bauer _ Freelance Webdeveloper
 *
 ***/

use \TYPO3\CMS\Core\Mail\MailMessage;
use \TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use \TYPO3\CMS\Extbase\Object\ObjectManager;
use \PhilippBauer\PbSendmail\Exceptions\SendmailException;

/**
 * Sendmail
 */
class SendmailUtility
{
    /**
     * The extension key
     * 
     * @var string
     */
    protected $extKey = '';

    /**
     * The extension name
     * 
     * @var string
     */
    protected $extName = '';

    /**
     * The extension path
     * 
     * @var string
     */
    protected $extPath = '';

    /**
     * TYPO3 MailSystem
     * 
     * @var \TYPO3\CMS\Core\Mail\MailMessage
     */
    protected $mailer = null;

    /**
     * The from email address
     * 
     * @var array
     */
    protected $from = [];

    /**
     * The to email addresses
     * 
     * @var array
     */
    protected $to = [];

    /**
     * The cc email addresses
     * 
     * @var array
     */
    protected $cc = [];

    /**
     * The bcc email addresses
     * 
     * @var array
     */
    protected $bcc = [];

    /**
     * The subject
     * 
     * @var string
     */
    protected $subject = '';

    /**
     * The content
     * 
     * @var mixed(string/array)
     */
    protected $content = null;

    /**
     * The defaultViewConfiguration
     * 
     * @var array
     */
    protected $defaultViewConfiguration = [];

    /**
     * The viewConfiguration
     * 
     * @var array
     */
    protected $viewConfiguration = [];

    /**
     * __construct
     *
     * Initiates the TYPO3 MailSystem.
     */
    public function __construct($extKey = null)
    {
        // Throw exception on missing parameter
        if ($extKey === null) {
            throw new SendmailException("No extension key parameter given.", 1480717855);
        }

        // Set the extension variables
        $this->setExtKey($extKey);
        $this->setExtName(str_replace('tx_', '', $extKey));
        $this->setExtPath(ExtensionManagementUtility::extPath(strtolower($this->getExtName())));

        // Set the default mailer
        $this->setMailer(new MailMessage);

        // Set the default view config
        $this->setDefaultViewConfiguration([
            'extKey' => $this->getExtKey(),
            'extName' => $this->getExtName(),
            'templateRootPath' => $this->getExtPath() . 'Resources/Private/Templates/',
            'layoutRootPath' => $this->getExtPath() . 'Resources/Private/Layouts/',
            'partialRootPath' => $this->getExtPath() . 'Resources/Private/Partials/',
            'templateRelPath' => 'Email/HtmlBody.html',
        ]);
    }

    /**
     * Sends a simple mail
     * Sends a simple text/plain messge to the given receiver(s).
     *
     * @return void
     */
    public function sendSimpleMail()
    {
        // Check for missing attributes
        $this->checkForMissingAttributes();

        // Send mail
        $this->getMailer()
             ->setFrom($this->getFrom())
             ->setTo($this->getTo())
             ->setCc($this->getCc())
             ->setBcc($this->getBcc())
             ->setSubject($this->getSubject())
             ->setBody($this->getContent(), 'text/plain');

        if (!empty($this->getCc())) {
            $this->getMailer()->setCc($this->getCc());
        }

        return $this->getMailer()->send();
    }

    /**
     * Sends a html mail with templated output
     *
     * @example "Example/Sendmail.php" 17 49 Example for the usage of sendHtmlMail
     * @return void
     */
    public function sendHtmlMail()
    {
        // Check for missing attributes
        $this->checkForMissingAttributes();

        // Set up email body view
        $emailView = $this->setupStandaloneView();

        // Setup mail
        $this->getMailer()
             ->setFrom($this->getFrom())
             ->setTo($this->getTo())
             ->setSubject($this->getSubject())
             ->setBody($emailView->render(), 'text/html');

        // Add CC receiver
        if (empty($this->getCc()) === false) {
            $this->getMailer()->setCc($this->getCc());
        }

        // Add BCC receiver
        if (empty($this->getBcc()) === false) {
            $this->getMailer()->setBcc($this->getBcc());
        }

        // Send mail and return result
        return $this->getMailer()->send();
    }

    /**
     * Preview HTML Mail
     * Returns the HTML preview for a html mail.
     *
     * @example "Example/Sendmail.php" 67 32 Example for the usage of previewHtmlMail
     * @return string
     */
    public function previewHtmlMail()
    {
        // Check for missing attributes
        $this->checkForMissingAttributes();

        // Set up email body view
        $emailView = $this->setupStandaloneView();

        // Return the email body
        return $emailView->render();
    }

    /**
     * Set up the standalone view for the email template
     * 
     * @return \TYPO3\CMS\Fluid\View\StandaloneView
     */
    private function setupStandaloneView()
    {  
        $objectManager = new ObjectManager;

        // Merge default and user view configuration
        $viewConfiguration = array_merge($this->getDefaultViewConfiguration(), $this->getViewConfiguration());
        
        // Set up the view
        $view = $objectManager->get('\\TYPO3\\CMS\\Fluid\\View\\StandaloneView');
        
        // Set extension name
        $view->getRequest()->setControllerExtensionName($viewConfiguration['extName']);
        
        // Set paths
        $view->setTemplatePathAndFilename($viewConfiguration['templateRootPath'] . $viewConfiguration['templateRelPath']);
        $view->setLayoutRootPath($viewConfiguration['layoutRootPath']);
        $view->setPartialRootPath($viewConfiguration['partialRootPath']);
        
        // Assign template variables
        foreach ($this->getContent() as $key => $value) {
            $view->assign($key, $value);
        }

        return $view;
    }

    /**
     * Make sure minimal attribute requirements are met
     * 
     * @return void
     */
    private function checkForMissingAttributes()
    {
        // Throw exception on missing sender
        if (empty($this->getFrom())) {
            throw new SendmailException("No sender given.", 1480717955);
        }
        
        // Throw exception on missing recipient
        if (empty($this->getTo())) {
            throw new SendmailException("No recipient given.", 1480717955);
        }

        // Throw exception on missing subject
        if (empty($this->getSubject())) {
            throw new SendmailException("No subject given.", 1480717955);
        }

        // Throw exception on missing content
        if (empty($this->getContent())) {
            throw new SendmailException("No content given.", 1480717955);
        }
    }

    /**
     * __destrucor
     *
     * Destructs the TYPO3 MailSystem.
     *
     * @return void
     */
    public function __destructor()
    {
        unset($this->mailer);
    }

    /**
     * Gets the extKey.
     *
     * @ignore
     * @return string
     */
    public function getExtKey()
    {
        return $this->extKey;
    }

    /**
     * Sets the extKey.
     *
     * @ignore
     * @param  string $extKey the extKey
     * @return self
     */
    public function setExtKey($extKey)
    {
        $this->extKey = $extKey;

        return $this;
    }

    /**
     * Gets the extName
     *
     * @ignore
     * @return string
     */
    public function getExtName()
    {
        return $this->extName;
    }

    /**
     * Sets the extName
     *
     * @ignore
     * @param  string $extName
     * @return string
     */
    public function setExtName($extName)
    {
        $this->extName = $extName;
        
        return $this;
    }

    /**
     * Gets the extPath
     *
     * @ignore
     * @return string
     */
    public function getExtPath()
    {
        return $this->extPath;
    }

    /**
     * Sets the extPath
     *
     * @ignore
     * @param  string $extPath
     * @return string
     */
    public function setExtPath($extPath)
    {
        $this->extPath = $extPath;

        return $this;
    }

    /**
     * Gets the TYPO3 MailSystem.
     *
     * @ignore
     * @return \TYPO3\CMS\Core\Mail\MailMessage
     */
    public function getMailer()
    {
        return $this->mailer;
    }

    /**
     * Sets the TYPO3 MailSystem.
     *
     * @ignore
     * @param  \TYPO3\CMS\Core\Mail\MailMessage $mailer the mailer
     * @return self
     */
    public function setMailer(MailMessage $mailer)
    {
        $this->mailer = $mailer;

        return $this;
    }

    /**
     * Gets the from.
     *
     * @ignore
     * @return array
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * Sets the from.
     *
     * @ignore
     * @param  array $from the from
     * @return self
     */
    public function setFrom($from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Gets the to.
     *
     * @ignore
     * @return array
     */
    public function getTo()
    {
        return $this->to;
    }

    /**
     * Sets the to.
     *
     * @ignore
     * @param  array $to the to
     * @return self
     */
    public function setTo($to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Gets the cc.
     *
     * @ignore
     * @return array
     */
    public function getCc()
    {
        return $this->cc;
    }

    /**
     * Sets the cc.
     *
     * @ignore
     * @param  array $cc the cc
     * @return self
     */
    public function setCc($cc)
    {
        $this->cc = $cc;

        return $this;
    }

    /**
     * Gets the bcc.
     *
     * @ignore
     * @return array
     */
    public function getBcc()
    {
        return $this->bcc;
    }

    /**
     * Sets the bcc.
     *
     * @ignore
     * @param  array $bcc the bcc
     * @return self
     */
    public function setBcc($bcc)
    {
        $this->bcc = $bcc;

        return $this;
    }

    /**
     * Gets the subject.
     *
     * @ignore
     * @return string
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Sets the subject.
     *
     * @ignore
     * @param  string $subject the subject
     * @return self
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Gets the content.
     *
     * @ignore
     * @return mixed(string/array)
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Sets the content.
     *
     * @ignore
     * @param  mixed(string/array) $content the content
     * @return self
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Gets the type.
     *
     * @ignore
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the type.
     *
     * @ignore
     * @param  string $type the type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Gets the defaultViewConfiguration.
     *
     * @ignore
     * @return array
     */
    public function getDefaultViewConfiguration()
    {
        return $this->defaultViewConfiguration;
    }

    /**
     * Sets the defaultViewConfiguration.
     *
     * @ignore
     * @param  array $defaultViewConfiguration the default view configuration
     * @return self
     */
    public function setDefaultViewConfiguration($defaultViewConfiguration)
    {
        $this->defaultViewConfiguration = $defaultViewConfiguration;

        return $this;
    }

    /**
     * Gets the viewConfiguration.
     *
     * @ignore
     * @return array
     */
    public function getViewConfiguration()
    {
        return $this->viewConfiguration;
    }

    /**
     * Sets the viewConfiguration.
     *
     * @ignore
     * @param  array $viewConfiguration the view configuration
     * @return self
     */
    public function setViewConfiguration($viewConfiguration)
    {
        $this->viewConfiguration = $viewConfiguration;

        return $this;
    }
}

