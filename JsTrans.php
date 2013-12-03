<?php
/**
 * JsTrans
 *
 * Use Yii translations in Javascript
 *
 */

/**
 * Publish translations in JSON and append to the page
 *
 * @param mixed $categories the categories that are exported (accepts array and string)
 * @param mixed $languages the languages that are exported (accepts array and string)
 * @param string $defaultLanguage the default language used in translations
 */
class JsTrans extends CApplicationComponent
{
    public $categories;
    public $languages;
    public $defaultLanguage;

    public function init()
    {
        // Set default language
        if (!$this->defaultLanguage) {
            $this->defaultLanguage = Yii::app()->language;
        }
        // Create arrays from params
        if (!is_array($this->categories)) {
            $this->categories = array($this->categories);
        }
        if (!is_array($this->languages)) {
            $this->languages = array($this->languages);
        }
        // Publish assets folder
        $assetsPath = dirname(__FILE__) . '/assets';
        $baseUrl = Yii::app()->assetManager->publish($assetsPath);
        $basePath = Yii::app()->assetManager->getPublishedPath($assetsPath);
        // Create hash
        $hash = substr(md5(implode($this->categories) . ':' . implode($this->languages)), 0, 10);
        $dictionaryFile = "dictionary-{$hash}.js";
        // Generate dictionary file if not exists or YII DEBUG is set
        if (!file_exists($basePath . '/' . $dictionaryFile) || YII_DEBUG) {
            // Declare config (passed to JS)
            $config = array('language' => $this->defaultLanguage);
            // Loop message files and store translations in array
            $dictionary = array();
            foreach ($this->languages as $lang) {
                if (!isset($dictionary[$lang])) {
                    $dictionary[$lang] = array();
                }
                foreach ($this->categories as $cat) {
                    if (Yii::app()->messages instanceof CDbMessageSource) {
                        // Read dictionary from the database.
                        $tbl_source = Yii::app()->messages->sourceMessageTable;
                        $tbl_message = Yii::app()->messages->translatedMessageTable;
                        $command = db()->createCommand()->select('s.message, m.translation')
                            ->from("{$tbl_source} s")->join("{$tbl_message} m", 'm.source_id = s.id')
                            ->where('s.category = :cat AND m.language = :lang', array(':cat' => $cat, ':lang' => $lang))
                            ->query();
                        while ($row = $command->read()) {
                            $dictionary[$lang][$cat][$row['message']] = $row['translation'];
                        }
                    } elseif (Yii::app()->messages instanceof CPhpMessageSource) {
                        // Read dictionary from PHP-files.
                        $messagesFolder = rtrim(Yii::app()->messages->basePath, '\/');
                        $messageFile = $messagesFolder . '/' . $lang . '/' . $cat . '.php';
                        if (file_exists($messageFile)) $dictionary[$lang][$cat] = array_filter(require($messageFile));
                    }
                }
            }
            // Save config/dictionary
            $data = 'Yii.translate.config=' . CJSON::encode($config) . ';';
            $data .= 'Yii.translate.dictionary=' . CJSON::encode($dictionary);
            // Save to dictionary file
            if (!file_put_contents($basePath . '/' . $dictionaryFile, $data)) {
                Yii::log('Error: Could not write dictionary file, check file permissions', 'trace', 'jstrans');
            }
        }
        // Publish library and dictionary
        if (file_exists($basePath . '/' . $dictionaryFile)) {
            Yii::app()->getClientScript()
                ->registerScriptFile($baseUrl . '/JsTrans.min.js', CClientScript::POS_HEAD)
                ->registerScriptFile($baseUrl . '/' . $dictionaryFile, CClientScript::POS_HEAD);
        } else {
            Yii::log('Error: Could not publish dictionary file, check file permissions', 'trace', 'jstrans');
        }
    }
}
