<?php

class WordCountController extends OtranceController {

    public function generateStatsAction() {
        $wordStats = $this->readStats();
        $this->writeToFile($wordStats);
    }

    private function readStats() {
        $languageModel = new Application_Model_Languages();
        $templateModel = new Application_Model_FileTemplates();
        $entriesModel = new Application_Model_LanguageEntries();

        $languages = $languageModel->getAllLanguages();
        $templates = $templateModel->getFileTemplates();
        $stats = array();

        foreach ($templates as $template) {
            foreach ($languages as $language) {
                $stats[$template['id']][$language['id']] = $entriesModel->getNumberOfUntranslatedWords(
                    $template['id'], $language['id']
                );
            }
        }

        return $stats;
    }

    private function writeToFile($stats) {
        $file = 'data/wordcount-stats.json';
        if (!file_exists($file) || !is_writable($file)) {
            echo "file not writeable";
            return;
        }

        $fp = fopen($file, 'w');
        fwrite($fp, json_encode($stats));
        fclose($fp);
    }
}