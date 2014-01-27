<?php
/**
 * This controller handles updates from the file system
 */
class UpdateController extends OtranceController {
    public function updateAction() {
        $params = $this->_request->getParams();
        $file = 'data/export/' . $params[0];

        if (!file_exists($file) || !is_readable($file)) {
            echo "File does not exists or cannot be read\n"; // TODO
            return;
        }

        $language = $params[1];
        $fileTemplate = $params[2];

        $data = file_get_contents($file);
        $importer = $this->getImporter((int)$fileTemplate);
        $extracted = $importer->extract($data);

        $entriesModel = new Application_Model_LanguageEntries();
        $existingKeys = $entriesModel->getKeysByTemplate($fileTemplate);

        if ($language == 2) {
            foreach ($extracted as $key => $value) {
                if (!array_key_exists($key, $existingKeys)) {
                    $keyId = $this->createKey($entriesModel, $key, $fileTemplate);
                    if ($keyId != false) {
                        $this->saveTranslation($entriesModel, $keyId, $language, $value);
                    }
                } else {
                    unset($existingKeys[$key]);
                }
            }

            foreach ($existingKeys as $current) {
                $entriesModel->deleteEntryByKeyId($current['id']);
            }
        } else {
            $currentLanguageTranslation = $entriesModel->getTranslations($language);
            $englishValues = $entriesModel->getTranslations(2);

            foreach ($extracted as $key => $value) {
                if (!array_key_exists($key, $existingKeys)) {
                    // Ignore because key does not exist
                    continue;
                }

                if (!$existingKeys['justCreated']) {
                    // Only import newly created keys
                    continue;
                }

                $keyId = $existingKeys[$key]['id'];

                if (array_key_exists($keyId, $currentLanguageTranslation)) {
                    // There is already a translation
                    continue;
                }

                if ($value == null || $value == "") {
                    // Empty
                    continue;
                }

                if ($value == $englishValues[$keyId]) {
                    // Value is just the english fallback value
                    continue;
                }

                $this->saveTranslation($entriesModel, $keyId, $language, $value);
            }
        }
    }

    public function cleanupAction() {
        $params = $this->_request->getParams();
        $fileTemplate = $params[0];
        $entriesModel = new Application_Model_LanguageEntries();
        $entriesModel->resetJustCreated($fileTemplate);
    }

    private function getImporter($fileTemplate) {
        $importers = array(
            4 => 'titanium',
            7 => 'po',
            6 => 'po',
            3 => 'yml',
            8 => 'ios'
        );

        if (!array_key_exists($fileTemplate, $importers)) {
            return null;
        }

        return Msd_Import::factory($importers[$fileTemplate]);
    }

    private function createKey($entriesModel, $key, $fileTemplate) {
        if (!$entriesModel->validateLanguageKey($key, $fileTemplate)) {
            return false;
        }
        $entriesModel->saveNewKey($key, $fileTemplate);

        $entry = $entriesModel->getEntryByKey($key, $fileTemplate);
        return $entry['id'];
    }

    private function saveTranslation($entriesModel, $keyId, $languageId, $translation) {
        $entriesModel->saveEntries(
            $keyId,
            array($languageId => $translation)
        );
    }
}