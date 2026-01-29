{* Panel przypisywania produktów do Podmiotu *}
<div class="panel">
  <h3>Przypisywanie produktów — Podmiot: #{$entity->id} {$entity->name|escape:'htmlall':'UTF-8'}</h3>

  <form method="post" action="{$action_url|escape}">
    <input type="hidden" name="token" value="{$token|escape}">
    <input type="hidden" name="id_gpsr_entity" value="{$entity->id|intval}">
    <input type="hidden" name="assign" value="1">

    <fieldset style="margin-bottom:20px;">
      <legend>Masowo (kategoria/producent/dostawca)</legend>

      <div class="radio">
        <label><input type="radio" name="mass_type" value="category" checked> Kategoria</label>
        <label><input type="radio" name="mass_type" value="manufacturer"> Producent</label>
        <label><input type="radio" name="mass_type" value="supplier"> Dostawca</label>
      </div>

      <div class="form-group">
        <label>Kategoria</label>
        <select name="mass_category" class="form-control">
          <option value="0">— wybierz —</option>
          {foreach $categories as $c}
            <option value="{$c.id_category|intval}">{$c.name|escape}</option>
          {/foreach}
        </select>
        <div class="checkbox">
          <label><input type="checkbox" name="include_children" value="1" checked> Uwzględnij podkategorie</label>
        </div>
      </div>

      <div class="form-group">
        <label>Producent</label>
        <select name="mass_manufacturer" class="form-control">
          <option value="0">— wybierz —</option>
          {foreach $manufacturers as $m}
            <option value="{$m.id|intval}">{$m.name|escape}</option>
          {/foreach}
        </select>
      </div>

      <div class="form-group">
        <label>Dostawca</label>
        <select name="mass_supplier" class="form-control">
          <option value="0">— wybierz —</option>
          {foreach $suppliers as $s}
            <option value="{$s.id|intval}">{$s.name|escape}</option>
          {/foreach}
        </select>
      </div>

      <div class="checkbox">
        <label><input type="checkbox" name="apply_now" value="1" checked> Zastosuj teraz dla istniejących produktów</label>
      </div>
      <div class="checkbox">
        <label><input type="checkbox" name="create_rule" value="1" checked> Zapisz jako regułę dla przyszłych produktów</label>
      </div>

      <button class="btn btn-primary" name="submitAssignMass" value="1">
        <i class="icon-ok"></i> Zastosuj
      </button>
    </fieldset>

    <fieldset style="margin-bottom:20px;">
      <legend>Indywidualnie (lista ID)</legend>
      <div class="form-group">
        <label>ID produktów (np. 1,2,5-9,15)</label>
        <input type="text" name="product_ids" class="form-control" placeholder="np. 10,12,100-125">
      </div>
      <button class="btn btn-default" name="submitAssignIndividual" value="1">
        <i class="icon-plus"></i> Przypnij produkty
      </button>
    </fieldset>

    <fieldset>
      <legend>Aktualnie przypięte produkty</legend>

      {if $assigned|@count}
        <table class="table">
          <thead>
            <tr>
              <th style="width:40px"><input type="checkbox" onclick="$('input[name=\'detach_ids[]\']').prop('checked', this.checked)"></th>
              <th>ID</th>
              <th>Nazwa</th>
              <th>SKU</th>
            </tr>
          </thead>
          <tbody>
          {foreach $assigned as $row}
            <tr>
              <td><input type="checkbox" name="detach_ids[]" value="{$row.id_product|intval}"></td>
              <td>{$row.id_product|intval}</td>
              <td>{$row.name|escape}</td>
              <td>{$row.reference|escape}</td>
            </tr>
          {/foreach}
          </tbody>
        </table>

        <div class="clearfix">
          <div class="pull-left">
            <button class="btn btn-danger" name="submitDetachSelected" value="1">
              <i class="icon-trash"></i> Odłącz zaznaczone
            </button>
          </div>
          <div class="pull-right">
            <ul class="pagination">
              {section name=p start=1 loop=$pages+1}
                {if $smarty.section.p.index == $page}
                  <li class="active"><span>{$page}</span></li>
                {else}
                  <li><a href="{$action_url|escape}&p={$smarty.section.p.index}">{$smarty.section.p.index}</a></li>
                {/if}
              {/section}
            </ul>
          </div>
        </div>
      {else}
        <p>Brak przypiętych produktów.</p>
      {/if}
    </fieldset>
  </form>
</div>

