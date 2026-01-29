<?php

require_once _PS_MODULE_DIR_.'prestadogpsrmanager/classes/GpsrAttachment.php';
require_once _PS_MODULE_DIR_.'prestadogpsrmanager/classes/Service/AssignmentService.php';
require_once _PS_MODULE_DIR_.'prestadogpsrmanager/classes/Service/RuleService.php';

class AdminGpsrAttachmentsController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'gpsr_attachment';
        $this->className = 'GpsrAttachment';
        $this->identifier = 'id_gpsr_attachment';
        $this->bootstrap = true;
        parent::__construct();

        $this->fields_list = [
            'id_gpsr_attachment' => ['title'=>'ID', 'class'=>'fixed-width-xs'],
            'name'               => ['title'=>'Nazwa'],
            'file_original'      => ['title'=>'Plik'],
            'active'             => ['title'=>'Aktywne', 'type'=>'bool'],
            'date_add'           => ['title'=>'Dodano'],
        ];
        $this->addRowAction('edit');
        $this->addRowAction('assign'); // niestandardowy
        $this->addRowAction('delete');
        $this->_defaultOrderBy = 'id_gpsr_attachment';
        $this->_defaultOrderWay = 'DESC';

        $this->bulk_actions = [
            'delete' => [
                'text' => $this->l('Usuń zaznaczone'),
                'confirm' => $this->l('Czy na pewno chcesz usunąć zaznaczone załączniki? Operacja usunie również powiązania, reguły oraz pliki z dysku.'),
            ],
        ];
    }

    public function displayAssignLink($token, $id, $name = null)
    {
        $url = $this->context->link->getAdminLink('AdminGpsrAttachments').'&assign=1&id_gpsr_attachment='.(int)$id;
        return '<a class="btn btn-default" href="'.$url.'"><i class="icon-random"></i> Przypisywanie produktów</a>';
    }

    /** Formularz dodaj/edytuj (upload w processAdd/processUpdate) */
    public function renderForm()
    {
        /** @var GpsrAttachment $obj */
        $obj = $this->loadObject(true);
        $currentFile = '';
        if (Validate::isLoadedObject($obj) && $obj->file_original) {
            $currentFile = sprintf('<p>Aktualny plik: <strong>%s</strong></p>', Tools::safeOutput($obj->file_original));
        }

        $this->fields_form = [
            'legend'=>['title'=>'Dodaj/Edytuj załącznik'],
            'input'=>[
                ['type'=>'text','name'=>'name','label'=>'Nazwa','required'=>true],
                ['type'=>'textarea','autoload_rte'=>true,'name'=>'description','label'=>'Opis'],
                ['type'=>'file','name'=>'upload','label'=>'Plik (wgraj)'],
                ['type'=>'free','name'=>'current_file','label'=>'','desc'=>$currentFile],
                ['type'=>'switch','name'=>'active','label'=>'Aktywne','is_bool'=>true,'values'=>[
                    ['id'=>'active_on','value'=>1,'label'=>'Tak'],
                    ['id'=>'active_off','value'=>0,'label'=>'Nie'],
                ]],
            ],
            'submit'=>['title'=>'Zapisz']
        ];
        return parent::renderForm();
    }

    /** Upload NOWEGO pliku przy dodawaniu */
    public function processAdd()
    {
        $this->handleUploadOrFail(true); // true => wymagaj pliku
        return parent::processAdd();
    }

    /** Upload NOWEGO pliku przy edycji (opcjonalny) */
    public function processUpdate()
    {
        $this->handleUploadOrFail(false); // false => plik opcjonalny
        return parent::processUpdate();
    }

    private function handleUploadOrFail(bool $required): void
    {
        if (empty($_FILES['upload']) || (int)$_FILES['upload']['error'] === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                $this->errors[] = $this->l('Wgraj plik załącznika.');
            }
            return;
        }

        $f = $_FILES['upload'];
        if (!isset($f['tmp_name']) || !is_uploaded_file($f['tmp_name'])) {
            $this->errors[] = $this->l('Nieprawidłowy upload pliku.');
            return;
        }

        $max = Tools::getMaxUploadSize(); // respektuje php.ini
        if ((int)$f['size'] > $max) {
            $this->errors[] = sprintf($this->l('Plik jest zbyt duży. Maksymalny rozmiar: %s.'), Tools::formatBytes($max));
            return;
        }

        $allowedExt = ['pdf','doc','docx','xls','xlsx','txt','jpg','jpeg','png','gif'];
        $orig = $f['name'];
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            $this->errors[] = $this->l('Niedozwolone rozszerzenie pliku.');
            return;
        }

        $mime = function_exists('mime_content_type') ? @mime_content_type($f['tmp_name']) : $f['type'];
        $size = (int)$f['size'];

        // Nazwa bezpieczna i unikalna
        $safeOrig = preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
        $hashPart = sha1_file($f['tmp_name']).'_'.time();
        $saved    = $hashPart.'.'.$ext;

        $destDir = _PS_MODULE_DIR_.$this->module->name.'/uploads/';
        if (!is_dir($destDir)) { @mkdir($destDir, 0755, true); }
        $dest = $destDir.$saved;

        if (!@move_uploaded_file($f['tmp_name'], $dest)) {
            $this->errors[] = $this->l('Nie udało się zapisać pliku na serwerze.');
            return;
        }

        // Jeśli to update — usuń poprzedni plik
        if (Tools::isSubmit('submitAdd'.$this->table) && ($id = (int)Tools::getValue($this->identifier))) {
            $prev = new GpsrAttachment($id);
            if (Validate::isLoadedObject($prev) && $prev->file_saved) {
                $old = $destDir.$prev->file_saved;
                if (is_file($old)) { @unlink($old); }
            }
        }

        // Podmień POST, by ObjectModel zapisał metadane
        $_POST['file_original'] = $safeOrig;
        $_POST['file_saved']    = $saved;
        $_POST['mime']          = (string)$mime;
        $_POST['size']          = (int)$size;
    }

    /** Przełącznik widoku listy → przypisywanie */
    public function renderList()
    {
        if (Tools::getIsset('assign') && ($id = (int)Tools::getValue('id_gpsr_attachment'))) {
            return $this->renderAssign($id);
        }
        return parent::renderList();
    }

    /** Widok przypisywania produktów do Załącznika */
    private function renderAssign(int $idAttachment)
    {
        $attachment = new GpsrAttachment($idAttachment);
        if (!Validate::isLoadedObject($attachment)) {
            return $this->displayError('Załącznik nie istnieje.');
        }

        $idShop = (int)$this->context->shop->id;
        $idLang = (int)$this->context->language->id;

        // listy do selectów
        $categories = Db::getInstance()->executeS('
            SELECT c.id_category, cl.name
            FROM `'._DB_PREFIX_.'category` c
            INNER JOIN `'._DB_PREFIX_.'category_lang` cl ON (cl.id_category=c.id_category AND cl.id_lang='.(int)$idLang.')
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

        // przypięte produkty (paginacja)
        $page  = max(1, (int)Tools::getValue('p', 1));
        $limit = 100;
        $offset = ($page-1)*$limit;

        $as = new AssignmentService($idShop);
        $assigned = $as->getAssignedProductsForAttachment($idAttachment, $idLang, $idShop, $limit, $offset);
        $total = $as->countAssignedProductsForAttachment($idAttachment, $idShop);
        $pages = (int)ceil($total / $limit);

        $this->context->smarty->assign([
            'attachment'    => $attachment,
            'token'         => $this->token,
            'action_url'    => $this->context->link->getAdminLink('AdminGpsrAttachments').'&assign=1&id_gpsr_attachment='.$idAttachment,
            'categories'    => $categories,
            'manufacturers' => $manufacturers,
            'suppliers'     => $suppliers,
            'assigned'      => $assigned,
            'page'          => $page,
            'pages'         => max(1,$pages),
            'limit'         => $limit,
        ]);

    return $this->context->smarty->fetch(_PS_MODULE_DIR_.'prestadogpsrmanager/templates/admin/attachments/assign.tpl');
    }

    /** Akcje: masowo / indywidualnie / odpinanie */
    public function postProcess()
    {
        parent::postProcess();

        if (Tools::getIsset('assign') && ($idAttachment = (int)Tools::getValue('id_gpsr_attachment'))) {
            $idShop = (int)$this->context->shop->id;
            $as = new AssignmentService($idShop);
            $rs = new RuleService($idShop);

            // MASOWO
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
                                $attached = $as->attachAttachmentToProducts($idAttachment, $pids, $idShop);
                                break;
                            case 'manufacturer':
                                $pids = $as->getProductIdsByManufacturer($targetId, $idShop);
                                $attached = $as->attachAttachmentToProducts($idAttachment, $pids, $idShop);
                                break;
                            case 'supplier':
                                $pids = $as->getProductIdsBySupplier($targetId, $idShop);
                                $attached = $as->attachAttachmentToProducts($idAttachment, $pids, $idShop);
                                break;
                        }
                    }
                    if ($createRule) {
                        $rs->createAttachmentRule($idAttachment, $type, $targetId, $includeChildren, $idShop);
                    }
                    $this->confirmations[] = $attached
                        ? sprintf('Przypięto %d produktów (masowo).', (int)$attached)
                        : 'Zapisano ustawienia masowe.';
                } else {
                    $this->errors[] = 'Wybierz cel (kategoria/producent/dostawca).';
                }
            }

            // INDYWIDUALNIE
            if (Tools::isSubmit('submitAssignIndividual')) {
                $raw = (string)Tools::getValue('product_ids');
                $ids = $as->parseIdList($raw);
                if ($ids) {
                    $attached = $as->attachAttachmentToProducts($idAttachment, $ids, $idShop);
                    $this->confirmations[] = sprintf('Przypięto %d produktów (indywidualnie).', (int)$attached);
                } else {
                    $this->errors[] = 'Podaj poprawną listę ID produktów.';
                }
            }

            // ODPINANIE
            if (Tools::isSubmit('submitDetachSelected')) {
                $ids = array_map('intval', (array)Tools::getValue('detach_ids', []));
                if ($ids) {
                    $det = $as->detachAttachmentFromProducts($idAttachment, $ids, $idShop);
                    $this->confirmations[] = sprintf('Odłączono %d produktów.', (int)$det);
                } else {
                    $this->errors[] = 'Zaznacz produkty do odłączenia.';
                }
            }
        }
    }
}

