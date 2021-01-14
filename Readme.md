# Installing

First, you need to fill the base config file. Copy the config file:

`cp src/DN42/conf/dns42.php.dist src/DN42/conf/dns42.php`

Next, fill some important fields.

* Be sure to set the `$cfg['debug']` to false in production environments.
* If the 'debug' variable is set to false, fill the admins array to recieve email notifications on we errors.
* Fill the 'secret_key' with a good random string (at least 40 chars).
* Configure the url section. Otherwise, the URL will not be generated correctly.
* Connect and configure a database engine. Please use MySQL, PostgreSQL is maybe broken.

## For apache

Just point you apache DocumentRoot to the www folder

## Bootstrapping the database

To bootstrap the database, use the script `migrate.php` like this:

`php migrate.php --conf=DNS42/conf/dns42.php -a -d -i`

## DNS Network watching

There is one config, 'dn42_ping_method' which controlls what methods to use it can be either exec or socket. Socket method needs root privileges.

There is 3 main script network watchers, `run_ns_check.php`, `run_ping_check.php`, and `update_from_repo.sh`. `update_from_repo.sh` is used to create and update the GIT repo from DN42, next, it process and updates every change on it. The other 2 scripts should run regulary every 5 minutes to the the ping and NS checks. Also, you should update

The scripts `all_ns_checks.php` and `all_ping_checks.php` can be used to trigger NS and ping checks on every host.

## Free DNS service

To make work the DNS service work, you need a Rabbit MQ server to send async messages. Please configure the variables `amqp_dns_*` in the config file.

The dynamic part, needs a bind9 version at least 9.16. Add the following configs to your bind9 server:

`allow-new-zones yes;`

Also, you will need two bind key, one to control the dns zone add and removal, and another to use as dynamic update zone.

Here is an example config:

```
key "machine-name-key" {
        algorithm hmac-md5;
        secret "==A_SECRET==";
};

key "key_a" {
        algorithm hmac-sha256;
        secret "==A_SECRET==";
};

controls {
        inet 127.0.0.1 port 953
        allow { 127.0.0.1; }
        keys {
                "rndc-key"; // This is the default bind key, don't remove it
                "machine-name-key";
        };
};
```

Next, configure the `rndc_*` options in the main config file. If everything goes fine, run the script `process_dns.php` as a service. It will process the async messages and will update the bind server.

