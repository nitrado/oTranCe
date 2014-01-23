<?php

class Module_Import_Yml implements Msd_Import_Interface {
    public function extract($data) {
        $translations = array();
        foreach (explode("\n", $data) as $line) {
            if (strlen(trim($line)) === 0) continue;
            list($key, $value) = explode(':', $line);
            $value = substr(trim($value), 1, -1);
            $value = str_replace('\"', '"', $value);
            $translations[trim($key)] = $value;
        }
        return $translations;
    }

    public function getInfo(Zend_View_Interface $view) {
        return $view->render('yml.phtml');
    }
}