{* Ignite service management page (WHMCS client area) — per service only. *}

<div class="ignite-reseller">
  {if $ignite.notice}
    <div class="alert alert-info">{$ignite.notice|escape}</div>
  {/if}

  {if $ignite.hasService}
    <h3>{$ignite.service.name|escape}
      {if $ignite.service.status}<span class="label label-info">{$ignite.service.status|escape}</span>{/if}
    </h3>

    <ul class="list-unstyled">
      {if $ignite.service.network.primaryIpv4}<li><strong>IPv4:</strong> {$ignite.service.network.primaryIpv4|escape}</li>{/if}
      {if $ignite.service.network.primaryIpv6}<li><strong>IPv6:</strong> {$ignite.service.network.primaryIpv6|escape}</li>{/if}
      {if $ignite.service.hostname}<li><strong>Hostname:</strong> {$ignite.service.hostname|escape}</li>{/if}
      {if $ignite.service.nodeName}<li><strong>Node:</strong> {$ignite.service.nodeName|escape}</li>{/if}
      {if $ignite.providerType}<li><strong>Typ:</strong> {$ignite.providerType|escape}</li>{/if}
    </ul>

    <h4>Energie-Aktionen</h4>
    <form method="post" action="clientarea.php?action=productdetails" class="form-inline" style="margin-bottom:20px">
      <input type="hidden" name="id" value="{$ignite_serviceid|escape}">
      <input type="hidden" name="modop" value="custom">
      <input type="hidden" name="a" value="Action">
      <input type="hidden" name="token" value="{$token}">
      <button class="btn btn-default" name="ignite_action" value="service.start">Starten</button>
      <button class="btn btn-default" name="ignite_action" value="service.restart">Neu starten</button>
      <button class="btn btn-default" name="ignite_action" value="service.stop">Stoppen</button>
      <button class="btn btn-default" name="ignite_action" value="service.reset">Reset</button>
    </form>

    {if $ignite.domainId}
      <h4>DNS – {$ignite.domainName|escape}</h4>
      <table class="table table-striped">
        <thead><tr><th>Name</th><th>Typ</th><th>Inhalt</th><th>TTL</th><th></th></tr></thead>
        <tbody>
          {foreach $ignite.dnsRecords as $record}
            <tr>
              <td>{$record.name|escape}</td>
              <td>{$record.type|escape}</td>
              <td>{$record.content|escape}</td>
              <td>{$record.ttl|escape}</td>
              <td>
                <form method="post" action="clientarea.php?action=productdetails" style="display:inline">
                  <input type="hidden" name="id" value="{$ignite_serviceid|escape}">
                  <input type="hidden" name="modop" value="custom">
                  <input type="hidden" name="a" value="Action">
                  <input type="hidden" name="token" value="{$token}">
                  <input type="hidden" name="ignite_action" value="dns.record.delete">
                  <input type="hidden" name="domain_id" value="{$ignite.domainId|escape}">
                  <input type="hidden" name="record_id" value="{$record.id|escape}">
                  <button class="btn btn-xs btn-danger">Löschen</button>
                </form>
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>

      <form method="post" action="clientarea.php?action=productdetails" class="form-inline">
        <input type="hidden" name="id" value="{$ignite_serviceid|escape}">
        <input type="hidden" name="modop" value="custom">
        <input type="hidden" name="a" value="Action">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="ignite_action" value="dns.record.create">
        <input type="hidden" name="domain_id" value="{$ignite.domainId|escape}">
        <input type="text" class="form-control" name="name" placeholder="Name (@ für Apex)">
        <input type="text" class="form-control" name="type" placeholder="A" size="4">
        <input type="text" class="form-control" name="content" placeholder="Inhalt">
        <input type="number" class="form-control" name="ttl" value="3600" size="5">
        <button class="btn btn-primary">Hinzufügen</button>
      </form>
    {/if}

    <h4>Reverse DNS (PTR)</h4>
    {if $ignite.ipEntries}
      <table class="table table-striped">
        <thead><tr><th>IP</th><th>Forward</th><th>Reverse (PTR)</th><th></th></tr></thead>
        <tbody>
          {foreach $ignite.ipEntries as $entry}
            <tr>
              <td>{$entry.ipAddress|escape}</td>
              <td>{$entry.forwardHostname|escape}</td>
              <td>
                <form method="post" action="clientarea.php?action=productdetails" class="form-inline">
                  <input type="hidden" name="id" value="{$ignite_serviceid|escape}">
                  <input type="hidden" name="modop" value="custom">
                  <input type="hidden" name="a" value="Action">
                  <input type="hidden" name="token" value="{$token}">
                  <input type="hidden" name="ignite_action" value="rdns.set">
                  <input type="hidden" name="entry_id" value="{$entry.id|escape}">
                  <input type="text" class="form-control" name="reverse_content" value="{$entry.reverseContent|escape}">
                  <button class="btn btn-xs btn-primary">Speichern</button>
                </form>
              </td>
              <td></td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    {else}
      <p class="text-muted">Keine IP-Einträge für diesen Service.</p>
    {/if}

    {if $ignite.providerType == 'mailcow'}
      <h4>E-Mail</h4>
      <form method="post" action="clientarea.php?action=productdetails" class="form-inline" style="margin-bottom:10px">
        <input type="hidden" name="id" value="{$ignite_serviceid|escape}">
        <input type="hidden" name="modop" value="custom">
        <input type="hidden" name="a" value="Action">
        <input type="hidden" name="token" value="{$token}">
        <button class="btn btn-default" name="ignite_action" value="mail.apply">DNS-Einträge anwenden</button>
        <button class="btn btn-default" name="ignite_action" value="mail.verify">DNS prüfen</button>
      </form>
    {/if}

    {if $ignite.providerType == 'plesk'}
      <h4>Plesk</h4>
      <form method="post" action="clientarea.php?action=productdetails" class="form-inline">
        <input type="hidden" name="id" value="{$ignite_serviceid|escape}">
        <input type="hidden" name="modop" value="custom">
        <input type="hidden" name="a" value="Action">
        <input type="hidden" name="token" value="{$token}">
        <button class="btn btn-default" name="ignite_action" value="plesk.rotate">Zugangsdaten neu erzeugen</button>
      </form>
    {/if}

    <h4>Backups</h4>
    {if $ignite.backups}
      <table class="table table-striped">
        <thead><tr><th>Erstellt</th><th>Größe</th><th>Status</th><th></th></tr></thead>
        <tbody>
          {foreach $ignite.backups as $backup}
            <tr>
              <td>{$backup.createdAt|escape}</td>
              <td>{$backup.size|escape}</td>
              <td>{$backup.status|escape}</td>
              <td>
                <form method="post" action="clientarea.php?action=productdetails" style="display:inline">
                  <input type="hidden" name="id" value="{$ignite_serviceid|escape}">
                  <input type="hidden" name="modop" value="custom">
                  <input type="hidden" name="a" value="Action">
                  <input type="hidden" name="token" value="{$token}">
                  <input type="hidden" name="ignite_action" value="backup.restore">
                  <input type="hidden" name="backup_id" value="{$backup.id|escape}">
                  <button class="btn btn-xs btn-default" onclick="return confirm('Backup wirklich wiederherstellen?');">Wiederherstellen</button>
                </form>
                <form method="post" action="clientarea.php?action=productdetails" style="display:inline">
                  <input type="hidden" name="id" value="{$ignite_serviceid|escape}">
                  <input type="hidden" name="modop" value="custom">
                  <input type="hidden" name="a" value="Action">
                  <input type="hidden" name="token" value="{$token}">
                  <input type="hidden" name="ignite_action" value="backup.delete">
                  <input type="hidden" name="backup_id" value="{$backup.id|escape}">
                  <button class="btn btn-xs btn-danger">Löschen</button>
                </form>
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    {/if}
    <form method="post" action="clientarea.php?action=productdetails" class="form-inline">
      <input type="hidden" name="id" value="{$ignite_serviceid|escape}">
      <input type="hidden" name="modop" value="custom">
      <input type="hidden" name="a" value="Action">
      <input type="hidden" name="token" value="{$token}">
      <button class="btn btn-default" name="ignite_action" value="backup.create">Backup erstellen</button>
    </form>
  {/if}
</div>
