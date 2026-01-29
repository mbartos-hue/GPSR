<div class="panel">
  <h3>Ustawienia modułu GPSR</h3>
  <form method="post" class="defaultForm form-horizontal">
    <div class="form-group">
      <label class="control-label col-lg-3">Miejsce wyświetlania na karcie produktu</label>
      <div class="col-lg-9">
        <div class="radio">
          <label>
            <input type="radio" name="gpsr_hook" value="extra" {if $current_hook=='extra'}checked{/if}>
            displayProductExtraContent (sekcja dodatkowa pod opisem)
          </label>
        </div>
        <div class="radio">
          <label>
            <input type="radio" name="gpsr_hook" value="additional" {if $current_hook=='additional'}checked{/if}>
            displayProductAdditionalInfo (blok obok/przy przyciskach)
          </label>
        </div>
        <div class="radio">
          <label>
            <input type="radio" name="gpsr_hook" value="custom" {if $current_hook=='custom'}checked{/if}>
            Ręczne wstawienie przez custom hook (brak automatycznego wyświetlania)
          </label>
        </div>
        <p class="help-block">Jeśli Twój motyw nie wspiera nowoczesnego hooka ExtraContent, użyj AdditionalInfo. Opcja "custom" wyłącza automatyczne wstawianie — wstawiasz blok samodzielnie w motywie:</p>
        <p class="help-block"><code>{ldelim}hook h='displayGpsrBlock' mod='prestadogpsrmanager' rdelim}</code></p>
        <p class="help-block">Lub z produktem w kontekście: <code>{ldelim}hook h='displayGpsrBlock' mod='prestadogpsrmanager' product=$product rdelim}</code></p>
      </div>
    </div>

    <div class="panel-footer">
      <button type="submit" name="submitPrestadogpsrmanagerConfig" class="btn btn-primary">
        <i class="process-icon-save"></i> Zapisz
      </button>
    </div>
  </form>
</div>

