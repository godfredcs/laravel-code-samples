<?php

namespace App\Support;

use Exception;
use DOMDocument;
use App\Models\User;
use App\Models\XmlForm;
use App\Models\Form;
use App\Constant\Common;
use App\Models\Field;
use App\Models\SystemList;

class XmlParserService
{
    /**
     * The xml version.
     *
     * @var string
     */
    private $version;

    /**
     * The xml encoding.
     *
     * @var string
     */
    private $encoding;

    /**
     * The DOMDocument instance.
     *
     * @var \DOMDocument
     */
    private $dom_document;

    /**
     * The xml form built.
     *
     * @var \DOMDocument
     */
    private $xml_form;

    /**
     * The db form built.
     *
     * @var array
     */
    private $db_form = [];

    /**
     * Make a new instance of the Xml Parser Service.
     *
     * @param string $version
     * @param string $encoding
     * @return void
     */
    public function __construct($version = '1.0', $encoding = 'UTF-8')
    {
        $this->version = $version;
        $this->encoding = $encoding;
        $this->dom_document = new DOMDocument($this->version, $this->encoding);
        $this->dom_document->xmlStandalone = true;
    }

    /**
     * Generate xml from the provided form.
     *
     * @param \App\Modules\FormBuilder\Models\Form $form
     * @return string
     */
    public function getFormXmlData(Form $form)
    {
        $this->createFormElement($form)
            ->createFieldsElement($form->fields)
            ->createKeywordsElement($form->keywords)
            ->createApprovalsElement($form->approvers)
            ->createTasksElement($form->triggered_task_id, $form->trigger_has_date)
            ->createCommunicationsElement($form->email_notifications);

        return $this->dom_document->saveXML();
    }

    /**
     * Generate xml from the provided form.
     *
     * @param \App\Modules\Generic\Models\XmlForm|\App\Modules\Generic\Models\XmlForm $instance
     * @return array
     */
    public function getFormDBData($instance)
    {
        $is_xmlform = $instance instanceof XmlForm;
        $entity = $is_xmlform ? 'xmlform' : 'task log';
        $id = $is_xmlform ? $instance->xmlform_id : $instance->task_log_id;
        $xmlform_dtd = $is_xmlform ? $instance->xmlform_dtd : $instance->task_log_xmldoc;

        try {
            $this->dom_document->loadXML($xmlform_dtd);
        } catch (Exception $e) {
            logger('An error occurred while parsing '. $entity .' with id ' . $id . ' Message: ' . $e->getMessage());

            return [];
        }

        $this->parseFormElement($this->dom_document->getElementsByTagName('form'))
            ->parseFieldsElement($this->dom_document->getElementsByTagName('fields'))
            ->parseKeywordsElement($this->dom_document->getElementsByTagName('keywords'))
            ->parseApprovalsElement($this->dom_document->getElementsByTagName('approvals'))
            ->parseTasksElement($this->dom_document->getElementsByTagName('tasks'))
            ->parseCommunicationsElement($this->dom_document->getElementsByTagName('communications'));

        return $this->db_form;
    }

    /**
     * Create the form element. Add its attributes and also
     * its basic fields such as version, description and instructions.
     *
     * @param \App\Modules\FormBuilder\Models\Form $form
     * @return $this
     */
    private function createFormElement(Form $form)
    {
        $description = $form->description && $form->description !== 'null' ?
            htmlspecialchars($form->description) : 'Please input the form description here';

        $instructions = $form->instructions && $form->instructions !== 'null' ?
            htmlspecialchars($form->instructions) : 'Please input the form instructions here';

        $xml_form = $this->dom_document->createElement('form');
        $form_version = $this->dom_document->createElement('fversion', '1');
        $form_description = $this->dom_document->createElement('fdescription', $description);
        $form_instructions = $this->dom_document->createElement('finstructions', $instructions);

        $xml_form->setAttribute('id', $form->id);
        $xml_form->setAttribute('isEditing', 'true');
        $xml_form->appendChild($form_version);
        $xml_form->appendChild($form_description);
        $xml_form->appendChild($form_instructions);

        $this->xml_form = $this->dom_document->appendChild($xml_form);

        return $this;
    }

    /**
     * Create the form keywords xml element.
     *
     * @param array $keywords
     * @return $this
     */
    private function createKeywordsElement($keywords)
    {
        if (count($keywords)) {
            $form_keywords = $this->dom_document->createElement('keywords');

            foreach ($keywords as $keyword) {
                $keyword_node = $this->dom_document->createElement('keyword', htmlspecialchars($keyword));
                $form_keywords->appendChild($keyword_node);
            }

            $this->xml_form->appendChild($form_keywords);
        }

        return $this;
    }

    /**
     * Create the form communications xml element.
     *
     * @param array $emails
     * @return $this
     */
    private function createCommunicationsElement($emails)
    {
        if (count($emails)) {
            $form_communications = $this->dom_document->createElement('communications');

            foreach ($emails as $email) {
                $comms_node = $this->dom_document->createElement('notify', htmlspecialchars($email));
                $comms_node->setAttribute('type', 'email');

                $form_communications->appendChild($comms_node);
            }

            $this->xml_form->appendChild($form_communications);
        }

        return $this;
    }

    /**
     * Create the form approvals xml element.
     *
     * @param array $approvers
     * @return $this
     */
    private function createApprovalsElement($approvers)
    {
        if (count($approvers)) {
            $form_approvals = $this->dom_document->createElement('approvals');

            foreach ($approvers as $approver) {
                $approval_node = $this->dom_document->createElement('approval');
                $approval_node->nodeValue = '';
                $approval_node->setAttribute('type', 'todo');
                $approval_node->setAttribute('approver', $approver->user->user_username);

                $form_approvals->appendChild($approval_node);
            }

            $this->xml_form->appendChild($form_approvals);
        }

        return $this;
    }

    /**
     * Create the form tasks xml element.
     *
     * @param int $triggered_task_id
     * @param int $trigger_has_date
     * @return $this
     */
    private function createTasksElement($triggered_task_id, $trigger_has_date = 0)
    {
        if ($triggered_task_id) {
            $form_tasks = $this->dom_document->createElement('tasks');

            $task_node = $this->dom_document->createElement('task');
            $task_node->setAttribute('type', 'new');
            $task_node->setAttribute('xmlform_id', $triggered_task_id);

            if ($trigger_has_date) {
                $task_node->setAttribute('trigger_has_date', $trigger_has_date);
            }

            $form_tasks->appendChild($task_node);
            $this->xml_form->appendChild($form_tasks);
        }

        return $this;
    }

    /**
     * Create the form fields xml element.
     *
     * @param array $fields
     * @return $this
     */
    private function createFieldsElement($fields)
    {
        if (!count($fields)) {
            return $this;
        }

        $form_fields = $this->dom_document->createElement('fields');

        foreach ($fields as $field) {
            $field_type = $field->field->system_name;
            $field_old_type = $field->field->getOldFieldType();
            $field_v = $field->getValue();
            $field_value = $field_type === 'checkbox_list' ? explode(Common::FORM_VALUE_DELIMITER, $field_v) : [ $field_v ];

            // Main field element
            $field_node = $this->dom_document->createElement('field');
            $field_node->nodeValue = '';

            $field_node->setAttribute('type', $field_old_type);
            $field_node->setAttribute('name', $field->name);
            $field_node->setAttribute('id', $field->id .'_'. $field_old_type);
            $field_node->setAttribute('description', $field->description);
            $field_node->setAttribute('instructions', $field->instructions);
            $field_node->setAttribute('keywords', $field->keywords);

            // Field attributes
            foreach ($field->attributes as $attribute) {
                if (!$attribute->name) {
                    continue;
                }

                // Value manipulations
                if (strtolower($attribute->name) === 'value') {
                    if (in_array($field_type, Field::getInfixValueFields())) {
                        if ($field_type !== 'system_list') {
                            $field_node->nodeValue = htmlspecialchars($attribute->value);
                        }
                    }

                    if (in_array($field_type, Field::getImageFields())) {
                        $field_node->setAttribute('src', $attribute->value);
                    }

                    if (in_array($field_type, Field::getValueFields())) {
                        $attr_value = $attribute->value;

                        if ($field_type === 'system_list') {
                            $system_list = SystemList::find($attr_value);

                            if ($system_list) {
                                $attr_value = $system_list->getOldListValue();
                            }
                        }

                        $field_node->setAttribute('value', $attr_value);
                        $field_node->setAttribute('data-value', $attr_value);
                    }

                    if (strtolower($field_type) === 'embed_files') {
                        $field_node->setAttribute('data-files', json_encode($attribute->value));
                        $field_node->setAttribute('data-value', json_encode($attribute->value));
                    }

                    continue;
                }

                // Triggered task handling
                if (strtolower($attribute->name) === 'triggered_task_id') {
                    $triggered_task = XmlForm::getFormattedXmlForms()
                        ->where('xmlform_id', $attribute->value)
                        ->first();

                    if ($triggered_task) {
                        $field_node->setAttribute('data-trigger-task', $triggered_task->id);
                        $field_node->setAttribute('data-trigger-task-name', $triggered_task->name);
                    }

                    continue;
                }

                if (strtolower($attribute->name) === 'trigger_has_date') {
                    if (intval($attribute->value)) {
                        $field_node->setAttribute('data-trigger-has-date', $attribute->value);
                    }

                    continue;
                }

                // Min and max manipulations
                if (in_array(strtolower($attribute->name), ['min', 'max'])) {
                    $attr_name = in_array($field_type, Field::getMinOrMaxHasLengthFields())
                        ? $attribute->name . 'length' : $attribute->name;

                    $field_node->setAttribute($attr_name, $attribute->value);

                    continue;
                }

                // Mandatory (Required) functionality
                if (strtolower($attribute->name) === 'required' && (int) $attribute->value === 1) {
                    $field_node->setAttribute('required', 'required');

                    continue;
                }

                $attr_prefix = preg_match('/\d/', substr($attribute->name, 0, 1)) ? '_' : null;
                $field_node->setAttribute($attr_prefix . $attribute->name, $attribute->value);
            }

            // Field Options
            if ($field->options && count($field->options)) {
                $field_options = $this->dom_document->createElement('options');

                foreach ($field->options as $option) {
                    $option_prefix = preg_match('/\d/', substr($attribute->name, 0, 1)) ? '_' : null;
                    $option_value = htmlspecialchars($option_prefix . $option->name);
                    $option_node = $this->dom_document->createElement('option', $option_value);

                    if ($option->triggered_task_id) {
                        $triggered_task = XmlForm::getFormattedXmlForms()
                            ->where('xmlform_id', $option->triggered_task_id)
                            ->first();

                        if ($triggered_task) {
                            $option_node->setAttribute('data-trigger-task', $triggered_task->id);
                            $option_node->setAttribute('data-trigger-task-name', $triggered_task->name);

                            if ($option->trigger_has_date) {
                                $option_node->setAttribute('data-trigger-has-date', $option->trigger_has_date);
                            }
                        }
                    }

                    if (in_array($option->name, $field_value)) {
                        $option_node->setAttribute('selected', 'true');
                    }

                    $field_options->appendChild($option_node);
                }

                $field_node->appendChild($field_options);
            }

            $form_fields->appendChild($field_node);
        }

        $this->xml_form->appendChild($form_fields);

        return $this;
    }

    /**
     * Parse the form element from xml to DB.
     *
     * @param \DomElement $form
     * @return $this
     */
    private function parseFormElement($form)
    {
        if (!$form->length) {
            return $this;
        }

        $description_node = $form->item(0)
            ->getElementsByTagName('fdescription')
            ->item(0);

        $instructions_node = $form->item(0)
            ->getElementsByTagName('finstructions')
            ->item(0);

        $this->db_form['description'] = $description_node ? $description_node->nodeValue : null;
        $this->db_form['instructions'] = $instructions_node ? $instructions_node->nodeValue : null;

        return $this;
    }

    /**
     * Parse the keywords element from xml to DB.
     *
     * @param \DomElement $keywords
     * @return $this
     */
    private function parseKeywordsElement($keywords)
    {
        if (!$keywords->length) {
            return $this;
        }

        $this->db_form['keywords'] = [];
        $keyword_nodes = $keywords->item(0)->getElementsByTagName('keyword');

        if ($keyword_nodes->length) {
            foreach ($keyword_nodes as $keyword_node) {
                $this->db_form['keywords'][] = $keyword_node->nodeValue;
            }
        }

        return $this;
    }

    /**
     * Parse the approvals element from xml to DB.
     *
     * @param \DomElement $approvals
     * @return $this
     */
    private function parseApprovalsElement($approvals)
    {
        if (!$approvals->length) {
            return $this;
        }

        $this->db_form['approvers'] = [];
        $approval_nodes = $approvals->item(0)->getElementsByTagName('approval');

        if ($approval_nodes->length) {
            foreach ($approval_nodes as $approval_node) {
                $approver = User::where('user_username', $approval_node->getAttribute('approver'))
                    ->first();

                if ($approver) {
                    $this->db_form['approvers'][] = $approver->user_id;
                }
            }
        }

        return $this;
    }

    /**
     * Parse the tasks element from xml to DB.
     *
     * @param \DomElement $tasks
     * @return $this
     */
    private function parseTasksElement($tasks)
    {
        if (!$tasks->length) {
            return $this;
        }

        $node = $tasks->item(0)
            ->getElementsByTagName('task')
            ->item(0);

        if ($node) {
            $this->db_form['triggered_task_id'] = $node->hasAttribute('xmlform_id') ? $node->getAttribute('xmlform_id') : null;
            $this->db_form['trigger_has_date'] = $node->hasAttribute('trigger_has_date') && intval($node->getAttribute('trigger_has_date')) === 1 ? 1 : 0;
        }

        return $this;
    }

    /**
     * Parse the communications element from xml to DB.
     *
     * @param \DomElement $communications
     * @return $this
     */
    private function parseCommunicationsElement($communications)
    {
        if (!$communications->length) {
            return $this;
        }

        $this->db_form['email_notifications'] = [];
        $comm_nodes = $communications->item(0)->getElementsByTagName('notify');

        if ($comm_nodes->length) {
            foreach ($comm_nodes as $comm_node) {
                $this->db_form['email_notifications'][] = $comm_node->nodeValue;
            }
        }

        return $this;
    }

    /**
     * Parse the fields element from xml to DB.
     *
     * @param \DomElement $fields
     * @return $this
     */
    private function parseFieldsElement($fields)
    {
        if (!$fields->length) {
            return $this;
        }

        $this->db_form['fields'] = [];
        $field_nodes = $fields->item(0)->getElementsByTagName('field');

        if (!$field_nodes->length) {
            return $this;
        }

        foreach ($field_nodes as $field_node) {
            $field = [
                'attributes' => [
                    'value' => $field_node->getAttribute('type') == 'checkboxlist' ? [] : null
                ]
            ];

            // Field Attributes
            if ($field_node->hasAttributes()) {
                foreach ($field_node->attributes as $field_attr) {
                    if (!$field_attr->name) {
                        continue;
                    }

                    // Form Function main attributes
                    if (
                        strtolower($field_node->getAttribute('type')) === 'function' &&
                        in_array(strtolower($field_attr->name), ['data-value', 'function_name', 'data-label'])
                    ) {
                        if (strtolower($field_attr->name) === 'data-value') {
                            $field['attributes']['value'] = htmlspecialchars_decode($field_attr->value);
                        } else {
                            $field['attributes'][$field_attr->name] = htmlspecialchars_decode($field_attr->value);

                            if (strtolower($field_attr->name) === 'function_name' && !$field['attributes']['value']) {
                                $field['attributes']['value'] = htmlspecialchars_decode($field_attr->value);
                            }
                        }

                        continue;
                    }

                    // System List value
                    if (strtolower($field_node->getAttribute('type')) === 'systemlist' && in_array(strtolower($field_attr->name), ['value', 'data-value'])) {
                        $system_list = SystemList::findByOldValue($field_attr->value);

                        if ($system_list) {
                            $field['attributes']['data-value'] = $system_list->id;
                        }

                        continue;
                    }

                    // Related Forms
                    if (strtolower($field_node->getAttribute('type')) === 'tasklogreference' && in_array(strtolower($field_attr->name), ['value', 'data-value', 'data-object'])) {
                        if (strtolower($field_attr->name) === 'data-object') {
                            $val = htmlspecialchars_decode($field_attr->value);
                            $field['attributes']['value'] = json_decode($val) ? $val : null;
                        }

                        continue;
                    }

                    //Embedded Files
                    if (strtolower($field_node->getAttribute('type')) === 'file' && in_array(strtolower($field_attr->name), ['value', 'data-value'])) {
                        $val = htmlspecialchars_decode($field_attr->value);
                        $field['attributes']['value'] = json_decode($val) ? $val : null;

                        continue;
                    }

                    // Attributes to be ignored
                    if (in_array($field_attr->name, Field::getAttributesToBeIgnored())) {
                        continue;
                    }

                    // On field attributes
                    if (in_array($field_attr->name, Field::getOnFieldAttributes())) {
                        $field[$field_attr->name] = htmlspecialchars_decode($field_attr->value);

                        continue;
                    }

                    // Field type
                    if (strtolower($field_attr->name) === 'type') {
                        $field_type = Field::findByOldType($field_attr->value);

                        if (!$field_type) {
                            $field_type = Field::getDefaultField();
                        }

                        $field['field_id'] = $field_type->id;

                        continue;
                    }

                    // Field Value
                    if (in_array($field_attr->name, Field::getValueAttributes())) {
                        $field['attributes']['value'] = htmlspecialchars_decode($field_attr->value);

                        continue;
                    }

                    // Trigger task
                    if ($field_attr->name == 'data-trigger-task') {
                        $field['attributes']['triggered_task_id'] = $field_attr->value
                            ? $field_attr->value : null;

                        continue;
                    }

                    if ($field_attr->name == 'data-trigger-has-date') {
                        $field['attributes']['trigger_has_date'] = intval($field_attr->value)
                            ?: 0;

                        continue;
                    }

                    // Minlength and Maxlength
                    if (in_array($field_attr->name, ['minlength', 'maxlength'])) {
                        $field['attributes'][substr($field_attr->name, 0, 3)] = $field_attr->value;

                        continue;
                    }

                    // Mandatory (Required)
                    if ($field_attr->name === 'required') {
                        if (in_array($field_attr->value, ['true', 'required'])) {
                            $field['attributes']['required'] = 'true';
                        }

                        continue;
                    }

                    $field['attributes'][$field_attr->name] = htmlspecialchars_decode($field_attr->value);
                }

                if (
                    !is_array($field['attributes']['value']) &&
                    !$field['attributes']['value'] &&
                    in_array($field_type->system_name, Field::getInfixValueFields())
                    || $field_type->system_name == 'yes_no'
                ) {
                    $field['attributes']['value'] = $field_node->hasChildNodes() && !$field_node->lastChild->hasChildNodes()
                        ? $field_node->lastChild->nodeValue : null;
                }
            }

            // Handle field options
            $field_options = $field_node->getElementsByTagName('option');

            if ($field_options->length) {
                $field['options'] = [];

                foreach ($field_options as $field_option) {
                    $option = [
                        'name' => $field_option->nodeValue
                    ];

                    if ($field_option->hasAttribute('selected') &&
                        in_array($field_option->getAttribute('selected'), ['true', 'yes'])
                    ) {
                        if (is_array($field['attributes']['value'])) {
                            $field['attributes']['value'][] = $option['name'];
                        } else {
                            $field['attributes']['value'] = $field['attributes']['value'] ?: $option['name'];
                        }
                    }

                    if ($field_option->hasAttribute('data-trigger-task')) {
                        $triggered_task = $field_option->getAttribute('data-trigger-task');
                        $option['triggered_task_id'] = $triggered_task ? $triggered_task : null;
                    }

                    if ($field_option->hasAttribute('data-trigger-has-date')) {
                        $trigger_has_date = $field_option->getAttribute('data-trigger-has-date');
                        $option['trigger_has_date'] = intval($trigger_has_date) ?: 0;
                    }

                    $field['options'][] = $option;
                }
            }

            $this->db_form['fields'][] = $field;
        }

        return $this;
    }
}
