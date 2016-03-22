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
use \SforceEnterpriseClient;
use components\salesforce\SalesforceObject;

class Salesforce
{
    private $username;
    private $password;
    private $token;
    private $schoolRecordTypeId;

    private $sf;

    public function conn($user)
    {
        $this->sf = new \SforceEnterpriseClient();
        $this->sf->createConnection(Yii::getAlias('@components')."/salesforce/enterprise.jsp.xml");
        $this->sf->login($this->username, $this->password.$this->token);
        return new SalesforceObject($this->sf, $user, $this->schoolRecordTypeId);
    }

    public function setUsername($u) {
        $this->username = $u;
    }

    public function setPassword($p) {
        $this->password = $p;
    }

    public function setToken($t) {
        $this->token = $t;
    }

    public function setSchoolRecordTypeId($s) {
        $this->schoolRecordTypeId = $s;
    }
}