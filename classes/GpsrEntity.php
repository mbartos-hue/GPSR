<?php
class GpsrEntity extends ObjectModel
{
    public $id_gpsr_entity;
    public $identifier;
    public $entity_type; // 0,1,2
    public $name;
    public $country_code;
    public $street;
    public $postcode;
    public $city;
    public $email;
    public $phone;
    public $active = 1;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'gpsr_entity',
        'primary' => 'id_gpsr_entity',
        'fields' => [
            'identifier'    => ['type'=>self::TYPE_STRING, 'validate'=>'isGenericName', 'required'=>true, 'size'=>64],
            'entity_type'   => ['type'=>self::TYPE_INT, 'validate'=>'isUnsignedInt', 'required'=>true],
            'name'          => ['type'=>self::TYPE_STRING, 'validate'=>'isGenericName', 'required'=>true, 'size'=>255],
            'country_code'  => ['type'=>self::TYPE_STRING, 'validate'=>'isLanguageIsoCode', 'required'=>true, 'size'=>2],
            'street'        => ['type'=>self::TYPE_STRING, 'validate'=>'isAddress', 'required'=>true, 'size'=>255],
            'postcode'      => ['type'=>self::TYPE_STRING, 'validate'=>'isPostCode', 'required'=>true, 'size'=>32],
            'city'          => ['type'=>self::TYPE_STRING, 'validate'=>'isCityName', 'required'=>true, 'size'=>128],
            'email'         => ['type'=>self::TYPE_STRING, 'validate'=>'isEmail', 'required'=>false, 'size'=>255],
            'phone'         => ['type'=>self::TYPE_STRING, 'validate'=>'isPhoneNumber', 'required'=>false, 'size'=>64],
            'active'        => ['type'=>self::TYPE_BOOL, 'validate'=>'isBool', 'required'=>true],
            'date_add'      => ['type'=>self::TYPE_DATE],
            'date_upd'      => ['type'=>self::TYPE_DATE],
        ],
    ];

    /**
     * Usuń podmiot wraz z powiązaniami i regułami.
     */
    public function delete()
    {
        // Usuń powiązania z produktami
        Db::getInstance()->execute('DELETE FROM `'.pSQL(_DB_PREFIX_).'gpsr_entity_product` WHERE id_gpsr_entity='.(int)$this->id);
        // Usuń reguły
        Db::getInstance()->execute('DELETE FROM `'.pSQL(_DB_PREFIX_).'gpsr_entity_rule` WHERE id_gpsr_entity='.(int)$this->id);
        return parent::delete();
    }
}

