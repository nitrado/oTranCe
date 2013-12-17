<?php
/**
 * PO importer for the website
 *
 * @package         oTranCe
 * @subpackage      Importer
 */

class Module_Import_Po implements Msd_Import_Interface
{
    /**
     * Analyze data and return exracted key=>value pairs
     *
     * Implementation based on the CakePHP implementation
     *
     * @abstract
     * @param string $data String data to analyze
     *
     * @return array Extracted key => value-Array
     */
    public function extract($data)
    {
        $lines = explode("\n", $data);
        $type = 0;
        $translations = array();
        $translationKey = '';
        $plural = 0;
        $header = '';

        foreach ($lines as $line) {
            $line = trim($line);
            
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (preg_match("/msgid[[:space:]]+\"(.+)\"$/i", $line, $regs)) {
                $type = 1;
                $translationKey = stripcslashes($regs[1]);
            } elseif (preg_match("/msgid[[:space:]]+\"\"$/i", $line, $regs)) {
                $type = 2;
                $translationKey = '';
            } elseif (preg_match("/^\"(.*)\"$/i", $line, $regs) && ($type == 1 || $type == 2 || $type == 3)) {
                $type = 3;
                $translationKey .= stripcslashes($regs[1]);
            } elseif (preg_match("/msgstr[[:space:]]+\"(.+)\"$/i", $line, $regs) && ($type == 1 || $type == 3) && $translationKey) {
                $translations[$translationKey] = stripcslashes($regs[1]);
                $type = 4;
            } elseif (preg_match("/msgstr[[:space:]]+\"\"$/i", $line, $regs) && ($type == 1 || $type == 3) && $translationKey) {
                $type = 4;
                $translations[$translationKey] = '';
            } elseif (preg_match("/^\"(.*)\"$/i", $line, $regs) && $type == 4 && $translationKey) {
                $translations[$translationKey] .= stripcslashes($regs[1]);
            } elseif (preg_match("/msgid_plural[[:space:]]+\".*\"$/i", $line, $regs)) {
                $type = 6;
            } elseif (preg_match("/^\"(.*)\"$/i", $line, $regs) && $type == 6 && $translationKey) {
                $type = 6;
            } elseif (preg_match("/msgstr\[(\d+)\][[:space:]]+\"(.+)\"$/i", $line, $regs) && ($type == 6 || $type == 7) && $translationKey) {
                $plural = $regs[1];
                $translations[$translationKey][$plural] = stripcslashes($regs[2]);
                $type = 7;
            } elseif (preg_match("/msgstr\[(\d+)\][[:space:]]+\"\"$/i", $line, $regs) && ($type == 6 || $type == 7) && $translationKey) {
                $plural = $regs[1];
                $translations[$translationKey][$plural] = '';
                $type = 7;
            } elseif (preg_match("/^\"(.*)\"$/i", $line, $regs) && $type == 7 && $translationKey) {
                $translations[$translationKey][$plural] .= stripcslashes($regs[1]);
            } elseif (preg_match("/msgstr[[:space:]]+\"(.+)\"$/i", $line, $regs) && $type == 2 && !$translationKey) {
                $header .= stripcslashes($regs[1]);
                $type = 5;
            } elseif (preg_match("/msgstr[[:space:]]+\"\"$/i", $line, $regs) && !$translationKey) {
                $header = '';
                $type = 5;
            } elseif (preg_match("/^\"(.*)\"$/i", $line, $regs) && $type == 5) {
                $header .= stripcslashes($regs[1]);
            } else {
                unset($translations[$translationKey]);
                $type = 0;
                $translationKey = '';
                $plural = 0;
            }
        }
        
        return $translations;
    }
    
    /**
     * Get rendered info view
     *
     * @param Zend_View_Interface $view View instance
     *
     * @return string
     */
    public function getInfo(Zend_View_Interface $view)
    {
        return $view->render('po.phtml');
    }
}
