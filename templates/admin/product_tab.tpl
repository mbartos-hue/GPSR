{* Karta GPSR na stronie produktu *}
<div class="panel">
  <h3>GPSR</h3>
  <p class="help-block">
    Operacje poniżej zostaną zastosowane po kliknięciu <strong>Zapisz</strong> na produkcie.
  </p>

  {*
    PODMIOTY
  *}
  <fieldset style="margin-bottom:20px;">
    <legend>Podmioty</legend>

    <div class="row">
      <div class="col-lg-6">
        <label>Dodaj podmiot(y)</label>
        <select name="gpsr_add_entity_ids[]" class="form-control" multiple size="6">
          {foreach $entities_all as $e}
            {assign var=etype value=$e.entity_type|intval}
            {if $etype==0}{assign var=etype_label value='Ogólny'}{/if}
            {if $etype==1}{assign var=etype_label value='Producent'}{/if}
            {if $etype==2}{assign var=etype_label value='Importer'}{/if}
            <option value="{$e.id_gpsr_entity|intval}">
              [{$etype_label}] {$e.name|escape} — {$e.identifier|escape}
            </option>
          {/foreach}
        </select>
        <p class="help-block">Przytrzymaj Ctrl / Cmd, aby wybrać wiele.</p>
      </div>

      <div class="col-lg-6">
        <label>Aktualnie przypięte — zaznacz, by odpiąć</label>
        {if $entities_assigned|@count}
          <div class="well" style="max-height:180px; overflow:auto">
            {foreach $entities_assigned as $e}
              {assign var=etype value=$e.entity_type|intval}
              {if $etype==0}{assign var=etype_label value='Ogólny'}{/if}
              {if $etype==1}{assign var=etype_label value='Producent'}{/if}
              {if $etype==2}{assign var=etype_label value='Importer'}{/if}
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="gpsr_remove_entity_ids[]" value="{$e.id_gpsr_entity|intval}">
                  [{$etype_label}] {$e.name|escape} — {$e.identifier|escape}
                </label>
              </div>
            {/foreach}
          </div>
        {else}
          <p>Brak przypiętych podmiotów.</p>
        {/if}
      </div>
    </div>
  </fieldset>

  {*
    ZAŁĄCZNIKI
  *}
  <fieldset>
    <legend>Załączniki</legend>

    <div class="row">
      <div class="col-lg-6">
        <label>Dodaj załącznik(i)</label>
        <select name="gpsr_add_attachment_ids[]" class="form-control" multiple size="6">
          {foreach $attachments_all as $a}
            <option value="{$a.id_gpsr_attachment|intval}">
              {$a.name|escape}{if $a.file_original} — {$a.file_original|escape}{/if}
            </option>
          {/foreach}
        </select>
        <p class="help-block">Przytrzymaj Ctrl / Cmd, aby wybrać wiele.</p>
      </div>

      <div class="col-lg-6">
        <label>Aktualnie przypięte — zaznacz, by odpiąć</label>
        {if $attachments_assigned|@count}
          <div class="well" style="max-height:180px; overflow:auto">
            {foreach $attachments_assigned as $a}
              <div class="checkbox">
                <label>
                  <input type="checkbox" name="gpsr_remove_attachment_ids[]" value="{$a.id_gpsr_attachment|intval}">
                  {$a.name|escape}{if $a.file_original} — {$a.file_original|escape}{/if}
                </label>
              </div>
            {/foreach}
          </div>
        {else}
          <p>Brak przypiętych załączników.</p>
        {/if}
      </div>
    </div>
  </fieldset>
</div>

