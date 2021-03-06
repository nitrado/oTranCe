<?php
/**
 * This file is part of oTranCe released under the GNU/GPL 2 license
 * http://www.otrance.org
 *
 * @package    oTranCe
 * @subpackage Models
 * @version    SVN: $
 * @author     $Author$
 */
/**
 * Export
 *
 * @package         oTranCe
 * @subpackage      Models
 */
class Application_Model_Export
{
    /**
     * Array with file templates.
     *
     * @var \Application_Model_FileTemplates
     */
    private $_fileTemplates = array();

    /**
     * Array with language meta info (name, locale, ect.).
     *
     * @var array
     */
    private $_langInfo = array();

    /**
     * Will hold all language keys grouped by template id
     *
     * @var array
     */
    private $_keys;

    /**
     * Will hold all texts of the fallback language
     *
     * @var array
     */
    private $_fallbackLanguageTranslations;

    /**
     * Will hold a list of translators grouped by languageId
     *
     * @var array
     */
    private $_translatorList;

    /**
     * Will hold the project's configuration
     *
     * @var array
     */
    private $_config;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $fileTemplateModel    = new Application_Model_FileTemplates();
        $this->_fileTemplates = $fileTemplateModel->getFileTemplatesAssoc();
    }

    /**
     * Get timestamp of the latest download package
     *
     * @return int
     */
    public function getLatestDownloadPackageTimestamp()
    {
        $mTime    = 0;
        $iterator = new DirectoryIterator(DOWNLOAD_PATH);
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $mTime = $fileInfo->getMTime();
        }

        return $mTime;
    }

    /**
     * Convert a unix timestamp to date format
     *
     * @param int $timestamp The timestamp to convert
     *
     * @return string
     */
    public function convertUnixDate($timestamp)
    {
        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * Export a language from db to file
     *
     * @param int $languageId Id of language
     *
     * @return array
     */
    public function exportLanguageFile($languageId)
    {
        $languageEntriesModel = new Application_Model_LanguageEntries();
        $languageModel        = new Application_Model_Languages();
        //mini cache - only read once per request
        if (!isset($this->_langInfo[$languageId])) {
            $this->_langInfo[$languageId] = $languageModel->getLanguageById($languageId);
        }
        if ($this->_langInfo[$languageId]['active'] != 1) {
            //language is set to inactive - return and do nothing
            return false;
        }

        if ($this->_keys == null) {
            $this->_keys = $languageEntriesModel->getAllKeys();
        }

        $this->_getFallbackLanguage($languageModel, $languageEntriesModel);
        $fileContent = $this->_addTranslations($languageId, $languageEntriesModel);

        //Add footers and save file content to physical file
        $exportOk = true;
        $res      = array();
        foreach ($fileContent as $templateId => $langFile) {
            $fileFooter = $this->_replaceLanguageMetaPlaceholder(
                $this->_fileTemplates[$templateId]['footer'],
                $languageId
            );
            $langFile['fileContent'] .= $fileFooter . "\n";
            $size     = file_put_contents($langFile['filename'], $langFile['fileContent']);
            $exportOk = ($size !== false) && $exportOk;
            //Suppress warnings, if we can't change the file permissions.
            @chmod($langFile['filename'], 0664);
            $res[$templateId]['size']     = $size;
            $res[$templateId]['filename'] = str_replace(EXPORT_PATH . '/', '', $langFile['filename']);
        }
        $res['exportOk'] = (count($res) > 0) && $exportOk;

        return $res;
    }

    /**
     * Detect fall back language and get translations
     *
     * @param Application_Model_Languages       $languageModel        The language model
     * @param Application_Model_LanguageEntries $languageEntriesModel The language entries model
     *
     * @return void
     */
    public function _getFallbackLanguage($languageModel, $languageEntriesModel)
    {
        //only read once per request
        if (!empty($this->_fallbackLanguageTranslations)) {
            return;
        }
        $fallbackLanguageId = $languageModel->getFallbackLanguageId();
        // if the fallback language isn't set, detect id for "English" and use it instead
        if ($fallbackLanguageId == false) {
            $fallbackLanguageId = $languageModel->getLanguageIdFromLocale('en');
        }
        $this->_fallbackLanguageTranslations = $languageEntriesModel->getTranslations($fallbackLanguageId);
    }

    /**
     * Get translations and add them to file content array
     *
     * @param int                               $languageId           Id of language
     * @param Application_Model_LanguageEntries $languageEntriesModel Instance of languageEntriesModel
     *
     * @return array
     */
    public function _addTranslations($languageId, $languageEntriesModel)
    {
        $fileContent  = array();
        $translations = $languageEntriesModel->getTranslations($languageId);

        if ($this->_config == null) {
            $config        = Msd_Registry::getConfig();
            $this->_config = $config->getParam('project');
        }

        foreach ($this->_keys as $key => $keyData) {
            $templateId = $keyData['templateId'];
            //Do we have the meta data for the exported language file? If not, we will create it now.
            if (!isset($fileContent[$templateId])) {
                $fileContent[$templateId] = $this->_getFileMetaData($languageId, $templateId);
            }

            $val = isset($translations[$key]) ? trim($translations[$key]) : '';
            //If we have no value, fill the var with the value of the fallback language.
            if ($val == '' && (int)$this->_config['translateToFallback'] == 1) {
                if (isset($this->_fallbackLanguageTranslations[$key])) {
                    $val = $this->_fallbackLanguageTranslations[$key];
                }
            }

            //escape value depending on the delimiter
            if ($fileContent[$templateId]['delimiter'] == "'") {
                $val = str_replace("'", "\'", $val);
            } else {
                $val = str_replace('"', '\"', $val);
            }

            // TODO FIXME Nitrado Hacks
            $val = $this->nitradoHacks($templateId, $val);

            //Add content to template array
            $fileContent[$templateId]['fileContent'] .= str_replace(
                array('{KEY}', '{VALUE}', '{YAML_VALUE}'),
                array($keyData['key'], $val, trim(str_replace("\n", " ", $val))),
                $fileContent[$templateId]['langVar']
            );
            $fileContent[$templateId]['fileContent'] .= "\n";
        }

        return $fileContent;
    }

    // TODO FIXME HACKY STUFF BY NITRADO: BEGIN
    private function nitradoHacks($templateId, $value) {
        if ($templateId == 4) { // Android
            $value = str_replace("'", "\'", $value);
            $value = str_replace("\n", "\\n", $value);
            if ($this->containsAndroidSpecialChars($value)) {
                $value = "<![CDATA[" . $value . "]]>";
            }
        } else if ($templateId == 6 || $templateId == 7) { // PO
            $value = str_replace("\r\n", "\n", $value);
            $value = str_replace("\r", "\n", $value);
            $value = str_replace("\n", "\\n\"\n\"", $value);
        }

        return $value;
    }

    private function containsAndroidSpecialChars($string) {
        if (strpos($string, "<") != false) {
            return true;
        }

        if (strpos($string, ">") != false) {
            return true;
        }

        if (strpos($string, "&") != false) {
            return true;
        }
        return false;
    }
    // TODO FIXME HACKY STUFF BY NITRADO: END

    /**
     * Extract meta data for a file and create directory if it doesn't exist
     *
     * @param int $languageId Id of language
     * @param int $templateId Id of template
     *
     * @return array
     */
    public function _getFileMetaData($languageId, $templateId)
    {
        $langFilename = EXPORT_PATH . '/' . $this->_replacePlaceholderInFileName(
            $this->_fileTemplates[$templateId]['filename'],
            $this->_langInfo[$languageId]['locale']
        );
        $langDir      = dirname($langFilename);
        if (!file_exists($langDir)) {
            mkdir($langDir, 0775, true);
        }
        $data = array(
            'dir'        => $langDir,
            'filename'   => $langFilename,
            'langVar'    => $this->_fileTemplates[$templateId]['content'],
            'langName'   => $this->_langInfo[$languageId]['name'],
            'langLocale' => $this->_langInfo[$languageId]['locale']
        );
        //Add file header
        $data['fileContent'] = $this->_replaceLanguageMetaPlaceholder(
            $this->_fileTemplates[$templateId]['header'],
            $languageId
        ) . "\n";

        //extract delimiter
        $pos               = strpos($this->_fileTemplates[$templateId]['content'], '{VALUE}') + 7;
        $data['delimiter'] = substr($this->_fileTemplates[$templateId]['content'], $pos, 1);

        return $data;
    }

    /**
     * Replace meta placeholders in file name
     *
     * @param string $fileName Name of file containing placeholder
     * @param string $locale   Locale of language
     *
     * @return string
     */
    protected function _replacePlaceholderInFileName($fileName, $locale)
    {
        return trim(str_replace('{LOCALE}', $locale, $fileName), '/');
    }

    /**
     * Replace meta placeholder of language.
     *
     * @param string $content    The content in which to search and replace
     * @param int    $languageId The Id of the language
     *
     * @return string
     */
    protected function _replaceLanguageMetaPlaceholder($content, $languageId)
    {
        if ($this->_translatorList == null) {
            $userModel             = new Application_Model_User();
            $this->_translatorList = $userModel->getTranslatorlist();
        }

        $search  = array(
            '{LANG_NAME}',
            '{LOCALE}',
            '{TRANSLATOR_NAMES}'
        );
        $replace = array(
            $this->_langInfo[$languageId]['name'],
            $this->_langInfo[$languageId]['locale'],
            empty($this->_translatorList[$languageId]) ? '' : $this->_translatorList[$languageId],
        );
        $res     = str_replace($search, $replace, $content);

        return $res;
    }

    /**
     * Get a list of files that will be created when doing an export.
     *
     * @return array
     */
    public function getFileTemplateList()
    {
        $files          = array();
        $languagesModel = new Application_Model_Languages();
        $templatesModel = new Application_Model_FileTemplates();
        $languages      = $languagesModel->getAllLanguages('', 0, 0, true);
        $fileTemplates  = $templatesModel->getFileTemplates();

        foreach ($languages as $language) {
            foreach ($fileTemplates as $file) {
                $files[] = $this->_replacePlaceholderInFileName($file['filename'], $language['locale']);
            }
        }

        return $files;
    }

}
