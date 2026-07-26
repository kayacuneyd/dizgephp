# Scheduled operations

Use the server cron definition at `/etc/cron.d/dizgecms` on the production host. All commands
run as `www-data`, so `storage/` must remain writable by that account.

| Schedule | Command | Purpose |
| --- | --- | --- |
| every minute | `php bin/dizge publish:due` | publishes approved content whose UTC schedule has arrived |
| 04:17 UTC daily | `php bin/cms-backup` | creates a retained SQLite snapshot before the daily cleanup |
| 04:29 UTC daily | `php bin/dizge cache:gc` | removes expired cache entries and rotates the audit log |

Check cron output after deployment:

```sh
tail -n 100 /var/log/dizgecms-cron.log
sudo -u www-data php /var/www/dizgecms/bin/dizge publish:due
```

Cron is not a substitute for release backups: `php bin/cms-upgrade --yes` always creates its own
pre-migration snapshot.
