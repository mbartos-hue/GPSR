<?php
if (!defined('_PS_VERSION_')) { exit; }

require_once _PS_MODULE_DIR_.'prestadogpsrmanager/classes/Service/AssignmentService.php';

class RuleService
{
    private $db;
    private $shopId;

    public function __construct($shopId = null)
    {
        $this->db = Db::getInstance();
        $this->shopId = $shopId ?: (int)Context::getContext()->shop->id;
    }

    /* ===================== PODMIOTY: CRUD / APPLY ===================== */

    public function createEntityRule(int $idEntity, string $type, int $idTarget, bool $includeChildren = true, ?int $shopId = null): int
    {
        $shopId = $shopId ?? $this->shopId;
        $ok = $this->db->insert('gpsr_entity_rule', [
            'id_gpsr_entity'  => (int)$idEntity,
            'rule_type'       => pSQL($type),            // 'category'|'manufacturer'|'supplier'
            'id_target'       => (int)$idTarget,         // id_category | id_manufacturer | id_supplier
            'include_children'=> (int)$includeChildren,  // tylko dla category
            'id_shop'         => $shopId,                // NULL = global
            'active'          => 1,
            'date_add'        => date('Y-m-d H:i:s'),
            'date_upd'        => date('Y-m-d H:i:s'),
        ], false, true, Db::INSERT_IGNORE);
        return $ok ? (int)$this->db->Insert_ID() : 0;
    }

    public function applyEntityRuleNow(int $idEntity, string $type, int $idTarget, bool $includeChildren, ?int $shopId = null): int
    {
        $as = new AssignmentService($shopId ?? $this->shopId);
        switch ($type) {
            case 'category':     $ids = $as->getProductIdsByCategory($idTarget, $includeChildren); break;
            case 'manufacturer': $ids = $as->getProductIdsByManufacturer($idTarget); break;
            case 'supplier':     $ids = $as->getProductIdsBySupplier($idTarget); break;
            default: return 0;
        }
        return $as->attachEntityToProducts($idEntity, $ids);
    }

    /** Zwraca ID podmiotów wynikających z reguł dla danego produktu */
    public function resolveEntitiesForProduct(int $idProduct, ?int $shopId = null): array
    {
        $shopId = $shopId ?? $this->shopId;
        $ids = [];

        // Manufacturer
        $rows = $this->db->executeS('
            SELECT DISTINCT r.id_gpsr_entity
            FROM `'._DB_PREFIX_.'gpsr_entity_rule` r
            INNER JOIN `'._DB_PREFIX_.'product` p ON (p.id_product='.(int)$idProduct.')
            WHERE r.active=1 AND r.rule_type="manufacturer"
              AND (r.id_shop IS NULL OR r.id_shop='.(int)$shopId.')
              AND r.id_target = p.id_manufacturer
        ');
        foreach ($rows ?: [] as $r) { $ids[] = (int)$r['id_gpsr_entity']; }

        // Supplier (domyślny)
        $rows = $this->db->executeS('
            SELECT DISTINCT r.id_gpsr_entity
            FROM `'._DB_PREFIX_.'gpsr_entity_rule` r
            INNER JOIN `'._DB_PREFIX_.'product` p ON (p.id_product='.(int)$idProduct.')
            WHERE r.active=1 AND r.rule_type="supplier"
              AND (r.id_shop IS NULL OR r.id_shop='.(int)$shopId.')
              AND r.id_target = p.id_supplier
        ');
        foreach ($rows ?: [] as $r) { $ids[] = (int)$r['id_gpsr_entity']; }

        // Category (z obsługą include_children)
        $rows = $this->db->executeS('
            SELECT DISTINCT r.id_gpsr_entity
            FROM `'._DB_PREFIX_.'gpsr_entity_rule` r
            INNER JOIN `'._DB_PREFIX_.'category` c_rule ON (c_rule.id_category=r.id_target)
            INNER JOIN `'._DB_PREFIX_.'category_product` cp ON (cp.id_product='.(int)$idProduct.')
            INNER JOIN `'._DB_PREFIX_.'category` c_prod ON (c_prod.id_category=cp.id_category)
            WHERE r.active=1 AND r.rule_type="category"
              AND (r.id_shop IS NULL OR r.id_shop='.(int)$shopId.')
              AND (
                   (r.include_children=1 AND c_rule.nleft <= c_prod.nleft AND c_rule.nright >= c_prod.nright)
                OR (r.include_children=0 AND r.id_target = c_prod.id_category)
              )
        ');
        foreach ($rows ?: [] as $r) { $ids[] = (int)$r['id_gpsr_entity']; }

        return array_values(array_unique(array_filter($ids)));
    }

    /* ===================== ZAŁĄCZNIKI: CRUD / APPLY ===================== */

    public function createAttachmentRule(int $idAttachment, string $type, int $idTarget, bool $includeChildren = true, ?int $shopId = null): int
    {
        $shopId = $shopId ?? $this->shopId;
        $ok = $this->db->insert('gpsr_attachment_rule', [
            'id_gpsr_attachment' => (int)$idAttachment,
            'rule_type'          => pSQL($type),
            'id_target'          => (int)$idTarget,
            'include_children'   => (int)$includeChildren,
            'id_shop'            => $shopId,
            'active'             => 1,
            'date_add'           => date('Y-m-d H:i:s'),
            'date_upd'           => date('Y-m-d H:i:s'),
        ], false, true, Db::INSERT_IGNORE);
        return $ok ? (int)$this->db->Insert_ID() : 0;
    }

    public function applyAttachmentRuleNow(int $idAttachment, string $type, int $idTarget, bool $includeChildren, ?int $shopId = null): int
    {
        $as = new AssignmentService($shopId ?? $this->shopId);
        switch ($type) {
            case 'category':     $ids = $as->getProductIdsByCategory($idTarget, $includeChildren); break;
            case 'manufacturer': $ids = $as->getProductIdsByManufacturer($idTarget); break;
            case 'supplier':     $ids = $as->getProductIdsBySupplier($idTarget); break;
            default: return 0;
        }
        return $as->attachAttachmentToProducts($idAttachment, $ids);
    }

    /** Zwraca ID załączników wynikających z reguł dla danego produktu */
    public function resolveAttachmentsForProduct(int $idProduct, ?int $shopId = null): array
    {
        $shopId = $shopId ?? $this->shopId;
        $ids = [];

        // Manufacturer
        $rows = $this->db->executeS('
            SELECT DISTINCT r.id_gpsr_attachment
            FROM `'._DB_PREFIX_.'gpsr_attachment_rule` r
            INNER JOIN `'._DB_PREFIX_.'product` p ON (p.id_product='.(int)$idProduct.')
            WHERE r.active=1 AND r.rule_type="manufacturer"
              AND (r.id_shop IS NULL OR r.id_shop='.(int)$shopId.')
              AND r.id_target = p.id_manufacturer
        ');
        foreach ($rows ?: [] as $r) { $ids[] = (int)$r['id_gpsr_attachment']; }

        // Supplier
        $rows = $this->db->executeS('
            SELECT DISTINCT r.id_gpsr_attachment
            FROM `'._DB_PREFIX_.'gpsr_attachment_rule` r
            INNER JOIN `'._DB_PREFIX_.'product` p ON (p.id_product='.(int)$idProduct.')
            WHERE r.active=1 AND r.rule_type="supplier"
              AND (r.id_shop IS NULL OR r.id_shop='.(int)$shopId.')
              AND r.id_target = p.id_supplier
        ');
        foreach ($rows ?: [] as $r) { $ids[] = (int)$r['id_gpsr_attachment']; }

        // Category
        $rows = $this->db->executeS('
            SELECT DISTINCT r.id_gpsr_attachment
            FROM `'._DB_PREFIX_.'gpsr_attachment_rule` r
            INNER JOIN `'._DB_PREFIX_.'category` c_rule ON (c_rule.id_category=r.id_target)
            INNER JOIN `'._DB_PREFIX_.'category_product` cp ON (cp.id_product='.(int)$idProduct.')
            INNER JOIN `'._DB_PREFIX_.'category` c_prod ON (c_prod.id_category=cp.id_category)
            WHERE r.active=1 AND r.rule_type="category"
              AND (r.id_shop IS NULL OR r.id_shop='.(int)$shopId.')
              AND (
                   (r.include_children=1 AND c_rule.nleft <= c_prod.nleft AND c_rule.nright >= c_prod.nright)
                OR (r.include_children=0 AND r.id_target = c_prod.id_category)
              )
        ');
        foreach ($rows ?: [] as $r) { $ids[] = (int)$r['id_gpsr_attachment']; }

        return array_values(array_unique(array_filter($ids)));
    }

    /* ===================== APPLY ALL FOR PRODUCT ===================== */

    /**
     * Zastosuj wszystkie aktywne reguły (podmioty + załączniki) dla pojedynczego produktu.
     * Zwraca liczby nowych przypięć (INSERT IGNORE -> liczy tylko nowe).
     */
    public function applyAllRulesForProduct(int $idProduct, ?int $shopId = null): array
    {
        $shopId = $shopId ?? $this->shopId;
        $as = new AssignmentService($shopId);

        $entities    = $this->resolveEntitiesForProduct($idProduct, $shopId);
        $attachments = $this->resolveAttachmentsForProduct($idProduct, $shopId);

        $addedEntities    = $entities ? $as->attachEntityToProductsList($entities, $idProduct, $shopId) : 0;
        $addedAttachments = $attachments ? $as->attachAttachmentToProductsList($attachments, $idProduct, $shopId) : 0;

        return ['entities' => $addedEntities, 'attachments' => $addedAttachments];
    }
}

