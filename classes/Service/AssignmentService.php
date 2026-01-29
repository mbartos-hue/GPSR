<?php
if (!defined('_PS_VERSION_')) { exit; }

class AssignmentService
{
    private $db;
    private $shopId;

    public function __construct($shopId = null)
    {
        $this->db = Db::getInstance();
        $this->shopId = $shopId ?: (int)Context::getContext()->shop->id;
    }

    /** Podpięcie podmiotu do produktów (INSERT IGNORE) */
    public function attachEntityToProducts(int $idEntity, array $productIds, ?int $shopId = null): int
    {
        $shopId = $shopId ?? $this->shopId;
        $count = 0;
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        foreach (array_chunk($productIds, 500) as $chunk) {
            $values = [];
            foreach ($chunk as $pid) {
                $values[] = '('.(int)$idEntity.','.(int)$pid.','.(int)$shopId.')';
            }
            if (!$values) { continue; }
            $sql = 'INSERT IGNORE INTO `'._DB_PREFIX_.'gpsr_entity_product` (`id_gpsr_entity`,`id_product`,`id_shop`) VALUES '.implode(',', $values);
            if ($this->db->execute($sql)) {
                $count += $this->db->Affected_Rows();
            }
        }
        return $count;
    }

    /** Odpięcie podmiotu od produktów */
    public function detachEntityFromProducts(int $idEntity, array $productIds, ?int $shopId = null): int
    {
        $shopId = $shopId ?? $this->shopId;
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds) return 0;
        $in = implode(',', $productIds);
        $sql = 'DELETE FROM `'._DB_PREFIX_.'gpsr_entity_product`
                WHERE id_gpsr_entity='.(int)$idEntity.' AND id_shop='.(int)$shopId.' AND id_product IN ('.$in.')';
        $this->db->execute($sql);
        return $this->db->Affected_Rows();
    }

    /** Parser listy ID: "1,2,5-9,15" → [1,2,5,6,7,8,9,15] */
    public function parseIdList(string $input): array
    {
        $out = [];
        foreach (preg_split('/\s*,\s*/', trim($input)) as $tok) {
            if ($tok === '') continue;
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $tok, $m)) {
                $a = (int)$m[1]; $b = (int)$m[2];
                if ($a > $b) { [$a, $b] = [$b, $a]; }
                $out = array_merge($out, range($a, $b));
            } elseif (ctype_digit($tok)) {
                $out[] = (int)$tok;
            }
        }
        return array_values(array_unique($out));
    }

    /** Produkty wg kategorii (z opcją podkategorii) */
    public function getProductIdsByCategory(int $idCategory, bool $includeChildren = true, ?int $shopId = null): array
    {
        $shopId = $shopId ?? $this->shopId;
        $ids = [];
        if ($includeChildren) {
            $cat = $this->db->getRow('SELECT nleft, nright FROM `'._DB_PREFIX_.'category` WHERE id_category='.(int)$idCategory);
            if (!$cat) return [];
            $rows = $this->db->executeS('
                SELECT DISTINCT cp.id_product
                FROM `'._DB_PREFIX_.'category` c
                INNER JOIN `'._DB_PREFIX_.'category_product` cp ON (cp.id_category=c.id_category)
                INNER JOIN `'._DB_PREFIX_.'product_shop` ps ON (ps.id_product=cp.id_product AND ps.id_shop='.(int)$shopId.')
                WHERE c.nleft BETWEEN '.(int)$cat['nleft'].' AND '.(int)$cat['nright']
            );
        } else {
            $rows = $this->db->executeS('
                SELECT DISTINCT cp.id_product
                FROM `'._DB_PREFIX_.'category_product` cp
                INNER JOIN `'._DB_PREFIX_.'product_shop` ps ON (ps.id_product=cp.id_product AND ps.id_shop='.(int)$shopId.')
                WHERE cp.id_category='.(int)$idCategory
            );
        }
        foreach ($rows as $r) { $ids[] = (int)$r['id_product']; }
        return $ids;
    }

    /** Produkty wg producenta */
    public function getProductIdsByManufacturer(int $idManufacturer, ?int $shopId = null): array
    {
        $shopId = $shopId ?? $this->shopId;
        $rows = $this->db->executeS('
            SELECT DISTINCT p.id_product
            FROM `'._DB_PREFIX_.'product` p
            INNER JOIN `'._DB_PREFIX_.'product_shop` ps ON (ps.id_product=p.id_product AND ps.id_shop='.(int)$shopId.')
            WHERE p.id_manufacturer='.(int)$idManufacturer
        );
        return array_map('intval', array_column($rows, 'id_product'));
    }

    /** Produkty wg dostawcy (domyślny) */
    public function getProductIdsBySupplier(int $idSupplier, ?int $shopId = null): array
    {
        $shopId = $shopId ?? $this->shopId;
        $rows = $this->db->executeS('
            SELECT DISTINCT p.id_product
            FROM `'._DB_PREFIX_.'product` p
            INNER JOIN `'._DB_PREFIX_.'product_shop` ps ON (ps.id_product=p.id_product AND ps.id_shop='.(int)$shopId.')
            WHERE p.id_supplier='.(int)$idSupplier
        );
        return array_map('intval', array_column($rows, 'id_product'));
    }
    
    /*public function attachEntityToProducts(int $idEntity, array $productIds, ?int $shopId = null): int
    {
        $shopId = $shopId ?? $this->shopId;
        $count = 0;
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        foreach (array_chunk($productIds, 500) as $chunk) {
            if (!$chunk) continue;
            $values = [];
            foreach ($chunk as $pid) {
                $values[] = '('.(int)$idEntity.','.(int)$pid.','.(int)$shopId.')';
            }
            $sql = 'INSERT IGNORE INTO `'._DB_PREFIX_.'gpsr_entity_product` (`id_gpsr_entity`,`id_product`,`id_shop`) VALUES '.implode(',', $values);
            if ($this->db->execute($sql)) {
                $count += $this->db->Affected_Rows();
            }
        }
        return $count;
    }*/

    /*public function detachEntityFromProducts(int $idEntity, array $productIds, ?int $shopId = null): int
    {
        $shopId = $shopId ?? $this->shopId;
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds) return 0;
        $in = implode(',', $productIds);
        $sql = 'DELETE FROM `'._DB_PREFIX_.'gpsr_entity_product`
                WHERE id_gpsr_entity='.(int)$idEntity.' AND id_shop='.(int)$shopId.' AND id_product IN ('.$in.')';
        $this->db->execute($sql);
        return $this->db->Affected_Rows();
    }*/

    /** Lista przypiętych produktów dla podmiotu (do widoku) */
    public function getAssignedProductsForEntity(int $idEntity, int $langId, int $shopId, int $limit = 100, int $offset = 0): array
    {
        return $this->db->executeS('
            SELECT p.id_product, pl.name, p.reference
            FROM `'._DB_PREFIX_.'gpsr_entity_product` ep
            INNER JOIN `'._DB_PREFIX_.'product` p ON (p.id_product=ep.id_product)
            INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON (pl.id_product=p.id_product AND pl.id_lang='.(int)$langId.' AND pl.id_shop='.(int)$shopId.')
            WHERE ep.id_gpsr_entity='.(int)$idEntity.' AND ep.id_shop='.(int)$shopId.'
            ORDER BY p.id_product DESC
            LIMIT '.(int)$limit.' OFFSET '.(int)$offset
        );
    }

    public function countAssignedProductsForEntity(int $idEntity, int $shopId): int
    {
        return (int)$this->db->getValue('
            SELECT COUNT(*) FROM `'._DB_PREFIX_.'gpsr_entity_product`
            WHERE id_gpsr_entity='.(int)$idEntity.' AND id_shop='.(int)$shopId
        );
    }
    
    public function attachAttachmentToProducts(int $idAttachment, array $productIds, ?int $shopId = null): int
    {
        $shopId = $shopId ?? $this->shopId;
        $count = 0;
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        foreach (array_chunk($productIds, 500) as $chunk) {
            if (!$chunk) continue;
            $values = [];
            foreach ($chunk as $pid) {
                $values[] = '('.(int)$idAttachment.','.(int)$pid.','.(int)$shopId.')';
            }
            $sql = 'INSERT IGNORE INTO `'._DB_PREFIX_.'gpsr_attachment_product` (`id_gpsr_attachment`,`id_product`,`id_shop`) VALUES '.implode(',', $values);
            if ($this->db->execute($sql)) {
                $count += $this->db->Affected_Rows();
            }
        }
        return $count;
    }

    public function detachAttachmentFromProducts(int $idAttachment, array $productIds, ?int $shopId = null): int
    {
        $shopId = $shopId ?? $this->shopId;
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        if (!$productIds) return 0;
        $in = implode(',', $productIds);
        $sql = 'DELETE FROM `'._DB_PREFIX_.'gpsr_attachment_product`
                WHERE id_gpsr_attachment='.(int)$idAttachment.' AND id_shop='.(int)$shopId.' AND id_product IN ('.$in.')';
        $this->db->execute($sql);
        return $this->db->Affected_Rows();
    }

    public function getAssignedProductsForAttachment(int $idAttachment, int $langId, int $shopId, int $limit = 100, int $offset = 0): array
    {
        return $this->db->executeS('
            SELECT p.id_product, pl.name, p.reference
            FROM `'._DB_PREFIX_.'gpsr_attachment_product` ap
            INNER JOIN `'._DB_PREFIX_.'product` p ON (p.id_product=ap.id_product)
            INNER JOIN `'._DB_PREFIX_.'product_lang` pl ON (pl.id_product=p.id_product AND pl.id_lang='.(int)$langId.' AND pl.id_shop='.(int)$shopId.')
            WHERE ap.id_gpsr_attachment='.(int)$idAttachment.' AND ap.id_shop='.(int)$shopId.'
            ORDER BY p.id_product DESC
            LIMIT '.(int)$limit.' OFFSET '.(int)$offset
        );
    }

    public function countAssignedProductsForAttachment(int $idAttachment, int $shopId): int
    {
        return (int)$this->db->getValue('
            SELECT COUNT(*) FROM `'._DB_PREFIX_.'gpsr_attachment_product`
            WHERE id_gpsr_attachment='.(int)$idAttachment.' AND id_shop='.(int)$shopId
        );
    }
    
        /** Wygodnie: przypnij JEDEN produkt do wielu podmiotów */
    public function attachEntityToProductsList(array $entityIds, int $idProduct, ?int $shopId = null): int
    {
        $entityIds = array_values(array_unique(array_map('intval', $entityIds)));
        if (!$entityIds) return 0;
        $shopId = $shopId ?? $this->shopId;
        $count = 0;
        foreach (array_chunk($entityIds, 500) as $chunk) {
            $values = [];
            foreach ($chunk as $eid) {
                $values[] = '('.(int)$eid.','.(int)$idProduct.','.(int)$shopId.')';
            }
            $sql = 'INSERT IGNORE INTO `'._DB_PREFIX_.'gpsr_entity_product` (`id_gpsr_entity`,`id_product`,`id_shop`) VALUES '.implode(',', $values);
            if ($this->db->execute($sql)) { $count += $this->db->Affected_Rows(); }
        }
        return $count;
    }

    /** Wygodnie: przypnij JEDEN produkt do wielu załączników */
    public function attachAttachmentToProductsList(array $attachmentIds, int $idProduct, ?int $shopId = null): int
    {
        $attachmentIds = array_values(array_unique(array_map('intval', $attachmentIds)));
        if (!$attachmentIds) return 0;
        $shopId = $shopId ?? $this->shopId;
        $count = 0;
        foreach (array_chunk($attachmentIds, 500) as $chunk) {
            $values = [];
            foreach ($chunk as $aid) {
                $values[] = '('.(int)$aid.','.(int)$idProduct.','.(int)$shopId.')';
            }
            $sql = 'INSERT IGNORE INTO `'._DB_PREFIX_.'gpsr_attachment_product` (`id_gpsr_attachment`,`id_product`,`id_shop`) VALUES '.implode(',', $values);
            if ($this->db->execute($sql)) { $count += $this->db->Affected_Rows(); }
        }
        return $count;
    }
    
        public function detachEntityFromProductsList(array $entityIds, int $idProduct, ?int $shopId = null): int
    {
        $entityIds = array_values(array_unique(array_map('intval', $entityIds)));
        if (!$entityIds) return 0;
        $shopId = $shopId ?? $this->shopId;
        $in = implode(',', $entityIds);
        $sql = 'DELETE FROM `'._DB_PREFIX_.'gpsr_entity_product`
                WHERE id_product='.(int)$idProduct.' AND id_shop='.(int)$shopId.' AND id_gpsr_entity IN ('.$in.')';
        $this->db->execute($sql);
        return $this->db->Affected_Rows();
    }

    public function detachAttachmentFromProductsList(array $attachmentIds, int $idProduct, ?int $shopId = null): int
    {
        $attachmentIds = array_values(array_unique(array_map('intval', $attachmentIds)));
        if (!$attachmentIds) return 0;
        $shopId = $shopId ?? $this->shopId;
        $in = implode(',', $attachmentIds);
        $sql = 'DELETE FROM `'._DB_PREFIX_.'gpsr_attachment_product`
                WHERE id_product='.(int)$idProduct.' AND id_shop='.(int)$shopId.' AND id_gpsr_attachment IN ('.$in.')';
        $this->db->execute($sql);
        return $this->db->Affected_Rows();
    }


}

