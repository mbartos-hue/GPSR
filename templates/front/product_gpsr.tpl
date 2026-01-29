{* Blok GPSR na produkcie (FO) *}
<div class="gpsr-block">
  {if $gpsr_producer || $gpsr_core_manufacturer}
    <div class="gpsr-section">
  <h4>{l s='Producent / Importer' d='Modules.Prestadogpsrmanager.Shop'}</h4>
      {if $gpsr_producer}
        <p><strong>{$gpsr_producer.name|escape}</strong></p>
        <p><strong>Ulica:</strong> {$gpsr_producer.street|escape}</p>
		<p><strong>Kod pocztowy:</strong> {$gpsr_producer.postcode|escape}</p>
		<p><strong>Miasto:</strong> {$gpsr_producer.city|escape}</p>
		<p><strong>Kraj:</strong> {$gpsr_producer.country_code|escape}</p>
  		{if $gpsr_producer.email}<p><strong>Email:</strong> {$gpsr_producer.email|escape}</p>{/if}
  		{if $gpsr_producer.phone}<p><strong>Telefon:</strong> {$gpsr_producer.phone|escape}</p>{/if}
      {else}
        <p><strong>{$gpsr_core_manufacturer.name|escape}</strong></p>
      {/if}
    </div>
  {/if}

  {if $gpsr_importer}
    <div class="gpsr-section">
  <h4>{l s='Importer' d='Modules.Prestadogpsrmanager.Shop'}</h4>
      <p><strong>{$gpsr_importer.name|escape}</strong></p>
      <p><strong>Ulica:</strong> {$gpsr_importer.street|escape}</p>
	  <p><strong>Kod pocztowy:</strong> {$gpsr_importer.postcode|escape}</p>
	  <p><strong>Miasto:</strong> {$gpsr_importer.city|escape}</p>
	  <p><strong>Kraj:</strong> {$gpsr_importer.country_code|escape}</p>
  {if $gpsr_importer.email}<p><strong>Email:</strong> {$gpsr_importer.email|escape}</p>{/if}
  {if $gpsr_importer.phone}<p><p><strong>Telefon:</strong> {$gpsr_importer.phone|escape}</p>{/if}
    </div>
  {/if}

  {if $gpsr_others && $gpsr_others|@count}
    <div class="gpsr-section">
  <h4>{l s='Podmiot odpowiedzialny' d='Modules.Prestadogpsrmanager.Shop'}</h4>
      {foreach $gpsr_others as $e}
        <div class="gpsr-entity">
          <p><strong>{$e.name|escape}</strong></p>
          <p><strong>Ulica:</strong> {$e.street|escape}</p>
		  <p><strong>Kod pocztowy:</strong> {$e.postcode|escape}</p>
		  <p><strong>Miasto:</strong> {$e.city|escape}</p>
		  <p><strong>Kraj:</strong> {$e.country_code|escape}</p>
          {if $e.email}<p><strong>Email:</strong> {$e.email|escape}</p>{/if}
          {if $e.phone}<p><p><p><strong>Telefon:</strong> {$e.phone|escape}</p>{/if}
        </div>
      {/foreach}
    </div>
  {/if}
  <br />

  {if $gpsr_attachments && $gpsr_attachments|@count}
    <div class="gpsr-section">
  <h4>{l s='Dokumenty' d='Modules.Prestadogpsrmanager.Shop'}</h4>
      <ul class="gpsr-list">
        {foreach $gpsr_attachments as $a}
          <li>
            {assign var=dl value=$gpsr_download_links[$a.id_gpsr_attachment]}
            <a href="{$dl|escape:'html':'UTF-8'}" rel="nofollow">
              {$a.name|escape}{if $a.file_original}{/if}
            </a>
          </li>
        {/foreach}
      </ul>
    </div>
  {/if}
</div>


