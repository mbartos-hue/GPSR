<?php

require_once _PS_MODULE_DIR_.'prestadogpsrmanager/classes/GpsrEntity.php';
require_once _PS_MODULE_DIR_.'prestadogpsrmanager/classes/Service/AssignmentService.php';
require_once _PS_MODULE_DIR_.'prestadogpsrmanager/classes/Service/RuleService.php';

class AdminGpsrEntitiesController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'gpsr_entity';
        $this->className = 'GpsrEntity';
        $this->identifier = 'id_gpsr_entity';
        $this->bootstrap = true;
        parent::__construct();

        $this->fields_list = [
            'id_gpsr_entity' => ['title'=>'ID', 'class'=>'fixed-width-xs'],
            'identifier'     => ['title'=>'Identyfikator'],
            'entity_type'    => ['title'=>'Rodzaj', 'callback'=>'renderType'],
            'name'           => ['title'=>'Nazwa'],
            'country_code'   => ['title'=>'Kraj'],
            'city'           => ['title'=>'Miejscowość'],
            'email'          => ['title'=>'Mail'],
            'phone'          => ['title'=>'Telefon'],
            'active'         => ['title'=>'Aktywne', 'active'=>'status', 'type'=>'bool'],
        ];
        $this->addRowAction('edit');
        $this->addRowAction('assign'); // niestandardowy przycisk
        $this->addRowAction('delete');

        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Usuń zaznaczone'),
                'confirm' => $this->l('Czy na pewno chcesz usunąć zaznaczone podmioty? Operacja usunie również powiązania i reguły.'),
            ],
        ];

        $this->_defaultOrderBy = 'id_gpsr_entity';
        $this->_defaultOrderWay = 'DESC';
    }

    public function renderType($value)
    {
        $map = [0=>'Ogólny',1=>'Producent',2=>'Importer'];
        return isset($map[(int)$value]) ? $map[(int)$value] : $value;
    }

    public function displayAssignLink($token, $id, $name = null)
    {
        $url = $this->context->link->getAdminLink('AdminGpsrEntities').'&assign=1&id_gpsr_entity='.(int)$id;
        return '<a class="btn btn-default" href="'.$url.'"><i class="icon-random"></i> Przypisywanie produktów</a>';
    }

    public function renderForm()
    {
        $this->fields_form = [
            'legend'=>['title'=>'Dodaj/Edytuj podmiot'],
            'input'=>[
                ['type'=>'text','name'=>'identifier','label'=>'Identyfikator','required'=>true],
                ['type'=>'select','name'=>'entity_type','label'=>'Rodzaj jednostki','required'=>true,
                    'options'=>['query'=>[
                        ['id'=>0,'name'=>'Ogólny'],['id'=>1,'name'=>'Producent'],['id'=>2,'name'=>'Importer']
                    ],'id'=>'id','name'=>'name']
                ],
                ['type'=>'text','name'=>'name','label'=>'Nazwa / Imię i nazwisko','required'=>true],
                ['type'=>'text','name'=>'country_code','label'=>'Kod kraju (ISO-2)','required'=>true],
                ['type'=>'text','name'=>'street','label'=>'Ulica i nr','required'=>true],
                ['type'=>'text','name'=>'postcode','label'=>'Kod pocztowy','required'=>true],
                ['type'=>'text','name'=>'city','label'=>'Miejscowość','required'=>true],
                ['type'=>'text','name'=>'email','label'=>'Mail'],
                ['type'=>'text','name'=>'phone','label'=>'Telefon'],
                ['type'=>'switch','name'=>'active','label'=>'Aktywne','is_bool'=>true,'values'=>[
                    ['id'=>'active_on','value'=>1,'label'=>'Tak'],
                    ['id'=>'active_off','value'=>0,'label'=>'Nie'],
                ]],
            ],
            'submit'=>['title'=>'Zapisz']
        ];
        return parent::renderForm();
    }

    public function renderList()
    {
        if (Tools::getIsset('assign') && ($id = (int)Tools::getValue('id_gpsr_entity'))) {
            return $this->renderAssign($id);
        }
        return parent::renderList();
    }

    private function renderAssign(int $idEntity)
    {
        $entity = new GpsrEntity($idEntity);
        if (!Validate::isLoadedObject($entity)) {
            return $this->displayError('Podmiot nie istnieje.');
        }

        $idShop = (int)$this->context->shop->id;
        $idLang = (int)$this->context->language->id;

        // listy do selectów
        $categories = Db::getInstance()->executeS('
            SELECT c.id_category, cl.name
            FROM `'._DB_PREFIX_.'category` c
            INNER JOIN `'._DB_PREFIX_.'category_lang` cl ON (cl.id_category=c.id_category AND cl.id_lang='.$idLang.')
            WHERE c.active=1 AND c.id_category NOT IN ('.(int)Configuration::get('PS_ROOT_CATEGORY').')
            ORDER BY cl.name ASC
            LIMIT 1000
        ');
        $manufacturers = Db::getInstance()->executeS('
            SELECT id_manufacturer as id, name FROM `'._DB_PREFIX_.'manufacturer` ORDER BY name ASC
        ');
        $suppliers = Db::getInstance()->executeS('
            SELECT id_supplier as id, name FROM `'._DB_PREFIX_.'supplier` ORDER BY name ASC
        ');

        // przypięte produkty (paginacja prosta)
        $page  = max(1, (int)Tools::getValue('p', 1));
        $limit = 100;
        $offset = ($page-1)*$limit;

        $as = new AssignmentService($idShop);
        $assigned = $as->getAssignedProductsForEntity($idEntity, $idLang, $idShop, $limit, $offset);
        $total = $as->countAssignedProductsForEntity($idEntity, $idShop);
        $pages = (int)ceil($total / $limit);

        $this->context->smarty->assign([
            'entity'        => $entity,
            'token'         => $this->token,
            'action_url'    => $this->context->link->getAdminLink('AdminGpsrEntities').'&assign=1&id_gpsr_entity='.$idEntity,
            'categories'    => $categories,
            'manufacturers' => $manufacturers,
            'suppliers'     => $suppliers,
            'assigned'      => $assigned,
            'page'          => $page,
            'pages'         => max(1,$pages),
            'limit'         => $limit,
        ]);

    return $this->context->smarty->fetch(_PS_MODULE_DIR_.'prestadogpsrmanager/templates/admin/entities/assign.tpl');
    }

    public function postProcess()
    {
        parent::postProcess();

        if (Tools::getIsset('assign') && ($idEntity = (int)Tools::getValue('id_gpsr_entity'))) {
            $idShop = (int)$this->context->shop->id;
            $as = new AssignmentService($idShop);
            $rs = new RuleService($idShop);

            // MASOWO (kategoria/producent/dostawca)
            if (Tools::isSubmit('submitAssignMass')) {
                $type = Tools::getValue('mass_type'); // category/manufacturer/supplier
                $includeChildren = (bool)Tools::getValue('include_children');
                $applyNow = (bool)Tools::getValue('apply_now');
                $createRule = (bool)Tools::getValue('create_rule');

                $targetId = 0;
                if ($type === 'category')     $targetId = (int)Tools::getValue('mass_category');
                if ($type === 'manufacturer') $targetId = (int)Tools::getValue('mass_manufacturer');
                if ($type === 'supplier')     $targetId = (int)Tools::getValue('mass_supplier');

                if ($targetId > 0) {
                    $attached = 0;
                    if ($applyNow) {
                        switch ($type) {
                            case 'category':
                                $pids = $as->getProductIdsByCategory($targetId, $includeChildren, $idShop);
                                $attached = $as->attachEntityToProducts($idEntity, $pids, $idShop);
                                break;
                            case 'manufacturer':
                                $pids = $as->getProductIdsByManufacturer($targetId, $idShop);
                                $attached = $as->attachEntityToProducts($idEntity, $pids, $idShop);
                                break;
                            case 'supplier':
                                $pids = $as->getProductIdsBySupplier($targetId, $idShop);
                                $attached = $as->attachEntityToProducts($idEntity, $pids, $idShop);
                                break;
                        }
                    }
                    if ($createRule) {
                        $rs->createEntityRule($idEntity, $type, $targetId, $includeChildren, $idShop);
                    }
                    if ($attached) {
                        $this->confirmations[] = sprintf('Przypięto %d produktów (masowo).', (int)$attached);
                    } else {
                        $this->confirmations[] = 'Zapisano ustawienia masowe.';
                    }
                } else {
                    $this->errors[] = 'Wybierz cel (kategoria/producent/dostawca).';
                }
            }

            // INDYWIDUALNIE (lista ID)
            if (Tools::isSubmit('submitAssignIndividual')) {
                $raw = (string)Tools::getValue('product_ids');
                $ids = $as->parseIdList($raw);
                if ($ids) {
                    $attached = $as->attachEntityToProducts($idEntity, $ids, $idShop);
                    $this->confirmations[] = sprintf('Przypięto %d produktów (indywidualnie).', (int)$attached);
                } else {
                    $this->errors[] = 'Podaj poprawną listę ID produktów.';
                }
            }

            // ODPINANIE
            if (Tools::isSubmit('submitDetachSelected')) {
                $ids = array_map('intval', (array)Tools::getValue('detach_ids', []));
                if ($ids) {
                    $det = $as->detachEntityFromProducts($idEntity, $ids, $idShop);
                    $this->confirmations[] = sprintf('Odłączono %d produktów.', (int)$det);
                } else {
                    $this->errors[] = 'Zaznacz produkty do odłączenia.';
                }
            }
        }
    }
}

