<?php
if (!defined('_PS_VERSION_')) { exit; }

// Tolerancja na starą nazwę modułu (rename w trakcie wdrożenia)
$__modDir = rtrim(_PS_MODULE_DIR_, '/\\').DIRECTORY_SEPARATOR;
if (file_exists($__modDir.'prestadogpsrmanager/classes/GpsrAttachment.php')) {
    require_once $__modDir.'prestadogpsrmanager/classes/GpsrAttachment.php';
} elseif (file_exists($__modDir.'gpsrmanager/classes/GpsrAttachment.php')) {
    require_once $__modDir.'gpsrmanager/classes/GpsrAttachment.php';
}

class PrestadogpsrmanagerDownloadModuleFrontController extends ModuleFrontController
{
    public $ssl = true; // użyj HTTPS gdy dostępne

    public function postProcess()
    {
        $idAttachment = (int)Tools::getValue('id_attachment');
        $idProduct    = (int)Tools::getValue('id_product');
        $shopId       = (int)$this->context->shop->id;

        if ($idAttachment <= 0 || $idProduct <= 0) {
            header('HTTP/1.1 400 Bad Request'); exit('Nieprawidłowe żądanie');
        }

                        // Załącznik musi być przypięty do danego produktu (w tym sklepie)
                    $q = new DbQuery();
                    $q->select('COUNT(1)');
                        $q->from('gpsr_attachment_product');
                        $q->where('id_gpsr_attachment='.(int)$idAttachment);
                        $q->where('id_product='.(int)$idProduct);
                    $q->where('id_shop IS NULL OR id_shop='.(int)$shopId);

                        // Zaloguj finalne zapytanie (manualna rekonstrukcja dla wglądu)
                        $sqlLog = 'SELECT COUNT(1) FROM `'.pSQL(_DB_PREFIX_).'gpsr_attachment_product`'
                            .' WHERE id_gpsr_attachment='.(int)$idAttachment
                            .' AND id_product='.(int)$idProduct
                            .' AND (id_shop IS NULL OR id_shop='.(int)$shopId.')';
                        if (class_exists('PrestaShopLogger')) {
                            PrestaShopLogger::addLog('[GPSR DL] SQL check: '.$sqlLog, 1, null, 'prestadogpsrmanager', 0, true);
                        } else {
                            @error_log('[GPSR DL] SQL check: '.$sqlLog);
                        }

                        try {
                            $assigned = ((int)Db::getInstance()->getValue($q) > 0);
                        } catch (Exception $e) {
                            $msg = '[GPSR DL] SQL error: '.$e->getMessage().' | SQL: '.$sqlLog;
                            if (class_exists('PrestaShopLogger')) {
                                PrestaShopLogger::addLog($msg, 3, null, 'prestadogpsrmanager', 0, true);
                            } else {
                                @error_log($msg);
                            }
                            header('HTTP/1.1 500 Internal Server Error');
                            exit('Błąd pobierania (SQL). Szczegóły w logach.');
                        }

    $att = new GpsrAttachment($idAttachment);
    $filePath = _PS_MODULE_DIR_.$this->module->name.'/uploads/'.$att->file_saved;

        if (!$assigned || !Validate::isLoadedObject($att) || !$att->active || !is_file($filePath)) {
            header('HTTP/1.1 404 Not Found'); exit('Nie znaleziono');
        }

        $filename = $att->file_original ?: ('attachment-'.$idAttachment);
        $filename = str_replace(["\r","\n",'"'], ['','',''], $filename);
        $mime     = $att->mime ?: 'application/octet-stream';

        // Czyścimy bufory i serwujemy plik
        while (ob_get_level()) { @ob_end_clean(); }
        header('Content-Type: '.$mime);
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: '.(string)filesize($filePath));
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('X-Robots-Tag: noindex, nofollow', true);

        readfile($filePath);
        exit;
    }

    public function initContent() {} // brak szablonu
}

// Alias klasy dla zgodności wstecznej: jeśli sklep nadal oczekuje starej nazwy modułu
if (!class_exists('GpsrmanagerDownloadModuleFrontController')) {
    class GpsrmanagerDownloadModuleFrontController extends PrestadogpsrmanagerDownloadModuleFrontController {}
}

