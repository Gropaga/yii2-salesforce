<?php
/**
 * Created by JetBrains PhpStorm.
 * User: iMac_Max
 * Date: 4/1/16
 * Time: 20:38
 * To change this template use File | Settings | File Templates.
 */

namespace salesforce;

use Yii;
use common\models\Centre;
use yii\base\Component;
use \SforceEnterpriseClient;
use common\models\SalesforceAccount;
use Exception;


class Salesforce extends Component
{
    private $user;
    private $conn;
    private $salesforceAccountModel;

    private $sf_contact_id;
    private $sf_parent_id;

    private $mainContact = [];
    private $parentSchool = [];
    private $associateSchools = [];
    private $seasonalSchools = [];

    private $sf_username;
    private $sf_password;
    private $sf_token;
    private $sf_school_record_type_id;

    private $contactFields = [
        'Id' => 'sf_id',
        'Salutation' => 'title',
        'FirstName' => 'forename',
        'LastName' => 'surname',
        'Title' => 'position',
        'Phone' => 'tel',
        'Email' => 'email',
        'Skype__c' => 'skype',
    ];

    private $schoolFields = [
        'Id' => 'sf_id',
        'Name' => 'name',
        'BillingStreet' => 'address1',
        'BillingCity' => 'city',
        'BillingPostalCode' => 'postcode',
        'BillingCountry' => 'country',
        'Website' => 'website',
        'RecordTypeId' => 'RecordTypeId',
        'School_Status__c' => 'School_Status__c',
        'Membership__c' => 'Membership__c',
        'Number_of_Classrooms__c' => 'total_classroom',
        'Main_Contact__c' => 'sf_contact_id',
    ];

    private $fullField = 'Full';

    private $associateFields = [
        'Associate 1' => 'Associate 1',
        'Associate 2' => 'Associate 2',
        'Associate 3' => 'Associate 3',
        'Associate 4+' => 'Associate 4+',
    ];

    private $seasonalFields = [
        'Medium-Term Seasonal' => 'Medium-Term Seasonal',
        'Short-Term Seasonal 1' => 'Short-Term Seasonal 1',
        'Short-Term Seasonal 2' => 'Short-Term Seasonal 2',
        'Short-Term Seasonal 3+' => 'Short-Term Seasonal 3+',
    ];

    public function getMembershipType($centre) {
        $membershipValue = $centre['Membership__c'];
        if ($membershipValue == $this->fullField) {
            return Centre::TYPE_F_A;
        }

        foreach ($this->associateFields as $index=>$field) {
            if ($membershipValue == $index) return Centre::TYPE_F_A;
        }

        foreach ($this->seasonalFields as $index=>$field) {
            if ($membershipValue == $index) return Centre::TYPE_SEASONAL;
        }
        return false;
    }

    public function getSchoolFields() {
        return $this->schoolFields;
    }

    public function getContactFields() {
        return $this->contactFields;
    }

    public function getSeasonalField() {
        return $this->seasonalFields;
    }

    public function getAssociateFields() {
        return $this->associateFields;
    }

    public function getSfContactId() {
        return $this->sf_contact_id;
    }

    public function getSfParentId() {
        return $this->sf_parent_id;
    }

    public function getMainContact() {
        return $this->mainContact;
    }

    public function getMainContactCount() {
        return sizeof($this->mainContact);
    }

    public function getParentSchool() {
        return $this->parentSchool;
    }

    public function getParentSchoolCount() {
        return sizeof($this->parentSchool);
    }

    public function getAssociateSchools() {
        return $this->associateSchools;
    }

    public function getAssociateSchoolsCount() {
        return sizeof($this->associateSchools);
    }

    public function getSeasonalSchools() {
        return $this->seasonalSchools;
    }

    public function getSeasonSchoolsCount() {
        return sizeof($this->seasonalSchools);
    }

    private function setSalesforceAccount() {
        return ($this->salesforceAccountModel = SalesforceAccount::findOne(['user_id' => Yii::$app->user->id])) ? true : false;
    }

    public function sync($post, $account, $contact, $schoolFields, $contactFields) {
        $centreObject = new \stdClass();
        $contactObject = new \stdClass();

        $valid = isset($post['Centre']) || isset($post['Contact']);

        if ($valid && isset($post['Centre'])) {
            $centreObject->Id = $account['sf_id'];
            foreach ($post['Centre'] as $key => $value) {
                if ($value == '1' && array_key_exists($key, $this->schoolFields))  {
                    $centreObject->$key = $schoolFields[$key]['db'];
                }
            }
            try {
                $this->conn->update([$centreObject], 'Account');
            } catch (Exception $e) {
                $valid = false;
            }
        }

        if ($valid && isset($post['Contact'])) {
            $contactObject->Id = $contact['sf_id'];
            foreach ($post['Contact'] as $key => $value) {
                if ($value == '1' && array_key_exists($key, $this->contactFields)) {
                    $contactObject->$key = $contactFields[$key]['db'];
                }
            }
            try {
                $this->conn->update([$contactObject], 'Contact');
            } catch (Exception $e) {
                $valid = false;
            }
        }

        return $valid;
    }

    public function getContact($sf_id) {
        $contact = [];
        $queryFields = implode(", ",array_keys($this->contactFields));
        $response = $this->conn->query("SELECT " . $queryFields . " from Contact Where id = '" . $sf_id . "'");
        $valid = $this->queryOne($contact, $this->contactFields, $response);
        return ($valid) ? $contact : false;
    }

    public function accountExists($sf_id) {
        try {
            $response = $this->conn->query("SELECT id from Account Where id = '".$sf_id."'");
        } catch (Exception $e) {
            return false;
        }
        return (count($response->records) == 1);
    }

    public function getAccount($sf_id) {
        $account = [];
        $queryFields = implode(", ",array_keys($this->schoolFields));
        $response = $this->conn->query("SELECT " . $queryFields . " from Account Where id = '".$sf_id."'");
        $valid = $this->queryOne($account, $this->schoolFields, $response);
        return ($valid) ? $account : false;
    }

    function __construct($sf_username, $sf_password, $sf_token, $sf_school_record_type_id) {
        $this->sf_username = $sf_username;
        $this->sf_password = $sf_password;
        $this->sf_token = $sf_token;
        $this->sf_school_record_type_id = $sf_school_record_type_id;
        
        $this->conn = new \SforceEnterpriseClient();
        $this->conn->createConnection(Yii::getAlias('@vendor')."/gropaga/yii2-salesforce/enterprise.jsp.xml");
        $this->conn->login($this->sf_username, $this->sf_password.$this->sf_token);

        $valid = true;

        $valid = $this->setSalesforceAccount() && $valid;

        if ($valid) {
            $this->sf_parent_id = $this->salesforceAccountModel->sf_parent_id;
            $queryFields = implode(", ",array_keys($this->schoolFields));
            $response = $this->conn->query("SELECT " . $queryFields . " from Account Where id = '".$this->sf_parent_id."'");
            $valid = $this->query($this->parentSchool, $this->schoolFields, $response) && $valid;
        }

        if ($valid = isset($this->parentSchool[$this->sf_parent_id]['sf_contact_id']) && $valid) {
            $this->sf_contact_id = $this->parentSchool[$this->sf_parent_id]['sf_contact_id'];
        }

        if ($valid) {
            $queryFields = implode(", ",array_keys($this->contactFields));
            $response = $this->conn->query("SELECT " . $queryFields . " from Contact Where id = '".$this->sf_contact_id."'");
            $valid = $this->query($this->mainContact, $this->contactFields, $response) && $valid;
        }

        if ($valid) {
            $queryFields = implode(", ",array_keys($this->schoolFields));
            $associateFields = implode("' Or Membership__c = '",array_keys($this->associateFields));
            $response = $this->conn->query("
                SELECT $queryFields
                from Account
                Where ParentId = '" . $this->sf_parent_id . "'
                And RecordTypeId = '" . $this->sf_school_record_type_id . "'
                And School_Status__c = 'Current School'
                And (Membership__c = '" . $associateFields . "')
                Order By Membership__c
            ");
            $this->query($this->associateSchools, $this->schoolFields, $response) && $valid;
        }

        if ($valid) {
            $queryFields = implode(", ",array_keys($this->schoolFields));
            $seasonalFields = implode("' Or Membership__c = '",array_keys($this->seasonalFields));
            $response = $this->conn->query("
                SELECT $queryFields
                from Account
                Where ParentId = '" . $this->sf_parent_id . "'
                And RecordTypeId = '" . $this->sf_school_record_type_id . "'
                And School_Status__c = 'Current School'
                And (Membership__c = '" . $seasonalFields . "')
                Order By Membership__c
            ");
            $this->query($this->seasonalSchools, $this->schoolFields, $response) && $valid;
        }
        return $valid;
    }

    private function query(&$variable, $fieldsArray, $response) {
        $records = $response->records;
        foreach ($records as $record) {
            $variable[$record->Id] = [];
            foreach ($fieldsArray as $key=>$val) {
                $variable[$record->Id][$val] = (isset($record->$key)) ? $record->$key : null;
            }
        }
        return !empty($variable);
    }

    private function queryOne(&$variable, $fieldsArray, $response) {
        $records = $response->records;
        foreach ($records as $record) {
            $variable = [];
            foreach ($fieldsArray as $key=>$val) {
                $variable[$val] = (isset($record->$key)) ? $record->$key : null;
            }
        }
        return !empty($variable);
    }
}

