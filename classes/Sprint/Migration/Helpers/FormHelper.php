<?php

namespace Sprint\Migration\Helpers;

use CDBResult;
use Sprint\Migration\Helper;

class FormHelper extends Helper
{
    private $formId;

    private $db;

    public function __construct()
    {
        \CModule::IncludeModule('form');
        global $DB;
        $this->db = $DB;
    }

    /**
     * @param $formId
     * @return array|null
     */
    public function initForm($formId)
    {
        $formId = (int)$formId;

        $form = \CForm::GetByID($formId)->Fetch();
        if (!$form) {
            return null;
        }
        $this->formId = $formId;
        $dbSites = $this->db->Query("SELECT SITE_ID FROM b_form_2_site WHERE FORM_ID = {$formId}");
        $form['arSITE'] = [];
        while ($ar = $dbSites->Fetch()) {
            $form['arSITE'][] = $ar["SITE_ID"];
        }
        return ['FORM' => $form];
    }

    /**
     * @param $form
     * @param $SID
     * @return bool|int
     * @throws \Exception
     */
    public function saveForm($form, $SID)
    {
        $formArray = $form['FORM'];
        $oldSid = $formArray['SID'];
        $formArray['SID'] = $SID;
        $formArray['VARNAME'] = $SID;
        $formArray['MAIL_EVENT_TYPE'] = str_replace($oldSid, $SID, $formArray['MAIL_EVENT_TYPE']);
        $formId = \CForm::Set($formArray);
        if (!$formId) {
            global $strError;
            throw new \Exception('Error while form setting - ' . $strError);
        }
        try {
            $this->saveStatuses($formId, $form['STATUSES']);
            $this->saveFieldsWithValidators($formId, $form['FIELDS'], $form['VALIDATORS']);
        } catch (\Exception $e) {
            //  Суровая транзакция
            \CForm::Delete($formId);
            throw $e;
        }
        //  TODO - доработать, проверить работоспособность
        \CForm::SetMailTemplate($formId, 'Y');

        return $formId;
    }

    /**
     * @param $formId
     * @param $statuses
     * @throws \Exception
     */
    public function saveStatuses($formId, $statuses)
    {
        foreach ($statuses as $status) {
            $status['FORM_ID'] = $formId;
            unset($status['TIMESTAMP_X']);
            unset($status['RESULTS']);
            unset($status['ID']);
            $statusID = \CFormStatus::Set($status);
            if (!$statusID) {
                global $strError;
                throw new \Exception('Error while status setting - ' . $strError);
            }
        }
    }

    /**
     * @param $formId
     * @param $fields
     * @param $validators
     * @throws \Exception
     */
    public function saveFieldsWithValidators($formId, $fields, $validators)
    {
        $arValidators = [];
        foreach ($validators as $validator) {
            $arValidators[$validator['FIELD_ID']][] = $validator;
        }
        foreach ($fields as $field) {
            $answers = $field['_ANSWERS'];
            $validators = $arValidators[$field['ID']];
            $field['FORM_ID'] = $formId;
            unset($field['_ANSWERS']);
            unset($field['VARNAME']);
            unset($field['TIMESTAMP_X']);
            unset($field['ID']);
            $fieldId = \CFormField::Set($field);
            if (!$fieldId) {
                global $strError;
                throw new \Exception('Error while field setting - ' . $strError);
            }
            foreach ($answers as $answer) {
                $answer['QUESTION_ID'] = $fieldId;
                unset($answer['ID']);
                unset($answer['FIELD_ID']);
                unset($answer['TIMESTAMP_X']);
                $answerID = \CFormAnswer::Set($answer);
                if (!$answerID) {
                    global $strError;
                    throw new \Exception('Error while answers setting - ' . $strError);
                }
            }
            foreach ($validators as $validator) {
                unset($validator['FORM_ID']);
                unset($validator['FIELD_ID']);
                unset($validator['ID']);
                $validator['SID'] = $validator['NAME'];
                $validatorId = \CFormValidator::Set($formId, $fieldId, $validator);
                if (!$validatorId) {
                    //  Имхо, тут падать не стоит
                    global $strError;
                    echo $strError;
                }
            }
        }
    }

    /**
     * @return array
     */
    public function getFormStatuses()
    {
        $dbStatuses = \CFormStatus::GetList($this->formId, $by = 's_sort', $order = 'asc', [], $f);
        return $this->fetchDbRes($dbStatuses);
    }

    /**
     * @return array
     */
    public function getFormFields()
    {
        $dbFields = \CFormField::GetList($this->formId, 'ALL', $by = 's_sort', $order = 'asc', [], $f);
        $fields = $this->fetchDbRes($dbFields);
        foreach ($fields as $k => $field) {
            $fields[$k]['_ANSWERS'] = $this->getFieldAnswers($field['ID']);
        }
        return $fields;
    }

    /**
     * @param $field_id
     * @return array
     */
    private function getFieldAnswers($field_id)
    {
        $dbAnswers = \CFormAnswer::GetList($field_id, $by = 's_sort', $order = 'asc', [], $f);
        return $this->fetchDbRes($dbAnswers);
    }

    /**
     * @return array
     */
    public function getFormValidators()
    {
        $dbValidators = \CFormValidator::GetList($this->formId, [], $by = 's_sort', $order = 'asc');
        return $this->fetchDbRes($dbValidators);
    }


    /**
     * @param CDBResult $dbRes
     * @return array
     */
    private function fetchDbRes(CDBResult $dbRes)
    {
        $res = [];
        while ($value = $dbRes->Fetch()) {
            $res[] = $value;
        }
        return $res;
    }

    /**
     * @param $sid
     * @throws \Exception
     */
    public function deleteFormBySID($sid)
    {
        $form = \CForm::GetBySID($sid)->Fetch();
        $id = $form['ID'];
        $res = \CForm::Delete($id);
        if (!$res) {
            throw new \Exception('Cannot delete form "' . $sid . '"');
        }
    }

}