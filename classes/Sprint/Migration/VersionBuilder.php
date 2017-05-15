<?php

namespace Sprint\Migration;

use Sprint\Migration\Exceptions\BuilderException;

class VersionBuilder
{

    /** @var VersionConfig */
    private $versionConfig = null;

    private $fields = array();

    private $templateName = '';
    private $templateVars = array();

    private $name;

    public function __construct(VersionConfig $versionConfig, $name) {
        $this->versionConfig = $versionConfig;
        $this->name = $name;

        $this->setField('prefix', array(
            'title' => GetMessage('SPRINT_MIGRATION_FORM_PREFIX'),
            'value' => $this->getConfigVal('version_prefix'),
            'width' => 250,
        ));

        $this->setField('description', array(
            'title' => GetMessage('SPRINT_MIGRATION_FORM_DESCR'),
            'width' => 350,
            'rows' => 3,
        ));

        $this->initialize();
    }

    public function getName(){
        return $this->name;
    }

    protected function initialize() {
        return true;
    }

    protected function execute() {
        return true;
    }

    protected function setField($code, $param = array()) {
        $param = array_merge(array(
            'title' => '',
            'value' => '',
            'bind' => 0
        ),$param);

        if (empty($param['title'])){
            $param['title'] = $code;
        }

        $this->fields[$code] = $param;
    }

    protected function getFieldValue($code, $default = '') {
        $val = isset($this->fields[$code]) ? $this->fields[$code]['value'] : $default;
        return $val;
    }

    public function getFields() {
        return $this->fields;
    }

    public function bind($postvars = array()) {
        foreach ($this->fields as $code => $field) {
            if (isset($postvars[$code])) {
                $field['value'] = $postvars[$code];
                $field['bind'] = 1;
            }
            $this->fields[$code] = $field;
        }
    }

    protected function renderFile($file, $vars = array()) {
        if (is_array($vars)) {
            extract($vars, EXTR_SKIP);
        }

        ob_start();

        if (is_file($file)) {
            /** @noinspection PhpIncludeInspection */
            include $file;
        }

        $html = ob_get_clean();

        return $html;
    }

    protected function setTemplateName($name){
        $this->templateName = $name;
    }

    protected function setTemplateVar($code, $value) {
        $this->templateVars[$code] = $value;
    }

    public function build() {
        try {
            if (false === $this->execute()) {
                throw new BuilderException('builder returns false');
            }

        } catch (\Exception $e) {
            Out::outError('%s: %s', GetMessage('SPRINT_MIGRATION_CREATED_ERROR'), $e->getMessage());
            return false;
        }

        $description = $this->purifyDescriptionForFile(
            $this->getFieldValue('description')
        );

        $prefix = $this->preparePrefix(
            $this->getFieldValue('prefix')
        );

        $versionName = $prefix . $this->getTimestamp();

        list($extendUse, $extendClass) = explode(' as ', $this->getConfigVal('migration_extend_class'));
        $extendUse = trim($extendUse);
        $extendClass = trim($extendClass);

        if (!empty($extendClass)) {
            $extendUse = 'use ' . $extendUse . ' as ' . $extendClass . ';' . PHP_EOL;
        } else {
            $extendClass = $extendUse;
            $extendUse = '';
        }

        $tplVars = array_merge(array(
            'version' => $versionName,
            'description' => $description,
            'extendUse' => $extendUse,
            'extendClass' => $extendClass,
        ), $this->templateVars);

        if (!empty($this->templateName)) {
            $tplName = Module::getModuleDir() . '/templates/'. $this->templateName . '.php';
        } else {
            $tplName = $this->getConfigVal('migration_template');
        }

        $fileName = $this->getVersionFile($versionName);
        $fileContent = $this->renderFile($tplName, $tplVars);

        file_put_contents($fileName, $fileContent);

        if (!is_file($fileName)) {
            Out::outError('%s, error: can\'t create a file "%s"', $versionName, $fileName);
            return false;
        }

        return $versionName;
    }

    protected function preparePrefix($prefix = '') {
        $prefix = trim($prefix);
        if (empty($prefix)) {
            $prefix = $this->getConfigVal('version_prefix');
            $prefix = trim($prefix);
        }

        $default = 'Version';
        if (empty($prefix)) {
            return $default;
        }

        $prefix = preg_replace("/[^a-z0-9_]/i", '', $prefix);
        if (empty($prefix)) {
            return $default;
        }

        if (preg_match('/^\d/', $prefix)) {
            return $default;
        }

        return $prefix;
    }

    protected function purifyDescriptionForFile($descr = '') {
        $descr = strval($descr);
        $descr = str_replace(array("\n\r", "\r\n", "\n", "\r"), ' ', $descr);
        $descr = strip_tags($descr);
        $descr = addslashes($descr);
        return $descr;
    }

    protected function getVersionFile($versionName) {
        return $this->getConfigVal('migration_dir') . '/' . $versionName . '.php';
    }

    protected function getConfigVal($val, $default = '') {
        return $this->versionConfig->getConfigVal($val, $default);
    }

    protected function getTimestamp() {
        $originTz = date_default_timezone_get();
        date_default_timezone_set('Europe/Moscow');
        $ts = date('YmdHis');
        date_default_timezone_set($originTz);
        return $ts;
    }

    protected function exitIf($cond, $msg) {
        if ($cond) {
            Throw new BuilderException($msg);
        }
    }

    protected function exitIfEmpty($var, $msg) {
        if (empty($var)) {
            Throw new BuilderException($msg);
        }
    }
}