# Monitoring with Nagios / Icinga
## Symfony side
With vrok/monitoring-bundle we can schedule a CLI command via cron
to execute every half hour:

```
2,32 * * * * www-data /usr/local/bin/php /var/www/html/bin/console monitor:send-alive-message
```

This command sends an email to the configured address with a specific subject.

.env.local or in environment:
```yaml
MONITOR_ADDRESS=serviceisalive@domain.tld
MONITOR_APP_NAME=hpo-backend.domain.tld
```

Email content with subject like "Service $MONITOR_APP_NAME is alive!",
(the integer timestamp is later used by the Icinga check to purge all but the 
newest message): 
```
Automatic message from HPO-API-Dev: The service is alive and can send emails
at 2020-09-06T09:02:01+02:00 (timestamp 1599375721)
```

## Icinga side

Create the check-script for Nagios/Icinga on a host that can access the mailserver
with the mailbox you send the monitoring mails to.
Adjust the mailserver & username & password, they are not shell arguments so we don't have
to store them on the Icinga server: 
```shell script
#!/bin/sh

# Alle alive-Nachrichten mit dem angegebenen Betreff löschen bis auf die letzte
# -s SUBJECT -s "$1" - mit diesem Betreff, nicht sämtliche Emails löschen, auch andere Services senden an die gleiche Adresse
# --capture-max "timestamp (\d+)" - nummerischen Wert extrahieren
# --capture-min thisdoesnotexist - notwendig um _keine_ minimale Nachricht zu fangen, diese würde sonst auch nicht gelöscht
# --no-delete-captured - wir wollen alte Nachrichten löschen damit das Postfach nicht volläuft (--delete ist automatisch an)
#   aber wir wollen die letzte behalten damit der Nagios-Check nicht fehlschlägt wenn er bspw. aller 30min läuft
#   daher mit --capture-max und --no-delete-captured die letzte Nachrichten behalten, den Rest (der zur Suche passenden) löschen
# dabei keine Ausgabe erzeugen (1>/dev/null) damit Icinga nur den Status des unteren checks liest
/usr/lib/nagios/plugins/check_imap_receive -H mail-server.domain.tld -U serviceisalive@domain.tld -P yourPassw0rd --tls -w 5 -c 10 --imap-retries 1 --search-critical-min 1 -s SUBJECT -s "$1" --capture-max "timestamp (\d+)" --capture-min thisdoesnotexist --nodelete-captured 1>/dev/null

# Pruefen ob in der letzten Stunde eine Nachricht mit dem angegbenen Betreff eingegangen ist:
# --imap-retries - nur einmal suchen statt 10x in 5sek-Intervallen
# -s YOUNGER -s 3600 = innerhalb der letzten Stunde
# -s SUBJECT -s "$1" mit diesem Betreff
/usr/lib/nagios/plugins/check_imap_receive -H mail-server.domain.tld -U serviceisalive@domain.tld -P yourPassw0rd --tls -w 5 -c 10 --imap-retries 1 --search-critical-min 1 -s YOUNGER -s 3600 -s SUBJECT -s "$1" --nodelete
```

Add the command definition in the Icinga master:
```
// Symfony service check - prueft auf eingegangene Mail mit angegebenem Betreff
object CheckCommand "check_service_alive" {
  command = [ PluginDir + "/contrib/check_service_alive" ]
  arguments = {
    "--subject" = {
      value = "$subject$"
      description = "email subject [substring] to search for"
      required = true
      skip_key = true
    }
  }
}
```

Also add the service definition:
```
// Symfony Service Check (innerhalb der letzten Stunde muss eine Mai mit dem angegebenem Betreff eingegangen sein)
apply Service for (name => subject in host.vars.service_alive) {
  check_command = "check_service_alive"
  check_interval = 30m
  display_name = name + " service alive"
  vars.subject = subject
  assign where host.vars.client_endpoint && host.vars.check_service_alive == true
  command_endpoint = host.vars.client_endpoint
}
```

Finally, enable & configure the service in your host definition:
```
    vars.check_service_alive = true
    vars.service_alive["HPO-Dev"] = "hpo-backend-dev.domain.tld is alive"
    vars.service_alive["HPO-Prod"] = "hpo-backend.domain.tld is alive"
```