<?php
class GpsrAttachment extends ObjectModel
{
    public $id_gpsr_attachment;
    public $name;
    public $description;
    public $file_original;
    public $file_saved;
    public $mime;
    public $size;
    public $active = 1;
    public $date_add;
    public $date_upd;

    public static $definition = [
        'table' => 'gpsr_attachment',
        'primary' => 'id_gpsr_attachment',
        'fields' => [
            'name'         => ['type'=>self::TYPE_STRING, 'validate'=>'isGenericName', 'required'=>true, 'size'=>255],
            'description'  => ['type'=>self::TYPE_HTML, 'validate'=>'isCleanHtml', 'required'=>false],
            'file_original'=> ['type'=>self::TYPE_STRING, 'validate'=>'isFileName', 'required'=>true, 'size'=>255],
            'file_saved'   => ['type'=>self::TYPE_STRING, 'validate'=>'isFileName', 'required'=>true, 'size'=>255],
            'mime'         => ['type'=>self::TYPE_STRING, 'required'=>false, 'size'=>128],
            'size'         => ['type'=>self::TYPE_INT, 'validate'=>'isUnsignedInt', 'required'=>false],
            'active'       => ['type'=>self::TYPE_BOOL, 'validate'=>'isBool', 'required'=>true],
            'date_add'     => ['type'=>self::TYPE_DATE],
            'date_upd'     => ['type'=>self::TYPE_DATE],
        ],
    ];

    /**
     * Usuń załącznik wraz z powiązaniami i plikiem na dysku.
     */
    public function delete()
    {
        // Usuń powiązania z produktami
        Db::getInstance()->execute('DELETE FROM `'.pSQL(_DB_PREFIX_).'gpsr_attachment_product` WHERE id_gpsr_attachment='.(int)$this->id);
        // Usuń reguły
        Db::getInstance()->execute('DELETE FROM `'.pSQL(_DB_PREFIX_).'gpsr_attachment_rule` WHERE id_gpsr_attachment='.(int)$this->id);

        // Usuń fizyczny plik (jeśli istnieje) – tolerancja obu nazw modułu
        if (!empty($this->file_saved)) {
            $base = rtrim(_PS_MODULE_DIR_, '/\\').DIRECTORY_SEPARATOR;
            $candidates = [
                $base.'prestadogpsrmanager/uploads/'.$this->file_saved,
                $base.'gpsrmanager/uploads/'.$this->file_saved,
            ];
            foreach ($candidates as $p) {
                if (is_file($p)) { @unlink($p); }
            }
        }

        return parent::delete();
    }
}

