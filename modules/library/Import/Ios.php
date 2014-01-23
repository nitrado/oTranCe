<?php

class Module_Import_Ios implements Msd_Import_Interface {

    public function extract($data) {
        $extractedData = array();

        $lines = explode("\n", $data);
        $lines_count  = count($lines);

        for ($i = 0; $i < $lines_count; $i++) {
            $regEx = '/^"(.*)"\s*=\s*"(.*)";$/';
            preg_match($regEx, $lines[$i], $currentLine);

            $currentKey = trim($currentLine[1]);
            if ($currentKey == '' || !isset($currentLine[1])) {
                continue;
            }
            $extractedData[$currentKey] = str_replace('\"', '"', trim($currentLine[2]));
        }

        return $extractedData;
    }

    public function getInfo(Zend_View_Interface $view)
    {
        return $view->render('ios.phtml');
    }
}
