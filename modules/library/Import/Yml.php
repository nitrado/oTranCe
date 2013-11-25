<?php

class Yml_Import_Symfony implements Msd_Import_Interface {
    public function extract($data) {
        $translations = array();
        foreach (explode("\n", $data) as $line) {
            if (strlen(trim($line)) === 0) continue;
            list($key, $value) = explode(':', $line);
            $translations[trim($key)] = trim($value);
        }
        return $translations;
    }

    public function getInfo(Zend_View_Interface $view) {
        return $view->render('yml.phtml');
    }
}