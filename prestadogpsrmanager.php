<?php
if (!defined('_PS_VERSION_')) { exit; }

require_once _PS_MODULE_DIR_.'prestadogpsrmanager/classes/Service/AssignmentService.php';
require_once _PS_MODULE_DIR_.'prestadogpsrmanager/classes/Service/RuleService.php';

class Prestadogpsrmanager extends Module
{
    const CONF_HOOK = 'PRESTADOGPSR_HOOK'; // 'extra' | 'additional'

    public function __construct()
    {
        $this->name = 'prestadogpsrmanager';
    $this->version = '1.0.0';
        $this->author = 'Prestado';
        $this->tab = 'administration';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();

        // Samorejestracja niestandardowego hooka bez konieczności "klikania" upgrade'u
        if (Module::isInstalled($this->name)) {
            try { $this->registerHook('displayGpsrBlock'); } catch (\Exception $e) {}
        }
        $this->displayName = $this->l('Prestado - GPSR Manager');
        $this->description = $this->l('Podmioty i załączniki GPSR + przypisywanie do produktów (BO + FO).');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && $this->installDb()
            && $this->ensureUploadDir()
            && Configuration::updateValue(self::CONF_HOOK, 'extra')
            // BO
            && $this->registerHook('displayAdminProductsExtra')
            && $this->registerHook('actionObjectProductAddAfter')
            && $this->registerHook('actionObjectProductUpdateAfter')
            && $this->registerHook('actionProductSave')
            // FO
            && $this->registerHook('displayProductExtraContent')
            && $this->registerHook('displayProductAdditionalInfo')
            && $this->registerHook('header')
            // Custom hook do ręcznego wstawiania w tpl
            && $this->registerHook('displayGpsrBlock')
            // Taby (opcjonalnie – jeśli używasz starych motywów zakładkowych)
            // && $this->registerHook('displayProductTab')
            // && $this->registerHook('displayProductTabContent')
            && $this->installTabs();
    }

    public function uninstall()
    {
        $this->uninstallTabs();
        $this->uninstallDb();
        $this->removeUploadDir();
        Configuration::deleteByName(self::CONF_HOOK);
        return parent::uninstall();
    }

    /* ===================== INSTALL HELPERS ===================== */

    private function installDb(): bool
    {
        $sql = @file_get_contents(__DIR__.'/sql/install.sql');
        if ($sql === false) { return false; }
        $sql = str_replace('ps_', _DB_PREFIX_, $sql);
        $queries = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql)));
        foreach ($queries as $q) {
            if ($q !== '' && !Db::getInstance()->execute($q)) { return false; }
        }
        return true;
    }

    private function ensureUploadDir(): bool
    {
        $dir = _PS_MODULE_DIR_.$this->name.'/uploads/';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { return false; }
        $ht = $dir.'.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht, "Options -Indexes\n<IfModule mod_headers.c>\nHeader set X-Content-Type-Options nosniff\n</IfModule>\n");
        }
        return true;
    }

    private function removeUploadDir(): void
    {
        $dir = _PS_MODULE_DIR_.$this->name.'/uploads/';
        if (!is_dir($dir)) { return; }
        // Usuń pliki w katalogu uploads
        foreach (@scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $path = $dir.$f;
            if (is_file($path)) { @unlink($path); }
        }
        // Usuń .htaccess i katalog
        if (is_file($dir.'.htaccess')) { @unlink($dir.'.htaccess'); }
        @rmdir($dir);
    }

    private function uninstallDb(): void
    {
        // Kasuj tabele w bezpiecznej kolejności (najpierw zależne)
        $db = Db::getInstance();
        $pref = _DB_PREFIX_;
        $db->execute('DROP TABLE IF EXISTS `'.$pref.'gpsr_attachment_rule`');
        $db->execute('DROP TABLE IF EXISTS `'.$pref.'gpsr_entity_rule`');
        $db->execute('DROP TABLE IF EXISTS `'.$pref.'gpsr_attachment_product`');
        $db->execute('DROP TABLE IF EXISTS `'.$pref.'gpsr_entity_product`');
        $db->execute('DROP TABLE IF EXISTS `'.$pref.'gpsr_attachment`');
        $db->execute('DROP TABLE IF EXISTS `'.$pref.'gpsr_entity`');
    }

    private function installTabs(): bool
    {
        $parent = new Tab();
        $parent->class_name = 'AdminGpsr';
        $parent->module = $this->name;
        $parent->id_parent = (int)Tab::getIdFromClassName('AdminCatalog');
        foreach (Language::getLanguages(true) as $lang) {
            $parent->name[$lang['id_lang']] = 'GPSR';
        }
        if (!$parent->add()) { return false; }

        $children = [
            'AdminGpsrEntities'    => 'Podmioty',
            'AdminGpsrAttachments' => 'Załączniki',
        ];
        foreach ($children as $class => $label) {
            $tab = new Tab();
            $tab->class_name = $class;
            $tab->module = $this->name;
            $tab->id_parent = (int)$parent->id;
            foreach (Language::getLanguages(true) as $lang) {
                $tab->name[$lang['id_lang']] = $label;
            }
            if (!$tab->add()) { return false; }
        }
        return true;
    }

    private function uninstallTabs(): void
    {
        foreach (['AdminGpsrEntities','AdminGpsrAttachments','AdminGpsr'] as $class) {
            if ($id = (int)Tab::getIdFromClassName($class)) {
                $tab = new Tab($id); $tab->delete();
            }
        }
    }

    /* ===================== BO: karta w edycji produktu ===================== */

    public function hookDisplayAdminProductsExtra($params)
    {
        $idProduct = (int)($params['id_product'] ?? 0);
        $idShop    = (int)$this->context->shop->id;

        if ($idProduct <= 0) {
            return '<div class="panel"><h3>GPSR</h3><p>'.$this->l('Zapisz produkt, aby móc przypisywać Podmioty i Załączniki.').'</p></div>';
        }

        $this->context->smarty->assign([
            'id_product'            => $idProduct,
            'entities_all'          => $this->getAllEntities(),
            'attachments_all'       => $this->getAllAttachments(),
            'entities_assigned'     => $this->getEntitiesForProduct($idProduct, $idShop),
            'attachments_assigned'  => $this->getAttachmentsForProduct($idProduct, $idShop),
        ]);

        Hook::exec('displayGpsrAdminProductPanel', ['id_product' => $idProduct], null, true);

        return $this->fetch('module:'.$this->name.'/templates/admin/product_tab.tpl');
    }

    /** Kolejność: 1) reguły, 2) ręczne dodania, 3) ręczne odpięcia */
    public function hookActionProductSave($params)
    {
        $idProduct = (int)($params['id_product'] ?? 0);
        if ($idProduct <= 0) { return; }

        $idShop = (int)$this->context->shop->id;
        $as = new AssignmentService($idShop);
        $rs = new RuleService($idShop);

        // 1) auto-reguły
        $rs->applyAllRulesForProduct($idProduct, $idShop);

        // 2) ręcznie dodane
        $addEntities = array_values(array_unique(array_map('intval', (array)Tools::getValue('gpsr_add_entity_ids', []))));
        foreach ($addEntities as $idEntity) {
            if ($idEntity > 0) { $as->attachEntityToProducts((int)$idEntity, [$idProduct], $idShop); }
        }
        $addAttachments = array_values(array_unique(array_map('intval', (array)Tools::getValue('gpsr_add_attachment_ids', []))));
        foreach ($addAttachments as $idAtt) {
            if ($idAtt > 0) { $as->attachAttachmentToProducts((int)$idAtt, [$idProduct], $idShop); }
        }

        // 3) ręcznie do odpięcia
        $delEntities = array_values(array_unique(array_map('intval', (array)Tools::getValue('gpsr_remove_entity_ids', []))));
        if ($delEntities) { $as->detachEntityFromProductsList($delEntities, $idProduct, $idShop); }
        $delAttachments = array_values(array_unique(array_map('intval', (array)Tools::getValue('gpsr_remove_attachment_ids', []))));
        if ($delAttachments) { $as->detachAttachmentFromProductsList($delAttachments, $idProduct, $idShop); }
    }

    public function hookActionObjectProductAddAfter($params)  { $this->hookActionProductSave($params); }
    public function hookActionObjectProductUpdateAfter($params){ $this->hookActionProductSave($params); }

    /* ===================== FO: karta produktu ===================== */

    /** Dodajemy styl na froncie */
    public function hookHeader()
    {
        $this->context->controller->registerStylesheet(
            'module-'.$this->name.'-front',
            'modules/'.$this->name.'/views/css/front.css',
            ['media' => 'all', 'priority' => 150]
        );
    }

    /** Nowoczesny hook (PS 1.7/8) – zwraca ProductExtraContent[] */
    public function hookDisplayProductExtraContent($params)
    {
        // Pokaż tylko, jeśli wybrano ten hook w konfiguracji
        if (Configuration::get(self::CONF_HOOK) !== 'extra') {
            return [];
        }
        $html = $this->buildFrontBlockHtml($params);
        if (!$html) { return []; }

        if (class_exists('\\PrestaShop\\PrestaShop\\Core\\Product\\ProductExtraContent')) {
            $block = new \PrestaShop\PrestaShop\Core\Product\ProductExtraContent();
            $block->setTitle($this->l('Informacje GPSR'));
            $block->setContent($html);
            return [$block];
        }
        
        return []; // fallback zrobi AdditionalInfo
    }

    /** Fallback dla motywów Classic/niestandardowych */
    public function hookDisplayProductAdditionalInfo($params)
    {
        // Pokaż tylko, jeśli wybrano ten hook w konfiguracji
        if (Configuration::get(self::CONF_HOOK) !== 'additional') {
            return '';
        }
        return $this->buildFrontBlockHtml($params);
    }

    /**
     * Własny hook do ręcznego wstawiania bloku w dowolnym szablonie.
     * Użycie w .tpl: {hook h='displayGpsrBlock' mod='prestadogpsrmanager' product=$product}
     * Parametr product jest opcjonalny – jeśli nie podasz, moduł spróbuje użyć id_product z URL.
     */
    public function hookDisplayGpsrBlock($params)
    {
        return $this->buildFrontBlockHtml($params);
    }

    /** Wspólne renderowanie frontowego bloku */
    private function buildFrontBlockHtml(array $params)
    {
        $idProduct = $this->resolveProductId($params);
        if ($idProduct <= 0) { return ''; }

        $idShop = (int)$this->context->shop->id;

        $entities = $this->getEntitiesForProduct($idProduct, $idShop);
        $attachments = $this->getAttachmentsForProduct($idProduct, $idShop);
        $producer = null;
        $importer = null;
        $others = [];

        foreach ($entities as $e) {
            switch ((int)$e['entity_type']) {
                case 1: $producer = $e; break; // Producent
                case 2: $importer = $e; break; // Importer
                default: $others[] = $e; // Ogólny
            }
        }

        // Fallback: jeśli nie ma przypiętego Producenta, pokaż wbudowanego producenta Presta
        $coreManufacturer = null;
        if (!$producer) {
            $coreManufacturer = $this->getCoreManufacturerForProduct($idProduct);
        }

        // Jeśli nadal nie ma żadnych danych – nie wyświetlaj bloku
        if (!$producer && !$importer && !$coreManufacturer && !$attachments) {
            return '';
        }

        $this->context->smarty->assign([
            'gpsr_producer'         => $producer,
            'gpsr_importer'         => $importer,
            'gpsr_others'           => $others,
            'gpsr_core_manufacturer'=> $coreManufacturer,
            'gpsr_attachments'      => $attachments,
        ]);
        
        // mapuj id_załącznika -> URL pobrania (z id_product)
        $downloadLinks = [];
        foreach ($attachments as $row) {
            $downloadLinks[(int)$row['id_gpsr_attachment']] =
                $this->context->link->getModuleLink(
                    $this->name,
                    'download',
                    ['id_attachment' => (int)$row['id_gpsr_attachment'], 'id_product' => $idProduct],
                    null, null, (int)$this->context->shop->id
                );
        }

        $this->context->smarty->assign([
            'gpsr_producer'          => $producer,
            'gpsr_importer'          => $importer,
            'gpsr_others'            => $others,
            'gpsr_core_manufacturer' => $coreManufacturer,
            'gpsr_attachments'       => $attachments,
            'gpsr_download_links'    => $downloadLinks,
        ]);

        return $this->fetch('module:'.$this->name.'/templates/front/product_gpsr.tpl');
    }

    /** Różne motywy różnie przekazują produkt do hooków */
    private function resolveProductId(array $params): int
    {
        // 1) produkt jako tablica
        if (isset($params['product']) && is_array($params['product'])) {
            if (!empty($params['product']['id_product'])) {
                return (int)$params['product']['id_product'];
            }
            if (!empty($params['product']['id'])) {
                return (int)$params['product']['id'];
            }
        }
        // 2) produkt jako obiekt
        if (isset($params['product']) && is_object($params['product'])) {
            if (isset($params['product']->id_product)) {
                return (int)$params['product']->id_product;
            }
            if (isset($params['product']->id)) {
                return (int)$params['product']->id;
            }
        }
        // 3) fallback z GET
        return (int)Tools::getValue('id_product');
    }

    /* ===================== POMOCNICZE ZAPYTANIA ===================== */

    private function getEntitiesForProduct(int $idProduct, int $idShop): array
    {
        $sql = 'SELECT e.id_gpsr_entity, e.identifier, e.entity_type, e.name, e.country_code, e.street, e.postcode, e.city, e.email, e.phone
                FROM `'._DB_PREFIX_.'gpsr_entity` e
                INNER JOIN `'._DB_PREFIX_.'gpsr_entity_product` ep
                  ON ep.id_gpsr_entity=e.id_gpsr_entity
                WHERE ep.id_product='.(int)$idProduct.' AND (ep.id_shop IS NULL OR ep.id_shop='.(int)$idShop.')
                ORDER BY e.entity_type ASC, e.name ASC';
        return Db::getInstance()->executeS($sql) ?: [];
    }

    private function getAttachmentsForProduct(int $idProduct, int $idShop): array
    {
        $sql = 'SELECT a.id_gpsr_attachment, a.name, a.file_original
                FROM `'._DB_PREFIX_.'gpsr_attachment` a
                INNER JOIN `'._DB_PREFIX_.'gpsr_attachment_product` ap
                  ON ap.id_gpsr_attachment=a.id_gpsr_attachment
                WHERE ap.id_product='.(int)$idProduct.' AND (ap.id_shop IS NULL OR ap.id_shop='.(int)$idShop.')
                ORDER BY a.name ASC';
        return Db::getInstance()->executeS($sql) ?: [];
    }

    private function getAllEntities(): array
    {
        $rows = Db::getInstance()->executeS('
            SELECT id_gpsr_entity, identifier, entity_type, name
            FROM `'._DB_PREFIX_.'gpsr_entity`
            WHERE active=1
            ORDER BY name ASC
        ');
        return $rows ?: [];
    }

    private function getAllAttachments(): array
    {
        $rows = Db::getInstance()->executeS('
            SELECT id_gpsr_attachment, name, file_original
            FROM `'._DB_PREFIX_.'gpsr_attachment`
            WHERE active=1
            ORDER BY name ASC
        ');
        return $rows ?: [];
    }

    /** Wbudowany producent Presta (fallback) */
    private function getCoreManufacturerForProduct(int $idProduct): ?array
    {
        $row = Db::getInstance()->getRow('
            SELECT m.id_manufacturer, m.name
            FROM `'._DB_PREFIX_.'product` p
            INNER JOIN `'._DB_PREFIX_.'manufacturer` m ON (m.id_manufacturer=p.id_manufacturer)
            WHERE p.id_product='.(int)$idProduct
        );
        return $row ?: null;
    }

    /* ===================== KONFIGURACJA MODUŁU ===================== */
    public function getContent()
    {
        $out = '';
        if (Tools::isSubmit('submitPrestadogpsrmanagerConfig')) {
            $hook = Tools::getValue('gpsr_hook');
            if (!in_array($hook, ['extra','additional','custom'], true)) {
                $this->context->controller->errors[] = $this->l('Nieprawidłowy wybór hooka.');
            } else {
                Configuration::updateValue(self::CONF_HOOK, $hook);
                $this->context->controller->confirmations[] = $this->l('Zapisano ustawienia.');
            }
        }

        $this->context->smarty->assign([
            'current_hook' => Configuration::get(self::CONF_HOOK) ?: 'extra',
        ]);
        $out .= $this->context->smarty->fetch('module:'.$this->name.'/views/templates/admin/configure.tpl');
        return $out;
    }
}
